<?php

use Helper\PremiumTestCase;
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
		$this->assertArrayHasKey( 'events', $presets['security'] );
		$this->assertIsArray( $presets['security']['events'] );
		$this->assertNotEmpty( $presets['security']['events'] );
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
	 * Test sanitize_destination_config handles null input.
	 */
	public function test_sanitize_destination_config_null_input(): void {
		$sanitized = Alerts_Module::sanitize_destination_config( 'email', null );

		$this->assertIsArray( $sanitized );
		$this->assertEmpty( $sanitized );
	}

	/**
	 * Test sanitize_destination_config with empty config array.
	 */
	public function test_sanitize_destination_config_empty_array(): void {
		$sanitized = Alerts_Module::sanitize_destination_config( 'email', [] );

		$this->assertIsArray( $sanitized );
		$this->assertArrayHasKey( 'recipients', $sanitized );
		$this->assertEmpty( $sanitized['recipients'] );
	}

	/**
	 * Test sanitize_destination_config normalizes multiple email separators.
	 */
	public function test_sanitize_destination_config_email_multiple_separators(): void {
		$config = [
			'recipients' => "user1@example.com, user2@example.com\nuser3@example.com\r\nuser4@example.com",
		];

		$sanitized  = Alerts_Module::sanitize_destination_config( 'email', $config );
		$recipients = explode( "\n", $sanitized['recipients'] );

		$this->assertCount( 4, $recipients );
		$this->assertContains( 'user1@example.com', $recipients );
		$this->assertContains( 'user2@example.com', $recipients );
		$this->assertContains( 'user3@example.com', $recipients );
		$this->assertContains( 'user4@example.com', $recipients );
	}

	/**
	 * Test sanitize_destination_config removes duplicate emails.
	 */
	public function test_sanitize_destination_config_email_removes_whitespace(): void {
		$config = [
			'recipients' => '   user@example.com   ,   admin@example.com   ',
		];

		$sanitized  = Alerts_Module::sanitize_destination_config( 'email', $config );
		$recipients = explode( "\n", $sanitized['recipients'] );

		// Should be trimmed.
		$this->assertContains( 'user@example.com', $recipients );
		$this->assertContains( 'admin@example.com', $recipients );
	}

	/**
	 * Test sanitize_destination_config filters out all invalid emails.
	 */
	public function test_sanitize_destination_config_email_all_invalid(): void {
		$config = [
			'recipients' => 'invalid, notanemail, @missing.com',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'email', $config );

		$this->assertEmpty( $sanitized['recipients'] );
	}

	/**
	 * Test sanitize_destination_config handles XSS attempts in webhook URL.
	 */
	public function test_sanitize_destination_config_webhook_xss_protection(): void {
		$config = [
			'webhook_url' => 'javascript:alert("xss")',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'slack', $config );

		// esc_url_raw should strip javascript: protocol.
		$this->assertStringNotContainsString( 'javascript', $sanitized['webhook_url'] );
	}

	/**
	 * Test sanitize_destination_config handles missing webhook_url key.
	 */
	public function test_sanitize_destination_config_slack_missing_url(): void {
		$config = [
			'some_other_key' => 'value',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'slack', $config );

		$this->assertArrayHasKey( 'webhook_url', $sanitized );
		$this->assertEmpty( $sanitized['webhook_url'] );
	}

	/**
	 * Test sanitize_destination_config handles missing Telegram config keys.
	 */
	public function test_sanitize_destination_config_telegram_missing_keys(): void {
		$config = [];

		$sanitized = Alerts_Module::sanitize_destination_config( 'telegram', $config );

		$this->assertArrayHasKey( 'bot_token', $sanitized );
		$this->assertArrayHasKey( 'chat_id', $sanitized );
		$this->assertEmpty( $sanitized['bot_token'] );
		$this->assertEmpty( $sanitized['chat_id'] );
	}

	/**
	 * Test sanitize_destination_config sanitizes Telegram bot token.
	 */
	public function test_sanitize_destination_config_telegram_sanitizes_token(): void {
		$config = [
			'bot_token' => '<script>alert("xss")</script>123:ABC',
			'chat_id'   => '-100<b>bold</b>123',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'telegram', $config );

		// HTML tags should be stripped.
		$this->assertStringNotContainsString( '<script>', $sanitized['bot_token'] );
		$this->assertStringNotContainsString( '<b>', $sanitized['chat_id'] );
	}

	/**
	 * Test sanitize_destination_config returns empty for unknown type.
	 */
	public function test_sanitize_destination_config_unknown_type(): void {
		$config = [
			'some_field' => 'some_value',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'unknown_type', $config );

		$this->assertIsArray( $sanitized );
		$this->assertEmpty( $sanitized );
	}

	/**
	 * Test sanitize_destination_config handles Teams type (same as Slack/Discord).
	 */
	public function test_sanitize_destination_config_teams(): void {
		$config = [
			'webhook_url' => 'https://outlook.office.com/webhook/xxx',
		];

		$sanitized = Alerts_Module::sanitize_destination_config( 'teams', $config );

		$this->assertArrayHasKey( 'webhook_url', $sanitized );
		$this->assertEquals( 'https://outlook.office.com/webhook/xxx', $sanitized['webhook_url'] );
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

	/**
	 * Test get_all_tracking returns empty array by default.
	 */
	public function test_get_all_tracking_returns_empty_by_default(): void {
		delete_option( Alerts_Module::OPTION_TRACKING );

		$tracking = Alerts_Module::get_all_tracking();

		$this->assertIsArray( $tracking );
		$this->assertEmpty( $tracking );
	}

	/**
	 * Test get_all_tracking returns stored tracking data.
	 */
	public function test_get_all_tracking_returns_stored_data(): void {
		$test_tracking = [
			'dest_123' => [
				'last_success'  => time(),
				'success_count' => 5,
			],
		];
		update_option( Alerts_Module::OPTION_TRACKING, $test_tracking, false );

		$tracking = Alerts_Module::get_all_tracking();

		$this->assertEquals( $test_tracking, $tracking );

		// Cleanup.
		delete_option( Alerts_Module::OPTION_TRACKING );
	}

	/**
	 * Test get_destination_tracking returns defaults for missing destination.
	 */
	public function test_get_destination_tracking_returns_defaults(): void {
		delete_option( Alerts_Module::OPTION_TRACKING );

		$tracking = Alerts_Module::get_destination_tracking( 'nonexistent_id' );

		$this->assertIsArray( $tracking );
		$this->assertEquals( 0, $tracking['last_success'] );
		$this->assertEquals( [], $tracking['last_error'] );
		$this->assertEquals( 0, $tracking['success_count'] );
		$this->assertEquals( 0, $tracking['error_count'] );
	}

	/**
	 * Test get_destination_tracking returns stored data with defaults merged.
	 */
	public function test_get_destination_tracking_returns_stored_with_defaults(): void {
		$test_tracking = [
			'dest_123' => [
				'success_count' => 10,
				// Missing other fields - should be filled with defaults.
			],
		];
		update_option( Alerts_Module::OPTION_TRACKING, $test_tracking, false );

		$tracking = Alerts_Module::get_destination_tracking( 'dest_123' );

		$this->assertEquals( 10, $tracking['success_count'] );
		$this->assertEquals( 0, $tracking['last_success'] ); // Default applied.
		$this->assertEquals( [], $tracking['last_error'] );  // Default applied.
		$this->assertEquals( 0, $tracking['error_count'] );  // Default applied.

		// Cleanup.
		delete_option( Alerts_Module::OPTION_TRACKING );
	}

	/**
	 * Test save_all_tracking saves data correctly.
	 */
	public function test_save_all_tracking_saves_data(): void {
		$test_tracking = [
			'dest_a' => [ 'success_count' => 5 ],
			'dest_b' => [ 'error_count' => 2 ],
		];

		$result = Alerts_Module::save_all_tracking( $test_tracking );

		$this->assertTrue( $result );

		$stored = get_option( Alerts_Module::OPTION_TRACKING );
		$this->assertEquals( $test_tracking, $stored );

		// Cleanup.
		delete_option( Alerts_Module::OPTION_TRACKING );
	}

	/**
	 * Test update_destination_tracking updates single destination.
	 */
	public function test_update_destination_tracking_updates_single(): void {
		$initial_tracking = [
			'dest_a' => [ 'success_count' => 5 ],
			'dest_b' => [ 'success_count' => 3 ],
		];
		update_option( Alerts_Module::OPTION_TRACKING, $initial_tracking, false );

		$new_tracking = [ 'success_count' => 10, 'last_success' => time() ];
		$result       = Alerts_Module::update_destination_tracking( 'dest_a', $new_tracking );

		$this->assertTrue( $result );

		$stored = get_option( Alerts_Module::OPTION_TRACKING );
		$this->assertEquals( 10, $stored['dest_a']['success_count'] );
		$this->assertEquals( 3, $stored['dest_b']['success_count'] ); // Unchanged.

		// Cleanup.
		delete_option( Alerts_Module::OPTION_TRACKING );
	}

	/**
	 * Test update_destination_tracking adds new destination.
	 */
	public function test_update_destination_tracking_adds_new(): void {
		$initial_tracking = [
			'dest_existing' => [ 'success_count' => 5 ],
		];
		update_option( Alerts_Module::OPTION_TRACKING, $initial_tracking, false );

		$new_tracking = [ 'success_count' => 1 ];
		$result       = Alerts_Module::update_destination_tracking( 'dest_new', $new_tracking );

		$this->assertTrue( $result );

		$stored = get_option( Alerts_Module::OPTION_TRACKING );
		$this->assertArrayHasKey( 'dest_existing', $stored );
		$this->assertArrayHasKey( 'dest_new', $stored );
		$this->assertEquals( 1, $stored['dest_new']['success_count'] );

		// Cleanup.
		delete_option( Alerts_Module::OPTION_TRACKING );
	}

	/**
	 * Test delete_destination_tracking removes destination.
	 */
	public function test_delete_destination_tracking_removes(): void {
		$initial_tracking = [
			'dest_a' => [ 'success_count' => 5 ],
			'dest_b' => [ 'success_count' => 3 ],
		];
		update_option( Alerts_Module::OPTION_TRACKING, $initial_tracking, false );

		$result = Alerts_Module::delete_destination_tracking( 'dest_a' );

		$this->assertTrue( $result );

		$stored = get_option( Alerts_Module::OPTION_TRACKING );
		$this->assertArrayNotHasKey( 'dest_a', $stored );
		$this->assertArrayHasKey( 'dest_b', $stored );

		// Cleanup.
		delete_option( Alerts_Module::OPTION_TRACKING );
	}

	/**
	 * Test delete_destination_tracking returns true for nonexistent destination.
	 */
	public function test_delete_destination_tracking_nonexistent(): void {
		$initial_tracking = [
			'dest_existing' => [ 'success_count' => 5 ],
		];
		update_option( Alerts_Module::OPTION_TRACKING, $initial_tracking, false );

		$result = Alerts_Module::delete_destination_tracking( 'dest_nonexistent' );

		$this->assertTrue( $result ); // Nothing to delete is not an error.

		$stored = get_option( Alerts_Module::OPTION_TRACKING );
		$this->assertArrayHasKey( 'dest_existing', $stored ); // Unchanged.

		// Cleanup.
		delete_option( Alerts_Module::OPTION_TRACKING );
	}

	/**
	 * Test tracking is separate from destinations option.
	 */
	public function test_tracking_separate_from_destinations(): void {
		// Set up destinations without tracking.
		$destinations = [
			'dest_123' => [
				'type'   => 'email',
				'name'   => 'Test Email',
				'config' => [ 'recipients' => 'test@example.com' ],
			],
		];
		update_option( Alerts_Module::OPTION_DESTINATIONS, $destinations, false );

		// Set up tracking separately.
		$tracking = [
			'dest_123' => [
				'success_count' => 10,
				'last_success'  => time(),
			],
		];
		update_option( Alerts_Module::OPTION_TRACKING, $tracking, false );

		// Verify they are separate.
		$stored_destinations = get_option( Alerts_Module::OPTION_DESTINATIONS );
		$stored_tracking     = get_option( Alerts_Module::OPTION_TRACKING );

		$this->assertArrayNotHasKey( 'tracking', $stored_destinations['dest_123'] );
		$this->assertArrayHasKey( 'dest_123', $stored_tracking );

		// Cleanup.
		delete_option( Alerts_Module::OPTION_DESTINATIONS );
		delete_option( Alerts_Module::OPTION_TRACKING );
	}
}
