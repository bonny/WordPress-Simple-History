<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Log_Initiators;
use Simple_History\Loggers\Logger;
use Simple_History\Loggers\User_Logger;
use Simple_History\Simple_History;

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
 * When a burst that crossed the threshold ends, one summary event records how many
 * attempts were skipped, so the log itself says what it left out (experimental).
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

	/**
	 * Option name for details about the burst currently being suppressed:
	 * when it started, the last skipped attempt, and who that attempt targeted.
	 * Deleted when the burst ends.
	 *
	 * @var string
	 */
	private const OPTION_BURST = 'sh_core_failed_login_burst';

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

			self::track_suppressed_attempt( $context );

			return false;
		}

		return $do_log;
	}

	/**
	 * Reset the counter when a non-failed-login event is logged.
	 *
	 * If the burst that just ended went past the threshold, a summary event
	 * is written so the log records how many attempts were skipped.
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
		if ( $count === 0 ) {
			return $do_log;
		}

		$threshold        = self::get_threshold();
		$suppressed_count = max( 0, $count - $threshold );

		// Reset before logging the summary: the summary is itself an event that
		// passes through this filter, so the counter must already be zero by then.
		update_option( self::OPTION_COUNTER, 0, false );

		if ( $suppressed_count > 0 ) {
			$burst = get_option( self::OPTION_BURST, [] );
			delete_option( self::OPTION_BURST );

			self::maybe_log_suppression_summary( $suppressed_count, $threshold, is_array( $burst ) ? $burst : [] );
		}

		return $do_log;
	}

	/**
	 * Remember when the current burst started and what its latest skipped attempt looked like.
	 *
	 * Called once per suppressed attempt. Public so premium's own limiter can
	 * feed the same summary from its counters.
	 *
	 * @since 5.32.0
	 *
	 * @param array $context Context of the failed login event that was not logged.
	 */
	public static function track_suppressed_attempt( $context ) {
		$now   = gmdate( 'Y-m-d H:i:s' );
		$burst = get_option( self::OPTION_BURST, [] );

		if ( ! is_array( $burst ) || empty( $burst['first_date'] ) ) {
			$burst = [ 'first_date' => $now ];
		}

		$burst['last_date']     = $now;
		$burst['last_username'] = (string) ( $context['login'] ?? $context['failed_username'] ?? '' );
		$burst['last_ip']       = (string) ( $context['_server_remote_addr'] ?? self::get_request_ip() );

		update_option( self::OPTION_BURST, $burst, false );
	}

	/**
	 * Log one event summarising a burst of failed logins that went past the threshold.
	 *
	 * Experimental: only writes when experimental features are enabled. The event
	 * is dated at the last skipped attempt so it sits in the timeline where the
	 * burst ended, and carries the attacker's IP rather than the IP of whoever
	 * happened to log the event that ended the burst.
	 *
	 * Public so premium's own limiter can call it when its counters reset.
	 *
	 * @since 5.32.0
	 *
	 * @param int   $suppressed_count Number of attempts that were not logged.
	 * @param int   $threshold        Number of attempts that were logged before skipping started.
	 * @param array $burst            Burst details as stored by track_suppressed_attempt(). May be empty.
	 */
	public static function maybe_log_suppression_summary( $suppressed_count, $threshold, $burst = [] ) {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		$user_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		if ( ! $user_logger instanceof User_Logger ) {
			return;
		}

		$context = [
			'_initiator'                         => Log_Initiators::WORDPRESS,
			'failed_login_suppressed_count'      => (int) $suppressed_count,
			'failed_login_threshold'             => (int) $threshold,
			'failed_login_suppressed_first_date' => $burst['first_date'] ?? '',
			'failed_login_suppressed_last_date'  => $burst['last_date'] ?? '',
			'failed_login_last_username'         => $burst['last_username'] ?? '',
			'failed_login_last_ip'               => $burst['last_ip'] ?? '',
		];

		if ( ! empty( $burst['last_date'] ) ) {
			$context['_date'] = $burst['last_date'];
		}

		if ( ! empty( $burst['last_ip'] ) ) {
			$context['_server_remote_addr'] = $burst['last_ip'];
		}

		$user_logger->warning_message( User_Logger::MESSAGE_KEY_FAILED_LOGINS_SUPPRESSED, $context );
	}

	/**
	 * Get the anonymized IP of the current request.
	 *
	 * The logger only appends the IP to the context after the do_log filter has
	 * run, so a suppressed event never gets that far and we read it here instead.
	 *
	 * @return string
	 */
	private static function get_request_ip() {
		$remote_addr = '';

		// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- REMOTE_ADDR is validated with filter_var() below
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
			$remote_addr  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			$validated_ip = filter_var( $remote_addr, FILTER_VALIDATE_IP );
			$remote_addr  = $validated_ip !== false ? $validated_ip : '';
		}
		// phpcs:enable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

		return Helpers::privacy_anonymize_ip( $remote_addr );
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

		return in_array( $message_key, User_Logger::get_failed_login_message_keys(), true );
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
