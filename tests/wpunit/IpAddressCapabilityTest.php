<?php

use Simple_History\Helpers;
use Simple_History\Log_Query;

/**
 * Test that IP addresses are only shown to users with the view IP address capability.
 *
 * Reading the log takes edit_pages (an Editor), seeing IP addresses takes
 * manage_options. Every output path must agree, or the address leaks through
 * whichever one forgot.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit IpAddressCapabilityTest
 */
class IpAddressCapabilityTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Log an event from a known address, as an admin.
	 *
	 * SimpleLogger, because Editors can read it. Editors can't read the user
	 * logger at all, so its failed logins were never the exposure: the
	 * addresses on post edits and the like, served through context, were.
	 */
	private function log_event_with_ip_addresses() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info(
			'Page updated',
			[
				'_server_remote_addr'            => '198.51.100.x',
				'_server_http_x_forwarded_for_0' => '203.0.113.x',
				'post_title'                     => 'Hello',
			]
		);
	}

	public function setUp(): void {
		parent::setUp();

		// Show addresses for every event, so an empty ip_addresses field or
		// row header can only come from the capability.
		add_filter( 'simple_history/row_header_output/display_ip_address', '__return_true' );
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/row_header_output/display_ip_address', '__return_true' );

		parent::tearDown();
	}

	/**
	 * Fetch the latest event through the REST API.
	 *
	 * @return array REST response data for the event.
	 */
	private function get_latest_rest_event() {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'per_page', 1 );
		$request->set_param( '_fields', 'id,context,ip_addresses' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		return $response->get_data()[0];
	}

	/**
	 * Count events found through the REST API with the given query params.
	 *
	 * @param array $params Query params.
	 * @return int
	 */
	private function count_rest_events( $params ) {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( '_fields', 'id' );
		$request->set_param( 'per_page', 100 );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		return count( $response->get_data() );
	}

	public function test_capability_defaults_to_manage_options_and_is_filterable() {
		$this->assertSame( 'manage_options', Helpers::get_view_ip_address_capability() );

		$filter = fn() => 'edit_pages';
		add_filter( 'simple_history/view_ip_address_capability', $filter );

		$this->assertSame( 'edit_pages', Helpers::get_view_ip_address_capability() );

		remove_filter( 'simple_history/view_ip_address_capability', $filter );
	}

	public function test_is_ip_address_context_key() {
		$this->assertTrue( Helpers::is_ip_address_context_key( '_server_remote_addr' ) );
		$this->assertTrue( Helpers::is_ip_address_context_key( '_server_http_x_forwarded_for_0' ) );
		$this->assertTrue( Helpers::is_ip_address_context_key( '_cli_ssh_client_ip' ) );
		$this->assertTrue( Helpers::is_ip_address_context_key( '_cli_process_user' ) );

		// Share the "_server_http_" prefix but hold no address.
		$this->assertFalse( Helpers::is_ip_address_context_key( '_server_http_referer' ) );
		$this->assertFalse( Helpers::is_ip_address_context_key( '_server_http_user_agent' ) );
		$this->assertFalse( Helpers::is_ip_address_context_key( '_cli_command' ) );
		$this->assertFalse( Helpers::is_ip_address_context_key( '_user_id' ) );
	}

	public function test_admin_sees_ip_addresses_in_rest_response() {
		$this->log_event_with_ip_addresses();

		$event = $this->get_latest_rest_event();

		$this->assertSame( '198.51.100.x', $event['context']['_server_remote_addr'] );
		$this->assertSame( '203.0.113.x', $event['context']['_server_http_x_forwarded_for_0'] );
		$this->assertSame( '198.51.100.x', $event['ip_addresses']['_server_remote_addr'] );
		$this->assertSame( '203.0.113.x', $event['ip_addresses']['_server_http_x_forwarded_for_0'] );
	}

	public function test_editor_gets_no_ip_addresses_in_rest_response() {
		$this->log_event_with_ip_addresses();

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$event = $this->get_latest_rest_event();

		$this->assertArrayNotHasKey( '_server_remote_addr', $event['context'] );
		$this->assertArrayNotHasKey( '_server_http_x_forwarded_for_0', $event['context'] );
		$this->assertSame( [], (array) $event['ip_addresses'] );

		// The rest of the context is still there.
		$this->assertSame( 'Hello', $event['context']['post_title'] );
	}

	public function test_editor_sees_ip_addresses_when_capability_is_filtered() {
		$this->log_event_with_ip_addresses();

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$filter = fn() => 'edit_pages';
		add_filter( 'simple_history/view_ip_address_capability', $filter );

		$event = $this->get_latest_rest_event();

		remove_filter( 'simple_history/view_ip_address_capability', $filter );

		$this->assertSame( '198.51.100.x', $event['context']['_server_remote_addr'] );
		$this->assertSame( '198.51.100.x', $event['ip_addresses']['_server_remote_addr'] );
	}

	public function test_row_header_hides_ip_address_from_editor() {
		$this->log_event_with_ip_addresses();

		$row    = ( new Log_Query() )->query( [ 'posts_per_page' => 1 ] )['log_rows'][0];
		$logger = Simple_History\Simple_History::get_instance()->get_instantiated_logger_by_slug( $row->logger );

		$this->assertStringContainsString( '198.51.100.x', $logger->get_log_row_header_ip_address_output( $row ) );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame( '', $logger->get_log_row_header_ip_address_output( $row ) );
	}

	public function test_editor_cannot_search_or_filter_on_ip_addresses() {
		$this->log_event_with_ip_addresses();

		$filter_by_ip     = [ 'ip_address' => '198.51.100.x' ];
		$filter_by_header = [ 'ip_address' => '203.0.113.x' ];
		$search_ip        = [ 'metadata_search' => '198.51.100' ];
		$context_filter   = [ 'context_filters' => [ '_server_remote_addr' => '198.51.100.x' ] ];

		// Admins can find the event by any of its addresses.
		$this->assertSame( 1, $this->count_rest_events( $filter_by_ip ) );
		$this->assertSame( 1, $this->count_rest_events( $filter_by_header ) );
		$this->assertSame( 1, $this->count_rest_events( $search_ip ) );
		$this->assertSame( 1, $this->count_rest_events( $context_filter ) );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$all_events = $this->count_rest_events( [] );

		$this->assertGreaterThan( 1, $all_events );

		// The IP filter matches nothing rather than pretending to filter.
		$this->assertSame( 0, $this->count_rest_events( $filter_by_ip ) );
		$this->assertSame( 0, $this->count_rest_events( $filter_by_header ) );
		$this->assertSame( 0, $this->count_rest_events( $search_ip ) );

		// A context filter on an IP key is ignored, so it can't confirm a guess.
		$this->assertSame( $all_events, $this->count_rest_events( $context_filter ) );

		// The database compares keys ignoring case and trailing spaces, so
		// these variants must not reach the address either: a right guess and
		// a wrong one must get the same answer.
		$key_variants = [ '_SERVER_REMOTE_ADDR', '_server_remote_addr ', '_Server_Remote_Addr' ];

		foreach ( $key_variants as $key_variant ) {
			$this->assertSame(
				$this->count_rest_events( [ 'context_filters' => [ $key_variant => '192.0.2.x' ] ] ),
				$this->count_rest_events( [ 'context_filters' => [ $key_variant => '198.51.100.x' ] ] ),
				"Context filter key \"{$key_variant}\" tells a right guess from a wrong one."
			);
		}
	}

	public function test_filter_ip_addresses_for_current_user() {
		$context = [
			'_server_remote_addr'            => '198.51.100.x',
			'_server_http_x_forwarded_for_0' => '203.0.113.x',
			'_server_http_user_agent'        => 'Mozilla',
			'post_title'                     => 'Hello',
		];

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertSame( $context, Helpers::filter_ip_addresses_for_current_user( $context ) );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame(
			[
				'_server_http_user_agent' => 'Mozilla',
				'post_title'              => 'Hello',
			],
			Helpers::filter_ip_addresses_for_current_user( $context )
		);
	}
}
