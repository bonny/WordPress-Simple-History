<?php

namespace Simple_History\Channels\Formatters;

use Simple_History\Log_Levels;

/**
 * Abstract base class for log entry formatters.
 *
 * Provides common functionality for all formatters including
 * log level to syslog severity mapping and timestamp helpers.
 *
 * @since 5.7.0
 */
abstract class Formatter implements Formatter_Interface {
	/**
	 * Map Simple History log levels to syslog severity (RFC 5424).
	 *
	 * @var array<string, int>
	 */
	protected const SYSLOG_SEVERITY_MAP = [
		Log_Levels::EMERGENCY => 0,
		Log_Levels::ALERT     => 1,
		Log_Levels::CRITICAL  => 2,
		Log_Levels::ERROR     => 3,
		Log_Levels::WARNING   => 4,
		Log_Levels::NOTICE    => 5,
		Log_Levels::INFO      => 6,
		Log_Levels::DEBUG     => 7,
	];

	/**
	 * Essential context fields to include in formatted output.
	 *
	 * @var array<int, string>
	 */
	protected const ESSENTIAL_FIELDS = [
		'_message_key',
		'_server_remote_addr',
		'_user_id',
		'_user_login',
		'_user_email',
	];

	/**
	 * Get the event ID from event data.
	 *
	 * @param array $event_data The event data array.
	 * @return int|null The event ID or null if not available.
	 */
	protected function get_event_id( array $event_data ): ?int {
		return isset( $event_data['id'] ) ? (int) $event_data['id'] : null;
	}

	/**
	 * Get syslog severity from Simple History log level.
	 *
	 * @param string $level The Simple History log level.
	 * @return int Syslog severity (0-7).
	 */
	protected function get_syslog_severity( string $level ): int {
		$level_lower = strtolower( $level );
		return self::SYSLOG_SEVERITY_MAP[ $level_lower ] ?? 6;
	}

	/**
	 * Get event timestamp in ISO 8601 UTC format.
	 *
	 * The event date is already stored in UTC/GMT in the database.
	 *
	 * @param string $event_date The event date in MySQL format (Y-m-d H:i:s), already in UTC.
	 * @return string ISO 8601 formatted timestamp in UTC with 'Z' suffix.
	 */
	protected function get_event_timestamp_utc( string $event_date ): string {
		// Event date is already in UTC, just reformat to ISO 8601.
		$datetime = date_create( $event_date, new \DateTimeZone( 'UTC' ) );
		if ( $datetime === false ) {
			// Fallback to current UTC time if parsing fails.
			return gmdate( 'Y-m-d\TH:i:s\Z' );
		}
		return $datetime->format( 'Y-m-d\TH:i:s\Z' );
	}

	/**
	 * Get current timestamp in ISO 8601 format with timezone.
	 *
	 * @deprecated Use get_event_timestamp_utc() with event date instead.
	 * @return string ISO 8601 formatted timestamp.
	 */
	protected function get_iso8601_timestamp(): string {
		return current_time( 'c' );
	}

	/**
	 * Get event timestamp as Unix timestamp.
	 *
	 * The event date is already stored in UTC/GMT in the database.
	 *
	 * @param string $event_date The event date in MySQL format (Y-m-d H:i:s), already in UTC.
	 * @return float Unix timestamp.
	 */
	protected function get_event_timestamp_unix( string $event_date ): float {
		// Event date is already in UTC.
		$datetime = date_create( $event_date, new \DateTimeZone( 'UTC' ) );
		if ( $datetime === false ) {
			// Fallback to current time if parsing fails.
			return microtime( true );
		}
		return (float) $datetime->format( 'U' );
	}

	/**
	 * Get current timestamp as Unix timestamp with milliseconds.
	 *
	 * @deprecated Use get_event_timestamp_unix() with event date instead.
	 * @return float Unix timestamp with milliseconds.
	 */
	protected function get_unix_timestamp(): float {
		return microtime( true );
	}

	/**
	 * Get the site hostname.
	 *
	 * @return string The site hostname.
	 */
	protected function get_hostname(): string {
		$parsed = wp_parse_url( get_site_url() );
		return $parsed['host'] ?? 'localhost';
	}

	/**
	 * Extract essential fields from event context.
	 *
	 * Returns only scalar values from the essential fields list,
	 * with leading underscores removed from keys.
	 *
	 * @param array $context The event context array.
	 * @return array<string, mixed> Filtered context with clean keys.
	 */
	protected function get_essential_context( array $context ): array {
		$result = [];

		foreach ( self::ESSENTIAL_FIELDS as $field ) {
			if ( isset( $context[ $field ] ) && is_scalar( $context[ $field ] ) ) {
				$clean_key            = ltrim( $field, '_' );
				$result[ $clean_key ] = $context[ $field ];
			}
		}

		return $result;
	}

	/**
	 * Sanitize a value for safe inclusion in structured data.
	 *
	 * @param mixed $value The value to sanitize.
	 * @return string|int|float|bool The sanitized scalar value.
	 */
	protected function sanitize_value( $value ) {
		if ( is_scalar( $value ) ) {
			return $value;
		}
		return wp_json_encode( $value );
	}
}
