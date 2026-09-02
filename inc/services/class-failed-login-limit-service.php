<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Log_Initiators;
use Simple_History\Loggers\Logger;
use Simple_History\Loggers\Simple_History_Logger;
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
	 * Deleted whenever the counter resets.
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

		// The summary written by end_burst() passes through this filter too.
		// The counter is already zero by then; skipping it here also means the
		// summary can never re-enter this path, whatever the option layer returns.
		if ( ( $context['_message_key'] ?? '' ) === Simple_History_Logger::MESSAGE_KEY_FAILED_LOGINS_NOT_RECORDED ) {
			return $do_log;
		}

		$count = (int) get_option( self::OPTION_COUNTER, 0 );

		// Only write to DB if counter is not already 0.
		if ( $count === 0 ) {
			return $do_log;
		}

		$threshold = self::get_threshold();

		// Reset before logging the summary: the summary is itself an event that
		// passes through this filter, so the counter must already be zero by then.
		update_option( self::OPTION_COUNTER, 0, false );

		self::end_burst( max( 0, $count - $threshold ), $threshold );

		return $do_log;
	}

	/**
	 * Remember when the current burst started and what its latest skipped attempt looked like.
	 *
	 * Called once per suppressed attempt. Public so premium's own limiter can
	 * feed the same summary from its counters. Does nothing unless experimental
	 * features are enabled, so sites that never see the summary pay nothing.
	 *
	 * @since 5.32.0
	 *
	 * @param array $context Context of the failed login event that was not logged.
	 */
	public static function track_suppressed_attempt( $context ) {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		$now   = gmdate( 'Y-m-d H:i:s' );
		$burst = get_option( self::OPTION_BURST, [] );

		if ( ! is_array( $burst ) || empty( $burst['first_date'] ) ) {
			$burst = [ 'first_date' => $now ];
		}

		$burst['last_date']       = $now;
		$burst['last_username']   = (string) ( $context['login'] ?? $context['failed_username'] ?? '' );
		$burst['request_context'] = self::get_request_context( $context );

		update_option( self::OPTION_BURST, $burst, false );
	}

	/**
	 * Close the burst that just ended: discard its details and, if attempts were
	 * skipped, log one event summarising them.
	 *
	 * Public so premium's own limiter can call it when its counters reset. The
	 * burst details are always discarded, even when nothing was skipped, so a
	 * later burst never inherits a stale start date.
	 *
	 * Experimental: the summary is only written when experimental features are
	 * enabled. It is dated at the last skipped attempt so it sits in the timeline
	 * where the burst ended, and carries the attacker's IP, forwarded headers and
	 * no user identity, rather than the request details of whoever happened to
	 * log the event that ended the burst.
	 *
	 * @since 5.32.0
	 *
	 * @param int $suppressed_count Number of attempts that were not logged.
	 * @param int $threshold        Number of attempts that were logged before skipping started.
	 */
	public static function end_burst( $suppressed_count, $threshold ) {
		$burst = get_option( self::OPTION_BURST );

		if ( $burst !== false ) {
			delete_option( self::OPTION_BURST );
		}

		if ( $suppressed_count <= 0 || ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleHistoryLogger' );

		if ( ! $logger instanceof Simple_History_Logger ) {
			return;
		}

		$burst = is_array( $burst ) ? $burst : [];

		$request_context = isset( $burst['request_context'] ) && is_array( $burst['request_context'] )
			? $burst['request_context']
			: [];

		$context = array_merge(
			$request_context,
			[
				'_initiator'                         => Log_Initiators::WORDPRESS,
				// Simple History skipped these attempts. No user did anything, so
				// stop the logger from attaching whoever ended the burst.
				'_user_id'                           => 0,
				'failed_login_total_count'             => (int) $threshold + (int) $suppressed_count,
				'failed_login_recorded_count'          => (int) $threshold,
				'failed_login_not_recorded_count'      => (int) $suppressed_count,
				'failed_login_first_not_recorded_date' => $burst['first_date'] ?? '',
				'failed_login_last_date'               => $burst['last_date'] ?? '',
				'failed_login_username'                => $burst['last_username'] ?? '',
				'failed_login_ip'                      => self::get_client_ip_from_request_context( $request_context ),
			]
		);

		if ( ! empty( $burst['last_date'] ) ) {
			$context['_date'] = $burst['last_date'];
		}

		$logger->warning_message( Simple_History_Logger::MESSAGE_KEY_FAILED_LOGINS_NOT_RECORDED, $context );
	}

	/**
	 * Capture the request details of a suppressed attempt: IP, forwarded IPs, referer.
	 *
	 * The logger only appends these to the context after the do_log filter has
	 * run, so a suppressed event never gets that far. Read them from the request
	 * here instead, unless the caller already put them in the context.
	 *
	 * @param array $context Context of the failed login event.
	 * @return array<string,string>
	 */
	private static function get_request_context( $context ) {
		if ( isset( $context['_server_remote_addr'] ) ) {
			$request_context = [];

			foreach ( $context as $key => $value ) {
				if ( is_string( $key ) && strpos( $key, '_server_' ) === 0 && is_scalar( $value ) ) {
					$request_context[ $key ] = (string) $value;
				}
			}
		} else {
			$request_context = Helpers::get_remote_addr_context();
		}

		if ( ! isset( $request_context['_server_http_referer'] ) && isset( $_SERVER['HTTP_REFERER'] ) ) {
			$request_context['_server_http_referer'] = Helpers::mask_sensitive_query_string(
				esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
			);
		}

		return $request_context;
	}

	/**
	 * Pick the IP to show as "the attacker": the first forwarded public IP when the
	 * site sits behind a proxy, otherwise the remote address.
	 *
	 * @param array<string,string> $request_context Keys as returned by get_request_context().
	 * @return string
	 */
	private static function get_client_ip_from_request_context( $request_context ) {
		foreach ( $request_context as $key => $value ) {
			if ( $key !== '_server_remote_addr' && $key !== '_server_http_referer' && strpos( $key, '_server_' ) === 0 ) {
				return $value;
			}
		}

		return $request_context['_server_remote_addr'] ?? '';
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
