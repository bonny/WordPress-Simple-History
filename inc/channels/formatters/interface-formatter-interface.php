<?php

namespace Simple_History\Channels\Formatters;

/**
 * Interface for log entry formatters.
 *
 * Formatters transform event data into a specific string format
 * suitable for different output destinations (files, syslog, etc.).
 *
 * @since 5.7.0
 */
interface Formatter_Interface {
	/**
	 * Get the unique identifier for this formatter.
	 *
	 * @return string The formatter slug (e.g., 'json_lines', 'rfc5424', 'human_readable').
	 */
	public function get_slug();

	/**
	 * Get the display name for this formatter.
	 *
	 * @return string The human-readable formatter name.
	 */
	public function get_name();

	/**
	 * Get the description for this formatter.
	 *
	 * @return string Short description of the format output.
	 */
	public function get_description();

	/**
	 * Format a log entry.
	 *
	 * @param array  $event_data        The event data array from Channels_Manager.
	 * @param string $formatted_message The interpolated message string.
	 * @return string The formatted log entry (including newline if appropriate).
	 */
	public function format( array $event_data, string $formatted_message );
}
