<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use function Simple_History\tests\get_latest_context;

/**
 * A stand-in for Redirection's REST route object.
 *
 * `Plugin_Redirection_Logger` matches on the route callback's class name, and
 * `Redirection_Api_Redirect` is the pre-5.10 spelling — a class that no longer
 * exists anywhere in Redirection 5.10.0, so declaring it here collides with
 * nothing while still exercising the real matcher.
 *
 * `route_create` only has to return something: the point of these tests is what
 * the logger does around the callback, not what Redirection's own callback does.
 */
if ( ! class_exists( 'Redirection_Api_Redirect' ) ) {
	class Redirection_Api_Redirect {
		/** @var mixed Value the fake route callback returns. */
		public $return_value = null;

		/**
		 * Fake "create a redirect" route callback.
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return mixed
		 */
		public function route_create( $request ) {
			return $this->return_value;
		}
	}
}

/**
 * Tests that Plugin_Redirection_Logger only logs a Redirection REST request
 * that Redirection itself accepted.
 *
 * The logger used to do all of its work on `rest_request_before_callbacks`,
 * which WordPress runs *before* the route's `permission_callback` (see
 * `WP_REST_Server::respond_to_request()`). Any logged-in user could therefore
 * write false audit entries by POSTing to a Redirection route they had no
 * access to. Logging now happens on `rest_request_after_callbacks`, where a
 * rejected request is still visible as a `WP_Error` or an error status.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginRedirectionLoggerRequestGatingTest
 */
class PluginRedirectionLoggerRequestGatingTest extends \Codeception\TestCase\WPTestCase {
	use RedirectionTestTrait;
	const REST_NAMESPACE = 'sh-test-redirection/v1';

	/** @var Simple_History */
	private $sh;

	/** @var Redirection_Api_Redirect */
	private $route_object;

	/** @var bool Whether the registered route's permission callback grants access. */
	private $permission_granted = true;

	public function setUp(): void {
		parent::setUp();

		$this->skip_without_redirection();

		$this->sh           = Simple_History::get_instance();
		$this->route_object = new Redirection_Api_Redirect();

		$this->route_object->return_value = new WP_REST_Response( array( 'ok' => true ), 200 );

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		add_action( 'rest_api_init', array( $this, 'register_test_route' ) );

		// Force a fresh server so the route above is registered.
		global $wp_rest_server;
		$wp_rest_server = null;
		rest_get_server();
	}

	public function tearDown(): void {
		remove_action( 'rest_api_init', array( $this, 'register_test_route' ) );

		global $wp_rest_server;
		$wp_rest_server = null;

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Register a route that looks like Redirection's "create redirect" route
	 * to the logger's callable matcher.
	 */
	public function register_test_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/redirect',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->route_object, 'route_create' ),
				'permission_callback' => function () {
					return $this->permission_granted;
				},
			)
		);
	}

	public function test_successful_request_is_logged_once() {
		$count_before = $this->get_redirection_logger_event_count();

		$response = $this->dispatch_create_request();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $count_before + 1, $this->get_redirection_logger_event_count() );

		$context = get_latest_context();
		$this->assertSame( 'redirection_redirection_added', $this->get_context_value( $context, '_message_key' ) );
		$this->assertSame( '/from-url/', $this->get_context_value( $context, 'source_url' ) );
		$this->assertSame( '/to-url/', $this->get_context_value( $context, 'target_url' ) );
	}

	public function test_request_rejected_by_the_permission_callback_is_not_logged() {
		$this->permission_granted = false;

		$count_before = $this->get_redirection_logger_event_count();

		$response = $this->dispatch_create_request();

		// 403 rather than 401: rest_authorization_required_code() returns
		// "forbidden" once someone is logged in, and this test is an admin.
		$this->assertSame( 403, $response->get_status(), 'A denied permission callback should not reach the route callback' );
		$this->assertSame( $count_before, $this->get_redirection_logger_event_count() );
	}

	public function test_request_whose_callback_returns_an_error_status_is_not_logged() {
		$this->route_object->return_value = new WP_REST_Response( array( 'error' => 'nope' ), 403 );

		$count_before = $this->get_redirection_logger_event_count();

		$response = $this->dispatch_create_request();

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( $count_before, $this->get_redirection_logger_event_count() );
	}

	public function test_request_whose_callback_returns_a_wp_error_is_not_logged() {
		$this->route_object->return_value = new WP_Error( 'redirect_create_failed', 'Invalid redirect', array( 'status' => 400 ) );

		$count_before = $this->get_redirection_logger_event_count();

		$response = $this->dispatch_create_request();

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( $count_before, $this->get_redirection_logger_event_count() );
	}

	/**
	 * POST the fake "create redirect" request through the real REST server, so
	 * the before/after filters run in the order WordPress runs them.
	 *
	 * @return \WP_REST_Response
	 */
	private function dispatch_create_request() {
		$request = new WP_REST_Request( 'POST', '/' . self::REST_NAMESPACE . '/redirect' );

		$request->set_body_params(
			array(
				'url'         => '/from-url/',
				'action_data' => array( 'url' => '/to-url/' ),
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Number of events logged by the Redirection logger so far.
	 *
	 * @return int
	 */
	private function get_redirection_logger_event_count(): int {
		global $wpdb;

		$db_table = $this->sh->get_events_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$db_table} WHERE logger = %s",
				'Plugin_Redirection'
			)
		);
	}

	/**
	 * Pick one value out of the rows returned by get_latest_context().
	 *
	 * @param array<int, array<string, string>> $context Context rows.
	 * @param string                            $key     Context key.
	 * @return string|null
	 */
	private function get_context_value( array $context, $key ) {
		foreach ( $context as $context_row ) {
			if ( $context_row['key'] === $key ) {
				return $context_row['value'];
			}
		}

		return null;
	}
}
