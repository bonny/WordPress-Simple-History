<?php

namespace Simple_History\Channels;

use Simple_History\Channels\Channel;
use Simple_History\Helpers;

/**
 * Example Channel demonstrating WordPress Settings API integration.
 *
 * This class serves as documentation and example for developers
 * creating new channels. It shows how to add settings fields using
 * the WordPress Settings API pattern.
 *
 * @since 4.4.0
 */
class Example_Channel extends Channel {
	/**
	 * The unique slug for this channel.
	 *
	 * @var ?string
	 */
	protected ?string $slug = 'example';

	/**
	 * Whether this channel supports async processing.
	 *
	 * @var bool
	 */
	protected bool $supports_async = true;

	/**
	 * Get the display name for this channel.
	 *
	 * @return string The channel display name.
	 */
	public function get_name() {
		return __( 'Example Channel', 'simple-history' );
	}

	/**
	 * Get the description for this channel.
	 *
	 * @return string The channel description.
	 */
	public function get_description() {
		return __( 'This is an example channel showing WordPress Settings API integration.', 'simple-history' );
	}

	/**
	 * Send an event to this channel.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	public function send_event( $event_data, $formatted_message ) {
		// This is just an example, so we don't actually send anything.
		$this->log_debug( 'Example channel would send: ' . $formatted_message );
		return true;
	}

	/**
	 * Add settings fields for this channel using WordPress Settings API.
	 *
	 * @param string $settings_page_slug The settings page slug.
	 * @param string $settings_section_id The settings section ID.
	 */
	public function add_settings_fields( $settings_page_slug, $settings_section_id ) {
		// Add parent's enable checkbox first.
		parent::add_settings_fields( $settings_page_slug, $settings_section_id );

		$option_name = $this->get_settings_option_name();

		// API Key field.
		add_settings_field(
			$option_name . '_api_key',
			Helpers::get_settings_field_title_output( __( 'API Key', 'simple-history' ) ),
			[ $this, 'settings_field_api_key' ],
			$settings_page_slug,
			$settings_section_id
		);

		// Webhook URL field.
		add_settings_field(
			$option_name . '_webhook_url',
			Helpers::get_settings_field_title_output( __( 'Webhook URL', 'simple-history' ) ),
			[ $this, 'settings_field_webhook_url' ],
			$settings_page_slug,
			$settings_section_id
		);

		// Log level field.
		add_settings_field(
			$option_name . '_log_level',
			Helpers::get_settings_field_title_output( __( 'Minimum Log Level', 'simple-history' ) ),
			[ $this, 'settings_field_log_level' ],
			$settings_page_slug,
			$settings_section_id
		);
	}

	/**
	 * Render the API Key settings field.
	 */
	public function settings_field_api_key() {
		$option_name = $this->get_settings_option_name();
		$value       = $this->get_setting( 'api_key', '' );
		?>
		<input
			type="text"
			name="<?php echo esc_attr( $option_name ); ?>[api_key]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="sk_live_1234567890"
		/>
		<p class="description">
			<?php esc_html_e( 'Enter your API key for authentication.', 'simple-history' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Webhook URL settings field.
	 */
	public function settings_field_webhook_url() {
		$option_name = $this->get_settings_option_name();
		$value       = $this->get_setting( 'webhook_url', '' );
		?>
		<input
			type="url"
			name="<?php echo esc_attr( $option_name ); ?>[webhook_url]"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="https://example.com/webhook"
		/>
		<p class="description">
			<?php esc_html_e( 'The URL where events will be sent.', 'simple-history' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the Log Level settings field.
	 */
	public function settings_field_log_level() {
		$option_name = $this->get_settings_option_name();
		$value       = $this->get_setting( 'log_level', 'info' );

		$options = [
			'debug'    => __( 'Debug', 'simple-history' ),
			'info'     => __( 'Info', 'simple-history' ),
			'notice'   => __( 'Notice', 'simple-history' ),
			'warning'  => __( 'Warning', 'simple-history' ),
			'error'    => __( 'Error', 'simple-history' ),
			'critical' => __( 'Critical', 'simple-history' ),
		];
		?>
		<select name="<?php echo esc_attr( $option_name ); ?>[log_level]">
			<?php foreach ( $options as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Only send events with this level or higher.', 'simple-history' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings for this channel.
	 *
	 * @param array $input Raw input data from form submission.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		// Get parent sanitization first.
		$sanitized = parent::sanitize_settings( $input );

		// Sanitize API key.
		$sanitized['api_key'] = sanitize_text_field( $input['api_key'] ?? '' );

		// Sanitize webhook URL.
		$sanitized['webhook_url'] = esc_url_raw( $input['webhook_url'] ?? '' );

		// Sanitize log level.
		$valid_levels            = [ 'debug', 'info', 'notice', 'warning', 'error', 'critical' ];
		$sanitized['log_level']  = in_array( $input['log_level'] ?? '', $valid_levels, true )
			? $input['log_level']
			: 'info';

		return $sanitized;
	}

	/**
	 * Get the default settings for this channel.
	 *
	 * @return array Array of default settings.
	 */
	protected function get_default_settings() {
		return array_merge(
			parent::get_default_settings(),
			[
				'api_key'     => '',
				'webhook_url' => '',
				'log_level'   => 'info',
			]
		);
	}

	/**
	 * Test the channel connection/configuration.
	 *
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	public function test_connection() {
		$webhook_url = $this->get_setting( 'webhook_url' );
		$api_key     = $this->get_setting( 'api_key' );

		if ( empty( $webhook_url ) || empty( $api_key ) ) {
			return [
				'success' => false,
				'message' => __( 'Please configure webhook URL and API key first.', 'simple-history' ),
			];
		}

		// Simulate a test request.
		$response = wp_remote_post(
			$webhook_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( [ 'test' => true ] ),
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
