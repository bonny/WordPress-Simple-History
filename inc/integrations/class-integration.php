<?php

namespace Simple_History\Integrations;

use Simple_History\Integrations\Interfaces\Integration_Interface;

/**
 * Abstract base class for all integrations.
 *
 * Provides common functionality for integrations that forward
 * Simple History events to external systems.
 *
 * @since 4.4.0
 */
abstract class Integration implements Integration_Interface {
	/**
	 * The unique slug for this integration.
	 *
	 * @var string
	 */
	protected string $slug;

	/**
	 * Whether this integration supports async processing.
	 *
	 * @var bool
	 */
	protected bool $supports_async = false;

	/**
	 * The settings option name for this integration.
	 *
	 * @var string
	 */
	protected string $settings_option_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_option_name = 'simple_history_integration_' . $this->get_slug();
	}

	/**
	 * Get the unique slug for this integration.
	 *
	 * @return string The integration slug.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get the display name for this integration.
	 *
	 * This method must be implemented by child classes.
	 *
	 * @return string The integration display name.
	 */
	abstract public function get_name();

	/**
	 * Get the description for this integration.
	 *
	 * This method must be implemented by child classes.
	 *
	 * @return string The integration description.
	 */
	abstract public function get_description();

	/**
	 * Check if this integration is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled() {
		$settings = $this->get_settings();
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Check if this integration supports async processing.
	 *
	 * @return bool True if supports async, false for synchronous only.
	 */
	public function supports_async() {
		return $this->supports_async;
	}

	/**
	 * Send an event to this integration.
	 *
	 * This method must be implemented by child classes.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	abstract public function send_event( $event_data, $formatted_message );

	/**
	 * Get the settings fields for this integration.
	 *
	 * This method should be overridden by child classes to provide
	 * specific configuration fields.
	 *
	 * @return array Array of settings fields.
	 */
	public function get_settings_fields() {
		return [
			[
				'type' => 'checkbox',
				'name' => 'enabled',
				'title' => __( 'Enable Integration', 'simple-history' ),
				'description' => sprintf(
					/* translators: %s: Integration name */
					__( 'Enable', 'simple-history' ),
					$this->get_name()
				),
			],
		];
	}

	/**
	 * Get the current settings for this integration.
	 *
	 * @return array Array of current settings.
	 */
	public function get_settings() {
		$defaults = $this->get_default_settings();
		$saved_settings = get_option( $this->settings_option_name, [] );

		return wp_parse_args( $saved_settings, $defaults );
	}

	/**
	 * Get the default settings for this integration.
	 *
	 * @return array Array of default settings.
	 */
	protected function get_default_settings() {
		$defaults = [ 'enabled' => false ];

		// Extract defaults from settings fields.
		foreach ( $this->get_settings_fields() as $field ) {
			if ( isset( $field['default'] ) ) {
				$defaults[ $field['name'] ] = $field['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Save settings for this integration.
	 *
	 * @param array $settings The settings to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_settings( $settings ) {
		// Validate settings first.
		$validated_settings = $this->validate_settings( $settings );

		if ( is_wp_error( $validated_settings ) ) {
			return false;
		}

		return update_option( $this->settings_option_name, $validated_settings );
	}

	/**
	 * Validate settings before saving.
	 *
	 * @param array $settings The settings to validate.
	 * @return array|\WP_Error Validated settings or WP_Error on failure.
	 */
	protected function validate_settings( $settings ) {
		$validated = [];
		$fields = $this->get_settings_fields();

		foreach ( $fields as $field ) {
			$name = $field['name'];
			$value = $settings[ $name ] ?? null;

			// Apply field-specific validation.
			switch ( $field['type'] ) {
				case 'checkbox':
					$validated[ $name ] = ! empty( $value );
					break;

				case 'text':
				case 'textarea':
					$validated[ $name ] = sanitize_text_field( $value );
					break;

				case 'url':
					$validated[ $name ] = esc_url_raw( $value );
					if ( ! empty( $value ) && empty( $validated[ $name ] ) ) {
						return new \WP_Error(
							'invalid_url',
							sprintf(
							/* translators: %s: Field name */
								__( 'Invalid URL in field: %s', 'simple-history' ),
								$field['title'] ?? $name
							)
						);
					}
					break;

				case 'email':
					$validated[ $name ] = sanitize_email( $value );
					if ( ! empty( $value ) && ! is_email( $validated[ $name ] ) ) {
						return new \WP_Error(
							'invalid_email',
							sprintf(
							/* translators: %s: Field name */
								__( 'Invalid email in field: %s', 'simple-history' ),
								$field['title'] ?? $name
							)
						);
					}
					break;

				default:
					$validated[ $name ] = $value;
			}

			// Check required fields.
			if ( ! empty( $field['required'] ) && empty( $validated[ $name ] ) ) {
				return new \WP_Error(
					'required_field',
					sprintf(
					/* translators: %s: Field name */
						__( 'Required field is empty: %s', 'simple-history' ),
						$field['title'] ?? $name
					)
				);
			}
		}

		return $validated;
	}

	/**
	 * Get the alert rules for this integration.
	 *
	 * @return array Array of alert rules.
	 */
	public function get_alert_rules() {
		$settings = $this->get_settings();
		return $settings['alert_rules'] ?? [];
	}

	/**
	 * Set the alert rules for this integration.
	 *
	 * @param array $rules Array of alert rules.
	 * @return bool True on success, false on failure.
	 */
	public function set_alert_rules( $rules ) {
		$settings = $this->get_settings();
		$settings['alert_rules'] = $rules;
		return $this->save_settings( $settings );
	}

	/**
	 * Check if an event should be sent based on alert rules.
	 *
	 * @param array $event_data The event data to check.
	 * @return bool True if event should be sent, false otherwise.
	 */
	public function should_send_event( $event_data ) {
		// If integration is not enabled, don't send.
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$rules = $this->get_alert_rules();

		// If no rules are configured, send all events.
		if ( empty( $rules ) ) {
			return true;
		}

		// TODO: Implement proper rule evaluation using Alert_Rules_Engine.
		// For now, just return true to send all events.
		return true;
	}

	/**
	 * Log an error for this integration.
	 *
	 * @param string $message The error message.
	 * @param array  $context Additional context data.
	 */
	protected function log_error( $message, $context = [] ) {
		$log_message = sprintf(
			'Simple History Integration %s: %s',
			$this->get_slug(),
			$message
		);

		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message );
	}

	/**
	 * Log debug information for this integration.
	 *
	 * @param string $message The debug message.
	 * @param array  $context Additional context data.
	 */
	protected function log_debug( $message, $context = [] ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_message = sprintf(
			'Simple History Integration %s (DEBUG): %s',
			$this->get_slug(),
			$message
		);

		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message );
	}

	/**
	 * Get additional info HTML to display before the settings fields.
	 *
	 * This method can be overridden by child classes to provide
	 * integration-specific information to users.
	 *
	 * @return string HTML content to display, or empty string if none.
	 */
	public function get_settings_info_before_fields_html() {
		return '';
	}

	/**
	 * Get additional info HTML to display after the settings fields.
	 *
	 * This method can be overridden by child classes to provide
	 * integration-specific information to users.
	 *
	 * @return string HTML content to display, or empty string if none.
	 */
	public function get_settings_info_after_fields_html() {
		return '';
	}
}
