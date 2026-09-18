<?php

/**
 * Test that the context field does not leak IP addresses past the gate.
 *
 * The ip_addresses field runs the display_ip_address filter, which defaults
 * to false. Raw context used to go around that filter entirely, so the same
 * event returned an empty ip_addresses object and handed out
 * _server_remote_addr through _fields=context instead.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit RestEventsContextIpTest
 */
class RestEventsContextIpTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/row_header_output/display_ip_address' );

		parent::tearDown();
	}

	/**
	 * Fetch one event's context through the REST API.
	 *
	 * @param int $event_id Event id.
	 * @return array The context array.
	 */
	private function get_context( $event_id ) {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events/' . $event_id );
		$request->set_param( '_fields', 'id,context,ip_addresses' );

		$data = rest_do_request( $request )->get_data();

		return $data['context'] ?? [];
	}

	/**
	 * Log an event and return its id.
	 *
	 * @return int
	 */
	private function log_event() {
		SimpleLogger()->info( 'Context IP test event' );

		$events = ( new \Simple_History\Log_Query() )->query(
			[
				'posts_per_page' => 1,
				'ungrouped'      => true,
			]
		);

		return (int) $events['log_rows'][0]->id;
	}

	public function test_context_hides_the_ip_when_the_filter_says_no() {
		$event_id = $this->log_event();

		$context = $this->get_context( $event_id );

		$this->assertNotEmpty( $context, 'The context should still be returned.' );
		$this->assertArrayNotHasKey( '_server_remote_addr', $context );
	}

	public function test_context_keeps_the_ip_when_the_filter_allows_it() {
		add_filter( 'simple_history/row_header_output/display_ip_address', '__return_true' );

		$event_id = $this->log_event();

		$this->assertArrayHasKey( '_server_remote_addr', $this->get_context( $event_id ) );
	}

	/**
	 * The two fields must agree. An event that withholds ip_addresses must
	 * not hand the same value over in context, which is the whole defect.
	 */
	public function test_the_two_fields_agree() {
		$event_id = $this->log_event();

		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events/' . $event_id );
		$request->set_param( '_fields', 'id,context,ip_addresses' );

		$data = rest_do_request( $request )->get_data();

		$has_ip_field   = ! empty( (array) $data['ip_addresses'] );
		$has_ip_context = isset( $data['context']['_server_remote_addr'] );

		$this->assertSame( $has_ip_field, $has_ip_context );
	}

	/**
	 * Everything that is not an address is left alone — context is useful and
	 * the point was never to empty it.
	 */
	public function test_other_context_keys_survive() {
		SimpleLogger()->info(
			'Context IP test event with data',
			[ 'some_key' => 'some value' ]
		);

		$events = ( new \Simple_History\Log_Query() )->query(
			[
				'posts_per_page' => 1,
				'ungrouped'      => true,
			]
		);

		$context = $this->get_context( (int) $events['log_rows'][0]->id );

		$this->assertSame( 'some value', $context['some_key'] ?? null );
		$this->assertArrayNotHasKey( '_server_remote_addr', $context );
	}
}
