<?php
/**
 * Date_Helper class for Simple History.
 *
 * Provides centralized date/time utilities and constants.
 */

namespace Simple_History;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Date_Helper
 *
 * Centralized date/time helper methods and time period constants.
 * All date/time calculations respect WordPress timezone settings.
 */
class Date_Helper {

	/**
	 * Time period constants in days.
	 */
	const DAYS_PER_WEEK      = 7;
	const DAYS_PER_MONTH     = 30;
	const DAYS_PER_FORTNIGHT = 14;
	const DAYS_PER_QUARTER   = 90;

	/**
	 * Get the number of days for a specific time period.
	 *
	 * @param string $period The time period (week, month, fortnight, quarter).
	 * @return int Number of days for the period.
	 */
	public static function get_days_for_period( $period ) {
		switch ( $period ) {
			case 'week':
				return self::DAYS_PER_WEEK;
			case 'month':
				return self::DAYS_PER_MONTH;
			case 'fortnight':
				return self::DAYS_PER_FORTNIGHT;
			case 'quarter':
				return self::DAYS_PER_QUARTER;
			default:
				// Default to month.
				return self::DAYS_PER_MONTH;
		}
	}

	/**
	 * Get all available time periods.
	 *
	 * @return array Array of available time periods.
	 */
	public static function get_available_periods() {
		return array(
			'week'      => self::DAYS_PER_WEEK,
			'fortnight' => self::DAYS_PER_FORTNIGHT,
			'month'     => self::DAYS_PER_MONTH,
			'quarter'   => self::DAYS_PER_QUARTER,
		);
	}

	/**
	 * Get current timestamp.
	 *
	 * @return int Current Unix timestamp.
	 */
	public static function get_current_timestamp() {
		return time();
	}

	/**
	 * Get timestamp for start of today (00:00:00) in WordPress timezone.
	 *
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * @return int Unix timestamp for start of today.
	 */
	public static function get_today_start_timestamp() {
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		return $today->getTimestamp();
	}

	/**
	 * Get timestamp for end of today (23:59:59) in WordPress timezone.
	 *
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * @return int Unix timestamp for end of today.
	 */
	public static function get_today_end_timestamp() {
		$today_end = new \DateTimeImmutable( 'today 23:59:59', wp_timezone() );
		return $today_end->getTimestamp();
	}

	/**
	 * Get start timestamp for "last N days" period including today.
	 *
	 * Returns the start of the day (00:00:00) that begins a period of N days
	 * ending today. The count includes today as one of the N days.
	 *
	 * Examples (assuming today is October 8, 2025):
	 * - get_last_n_days_start_timestamp(1) returns Oct 8 00:00:00 (today)
	 * - get_last_n_days_start_timestamp(7) returns Oct 2 00:00:00 (last 7 days including today)
	 * - get_last_n_days_start_timestamp(30) returns Sept 9 00:00:00 (last 30 days including today)
	 *
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * @param int $days Number of days to include in the period (including today).
	 * @return int Unix timestamp for start of the period (00:00:00).
	 */
	public static function get_last_n_days_start_timestamp( $days ) {
		// Subtract (days - 1) because "last N days" includes today.
		// E.g., "last 30 days" on Oct 8 = Sept 9 to Oct 8 (30 days total).
		$days_ago   = $days - 1;
		$date       = new \DateTimeImmutable( "-{$days_ago} days", wp_timezone() );
		$date_start = new \DateTimeImmutable( $date->format( 'Y-m-d' ) . ' 00:00:00', wp_timezone() );
		return $date_start->getTimestamp();
	}

	/**
	 * Get default date range (last 30 days to end of today).
	 *
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * @return array Array with 'from' and 'to' Unix timestamps.
	 */
	public static function get_default_date_range() {
		return array(
			'from' => self::get_last_n_days_start_timestamp( self::DAYS_PER_MONTH ),
			'to'   => self::get_today_end_timestamp(),
		);
	}

	/**
	 * Get date range for last N days.
	 *
	 * Range goes from N days ago (00:00:00) to end of today (23:59:59).
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * @param int $days Number of days.
	 * @return array Array with 'from' and 'to' Unix timestamps.
	 */
	public static function get_last_n_days_range( $days ) {
		return array(
			'from' => self::get_last_n_days_start_timestamp( $days ),
			'to'   => self::get_today_end_timestamp(),
		);
	}

	/**
	 * Get date range for a specific period (week, month, fortnight, quarter).
	 *
	 * Range goes from period start (00:00:00) to end of today (23:59:59).
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * @param string $period Period name: 'week', 'month', 'fortnight', 'quarter'.
	 * @return array Array with 'from' and 'to' Unix timestamps.
	 */
	public static function get_period_range( $period ) {
		$days = self::get_days_for_period( $period );
		return self::get_last_n_days_range( $days );
	}

	/**
	 * Get date range for last N complete days (excludes today).
	 *
	 * Returns from N days ago (00:00:00) to yesterday (23:59:59).
	 * Useful for previews and reports that should exclude partial current day.
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * Examples (assuming today is October 8, 2025):
	 * - get_last_n_complete_days_range(7) returns Oct 1 00:00:00 to Oct 7 23:59:59 (7 complete days)
	 * - get_last_n_complete_days_range(1) returns Oct 7 00:00:00 to Oct 7 23:59:59 (yesterday only)
	 *
	 * @param int $days Number of complete days.
	 * @return array Array with 'from' and 'to' Unix timestamps.
	 */
	public static function get_last_n_complete_days_range( $days ) {
		$yesterday     = new \DateTimeImmutable( 'yesterday', wp_timezone() );
		$yesterday_end = new \DateTimeImmutable( $yesterday->format( 'Y-m-d' ) . ' 23:59:59', wp_timezone() );

		// Go back N days from yesterday.
		$days_ago      = $days - 1;
		$start_date    = $yesterday->modify( "-{$days_ago} days" );
		$start_date_00 = new \DateTimeImmutable( $start_date->format( 'Y-m-d' ) . ' 00:00:00', wp_timezone() );

		return array(
			'from' => $start_date_00->getTimestamp(),
			'to'   => $yesterday_end->getTimestamp(),
		);
	}

	/**
	 * Get date range for last complete week (Monday-Sunday).
	 *
	 * Returns the most recent complete week (Monday 00:00:00 to Sunday 23:59:59).
	 * Useful for weekly reports sent on Monday that should show previous week.
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * Examples:
	 * - If today is Monday Oct 8, returns Oct 1 00:00:00 to Oct 7 23:59:59
	 * - If today is Wednesday Oct 10, returns Oct 1 00:00:00 to Oct 7 23:59:59
	 *
	 * @return array Array with 'from' and 'to' Unix timestamps.
	 */
	public static function get_last_complete_week_range() {
		$now = new \DateTimeImmutable( 'now', wp_timezone() );

		// Get last Sunday at 23:59:59.
		$last_sunday = new \DateTimeImmutable( 'last sunday', wp_timezone() );
		// If today is Sunday, we need to go back one more week.
		if ( $now->format( 'w' ) === '0' ) {
			$last_sunday = $last_sunday->modify( '-7 days' );
		}
		$last_sunday_end = new \DateTimeImmutable( $last_sunday->format( 'Y-m-d' ) . ' 23:59:59', wp_timezone() );

		// Get Monday of that week at 00:00:00.
		$last_monday       = $last_sunday->modify( '-6 days' );
		$last_monday_start = new \DateTimeImmutable( $last_monday->format( 'Y-m-d' ) . ' 00:00:00', wp_timezone() );

		return array(
			'from' => $last_monday_start->getTimestamp(),
			'to'   => $last_sunday_end->getTimestamp(),
		);
	}

	/**
	 * Get WordPress timezone object.
	 *
	 * Returns DateTimeZone object for the site's timezone as configured
	 * in Settings > General.
	 *
	 * @return \DateTimeZone WordPress site timezone.
	 */
	public static function get_wp_timezone() {
		return wp_timezone();
	}

	/**
	 * Get WordPress timezone string.
	 *
	 * Returns timezone string like 'Europe/Stockholm' or '+02:00'.
	 * Uses Settings > General timezone configuration.
	 *
	 * @return string Timezone string.
	 */
	public static function get_wp_timezone_string() {
		return wp_timezone_string();
	}
}
