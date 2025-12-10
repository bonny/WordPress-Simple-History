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
	 * Essential context fields for human-readable output.
	 * Excludes server_remote_addr as it adds noise to manual log inspection.
	 *
	 * @var array<int, string>
	 */
	protected const HUMAN_READABLE_FIELDS = [
		'_message_key',
		'_user_id',
		'_user_login',
		'_user_email',
	];

	/**
	 * Map of log level lengths for alignment.
	 * Longest level is "EMERGENCY" (9 chars).
	 *
	 * @var int
	 */
	private const LEVEL_WIDTH = 9;

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

		// Pad level to fixed width for alignment.
		$level_padded = str_pad( $level, self::LEVEL_WIDTH, ' ', STR_PAD_RIGHT );

		// Build structured data from essential fields (human-readable subset).
		$structured_parts = [];

		// Always include initiator.
		$structured_parts[] = 'initiator=' . $initiator;

		$essential = $this->get_human_readable_context( $context );

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
			$level_padded,
			$logger,
			$formatted_message,
			$structured_suffix
		);
	}

	/**
	 * Extract essential fields for human-readable output.
	 *
	 * Uses a reduced set of fields compared to other formatters,
	 * excluding server_remote_addr to reduce noise.
	 *
	 * @param array $context The event context array.
	 * @return array<string, mixed> Filtered context with clean keys.
	 */
	protected function get_human_readable_context( array $context ): array {
		$result = [];

		foreach ( self::HUMAN_READABLE_FIELDS as $field ) {
			if ( isset( $context[ $field ] ) && is_scalar( $context[ $field ] ) ) {
				$clean_key            = ltrim( $field, '_' );
				$result[ $clean_key ] = $context[ $field ];
			}
		}

		return $result;
	}
}
