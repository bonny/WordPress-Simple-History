<?php

namespace Simple_History\Channels\Formatters;

/**
 * JSON Lines formatter (GELF 1.1 compatible).
 *
 * Produces one JSON object per line, compatible with Graylog Extended Log Format.
 * Ideal for log aggregation tools like Graylog, ELK, Splunk, Datadog.
 *
 * Example output:
 * {"version":"1.1","host":"example.com","short_message":"User logged in","timestamp":1733414096.123,"level":6,"_logger":"SimpleUserLogger","_message_key":"user_logged_in","_user_id":"42","_initiator":"wp_user"}
 *
 * @since 5.7.0
 * @see https://archivedocs.graylog.org/en/latest/pages/gelf.html
 */
class Json_Lines_Formatter extends Formatter {
	/**
	 * GELF specification version.
	 *
	 * @var string
	 */
	private const GELF_VERSION = '1.1';

	/**
	 * Get the unique identifier for this formatter.
	 *
	 * @return string The formatter slug.
	 */
	public function get_slug() {
		return 'json_lines';
	}

	/**
	 * Get the display name for this formatter.
	 *
	 * @return string The human-readable formatter name.
	 */
	public function get_name() {
		return __( 'JSON Lines (GELF)', 'simple-history' );
	}

	/**
	 * Get the description for this formatter.
	 *
	 * @return string Short description of the format output.
	 */
	public function get_description() {
		return __( 'One JSON object per line. Compatible with Graylog, ELK, Splunk, and other log aggregation tools.', 'simple-history' );
	}

	/**
	 * Format a log entry as GELF-compatible JSON.
	 *
	 * @param array  $event_data        The event data array.
	 * @param string $formatted_message The interpolated message string.
	 * @return string The formatted JSON log entry with newline.
	 */
	public function format( array $event_data, string $formatted_message ) {
		$level     = strtolower( $event_data['level'] ?? 'info' );
		$logger    = $event_data['logger'] ?? 'Unknown';
		$initiator = $event_data['initiator'] ?? 'unknown';
		$context   = $event_data['context'] ?? [];
		$date      = $event_data['date'] ?? current_time( 'mysql', 1 );

		// Build GELF-compatible structure.
		// Required fields first.
		$gelf = [
			'version'       => self::GELF_VERSION,
			'host'          => $this->get_hostname(),
			'short_message' => $formatted_message,
			'timestamp'     => $this->get_event_timestamp_unix( $date ),
			'level'         => $this->get_syslog_severity( $level ),
		];

		// Add custom fields with underscore prefix (GELF requirement).
		$gelf['_logger']    = $logger;
		$gelf['_initiator'] = $initiator;

		// Add essential context fields.
		$essential = $this->get_essential_context( $context );
		foreach ( $essential as $key => $value ) {
			$gelf[ '_' . $key ] = $this->sanitize_value( $value );
		}

		// Encode as JSON without pretty printing (one line).
		$json = wp_json_encode( $gelf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return $json . "\n";
	}
}
