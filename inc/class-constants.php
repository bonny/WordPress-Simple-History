<?php
/**
 * Constants class for Simple History.
 */

namespace Simple_History;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Constants
 *
 * Centralized constants for Simple History plugin.
 */
class Constants {

	/**
	 * Time period constants in days.
	 */
	const DAYS_PER_WEEK = 7;
	const DAYS_PER_MONTH = 30;
	const DAYS_PER_FORTNIGHT = 14;
	const DAYS_PER_QUARTER = 90;

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
	 * Get timestamp for N days ago (00:00:00) in WordPress timezone.
	 *
	 * Uses WordPress timezone setting from Settings > General.
	 *
	 * @param int $days Number of days to go back.
	 * @return int Unix timestamp for start of day N days ago.
	 */
	public static function get_n_days_ago_timestamp( $days ) {
		$date = new \DateTimeImmutable( "-{$days} days", wp_timezone() );
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
			'from' => self::get_n_days_ago_timestamp( self::DAYS_PER_MONTH ),
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
			'from' => self::get_n_days_ago_timestamp( $days ),
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
