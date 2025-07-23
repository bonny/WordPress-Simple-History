<?php

namespace Simple_History\Tests\WPUnit;

use Simple_History\Simple_History;
use Simple_History\Integrations\Integrations_Manager;
use Simple_History\Integrations\Integrations\File_Integration;
use Simple_History\Integrations\Integrations\Example_Integration;

/**
 * Test the Integrations Manager functionality.
 */
class IntegrationsManagerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var Simple_History
	 */
	private $simple_history;

	/**
	 * @var Integrations_Manager
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
		$this->manager = new Integrations_Manager( $this->simple_history );
		$this->manager->loaded();
	}

	/**
	 * Test that the manager can register integrations.
	 */
	public function test_register_integration() {
		$integration = new Example_Integration();

		// Register the integration
		$result = $this->manager->register_integration( $integration );
		$this->assertTrue( $result );

		// Try to register the same integration again - should fail
		$result = $this->manager->register_integration( $integration );
		$this->assertFalse( $result );

		// Check that the integration is registered
		$registered = $this->manager->get_integration( 'example' );
		$this->assertInstanceOf( Example_Integration::class, $registered );
	}

	/**
	 * Test getting all integrations.
	 */
	public function test_get_integrations() {
		$integrations = $this->manager->get_integrations();

		$this->assertIsArray( $integrations );
		// File integration should be registered by default
		$this->assertArrayHasKey( 'file', $integrations );
		$this->assertInstanceOf( File_Integration::class, $integrations['file'] );
	}

	/**
	 * Test getting enabled integrations.
	 */
	public function test_get_enabled_integrations() {
		// Initially, no integrations should be enabled
		$enabled = $this->manager->get_enabled_integrations();
		$this->assertIsArray( $enabled );
		$this->assertEmpty( $enabled );

		// Enable file integration
		$file_integration = $this->manager->get_integration( 'file' );
		$file_integration->set_setting( 'enabled', true );

		// Now it should be in the enabled list
		$enabled = $this->manager->get_enabled_integrations();
		$this->assertCount( 1, $enabled );
		$this->assertArrayHasKey( 'file', $enabled );

		// Clean up
		delete_option( 'simple_history_integration_file' );
	}

	/**
	 * Test event processing.
	 */
	public function test_process_logged_event() {
		// Enable file integration
		$file_integration = $this->manager->get_integration( 'file' );
		$file_integration->set_setting( 'enabled', true );

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
		$log_dir = $this->invoke_method( $file_integration, 'get_log_directory_path', [] );
		$log_file = $log_dir . '/events-' . gmdate( 'Y-m-d' ) . '.log';

		$this->assertFileExists( $log_file );

		// Read the log file and verify content
		$log_content = file_get_contents( $log_file );
		$this->assertStringContainsString( 'Test Post', $log_content );
		$this->assertStringContainsString( 'INFO', $log_content );
		$this->assertStringContainsString( 'SimplePostLogger', $log_content );

		// Clean up
		@unlink( $log_file );
		@rmdir( $log_dir );
		delete_option( 'simple_history_integration_file' );
	}

	/**
	 * Test that disabled integrations don't receive events.
	 */
	public function test_disabled_integration_no_events() {
		// Ensure file integration is disabled
		$file_integration = $this->manager->get_integration( 'file' );
		$file_integration->set_setting( 'enabled', false );

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
		$log_dir = $this->invoke_method( $file_integration, 'get_log_directory_path', [] );
		$log_file = $log_dir . '/events-' . gmdate( 'Y-m-d' ) . '.log';

		$this->assertFileDoesNotExist( $log_file );

		// Clean up
		delete_option( 'simple_history_integration_file' );
	}

	/**
	 * Test message formatting with context interpolation.
	 */
	public function test_message_formatting() {
		// Test the format_message_for_integration method
		$integration = new File_Integration();

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
			'format_message_for_integration',
			[ $integration, $event_data ]
		);

		$this->assertEquals( 'User John Doe updated post "My Test Post" with ID 123', $formatted );
		$this->assertStringNotContainsString( '{', $formatted );
		$this->assertStringNotContainsString( '}', $formatted );
		$this->assertStringNotContainsString( '_internal_field', $formatted );
	}

	/**
	 * Test settings tab registration.
	 */
	public function test_settings_tab_registration() {
		$tabs = [];

		// The manager should add a settings tab
		$tabs = $this->manager->add_settings_tab( $tabs );

		$this->assertIsArray( $tabs );
		$this->assertNotEmpty( $tabs );

		// Find the integrations tab
		$integrations_tab = null;
		foreach ( $tabs as $tab ) {
			if ( $tab['slug'] === 'integrations' ) {
				$integrations_tab = $tab;
				break;
			}
		}

		$this->assertNotNull( $integrations_tab );
		$this->assertEquals( 'Integrations', $integrations_tab['name'] );
		$this->assertIsCallable( $integrations_tab['function'] );
	}

	/**
	 * Test integration action hook.
	 */
	public function test_integration_registration_hook() {
		$hook_called = false;
		$received_integration = null;
		$received_manager = null;

		// Add hook listener
		add_action( 'simple_history/integrations/registered', function( $integration, $manager ) use ( &$hook_called, &$received_integration, &$received_manager ) {
			$hook_called = true;
			$received_integration = $integration;
			$received_manager = $manager;
		}, 10, 2 );

		// Register a new integration
		$integration = new Example_Integration();
		$this->manager->register_integration( $integration );

		// Verify hook was called
		$this->assertTrue( $hook_called );
		$this->assertSame( $integration, $received_integration );
		$this->assertSame( $this->manager, $received_manager );

		// Clean up
		remove_all_actions( 'simple_history/integrations/registered' );
	}

	/**
	 * Test that external integrations can be registered.
	 */
	public function test_external_integration_registration() {
		$external_registered = false;

		// Simulate external plugin registering an integration
		add_action( 'simple_history/integrations/register', function( $manager ) use ( &$external_registered ) {
			$integration = new Example_Integration();
			$result = $manager->register_integration( $integration );
			$external_registered = $result;
		} );

		// Trigger the action
		do_action( 'simple_history/integrations/register', $this->manager );

		// Verify registration worked
		$this->assertTrue( $external_registered );
		$this->assertInstanceOf( Example_Integration::class, $this->manager->get_integration( 'example' ) );
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