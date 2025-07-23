<?php

namespace Simple_History\Tests\WPUnit;

use Simple_History\Integrations\Integration;
use Simple_History\Integrations\Integrations\Example_Integration;
use Simple_History\Integrations\Integrations\File_Integration;

/**
 * Test the integrations system field types and validation.
 */
class IntegrationsTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Test that all field types are properly validated.
	 */
	public function test_field_type_validation() {
		$integration = new Example_Integration();

		// Test checkbox field - should convert to boolean
		$settings = [
			'enabled' => '1',
			'send_user_data' => 'yes',
			'send_ip_address' => '',
		];

		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );

		$this->assertIsArray( $validated );
		$this->assertTrue( $validated['enabled'] );
		$this->assertTrue( $validated['send_user_data'] );
		$this->assertFalse( $validated['send_ip_address'] );
	}

	/**
	 * Test text and textarea field validation.
	 */
	public function test_text_field_validation() {
		$integration = new Example_Integration();

		$settings = [
			'api_key' => '<script>alert("xss")</script>API_KEY_123',
			'custom_headers' => "Header1: Value1\n<script>evil</script>",
		];

		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );

		// Text fields should be sanitized
		$this->assertStringNotContainsString( '<script>', $validated['api_key'] );
		$this->assertStringNotContainsString( '<script>', $validated['custom_headers'] );
		$this->assertStringContainsString( 'API_KEY_123', $validated['api_key'] );
	}

	/**
	 * Test URL field validation.
	 */
	public function test_url_field_validation() {
		$integration = new Example_Integration();

		// Valid URL
		$settings = [ 'webhook_url' => 'https://example.com/webhook' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertEquals( 'https://example.com/webhook', $validated['webhook_url'] );

		// Invalid URL should return WP_Error
		$settings = [ 'webhook_url' => 'not a valid url' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertInstanceOf( \WP_Error::class, $validated );
		$this->assertEquals( 'invalid_url', $validated->get_error_code() );
	}

	/**
	 * Test email field validation.
	 */
	public function test_email_field_validation() {
		$integration = new Example_Integration();

		// Valid email
		$settings = [ 'notification_email' => 'test@example.com' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertEquals( 'test@example.com', $validated['notification_email'] );

		// Invalid email should return WP_Error
		$settings = [ 'notification_email' => 'not-an-email' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertInstanceOf( \WP_Error::class, $validated );
		$this->assertEquals( 'invalid_email', $validated->get_error_code() );
	}

	/**
	 * Test number field validation with min/max.
	 */
	public function test_number_field_validation() {
		$integration = new Example_Integration();

		// Within bounds
		$settings = [ 'batch_size' => '50' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertEquals( 50, $validated['batch_size'] );

		// Below minimum - should be clamped to min
		$settings = [ 'batch_size' => '-5' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertEquals( 1, $validated['batch_size'] ); // min is 1

		// Above maximum - should be clamped to max
		$settings = [ 'batch_size' => '200' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertEquals( 100, $validated['batch_size'] ); // max is 100
	}

	/**
	 * Test select field validation.
	 */
	public function test_select_field_validation() {
		$integration = new Example_Integration();

		// Valid option
		$settings = [ 'log_level' => 'warning' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertEquals( 'warning', $validated['log_level'] );

		// Invalid option - should use default
		$settings = [ 'log_level' => 'invalid_level' ];
		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertEquals( 'info', $validated['log_level'] ); // default is 'info'
	}

	/**
	 * Test required field validation.
	 */
	public function test_required_field_validation() {
		$integration = new Example_Integration();

		// Missing required field
		$settings = [
			'enabled' => true,
			// 'api_key' is required but missing
		];

		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );
		$this->assertInstanceOf( \WP_Error::class, $validated );
		$this->assertEquals( 'required_field', $validated->get_error_code() );
	}

	/**
	 * Test get_settings_fields returns expected structure.
	 */
	public function test_get_settings_fields_structure() {
		$integration = new Example_Integration();
		$fields = $integration->get_settings_fields();

		$this->assertIsArray( $fields );
		$this->assertNotEmpty( $fields );

		// Check that each field has required properties
		foreach ( $fields as $field ) {
			$this->assertArrayHasKey( 'type', $field );
			$this->assertArrayHasKey( 'name', $field );
			$this->assertArrayHasKey( 'title', $field );
		}

		// Check specific field types exist
		$field_types = array_column( $fields, 'type' );
		$this->assertContains( 'checkbox', $field_types );
		$this->assertContains( 'text', $field_types );
		$this->assertContains( 'url', $field_types );
		$this->assertContains( 'email', $field_types );
		$this->assertContains( 'select', $field_types );
		$this->assertContains( 'number', $field_types );
		$this->assertContains( 'textarea', $field_types );
	}

	/**
	 * Test settings persistence.
	 */
	public function test_settings_persistence() {
		$integration = new File_Integration();

		$test_settings = [
			'enabled' => true,
			'rotation_frequency' => 'weekly',
			'keep_files' => 10,
		];

		// Save settings
		$result = $integration->save_settings( $test_settings );
		$this->assertTrue( $result );

		// Retrieve settings
		$saved_settings = $integration->get_settings();
		$this->assertEquals( true, $saved_settings['enabled'] );
		$this->assertEquals( 'weekly', $saved_settings['rotation_frequency'] );
		$this->assertEquals( 10, $saved_settings['keep_files'] );

		// Clean up
		delete_option( 'simple_history_integration_file' );
	}

	/**
	 * Test default settings.
	 */
	public function test_default_settings() {
		$integration = new File_Integration();
		
		// Delete any existing settings
		delete_option( 'simple_history_integration_file' );

		$settings = $integration->get_settings();

		// Check defaults are applied
		$this->assertFalse( $settings['enabled'] ); // Default from parent
		$this->assertEquals( 'daily', $settings['rotation_frequency'] ); // Default from File_Integration
		$this->assertEquals( 30, $settings['keep_files'] ); // Default from File_Integration
	}

	/**
	 * Test individual setting getter/setter.
	 */
	public function test_individual_setting_methods() {
		$integration = new File_Integration();

		// Set individual setting
		$result = $integration->set_setting( 'rotation_frequency', 'monthly' );
		$this->assertTrue( $result );

		// Get individual setting
		$value = $integration->get_setting( 'rotation_frequency' );
		$this->assertEquals( 'monthly', $value );

		// Get non-existent setting with default
		$value = $integration->get_setting( 'non_existent', 'default_value' );
		$this->assertEquals( 'default_value', $value );

		// Clean up
		delete_option( 'simple_history_integration_file' );
	}

	/**
	 * Test is_enabled method.
	 */
	public function test_is_enabled() {
		$integration = new File_Integration();

		// Should be disabled by default
		$this->assertFalse( $integration->is_enabled() );

		// Enable it
		$integration->set_setting( 'enabled', true );
		$this->assertTrue( $integration->is_enabled() );

		// Disable it
		$integration->set_setting( 'enabled', false );
		$this->assertFalse( $integration->is_enabled() );

		// Clean up
		delete_option( 'simple_history_integration_file' );
	}

	/**
	 * Test custom field type passes through validation.
	 */
	public function test_custom_field_type() {
		$integration = new Example_Integration();

		// Add a custom field type in the settings
		$settings = [
			'custom_field' => 'custom_value',
		];

		$validated = $this->invoke_method( $integration, 'validate_settings', [ $settings ] );

		// Custom fields should pass through unchanged
		$this->assertEquals( 'custom_value', $validated['custom_field'] );
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