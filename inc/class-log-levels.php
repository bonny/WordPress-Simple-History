<?php

namespace Simple_History;

/**
 * Describes log levels.
 */
class Log_Levels {
	public const EMERGENCY = 'emergency';
	public const ALERT     = 'alert';
	public const CRITICAL  = 'critical';
	public const ERROR     = 'error';
	public const WARNING   = 'warning';
	public const NOTICE    = 'notice';
	public const INFO      = 'info';
	public const DEBUG     = 'debug';

	/**
	 * Return translated loglevel.
	 *
	 * @since 2.0.14
	 * @param string $loglevel Level as lowercase i.e. "info" or with first char uppercase i.e. "Info".
	 * @return string translated loglevel
	 */
	public static function get_log_level_translated( $loglevel ) {
		$str_translated = '';

		switch ( $loglevel ) {
			// Lowercase.
			case 'emergency':
				$str_translated = _x( 'emergency', 'Log level in gui', 'simple-history' );
				break;

			case 'alert':
				$str_translated = _x( 'alert', 'Log level in gui', 'simple-history' );
				break;

			case 'critical':
				$str_translated = _x( 'critical', 'Log level in gui', 'simple-history' );
				break;

			case 'error':
				$str_translated = _x( 'error', 'Log level in gui', 'simple-history' );
				break;

			case 'warning':
				$str_translated = _x( 'warning', 'Log level in gui', 'simple-history' );
				break;

			case 'notice':
				$str_translated = _x( 'notice', 'Log level in gui', 'simple-history' );
				break;

			case 'info':
				$str_translated = _x( 'info', 'Log level in gui', 'simple-history' );
				break;

			case 'debug':
				$str_translated = _x( 'debug', 'Log level in gui', 'simple-history' );
				break;

			// Uppercase.
			case 'Emergency':
				$str_translated = _x( 'Emergency', 'Log level in gui', 'simple-history' );
				break;

			case 'Alert':
				$str_translated = _x( 'Alert', 'Log level in gui', 'simple-history' );
				break;

			case 'Critical':
				$str_translated = _x( 'Critical', 'Log level in gui', 'simple-history' );
				break;

			case 'Error':
				$str_translated = _x( 'Error', 'Log level in gui', 'simple-history' );
				break;

			case 'Warning':
				$str_translated = _x( 'Warning', 'Log level in gui', 'simple-history' );
				break;

			case 'Notice':
				$str_translated = _x( 'Notice', 'Log level in gui', 'simple-history' );
				break;

			case 'Info':
				$str_translated = _x( 'Info', 'Log level in gui', 'simple-history' );
				break;

			case 'Debug':
				$str_translated = _x( 'Debug', 'Log level in gui', 'simple-history' );
				break;

			default:
				$str_translated = $loglevel;
		}

		return $str_translated;
	}

	/**
	 * Get all valid log levels.
	 *
	 * @since 5.13.1
	 * @return array Array of valid log levels.
	 */
	public static function get_valid_log_levels() {
		return array(
			self::EMERGENCY,
			self::ALERT,
			self::CRITICAL,
			self::ERROR,
			self::WARNING,
			self::NOTICE,
			self::INFO,
			self::DEBUG,
		);
	}

	/**
	 * Check if a string is a valid log level.
	 *
	 * @since 4.0.0
	 * @param string $level Level to check.
	 * @return bool True if valid log level, false otherwise.
	 */
	public static function is_valid_level( $level ) {
		return in_array( strtolower( $level ), self::get_valid_log_levels(), true );
	}
}
