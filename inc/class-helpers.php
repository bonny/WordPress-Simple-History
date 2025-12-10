<?php

namespace Simple_History;

use Simple_History\Simple_History;
use Simple_History\Services\Setup_Settings_Page;
use Simple_History\Date_Helper;

/**
 * Helper functions.
 */
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
	 * @param string       $left_string "old" (left) version of string.
	 * @param string       $right_string "new" (right) version of string.
	 * @param string|array $args Optional. Change 'title', 'title_left', and 'title_right' defaults. And leading_context_lines and trailing_context_lines.
	 * @return string Empty string if strings are equivalent or HTML with differences.
	 */
	public static function text_diff( $left_string, $right_string, $args = null ) {
		$defaults = array(
			'title'                  => '',
			'title_left'             => '',
			'title_right'            => '',
			'leading_context_lines'  => 1,
			'trailing_context_lines' => 1,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( ! class_exists( 'WP_Text_Diff_Renderer_Table' ) ) {
			require ABSPATH . WPINC . '/wp-diff.php';
		}

		$left_string  = normalize_whitespace( $left_string );
		$right_string = normalize_whitespace( $right_string );

		$left_lines  = explode( "\n", $left_string );
		$right_lines = explode( "\n", $right_string );
		$text_diff   = new \Text_Diff( $left_lines, $right_lines );

		$renderer                          = new \WP_Text_Diff_Renderer_Table( $args );
		$renderer->_leading_context_lines  = $args['leading_context_lines'];
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

		$r .= "<tbody>\n$diff\n</tbody>\n";
		$r .= '</table>';

		$r .= '</div>';
		$r .= '</div>';

		return $r;
	}

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @param string $message Message with placeholders.
	 * @param array  $context Context values to replace placeholders with.
	 * @param object $row Currently not always passed, because loggers need to be updated to support this...
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

		// Build a replacement array with braces around the context keys.
		$replace = array();
		foreach ( $context as $key => $val ) {
			// key ok.

			if ( ! is_string( $val ) && ! is_numeric( $val ) ) {
				// not a value we can replace.
				continue;
			}

			$replace[ '{' . $key . '}' ] = $val;
		}

		// Interpolate replacement values into the message and return.
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
			'action'          => null,
			'base'            => null,
			'id'              => null,
			'is_network'      => null,
			'is_user'         => null,
			'parent_base'     => null,
			'parent_file'     => null,
			'post_type'       => null,
			'taxonomy'        => null,
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Before switching to wp_json_encode we need to verify that it's safe to use.
		return json_encode( $value, JSON_PRETTY_PRINT );
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
	 * @param string $haystack String to check.
	 * @param string $needle String to check if $haystack ends with.
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
	 * @return string Incrementor value, example: `68c1c8545881b`.
	 */
	public static function get_cache_incrementor( $refresh = false ) {
		$incrementor_key   = 'simple_history_incrementor';
		$incrementor_value = wp_cache_get( $incrementor_key );

		// Generate a new incrementor if it doesn't exist or if we want to refresh it.
		if ( false === $incrementor_value || $refresh ) {
			$incrementor_value = uniqid();
			wp_cache_set( $incrementor_key, $incrementor_value );
		}

		return $incrementor_value;
	}

	/**
	 * Get the cache group for the cache.
	 * Used by all function that use cache, so they use the
	 * same cache group, meaning if we invalidate the cache group
	 * all caches will be cleared/flushed.
	 *
	 * @return string
	 */
	public static function get_cache_group() {
		return 'simple-history-' . self::get_cache_incrementor();
	}

	/**
	 * Clears the cache.
	 */
	public static function clear_cache() {
		self::get_cache_incrementor( true );
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
	 * @param callable $callback The callable thing to check.
	 * @return string Name of callable.
	 */
	public static function get_callable_name( $callback ) {
		if ( is_string( $callback ) ) {
			return trim( $callback );
		} elseif ( is_array( $callback ) ) {
			if ( is_object( $callback[0] ) ) {
				return sprintf( '%s::%s', get_class( $callback[0] ), trim( $callback[1] ) );
			} else {
				return sprintf( '%s::%s', trim( $callback[0] ), trim( $callback[1] ) );
			}
		} elseif ( $callback instanceof \Closure ) {
			return 'closure';
		} else {
			return 'unknown';
		}
	}

	/**
	 * Get number of rows and the size of each Simple History table in the database.
	 *
	 * @return array<string, object{table_name: string, size_in_mb: float, num_rows: int}>
	 */
	public static function get_db_table_stats() {
		switch ( Log_Query::get_db_engine() ) {
			case 'mysql':
				return self::get_db_table_stats_mysql();
			case 'sqlite':
				return self::get_db_table_stats_sqlite();
			default:
				return [];
		}
	}

	/**
	 * Get number of rows and the size of each Simple History table in the database.
	 *
	 * Uses sqlite_master to check if tables exist (always available),
	 * then tries dbstat for size info (may not be available in wp-playground).
	 *
	 * @return array<string, array{table_name: string, size_in_mb: float|string, num_rows: int}>
	 */
	public static function get_db_table_stats_sqlite() {
		/** @var \wpdb $wpdb */
		global $wpdb;
		$simple_history = Simple_History::get_instance();

		$events_table   = $simple_history->get_events_table_name();
		$contexts_table = $simple_history->get_contexts_table_name();

		// Check if tables exist using sqlite_master (always available).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM sqlite_master WHERE type='table' AND name=%s",
				$events_table
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$contexts_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT name FROM sqlite_master WHERE type='table' AND name=%s",
				$contexts_table
			)
		);

		// Bail if any of the tables are missing.
		if ( ! $events_exists || ! $contexts_exists ) {
			return [];
		}

		// Try to get sizes using dbstat (may not be available in wp-playground).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events_size_result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(pgsize) / 1024.0 / 1024.0 FROM dbstat WHERE name = %s',
				$events_table
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$contexts_size_result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT SUM(pgsize) / 1024.0 / 1024.0 FROM dbstat WHERE name = %s',
				$contexts_table
			)
		);

		// Use results if available, otherwise show N/A.
		$events_size   = $events_size_result !== null ? round( (float) $events_size_result, 2 ) : 'N/A';
		$contexts_size = $contexts_size_result !== null ? round( (float) $contexts_size_result, 2 ) : 'N/A';

		// Get row counts (always works).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$contexts_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$contexts_table}" );

		return [
			'simple_history'          => [
				'table_name' => $events_table,
				'size_in_mb' => $events_size,
				'num_rows'   => $events_rows,
			],
			'simple_history_contexts' => [
				'table_name' => $contexts_table,
				'size_in_mb' => $contexts_size,
				'num_rows'   => $contexts_rows,
			],
		];
	}

	/**
	 * Get number of rows and the size of each Simple History table in the database.
	 *
	 * @return array<string, object{table_name: string, size_in_mb: float, num_rows: int}>
	 */
	public static function get_db_table_stats_mysql() {
		global $wpdb;
		$simple_history = Simple_History::get_instance();

		// Get table sizes in mb.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_size_result = $wpdb->get_results(
			$wpdb->prepare(
				'
					SELECT table_name AS "table_name",
					round(((data_length + index_length) / 1024 / 1024), 2) "size_in_mb"
					FROM information_schema.TABLES
					WHERE table_schema = %s
					AND table_name IN (%s, %s);
					',
				DB_NAME, // 1
				$simple_history->get_events_table_name(), // 2
				$simple_history->get_contexts_table_name() // 3
			),
			ARRAY_A
		);

		// Bail if not exactly two tables found.
		if ( sizeof( $table_size_result ) !== 2 ) {
			return [];
		}

		$table_size_result = [
			'simple_history'          => $table_size_result[0],
			'simple_history_contexts' => $table_size_result[1],
		];

		// Get num of rows for each table.
		$table_size_result['simple_history']['num_rows'] = (int) $wpdb->get_var( "select count(*) FROM {$simple_history->get_events_table_name()}" ); // phpcs:ignore
		$table_size_result['simple_history_contexts']['num_rows'] = (int) $wpdb->get_var( "select count(*) FROM {$simple_history->get_contexts_table_name()}" ); // phpcs:ignore

		return $table_size_result;
	}

	/**
	 * Disable logging of a taxonomy, if a condition is met.
	 *
	 * An easier method that using filters manually each time.
	 *
	 * @param string $taxonomy_slug Slug of taxonomy to disable logging for.
	 * @param bool   $disable Pass true to disable logging of $taxonomy.
	 */
	public static function disable_taxonomy_log( $taxonomy_slug, $disable = false ) {
		// Bail if taxonomy should not be disabled.
		if ( ! $disable ) {
			return;
		}

		add_filter(
			'simple_history/categories_logger/skip_taxonomies',
			function ( $taxononomies_to_skip ) use ( $taxonomy_slug ) {
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
	 * @param string       $email email address.
	 * @param string       $size Size of the avatar image.
	 * @param string       $default_value URL to a default image to use if no avatar is available.
	 * @param string|false $alt Alternative text to use in image tag. Defaults to blank.
	 * @param array        $args Avatar arguments.
	 * @return string The img element for the user's avatar
	 */
	public static function get_avatar( $email, $size = '96', $default_value = '', $alt = false, $args = array() ) {
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

		return get_avatar( $email, $size, $default_value, $alt, $args );
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
	 *
	 * @param string $ip_address IP-address to get valid IP-address from.
	 * @return string
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
	 * Used by the developer of the plugin to test things.
	 * Check if Simple History dev mode is enabled.
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
	 * @param string $plugin_file_path Path to plugin file, relative to plugins dir. I.e. "simple-history/simple-history.php".
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
		$ip_header_names_keys            = self::get_ip_number_header_names();
		$arr_found_additional_ip_headers = array();
		$context                         = $row->context;

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
		}

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
	 * @param object $instance Object instance to get short name for.
	 * @return string
	 */
	public static function get_class_short_name( $instance ) {
		return substr( strrchr( get_class( $instance ), '\\' ), 1 );
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
				'table_name'   => $simple_history_instance->get_events_table_name(),
				'table_exists' => null,
			],
			[
				'table_name'   => $simple_history_instance->get_contexts_table_name(),
				'table_exists' => null,
			],
		);

		foreach ( $tables as $key => $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table_exists                   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table['table_name'] ) );
			$tables[ $key ]['table_exists'] = $table_exists;
		}

		return $tables;
	}

	/**
	 * Output title for settings section title.
	 * with wrapper classes and markup + classes for icon appended.
	 *
	 * @param string  $title Title.
	 * @param ?string $icon_class_suffix Icon class suffix.
	 * @param ?string $title_suffix Optional HTML suffix (e.g., a premium badge). Must be pre-escaped.
	 * @return string
	 */
	public static function get_settings_section_title_output( $title, $icon_class_suffix = null, $title_suffix = null ) {
		$icon_output = '';

		if ( ! is_null( $icon_class_suffix ) ) {
			$icon_output = sprintf(
				'<span class="sh-SettingsPage-settingsSection-icon sh-Icon--%1$s"></span>',
				esc_attr( $icon_class_suffix )
			);
		}

		$suffix_output = '';
		if ( ! is_null( $title_suffix ) ) {
			// Suffix is expected to be pre-escaped HTML (e.g., a premium badge).
			$suffix_output = ' ' . $title_suffix;
		}

		return sprintf(
			'
			<span class="sh-SettingsPage-settingsSection-title">
				%2$s
				%1$s%3$s
			</span>
			',
			esc_html( $title ),
			$icon_output,
			$suffix_output
		);
	}

	/**
	 * Output title for settings field title.
	 * with wrapper classes and markup + classes for icon appended.
	 *
	 * @param string  $title Title.
	 * @param ?string $icon_class_suffix Icon class suffix.
	 * @param ?string $label_for Optional. The id of the input element to associate the label with.
	 *                           When provided, the title becomes a clickable label.
	 * @return string
	 */
	public static function get_settings_field_title_output( $title, $icon_class_suffix = null, $label_for = null ) {
		$icon_output = '';

		if ( ! is_null( $icon_class_suffix ) ) {
			$icon_output = sprintf(
				'<span class="sh-SettingsPage-settingsField-icon sh-Icon--%1$s"></span>',
				esc_attr( $icon_class_suffix )
			);
		}

		// If $label_for is provided, wrap title in a label element.
		if ( ! is_null( $label_for ) ) {
			$title_output = sprintf(
				'<label for="%1$s">%2$s</label>',
				esc_attr( $label_for ),
				esc_html( $title )
			);
		} else {
			$title_output = esc_html( $title );
		}

		return sprintf(
			'
			<span class="sh-SettingsPage-settingsField">
				%2$s
				%1$s
			</span>
			',
			$title_output,
			$icon_output
		);
	}

	/**
	 * Wrapper for \add_settings_section with added support for:
	 * - Icon before title.
	 * - Wrapper div automatically added.
	 * - Optional HTML ID attribute.
	 * - Optional HTML suffix (e.g., a premium badge).
	 *
	 * @param string       $id Slug-name to identify the section. Used in the 'id' attribute of tags.
	 * @param string|array $title Formatted title of the section. Shown as the heading for the section.
	 *                     Pass in array instead of string to use as ['Section title', 'icon-slug'].
	 *                     Or pass ['Section title', 'icon-slug', 'html-id'] to include an HTML ID attribute.
	 *                     Or pass ['Section title', 'icon-slug', 'html-id', 'suffix'] to include a suffix (e.g., premium badge).
	 * @param callable     $callback_top Function that echos out any content at the top of the section (between heading and fields).
	 * @param string       $page The slug-name of the settings page on which to show the section. Built-in pages include 'general', 'reading', 'writing', 'discussion', 'media', etc. Create your own using add_options_page().
	 * @param array        $args {
	 *     Optional. Additional arguments.
	 *
	 *     @type callable $callback_last Function that echos out any content at the end of the section (before closing wrapper div).
	 * }
	 */
	public static function add_settings_section( $id, $title, $callback_top, $page, $args = [] ) {
		// If title is array then it can be [title, icon-slug, html-id, suffix].
		$html_id = '';
		if ( is_array( $title ) ) {
			$title_text   = $title[0];
			$icon_slug    = $title[1] ?? null;
			$html_id      = $title[2] ?? '';
			$title_suffix = $title[3] ?? null;
			$title        = self::get_settings_section_title_output( $title_text, $icon_slug, $title_suffix );
		} else {
			$title = self::get_settings_section_title_output( $title );
		}

		/**
		 * In WP 6.7 after section is not always outputted.
		 * See track tickets:
		 * https://core.trac.wordpress.org/ticket/62746
		 * https://core.trac.wordpress.org/changeset/59564
		 */
		$id_attribute = '';
		if ( ! empty( $html_id ) ) {
			$id_attribute = sprintf( ' id="%s"', esc_attr( $html_id ) );
		}

		// Build after_section content.
		$after_section_content = '';
		$callback_last         = $args['callback_last'] ?? null;
		if ( is_callable( $callback_last ) ) {
			ob_start();
			call_user_func( $callback_last );
			$after_section_content = ob_get_clean();
		}
		$after_section_content .= '</div>';

		$args = [
			'before_section' => sprintf( '<div class="sh-SettingsCard sh-SettingsPage-settingsSection-wrap"%s>', $id_attribute ),
			'after_section'  => $after_section_content,
		];

		add_settings_section( $id, $title, $callback_top, $page, $args );
	}

	/**
	 *  Add link to add-ons.
	 *
	 * @return string HTML for link to add-ons.
	 */
	public static function get_header_add_ons_link() {
		ob_start();

		?>
		<div class="sh-PageHeader-rightLink">
			<a href="<?php echo esc_url( self::get_tracking_url( 'https://simple-history.com/add-ons/', 'premium_header_addons' ) ); ?>" target="_blank">
				<span class="sh-PageHeader-settingsLinkIcon sh-Icon sh-Icon--extension"></span>
				<span class="sh-PageHeader-settingsLinkText"><?php esc_html_e( 'Add-ons', 'simple-history' ); ?></span>
			</a>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Gets the pager size,
	 * i.e. the number of items to show on each page in the history
	 *
	 * @return int
	 */
	public static function get_pager_size() {
		$pager_size = get_option( 'simple_history_pager_size', 20 );

		/**
		 * Filter the pager size setting
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/pager_size', $pager_size );

		/**
		 * Filter the pager size setting for the history page
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/page_pager_size', $pager_size );

		// Ensure we return a positive int to prevent type errors and division by zero.
		return max( 1, (int) $pager_size );
	}

	/**
	 * Gets the pager size for the dashboard widget,
	 * i.e. the number of items to show on each page in the history
	 *
	 * @since 2.12
	 * @return int
	 */
	public static function get_pager_size_dashboard() {
		$pager_size = get_option( 'simple_history_pager_size_dashboard', 5 );

		/**
		 * Filter the pager size setting for the dashboard.
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/dashboard_pager_size', $pager_size );

		/**
		 * Filter the pager size setting
		 *
		 * @since 2.12
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/pager_size_dashboard', $pager_size );

		return $pager_size;
	}

	/**
	 * Check if the current user can clear the log.
	 *
	 * @since 2.19
	 * @return bool
	 */
	public static function user_can_clear_log() {
		/**
		 * Allows controlling who can manually clear the log.
		 * When this is true then the "Clear"-button in shown in the settings.
		 * When this is false then no button is shown.
		 *
		 * @example
		 * ```php
		 *  // Remove the "Clear"-button, so a user with admin access can not clear the log
		 *  // and wipe their mischievous behavior from the log.
		 *  add_filter(
		 *      'simple_history/user_can_clear_log',
		 *      function ( $user_can_clear_log ) {
		 *          $user_can_clear_log = false;
		 *          return $user_can_clear_log;
		 *      }
		 *  );
		 * ```
		 *
		 * @param bool $allow Whether the current user is allowed to clear the log.
		*/
		return apply_filters( 'simple_history/user_can_clear_log', true );
	}

	/**
	 * Removes all items from the log.
	 *
	 * @return int Number of rows removed.
	 */
	public static function clear_log() {
		global $wpdb;

		$simple_history = Simple_History::get_instance();

		$simple_history_table          = $simple_history->get_events_table_name();
		$simple_history_contexts_table = $simple_history->get_contexts_table_name();

		// Get number of rows before delete.
		$sql_num_rows = "SELECT count(id) AS num_rows FROM {$simple_history_table}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$num_rows = $wpdb->get_var( $sql_num_rows, 0 );

		// Use truncate instead of delete because it's much faster (I think, writing this much later).
		$sql = "TRUNCATE {$simple_history_table}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $sql );

		$sql = "TRUNCATE {$simple_history_contexts_table}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $sql );

		self::clear_cache();

		return $num_rows;
	}

	/**
	 * How old log entries are allowed to be.
	 * 0 = don't delete old entries.
	 *
	 * @return int Number of days.
	 */
	public static function get_clear_history_interval() {
		$days = 60;

		/**
		 * Deprecated filter name, use `simple_history/db_purge_days_interval` instead.
		 * @deprecated
		 */
		$days = (int) apply_filters( 'simple_history_db_purge_days_interval', $days );

		/**
		 * Filter to modify number of days of history to keep.
		 * Default is 60 days.
		 *
		 * @example Keep only the most recent 7 days in the log.
		 *
		 * ```php
		 * add_filter( "simple_history/db_purge_days_interval", function( $days ) {
		 *      $days = 7;
		 *      return $days;
		 *  } );
		 * ```
		 *
		 * @example Expand the log to keep 90 days in the log.
		 *
		 * ```php
		 * add_filter( "simple_history/db_purge_days_interval", function( $days ) {
		 *      $days = 90;
		 *      return $days;
		 *  } );
		 * ```
		 *
		 * @param int $days Number of days of history to keep
		 */
		$days = apply_filters( 'simple_history/db_purge_days_interval', $days );

		return $days;
	}

	/**
	 * Return capability required to view history = for who will the History page be added.
	 * Default capability is "edit_pages".
	 *
	 * @since 2.1.5
	 * @return string capability
	 */
	public static function get_view_history_capability() {
		$view_history_capability = 'edit_pages';

		/**
		 * Deprecated, use filter `simple_history/view_history_capability` instead.
		 */
		$view_history_capability = apply_filters( 'simple_history_view_history_capability', $view_history_capability );

		/**
		 * Filter the capability required to view main simple history page, with the activity feed.
		 * Default capability is "edit_pages".
		 *
		 * @example Change the capability required to view the log to "manage options", so only allow admins are allowed to view the history log page.
		 *
		 * ```php
		 *  add_filter(
		 *      'simple_history/view_history_capability',
		 *      function ( $capability ) {
		 *          $capability = 'manage_options';
		 *          return $capability;
		 *      }
		 *  );
		 * ```
		 *
		 * @param string $view_history_capability
		 */
		$view_history_capability = apply_filters( 'simple_history/view_history_capability', $view_history_capability );

		return $view_history_capability;
	}

	/**
	 * Return capability required to view settings.
	 * Default capability is "manage_options",
	 * but can be modified using filter.
	 *
	 * @since 2.1.5
	 * @return string capability
	 */
	public static function get_view_settings_capability() {
		$view_settings_capability = 'manage_options';

		/**
		 * Old filter name, use `simple_history/view_settings_capability` instead.
		 */
		$view_settings_capability = apply_filters( 'simple_history_view_settings_capability', $view_settings_capability );

		/**
		 * Filters the capability required to view the settings page.
		 *
		 * @example Change capability required to view the
		 *
		 * ```php
		 *  add_filter(
		 *      'simple_history/view_settings_capability',
		 *      function ( $capability ) {
		 *
		 *          $capability = 'manage_options';
		 *          return $capability;
		 *      }
		 *  );
		 * ```
		 *
		 * @param string $view_settings_capability
		 */
		$view_settings_capability = apply_filters( 'simple_history/view_settings_capability', $view_settings_capability );

		return $view_settings_capability;
	}

	/**
	 * Check if the current page is any of the pages that belong
	 * to Simple History.
	 *
	 * Since it uses current_screen() is must be called
	 * after the 'admin_menu' action has been fired.
	 *
	 * @return bool
	 */
	public static function is_on_our_own_pages() {
		// Check if we are on an admin page with Simple History content.
		// All Simple History admin pages have a ?page=simple_history_... query arg.
		// where page is the slug of the registered page.
		$all_menu_pages_slugs = Simple_History::get_instance()->get_menu_manager()->get_all_slugs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : null;

		if ( $page && in_array( $page, $all_menu_pages_slugs, true ) ) {
			return true;
		}

		$current_screen = self::get_current_screen();

		// We are on a Simple History page if we are on dashboard and the setting is set to show on dashboard.
		if ( $current_screen->base === 'dashboard' && self::setting_show_on_dashboard() ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if the database has any data, i.e. at least 1 row.
	 *
	 * @since 2.1.6
	 * @return bool True if database is not empty, false if database is empty = contains no data
	 */
	public static function db_has_data() {
		global $wpdb;

		$simple_history = Simple_History::get_instance();

		$table_name = $simple_history->get_events_table_name();

		$sql_data_exists = "SELECT id AS id_exists FROM {$table_name} LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$data_exists = (bool) $wpdb->get_var( $sql_data_exists, 0 );

		return $data_exists;
	}

	/**
	 * Get setting if plugin should be visible on dashboard.
	 * Defaults to true
	 *
	 * @return bool
	 */
	public static function setting_show_on_dashboard() {
		$show_on_dashboard = get_option( 'simple_history_show_on_dashboard', 1 );

		$show_on_dashboard = apply_filters( 'simple_history_show_on_dashboard', $show_on_dashboard );

		/**
		 * Filter if Simple History should be shown on the dashboard.
		 *
		 * @param int $show_on_dashboard If 1 then show on dashboard, if 0 then do not show on dashboard.
		 */
		$show_on_dashboard = apply_filters( 'simple_history/show_on_dashboard', $show_on_dashboard );

		return (bool) $show_on_dashboard;
	}

	/**
	 * Should Simple History be shown as a page inside the dashboard menu.
	 *
	 * Defaults to true.
	 *
	 * @deprecated 5.7.0
	 * @return bool
	 */
	public static function setting_show_as_page() {
		$setting = get_option( 'simple_history_show_as_page', 1 );
		$setting = apply_filters( 'simple_history_show_as_page', $setting );

		return (bool) $setting;
	}

	/**
	 * Should Simple History be shown as a page in the main menu, at top level,
	 * next to pages, tools, settings, etc.
	 *
	 * Defaults to true.
	 *
	 * @return bool
	 */
	public static function setting_show_as_menu_page() {
		/**
		 * Filter if Simple History should be shown as a page in the main admin menu.
		 *
		 * @since 5.5.2
		 */
		$setting = apply_filters( 'simple_history/show_admin_menu_page', true );

		return (bool) $setting;
	}

	/**
	 * Returns the location of the main simple history menu page.
	 *
	 * Valid locations are:
	 * - 'top' = Below dashboard and Jetpack and similar top level menu items.
	 * - 'bottom' = Below settings and similar bottom level menu items.
	 * - 'inside_tools' = Inside the tools menu.
	 * - 'inside_dashboard' = Inside the settings menu.
	 *
	 * Defaults to 'top'.
	 *
	 * @return string Location of the main menu page.
	 */
	public static function get_menu_page_location() {
		$option_slug = 'simple_history_menu_page_location';
		$setting     = get_option( $option_slug );

		// If it does not exist, then default so the option can auto-load.
		if ( false === $setting ) {
			$setting = 'top';
			update_option( $option_slug, $setting, true );
		}

		/**
		 * Filter to control the placement of Simple History in the Admin Menu.
		 * Valid locations:
		 * - 'top' for placement close to dashboard at top of main menu
		 * - 'bottom' for placement near below settings
		 * - 'inside_tools' for placement inside the tools menu
		 * - 'inside_dashboard' for placement inside the dashboard menu
		 *
		 * @since 5.5.2
		 *
		 * @param string $setting Location slug.
		 */
		$setting = apply_filters( 'simple_history/admin_menu_location', $setting );

		return $setting;
	}

	/**
	 * Returns true if Simple History can be shown in the admin bar
	 *
	 * @return bool
	 */
	public static function setting_show_in_admin_bar() {
		$setting = get_option( 'simple_history_show_in_admin_bar', 1 );
		$setting = apply_filters( 'simple_history_show_in_admin_bar', $setting );

		/**
		 * Filter if Simple History should be shown in the admin bar.
		 *
		 * @since 5.5.1
		 */
		$setting = apply_filters( 'simple_history/show_in_admin_bar', $setting );

		return (bool) $setting;
	}

	/**
	 * Returns true if Detective Mode is active.
	 *
	 * Default is false.
	 *
	 * @return bool
	 */
	public static function detective_mode_is_enabled() {
		return (bool) apply_filters(
			'simple_history/detective_mode_enabled',
			get_option( 'simple_history_detective_mode_enabled', 0 )
		);
	}

	/**
	 * Returns true if Experimental Features is active.
	 *
	 * Default is false.
	 *
	 * @return bool
	 */
	public static function experimental_features_is_enabled() {
		return (bool) apply_filters(
			'simple_history/experimental_features_enabled',
			get_option( 'simple_history_experimental_features_enabled', 0 )
		);
	}

	/**
	 * Get number of events the last n days.
	 *
	 * Respects user permissions - only counts events from loggers the current user can view.
	 * Uses WordPress timezone for date calculations.
	 *
	 * @param int $period_days Number of days to get events for.
	 * @return int Number of events user can view.
	 */
	public static function get_num_events_last_n_days( $period_days = Date_Helper::DAYS_PER_MONTH ) {
		global $wpdb;
		$simple_history              = Simple_History::get_instance();
		$sqlStringLoggersUserCanRead = $simple_history->get_loggers_that_user_can_read( null, 'sql' );

		$sql = sprintf(
			'
                SELECT count(*)
                FROM %1$s
                WHERE UNIX_TIMESTAMP(date) >= %2$d
                AND logger IN %3$s
            ',
			$simple_history->get_events_table_name(),
			Date_Helper::get_last_n_days_start_timestamp( $period_days ),
			$sqlStringLoggersUserCanRead
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $sql );

		return (int) $count;
	}

	/**
	 * Get number of events today (WordPress timezone-aware).
	 *
	 * Counts individual events from midnight today (00:00:00) in WordPress timezone.
	 * Respects user permissions - only counts events from loggers the current user can view.
	 *
	 * @return int Number of events today that user can view.
	 */
	public static function get_num_events_today() {
		global $wpdb;
		$simple_history              = Simple_History::get_instance();
		$sqlStringLoggersUserCanRead = $simple_history->get_loggers_that_user_can_read( null, 'sql' );

		$sql = sprintf(
			'
                SELECT count(*)
                FROM %1$s
                WHERE UNIX_TIMESTAMP(date) >= %2$d
                AND logger IN %3$s
            ',
			$simple_history->get_events_table_name(),
			Date_Helper::get_today_start_timestamp(),
			$sqlStringLoggersUserCanRead
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $sql );

		return (int) $count;
	}

	/**
	 * Get current number of events in database.
	 *
	 * Counts all events currently stored in the database.
	 * Respects user permissions - only counts events from loggers the current user can view.
	 *
	 * @return int Number of events currently in database that user can view.
	 */
	public static function get_current_database_events_count() {
		global $wpdb;
		$simple_history              = Simple_History::get_instance();
		$sqlStringLoggersUserCanRead = $simple_history->get_loggers_that_user_can_read( null, 'sql' );

		$sql = sprintf(
			'
                SELECT count(*)
                FROM %1$s
                WHERE logger IN %2$s
            ',
			$simple_history->get_events_table_name(),
			$sqlStringLoggersUserCanRead
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $sql );

		return (int) $count;
	}

	/**
	 * Get number of events per day the last n days.
	 *
	 * Respects user permissions - only counts events from loggers the current user can view.
	 * Uses WordPress timezone for date calculations.
	 *
	 * @param int $period_days Number of days to get events for.
	 * @return array Array with date as key and number of events user can view as value.
	 */
	public static function get_num_events_per_day_last_n_days( $period_days = Date_Helper::DAYS_PER_MONTH ) {
		/** @var \wpdb $wpdb */
		global $wpdb;

		$simple_history              = Simple_History::get_instance();
		$sqlStringLoggersUserCanRead = $simple_history->get_loggers_that_user_can_read( null, 'sql' );
		$db_engine                   = Log_Query::get_db_engine();

		// Get WordPress timezone offset for converting dates from GMT to local timezone.
		// Database stores dates in GMT, but we need to group by dates in WordPress timezone.
		$wp_timezone       = wp_timezone();
		$wp_offset_seconds = $wp_timezone->getOffset( new \DateTime( 'now', $wp_timezone ) );

		$sql = null;

		if ( $db_engine === 'mysql' ) {
			// Use DATE_ADD with INTERVAL to convert from GMT to WordPress timezone.
			// This is more reliable than CONVERT_TZ which requires timezone tables.
			// Note: We use seconds because timezone offsets can be fractional hours (e.g., +5:30 for India).
			$sql = sprintf(
				'
					SELECT
						date_format(DATE_ADD(date, INTERVAL %4$d SECOND), "%%Y-%%m-%%d") AS yearDate,
						count(date) AS count
					FROM
						%1$s
					WHERE
						UNIX_TIMESTAMP(date) >= %2$d
						AND logger IN %3$s
					GROUP BY yearDate
					ORDER BY yearDate ASC
				',
				$simple_history->get_events_table_name(),
				Date_Helper::get_last_n_days_start_timestamp( $period_days ),
				$sqlStringLoggersUserCanRead,
				$wp_offset_seconds
			);
		} elseif ( $db_engine === 'sqlite' ) {
			// SQLite: Convert from GMT to WordPress timezone by adding offset seconds.
			$sql = sprintf(
				'
					SELECT
						strftime("%%Y-%%m-%%d", datetime(date, "%4$s seconds")) AS yearDate,
						count(date) AS count
					FROM
						%1$s
					WHERE
						unixepoch(date) >= %2$d
						AND logger IN %3$s
					GROUP BY yearDate
					ORDER BY yearDate ASC
				',
				$simple_history->get_events_table_name(),
				Date_Helper::get_last_n_days_start_timestamp( $period_days ),
				$sqlStringLoggersUserCanRead,
				$wp_offset_seconds >= 0 ? "+{$wp_offset_seconds}" : $wp_offset_seconds
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dates = $wpdb->get_results( $sql );

		return $dates;
	}

	/**
	 * Get number of unique events the last n days.
	 *
	 * @param int $days Number of days to get events for.
	 * @return int Number of unique events.
	 */
	public static function get_unique_events_for_days( $days = 7 ) {
		global $wpdb;
		$simple_history = Simple_History::get_instance();

		$days       = (int) $days;
		$table_name = $simple_history->get_events_table_name();
		$cache_key  = 'sh_' . md5( __METHOD__ . $days );
		$numEvents  = get_transient( $cache_key );

		if ( $numEvents === false ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"
                SELECT count( DISTINCT occasionsID )
                FROM $table_name
                WHERE date >= DATE_ADD(CURDATE(), INTERVAL -%d DAY)
            	",
				$days
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$numEvents = $wpdb->get_var( $sql );

			// Don't cache failed queries (null result when table doesn't exist).
			if ( $numEvents !== null ) {
				set_transient( $cache_key, $numEvents, HOUR_IN_SECONDS );
			}
		}

		// Always return int to prevent type errors in arithmetic operations.
		return (int) $numEvents;
	}

	/**
	 * Check if the current request is a request made from WP CLI.
	 *
	 * @return bool
	 */
	public static function is_wp_cli() {
		return defined( 'WP_CLI' ) && WP_CLI;
	}

	/**
	 * Calculates what to show in the date filter dropdown.
	 * Returns an array with keys and values:
	 * - "arr_days_and_pages": Array with debug info about how many days and pages to show.
	 * - "daysToShow": Optimal number of days to show, regarding to number of items in the log, to prevent the initial query from being too slow.
	 * - "result_months": Array with unique months, so the date dropdown can show only months with events.
	 *
	 * @return array
	 */
	public static function get_data_for_date_filter() {
		global $wpdb;

		$simple_history = Simple_History::get_instance();

		// Start months filter.
		$table_name                   = $simple_history->get_events_table_name();
		$loggers_user_can_read_sql_in = $simple_history->get_loggers_that_user_can_read( null, 'sql' );

		// Get unique months.
		$loggers_slugs = $simple_history->get_loggers_that_user_can_read( null, 'slugs' );
		$cache_key     = 'sh_filter_unique_months_' . md5( implode( ',', $loggers_slugs ) );
		$result_months = get_transient( $cache_key );

		if ( false === $result_months ) {
			$sql_dates = sprintf(
				'
				SELECT DISTINCT ( date_format(DATE, "%%Y-%%m") ) AS yearMonth
				FROM %s
				WHERE logger IN %s
				ORDER BY yearMonth DESC
				',
				$table_name, // 1
				$loggers_user_can_read_sql_in // 2
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result_months = $wpdb->get_results( $sql_dates );

			set_transient( $cache_key, $result_months, HOUR_IN_SECONDS );
		}

		$arr_days_and_pages = array();

		// Default month = current month
		// Mainly for performance reasons, since often
		// it's not the users intention to view all events,
		// but just the latest.

		// Determine if we limit the date range by default.
		$daysToShow = 1;

		// Start with the latest day.
		$numEvents = self::get_unique_events_for_days( $daysToShow );
		$numPages  = $numEvents / self::get_pager_size();

		$arr_days_and_pages[] = array(
			'daysToShow' => $daysToShow,
			'numPages'   => $numPages,
		);

		// Example on my server with lots of brute force attacks (causing log to not load)
		// 166434 / 15 = 11 000 pages for last 7 days
		// 1 day = 3051 / 15 = 203 pages = still much but better than 11000 pages!
		if ( $numPages < 20 ) {
			// Not that many things the last day. Let's try to expand to 7 days instead.
			$daysToShow = 7;
			$numEvents  = self::get_unique_events_for_days( $daysToShow );
			$numPages   = $numEvents / self::get_pager_size();

			$arr_days_and_pages[] = array(
				'daysToShow' => $daysToShow,
				'numPages'   => $numPages,
			);

			if ( $numPages < 20 ) {
				// Not that many things the last 7 days. Let's try to expand to 14 days instead.
				$daysToShow = 14;
				$numEvents  = self::get_unique_events_for_days( $daysToShow );
				$numPages   = $numEvents / self::get_pager_size();

				$arr_days_and_pages[] = array(
					'daysToShow' => $daysToShow,
					'numPages'   => $numPages,
				);

				if ( $numPages < 20 ) {
					// Not many things the last 14 days either. Let try with 30 days.
					$daysToShow = 30;
					$numEvents  = self::get_unique_events_for_days( $daysToShow );
					$numPages   = $numEvents / self::get_pager_size();

					$arr_days_and_pages[] = array(
						'daysToShow' => $daysToShow,
						'numPages'   => $numPages,
					);

					// If 30 days gives a big amount of pages, go back to 14 days.
					if ( $numPages > 1000 ) {
						$daysToShow = 14;
					}
				}
			}
		}

		return [
			'arr_days_and_pages' => $arr_days_and_pages,
			'daysToShow'         => $daysToShow,
			'result_months'      => $result_months,
		];
	}

	/**
	 * How often the script checks for new rows.
	 *
	 * @return int Interval in milliseconds.
	 */
	public static function get_new_events_check_interval() {
		/**
		 * Filter the interval for how often the script checks for new rows.
		 *
		 * Set to 0 to disable the notifier.
		 *
		 * @example Change the interval from the default 10000 ms (10 seconds) to a minute (300000 ms).
		 * // Change the interval from the default 10000 ms (10 seconds) to a minute (300000 ms).
		 * ```php
		 * add_filter(
		 *   'SimpleHistoryNewRowsNotifier/interval',
		 *   function( $interval ) {
		 *     $interval = 300000;
		 *     return $interval;
		 *   }
		 * );
		 * ```
		 *
		 * @param int $interval Interval in milliseconds.
		 */
		return (int) apply_filters( 'SimpleHistoryNewRowsNotifier/interval', 10000 );
	}

	/**
	 * Increase the total number of logged events.
	 * Used to keep track of how many events have been logged since the plugin was installed.
	 */
	public static function increase_total_logged_events_count() {
		// Don't log when updating widgets,
		// because it causes error "widget_setting_too_many_options".
		// Bug report: https://github.com/bonny/WordPress-Simple-History/issues/498.
		if ( doing_action( 'wp_ajax_update-widget' ) ) {
			return;
		}

		update_option(
			'simple_history_total_logged_events_count',
			self::get_total_logged_events_count() + 1,
			false
		);
	}

	/**
	 * Get the total number of logged events.
	 * Used to keep track of how many events have been logged since the plugin was installed.
	 *
	 * @return int
	 */
	public static function get_total_logged_events_count() {
		return (int) get_option( 'simple_history_total_logged_events_count', 0 );
	}

	/**
	 * Get plugin install date as GMT.
	 *
	 * @return string|false Date as GMT or false if not set.
	 */
	public static function get_plugin_install_date() {
		return get_option( 'simple_history_install_date_gmt' );
	}

	/**
	 * Escape a string to be used in a CSV context, by adding an apostrophe if field
	 * begins with '=', '+', '-', or '@'.
	 *
	 * Function taken from the Jetpack Plugin, Copyright Automattic.
	 *
	 * @see https://www.drupal.org/project/webform/issues/3157877#:~:text=At%20present%2C%20the%20best%20defence%20strategy%20we%20are%20aware%20of%20is%20prefixing%20cells%20that%20start%20with%20%E2%80%98%3D%E2%80%99%20%2C%20%27%2B%27%20or%20%27%2D%27%20with%20an%20apostrophe.
	 * @see https://github.com/Automattic/jetpack/blob/d4068d52c35a30edc01b9356a4764132aeb532fd/projects/packages/forms/src/contact-form/class-contact-form-plugin.php#L1854
	 * @param string $field - the CSV field.
	 * @return string CSV field with escaped characters.
	 */
	public static function esc_csv_field( $field ) {
		// Bail if not string.
		if ( ! is_string( $field ) ) {
			return '';
		}

		$active_content_triggers = array( '=', '+', '-', '@' );

		if ( in_array( substr( $field, 0, 1 ), $active_content_triggers, true ) ) {
			$field = "'" . $field;
		}

		return $field;
	}

	/**
	 * Determine if promo boxes should be shown.
	 *
	 * @return bool True if promo boxes should be shown, false otherwise.
	 */
	public static function show_promo_boxes() {
		$show_promo_boxes = true;

		// Hide if Premium add-on is active.
		if ( self::is_premium_add_on_active() ) {
			$show_promo_boxes = false;
		}

		// Hide if Extended Settings is active.
		if ( self::is_extended_settings_add_on_active() ) {
			$show_promo_boxes = false;
		}

		/**
		 * Filter to determine if promo boxes should be shown.
		 *
		 * @param bool $show_promo_boxes True if promo boxes should be shown, false otherwise.
		 */
		$show_promo_boxes = apply_filters( 'simple_history/show_promo_boxes', $show_promo_boxes );

		return $show_promo_boxes;
	}

	/**
	 * Get HTML for a premium feature teaser box.
	 *
	 * Note: When echoing the output, wrap it in wp_kses_post() to satisfy PHPCS:
	 * echo wp_kses_post( Helpers::get_premium_feature_teaser( ... ) );
	 *
	 * @param string       $title The title/heading for the premium feature.
	 * @param string|array $description The description of the premium feature, or an array of feature strings for a bullet list.
	 * @param string       $tracking_param The tracking parameter for the "Learn More" link (e.g., 'premium_feeds_settings').
	 * @param string       $button_text Optional. The text for the button. Default: 'Learn More'.
	 * @return string HTML for the premium teaser box, or empty string if promo boxes should not be shown.
	 */
	public static function get_premium_feature_teaser( $title, $description, $tracking_param, $button_text = '' ) {
		// Only show premium teaser if premium is not installed.
		if ( ! self::show_promo_boxes() ) {
			return '';
		}

		if ( empty( $button_text ) ) {
			$button_text = __( 'Get Premium', 'simple-history' );
		}

		$premium_url = self::get_tracking_url( 'https://simple-history.com/add-ons/premium/', $tracking_param );

		ob_start();
		?>
		<div class="sh-PremiumFeatureTeaser">
			<p class="sh-PremiumFeatureTeaser-title">
				<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				<strong><?php echo esc_html( $title ); ?></strong>
			</p>

			<?php if ( is_array( $description ) ) { ?>
				<ul class="sh-PremiumFeatureTeaser-features">
					<?php foreach ( $description as $feature ) { ?>
						<li>
							<span class="dashicons dashicons-yes"></span>
							<?php echo esc_html( $feature ); ?>
						</li>
					<?php } ?>
				</ul>
			<?php } else { ?>
				<p>
					<?php echo esc_html( $description ); ?>
				</p>
			<?php } ?>

			<p class="sh-PremiumFeatureTeaser-ctaLinkContainer">
				<a href="<?php echo esc_url( $premium_url ); ?>"
					target="_blank"
					class="sh-PremiumFeatureTeaser-ctaLink">
					<?php echo esc_html( $button_text ); ?>
				</a>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if premium add-on is active.
	 *
	 * @return bool True if premium add-on is active, false otherwise.
	 */
	public static function is_premium_add_on_active() {
		return self::is_plugin_active( 'simple-history-premium/simple-history-premium.php' );
	}

	/**
	 * Check if Extended Settings add-on is active.
	 */
	public static function is_extended_settings_add_on_active() {
		return self::is_plugin_active( 'simple-history-extended-settings/index.php' );
	}

	/**
	 * Get the URL to the admin page where user views the history feed.
	 *
	 * Can not use `menu_page_url()` because it only works within the admin area.
	 * But we want to be able to link to history page also from front end.
	 *
	 * Calls to this from the Admin Bar Quick View also happens before menu is registered.
	 *
	 * @return string URL to admin page, for example http://wordpress-stable.test/wordpress/wp-admin/index.php?page=simple_history_page.
	 */
	public static function get_history_admin_url() {
		$history_page_location = self::get_menu_page_location();

		if ( in_array( $history_page_location, array( 'top', 'bottom' ), true ) ) {
			return admin_url( 'admin.php?page=' . Simple_History::MENU_PAGE_SLUG );
		} elseif ( 'inside_tools' === $history_page_location ) {
			return admin_url( 'tools.php?page=' . Simple_History::MENU_PAGE_SLUG );
		} elseif ( 'inside_dashboard' === $history_page_location ) {
			return admin_url( 'index.php?page=' . Simple_History::MENU_PAGE_SLUG );
		} else {
			// Fallback if no match found.
			return admin_url( 'admin.php?page=' . Simple_History::MENU_PAGE_SLUG );
		}
	}

	/**
	 * Get URL for settings page.
	 *
	 * Uses the same menu location logic as the main history page.
	 *
	 * @return string URL for settings page.
	 */
	public static function get_settings_page_url() {
		$history_page_location = self::get_menu_page_location();

		if ( in_array( $history_page_location, array( 'top', 'bottom' ), true ) ) {
			return admin_url( 'admin.php?page=' . Simple_History::SETTINGS_MENU_PAGE_SLUG );
		} elseif ( in_array( $history_page_location, array( 'inside_tools', 'inside_dashboard' ), true ) ) {
			return admin_url( 'options-general.php?page=' . Simple_History::SETTINGS_MENU_PAGE_SLUG );
		} else {
			// Fallback if no match found.
			return admin_url( 'admin.php?page=' . Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}
	}


	/**
	 * Get URL for a main tab in the settings page.
	 *
	 * @param string $tab_slug Slug for the tab.
	 * @return string URL for the tab, unescaped.
	 */
	public static function get_settings_page_tab_url( $tab_slug ) {
		return add_query_arg(
			[
				'selected-tab' => $tab_slug,
			],
			self::get_settings_page_url()
		);
	}

	/**
	 * Get URL for a sub-tab in the settings page.
	 *
	 * @param string $sub_tab_slug Slug for the sub-tab.
	 * @return string URL for the sub-tab, unescaped.
	 */
	public static function get_settings_page_sub_tab_url( $sub_tab_slug ) {
		return add_query_arg(
			[
				'selected-tab'     => Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG,
				'selected-sub-tab' => $sub_tab_slug,
			],
			self::get_settings_page_url()
		);
	}

	/**
	 * Checks if an event exists in the database.
	 * Does not check permissions.
	 *
	 * @param int $event_id Event ID.
	 * @return bool True if event exists, false otherwise.
	 */
	public static function event_exists( $event_id ) {
		global $wpdb;
		$simple_history    = Simple_History::get_instance();
		$events_table_name = $simple_history->get_events_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE id = %d',
				$events_table_name,
				$event_id
			)
		);
	}

	/**
	 * Count events in the database.
	 *
	 * Returns a raw count of events, without any permission checks.
	 * Useful for tests and internal statistics.
	 *
	 * @since 5.21.0
	 *
	 * @param array $args {
	 *     Optional. Arguments to filter the count.
	 *
	 *     @type string $logger Only count events from this logger.
	 *     @type string $level  Only count events with this level.
	 * }
	 * @return int Number of events matching the criteria.
	 */
	public static function count_events( $args = [] ) {
		global $wpdb;
		$simple_history    = Simple_History::get_instance();
		$events_table_name = $simple_history->get_events_table_name();

		$where_clauses = [];
		$where_values  = [];

		if ( ! empty( $args['logger'] ) ) {
			$where_clauses[] = 'logger = %s';
			$where_values[]  = $args['logger'];
		}

		if ( ! empty( $args['level'] ) ) {
			$where_clauses[] = 'level = %s';
			$where_values[]  = $args['level'];
		}

		$where_sql = '';
		if ( ! empty( $where_clauses ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		$sql = "SELECT COUNT(*) FROM {$events_table_name} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $where_values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Returns markup for a tooltip with help text icon.
	 *
	 * @param string $tooltip_text The text to display in the tooltip.
	 * @return string
	 */
	public static function get_tooltip_html( $tooltip_text ) {
		$tooltip_text = trim( $tooltip_text );

		if ( empty( $tooltip_text ) ) {
			return '';
		}

		ob_start();
		?>
		<span class="sh-Icon sh-Icon--help sh-TooltipIcon" title="<?php echo esc_attr( $tooltip_text ); ?>"></span>
		<?php
		return trim( ob_get_clean() );
	}

	/**
	 * Get all sticky event IDs.
	 *
	 * @return array<int> Array of sticky event IDs.
	 */
	public static function get_sticky_event_ids() {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT history_id FROM %i WHERE `key` = %s',
				$contexts_table,
				'_sticky'
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Generate a URL to the events log with filters applied.
	 *
	 * This helper function creates properly formatted and encoded URLs for linking
	 * to the events log with various filters applied. It can be used anywhere in the
	 * plugin where you need to link to filtered event results.
	 *
	 * IMPORTANT: When outputting the returned URL in HTML, you MUST use esc_url()
	 * for security, per WordPress coding standards:
	 *
	 *     echo '<a href="' . esc_url( $url ) . '">Link</a>';
	 *
	 * @param array $args {
	 *     Optional. Array of filter arguments.
	 *
	 *     @type array|int    $users      Array of user data with 'id', 'display_name', 'user_email' keys,
	 *                                     or array of such arrays for multiple users,
	 *                                     or single user ID as integer.
	 *     @type string       $date       Date filter string (e.g., 'lastdays:30', 'lastdays:7').
	 *     @type string       $search     Search text to filter containing words.
	 *     @type array        $loglevels  Array of log level filters.
	 *     @type array        $messages   Array of message type filters.
	 *     @type array        $initiators Array of initiator filters.
	 *     @type string       $context    Context filter string.
	 * }
	 * @return string The filtered events log URL (must be escaped with esc_url() before output).
	 */
	public static function get_filtered_events_url( $args = [] ) {
		$query_args = [
			'page' => 'simple_history_admin_menu_page',
		];

		// Handle users filter.
		if ( ! empty( $args['users'] ) ) {
			$users_filter = [];

			// If it's a single user ID (integer), convert to array format.
			if ( is_int( $args['users'] ) ) {
				$user = get_userdata( $args['users'] );
				if ( $user ) {
					$users_filter[] = [
						'id'    => (string) $user->ID,
						'value' => $user->display_name . ' (' . $user->user_email . ')',
					];
				}
			} elseif ( is_array( $args['users'] ) ) {
				// Check if it's a single user array (has 'id' key) or array of users.
				if ( isset( $args['users']['id'] ) ) {
					// Single user array.
					$user_value = $args['users']['display_name'];
					if ( ! empty( $args['users']['user_email'] ) ) {
						$user_value .= ' (' . $args['users']['user_email'] . ')';
					}

					$users_filter[] = [
						'id'    => (string) $args['users']['id'],
						'value' => $user_value,
					];
				} else {
					// Array of users.
					foreach ( $args['users'] as $user ) {
						$user_value = $user['display_name'];
						if ( ! empty( $user['user_email'] ) ) {
							$user_value .= ' (' . $user['user_email'] . ')';
						}

						$users_filter[] = [
							'id'    => (string) $user['id'],
							'value' => $user_value,
						];
					}
				}
			}

			if ( ! empty( $users_filter ) ) {
				$users_json          = wp_json_encode( $users_filter );
				$query_args['users'] = $users_json;
			}
		}

		// Handle date filter.
		if ( ! empty( $args['date'] ) ) {
			$query_args['date'] = $args['date'];
		}

		// Handle search filter.
		if ( ! empty( $args['search'] ) ) {
			$query_args['search'] = $args['search'];
		}

		// Handle log levels filter.
		if ( ! empty( $args['loglevels'] ) && is_array( $args['loglevels'] ) ) {
			$query_args['loglevels'] = wp_json_encode( $args['loglevels'] );
		}

		// Handle message types filter.
		if ( ! empty( $args['messages'] ) && is_array( $args['messages'] ) ) {
			$query_args['messages'] = wp_json_encode( $args['messages'] );
		}

		// Handle initiators filter.
		if ( ! empty( $args['initiators'] ) && is_array( $args['initiators'] ) ) {
			$query_args['initiators'] = wp_json_encode( $args['initiators'] );
		}

		// Handle context filter.
		if ( ! empty( $args['context'] ) ) {
			$query_args['context'] = $args['context'];
		}

		// Handle exclusion/negative filters.
		if ( ! empty( $args['exclude_search'] ) ) {
			$query_args['exclude-search'] = $args['exclude_search'];
		}

		if ( ! empty( $args['exclude_loglevels'] ) && is_array( $args['exclude_loglevels'] ) ) {
			$query_args['exclude-levels'] = wp_json_encode( $args['exclude_loglevels'] );
		}

		if ( ! empty( $args['exclude_loggers'] ) && is_array( $args['exclude_loggers'] ) ) {
			$query_args['exclude-loggers'] = wp_json_encode( $args['exclude_loggers'] );
		}

		if ( ! empty( $args['exclude_messages'] ) && is_array( $args['exclude_messages'] ) ) {
			$query_args['exclude-messages'] = wp_json_encode( $args['exclude_messages'] );
		}

		if ( ! empty( $args['exclude_users'] ) && is_array( $args['exclude_users'] ) ) {
			$query_args['exclude-users'] = wp_json_encode( $args['exclude_users'] );
		}

		if ( ! empty( $args['exclude_initiators'] ) && is_array( $args['exclude_initiators'] ) ) {
			$query_args['exclude-initiator'] = wp_json_encode( $args['exclude_initiators'] );
		}

		if ( ! empty( $args['exclude_context'] ) ) {
			$query_args['exclude-context'] = $args['exclude_context'];
		}

		// Build the URL manually to properly encode JSON parameters.
		$base_url  = admin_url( 'admin.php' );
		$url_parts = [];

		foreach ( $query_args as $key => $value ) {
			$url_parts[] = $key . '=' . rawurlencode( $value );
		}

		return $base_url . '?' . implode( '&', $url_parts );
	}

	/**
	 * Remove 4-byte UTF-8 characters (emojis and other supplementary plane chars)
	 * from a string. This is needed because the database tables use utf8 charset
	 * which only supports 3-byte characters.
	 *
	 * @since 5.19.0
	 * @param mixed $value The value to sanitize.
	 * @return mixed The value with 4-byte characters removed if string, otherwise unchanged.
	 */
	public static function strip_4_byte_chars( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		return preg_replace( '/[\x{10000}-\x{10FFFF}]/u', '', $value );
	}

	/**
	 * Build tracking URL with standardized UTM parameters for analytics.
	 *
	 * Creates consistent tracking URLs for monitoring which features generate
	 * user interest in premium functionality. UTM parameters follow a structured
	 * naming convention for easy analysis in Google Analytics.
	 *
	 * Uses utm_campaign as the primary tracking parameter because it appears in
	 * standard GA4 Traffic Acquisition reports (unlike utm_content which requires
	 * Custom Explorations to view).
	 *
	 * @since 5.0.0
	 *
	 * @param string $url Base URL to add tracking parameters to.
	 * @param string $utm_campaign Campaign identifier in format: {category}_{location}_{action}.
	 *                             Examples:
	 *                             - 'premium_dashboard_sidebar' - Sidebar promo on main dashboard
	 *                             - 'premium_stats_daterange' - Date range feature in stats
	 *                             - 'premium_export_banner' - Export page premium promo
	 *                             - 'premium_events_ipaddress' - IP address Google Maps feature
	 *                             - 'docs_filter_help' - Documentation for filter help.
	 * @param string $utm_source Traffic source. Default: 'wpadmin'. Alternative: 'wordpress_admin'.
	 * @param string $utm_medium Traffic medium. Default: 'plugin'. Alternative: 'Simple_History'.
	 * @param string $utm_content Optional content identifier for A/B testing variations.
	 *                            Only use when testing different versions of the SAME feature.
	 *                            Examples: 'blue_button', 'variant_a', 'top_link'.
	 *                            Leave empty for standard tracking.
	 * @return string URL with UTM tracking parameters appended.
	 */
	public static function get_tracking_url( $url, $utm_campaign, $utm_source = 'wpadmin', $utm_medium = 'plugin', $utm_content = '' ) {
		$params = [
			'utm_source'   => $utm_source,
			'utm_medium'   => $utm_medium,
			'utm_campaign' => $utm_campaign,
		];

		// Only add utm_content if provided (for A/B testing).
		if ( ! empty( $utm_content ) ) {
			$params['utm_content'] = $utm_content;
		}

		return add_query_arg( $params, $url );
	}
}
