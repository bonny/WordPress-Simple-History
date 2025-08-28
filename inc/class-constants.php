<?php

namespace Simple_History;

/**
 * Constants class for Simple History.
 *
 * @package SimpleHistory
 */

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
				return self::DAYS_PER_MONTH; // Default to month
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
}
