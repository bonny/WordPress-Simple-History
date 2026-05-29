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
		$this->assertSame(
			'Simple History activity log',
			$exporters['simple-history']['exporter_friendly_name']
		);
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
		$this->assertContains( 'Logger', $field_names );
		$this->assertContains( 'Level', $field_names );
		$this->assertContains( 'Message', $field_names );
		$this->assertSame( 'Simple History activity log', $first['group_label'] );
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

	/**
	 * Helper: read an event's context as an associative array straight from the DB.
	 *
	 * @param int $history_id Event id.
	 * @return array<string,string>
	 */
	private function read_context( $history_id ) {
		global $wpdb;
		$table = Simple_History::get_instance()->get_contexts_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT `key`, value FROM {$table} WHERE history_id = %d", $history_id ),
			ARRAY_A
		);

		$out = [];
		foreach ( $rows as $r ) {
			$out[ $r['key'] ] = $r['value'];
		}
		return $out;
	}

	/**
	 * Scrubbing removes/masks PII keys but keeps the event and non-PII context.
	 */
	public function test_anonymize_event_scrubs_pii_keeps_rest() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'scrub-' . uniqid() . '@example.com' ] );
		wp_set_current_user( $user_id );

		$logger = SimpleLogger()->info(
			'Scrub me',
			[
				'_server_remote_addr'            => '203.0.113.45',
				'_server_http_x_forwarded_for_0' => '198.51.100.7',
				'server_http_user_agent'         => 'Mozilla/5.0 (TestAgent)',
				'_server_http_referer'           => 'https://example.com/secret?token=abc',
				'object_subtype'                 => 'post',
			]
		);
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'anonymize_event' );
		$method->setAccessible( true );
		$method->invoke( $service, $event_id );

		$context = $this->read_context( $event_id );

		// Identity removed / zeroed.
		$this->assertSame( '0', $context['_user_id'] );
		$this->assertArrayNotHasKey( '_user_login', $context );
		$this->assertArrayNotHasKey( '_user_email', $context );
		$this->assertArrayNotHasKey( '_user_role', $context );
		$this->assertArrayNotHasKey( 'server_http_user_agent', $context );
		$this->assertArrayNotHasKey( '_server_http_referer', $context );

		// All IP keys fully anonymized.
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertSame( '0.0.0.x', $context['_server_http_x_forwarded_for_0'] );

		// Non-PII context preserved.
		$this->assertSame( 'post', $context['object_subtype'] );

		// Event row itself still exists.
		global $wpdb;
		$events_table = Simple_History::get_instance()->get_events_table_name();
		$still_there  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE id = %d", $event_id ) );
		$this->assertSame( '1', (string) $still_there, 'Event row must NOT be deleted.' );
	}

	/**
	 * Running the scrub twice is a stable no-op.
	 */
	public function test_anonymize_event_is_idempotent() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'idem-' . uniqid() . '@example.com' ] );
		wp_set_current_user( $user_id );

		$logger   = SimpleLogger()->info( 'Idempotent', [ '_server_remote_addr' => '203.0.113.45' ] );
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'anonymize_event' );
		$method->setAccessible( true );

		$method->invoke( $service, $event_id );
		$method->invoke( $service, $event_id );

		$context = $this->read_context( $event_id );
		$this->assertSame( '0', $context['_user_id'] );
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertArrayNotHasKey( '_user_login', $context );
		$this->assertArrayNotHasKey( '_user_email', $context );
	}

	/**
	 * Registering the eraser adds our group with a callable.
	 */
	public function test_register_eraser_adds_group() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$erasers = $service->register_eraser( [] );

		$this->assertArrayHasKey( 'simple-history', $erasers );
		$this->assertIsCallable( $erasers['simple-history']['callback'] );
		$this->assertSame(
			'Simple History activity log',
			$erasers['simple-history']['eraser_friendly_name']
		);
	}

	/**
	 * Erasing scrubs the user's events and reports the WP-shaped result.
	 */
	public function test_erase_user_data_scrubs_and_reports() {
		$email   = 'erase-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		wp_set_current_user( $user_id );
		$logger   = SimpleLogger()->info( 'Erase me', [ '_server_remote_addr' => '203.0.113.45' ] );
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->erase_user_data( $email, 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['messages'] );

		$context = $this->read_context( $event_id );
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertSame( '0', $context['_user_id'] );
	}

	/**
	 * Remove any experimental-feature filters added by the gating tests so that
	 * a failing assertion cannot leak state to subsequent tests.
	 */
	public function tearDown(): void {
		remove_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
		parent::tearDown();
	}

	/**
	 * The eraser is NOT registered when experimental features are off.
	 */
	public function test_eraser_not_registered_when_experimental_off() {
		add_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );

		// Re-run loaded() to re-evaluate the gate with the filter applied.
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		remove_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] );
		$service->loaded();

		$this->assertFalse(
			has_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] ),
			'Eraser must not be registered when experimental features are off.'
		);

		remove_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );
	}

	/**
	 * The eraser IS registered when experimental features are on.
	 */
	public function test_eraser_registered_when_experimental_on() {
		add_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$service->loaded();

		$this->assertNotFalse(
			has_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] ),
			'Eraser must be registered when experimental features are on.'
		);

		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
		remove_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] );
	}
}
