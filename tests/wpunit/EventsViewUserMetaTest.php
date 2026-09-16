<?php

use Simple_History\Services\REST_API;

/**
 * Issue 240: the event log view (Detailed or Compact) is stored per user in
 * user meta and saved through the dedicated POST /simple-history/v1/events-view
 * route, not the core /wp/v2/users/me endpoint. See the final review fix
 * brief (F1) for why: /wp/v2/users/me always runs wp_update_user(), which
 * fires `profile_update`, and third-party plugins act on that hook for
 * things that are not this.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit EventsViewUserMetaTest
 */
class EventsViewUserMetaTest extends \Codeception\TestCase\WPTestCase {
	/** @var int */
	private $subscriber_id;

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );

		// rest_do_request() doesn't define REST_REQUEST in the test environment.
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		// wp-browser's tear_down() calls unregister_all_meta_keys(), so
		// re-register before each test. The REST route itself does not need
		// the same treatment: nothing in wp-browser or the core test suite
		// tears down $wp_rest_server or its registered routes between tests,
		// so once rest_api_init has fired once for the process (the first
		// rest_do_request() call anywhere in the suite triggers it, since
		// rest_get_server() creates $wp_rest_server lazily and caches it),
		// every route registered by REST_API::register_routes() — including
		// /simple-history/v1/events-view — stays registered for every later
		// test and test class in the same run. Confirmed by running this
		// class both alone and after other wpunit suites that make REST
		// requests first: the route is present either way. So, unlike meta,
		// no re-registration is needed here.
		\Simple_History\Simple_History::get_instance()->get_service( REST_API::class )->register_user_meta();
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_meta_key_is_registered_without_show_in_rest() {
		$keys = get_registered_meta_keys( 'user' );

		$this->assertArrayHasKey( REST_API::EVENTS_VIEW_USER_META_KEY, $keys );

		$args = $keys[ REST_API::EVENTS_VIEW_USER_META_KEY ];

		$this->assertTrue( $args['single'] );
		$this->assertSame( 'detailed', $args['default'] );

		// Not exposed in REST: register_meta() fills in a default `false` when
		// show_in_rest isn't passed, and it must not show up in /wp/v2/users
		// responses (see test_users_endpoint_does_not_expose_the_view_meta).
		$this->assertFalse( $args['show_in_rest'] );

		// Verify production wires it on init.
		$service = \Simple_History\Simple_History::get_instance()->get_service( REST_API::class );
		$this->assertNotFalse( has_action( 'init', [ $service, 'register_user_meta' ] ) );
	}

	public function test_default_is_detailed() {
		$this->assertSame(
			'detailed',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_user_with_capability_can_save_compact_view() {
		wp_set_current_user( $this->admin_id );

		$response = $this->post_view( 'compact' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			'compact',
			get_user_meta( $this->admin_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_invalid_value_is_rejected() {
		wp_set_current_user( $this->admin_id );

		$response = $this->post_view( 'table' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			'detailed',
			get_user_meta( $this->admin_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_user_without_capability_is_forbidden() {
		wp_set_current_user( $this->subscriber_id );

		$response = $this->post_view( 'compact' );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame(
			'detailed',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_logged_out_user_is_unauthorized() {
		wp_set_current_user( 0 );

		$response = $this->post_view( 'compact' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame(
			'detailed',
			get_user_meta( $this->admin_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_saving_view_does_not_fire_profile_update() {
		wp_set_current_user( $this->admin_id );

		$count_before = did_action( 'profile_update' );

		$response = $this->post_view( 'compact' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			$count_before,
			did_action( 'profile_update' ),
			'Switching the event log view must not fire profile_update'
		);
	}

	public function test_users_endpoint_does_not_expose_the_view_meta() {
		wp_set_current_user( $this->admin_id );

		update_user_meta( $this->admin_id, REST_API::EVENTS_VIEW_USER_META_KEY, 'compact' );

		$request  = new WP_REST_Request( 'GET', "/wp/v2/users/{$this->admin_id}" );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( REST_API::EVENTS_VIEW_USER_META_KEY, $data['meta'] ?? [] );
	}

	/**
	 * @param string $view View value to save.
	 * @return WP_REST_Response
	 */
	private function post_view( $view ) {
		$request = new WP_REST_Request( 'POST', '/simple-history/v1/events-view' );
		$request->set_body_params( array( 'view' => $view ) );

		return rest_do_request( $request );
	}
}
