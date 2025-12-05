<?php

namespace Simple_History\Channels;

use Simple_History\Channels\Interfaces\Channel_Interface;

/**
 * Abstract base class for all channels.
 *
 * Provides common functionality for channels that forward
 * Simple History events to external systems.
 *
 * @since 4.4.0
 */
abstract class Channel implements Channel_Interface {
	/**
	 * The unique slug for this channel.
	 * Must be defined by child classes.
	 *
	 * @var ?string
	 */
	protected ?string $slug = null;

	/**
	 * Whether this channel supports async processing.
	 *
	 * @var bool
	 */
	protected bool $supports_async = false;

	/**
	 * Called when the channel is loaded and ready.
	 *
	 * Child classes should override this method to register hooks
	 * and perform initialization that has side effects.
	 * This keeps the class instantiation free of side effects for testability.
	 */
	public function loaded() {
		// Override in child classes to register hooks, etc.
	}

	/**
	 * Get the unique slug for this channel.
	 *
	 * @return string The channel slug.
	 */
	public function get_slug() {
		if ( $this->slug === null ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: Class name */
					esc_html__( 'Channel class %s must define a $slug property.', 'simple-history' ),
					static::class
				),
				'4.4.0'
			);
			return '';
		}
		return $this->slug;
	}

	/**
	 * Get the display name for this channel.
	 *
	 * This method must be implemented by child classes.
	 *
	 * @return string The channel display name.
	 */
	abstract public function get_name();

	/**
	 * Get the description for this channel.
	 *
	 * This method must be implemented by child classes.
	 *
	 * @return string The channel description.
	 */
	abstract public function get_description();

	/**
	 * Check if this channel is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled() {
		return ! empty( $this->get_setting( 'enabled', false ) );
	}

	/**
	 * Check if this channel supports async processing.
	 *
	 * @return bool True if supports async, false for synchronous only.
	 */
	public function supports_async() {
		return $this->supports_async;
	}

	/**
	 * Send an event to this channel.
	 *
	 * This method must be implemented by child classes.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	abstract public function send_event( $event_data, $formatted_message );

	/**
	 * Get the settings fields for this channel.
	 *
	 * This method should be overridden by child classes to provide
	 * specific configuration fields.
	 *
	 * ## Supported Field Types:
	 *
	 * ### checkbox
	 * - Renders as a checkbox input
	 * - Value is stored as boolean (true/false)
	 * - Example: ['type' => 'checkbox', 'name' => 'enabled', 'title' => 'Enable']
	 *
	 * ### text
	 * - Renders as a single-line text input
	 * - Value is sanitized with sanitize_text_field()
	 * - Example: ['type' => 'text', 'name' => 'api_key', 'title' => 'API Key']
	 *
	 * ### textarea
	 * - Renders as a multi-line text area
	 * - Value is sanitized with sanitize_text_field()
	 * - Example: ['type' => 'textarea', 'name' => 'message', 'title' => 'Message']
	 *
	 * ### url
	 * - Renders as a URL input field
	 * - Value is validated and sanitized with esc_url_raw()
	 * - Validation fails if an invalid URL is provided
	 * - Example: ['type' => 'url', 'name' => 'webhook_url', 'title' => 'Webhook URL']
	 *
	 * ### email
	 * - Renders as an email input field
	 * - Value is validated with is_email() and sanitized with sanitize_email()
	 * - Validation fails if an invalid email is provided
	 * - Example: ['type' => 'email', 'name' => 'recipient', 'title' => 'Email Address']
	 *
	 * ### select
	 * - Renders as a dropdown select field
	 * - Requires 'options' array with key => label pairs
	 * - Example: [
	 *     'type' => 'select',
	 *     'name' => 'frequency',
	 *     'title' => 'Frequency',
	 *     'options' => ['daily' => 'Daily', 'weekly' => 'Weekly']
	 * ]
	 *
	 * ### number
	 * - Renders as a number input field
	 * - Supports 'min' and 'max' attributes
	 * - Example: [
	 *     'type' => 'number',
	 *     'name' => 'retention_days',
	 *     'title' => 'Days to Keep',
	 *     'min' => 1,
	 *     'max' => 365
	 * ]
	 *
	 * ## Common Field Properties:
	 * - name: (required) The field name/key for storing the value
	 * - title: (required) The label displayed to users
	 * - description: (optional) Help text shown below the field
	 * - default: (optional) Default value if none is set
	 * - required: (optional) Boolean, marks field as required
	 * - placeholder: (optional) Placeholder text for input fields
	 *
	 * ## Custom Field Types:
	 * If you need a field type not listed above, the value will be passed
	 * through without validation (see the default case in validate_settings()).
	 * You should implement custom validation in your channel class.
	 *
	 * @return array Array of settings fields.
	 */
	public function get_settings_fields() {
		return [
			[
				'type'        => 'checkbox',
				'name'        => 'enabled',
				'title'       => __( 'Enable Channel', 'simple-history' ),
				'description' => sprintf(
					/* translators: %s: Channel name */
					__( 'Enable', 'simple-history' ),
					$this->get_name()
				),
			],
		];
	}

	/**
	 * Get the current settings for this channel.
	 *
	 * @return array Array of current settings.
	 */
	public function get_settings() {
		$defaults = $this->get_default_settings();
		/** @var array<string, mixed> $saved_settings */
		$saved_settings = get_option( $this->get_settings_option_name(), [] );

		return wp_parse_args( $saved_settings, $defaults );
	}

	/**
	 * Get a specific setting value for this channel.
	 *
	 * @param string $setting_name The name of the setting to retrieve.
	 * @param mixed  $default Optional. Default value to return if setting doesn't exist.
	 * @return mixed The setting value or default if not found.
	 */
	public function get_setting( $setting_name, $default = null ) {
		$settings = $this->get_settings();
		return $settings[ $setting_name ] ?? $default;
	}

	/**
	 * Set a specific setting value for this channel.
	 *
	 * @param string $setting_name The name of the setting to set.
	 * @param mixed  $value The value to set.
	 * @return bool True on success, false on failure.
	 */
	public function set_setting( $setting_name, $value ) {
		$settings                  = $this->get_settings();
		$settings[ $setting_name ] = $value;
		return $this->save_settings( $settings );
	}

	/**
	 * Get the WordPress option name for this channel's settings.
	 *
	 * Computed lazily from the channel slug.
	 *
	 * @return string The option name used to store settings in the database.
	 */
	public function get_settings_option_name() {
		return 'simple_history_channel_' . $this->get_slug();
	}

	/**
	 * Get the default settings for this channel.
	 *
	 * @return array Array of default settings.
	 */
	protected function get_default_settings() {
		$defaults = [ 'enabled' => false ];

		// Extract defaults from settings fields.
		foreach ( $this->get_settings_fields() as $field ) {
			/** @var array<string, mixed> $field - Individual settings field configuration */
			if ( isset( $field['default'] ) ) {
				$defaults[ $field['name'] ] = $field['default'];
			}
		}

		return $defaults;
	}

	/**
	 * Save settings for this channel.
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

		return update_option( $this->get_settings_option_name(), $validated_settings );
	}

	/**
	 * Validate settings before saving.
	 *
	 * @param array $settings The settings to validate.
	 * @return array|\WP_Error Validated settings or WP_Error on failure.
	 */
	protected function validate_settings( $settings ) {
		$validated = [];
		$fields    = $this->get_settings_fields();

		foreach ( $fields as $field ) {
			$name  = $field['name'];
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

				case 'number':
					$validated[ $name ] = intval( $value );

					// Check min/max bounds if specified.
					if ( isset( $field['min'] ) && $validated[ $name ] < $field['min'] ) {
						$validated[ $name ] = $field['min'];
					}
					if ( isset( $field['max'] ) && $validated[ $name ] > $field['max'] ) {
						$validated[ $name ] = $field['max'];
					}
					break;

				case 'select':
					// Validate that the value is one of the allowed options.
					if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
						if ( array_key_exists( $value, $field['options'] ) ) {
							$validated[ $name ] = $value;
						} else {
							// Use the default or first option if invalid value.
							$validated[ $name ] = $field['default'] ?? array_key_first( $field['options'] );
						}
					} else {
						$validated[ $name ] = $value;
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
	 * Get the alert rules for this channel.
	 *
	 * @return array Array of alert rules.
	 */
	public function get_alert_rules() {
		return $this->get_setting( 'alert_rules', [] );
	}

	/**
	 * Set the alert rules for this channel.
	 *
	 * @param array $rules Array of alert rules.
	 * @return bool True on success, false on failure.
	 */
	public function set_alert_rules( $rules ) {
		return $this->set_setting( 'alert_rules', $rules );
	}

	/**
	 * Check if an event should be sent based on alert rules.
	 *
	 * @param array $event_data The event data to check.
	 * @return bool True if event should be sent, false otherwise.
	 */
	public function should_send_event( $event_data ) {
		// If channel is not enabled, don't send.
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
	 * Log an error for this channel.
	 *
	 * @param string $message The error message.
	 * @param array  $context Additional context data.
	 */
	protected function log_error( $message, $context = [] ) {
		$log_message = sprintf(
			'Simple History Channel %s: %s',
			$this->get_slug(),
			$message
		);

		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message );
	}

	/**
	 * Log debug information for this channel.
	 *
	 * @param string $message The debug message.
	 * @param array  $context Additional context data.
	 */
	protected function log_debug( $message, $context = [] ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_message = sprintf(
			'Simple History Channel %s (DEBUG): %s',
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
	 * channel-specific information to users.
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
	 * channel-specific information to users.
	 *
	 * @return string HTML content to display, or empty string if none.
	 */
	public function get_settings_info_after_fields_html() {
		return '';
	}
}
