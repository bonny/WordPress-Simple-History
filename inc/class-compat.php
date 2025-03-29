<?php

namespace Simple_History;

use Simple_History\Simple_History;
use Simple_History\Services\Setup_Settings_Page;

/**
 * Compatibility functions,
 * so we can use new functions in older versions of WordPress.
 *
 * Example:
 * instead of calling `wp_date()`
 * call `Compat::wp_date()`
 *
 * Add methods for these functions:
 *
 * - wp_date
 * - rest_is_field_included
 * - esc_xml
 */
class Compat {
	/**
	 * Retrieves the date, in localized format .
	 *
	 * This is a newer function, intended to replace `date_i18n()` without legacy quirks in it .
	 *
	 * Note that, unlike `date_i18n()`, this function accepts a true Unix timestamp, not summed
	 * with timezone offset .
	 *
	 * @since 5.3.0
	 *
	 * @global WP_Locale $wp_locale WordPress date and time locale object .
	 *
	 * @param string       $format    PHP date format .
	 * @param int          $timestamp Optional . Unix timestamp . Defaults to current time .
	 * @param DateTimeZone $timezone  Optional. Timezone to output result in. Defaults to timezone
	 *                                from site settings.
	 * @return string|false The date, translated if locale specifies it. False on invalid timestamp input.
	 */
	public static function wp_date( $format, $timestamp = null, $timezone = null ) {
		// Use wp_date() that was added in WordPress 5.3, if available.
		if ( function_exists( 'wp_date' ) ) {
			return wp_date( $format, $timestamp, $timezone );
		}

		// Start of function wp_date() as it was in WordPress 6.7.
		global $wp_locale;

		if ( null === $timestamp ) {
			$timestamp = time();
		} elseif ( ! is_numeric( $timestamp ) ) {
			return false;
		}

		if ( ! $timezone ) {
			$timezone = wp_timezone();
		}

		$datetime = date_create( '@' . $timestamp );
		$datetime->setTimezone( $timezone );

		if ( empty( $wp_locale->month ) || empty( $wp_locale->weekday ) ) {
			$date = $datetime->format( $format );
		} else {
			// We need to unpack shorthand `r` format because it has parts that might be localized.
			$format = preg_replace( '/(?<!\\\\)r/', DATE_RFC2822, $format );

			$new_format    = '';
			$format_length = strlen( $format );
			$month         = $wp_locale->get_month( $datetime->format( 'm' ) );
			$weekday       = $wp_locale->get_weekday( $datetime->format( 'w' ) );

			for ( $i = 0; $i < $format_length; $i++ ) {
				switch ( $format[ $i ] ) {
					case 'D':
						$new_format .= addcslashes( $wp_locale->get_weekday_abbrev( $weekday ), '\\A..Za..z' );
						break;
					case 'F':
						$new_format .= addcslashes( $month, '\\A..Za..z' );
						break;
					case 'l':
						$new_format .= addcslashes( $weekday, '\\A..Za..z' );
						break;
					case 'M':
						$new_format .= addcslashes( $wp_locale->get_month_abbrev( $month ), '\\A..Za..z' );
						break;
					case 'a':
						$new_format .= addcslashes( $wp_locale->get_meridiem( $datetime->format( 'a' ) ), '\\A..Za..z' );
						break;
					case 'A':
						$new_format .= addcslashes( $wp_locale->get_meridiem( $datetime->format( 'A' ) ), '\\A..Za..z' );
						break;
					case '\\':
						$new_format .= $format[ $i ];

						// If character follows a slash, we add it without translating.
						if ( $i < $format_length ) {
							$new_format .= $format[ ++$i ];
						}
						break;
					default:
						$new_format .= $format[ $i ];
						break;
				}
			}

			$date = $datetime->format( $new_format );
			$date = wp_maybe_decline_date( $date, $format );
		}

		/**
		 * Filters the date formatted based on the locale.
		 *
		 * @since 5.3.0
		 *
		 * @param string       $date      Formatted date string.
		 * @param string       $format    Format to display the date.
		 * @param int          $timestamp Unix timestamp.
		 * @param DateTimeZone $timezone  Timezone.
		 */
		$date = apply_filters( 'wp_date', $date, $format, $timestamp, $timezone );

		return $date;
	}
}
