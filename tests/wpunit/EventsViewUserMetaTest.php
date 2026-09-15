<?php

use Simple_History\Services\REST_API;

/**
 * Issue 240: the event log view (Detailed or Compact) is stored per user in
 * user meta and saved through the core /wp/v2/users/me endpoint.
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

		// wp-browser unregisters all meta in tearDown, so re-register before each test.
		\Simple_History\Simple_History::get_instance()->get_service( REST_API::class )->register_user_meta();
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_meta_key_is_registered_for_rest_with_enum() {
		$keys = get_registered_meta_keys( 'user' );

		$this->assertArrayHasKey( REST_API::EVENTS_VIEW_USER_META_KEY, $keys );

		$args = $keys[ REST_API::EVENTS_VIEW_USER_META_KEY ];

		$this->assertTrue( $args['single'] );
		$this->assertSame( 'detailed', $args['default'] );
		$this->assertSame( array( 'detailed', 'compact' ), $args['show_in_rest']['schema']['enum'] );

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

	public function test_user_can_save_own_view() {
		wp_set_current_user( $this->subscriber_id );

		$response = $this->post_view( '/wp/v2/users/me', 'compact' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			'compact',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_invalid_value_is_rejected() {
		wp_set_current_user( $this->subscriber_id );

		$response = $this->post_view( '/wp/v2/users/me', 'table' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			'detailed',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_admin_cannot_write_another_users_view() {
		wp_set_current_user( $this->admin_id );

		$response = $this->post_view( "/wp/v2/users/{$this->subscriber_id}", 'compact' );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame(
			'detailed',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_saving_view_does_not_log_a_profile_edit() {
		wp_set_current_user( $this->admin_id );

		$count_before = $this->get_event_count();

		$response = $this->post_view( '/wp/v2/users/me', 'compact' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			$count_before,
			$this->get_event_count(),
			'Switching the event log view must not log a profile edit'
		);
	}

	/**
	 * @param string $route REST route.
	 * @param string $view  View value to save.
	 * @return WP_REST_Response
	 */
	private function post_view( $route, $view ) {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_body_params(
			array(
				'meta' => array(
					REST_API::EVENTS_VIEW_USER_META_KEY => $view,
				),
			)
		);

		return rest_do_request( $request );
	}

	/**
	 * @return int
	 */
	private function get_event_count() {
		global $wpdb;

		$table = \Simple_History\Simple_History::get_instance()->get_events_table_name();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
