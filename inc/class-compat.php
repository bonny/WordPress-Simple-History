<?php

namespace Simple_History;

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
	 * @global \WP_Locale $wp_locale WordPress date and time locale object .
	 *
	 * @param string        $format    PHP date format .
	 * @param int           $timestamp Optional . Unix timestamp . Defaults to current time .
	 * @param \DateTimeZone $timezone  Optional. Timezone to output result in. Defaults to timezone
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
		 * @param \DateTimeZone $timezone  Timezone.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$date = apply_filters( 'wp_date', $date, $format, $timestamp, $timezone );

		return $date;
	}

	/**
	 * Escaping for XML blocks.
	 *
	 * @since 5.5.0
	 *
	 * @param string $text Text to escape.
	 * @return string Escaped text.
	 */
	public static function esc_xml( $text ) {
		// Use esc_xml() that was added in WordPress 5.5.0, if available.
		if ( function_exists( 'esc_xml' ) ) {
			return esc_xml( $text );
		}

		// Start of function esc_xml() as it was in WordPress 6.7.
		$safe_text = wp_check_invalid_utf8( $text );

		$cdata_regex = '\<\!\[CDATA\[.*?\]\]\>';
		$regex       = <<<EOF
/
	(?=.*?{$cdata_regex})                 # lookahead that will match anything followed by a CDATA Section
	(?<non_cdata_followed_by_cdata>(.*?)) # the "anything" matched by the lookahead
	(?<cdata>({$cdata_regex}))            # the CDATA Section matched by the lookahead

|	                                      # alternative

	(?<non_cdata>(.*))                    # non-CDATA Section
/sx
EOF;

		$safe_text = (string) preg_replace_callback(
			$regex,
			static function ( $matches ) {
				if ( ! isset( $matches[0] ) ) {
					return '';
				}

				if ( isset( $matches['non_cdata'] ) ) {
					// escape HTML entities in the non-CDATA Section.
					return _wp_specialchars( $matches['non_cdata'], ENT_XML1 );
				}

				// Return the CDATA Section unchanged, escape HTML entities in the rest.
				return _wp_specialchars( $matches['non_cdata_followed_by_cdata'], ENT_XML1 ) . $matches['cdata'];
			},
			$safe_text
		);

		/**
		 * Filters a string cleaned and escaped for output in XML.
		 *
		 * Text passed to esc_xml() is stripped of invalid or special characters
		 * before output. HTML named character references are converted to their
		 * equivalent code points.
		 *
		 * @since 5.5.0
		 *
		 * @param string $safe_text The text after it has been escaped.
		 * @param string $text      The text prior to being escaped.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( 'esc_xml', $safe_text, $text );
	}


	/**
	 * Given an array of fields to include in a response, some of which may be
	 * `nested.fields`, determine whether the provided field should be included
	 * in the response body.
	 *
	 * If a parent field is passed in, the presence of any nested field within
	 * that parent will cause the method to return `true`. For example "title"
	 * will return true if any of `title`, `title.raw` or `title.rendered` is
	 * provided.
	 *
	 * @param string $field  A field to test for inclusion in the response body.
	 * @param array  $fields An array of string fields supported by the endpoint.
	 * @return bool Whether to include the field or not.
	 */
	public static function rest_is_field_included( $field, $fields ) {
		// Use rest_is_field_included() that was added in WordPress 5.3.0, if available.
		if ( function_exists( 'rest_is_field_included' ) ) {
			return rest_is_field_included( $field, $fields );
		}

		// Start of function rest_is_field_included() as it was in WordPress 6.7.
		if ( in_array( $field, $fields, true ) ) {
			return true;
		}

		foreach ( $fields as $accepted_field ) {
			/*
			 * Check to see if $field is the parent of any item in $fields.
			 * A field "parent" should be accepted if "parent.child" is accepted.
			 */
			if ( str_starts_with( $accepted_field, "$field." ) ) {
				return true;
			}

			/*
			 * Conversely, if "parent" is accepted, all "parent.child" fields
			 * should also be accepted.
			 */
			if ( str_starts_with( $field, "$accepted_field." ) ) {
				return true;
			}
		}

		return false;
	}
}
