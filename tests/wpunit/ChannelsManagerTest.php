<?php

namespace Simple_History\Tests\WPUnit;

use Simple_History\Simple_History;
use Simple_History\Channels\Channels_Manager;
use Simple_History\Channels\File_Channel;

// Include test fixture
require_once __DIR__ . '/fixtures/class-example-channel.php';
use Simple_History\Channels\Example_Channel;

/**
 * Test the Integrations Manager functionality.
 */
class ChannelsManagerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var Simple_History
	 */
	private $simple_history;

	/**
	 * @var Channels_Manager
	 */
	private $manager;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Get Simple History instance
		$this->simple_history = Simple_History::get_instance();

		// Create a new manager instance for testing
		$this->manager = new Channels_Manager( $this->simple_history );
		$this->manager->loaded();
	}

	/**
	 * Test that the manager can register integrations.
	 */
	public function test_register_channel() {
		$channel = new Example_Channel();

		// Register the channel
		$result = $this->manager->register_channel( $channel );
		$this->assertTrue( $result );

		// Try to register the same channel again - should fail
		$result = $this->manager->register_channel( $channel );
		$this->assertFalse( $result );

		// Check that the channel is registered
		$registered = $this->manager->get_channel( 'example' );
		$this->assertInstanceOf( Example_Channel::class, $registered );
	}

	/**
	 * Test getting all integrations.
	 */
	public function test_get_channels() {
		$channels = $this->manager->get_channels();

		$this->assertIsArray( $channels );
		// File channel should be registered by default
		$this->assertArrayHasKey( 'file', $channels );
		$this->assertInstanceOf( File_Channel::class, $channels['file'] );
	}

	/**
	 * Test getting enabled integrations.
	 */
	public function test_get_enabled_channels() {
		// Initially, no integrations should be enabled
		$enabled = $this->manager->get_enabled_channels();
		$this->assertIsArray( $enabled );
		$this->assertEmpty( $enabled );

		// Enable file channel
		$file_channel = $this->manager->get_channel( 'file' );
		$file_channel->set_setting( 'enabled', true );

		// Now it should be in the enabled list
		$enabled = $this->manager->get_enabled_channels();
		$this->assertCount( 1, $enabled );
		$this->assertArrayHasKey( 'file', $enabled );

		// Clean up
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Test event processing.
	 */
	public function test_process_logged_event() {
		// Enable file channel
		$file_channel = $this->manager->get_channel( 'file' );
		$file_channel->set_setting( 'enabled', true );

		// Mock event data
		$context = [
			'_user_id' => 1,
			'_user_login' => 'admin',
			'post_id' => 123,
			'post_title' => 'Test Post',
		];

		$data = [
			'id' => 999,
			'date' => current_time( 'mysql' ),
			'logger' => 'SimplePostLogger',
			'level' => 'info',
			'message' => 'Updated post "{post_title}"',
			'initiator' => 'wp_user',
		];

		$logger = null; // Can be null for this test

		// Process the event (this should write to a file)
		$this->manager->process_logged_event( $context, $data, $logger );

		// Verify the log file was created
		$log_dir = $this->invoke_method( $file_channel, 'get_log_directory_path', [] );
		$log_file = $log_dir . '/events-' . current_time( 'Y-m-d' ) . '.log';

		$this->assertFileExists( $log_file );

		// Read the log file and verify content
		$log_content = file_get_contents( $log_file );
		$this->assertStringContainsString( 'Test Post', $log_content );
		$this->assertStringContainsString( 'INFO', $log_content );
		$this->assertStringContainsString( 'SimplePostLogger', $log_content );

		// Clean up
		@unlink( $log_file );
		@rmdir( $log_dir );
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Test that disabled integrations don't receive events.
	 */
	public function test_disabled_channel_no_events() {
		// Ensure file channel is disabled
		$file_channel = $this->manager->get_channel( 'file' );
		$file_channel->set_setting( 'enabled', false );

		// Mock event data
		$context = [];
		$data = [
			'logger' => 'TestLogger',
			'level' => 'info',
			'message' => 'Test message',
		];
		$logger = null;

		// Process the event
		$this->manager->process_logged_event( $context, $data, $logger );

		// Verify no log file was created
		$log_dir = $this->invoke_method( $file_channel, 'get_log_directory_path', [] );
		$log_file = $log_dir . '/events-' . current_time( 'Y-m-d' ) . '.log';

		$this->assertFileDoesNotExist( $log_file );

		// Clean up
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Test message formatting with context interpolation.
	 */
	public function test_message_formatting() {
		// Test the format_message_for_channel method
		$channel = new File_Channel();

		$event_data = [
			'message' => 'User {user_name} updated post "{post_title}" with ID {post_id}',
			'context' => [
				'user_name' => 'John Doe',
				'post_title' => 'My Test Post',
				'post_id' => '123',
				'_internal_field' => 'should not be interpolated',
			],
		];

		$formatted = $this->invoke_method(
			$this->manager,
			'format_message_for_channel',
			[ $channel, $event_data ]
		);

		$this->assertEquals( 'User John Doe updated post "My Test Post" with ID 123', $formatted );
		$this->assertStringNotContainsString( '{', $formatted );
		$this->assertStringNotContainsString( '}', $formatted );
		$this->assertStringNotContainsString( '_internal_field', $formatted );
	}

	/**
	 * Test that manager is loaded correctly.
	 */
	public function test_manager_loaded() {
		// Test that the manager has been loaded with default integrations
		$channels = $this->manager->get_channels();
		$this->assertIsArray( $channels );
		$this->assertArrayHasKey( 'file', $channels );
	}

	/**
	 * Test channel action hook.
	 */
	public function test_channel_registration_hook() {
		$hook_called = false;
		$received_channel = null;
		$received_manager = null;

		// Add hook listener
		add_action( 'simple_history/channels/registered', function( $channel, $manager ) use ( &$hook_called, &$received_channel, &$received_manager ) {
			$hook_called = true;
			$received_channel = $channel;
			$received_manager = $manager;
		}, 10, 2 );

		// Register a new channel
		$channel = new Example_Channel();
		$this->manager->register_channel( $channel );

		// Verify hook was called
		$this->assertTrue( $hook_called );
		$this->assertSame( $channel, $received_channel );
		$this->assertSame( $this->manager, $received_manager );

		// Clean up
		remove_all_actions( 'simple_history/channels/registered' );
	}

	/**
	 * Test that external integrations can be registered.
	 */
	public function test_external_channel_registration() {
		$external_registered = false;

		// Simulate external plugin registering a channel
		add_action( 'simple_history/channels/register', function( $manager ) use ( &$external_registered ) {
			$channel = new Example_Channel();
			$result = $manager->register_channel( $channel );
			$external_registered = $result;
		} );

		// Trigger the action
		do_action( 'simple_history/channels/register', $this->manager );

		// Verify registration worked
		$this->assertTrue( $external_registered );
		$this->assertInstanceOf( Example_Channel::class, $this->manager->get_channel( 'example' ) );
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