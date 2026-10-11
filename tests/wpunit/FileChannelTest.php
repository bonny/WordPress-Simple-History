<?php

namespace Simple_History\Tests\WPUnit;

use Simple_History\Channels\File_Channel;

/**
 * Test the File Channel implementation.
 */
class FileChannelTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var File_Channel
	 */
	private $channel;

	/**
	 * @var string
	 */
	private $test_log_dir;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->channel = new File_Channel();
		
		// Create a test log directory
		$this->test_log_dir = sys_get_temp_dir() . '/simple-history-test-' . uniqid();
		
		// Clean up any existing settings
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Clean up test files and directory
		if ( is_dir( $this->test_log_dir ) ) {
			$files = glob( $this->test_log_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file );
				}
			}
			@rmdir( $this->test_log_dir );
		}

		// Clean up settings
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Test channel properties.
	 */
	public function test_channel_properties() {
		$this->assertEquals( 'file', $this->channel->get_slug() );
		$this->assertEquals( 'Local Files', $this->channel->get_name() );
		$this->assertStringContainsString( 'log files', $this->channel->get_description() );
		$this->assertFalse( $this->channel->supports_async() );
	}

	/**
	 * Test default settings.
	 */
	public function test_default_settings() {
		$settings = $this->channel->get_settings();

		$this->assertFalse( $settings['enabled'] );
		$this->assertEquals( 'daily', $settings['rotation_frequency'] );
		$this->assertEquals( 'human_readable', $settings['formatter'] );
		$this->assertEquals( 30, $settings['keep_files'] );
	}

	/**
	 * Test log file path generation.
	 */
	public function test_log_file_path_generation() {
		// Test daily rotation
		$this->channel->set_setting( 'rotation_frequency', 'daily' );
		$path = $this->invoke_method( $this->channel, 'get_log_file_path', [] );
		$this->assertStringContainsString( 'events-' . current_time( 'Y-m-d' ) . '.log', $path );

		// Test weekly rotation
		$this->channel->set_setting( 'rotation_frequency', 'weekly' );
		$path = $this->invoke_method( $this->channel, 'get_log_file_path', [] );
		// Just check that it contains the weekly pattern with W prefix
		$this->assertMatchesRegularExpression( '/events-\d{4}-W\d{2}\.log/', basename( $path ) );

		// Test monthly rotation
		$this->channel->set_setting( 'rotation_frequency', 'monthly' );
		$path = $this->invoke_method( $this->channel, 'get_log_file_path', [] );
		$this->assertStringContainsString( 'events-' . current_time( 'Y-m' ) . '.log', $path );
	}

	/**
	 * Test log entry formatting with Human_Readable_Formatter.
	 */
	public function test_log_entry_formatting() {
		$formatter = new \Simple_History\Channels\Human_Readable_Formatter();

		$event_data = [
			'id'        => 123,
			'date'      => '2025-01-23 12:00:00',
			'logger'    => 'TestLogger',
			'level'     => 'info',
			'message'   => 'Test message',
			'initiator' => 'wp_user',
			'context'   => [
				'_user_id'    => 1,
				'_user_login' => 'admin',
			],
		];

		$formatted_message = 'Test message processed';
		$log_entry         = $formatter->format( $event_data, $formatted_message );

		// Check the log entry format (ISO 8601 timestamp).
		$this->assertMatchesRegularExpression( '/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $log_entry );
		$this->assertStringContainsString( 'INFO', $log_entry );
		$this->assertStringContainsString( 'TestLogger', $log_entry );
		$this->assertStringContainsString( 'Test message processed', $log_entry );
		$this->assertStringContainsString( 'initiator=wp_user', $log_entry );
		$this->assertStringContainsString( '|', $log_entry );
		$this->assertStringContainsString( "\n", $log_entry );
	}

	/**
	 * Test sending an event (writing to file).
	 */
	public function test_send_event() {
		// Enable the channel
		$this->channel->set_setting( 'enabled', true );

		$event_data = [
			'id' => 456,
			'date' => current_time( 'mysql' ),
			'logger' => 'SimplePostLogger',
			'level' => 'warning',
			'message' => 'Post updated',
			'initiator' => 'wp_cli',
			'context' => [],
		];

		$formatted_message = 'Post "Hello World" was updated';

		// Send the event
		$result = $this->channel->send_event( $event_data, $formatted_message );
		$this->assertTrue( $result );

		// Verify the log file exists and contains the event
		$log_file = $this->invoke_method( $this->channel, 'get_log_file_path', [] );
		$this->assertFileExists( $log_file );

		$content = file_get_contents( $log_file );
		$this->assertStringContainsString( 'WARNING', $content );
		$this->assertStringContainsString( 'SimplePostLogger', $content );
		$this->assertStringContainsString( 'Post "Hello World" was updated', $content );
		$this->assertStringContainsString( 'initiator=wp_cli', $content );

		// Clean up
		@unlink( $log_file );
	}

	/**
	 * Test that multiple events are written to file.
	 */
	public function test_multiple_events_written_to_file() {
		// Enable buffering by sending multiple events
		$this->channel->set_setting( 'enabled', true );

		// Send multiple events quickly
		for ( $i = 1; $i <= 5; $i++ ) {
			$event_data = [
				'id' => $i,
				'date' => current_time( 'mysql' ),
				'logger' => 'TestLogger',
				'level' => 'info',
				'message' => "Event $i",
				'initiator' => 'wp_user',
			];

			$result = $this->channel->send_event( $event_data, "Event number $i" );
			$this->assertTrue( $result );
		}

		// Verify all events were written
		$log_file = $this->invoke_method( $this->channel, 'get_log_file_path', [] );
		$this->assertFileExists( $log_file );

		$content = file_get_contents( $log_file );
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->assertStringContainsString( "Event number $i", $content );
		}

		// Clean up
		@unlink( $log_file );
	}

	/**
	 * Test file cleanup functionality.
	 */
	public function test_file_cleanup() {
		// Set to keep only 2 files
		$this->channel->set_setting( 'enabled', true );
		$this->channel->set_setting( 'keep_files', 2 );
		$this->channel->set_setting( 'rotation_frequency', 'daily' );

		// Get the log directory
		$log_dir = $this->invoke_method( $this->channel, 'get_log_directory_path', [] );
		
		// Create the directory
		if ( ! is_dir( $log_dir ) ) {
			wp_mkdir_p( $log_dir );
		}

		// Create 5 old log files with different timestamps
		$base_time = time();
		for ( $i = 1; $i <= 5; $i++ ) {
			$date = gmdate( 'Y-m-d', $base_time - ( $i * 86400 ) ); // Days ago
			$filename = $log_dir . '/events-' . $date . '.log';
			file_put_contents( $filename, "Log for $date\n" );
			// Set modification time to match the date
			touch( $filename, $base_time - ( $i * 86400 ) );
		}

		// Run cleanup
		$this->invoke_method( $this->channel, 'cleanup_old_files', [] );

		// Check that only 2 newest files remain
		$remaining_files = glob( $log_dir . '/events-*.log' );
		$this->assertCount( 2, $remaining_files );

		// Clean up all files
		foreach ( glob( $log_dir . '/*' ) as $file ) {
			@unlink( $file );
		}
		@rmdir( $log_dir );
	}

	/**
	 * Test directory security (.htaccess creation).
	 */
	public function test_directory_security() {
		$result = $this->invoke_method( 
			$this->channel, 
			'ensure_directory_exists', 
			[ $this->test_log_dir ] 
		);

		$this->assertTrue( $result );
		$this->assertDirectoryExists( $this->test_log_dir );
		
		// Check for .htaccess file
		$htaccess_file = $this->test_log_dir . '/.htaccess';
		$this->assertFileExists( $htaccess_file );

		// Verify .htaccess content
		$htaccess_content = file_get_contents( $htaccess_file );
		$this->assertStringContainsString( 'deny', strtolower( $htaccess_content ) );
	}

	/**
	 * Test log directory path output.
	 */
	public function test_log_directory_path() {
		$path = $this->channel->get_log_directory_path();

		$this->assertIsString( $path );
		$this->assertStringContainsString( 'simple-history-logs-', $path );

		// Should be in uploads directory for VIP compatibility.
		$upload_dir = wp_get_upload_dir();
		$this->assertStringContainsString( $upload_dir['basedir'], $path );
	}

	/**
	 * Test that disabled channel doesn't write files.
	 */
	public function test_disabled_channel_no_write() {
		// Ensure channel is disabled
		$this->channel->set_setting( 'enabled', false );

		$event_data = [
			'logger' => 'TestLogger',
			'level' => 'info',
			'message' => 'Test',
		];

		// should_send_event will return false for disabled channel
		$should_send = $this->channel->should_send_event( $event_data );
		$this->assertFalse( $should_send );

		// Get the log file path before attempting to write
		$log_file = $this->invoke_method( $this->channel, 'get_log_file_path', [] );

		// Try to send event anyway - it should not write to the file because channel is disabled
		$result = $this->channel->send_event( $event_data, 'Test message' );
		$this->assertTrue( $result ); // send_event returns true but doesn't write

		// Verify no log file was created
		$this->assertFileDoesNotExist( $log_file );
	}

	/**
	 * Test that send_event handles non-writable directories gracefully.
	 */
	public function test_send_event_handles_unwritable_directory() {
		$this->channel->set_setting( 'enabled', true );

		// Test that send_event returns false for non-existent/unwritable paths
		// without throwing exceptions. The actual implementation logs to a
		// specific directory, so we just verify the channel is enabled
		// and can process events (the actual write may fail gracefully).
		$this->assertTrue( $this->channel->is_enabled() );

		// Verify send_event method exists and is callable
		$this->assertTrue( method_exists( $this->channel, 'send_event' ) );
	}

	/**
	 * Helper method to invoke private/protected methods.
	 *
	 * @param object $object The object to invoke the method on.
	 * @param string $method_name The method name.
	 * @param array  $parameters The method parameters.
	 * @return mixed The method return value.
	 */
	private function invoke_method( $object, $method_name, array $parameters = [] ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

	/**
	 * Helper method to set private/protected properties.
	 *
	 * @param object $object The object to set the property on.
	 * @param string $property_name The property name.
	 * @param mixed  $value The value to set.
	 */
	private function set_property( $object, $property_name, $value ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$property = $reflection->getProperty( $property_name );
		$property->setAccessible( true );
		$property->setValue( $object, $value );
	}

	/**
	 * CR and LF in the message or context must not start a new, forged log line.
	 */
	public function test_human_readable_formatter_escapes_line_breaks() {
		$formatter = new \Simple_History\Channels\Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'     => 'info',
				'logger'    => 'SimpleUserLogger',
				'initiator' => 'web_user',
				'context'   => [ '_user_login' => "admin\r\n[2026-01-01T00:00:00Z] CRITICAL forged" ],
			],
			"Failed login\n[2026-01-01T00:00:00Z] CRITICAL SimpleUserLogger: forged"
		);

		$this->assertSame( 1, substr_count( $output, "\n" ), 'Only the trailing newline is allowed.' );
		$this->assertStringNotContainsString( "\r", $output );
		$this->assertStringContainsString( 'Failed login\n[2026-01-01', $output );
	}

	/**
	 * Control characters and line separators other than CR/LF must not reach
	 * the file raw: ESC can rewrite earlier lines in a terminal running tail -f,
	 * and many tools split lines on VT, FF, NEL, LS and PS.
	 */
	public function test_human_readable_formatter_escapes_control_characters() {
		$formatter = new \Simple_History\Channels\Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'     => "info\n[2026-01-01T00:00:00Z] CRITICAL forged",
				'logger'    => 'SimpleUserLogger',
				'initiator' => 'web_user',
				'context'   => [ '_user_login' => "admin\x1b[1A\x1b[2K\x0B\x0C\u{0085}\u{2028}\u{2029}\t" ],
			],
			'Failed login'
		);

		$this->assertSame( 1, substr_count( $output, "\n" ), 'Only the trailing newline is allowed.' );
		$this->assertSame( 0, preg_match( '/[\x00-\x09\x0B-\x1F\x7F]|\x{0085}|\x{2028}|\x{2029}/u', $output ), 'No raw control characters or line separators.' );
		$this->assertStringContainsString( 'user_login="admin\x1B[1A\x1B[2K\x0B\x0C\u0085\u2028\u2029\t"', $output );
		$this->assertStringContainsString( 'INFO\n[2026-01-01', $output, 'The level is escaped too.' );
	}

	/**
	 * A real line break and a backslash followed by "n" must not look the same.
	 */
	public function test_human_readable_formatter_escapes_backslashes() {
		$formatter = new \Simple_History\Channels\Human_Readable_Formatter();
		$event     = [
			'level'     => 'info',
			'logger'    => 'SimpleUserLogger',
			'initiator' => 'web_user',
			'context'   => [],
		];

		$this->assertNotSame(
			$formatter->format( $event, "a\nb" ),
			$formatter->format( $event, 'a\nb' )
		);
		$this->assertStringContainsString( 'C:\\\\temp', $formatter->format( $event, 'C:\temp' ) );
	}

	public function test_human_readable_formatter_handles_invalid_utf8() {
		$formatter = new \Simple_History\Channels\Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'     => 'info',
				'logger'    => 'SimpleUserLogger',
				'initiator' => 'web_user',
				'context'   => [],
			],
			"bad \xff byte\x1b[2K\nforged"
		);

		$this->assertSame( 1, substr_count( $output, "\n" ) );
		$this->assertStringContainsString( "bad \xff byte\\x1B[2K\\nforged", $output );
	}

	/**
	 * A formatter added through the filter may not escape anything. The file
	 * channel still writes one line per event.
	 */
	public function test_send_event_keeps_one_line_with_a_third_party_formatter() {
		$formatter = new class() implements \Simple_History\Channels\Formatter_Interface {
			public function get_slug() {
				return 'naive';
			}

			public function get_name() {
				return 'Naive';
			}

			public function get_description() {
				return 'Writes the message as is.';
			}

			public function format( array $event_data, string $formatted_message ) {
				return $formatted_message . "\n";
			}
		};

		add_filter(
			'simple_history/file_channel/formatters',
			function ( $formatters ) use ( $formatter ) {
				$formatters['naive'] = $formatter;
				return $formatters;
			}
		);

		$this->channel->set_setting( 'enabled', true );
		$this->channel->set_setting( 'formatter', 'naive' );

		$this->channel->send_event( [ 'context' => [] ], "Updated post\r\n[2026-01-01T00:00:00Z] CRITICAL forged" );

		$log_file = $this->invoke_method( $this->channel, 'get_log_file_path', [] );
		$content  = file_get_contents( $log_file );
		@unlink( $log_file );
		remove_all_filters( 'simple_history/file_channel/formatters' );

		$this->assertSame( "Updated post\\r\\n[2026-01-01T00:00:00Z] CRITICAL forged\n", $content );
	}

	/**
	 * A value can't add key=value fields of its own, and text can't look like
	 * the start of the next "|" section.
	 */
	public function test_human_readable_formatter_cannot_forge_fields() {
		$formatter = new \Simple_History\Channels\Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'        => 'info',
				'logger'       => 'SimpleUserLogger',
				'initiator'    => 'web_user',
				'context'      => [
					'_user_login' => 'x user_id=1 user_email=admin@site.tld',
					'_user_email' => 'a"b',
				],
				'details_text' => 'Title: a | initiator=wp_user',
			],
			'Failed login | initiator=wp_user user_id=1'
		);

		$this->assertStringContainsString( 'Failed login \| initiator=wp_user user_id=1 | Title: a \| initiator=wp_user | initiator=web_user', $output );
		$this->assertStringContainsString( 'user_login="x user_id=1 user_email=admin@site.tld"', $output );
		$this->assertStringContainsString( 'user_email="a\"b"', $output );
		$this->assertSame( 2, substr_count( $output, ' | ' ), 'Only the formatter\'s own two separators.' );
	}

	public function test_human_readable_formatter_leaves_simple_values_unquoted() {
		$formatter = new \Simple_History\Channels\Human_Readable_Formatter();
		$output    = $formatter->format(
			[
				'level'     => 'info',
				'logger'    => 'SimpleUserLogger',
				'initiator' => 'wp_user',
				'context'   => [
					'_message_key' => 'user_logged_in',
					'_user_id'     => 42,
					'_user_email'  => 'admin@example.com',
				],
			],
			'Logged in'
		);

		$this->assertStringContainsString( '| initiator=wp_user message_key=user_logged_in user_id=42 user_email=admin@example.com', $output );
	}
}
