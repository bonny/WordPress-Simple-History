<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Loggers\Logger;

/**
 * Limits logging of consecutive failed login attempts to prevent database bloat.
 *
 * Stops logging after 100 consecutive failed login attempts (known or unknown users).
 * Counter resets when any non-failed-login event is logged.
 *
 * At 100, normal users never hit the limit (even with bad memory), while brute force
 * attacks with thousands of attempts are effectively capped. The admin still gets
 * 100 logged entries — plenty to see IP, username, and timing patterns.
 *
 * Premium overrides this with configurable per-user-type thresholds.
 *
 * @since 5.24.0
 */
class Failed_Login_Limit_Service extends Service {

	/** @var int Default maximum consecutive failed login attempts to log. */
	private const DEFAULT_THRESHOLD = 100;

	/** @var string Option name for the consecutive failed attempts counter. */
	private const OPTION_COUNTER = 'sh_core_failed_login_count';

	/** @var string Option name for the all-time total of suppressed attempts. */
	private const OPTION_TOTAL_SUPPRESSED = 'sh_core_failed_login_total_suppressed';

	/** @var string[] Message keys for failed login events. */
	private const FAILED_LOGIN_MESSAGE_KEYS = [
		'user_login_failed',
		'user_unknown_login_failed',
	];

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		if ( ! self::is_active() ) {
			return;
		}

		// Priority 9 so premium (priority 10) can override.
		add_filter( 'simple_history/log/do_log', [ $this, 'maybe_limit_failed_login' ], 9, 5 );
		add_filter( 'simple_history/log/do_log', [ $this, 'maybe_reset_counter' ], 9, 5 );
	}

	/**
	 * Get the threshold for consecutive failed login attempts.
	 *
	 * @since 5.24.0
	 *
	 * @return int
	 */
	public static function get_threshold() {
		/**
		 * Filter the maximum number of consecutive failed login attempts to log.
		 *
		 * @since 5.24.0
		 *
		 * @param int $threshold Default 100.
		 */
		return (int) apply_filters( 'simple_history/failed_login_limit/threshold', self::DEFAULT_THRESHOLD );
	}

	/**
	 * Check if a failed login should be logged based on the consecutive attempt counter.
	 *
	 * @param bool   $do_log Whether to log the event.
	 * @param string $level  Log level.
	 * @param string $message Log message.
	 * @param array  $context Message context.
	 * @param Logger $logger Logger instance.
	 * @return bool Whether to log the event.
	 */
	public function maybe_limit_failed_login( $do_log, $level, $message, $context, $logger ) {
		if ( ! $do_log ) {
			return $do_log;
		}

		if ( ! $this->is_failed_login( $context, $logger ) ) {
			return $do_log;
		}

		$threshold = self::get_threshold();
		$count     = (int) get_option( self::OPTION_COUNTER, 0 );
		++$count;
		update_option( self::OPTION_COUNTER, $count, false );

		if ( $count > $threshold ) {
			// Increment the all-time suppressed total.
			$total = (int) get_option( self::OPTION_TOTAL_SUPPRESSED, 0 );
			update_option( self::OPTION_TOTAL_SUPPRESSED, $total + 1, false );

			return false;
		}

		return $do_log;
	}

	/**
	 * Reset the counter when a non-failed-login event is logged.
	 *
	 * @param bool   $do_log Whether to log the event.
	 * @param string $level  Log level.
	 * @param string $message Log message.
	 * @param array  $context Message context.
	 * @param Logger $logger Logger instance.
	 * @return bool Whether to log the event (always unchanged).
	 */
	public function maybe_reset_counter( $do_log, $level, $message, $context, $logger ) {
		if ( $this->is_failed_login( $context, $logger ) ) {
			return $do_log;
		}

		$count = (int) get_option( self::OPTION_COUNTER, 0 );

		// Only write to DB if counter is not already 0.
		if ( $count !== 0 ) {
			update_option( self::OPTION_COUNTER, 0, false );
		}

		return $do_log;
	}

	/**
	 * Check if an event is a failed login attempt.
	 *
	 * @param array  $context Message context.
	 * @param Logger $logger Logger instance.
	 * @return bool
	 */
	private function is_failed_login( $context, $logger ) {
		if ( ! $logger instanceof \Simple_History\Loggers\User_Logger ) {
			return false;
		}

		$message_key = $context['_message_key'] ?? '';

		return in_array( $message_key, self::FAILED_LOGIN_MESSAGE_KEYS, true );
	}

	/**
	 * Get the number of currently suppressed attempts.
	 *
	 * Only returns a count when an attack is actively ongoing
	 * (counter > threshold). Returns 0 once the burst ends and
	 * the counter resets, so the event list banner disappears.
	 *
	 * @return int
	 */
	public static function get_last_suppressed_count() {
		$current_count = (int) get_option( self::OPTION_COUNTER, 0 );

		if ( $current_count === 0 ) {
			return 0;
		}

		$threshold = self::get_threshold();

		return $current_count > $threshold
			? $current_count - $threshold
			: 0;
	}

	/**
	 * Get the all-time total number of suppressed failed login attempts.
	 *
	 * @return int
	 */
	public static function get_total_suppressed_count() {
		return (int) get_option( self::OPTION_TOTAL_SUPPRESSED, 0 );
	}

	/**
	 * Check if the failed login limit is currently active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		// Yield to premium's own failed login module if active.
		if ( Helpers::is_premium_add_on_active() ) {
			return false;
		}

		/**
		 * Filter to enable or disable the core failed login limit.
		 *
		 * @since 5.24.0
		 *
		 * @param bool $enabled Whether the limit is enabled. Default true.
		 */
		return (bool) apply_filters( 'simple_history/failed_login_limit/enabled', true );
	}
}
