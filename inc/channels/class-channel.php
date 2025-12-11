<?php

namespace Simple_History\Channels;

use Simple_History\Channels\Interfaces\Channel_Interface;
use Simple_History\Helpers;

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
	 * Add settings fields for this channel using WordPress Settings API.
	 *
	 * Override this method in child classes to add custom settings fields.
	 * The base implementation adds the "Enable" checkbox.
	 *
	 * @param string $settings_page_slug The settings page slug.
	 * @param string $settings_section_id The settings section ID.
	 */
	public function add_settings_fields( $settings_page_slug, $settings_section_id ) {
		// Add the enable checkbox - common to all channels.
		add_settings_field(
			$this->get_settings_option_name() . '_enabled',
			Helpers::get_settings_field_title_output( __( 'Status', 'simple-history' ) ),
			[ $this, 'settings_field_enabled' ],
			$settings_page_slug,
			$settings_section_id
		);
	}

	/**
	 * Render the "Status" settings field.
	 */
	public function settings_field_enabled() {
		$enabled     = $this->is_enabled();
		$option_name = $this->get_settings_option_name();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $option_name ); ?>[enabled]"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Enabled', 'simple-history' ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitize settings for this channel.
	 *
	 * Override this method in child classes for custom sanitization.
	 * The base implementation handles the "enabled" checkbox.
	 *
	 * @param array $input Raw input data from form submission.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		// Start with existing settings to preserve non-form values (like folder_token).
		$sanitized = $this->get_settings();

		// Handle enabled checkbox.
		$sanitized['enabled'] = ! empty( $input['enabled'] );

		return $sanitized;
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
	 * @param mixed  $default_value Optional. Default value to return if setting doesn't exist.
	 * @return mixed The setting value or default if not found.
	 */
	public function get_setting( $setting_name, $default_value = null ) {
		$settings = $this->get_settings();
		return $settings[ $setting_name ] ?? $default_value;
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
	 * Override in child classes to add additional defaults.
	 *
	 * @return array Array of default settings.
	 */
	protected function get_default_settings() {
		return [ 'enabled' => false ];
	}

	/**
	 * Save settings for this channel.
	 *
	 * @param array $settings The settings to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_settings( $settings ) {
		return update_option( $this->get_settings_option_name(), $settings );
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
	 * Output HTML after the description in the intro section.
	 *
	 * Override this method to add custom HTML content after the
	 * channel description paragraph.
	 */
	public function settings_output_intro() {
		// Default implementation does nothing.
		// Override in child classes to add custom content.
	}

	/**
	 * Output HTML after the settings fields.
	 *
	 * Override this method to add custom HTML content at the bottom
	 * of the channel's settings section, after all fields.
	 */
	public function settings_output_after_fields() {
		// Default implementation does nothing.
		// Override in child classes to add custom content.
	}
}
