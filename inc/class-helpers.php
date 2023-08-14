<?php

namespace Simple_History;

use Simple_History\Simple_History;

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

		if ( $diff === '' ) {
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
	 * @param object  $row Currently not always passed, because loggers need to be updated to support this...
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
		 * @param object $row The row. Not supported by all loggers.
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
			// key ok

			if ( ! is_string( $val ) && ! is_numeric( $val ) ) {
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
		return filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) !== false;
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
		 * Filters the array with HTTP headers that may contain user IP address.
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
	 * @param bool $refresh Pass true to invalidate the cache.
	 * @return string
	 */
	public static function get_cache_incrementor( $refresh = false ) {
		$incrementor_key = 'simple_history_incrementor';
		$incrementor_value = wp_cache_get( $incrementor_key );

		if ( false === $incrementor_value || $refresh ) {
			$incrementor_value = uniqid();
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

	/**
	 * Get number of rows in the database tables.
	 *
	 * @return array Array with table name, size in mb and number of rows, if tables found.
	 */
	public static function get_db_table_stats() {
		global $wpdb;
		$simple_history = Simple_History::get_instance();

		// Get table sizes in mb.
		$table_size_result = $wpdb->get_results(
			$wpdb->prepare(
				'
					SELECT table_name AS "table_name",
					round(((data_length + index_length) / 1024 / 1024), 2) "size_in_mb"
					FROM information_schema.TABLES
					WHERE table_schema = "%1$s"
					AND table_name IN ("%2$s", "%3$s");
					',
				DB_NAME, // 1
				$simple_history->get_events_table_name(), // 2
				$simple_history->get_contexts_table_name() // 3
			)
		);

		// If empty array returned then tables does not exist.
		if ( sizeof( $table_size_result ) === 0 ) {
			return array();
		}

		// Get num of rows for each table
		$total_num_rows_table = (int) $wpdb->get_var( "select count(*) FROM {$simple_history->get_events_table_name()}" ); // phpcs:ignore
		$total_num_rows_table_contexts = (int) $wpdb->get_var( "select count(*) FROM {$simple_history->get_contexts_table_name()}" ); // phpcs:ignore

		$table_size_result[0]->num_rows = $total_num_rows_table;
		$table_size_result[1]->num_rows = $total_num_rows_table_contexts;

		return $table_size_result;
	}

	/**
	 * Disable logging of a taxonomy, if a condition is met.
	 *
	 * An easier method that using filters manually each time.
	 *
	 * @param string $taxonomy_slug Slug of taxonomy to disable logging for.
	 * @param bool $disable Pass true to disable logging of $taxonomy.
	 */
	public static function disable_taxonomy_log( $taxonomy_slug, $disable = false ) {
		// Bail if taxonomy should not be disabled.
		if ( ! $disable ) {
			return;
		}

		add_filter(
			'simple_history/categories_logger/skip_taxonomies',
			function( $taxononomies_to_skip ) use ( $taxonomy_slug ) {
				$taxononomies_to_skip[] = $taxonomy_slug;
				return $taxononomies_to_skip;
			},
		);
	}

	/**
	 * Retrieve the avatar for a user who provided a user ID or email address.
	 * A modified version of the function that comes with WordPress, but we
	 * want to allow/show gravatars even if they are disabled in discussion settings
	 *
	 * @since 2.0
	 * @since 3.3 Respects gravatar setting in discussion settings.
	 *
	 * @param string       $email email address
	 * @param string       $size Size of the avatar image
	 * @param string       $default URL to a default image to use if no avatar is available
	 * @param string|false $alt Alternative text to use in image tag. Defaults to blank
	 * @param array        $args Avatar arguments
	 * @return string The img element for the user's avatar
	 */
	public static function get_avatar( $email, $size = '96', $default = '', $alt = false, $args = array() ) {
		$args = array(
			'force_display' => false,
		);

		/**
		 * Filter to control if avatars should be displayed, even if the show_avatars option
		 * is set to false in WordPress discussion settings.
		 *
		 * @since 3.3.0
		 *
		 * @example Force display of Gravatars
		 *
		 * ```php
		 *  add_filter(
		 *      'simple_history/show_avatars',
		 *      function ( $force ) {
		 *          $force = true;
		 *          return $force;
		 *      }
		 *  );
		 * ```
		 *
		 * @param bool $force_display Force display. Default false.
		 */
		$args['force_display'] = apply_filters( 'simple_history/show_avatars', $args['force_display'] );

		return get_avatar( $email, $size, $default, $alt, $args );
	}

	/**
	 * Function that converts camelCase to snake_case.
	 * Used to map old functions to new ones.
	 *
	 * @since 4.3.0
	 * @param string $input For example "getLogRowHtmlOutput".
	 * @return string Modified to for example "get_log_row_html_output".
	 */
	public static function camel_case_to_snake_case( $input ) {
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $input ) );
	}

	/**
	 * Anonymize IP-address using the WordPress function wp_privacy_anonymize_ip(),
	 * with addition that it replaces the last 0 with a "x" so
	 * users hopefully understand that it is a modified IP-address
	 * that is anonymized.
	 *
	 * @param string $ip_address IP-address to anonymize.
	 * @return string
	 */
	public static function privacy_anonymize_ip( $ip_address ) {
		/**
		 * Filter to control if ip addresses should be anonymized or not.
		 * Defaults to true, meaning that any IP address is anonymized.
		 *
		 * @example Disable IP anonymization.
		 *
		 * ```php
		 * add_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );
		 * ```
		 *
		 * @since 2.22
		 *
		 * @param bool $do_anonymize true to anonymize ip address, false to keep original ip address.
		 */
		$anonymize_ip_address = apply_filters(
			'simple_history/privacy/anonymize_ip_address',
			true
		);

		if ( ! $anonymize_ip_address ) {
			return $ip_address;
		}

		$ip_address = wp_privacy_anonymize_ip( $ip_address );

		$add_char = apply_filters(
			'simple_history/privacy/add_char_to_anonymized_ip_address',
			true
		);

		$is_ipv4 = ( 3 === substr_count( $ip_address, '.' ) );
		if ( $add_char && $is_ipv4 ) {
			$ip_address = preg_replace( '/\.0$/', '.x', $ip_address );
		}

		return $ip_address;
	}

	/**
	 * Static function that receives a possible anonymized IP-address
	 * and returns a valid one, e.g. the last '.x' replaced with '.0'.
	 *
	 * Used when fetching IP-address info from ipinfo.io, or API call
	 * will fail due to malformed IP address.
	 */
	public static function get_valid_ip_address_from_anonymized( $ip_address ) {
		$ip_address = preg_replace( '/\.x$/', '.0', $ip_address );
		return $ip_address;
	}

	/**
	 * Check if debug logging is enabled.
	 * Used by loggers to check if they should log debug messages or not.
	 *
	 * @return bool True if debug logging is enabled.
	 */
	public static function log_debug_is_enabled() {
		return defined( 'SIMPLE_HISTORY_LOG_DEBUG' ) && \SIMPLE_HISTORY_LOG_DEBUG;
	}

	/**
	 * Check if Simple History dev mode is enabled.
	 * Used by the developer of the plugin to test things.
	 *
	 * @return bool True if dev mode is enabled.
	 */
	public static function dev_mode_is_enabled() {
		return defined( 'SIMPLE_HISTORY_DEV' ) && \SIMPLE_HISTORY_DEV;
	}

	/**
	 * Wrapper around WordPress function is_plugin_active()
	 * that loads the required files if function does not exist.
	 *
	 * @param string $plugin_file_path Path to plugin file, relative to plugins dir.
	 * @return bool True if plugin is active.
	 */
	public static function is_plugin_active( $plugin_file_path ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_file_path );
	}

	/**
	 * Returns additional headers with ip numbers found in context.
	 * Additional = headers that are not the main ip number header.
	 *
	 * @since 2.0.29
	 * @param object $row Row with info.
	 * @return array Headers
	 */
	public static function get_event_ip_number_headers( $row ) {
		$ip_header_names_keys = self::get_ip_number_header_names();
		$arr_found_additional_ip_headers = array();
		$context = $row->context;

		foreach ( $ip_header_names_keys as $one_ip_header_key ) {
			$one_ip_header_key_lower = strtolower( $one_ip_header_key );

			foreach ( $context as $context_key => $context_val ) {
				// Header value is stored in key with lowercased
				// header name and with a number appended to it.
				// Examples:
				// _server_http_x_forwarded_for_0, _server_http_x_forwarded_for_1, ...
				$match = preg_match(
					"/^_server_{$one_ip_header_key_lower}_[\d+]/",
					$context_key,
					$matches
				);

				if ( $match ) {
					$arr_found_additional_ip_headers[ $context_key ] = $context_val;
				}
			}
		} // End foreach().

		return $arr_found_additional_ip_headers;
	}

	/**
	 * Sanitize checkbox inputs with value "0" or "1",
	 * so unchecked boxes get value 0 instead of null.
	 * To be used as sanitization callback for register_setting().
	 *
	 * @param string $field value of the field.
	 * @return string "1" if enabled, "0" if disabled.
	 */
	public static function sanitize_checkbox_input( $field ) {
		return ( $field === '1' ) ? '1' : '0';
	}

	/**
	 * Get shortname for a class,
	 * i.e. the unqualified class name.
	 * Example get_class_short_name( Simple_History\Services\Loggers_Loader )
	 * returns 'Loggers_Loader'.
	 *
	 * Solution from:
	 * https://stackoverflow.com/a/27457689
	 *
	 * @param object $class Class to get short name for.
	 * @return string
	 */
	public static function get_class_short_name( $class ) {
		return substr( strrchr( get_class( $class ), '\\' ), 1 );
	}

	/**
	 * Check if the db tables required by Simple History exists.
	 *
	 * @return array Array with info about the tables and their existence.
	 */
	public static function required_tables_exist() {
		global $wpdb;

		$simple_history_instance = Simple_History::get_instance();

		$tables = array(
			[
				'table_name' => $simple_history_instance->get_events_table_name(),
				'table_exists' => null,
			],
			[
				'table_name' => $simple_history_instance->get_contexts_table_name(),
				'table_exists' => null,
			],
		);

		foreach ( $tables as $key => $table ) {
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table['table_name'] ) );
			$tables[ $key ]['table_exists']  = $table_exists;
		}

		return $tables;
	}
}
