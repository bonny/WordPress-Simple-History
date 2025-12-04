<?php

namespace Simple_History\Integrations\Integrations;

use Simple_History\Integrations\Integration;

/**
 * Example Integration demonstrating all available field types.
 *
 * This class serves as documentation and example for developers
 * creating new integrations. It shows how to use all supported
 * field types in the get_settings_fields() method.
 *
 * @since 4.4.0
 */
class Example_Integration extends Integration {
	/**
	 * The unique slug for this integration.
	 *
	 * @var ?string
	 */
	protected ?string $slug = 'example';

	/**
	 * Whether this integration supports async processing.
	 *
	 * @var bool
	 */
	protected bool $supports_async = true;

	/**
	 * Get the display name for this integration.
	 *
	 * @return string The integration display name.
	 */
	public function get_name() {
		return __( 'Example Integration', 'simple-history' );
	}

	/**
	 * Get the description for this integration.
	 *
	 * @return string The integration description.
	 */
	public function get_description() {
		return __( 'This is an example integration showing all available field types.', 'simple-history' );
	}

	/**
	 * Send an event to this integration.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	public function send_event( $event_data, $formatted_message ) {
		// This is just an example, so we don't actually send anything.
		$this->log_debug( 'Example integration would send: ' . $formatted_message );
		return true;
	}

	/**
	 * Get the settings fields for this integration.
	 *
	 * This demonstrates all available field types and their properties.
	 *
	 * @return array Array of settings fields.
	 */
	public function get_settings_fields() {
		$base_fields = parent::get_settings_fields();

		$example_fields = [
			// Text field example
			[
				'type' => 'text',
				'name' => 'api_key',
				'title' => __( 'API Key', 'simple-history' ),
				'description' => __( 'Enter your API key for authentication.', 'simple-history' ),
				'placeholder' => 'sk_live_1234567890',
				'required' => true,
			],

			// URL field example
			[
				'type' => 'url',
				'name' => 'webhook_url',
				'title' => __( 'Webhook URL', 'simple-history' ),
				'description' => __( 'The URL where events will be sent.', 'simple-history' ),
				'placeholder' => 'https://example.com/webhook',
				'required' => true,
			],

			// Email field example
			[
				'type' => 'email',
				'name' => 'notification_email',
				'title' => __( 'Notification Email', 'simple-history' ),
				'description' => __( 'Email address for notifications.', 'simple-history' ),
				'placeholder' => 'admin@example.com',
				'default' => get_option( 'admin_email' ),
			],

			// Select field example
			[
				'type' => 'select',
				'name' => 'log_level',
				'title' => __( 'Minimum Log Level', 'simple-history' ),
				'description' => __( 'Only send events with this level or higher.', 'simple-history' ),
				'options' => [
					'debug' => __( 'Debug', 'simple-history' ),
					'info' => __( 'Info', 'simple-history' ),
					'notice' => __( 'Notice', 'simple-history' ),
					'warning' => __( 'Warning', 'simple-history' ),
					'error' => __( 'Error', 'simple-history' ),
					'critical' => __( 'Critical', 'simple-history' ),
				],
				'default' => 'info',
			],

			// Number field example
			[
				'type' => 'number',
				'name' => 'batch_size',
				'title' => __( 'Batch Size', 'simple-history' ),
				'description' => __( 'Number of events to send in each batch.', 'simple-history' ),
				'default' => 10,
				'min' => 1,
				'max' => 100,
			],

			// Textarea field example
			[
				'type' => 'textarea',
				'name' => 'custom_headers',
				'title' => __( 'Custom Headers', 'simple-history' ),
				'description' => __( 'Additional HTTP headers (one per line, format: Header-Name: value)', 'simple-history' ),
				'placeholder' => "X-Custom-Header: value\nAuthorization: Bearer token",
			],

			// Multiple checkboxes example (using custom validation)
			[
				'type' => 'checkbox',
				'name' => 'send_user_data',
				'title' => __( 'Include User Data', 'simple-history' ),
				'description' => __( 'Include user information in sent events.', 'simple-history' ),
				'default' => true,
			],

			[
				'type' => 'checkbox',
				'name' => 'send_ip_address',
				'title' => __( 'Include IP Address', 'simple-history' ),
				'description' => __( 'Include IP addresses in sent events.', 'simple-history' ),
				'default' => false,
			],
		];

		return array_merge( $base_fields, $example_fields );
	}

	/**
	 * Test the integration connection/configuration.
	 *
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	public function test_connection() {
		$webhook_url = $this->get_setting( 'webhook_url' );
		$api_key = $this->get_setting( 'api_key' );

		if ( empty( $webhook_url ) || empty( $api_key ) ) {
			return [
				'success' => false,
				'message' => __( 'Please configure webhook URL and API key first.', 'simple-history' ),
			];
		}

		// Simulate a test request
		$response = wp_remote_post(
			$webhook_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				],
				'body' => wp_json_encode( [ 'test' => true ] ),
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Connection failed: %s', 'simple-history' ),
					$response->get_error_message()
				),
			];
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		
		if ( $response_code >= 200 && $response_code < 300 ) {
			return [
				'success' => true,
				'message' => __( 'Connection successful!', 'simple-history' ),
			];
		} else {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: HTTP response code */
					__( 'Server returned error code: %d', 'simple-history' ),
					$response_code
				),
			];
		}
	}
}