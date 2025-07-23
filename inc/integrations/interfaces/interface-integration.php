<?php

namespace Simple_History\Integrations\Interfaces;

/**
 * Interface for all integrations.
 *
 * Defines the contract that all integrations must implement.
 * This includes methods for sending events, managing settings,
 * and determining integration capabilities.
 *
 * @since 4.4.0
 */
interface Integration_Interface {
	/**
	 * Get the unique slug for this integration.
	 *
	 * @return string The integration slug.
	 */
	public function get_slug();

	/**
	 * Get the display name for this integration.
	 *
	 * @return string The integration display name.
	 */
	public function get_name();

	/**
	 * Get the description for this integration.
	 *
	 * @return string The integration description.
	 */
	public function get_description();

	/**
	 * Check if this integration is enabled.
	 *
	 * @return bool True if enabled, false otherwise.
	 */
	public function is_enabled();

	/**
	 * Check if this integration supports async processing.
	 *
	 * @return bool True if supports async, false for synchronous only.
	 */
	public function supports_async();

	/**
	 * Send an event to this integration.
	 *
	 * @param array $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	public function send_event( $event_data, $formatted_message );

	/**
	 * Get the settings fields for this integration.
	 *
	 * @return array Array of settings fields.
	 */
	public function get_settings_fields();

	/**
	 * Get the current settings for this integration.
	 *
	 * @return array Array of current settings.
	 */
	public function get_settings();

	/**
	 * Save settings for this integration.
	 *
	 * @param array $settings The settings to save.
	 * @return bool True on success, false on failure.
	 */
	public function save_settings( $settings );

	/**
	 * Test the integration connection/configuration.
	 *
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	public function test_connection();

	/**
	 * Get the alert rules for this integration.
	 *
	 * @return array Array of alert rules.
	 */
	public function get_alert_rules();

	/**
	 * Set the alert rules for this integration.
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
}