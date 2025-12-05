<?php

namespace Simple_History\Tests\WPUnit;

use Simple_History\Channels\Channel;
use Simple_History\Channels\Channels\File_Channel;

// Include test fixture.
require_once __DIR__ . '/fixtures/class-example-channel.php';
use Simple_History\Channels\Channels\Example_Channel;

/**
 * Test the channels system settings and validation.
 */
class ChannelsTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Test checkbox field sanitization.
	 */
	public function test_checkbox_sanitization() {
		$channel = new Example_Channel();

		// Test various truthy values.
		$settings = [ 'enabled' => '1' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertTrue( $sanitized['enabled'] );

		$settings = [ 'enabled' => 'yes' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertTrue( $sanitized['enabled'] );

		// Test falsy values.
		$settings = [ 'enabled' => '' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertFalse( $sanitized['enabled'] );

		$settings = [];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertFalse( $sanitized['enabled'] );
	}

	/**
	 * Test text field sanitization.
	 */
	public function test_text_field_sanitization() {
		$channel = new Example_Channel();

		$settings = [
			'api_key' => '<script>alert("xss")</script>API_KEY_123',
		];

		$sanitized = $channel->sanitize_settings( $settings );

		// Text fields should be sanitized.
		$this->assertStringNotContainsString( '<script>', $sanitized['api_key'] );
		$this->assertStringContainsString( 'API_KEY_123', $sanitized['api_key'] );
	}

	/**
	 * Test URL field sanitization.
	 */
	public function test_url_field_sanitization() {
		$channel = new Example_Channel();

		// Valid URL.
		$settings = [ 'webhook_url' => 'https://example.com/webhook' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 'https://example.com/webhook', $sanitized['webhook_url'] );

		// Invalid URL should be sanitized to empty.
		$settings = [ 'webhook_url' => 'javascript:alert(1)' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEmpty( $sanitized['webhook_url'] );
	}

	/**
	 * Test select field sanitization.
	 */
	public function test_select_field_sanitization() {
		$channel = new Example_Channel();

		// Valid option.
		$settings = [ 'log_level' => 'warning' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 'warning', $sanitized['log_level'] );

		// Invalid option - should use default.
		$settings = [ 'log_level' => 'invalid_level' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 'info', $sanitized['log_level'] ); // Default is 'info'.
	}

	/**
	 * Test File_Channel rotation frequency sanitization.
	 */
	public function test_file_channel_rotation_sanitization() {
		$channel = new File_Channel();

		// Valid rotation frequency.
		$settings = [ 'rotation_frequency' => 'weekly' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 'weekly', $sanitized['rotation_frequency'] );

		// Invalid rotation frequency - should use default.
		$settings = [ 'rotation_frequency' => 'invalid' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 'daily', $sanitized['rotation_frequency'] );
	}

	/**
	 * Test File_Channel keep_files sanitization with bounds.
	 */
	public function test_file_channel_keep_files_sanitization() {
		$channel = new File_Channel();

		// Within bounds.
		$settings = [ 'keep_files' => '50' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 50, $sanitized['keep_files'] );

		// Negative value - absint() converts to absolute value first, then clamped.
		$settings = [ 'keep_files' => '-5' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 5, $sanitized['keep_files'] ); // absint('-5') = 5.

		// Above maximum - should be clamped to 365.
		$settings = [ 'keep_files' => '500' ];
		$sanitized = $channel->sanitize_settings( $settings );
		$this->assertEquals( 365, $sanitized['keep_files'] );
	}

	/**
	 * Test settings persistence.
	 */
	public function test_settings_persistence() {
		$channel = new File_Channel();

		$test_settings = [
			'enabled'            => true,
			'rotation_frequency' => 'weekly',
			'keep_files'         => 10,
		];

		// Save settings.
		$result = $channel->save_settings( $test_settings );
		$this->assertTrue( $result );

		// Retrieve settings.
		$saved_settings = $channel->get_settings();
		$this->assertEquals( true, $saved_settings['enabled'] );
		$this->assertEquals( 'weekly', $saved_settings['rotation_frequency'] );
		$this->assertEquals( 10, $saved_settings['keep_files'] );

		// Clean up.
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Test default settings.
	 */
	public function test_default_settings() {
		$channel = new File_Channel();

		// Delete any existing settings.
		delete_option( 'simple_history_channel_file' );

		$settings = $channel->get_settings();

		// Check defaults are applied.
		$this->assertFalse( $settings['enabled'] ); // Default from parent.
		$this->assertEquals( 'daily', $settings['rotation_frequency'] ); // Default from File_Channel.
		$this->assertEquals( 30, $settings['keep_files'] ); // Default from File_Channel.
	}

	/**
	 * Test individual setting getter/setter.
	 */
	public function test_individual_setting_methods() {
		$channel = new File_Channel();

		// Set individual setting.
		$result = $channel->set_setting( 'rotation_frequency', 'monthly' );
		$this->assertTrue( $result );

		// Get individual setting.
		$value = $channel->get_setting( 'rotation_frequency' );
		$this->assertEquals( 'monthly', $value );

		// Get non-existent setting with default.
		$value = $channel->get_setting( 'non_existent', 'default_value' );
		$this->assertEquals( 'default_value', $value );

		// Clean up.
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Test is_enabled method.
	 */
	public function test_is_enabled() {
		$channel = new File_Channel();

		// Should be disabled by default.
		$this->assertFalse( $channel->is_enabled() );

		// Enable it.
		$channel->set_setting( 'enabled', true );
		$this->assertTrue( $channel->is_enabled() );

		// Disable it.
		$channel->set_setting( 'enabled', false );
		$this->assertFalse( $channel->is_enabled() );

		// Clean up.
		delete_option( 'simple_history_channel_file' );
	}

	/**
	 * Test settings option name method.
	 */
	public function test_settings_option_name() {
		$channel = new File_Channel();

		$option_name = $channel->get_settings_option_name();
		$this->assertEquals( 'simple_history_channel_file', $option_name );

		$example_channel     = new Example_Channel();
		$example_option_name = $example_channel->get_settings_option_name();
		$this->assertEquals( 'simple_history_channel_example', $example_option_name );
	}

	/**
	 * Test channel slug methods.
	 */
	public function test_channel_slug() {
		$file_channel = new File_Channel();
		$this->assertEquals( 'file', $file_channel->get_slug() );

		$example_channel = new Example_Channel();
		$this->assertEquals( 'example', $example_channel->get_slug() );
	}

	/**
	 * Test channel name and description.
	 */
	public function test_channel_name_description() {
		$channel = new File_Channel();

		$this->assertNotEmpty( $channel->get_name() );
		$this->assertNotEmpty( $channel->get_description() );
		$this->assertIsString( $channel->get_name() );
		$this->assertIsString( $channel->get_description() );
	}

	/**
	 * Test supports_async method.
	 */
	public function test_supports_async() {
		$file_channel = new File_Channel();
		$this->assertFalse( $file_channel->supports_async() );

		$example_channel = new Example_Channel();
		$this->assertTrue( $example_channel->supports_async() );
	}
}
