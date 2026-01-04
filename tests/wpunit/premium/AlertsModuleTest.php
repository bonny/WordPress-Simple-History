<?php

namespace Simple_History\Tests\Premium;

use Simple_History\AddOns\Pro\Modules\Alerts_Module;

/**
 * Tests for the Alerts_Module class.
 *
 * @group premium
 * @group alerts
 */
class AlertsModuleTest extends PremiumTestCase {
	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	/**
	 * Test that premium sets the alerts filter.
	 */
	public function test_premium_handles_alerts_filter(): void {
		$this->assertPremiumHandlingAlerts();
	}

	/**
	 * Test that core handles alerts when premium is not active.
	 */
	public function test_core_handles_alerts_when_premium_inactive(): void {
		$this->deactivate_premium();

		// Remove all filters first to get clean state.
		remove_all_filters( 'simple_history/alerts/is_premium_handling' );

		$this->assertCoreHandlingAlerts();
	}

	/**
	 * Test get_preset_definitions returns expected presets.
	 */
	public function test_get_preset_definitions_returns_array(): void {
		$presets = Alerts_Module::get_preset_definitions();

		$this->assertIsArray( $presets );
		$this->assertNotEmpty( $presets );
	}

	/**
	 * Test that security preset exists.
	 */
	public function test_security_preset_exists(): void {
		$presets = Alerts_Module::get_preset_definitions();

		$this->assertArrayHasKey( 'security', $presets );
		$this->assertArrayHasKey( 'name', $presets['security'] );
		$this->assertArrayHasKey( 'description', $presets['security'] );
		$this->assertArrayHasKey( 'message_keys', $presets['security'] );
	}

	/**
	 * Test that content preset exists.
	 */
	public function test_content_preset_exists(): void {
		$presets = Alerts_Module::get_preset_definitions();

		$this->assertArrayHasKey( 'content', $presets );
	}

	/**
	 * Test that plugins preset exists.
	 */
	public function test_plugins_preset_exists(): void {
		$presets = Alerts_Module::get_preset_definitions();

		$this->assertArrayHasKey( 'plugins', $presets );
	}

	/**
	 * Test get_destination_type_label returns correct labels.
	 */
	public function test_get_destination_type_label_email(): void {
		$label = Alerts_Module::get_destination_type_label( 'email' );

		$this->assertEquals( 'Email', $label );
	}

	/**
	 * Test get_destination_type_label returns correct labels for Slack.
	 */
	public function test_get_destination_type_label_slack(): void {
		$label = Alerts_Module::get_destination_type_label( 'slack' );

		$this->assertEquals( 'Slack', $label );
	}

	/**
	 * Test get_destination_type_label returns type for unknown types.
	 */
	public function test_get_destination_type_label_unknown(): void {
		$label = Alerts_Module::get_destination_type_label( 'unknown_type' );

		$this->assertEquals( 'unknown_type', $label );
	}

	/**
	 * Test get_tracking_status_label for never used destination.
	 */
	public function test_get_tracking_status_label_never_used(): void {
		$tracking = [];
		$label    = Alerts_Module::get_tracking_status_label( $tracking );

		$this->assertStringContainsString( 'Never used', $label );
	}

	/**
	 * Test get_tracking_status_label for successful destination.
	 */
	public function test_get_tracking_status_label_success(): void {
		$tracking = [
			'last_success' => time() - 3600, // 1 hour ago.
			'last_error'   => [],
		];
		$label    = Alerts_Module::get_tracking_status_label( $tracking );

		$this->assertStringContainsString( 'OK', $label );
		$this->assertStringContainsString( 'ago', $label );
	}

	/**
	 * Test get_tracking_status_label for error state.
	 */
	public function test_get_tracking_status_label_error(): void {
		$tracking = [
			'last_success' => time() - 7200, // 2 hours ago.
			'last_error'   => [
				'time'    => time() - 1800, // 30 min ago (more recent).
				'message' => 'Connection failed',
			],
		];
		$label    = Alerts_Module::get_tracking_status_label( $tracking );

		$this->assertStringContainsString( 'Error', $label );
		$this->assertStringContainsString( 'ago', $label );
	}

	/**
	 * Test sanitize_destination_config for email type.
	 */
	public function test_sanitize_destination_config_email(): void {
		$config = [
			'recipients' => "test@example.com, user@example.com\ninvalid-email",
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'email', $config );

		$this->assertArrayHasKey( 'recipients', $sanitized );
		// Should filter out invalid email and normalize.
		$this->assertStringContainsString( 'test@example.com', $sanitized['recipients'] );
		$this->assertStringContainsString( 'user@example.com', $sanitized['recipients'] );
		$this->assertStringNotContainsString( 'invalid-email', $sanitized['recipients'] );
	}

	/**
	 * Test sanitize_destination_config for Slack type.
	 */
	public function test_sanitize_destination_config_slack(): void {
		$config = [
			'webhook_url' => 'https://hooks.slack.com/services/T00/B00/XXX',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'slack', $config );

		$this->assertArrayHasKey( 'webhook_url', $sanitized );
		$this->assertEquals( 'https://hooks.slack.com/services/T00/B00/XXX', $sanitized['webhook_url'] );
	}

	/**
	 * Test sanitize_destination_config for Discord type.
	 */
	public function test_sanitize_destination_config_discord(): void {
		$config = [
			'webhook_url' => 'https://discord.com/api/webhooks/123/abc',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'discord', $config );

		$this->assertArrayHasKey( 'webhook_url', $sanitized );
		$this->assertEquals( 'https://discord.com/api/webhooks/123/abc', $sanitized['webhook_url'] );
	}

	/**
	 * Test sanitize_destination_config for Telegram type.
	 */
	public function test_sanitize_destination_config_telegram(): void {
		$config = [
			'bot_token' => '123456:ABC-DEF',
			'chat_id'   => '-1001234567890',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'telegram', $config );

		$this->assertArrayHasKey( 'bot_token', $sanitized );
		$this->assertArrayHasKey( 'chat_id', $sanitized );
		$this->assertEquals( '123456:ABC-DEF', $sanitized['bot_token'] );
		$this->assertEquals( '-1001234567890', $sanitized['chat_id'] );
	}

	/**
	 * Test sanitize_destination_config handles non-array input.
	 */
	public function test_sanitize_destination_config_invalid_input(): void {
		$sanitized = Alerts_Module::sanitize_destination_config( 'email', 'not-an-array' );

		$this->assertIsArray( $sanitized );
		$this->assertEmpty( $sanitized );
	}

	/**
	 * Test get_sender returns correct sender for email.
	 */
	public function test_get_sender_email(): void {
		$sender = Alerts_Module::get_sender( 'email' );

		$this->assertNotNull( $sender );
		$this->assertInstanceOf(
			'Simple_History\AddOns\Pro\Destinations\Destination_Sender',
			$sender
		);
	}

	/**
	 * Test get_sender returns correct sender for Slack.
	 */
	public function test_get_sender_slack(): void {
		$sender = Alerts_Module::get_sender( 'slack' );

		$this->assertNotNull( $sender );
	}

	/**
	 * Test get_sender returns null for unknown type.
	 */
	public function test_get_sender_unknown(): void {
		$sender = Alerts_Module::get_sender( 'unknown_type' );

		$this->assertNull( $sender );
	}
}
