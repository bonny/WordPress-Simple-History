<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Modules\Alerts_Logger;
use Simple_History\Simple_History;

/**
 * Tests for the Alerts_Logger class.
 *
 * @group premium
 * @group alerts
 * @group loggers
 */
class AlertsLoggerTest extends PremiumTestCase {
	/** @var Alerts_Logger */
	private Alerts_Logger $logger;

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		// Get the logger instance.
		$simple_history = Simple_History::get_instance();
		$this->logger   = $simple_history->get_instantiated_logger_by_slug( 'AlertsLogger' );
	}

	/**
	 * Test that the logger is registered.
	 */
	public function test_logger_is_registered(): void {
		$this->assertNotNull( $this->logger, 'AlertsLogger should be registered.' );
		$this->assertInstanceOf( Alerts_Logger::class, $this->logger );
	}

	/**
	 * Test logger info contains required fields.
	 */
	public function test_logger_info_has_required_fields(): void {
		$info = $this->logger->get_info();

		$this->assertArrayHasKey( 'name', $info );
		$this->assertArrayHasKey( 'description', $info );
		$this->assertArrayHasKey( 'messages', $info );
	}

	/**
	 * Test logger has destination_created message.
	 */
	public function test_logger_has_destination_created_message(): void {
		$info = $this->logger->get_info();

		$this->assertArrayHasKey( 'destination_created', $info['messages'] );
	}

	/**
	 * Test logger has destination_updated message.
	 */
	public function test_logger_has_destination_updated_message(): void {
		$info = $this->logger->get_info();

		$this->assertArrayHasKey( 'destination_updated', $info['messages'] );
	}

	/**
	 * Test logger has destination_deleted message.
	 */
	public function test_logger_has_destination_deleted_message(): void {
		$info = $this->logger->get_info();

		$this->assertArrayHasKey( 'destination_deleted', $info['messages'] );
	}

	/**
	 * Test logger has rule_enabled message.
	 */
	public function test_logger_has_rule_enabled_message(): void {
		$info = $this->logger->get_info();

		$this->assertArrayHasKey( 'rule_enabled', $info['messages'] );
	}

	/**
	 * Test logger has rule_disabled message.
	 */
	public function test_logger_has_rule_disabled_message(): void {
		$info = $this->logger->get_info();

		$this->assertArrayHasKey( 'rule_disabled', $info['messages'] );
	}

	/**
	 * Test destination_created hook triggers logging.
	 */
	public function test_destination_created_hook_logs_event(): void {
		// Set up as admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Trigger the hook.
		do_action(
			'simple_history/alerts/destination_created',
			'dest_test123',
			[
				'name' => 'Test Destination',
				'type' => 'email',
			]
		);

		// Query for the log entry.
		$log_query      = new \Simple_History\Log_Query();
		$query_results  = $log_query->query(
			[
				'posts_per_page' => 1,
				'loggers'        => 'AlertsLogger',
			]
		);

		$this->assertNotEmpty( $query_results['log_rows'], 'Should have logged an event.' );

		$log_row = $query_results['log_rows'][0];
		$this->assertEquals( 'AlertsLogger', $log_row->logger );
		$this->assertStringContainsString( 'destination_created', $log_row->message );
	}

	/**
	 * Test destination_deleted hook triggers logging.
	 */
	public function test_destination_deleted_hook_logs_event(): void {
		// Set up as admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Trigger the hook.
		do_action(
			'simple_history/alerts/destination_deleted',
			'dest_test456',
			[
				'name' => 'Deleted Destination',
				'type' => 'slack',
			]
		);

		// Query for the log entry.
		$log_query      = new \Simple_History\Log_Query();
		$query_results  = $log_query->query(
			[
				'posts_per_page' => 1,
				'loggers'        => 'AlertsLogger',
			]
		);

		$this->assertNotEmpty( $query_results['log_rows'], 'Should have logged an event.' );

		$log_row = $query_results['log_rows'][0];
		$this->assertStringContainsString( 'destination_deleted', $log_row->message );
	}

	/**
	 * Test rules_saved hook logs rule enabled.
	 */
	public function test_rules_saved_logs_rule_enabled(): void {
		// Set up as admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$old_rules = [
			'security' => [
				'enabled'      => false,
				'destinations' => [],
			],
		];

		$new_rules = [
			'security' => [
				'enabled'      => true,
				'name'         => 'Security Alerts',
				'destinations' => [ 'dest_1' ],
			],
		];

		// Trigger the hook.
		do_action( 'simple_history/alerts/rules_saved', $new_rules, $old_rules );

		// Query for the log entry.
		$log_query      = new \Simple_History\Log_Query();
		$query_results  = $log_query->query(
			[
				'posts_per_page' => 1,
				'loggers'        => 'AlertsLogger',
			]
		);

		$this->assertNotEmpty( $query_results['log_rows'], 'Should have logged an event.' );

		$log_row = $query_results['log_rows'][0];
		$this->assertStringContainsString( 'rule_enabled', $log_row->message );
	}

	/**
	 * Test rules_saved hook logs rule disabled.
	 */
	public function test_rules_saved_logs_rule_disabled(): void {
		// Set up as admin user.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$old_rules = [
			'content' => [
				'enabled'      => true,
				'name'         => 'Content Alerts',
				'destinations' => [ 'dest_1' ],
			],
		];

		$new_rules = [
			'content' => [
				'enabled'      => false,
				'name'         => 'Content Alerts',
				'destinations' => [ 'dest_1' ],
			],
		];

		// Trigger the hook.
		do_action( 'simple_history/alerts/rules_saved', $new_rules, $old_rules );

		// Query for the log entry.
		$log_query      = new \Simple_History\Log_Query();
		$query_results  = $log_query->query(
			[
				'posts_per_page' => 1,
				'loggers'        => 'AlertsLogger',
			]
		);

		$this->assertNotEmpty( $query_results['log_rows'], 'Should have logged an event.' );

		$log_row = $query_results['log_rows'][0];
		$this->assertStringContainsString( 'rule_disabled', $log_row->message );
	}
}
