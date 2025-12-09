<?php

namespace Simple_History\Channels\Interfaces;

/**
 * Interface for all channels.
 *
 * Defines the contract that all channels must implement.
 * This includes methods for sending events, managing settings,
 * and determining channel capabilities.
 *
 * @since 4.4.0
 */
interface Channel_Interface {
	/**
	 * Called when the channel is loaded and ready.
	 *
	 * This method is called by the Channels_Manager after registration.
	 * Use this to register hooks and perform initialization that has side effects.
	 * Keeping this separate from construction allows for side-effect-free instantiation.
	 */
	public function loaded();

	/**
	 * Get the unique slug for this channel.
	 *
	 * @return string The channel slug.
	 */
	public function get_slug();

	/**
	 * Get the display name for this channel.
	 *
	 * @return string The channel display name.
	 */
	public function get_name();

	/**
	 * Get the description for this channel.
	 *
	 * @return string The channel description.
	 */
	public function get_description();

	/**
	 * Check if this channel is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled();

	/**
	 * Check if this channel supports async processing.
	 *
	 * @return bool True if supports async, false for synchronous only.
	 */
	public function supports_async();

	/**
	 * Send an event to this channel.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	public function send_event( $event_data, $formatted_message );

	/**
	 * Add settings fields for this channel using WordPress Settings API.
	 *
	 * Called during admin_init to register settings fields.
	 *
	 * @param string $settings_page_slug The settings page slug.
	 * @param string $settings_section_id The settings section ID.
	 */
	public function add_settings_fields( $settings_page_slug, $settings_section_id );

	/**
	 * Sanitize settings for this channel.
	 *
	 * @param array $input Raw input data from form submission.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input );

	/**
	 * Get the current settings for this channel.
	 *
	 * @return array Array of current settings.
	 */
	public function get_settings();

	/**
	 * Get a specific setting value for this channel.
	 *
	 * @param string $setting_name The name of the setting to retrieve.
	 * @param mixed  $default_value Optional. Default value to return if setting doesn't exist.
	 * @return mixed The setting value or default if not found.
	 */
	public function get_setting( $setting_name, $default_value = null );

	/**
	 * Set a specific setting value for this channel.
	 *
	 * @param string $setting_name The name of the setting to set.
	 * @param mixed  $value The value to set.
	 * @return bool True on success, false on failure.
	 */
	public function set_setting( $setting_name, $value );

	/**
	 * Get the WordPress option name for this channel's settings.
	 *
	 * @return string The option name used to store settings in the database.
	 */
	public function get_settings_option_name();

	/**
	 * Save settings for this channel.
	 *
	 * @param array $settings The settings to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_settings( $settings );

	/**
	 * Get the alert rules for this channel.
	 *
	 * @return array Array of alert rules.
	 */
	public function get_alert_rules();

	/**
	 * Set the alert rules for this channel.
	 *
	 * @param array $rules Array of alert rules.
	 * @return bool True on success, false on failure.
	 */
	public function set_alert_rules( $rules );

	/**
	 * Check if an event should be sent based on alert rules.
	 *
	 * @param array $event_data The event data to check.
	 * @return bool True if event should be sent, false otherwise.
	 */
	public function should_send_event( $event_data );

	/**
	 * Output HTML after the description in the intro section.
	 *
	 * Override this method to add custom HTML content after the
	 * channel description paragraph.
	 */
	public function settings_output_intro();

	/**
	 * Output HTML after the settings fields.
	 *
	 * Override this method to add custom HTML content at the bottom
	 * of the channel's settings section, after all fields.
	 */
	public function settings_output_after_fields();
}
