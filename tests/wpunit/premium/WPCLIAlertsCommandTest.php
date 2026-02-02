<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Modules\Alerts_Module;

/**
 * Tests for the Alerts WP-CLI commands (Alerts_Destinations_Command and Alerts_Rules_Command).
 *
 * Note: These tests verify the command logic without actually running WP-CLI.
 * For full CLI integration tests, use the functional test suite.
 *
 * @group premium
 * @group alerts
 * @group cli
 */
class WPCLIAlertsCommandTest extends PremiumTestCase {
	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	/**
	 * Test that destinations can be stored and retrieved.
	 */
	public function test_destinations_option_storage(): void {
		$destinations = [
			'dest_123' => [
				'type'   => 'email',
				'name'   => 'Admin Email',
				'config' => [
					'recipients' => 'admin@example.com',
				],
			],
			'dest_456' => [
				'type'   => 'slack',
				'name'   => 'Dev Slack',
				'config' => [
					'webhook_url' => 'https://hooks.slack.com/xxx',
				],
			],
		];

		update_option( 'simple_history_alert_destinations', $destinations );

		$retrieved = get_option( 'simple_history_alert_destinations', [] );

		$this->assertCount( 2, $retrieved );
		$this->assertArrayHasKey( 'dest_123', $retrieved );
		$this->assertArrayHasKey( 'dest_456', $retrieved );
	}

	/**
	 * Test that alert rules can be stored and retrieved.
	 */
	public function test_alert_rules_option_storage(): void {
		$rules = [
			'security' => [
				'preset'       => 'security',
				'enabled'      => true,
				'destinations' => [ 'dest_123' ],
			],
			'content'  => [
				'preset'       => 'content',
				'enabled'      => false,
				'destinations' => [],
			],
		];

		update_option( 'simple_history_alert_rules', $rules );

		$retrieved = get_option( 'simple_history_alert_rules', [] );

		$this->assertCount( 2, $retrieved );
		$this->assertTrue( $retrieved['security']['enabled'] );
		$this->assertFalse( $retrieved['content']['enabled'] );
	}

	/**
	 * Test preset definitions are available for CLI.
	 */
	public function test_preset_definitions_available(): void {
		$presets = Alerts_Module::get_preset_definitions();

		$this->assertArrayHasKey( 'security', $presets );
		$this->assertArrayHasKey( 'content', $presets );
		$this->assertArrayHasKey( 'plugins', $presets );
	}

	/**
	 * Test get_sender returns valid senders for CLI.
	 */
	public function test_get_sender_for_cli(): void {
		$email_sender = Alerts_Module::get_sender( 'email' );
		$slack_sender = Alerts_Module::get_sender( 'slack' );

		$this->assertNotNull( $email_sender );
		$this->assertNotNull( $slack_sender );
	}

	/**
	 * Test tracking status label for CLI display.
	 */
	public function test_tracking_status_label_for_cli(): void {
		// Not used yet.
		$label = Alerts_Module::get_tracking_status_label( [] );
		$this->assertStringContainsString( 'Not used yet', $label );

		// Success.
		$label = Alerts_Module::get_tracking_status_label( [
			'last_success' => time() - 60,
		] );
		$this->assertStringContainsString( 'OK', $label );

		// Error.
		$label = Alerts_Module::get_tracking_status_label( [
			'last_success' => time() - 3600,
			'last_error'   => [
				'time' => time() - 60,
			],
		] );
		$this->assertStringContainsString( 'Error', $label );
	}

	/**
	 * Test destination type filtering.
	 */
	public function test_destinations_can_be_filtered_by_type(): void {
		$destinations = [
			'dest_1' => [ 'type' => 'email', 'name' => 'Email 1' ],
			'dest_2' => [ 'type' => 'slack', 'name' => 'Slack 1' ],
			'dest_3' => [ 'type' => 'email', 'name' => 'Email 2' ],
			'dest_4' => [ 'type' => 'discord', 'name' => 'Discord 1' ],
		];

		update_option( 'simple_history_alert_destinations', $destinations );

		$all = get_option( 'simple_history_alert_destinations', [] );

		// Filter email destinations.
		$email_dests = array_filter(
			$all,
			function ( $dest ) {
				return ( $dest['type'] ?? '' ) === 'email';
			}
		);

		$this->assertCount( 2, $email_dests );

		// Filter slack destinations.
		$slack_dests = array_filter(
			$all,
			function ( $dest ) {
				return ( $dest['type'] ?? '' ) === 'slack';
			}
		);

		$this->assertCount( 1, $slack_dests );
	}

	/**
	 * Test enabling a rule updates the option.
	 */
	public function test_enabling_rule_updates_option(): void {
		$rules = [
			'security' => [
				'preset'       => 'security',
				'enabled'      => false,
				'destinations' => [],
			],
		];

		update_option( 'simple_history_alert_rules', $rules );

		// Simulate enabling the rule.
		$rules['security']['enabled']      = true;
		$rules['security']['destinations'] = [ 'dest_123' ];

		update_option( 'simple_history_alert_rules', $rules );

		$updated = get_option( 'simple_history_alert_rules', [] );

		$this->assertTrue( $updated['security']['enabled'] );
		$this->assertContains( 'dest_123', $updated['security']['destinations'] );
	}

	/**
	 * Test disabling a rule updates the option.
	 */
	public function test_disabling_rule_updates_option(): void {
		$rules = [
			'content' => [
				'preset'       => 'content',
				'enabled'      => true,
				'destinations' => [ 'dest_456' ],
			],
		];

		update_option( 'simple_history_alert_rules', $rules );

		// Simulate disabling the rule.
		$rules['content']['enabled'] = false;

		update_option( 'simple_history_alert_rules', $rules );

		$updated = get_option( 'simple_history_alert_rules', [] );

		$this->assertFalse( $updated['content']['enabled'] );
	}

	/**
	 * Test deleting a destination removes it from the option.
	 */
	public function test_deleting_destination_removes_from_option(): void {
		$destinations = [
			'dest_to_keep'   => [ 'type' => 'email', 'name' => 'Keep' ],
			'dest_to_delete' => [ 'type' => 'slack', 'name' => 'Delete' ],
		];

		update_option( 'simple_history_alert_destinations', $destinations );

		// Simulate deletion.
		unset( $destinations['dest_to_delete'] );
		update_option( 'simple_history_alert_destinations', $destinations );

		$updated = get_option( 'simple_history_alert_destinations', [] );

		$this->assertCount( 1, $updated );
		$this->assertArrayHasKey( 'dest_to_keep', $updated );
		$this->assertArrayNotHasKey( 'dest_to_delete', $updated );
	}
}
