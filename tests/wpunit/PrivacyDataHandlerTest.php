<?php

use Simple_History\Simple_History;
use Simple_History\Services\Privacy_Data_Handler;

/**
 * Tests for the WordPress privacy export/erasure integration.
 */
class PrivacyDataHandlerTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The service must be registered and loaded by Simple History.
	 */
	public function test_service_is_loaded() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );

		$this->assertInstanceOf(
			Privacy_Data_Handler::class,
			$service,
			'Privacy_Data_Handler should be loaded as a core service.'
		);
	}

	/**
	 * Helper: log an event as a specific user with explicit PII context.
	 *
	 * @param int    $user_id Initiator user id.
	 * @param string $message Event message.
	 * @return int New event id.
	 */
	private function log_event_as_user( $user_id, $message ) {
		wp_set_current_user( $user_id );

		$logger = SimpleLogger()->info(
			$message,
			[
				'_server_remote_addr'    => '203.0.113.45',
				'server_http_user_agent' => 'Mozilla/5.0 (TestAgent)',
			]
		);

		return $logger->last_insert_id;
	}

	/**
	 * Returns initiated events for the matching user by email.
	 */
	public function test_get_user_event_rows_returns_users_events() {
		$email   = 'erika-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		$this->log_event_as_user( $user_id, 'Erika did a thing' );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, $email, 1 );

		$this->assertNotEmpty( $rows, 'Should return the user\'s events.' );
		$this->assertSame( (string) $user_id, (string) $rows[0]->context['_user_id'] );
	}

	/**
	 * Unknown email yields no rows.
	 */
	public function test_get_user_event_rows_unknown_email_returns_empty() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, 'nobody-' . uniqid() . '@example.com', 1 );

		$this->assertSame( [], $rows, 'Unknown email should return an empty array.' );
	}

	/**
	 * Repeated (occasion-grouped) events are each returned individually, so
	 * none are silently excluded from export/erasure.
	 */
	public function test_get_user_event_rows_returns_each_repeated_event() {
		$email   = 'repeat-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		wp_set_current_user( $user_id );

		// Three events that would normally collapse into one occasion group.
		for ( $i = 0; $i < 3; $i++ ) {
			SimpleLogger()->info( 'Repeated privacy event', [ '_occasionsID' => 'privacy_test_occasion' ] );
		}

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, $email, 1 );

		$this->assertGreaterThanOrEqual( 3, count( $rows ), 'All three repeated events must be returned individually (ungrouped).' );
	}

	/**
	 * Registering the exporter adds our group to the exporters array.
	 */
	public function test_register_exporter_adds_group() {
		$service   = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$exporters = $service->register_exporter( [] );

		$this->assertArrayHasKey( 'simple-history', $exporters );
		$this->assertIsCallable( $exporters['simple-history']['callback'] );
	}

	/**
	 * Export returns the user's events with the expected fields and a done flag.
	 */
	public function test_export_user_data_returns_events() {
		$email   = 'export-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		$this->log_event_as_user( $user_id, 'Exportable event' );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $email, 1 );

		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['data'] );

		$first = $result['data'][0];
		$this->assertSame( 'simple-history', $first['group_id'] );
		$this->assertStringStartsWith( 'sh-event-', $first['item_id'] );

		$field_names = wp_list_pluck( $first['data'], 'name' );
		$this->assertContains( 'Date', $field_names );
		$this->assertContains( 'IP address', $field_names );
		$this->assertContains( 'User agent', $field_names );
	}

	/**
	 * Export for an unknown email is empty and done.
	 */
	public function test_export_user_data_unknown_email_is_done() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( 'nobody-' . uniqid() . '@example.com', 1 );

		$this->assertSame( [], $result['data'] );
		$this->assertTrue( $result['done'] );
	}
}
