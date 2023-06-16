<?php

namespace Simple_History;

class Helpers {
	/**
	 * Pretty much same as wp_text_diff() but with this you can set leading and trailing context lines
	 *
	 * @since 2.0.29
	 *
	 * Original description from wp_text_diff():
	 *
	 * Displays a human readable HTML representation of the difference between two strings.
	 *
	 * The Diff is available for getting the changes between versions. The output is
	 * HTML, so the primary use is for displaying the changes. If the two strings
	 * are equivalent, then an empty string will be returned.
	 *
	 * The arguments supported and can be changed are listed below.
	 *
	 * 'title' : Default is an empty string. Titles the diff in a manner compatible
	 *      with the output.
	 * 'title_left' : Default is an empty string. Change the HTML to the left of the
	 *      title.
	 * 'title_right' : Default is an empty string. Change the HTML to the right of
	 *      the title.
	 *
	 * @see wp_parse_args() Used to change defaults to user defined settings.
	 * @uses Text_Diff
	 * @uses WP_Text_Diff_Renderer_Table
	 *
	 * @param string       $left_string "old" (left) version of string
	 * @param string       $right_string "new" (right) version of string
	 * @param string|array $args Optional. Change 'title', 'title_left', and 'title_right' defaults. And leading_context_lines and trailing_context_lines.
	 * @return string Empty string if strings are equivalent or HTML with differences.
	 */
	public static function text_diff( $left_string, $right_string, $args = null ) {
		$defaults = array(
			'title' => '',
			'title_left' => '',
			'title_right' => '',
			'leading_context_lines' => 1,
			'trailing_context_lines' => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( ! class_exists( 'WP_Text_Diff_Renderer_Table' ) ) {
			require ABSPATH . WPINC . '/wp-diff.php';
		}

		$left_string = normalize_whitespace( $left_string );
		$right_string = normalize_whitespace( $right_string );

		$left_lines = explode( "\n", $left_string );
		$right_lines = explode( "\n", $right_string );
		$text_diff = new \Text_Diff( $left_lines, $right_lines );

		$renderer = new \WP_Text_Diff_Renderer_Table( $args );
		$renderer->_leading_context_lines = $args['leading_context_lines'];
		$renderer->_trailing_context_lines = $args['trailing_context_lines'];

		$diff = $renderer->render( $text_diff );

		if ( ! $diff ) {
			return '';
		}

		$r = '';

		$r .= "<div class='SimpleHistory__diff__contents' tabindex='0'>";
		$r .= "<div class='SimpleHistory__diff__contentsInner'>";

		$r .= "<table class='diff SimpleHistory__diff'>\n";

		if ( ! empty( $args['show_split_view'] ) ) {
			$r .=
			"<col class='content diffsplit left' /><col class='content diffsplit middle' /><col class='content diffsplit right' />";
		} else {
			$r .= "<col class='content' />";
		}

		if ( $args['title'] || $args['title_left'] || $args['title_right'] ) {
			$r .= '<thead>';
		}
		if ( $args['title'] ) {
			$r .= "<tr class='diff-title'><th colspan='4'>$args[title]</th></tr>\n";
		}
		if ( $args['title_left'] || $args['title_right'] ) {
			$r .= "<tr class='diff-sub-title'>\n";
			$r .= "\t<td></td><th>$args[title_left]</th>\n";
			$r .= "\t<td></td><th>$args[title_right]</th>\n";
			$r .= "</tr>\n";
		}
		if ( $args['title'] || $args['title_left'] || $args['title_right'] ) {
			$r .= "</thead>\n";
		}

		$r .= "<tbody>\n$diff</div>\n</tbody>\n";
		$r .= '</table>';

		$r .= '</div>';
		$r .= '</div>';

		return $r;
	}

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param array  $row Currently not always passed, because loggers need to be updated to support this...
	 */
	public static function interpolate( $message, $context = array(), $row = null ) {
		if ( ! is_array( $context ) ) {
			return $message;
		}

		/**
		 * Filters the context used to create the message from the message template.
		 * Can be used to modify the variables sent to the message template.
		 *
		 * @example Example that modifies the parameters sent to the message template.
		 *
		 * This example will change the post type from "post" or "page" or similar to "my own page type".
		 *
		 *  ```php
		 *  add_filter(
		 *      'simple_history/logger/interpolate/context',
		 *      function ( $context, $message, $row ) {
		 *
		 *          if ( empty( $row ) ) {
		 *              return $context;
		 *          }
		 *
		 *          if ( $row->logger == 'SimplePostLogger' && $row->context_message_key == 'post_updated' ) {
		 *              $context['post_type'] = 'my own page type';
		 *          }
		 *
		 *          return $context;
		 *      },
		 *      10,
		 *      3
		 *  );
		 * ```
		 *
		 * @since 2.2.4
		 *
		 * @param array $context
		 * @param string $message
		 * @param array $row The row. Not supported by all loggers.
		 */
		$context = apply_filters(
			'simple_history/logger/interpolate/context',
			$context,
			$message,
			$row
		);

		// Build a replacement array with braces around the context keys
		$replace = array();
		foreach ( $context as $key => $val ) {
			// Both key and val must be strings or number (for vals)
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( is_string( $key ) || is_numeric( $key ) ) {
				// key ok
			}

			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( is_string( $val ) || is_numeric( $val ) ) {
				// val ok
			} else {
				// not a value we can replace
				continue;
			}

			$replace[ '{' . $key . '}' ] = $val;
		}

		// Interpolate replacement values into the message and return
		return strtr( $message, $replace );
	}

	/**
	 * Get the current screen object.
	 * Returns an object with all attributes empty if functions is not found or if function
	 * returns null. Makes it easier to use get_current_screen when we don't have to
	 * check for function existence and or null.
	 *
	 * @return WP_Screen|Object Current screen object or object with empty attributes when screen not defined.
	 */
	public static function get_current_screen() {
		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = get_current_screen();
			if ( $current_screen instanceof \WP_Screen ) {
				return $current_screen;
			}
		}

		// No screen found, return object with same properties but with empty values.
		return (object) array(
			'action' => null,
			'base' => null,
			'id' => null,
			'is_network' => null,
			'is_user' => null,
			'parent_base' => null,
			'parent_file' => null,
			'post_type' => null,
			'taxonomy' => null,
			'is_block_editor' => null,
		);
	}

	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range or is within reserved range.
	 *
	 * @param string $ip IP number.
	 * @return bool
	 */
	public static function is_valid_public_ip( $ip ) {
		if (
			filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_IPV4 |
				FILTER_FLAG_NO_PRIV_RANGE |
				FILTER_FLAG_NO_RES_RANGE
			) === false
		) {
			return false;
		}

		return true;
	}

	/**
	 * Works like json_encode, but adds JSON_PRETTY_PRINT.
	 *
	 * @param mixed $value array|object|string|whatever that is json_encode'able.
	 */
	public static function json_encode( $value ) {
		return version_compare( PHP_VERSION, '5.4.0' ) >= 0
			? json_encode( $value, JSON_PRETTY_PRINT )
			: json_encode( $value );
	}

	/**
	 * Returns array with headers that may contain user IP address.
	 *
	 * @since 2.0.29
	 *
	 * @return array
	 */
	public static function get_ip_number_header_names() {
		$headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
		);

		/**
		 * Filters the array with HTTP headers thay may contain user IP address.
		 *
		 * @param array $headers
		 */
		$headers = apply_filters(
			'simple_history/ip_number_header_names',
			$headers,
		);

		return $headers;
	}

	/**
	 * Returns true if $haystack ends with $needle
	 *
	 * @param string $haystack
	 * @param string $needle
	 */
	public static function ends_with( $haystack, $needle ) {
		return $needle === substr( $haystack, -strlen( $needle ) );
	}

	/**
	 * Get the Incrementor value for the cache.
	 *
	 * Based on code from https://www.tollmanz.com/invalidation-schemes/.
	 *
	 * @param $refresh bool Pass true to invalidate the cache.
	 * @return int
	 */
	public static function get_cache_incrementor( $refresh = false ) {
		$incrementor_key = 'simple_history_incrementor';
		$incrementor_value = wp_cache_get( $incrementor_key );

		if ( false === $incrementor_value || true === $refresh ) {
			$incrementor_value = time();
			wp_cache_set( $incrementor_key, $incrementor_value );
		}

		return $incrementor_value;
	}

	/**
	 * Return a name for a callable.
	 *
	 * Examples of return values:
	 * - WP_REST_Posts_Controller::get_items
	 * - WP_REST_Users_Controller::get_items"
	 * - WP_REST_Server::get_index
	 * - Redirection_Api_Redirect::route_bulk
	 * - wpcf7_rest_create_feedback
	 * - closure
	 *
	 * Function based on code found on stack overflow:
	 * https://stackoverflow.com/questions/34324576/print-name-or-definition-of-callable-in-php
	 *
	 * @param callable $callable The callable thing to check.
	 * @return string Name of callable.
	 */
	public static function get_callable_name( $callable ) {
		if ( is_string( $callable ) ) {
			return trim( $callable );
		} elseif ( is_array( $callable ) ) {
			if ( is_object( $callable[0] ) ) {
				return sprintf( '%s::%s', get_class( $callable[0] ), trim( $callable[1] ) );
			} else {
				return sprintf( '%s::%s', trim( $callable[0] ), trim( $callable[1] ) );
			}
		} elseif ( $callable instanceof \Closure ) {
			return 'closure';
		} else {
			return 'unknown';
		}
	}
}
