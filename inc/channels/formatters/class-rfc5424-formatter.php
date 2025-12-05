<?php

namespace Simple_History\Channels\Formatters;

/**
 * RFC 5424 Syslog formatter.
 *
 * Produces log entries conforming to RFC 5424 (The Syslog Protocol).
 * Includes structured data for machine parsing while maintaining
 * compatibility with syslog infrastructure.
 *
 * Example output:
 * <14>1 2025-12-05T12:34:56Z example.com SimpleHistory - user_logged_in [simplehistory@0 level="info" logger="SimpleUserLogger" initiator="wp_user" message_key="user_logged_in" user_id="42"] User logged in
 *
 * @since 5.7.0
 * @see https://datatracker.ietf.org/doc/html/rfc5424
 */
class Rfc5424_Formatter extends Formatter {
	/**
	 * Syslog facility for user-level messages.
	 *
	 * @var int
	 */
	private const FACILITY_USER = 1;

	/**
	 * RFC 5424 version.
	 *
	 * @var int
	 */
	private const VERSION = 1;

	/**
	 * Nil value for optional fields.
	 *
	 * @var string
	 */
	private const NILVALUE = '-';

	/**
	 * SD-ID for Simple History structured data.
	 * Using enterprise number 0 as placeholder (reserved for examples).
	 *
	 * @var string
	 */
	private const SD_ID = 'simplehistory@0';

	/**
	 * Application name for syslog.
	 *
	 * @var string
	 */
	private const APP_NAME = 'SimpleHistory';

	/**
	 * Get the unique identifier for this formatter.
	 *
	 * @return string The formatter slug.
	 */
	public function get_slug() {
		return 'rfc5424';
	}

	/**
	 * Get the display name for this formatter.
	 *
	 * @return string The human-readable formatter name.
	 */
	public function get_name() {
		return __( 'RFC 5424 Syslog', 'simple-history' );
	}

	/**
	 * Get the description for this formatter.
	 *
	 * @return string Short description of the format output.
	 */
	public function get_description() {
		return __( 'Standard syslog format with structured data. Best for syslog servers and SIEM tools.', 'simple-history' );
	}

	/**
	 * Format a log entry as RFC 5424 syslog message.
	 *
	 * @param array  $event_data        The event data array.
	 * @param string $formatted_message The interpolated message string.
	 * @return string The formatted syslog entry with newline.
	 */
	public function format( array $event_data, string $formatted_message ) {
		$level     = strtolower( $event_data['level'] ?? 'info' );
		$logger    = $event_data['logger'] ?? 'Unknown';
		$initiator = $event_data['initiator'] ?? 'unknown';
		$context   = $event_data['context'] ?? [];
		$date      = $event_data['date'] ?? current_time( 'mysql' );

		// Calculate PRI value: facility * 8 + severity.
		$severity = $this->get_syslog_severity( $level );
		$pri      = ( self::FACILITY_USER * 8 ) + $severity;

		// Build structured data.
		$structured_data = $this->build_structured_data( $level, $logger, $initiator, $context );

		// Use message_key as MSGID if available.
		$msgid = isset( $context['_message_key'] ) ? $context['_message_key'] : self::NILVALUE;

		// RFC 5424 format.
		// <PRI>VERSION TIMESTAMP HOSTNAME APP-NAME PROCID MSGID STRUCTURED-DATA MSG.
		return sprintf(
			"<%d>%d %s %s %s %s %s %s %s\n",
			$pri,
			self::VERSION,
			$this->get_event_timestamp_utc( $date ),
			$this->get_hostname(),
			self::APP_NAME,
			self::NILVALUE, // PROCID.
			$msgid, // MSGID - uses message_key if available.
			$structured_data,
			$formatted_message
		);
	}

	/**
	 * Build RFC 5424 structured data element.
	 *
	 * Format: [SD-ID key="val" key2="val2"]
	 *
	 * @param string $level     The log level.
	 * @param string $logger    The logger slug.
	 * @param string $initiator The initiator type.
	 * @param array  $context   The event context.
	 * @return string Structured data string or NILVALUE if empty.
	 */
	private function build_structured_data( string $level, string $logger, string $initiator, array $context ): string {
		$params = [];

		// Add level, logger and initiator.
		$params[] = $this->format_sd_param( 'level', $level );
		$params[] = $this->format_sd_param( 'logger', $logger );
		$params[] = $this->format_sd_param( 'initiator', $initiator );

		// Add essential context fields.
		$essential = $this->get_essential_context( $context );
		foreach ( $essential as $key => $value ) {
			$params[] = $this->format_sd_param( $key, (string) $value );
		}

		return '[' . self::SD_ID . ' ' . implode( ' ', $params ) . ']';
	}

	/**
	 * Format a single structured data parameter.
	 *
	 * Escapes special characters per RFC 5424 section 6.3.3.
	 *
	 * @param string $name  Parameter name.
	 * @param string $value Parameter value.
	 * @return string Formatted parameter (name="escaped_value").
	 */
	private function format_sd_param( string $name, string $value ): string {
		// RFC 5424: Within PARAM-VALUE, the characters '"' (ABNF %d34),
		// '\' (ABNF %d92), and ']' (ABNF %d93) MUST be escaped.
		$escaped = str_replace(
			[ '\\', '"', ']' ],
			[ '\\\\', '\\"', '\\]' ],
			$value
		);

		return $name . '="' . $escaped . '"';
	}
}
