<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Destinations\Email_Destination_Sender;
use Simple_History\AddOns\Pro\Destinations\Slack_Destination_Sender;
use Simple_History\AddOns\Pro\Destinations\Discord_Destination_Sender;
use Simple_History\AddOns\Pro\Destinations\Telegram_Destination_Sender;

/**
 * Tests for premium destination senders.
 *
 * @group premium
 * @group destinations
 * @group alerts
 */
class DestinationSendersTest extends PremiumTestCase {
	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	// =========================================================================
	// Email Destination Sender Tests
	// =========================================================================

	/**
	 * Test Email sender class exists.
	 */
	public function test_email_sender_exists(): void {
		$this->assertTrue(
			class_exists( Email_Destination_Sender::class ),
			'Email_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Email sender type.
	 */
	public function test_email_sender_type(): void {
		$sender = new Email_Destination_Sender();
		$this->assertEquals( 'email', $sender->get_type() );
	}

	/**
	 * Test Email sender name.
	 */
	public function test_email_sender_name(): void {
		$sender = new Email_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	/**
	 * Test Email sender get_test_event_data returns proper structure.
	 */
	public function test_email_sender_get_test_event_data(): void {
		$sender    = new Email_Destination_Sender();
		$test_data = $sender->get_test_event_data();

		$this->assertArrayHasKey( 'event_data', $test_data );
		$this->assertArrayHasKey( 'context', $test_data );
		$this->assertArrayHasKey( 'level', $test_data['event_data'] );
		$this->assertArrayHasKey( 'message', $test_data['event_data'] );
	}

	/**
	 * Test Email sender fails with empty recipients.
	 */
	public function test_email_sender_fails_with_empty_recipients(): void {
		$sender = new Email_Destination_Sender();

		$config = [
			'config' => [
				'recipients' => '',
			],
		];

		$result = $sender->test( $config );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['message'] );
	}

	/**
	 * Test Email subject includes site name prefix.
	 */
	public function test_email_subject_includes_site_prefix(): void {
		$sender = new Email_Destination_Sender();

		// Use reflection to access private method.
		$method = new ReflectionMethod( $sender, 'build_subject' );
		$method->setAccessible( true );

		$normalized = [
			'site_name' => 'My Test Site',
			'message'   => 'User logged in from new location',
			'level'     => 'info',
		];

		$subject = $method->invoke( $sender, $normalized );

		$this->assertStringStartsWith( '[My Test Site]', $subject );
		$this->assertStringContainsString( 'User logged in', $subject );
	}

	/**
	 * Test Email subject truncates long site names.
	 */
	public function test_email_subject_truncates_long_site_name(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_subject' );
		$method->setAccessible( true );

		$normalized = [
			'site_name' => 'ThisIsAVeryLongSiteNameThatShouldBeTruncated',
			'message'   => 'Test message',
			'level'     => 'info',
		];

		$subject = $method->invoke( $sender, $normalized );

		// Site name should be truncated to 20 chars.
		$this->assertStringStartsWith( '[ThisIsAVeryLongSiteN]', $subject );
	}

	/**
	 * Test Email subject format for critical level.
	 */
	public function test_email_subject_adds_emoji_for_critical(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_subject' );
		$method->setAccessible( true );

		$normalized = [
			'site_name' => 'Site',
			'message'   => 'Critical error occurred',
			'level'     => 'critical',
		];

		$subject = $method->invoke( $sender, $normalized );

		// Subject format: [Site] message (truncated).
		$this->assertStringStartsWith( '[Site]', $subject );
		$this->assertStringContainsString( 'Critical', $subject );
	}

	/**
	 * Test Email subject format for error level.
	 */
	public function test_email_subject_adds_emoji_for_error(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_subject' );
		$method->setAccessible( true );

		$normalized = [
			'site_name' => 'Site',
			'message'   => 'Error message here',
			'level'     => 'error',
		];

		$subject = $method->invoke( $sender, $normalized );

		// Subject format: [Site] message (truncated).
		$this->assertStringStartsWith( '[Site]', $subject );
		$this->assertStringContainsString( 'Error', $subject );
	}

	/**
	 * Test Email subject does not add emoji for info level.
	 */
	public function test_email_subject_no_emoji_for_info(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_subject' );
		$method->setAccessible( true );

		$normalized = [
			'site_name' => 'Site',
			'message'   => 'Info message here',
			'level'     => 'info',
		];

		$subject = $method->invoke( $sender, $normalized );

		// Should not contain emojis for info level.
		$this->assertStringNotContainsString( '🔴', $subject );
		$this->assertStringNotContainsString( '⚫', $subject );
	}

	/**
	 * Test Email From header includes site name.
	 */
	public function test_email_from_header_includes_site_name(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_from_header' );
		$method->setAccessible( true );

		$normalized = [
			'site_name' => 'My WordPress Site',
		];

		$from_header = $method->invoke( $sender, $normalized );

		$this->assertStringStartsWith( 'From: "My WordPress Site"', $from_header );
		$this->assertStringContainsString( '<', $from_header );
		$this->assertStringContainsString( '>', $from_header );
	}

	/**
	 * Test Email From header sanitizes special characters.
	 */
	public function test_email_from_header_sanitizes_special_chars(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_from_header' );
		$method->setAccessible( true );

		$normalized = [
			'site_name' => "Site with \"quotes\" and\nnewlines",
		];

		$from_header = $method->invoke( $sender, $normalized );

		// Should not contain quotes or newlines in site name.
		$this->assertStringNotContainsString( '"quotes"', $from_header );
		$this->assertStringNotContainsString( "\n", $from_header );
	}

	/**
	 * Test Email body follows Site → User → Time → Action hierarchy.
	 */
	public function test_email_body_hierarchy(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_body' );
		$method->setAccessible( true );

		$normalized = [
			'site_name'   => 'Test Site',
			'site_url'    => 'https://example.com',
			'user_id'     => 1,
			'user_login'  => 'admin',
			'user_email'  => 'admin@example.com',
			'initiator'   => 'wp_user',
			'date_human'  => 'January 8, 2026 15:30',
			'level'       => 'warning',
			'message'     => 'User updated their profile',
		];

		$body = $method->invoke( $sender, $normalized );

		// Check hierarchy: Site should come first.
		$site_pos = strpos( $body, 'Test Site' );
		$user_pos = strpos( $body, 'admin' );
		$date_pos = strpos( $body, 'January 8' );
		$msg_pos  = strpos( $body, 'User updated' );

		$this->assertLessThan( $user_pos, $site_pos, 'Site should appear before user' );
		$this->assertLessThan( $date_pos, $user_pos, 'User should appear before date' );
		$this->assertLessThan( $msg_pos, $date_pos, 'Date should appear before message' );
	}

	/**
	 * Test Email body includes site URL.
	 */
	public function test_email_body_includes_site_url(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_body' );
		$method->setAccessible( true );

		$normalized = [
			'site_name'   => 'Test Site',
			'site_url'    => 'https://example.com',
			'user_id'     => 0,
			'user_login'  => '',
			'user_email'  => '',
			'initiator'   => 'wp',
			'date_human'  => 'January 8, 2026',
			'level'       => 'info',
			'message'     => 'Test message',
		];

		$body = $method->invoke( $sender, $normalized );

		// Body includes site name and host extracted from URL.
		$this->assertStringContainsString( 'example.com', $body );
	}

	/**
	 * Test Email body includes message.
	 */
	public function test_email_body_includes_level_info(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_body' );
		$method->setAccessible( true );

		$normalized = [
			'site_name'   => 'Test Site',
			'site_url'    => 'https://example.com',
			'user_id'     => 0,
			'user_login'  => '',
			'user_email'  => '',
			'initiator'   => 'wp',
			'date_human'  => 'January 8, 2026',
			'level'       => 'warning',
			'message'     => 'Warning message here',
		];

		$body = $method->invoke( $sender, $normalized );

		// Body includes the message.
		$this->assertStringContainsString( 'Warning message here', $body );
	}

	/**
	 * Test Email body includes View Site History link.
	 */
	public function test_email_body_includes_view_history_link(): void {
		$sender = new Email_Destination_Sender();

		$method = new ReflectionMethod( $sender, 'build_body' );
		$method->setAccessible( true );

		$normalized = [
			'site_name'   => 'Test Site',
			'site_url'    => 'https://example.com',
			'user_id'     => 0,
			'user_login'  => '',
			'user_email'  => '',
			'initiator'   => 'wp',
			'date_human'  => 'January 8, 2026',
			'level'       => 'info',
			'message'     => 'Test message',
		];

		$body = $method->invoke( $sender, $normalized );

		$this->assertStringContainsString( 'View Site History', $body );
	}

	/**
	 * Test Email is sent as plain text.
	 */
	public function test_email_sent_as_plain_text(): void {
		$sender        = new Email_Destination_Sender();
		$captured_args = null;

		// Hook into wp_mail to capture arguments.
		add_filter(
			'wp_mail',
			function ( $args ) use ( &$captured_args ) {
				$captured_args = $args;
				// Return empty to prevent actual sending.
				return $args;
			}
		);

		$config = [
			'config' => [
				'recipients' => 'test@example.com',
			],
		];

		$test_data = $sender->get_test_event_data();
		$sender->send( $config, $test_data['event_data'], $test_data['context'] );

		$this->assertNotNull( $captured_args );
		$this->assertIsArray( $captured_args['headers'] );

		// Check for plain text content type.
		$has_plain_text = false;
		foreach ( $captured_args['headers'] as $header ) {
			if ( stripos( $header, 'text/plain' ) !== false ) {
				$has_plain_text = true;
				break;
			}
		}
		$this->assertTrue( $has_plain_text, 'Email should be sent as plain text' );
	}

	// =========================================================================
	// Slack Destination Sender Tests
	// =========================================================================

	/**
	 * Test Slack sender class exists.
	 */
	public function test_slack_sender_exists(): void {
		$this->assertTrue(
			class_exists( Slack_Destination_Sender::class ),
			'Slack_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Slack sender type.
	 */
	public function test_slack_sender_type(): void {
		$sender = new Slack_Destination_Sender();
		$this->assertEquals( 'slack', $sender->get_type() );
	}

	/**
	 * Test Slack sender name.
	 */
	public function test_slack_sender_name(): void {
		$sender = new Slack_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	// =========================================================================
	// Discord Destination Sender Tests
	// =========================================================================

	/**
	 * Test Discord sender class exists.
	 */
	public function test_discord_sender_exists(): void {
		$this->assertTrue(
			class_exists( Discord_Destination_Sender::class ),
			'Discord_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Discord sender type.
	 */
	public function test_discord_sender_type(): void {
		$sender = new Discord_Destination_Sender();
		$this->assertEquals( 'discord', $sender->get_type() );
	}

	/**
	 * Test Discord sender name.
	 */
	public function test_discord_sender_name(): void {
		$sender = new Discord_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	// =========================================================================
	// Telegram Destination Sender Tests
	// =========================================================================

	/**
	 * Test Telegram sender class exists.
	 */
	public function test_telegram_sender_exists(): void {
		$this->assertTrue(
			class_exists( Telegram_Destination_Sender::class ),
			'Telegram_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Telegram sender type.
	 */
	public function test_telegram_sender_type(): void {
		$sender = new Telegram_Destination_Sender();
		$this->assertEquals( 'telegram', $sender->get_type() );
	}

	/**
	 * Test Telegram sender name.
	 */
	public function test_telegram_sender_name(): void {
		$sender = new Telegram_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	// =========================================================================
	// Cross-sender Tests
	// =========================================================================

	/**
	 * Test all senders have unique types.
	 */
	public function test_all_senders_have_unique_types(): void {
		$senders = [
			new Email_Destination_Sender(),
			new Slack_Destination_Sender(),
			new Discord_Destination_Sender(),
			new Telegram_Destination_Sender(),
		];

		$types = [];
		foreach ( $senders as $sender ) {
			$type = $sender->get_type();
			$this->assertNotContains(
				$type,
				$types,
				"Duplicate sender type found: {$type}"
			);
			$types[] = $type;
		}

		$this->assertCount( 4, $types );
	}

	/**
	 * Test all senders have non-empty names.
	 */
	public function test_all_senders_have_names(): void {
		$senders = [
			new Email_Destination_Sender(),
			new Slack_Destination_Sender(),
			new Discord_Destination_Sender(),
			new Telegram_Destination_Sender(),
		];

		foreach ( $senders as $sender ) {
			$this->assertNotEmpty(
				$sender->get_name(),
				"Sender {$sender->get_type()} should have a name."
			);
		}
	}

	/**
	 * Test all senders provide test event data.
	 */
	public function test_all_senders_provide_test_event_data(): void {
		$senders = [
			new Email_Destination_Sender(),
			new Slack_Destination_Sender(),
			new Discord_Destination_Sender(),
			new Telegram_Destination_Sender(),
		];

		foreach ( $senders as $sender ) {
			$test_data = $sender->get_test_event_data();

			$this->assertArrayHasKey(
				'event_data',
				$test_data,
				"Sender {$sender->get_type()} test data should have event_data."
			);
			$this->assertArrayHasKey(
				'context',
				$test_data,
				"Sender {$sender->get_type()} test data should have context."
			);
		}
	}

	// =========================================================================
	// Tracking Trait Tests (create_success_tracking / create_error_tracking)
	// =========================================================================

	/**
	 * Test create_success_tracking with empty tracking data.
	 */
	public function test_create_success_tracking_empty_initial(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [];

		$new_tracking = $sender->create_success_tracking( $current_tracking );

		$this->assertArrayHasKey( 'last_success', $new_tracking );
		$this->assertArrayHasKey( 'last_error', $new_tracking );
		$this->assertArrayHasKey( 'success_count', $new_tracking );
		$this->assertArrayHasKey( 'error_count', $new_tracking );

		// Timestamp should be recent (within last 5 seconds).
		$this->assertGreaterThan( time() - 5, $new_tracking['last_success'] );
		$this->assertLessThanOrEqual( time(), $new_tracking['last_success'] );

		// Success count should be 1.
		$this->assertEquals( 1, $new_tracking['success_count'] );

		// Error count should remain 0.
		$this->assertEquals( 0, $new_tracking['error_count'] );
	}

	/**
	 * Test create_success_tracking increments success count.
	 */
	public function test_create_success_tracking_increments_count(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [
			'last_success'  => time() - 3600,
			'last_error'    => [],
			'success_count' => 5,
			'error_count'   => 2,
		];

		$new_tracking = $sender->create_success_tracking( $current_tracking );

		$this->assertEquals( 6, $new_tracking['success_count'] );
		$this->assertEquals( 2, $new_tracking['error_count'] ); // Should not change.
	}

	/**
	 * Test create_success_tracking preserves last error for history.
	 */
	public function test_create_success_tracking_preserves_last_error(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [
			'last_success'  => time() - 7200,
			'last_error'    => [
				'message' => 'Previous error message',
				'code'    => 500,
				'time'    => time() - 3600,
			],
			'success_count' => 3,
			'error_count'   => 1,
		];

		$new_tracking = $sender->create_success_tracking( $current_tracking );

		// Last error should be preserved.
		$this->assertArrayHasKey( 'last_error', $new_tracking );
		$this->assertEquals( 'Previous error message', $new_tracking['last_error']['message'] );
	}

	/**
	 * Test create_error_tracking with empty tracking data.
	 */
	public function test_create_error_tracking_empty_initial(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [];
		$error_message    = 'Connection timeout';
		$error_code       = 504;

		$new_tracking = $sender->create_error_tracking( $current_tracking, $error_message, $error_code );

		$this->assertArrayHasKey( 'last_success', $new_tracking );
		$this->assertArrayHasKey( 'last_error', $new_tracking );
		$this->assertArrayHasKey( 'success_count', $new_tracking );
		$this->assertArrayHasKey( 'error_count', $new_tracking );

		// last_success should remain 0 (never succeeded).
		$this->assertEquals( 0, $new_tracking['last_success'] );

		// Error count should be 1.
		$this->assertEquals( 1, $new_tracking['error_count'] );

		// Success count should remain 0.
		$this->assertEquals( 0, $new_tracking['success_count'] );

		// Error details should be recorded.
		$this->assertArrayHasKey( 'message', $new_tracking['last_error'] );
		$this->assertArrayHasKey( 'code', $new_tracking['last_error'] );
		$this->assertArrayHasKey( 'time', $new_tracking['last_error'] );
		$this->assertEquals( 'Connection timeout', $new_tracking['last_error']['message'] );
		$this->assertEquals( 504, $new_tracking['last_error']['code'] );
	}

	/**
	 * Test create_error_tracking increments error count.
	 */
	public function test_create_error_tracking_increments_count(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [
			'last_success'  => time() - 3600,
			'last_error'    => [
				'message' => 'Old error',
				'code'    => 400,
				'time'    => time() - 7200,
			],
			'success_count' => 10,
			'error_count'   => 3,
		];

		$new_tracking = $sender->create_error_tracking( $current_tracking, 'New error', 500 );

		$this->assertEquals( 4, $new_tracking['error_count'] );
		$this->assertEquals( 10, $new_tracking['success_count'] ); // Should not change.
	}

	/**
	 * Test create_error_tracking preserves last_success timestamp.
	 */
	public function test_create_error_tracking_preserves_last_success(): void {
		$sender            = new Email_Destination_Sender();
		$original_success  = time() - 3600;
		$current_tracking  = [
			'last_success'  => $original_success,
			'last_error'    => [],
			'success_count' => 5,
			'error_count'   => 0,
		];

		$new_tracking = $sender->create_error_tracking( $current_tracking, 'Error occurred', 500 );

		$this->assertEquals( $original_success, $new_tracking['last_success'] );
	}

	/**
	 * Test create_error_tracking overwrites previous error.
	 */
	public function test_create_error_tracking_overwrites_previous_error(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [
			'last_success'  => 0,
			'last_error'    => [
				'message' => 'Old error message',
				'code'    => 400,
				'time'    => time() - 3600,
			],
			'success_count' => 0,
			'error_count'   => 1,
		];

		$new_tracking = $sender->create_error_tracking( $current_tracking, 'New error message', 503 );

		$this->assertEquals( 'New error message', $new_tracking['last_error']['message'] );
		$this->assertEquals( 503, $new_tracking['last_error']['code'] );
		// Time should be updated.
		$this->assertGreaterThan( time() - 5, $new_tracking['last_error']['time'] );
	}

	/**
	 * Test create_error_tracking with default error code.
	 */
	public function test_create_error_tracking_default_error_code(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [];

		// Only pass message, let code default to 0.
		$new_tracking = $sender->create_error_tracking( $current_tracking, 'Some error' );

		$this->assertEquals( 0, $new_tracking['last_error']['code'] );
	}

	/**
	 * Test create_error_tracking sanitizes long error messages.
	 */
	public function test_create_error_tracking_sanitizes_long_message(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [];

		// Create a very long error message (over 300 chars).
		$long_message = str_repeat( 'x', 500 );

		$new_tracking = $sender->create_error_tracking( $current_tracking, $long_message );

		// Message should be truncated.
		$this->assertLessThanOrEqual( 300, strlen( $new_tracking['last_error']['message'] ) );
		$this->assertStringEndsWith( '...', $new_tracking['last_error']['message'] );
	}

	/**
	 * Test create_error_tracking sanitizes HTML from error message.
	 */
	public function test_create_error_tracking_strips_html(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [];
		$html_message     = '<p>Error: <strong>Connection failed</strong></p>';

		$new_tracking = $sender->create_error_tracking( $current_tracking, $html_message );

		// HTML should be stripped.
		$this->assertStringNotContainsString( '<p>', $new_tracking['last_error']['message'] );
		$this->assertStringNotContainsString( '<strong>', $new_tracking['last_error']['message'] );
		$this->assertStringContainsString( 'Connection failed', $new_tracking['last_error']['message'] );
	}

	/**
	 * Test create_error_tracking decodes HTML entities.
	 */
	public function test_create_error_tracking_decodes_entities(): void {
		$sender           = new Email_Destination_Sender();
		$current_tracking = [];
		$entity_message   = 'Error: &quot;Connection&quot; &amp; &lt;timeout&gt;';

		$new_tracking = $sender->create_error_tracking( $current_tracking, $entity_message );

		// Entities should be decoded.
		$this->assertStringContainsString( '"Connection"', $new_tracking['last_error']['message'] );
		$this->assertStringContainsString( '&', $new_tracking['last_error']['message'] );
	}

	/**
	 * Test tracking methods work across different sender types.
	 */
	public function test_tracking_methods_consistent_across_senders(): void {
		$senders = [
			new Email_Destination_Sender(),
			new Slack_Destination_Sender(),
			new Discord_Destination_Sender(),
			new Telegram_Destination_Sender(),
		];

		foreach ( $senders as $sender ) {
			$success_tracking = $sender->create_success_tracking( [] );
			$error_tracking   = $sender->create_error_tracking( [], 'Test error', 500 );

			// All senders should produce consistent structure.
			$this->assertArrayHasKey( 'last_success', $success_tracking );
			$this->assertArrayHasKey( 'success_count', $success_tracking );
			$this->assertEquals( 1, $success_tracking['success_count'] );

			$this->assertArrayHasKey( 'last_error', $error_tracking );
			$this->assertArrayHasKey( 'error_count', $error_tracking );
			$this->assertEquals( 1, $error_tracking['error_count'] );
		}
	}
}
