<?php

namespace Simple_History\Tests\WPUnit;

use Simple_History\Integrations\Integrations\File_Integration;

/**
 * Test the File Integration implementation.
 */
class FileIntegrationTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var File_Integration
	 */
	private $integration;

	/**
	 * @var string
	 */
	private $test_log_dir;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->integration = new File_Integration();
		
		// Create a test log directory
		$this->test_log_dir = sys_get_temp_dir() . '/simple-history-test-' . uniqid();
		
		// Clean up any existing settings
		delete_option( 'simple_history_integration_file' );
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
		delete_option( 'simple_history_integration_file' );
	}

	/**
	 * Test integration properties.
	 */
	public function test_integration_properties() {
		$this->assertEquals( 'file', $this->integration->get_slug() );
		$this->assertEquals( 'File Backup', $this->integration->get_name() );
		$this->assertStringContainsString( 'save all events', $this->integration->get_description() );
		$this->assertFalse( $this->integration->supports_async() );
	}

	/**
	 * Test default settings.
	 */
	public function test_default_settings() {
		$settings = $this->integration->get_settings();

		$this->assertFalse( $settings['enabled'] );
		$this->assertEquals( 'daily', $settings['rotation_frequency'] );
		$this->assertEquals( 30, $settings['keep_files'] );
	}

	/**
	 * Test log file path generation.
	 */
	public function test_log_file_path_generation() {
		// Test daily rotation
		$this->integration->set_setting( 'rotation_frequency', 'daily' );
		$path = $this->invoke_method( $this->integration, 'get_log_file_path', [] );
		$this->assertStringContainsString( 'events-' . gmdate( 'Y-m-d' ) . '.log', $path );

		// Test weekly rotation
		$this->integration->set_setting( 'rotation_frequency', 'weekly' );
		$path = $this->invoke_method( $this->integration, 'get_log_file_path', [] );
		$this->assertStringContainsString( 'events-' . gmdate( 'Y' ) . '-W' . gmdate( 'W' ) . '.log', $path );

		// Test monthly rotation
		$this->integration->set_setting( 'rotation_frequency', 'monthly' );
		$path = $this->invoke_method( $this->integration, 'get_log_file_path', [] );
		$this->assertStringContainsString( 'events-' . gmdate( 'Y-m' ) . '.log', $path );

		// Test no rotation
		$this->integration->set_setting( 'rotation_frequency', 'never' );
		$path = $this->invoke_method( $this->integration, 'get_log_file_path', [] );
		$this->assertStringContainsString( 'events.log', $path );
		$this->assertStringNotContainsString( gmdate( 'Y' ), basename( $path ) );
	}

	/**
	 * Test log entry formatting.
	 */
	public function test_log_entry_formatting() {
		$event_data = [
			'id' => 123,
			'date' => '2025-01-23 12:00:00',
			'logger' => 'TestLogger',
			'level' => 'info',
			'message' => 'Test message',
			'initiator' => 'wp_user',
			'context' => [
				'user_id' => 1,
				'user_login' => 'admin',
			],
		];

		$formatted_message = 'Test message processed';

		$log_entry = $this->invoke_method( 
			$this->integration, 
			'format_log_entry', 
			[ $event_data, $formatted_message ] 
		);

		// Check the log entry format (RFC 5424-like)
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $log_entry );
		$this->assertStringContainsString( 'INFO', $log_entry );
		$this->assertStringContainsString( 'TestLogger', $log_entry );
		$this->assertStringContainsString( 'Test message processed', $log_entry );
		$this->assertStringContainsString( 'wp_user', $log_entry );
		$this->assertStringContainsString( "\n", $log_entry );
	}

	/**
	 * Test sending an event (writing to file).
	 */
	public function test_send_event() {
		// Enable the integration
		$this->integration->set_setting( 'enabled', true );

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
		$result = $this->integration->send_event( $event_data, $formatted_message );
		$this->assertTrue( $result );

		// Verify the log file exists and contains the event
		$log_file = $this->invoke_method( $this->integration, 'get_log_file_path', [] );
		$this->assertFileExists( $log_file );

		$content = file_get_contents( $log_file );
		$this->assertStringContainsString( 'WARNING', $content );
		$this->assertStringContainsString( 'SimplePostLogger', $content );
		$this->assertStringContainsString( 'Post "Hello World" was updated', $content );
		$this->assertStringContainsString( 'wp_cli', $content );

		// Clean up
		@unlink( $log_file );
	}

	/**
	 * Test write buffering.
	 */
	public function test_write_buffering() {
		// Enable buffering by sending multiple events
		$this->integration->set_setting( 'enabled', true );

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

			$result = $this->integration->send_event( $event_data, "Event number $i" );
			$this->assertTrue( $result );
		}

		// Force buffer flush
		$this->invoke_method( $this->integration, 'flush_write_buffer', [] );

		// Verify all events were written
		$log_file = $this->invoke_method( $this->integration, 'get_log_file_path', [] );
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
		$this->integration->set_setting( 'enabled', true );
		$this->integration->set_setting( 'keep_files', 2 );
		$this->integration->set_setting( 'rotation_frequency', 'daily' );

		// Get the log directory
		$log_dir = $this->invoke_method( $this->integration, 'get_log_directory_path', [] );
		
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
		$this->invoke_method( $this->integration, 'cleanup_old_files', [] );

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
			$this->integration, 
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
	 * Test settings info HTML output.
	 */
	public function test_settings_info_html() {
		$html = $this->integration->get_settings_info_after_fields_html();

		$this->assertIsString( $html );
		$this->assertStringContainsString( 'Files are saved to directory:', $html );
		$this->assertStringContainsString( '<code>', $html );
		$this->assertStringContainsString( 'simple-history-logs-', $html );
	}

	/**
	 * Test that disabled integration doesn't write files.
	 */
	public function test_disabled_integration_no_write() {
		// Ensure integration is disabled
		$this->integration->set_setting( 'enabled', false );

		$event_data = [
			'logger' => 'TestLogger',
			'level' => 'info',
			'message' => 'Test',
		];

		// Try to send event
		$result = $this->integration->send_event( $event_data, 'Test message' );
		$this->assertTrue( $result ); // send_event returns true but doesn't write

		// Verify no log file was created
		$log_file = $this->invoke_method( $this->integration, 'get_log_file_path', [] );
		$this->assertFileDoesNotExist( $log_file );
	}

	/**
	 * Test retry mechanism on write failure.
	 */
	public function test_write_retry_on_failure() {
		$this->integration->set_setting( 'enabled', true );

		// Create a read-only directory to force write failure
		$readonly_dir = $this->test_log_dir . '-readonly';
		mkdir( $readonly_dir, 0444 ); // Read-only

		// Try to write to the read-only directory
		// This should fail but not throw an exception
		$result = $this->invoke_method(
			$this->integration,
			'write_to_file_with_retry',
			[ $readonly_dir . '/test.log', 'Test content' ]
		);

		$this->assertFalse( $result );

		// Clean up
		chmod( $readonly_dir, 0755 );
		rmdir( $readonly_dir );
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
}