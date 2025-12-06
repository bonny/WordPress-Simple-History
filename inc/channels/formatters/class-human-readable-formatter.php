<?php

namespace Simple_History\Channels\Formatters;

/**
 * Human-readable log formatter.
 *
 * Produces easy-to-read log entries with ISO 8601 timestamps
 * and key=value metadata.
 *
 * Example output:
 * [2025-12-05T12:34:56Z] INFO SimpleUserLogger: User logged in | message_key=user_logged_in user_id=42 initiator=wp_user
 *
 * @since 5.7.0
 */
class Human_Readable_Formatter extends Formatter {
	/**
	 * Get the unique identifier for this formatter.
	 *
	 * @return string The formatter slug.
	 */
	public function get_slug() {
		return 'human_readable';
	}

	/**
	 * Get the display name for this formatter.
	 *
	 * @return string The human-readable formatter name.
	 */
	public function get_name() {
		return __( 'Human-readable', 'simple-history' );
	}

	/**
	 * Get the description for this formatter.
	 *
	 * @return string Short description of the format output.
	 */
	public function get_description() {
		return __( 'Easy-to-read format with ISO 8601 timestamps. Best for manual log inspection.', 'simple-history' );
	}

	/**
	 * Format a log entry in human-readable format.
	 *
	 * @param array  $event_data        The event data array.
	 * @param string $formatted_message The interpolated message string.
	 * @return string The formatted log entry with newline.
	 */
	public function format( array $event_data, string $formatted_message ) {
		$date      = $event_data['date'] ?? current_time( 'mysql', 1 );
		$timestamp = $this->get_event_timestamp_utc( $date );
		$level     = strtoupper( $event_data['level'] ?? 'info' );
		$logger    = $event_data['logger'] ?? 'Unknown';
		$initiator = $event_data['initiator'] ?? 'unknown';
		$context   = $event_data['context'] ?? [];

		// Build structured data from essential fields.
		$structured_parts = [];

		// Always include initiator.
		$structured_parts[] = 'initiator=' . $initiator;

		$essential = $this->get_essential_context( $context );

		foreach ( $essential as $key => $value ) {
			$structured_parts[] = $key . '=' . $value;
		}

		$structured_suffix = '';
		if ( count( $structured_parts ) > 0 ) {
			$structured_suffix = ' | ' . implode( ' ', $structured_parts );
		}

		return sprintf(
			"[%s] %s %s: %s%s\n",
			$timestamp,
			$level,
			$logger,
			$formatted_message,
			$structured_suffix
		);
	}
}
