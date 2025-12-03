<?php

namespace Simple_History;

use WP_Session_Tokens;

/**
 * Class that handles stats functionality for Simple History,
 * i.e. retrieving stats data for the stats page and for the Stats REST API.
 */
class Events_Stats {
	/**
	 * Events table name.
	 *
	 * @var string
	 */
	private $events_table;

	/**
	 * Contexts table name.
	 *
	 * @var string
	 */
	private $contexts_table;

	/**
	 * Get the events table name, lazy-loading if needed.
	 *
	 * @return string
	 */
	private function get_events_table_name() {
		if ( ! $this->events_table ) {
			$simple_history     = Simple_History::get_instance();
			$this->events_table = $simple_history->get_events_table_name();
		}
		return $this->events_table;
	}

	/**
	 * Get the contexts table name, lazy-loading if needed.
	 *
	 * @return string
	 */
	private function get_contexts_table_name() {
		if ( ! $this->contexts_table ) {
			$simple_history       = Simple_History::get_instance();
			$this->contexts_table = $simple_history->get_contexts_table_name();
		}
		return $this->contexts_table;
	}

	/**
	 * Method for getting event counts by logger and message value.
	 *
	 * Examples:
	 * get_event_count( 'SimpleUserLogger', [ 'user_login_failed', 'user_unknown_login_failed' ], $date_from, $date_to );
	 * et_event_count( 'SimpleUserLogger', 'user_created', $date_from, $date_to );
	 *
	 * @param string       $logger_slug    The logger slug (e.g. 'SimpleMediaLogger').
	 * @param string|array $message_value  The value(s) to match against.
	 * @param int          $date_from      Start timestamp.
	 * @param int          $date_to        End timestamp.
	 * @return int Count of matching events.
	 */
	protected function get_event_count( $logger_slug, $message_value, $date_from, $date_to ) {
		global $wpdb;

		// Convert single value to array for consistent handling.
		$values = (array) $message_value;

		// Create placeholders for the IN clause.
		// This creates a string like this: "%s,%s,%s".
		$value_placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );

		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		$query_args = array_merge(
			[ $events_table, $contexts_table, $logger_slug ],
			$values,
			[ $date_from, $date_to ]
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic IN clause placeholders in $value_placeholders variable matched with merged $query_args array
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT h.id)
					FROM %i h
					JOIN %i c ON h.id = c.history_id
					WHERE h.logger = %s
					AND c.key = "_message_key"
					AND c.value IN (' . $value_placeholders . ')
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)',
				$query_args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get currently logged in users.
	 *
	 * @param int $limit Optional. Limit the number of users returned. Default is 10.
	 * @return array Array of currently logged in users with their last activity.
	 */
	public function get_logged_in_users( $limit = 10 ) {
		global $wpdb;
		$logged_in_users = [];

		// Query session tokens directly from user meta table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$users_with_session_tokens = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
				'session_tokens'
			)
		);

		foreach ( $users_with_session_tokens as $one_user_id ) {
			$sessions = WP_Session_Tokens::get_instance( $one_user_id );

			$all_user_sessions = $sessions->get_all();
			if ( $all_user_sessions ) {
				$logged_in_users[] = [
					'user'           => get_userdata( $one_user_id ),
					'sessions_count' => count( $all_user_sessions ),
					'sessions'       => $all_user_sessions,
				];
			}
		}

		return array_slice( $logged_in_users, 0, $limit );
	}

	/**
	 * Get total number of events for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total number of events, or false if invalid dates.
	 */
	public function get_total_events( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table = $this->get_events_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				'SELECT 
					COUNT(*)
				FROM 
					%i
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)',
				$events_table,
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get total number of unique users involved in events for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total number of unique users, or false if invalid dates.
	 */
	public function get_total_users( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT 
					COUNT(DISTINCT c.value)
				FROM 
					%i c
				JOIN 
					%i h ON h.id = c.history_id
				WHERE 
					c.key = "_user_id"
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)',
				$contexts_table,
				$events_table,
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get the last user edit action.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return object|false Last edit action details, or false if invalid dates or no actions found.
	 */
	public function get_last_edit_action( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Performance-critical stats query, WP user APIs too slow for bulk data
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT
					h.*,
					c.value as user_id,
					u.display_name
				FROM
					%i h
				JOIN
					%i c ON h.id = c.history_id
				LEFT JOIN
					' . $wpdb->users . ' u ON u.ID = CAST(c.value AS UNSIGNED)
				WHERE
					c.key = "_user_id"
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				ORDER BY
					h.date DESC
				LIMIT 1',
				$events_table,
				$contexts_table,
				$date_from,
				$date_to
			)
		);
		// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
	}

	/**
	 * Get top users by activity count,
	 * i.e. users with most actions performed, no matter what action.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @param int $limit      Optional. Number of users to return. Default 10.
	 * @return array<int,array{id:string,display_name:string,avatar:string,count:int}>|false Array of users with their activity counts, or false if invalid dates.
	 */
	public function get_top_users( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Performance-critical stats query, WP user APIs too slow for bulk data
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$users = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					c.value as user_id,
					COUNT(*) as count,
					u.display_name,
					u.user_email
				FROM
					%i c
				JOIN
					%i h ON h.id = c.history_id
				LEFT JOIN
					%i u ON u.ID = CAST(c.value AS UNSIGNED)
				WHERE
					c.key = "_user_id"
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				GROUP BY
					c.value
				ORDER BY
					count DESC
				LIMIT %d',
				$contexts_table,
				$events_table,
				$wpdb->users,
				$date_from,
				$date_to,
				$limit
			)
			// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		);

		if ( ! $users ) {
			return [];
		}

		// Format user data with avatars and proper types.
		return array_map(
			function ( $user ) {
				return [
					'id'           => $user->user_id,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
					'avatar'       => get_avatar_url( $user->user_id ),
					'count'        => (int) $user->count,
				];
			},
			$users
		);
	}

	/**
	 * Get activity overview by date.
	 * Includes empty days, so the chart is complete.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @return array|false Array of dates with their activity counts, or false if invalid dates. Dates are in MySQL format (YYYY-MM-DD).
	 */
	public function get_activity_overview_by_date( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table = $this->get_events_table_name();

		// Get WordPress timezone offset for converting dates from GMT to local timezone.
		// Database stores dates in GMT, but we need to group by dates in WordPress timezone.
		$wp_timezone       = wp_timezone();
		$wp_offset_seconds = $wp_timezone->getOffset( new \DateTime( 'now', $wp_timezone ) );

		// Use DATE_ADD with INTERVAL to convert from GMT to WordPress timezone.
		// This is more reliable than CONVERT_TZ which requires timezone tables.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					DATE(DATE_ADD(date, INTERVAL %d SECOND)) as date,
					COUNT(*) as count
				FROM
					%i
				WHERE
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY
					DATE(DATE_ADD(date, INTERVAL %d SECOND))
				ORDER BY
					date ASC',
				$wp_offset_seconds,
				$events_table,
				$date_from,
				$date_to,
				$wp_offset_seconds
			)
		);

		// Convert results to associative array with date as key.
		$activity_by_date = array();
		foreach ( $results as $row ) {
			$activity_by_date[ $row->date ] = $row;
		}

		// Create a complete date range with all days.
		$complete_range = array();
		// Create DateTime objects in WordPress timezone to ensure correct date boundaries.
		$current_date = new \DateTime( '@' . $date_from );
		$current_date->setTimezone( wp_timezone() );
		$end_date = new \DateTime( '@' . $date_to );
		$end_date->setTimezone( wp_timezone() );

		while ( $current_date <= $end_date ) {
			$date_str = $current_date->format( 'Y-m-d' );

			if ( isset( $activity_by_date[ $date_str ] ) ) {
				$complete_range[] = $activity_by_date[ $date_str ];
			} else {
				// Add empty day with zero count.
				$empty_day        = new \stdClass();
				$empty_day->date  = $date_str;
				$empty_day->count = 0;
				$complete_range[] = $empty_day;
			}

			$current_date->modify( '+1 day' );
		}

		return $complete_range;
	}

	/**
	 * Get peak activity times.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @return array|false Array of hours (0-23) with their activity counts, or false if invalid dates.
	 */
	public function get_peak_activity_times( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table = $this->get_events_table_name();

		// Get WordPress timezone offset for converting dates from GMT to local timezone.
		// Database stores dates in GMT, but we need to group by hours in WordPress timezone.
		$wp_timezone       = wp_timezone();
		$wp_offset_seconds = $wp_timezone->getOffset( new \DateTime( 'now', $wp_timezone ) );

		// Use DATE_ADD with INTERVAL to convert from GMT to WordPress timezone.
		// This is more reliable than CONVERT_TZ which requires timezone tables.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					HOUR(DATE_ADD(date, INTERVAL %d SECOND)) as hour,
					COUNT(*) as count
				FROM
					%i
				WHERE
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY
					HOUR(DATE_ADD(date, INTERVAL %d SECOND))
				ORDER BY
					hour ASC',
				$wp_offset_seconds,
				$events_table,
				$date_from,
				$date_to,
				$wp_offset_seconds
			)
		);

		// Add human readable time spans to each result.
		foreach ( $results as $result ) {
			$hour              = (int) $result->hour;
			$result->time_span = sprintf( '%02d:00-%02d:59', $hour, $hour );
		}

		return $results;
	}

	/**
	 * Get peak days of the week.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @return array|false Array of weekdays (0-6, Sunday-Saturday) with their activity counts, or false if invalid dates.
	 */
	public function get_peak_days( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table = $this->get_events_table_name();

		// Get WordPress timezone offset for converting dates from GMT to local timezone.
		// Database stores dates in GMT, but we need to group by dates in WordPress timezone.
		$wp_timezone       = wp_timezone();
		$wp_offset_seconds = $wp_timezone->getOffset( new \DateTime( 'now', $wp_timezone ) );

		// Use DATE_ADD with INTERVAL to convert from GMT to WordPress timezone.
		// This is more reliable than CONVERT_TZ which requires timezone tables.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					DAYOFWEEK(DATE_ADD(date, INTERVAL %d SECOND)) - 1 as day,
					COUNT(*) as count
				FROM
					%i
				WHERE
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY
					DAYOFWEEK(DATE_ADD(date, INTERVAL %d SECOND))
				ORDER BY
					day ASC',
				$wp_offset_seconds,
				$events_table,
				$date_from,
				$date_to,
				$wp_offset_seconds
			)
		);

		// Add day names to the results.
		// No domain for translation because we are reusing the WordPress core translations.
		// phpcs:disable WordPress.WP.I18n.MissingArgDomain
		$day_names = array(
			0 => __( 'Sunday' ),
			1 => __( 'Monday' ),
			2 => __( 'Tuesday' ),
			3 => __( 'Wednesday' ),
			4 => __( 'Thursday' ),
			5 => __( 'Friday' ),
			6 => __( 'Saturday' ),
		);
		// phpcs:enable WordPress.WP.I18n.MissingArgDomain

		foreach ( $results as $result ) {
			$result->day_name = $day_names[ $result->day ];
		}

		return $results;
	}

	/**
	 * Get number of failed logins for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of failed logins, or false if invalid dates.
	 */
	public function get_failed_logins_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleUserLogger', [ 'user_login_failed', 'user_unknown_login_failed' ], $date_from, $date_to );
	}

	/**
	 * Get number of users added for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of users added, or false if invalid dates.
	 */
	public function get_user_added_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleUserLogger', 'user_created', $date_from, $date_to );
	}

	/**
	 * Get number of users removed for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of users removed, or false if invalid dates.
	 */
	public function get_user_removed_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleUserLogger', 'user_deleted', $date_from, $date_to );
	}

	/**
	 * Get number of users updated for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of users updated, or false if invalid dates.
	 */
	public function get_user_updated_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleUserLogger', 'user_updated_profile', $date_from, $date_to );
	}

	/**
	 * Get successful logins count in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of successful logins, or false if invalid dates.
	 */
	public function get_successful_logins_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleUserLogger', [ 'user_logged_in', 'user_unknown_logged_in' ], $date_from, $date_to );
	}

	/**
	 * Get number of WordPress core updates found for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of core updates found, or false if invalid dates.
	 */
	public function get_wordpress_core_updates_found( $date_from, $date_to ) {
		return $this->get_event_count( 'AvailableUpdatesLogger', 'core_update_available', $date_from, $date_to );
	}

	/**
	 * Get number of available plugin updates.
	 *
	 * @return int Number of available plugin updates.
	 */
	public function get_available_plugin_updates() {
		$update_data = wp_get_update_data();
		return isset( $update_data['counts']['plugins'] ) ? (int) $update_data['counts']['plugins'] : 0;
	}

	/**
	 * Get details of plugins based on action type.
	 *
	 * @param string $action_type Type of action ('updated', 'deleted', 'activated', 'deactivated', 'plugin_update_available').
	 * @param int    $date_from   Start date as Unix timestamp.
	 * @param int    $date_to     End date as Unix timestamp.
	 * @param int    $limit       Optional. Number of plugins to return. Default 5.
	 * @return array Array of plugin details.
	 */
	public function get_plugin_details( $action_type, $date_from, $date_to, $limit = 5 ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return array();
		}

		$message_keys = array();
		$logger_slug  = 'SimplePluginLogger';
		$name_key     = 'plugin_name';
		$version_key  = 'plugin_version';

		switch ( $action_type ) {
			case 'updated':
				$message_keys = array( 'plugin_updated', 'plugin_bulk_updated' );
				break;
			case 'deleted':
				$message_keys = array( 'plugin_deleted' );
				break;
			case 'activated':
				$message_keys = array( 'plugin_activated' );
				break;
			case 'deactivated':
				$message_keys = array( 'plugin_deactivated' );
				break;
			case 'installed':
				$message_keys = array( 'plugin_installed' );
				break;
			case 'plugin_update_available':
				$message_keys = array( 'plugin_update_available' );
				$logger_slug  = 'AvailableUpdatesLogger';
				$name_key     = 'plugin_name';
				$version_key  = 'plugin_new_version';
				break;
			default:
				return [];
		}

		// Prepare the query parts for safe execution.
		$where_in       = implode( ',', array_fill( 0, count( $message_keys ), '%s' ) );
		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		$sql = $wpdb->prepare(
			'SELECT 
				h.date,
				c1.value as plugin_name,
				c2.value as plugin_version
			FROM 
				%i h
			JOIN 
				%i c ON h.id = c.history_id
			JOIN 
				%i c1 ON h.id = c1.history_id
			LEFT JOIN 
				%i c2 ON h.id = c2.history_id
			WHERE 
				h.logger = %s
				AND c.key = %s
				AND c1.key = %s
				AND c2.key = %s
				AND h.date >= FROM_UNIXTIME(%d)
				AND h.date <= FROM_UNIXTIME(%d)',
			$events_table,
			$contexts_table,
			$contexts_table,
			$contexts_table,
			$logger_slug,
			'_message_key',
			$name_key,
			$version_key,
			$date_from,
			$date_to
		);

		// Add the IN clause and limit safely.
		$sql .= " AND c.value IN ($where_in) ORDER BY h.date DESC LIMIT %d";
		
		// Prepare the complete query with all parameters.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				array_merge(
					$message_keys,
					array( $limit )
				)
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$plugins = array();
		foreach ( $results as $result ) {
			$plugins[] = array(
				'name'    => $result->plugin_name,
				'version' => $result->plugin_version,
				'when'    => sprintf(
					/* translators: %s last modified date and time in human time diff-format */
					__( '%1$s ago', 'simple-history' ),
					human_time_diff( strtotime( $result->date ), time() )
				),
			);
		}

		return $plugins;
	}

	/**
	 * Get list of plugins that have updates available.
	 *
	 * @return array Array of plugins with updates.
	 */
	public function get_plugins_with_updates() {
		$plugins        = array();
		$update_plugins = get_site_transient( 'update_plugins' );

		if ( ! empty( $update_plugins->response ) ) {
			foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
				$plugin_info = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
				$plugins[]   = array(
					'name'            => $plugin_info['Name'],
					'current_version' => $plugin_info['Version'],
					'new_version'     => $plugin_data->new_version,
				);
			}
		}

		return $plugins;
	}

	/**
	 * Get number of posts and pages created in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of posts and pages created, or false if invalid dates.
	 */
	public function get_posts_pages_created( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePostLogger', 'post_created', $date_from, $date_to );
	}

	/**
	 * Get number of posts and pages updated in a given period.
	 * This is the number of edits, not the number of posts and pages updated.
	 * So same post can be updated multiple times.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of posts and pages updated, or false if invalid dates.
	 */
	public function get_posts_pages_updated( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePostLogger', 'post_updated', $date_from, $date_to );
	}

	/**
	 * Get number of posts and pages deleted in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of posts and pages deleted, or false if invalid dates.
	 */
	public function get_posts_pages_deleted( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePostLogger', 'post_deleted', $date_from, $date_to );
	}

	/**
	 * Get number of posts and pages trashed in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of posts and pages trashed, or false if invalid dates.
	 */
	public function get_posts_pages_trashed( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePostLogger', 'post_trashed', $date_from, $date_to );
	}

	/**
	 * Get most edited posts and pages in a given period.
	 * An edits post = message key is any of:
	 * post_created, post_updated, post_restored, post_deleted, post_trashed
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @param int $limit     Optional. Number of posts to return. Default 5.
	 * @return array|false Array of most edited posts with their edit counts, or false if invalid dates.
	 */
	public function get_most_edited_posts( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					c2.value as post_title,
					c3.value as post_id,
					COUNT(*) as edit_count
				FROM 
					%i h
				JOIN 
					%i c ON h.id = c.history_id
				JOIN 
					%i c2 ON h.id = c2.history_id
				JOIN 
					%i c3 ON h.id = c3.history_id
				WHERE 
					h.logger = 'SimplePostLogger'
					AND c.key = '_message_key'
					AND c.value IN ('post_created', 'post_updated', 'post_restored', 'post_deleted', 'post_trashed')
					AND c2.key = 'post_title'
					AND c3.key = 'post_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				GROUP BY 
					c2.value, c3.value
				ORDER BY 
					edit_count DESC
				LIMIT %d",
				$events_table,
				$contexts_table,
				$contexts_table,
				$contexts_table,
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get number of media uploads in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of media uploads, or false if invalid dates.
	 */
	public function get_media_uploads( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleMediaLogger', 'attachment_created', $date_from, $date_to );
	}

	/**
	 * Get media uploads count in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of media uploads, or false if invalid dates.
	 */
	public function get_media_uploads_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleMediaLogger', 'attachment_created', $date_from, $date_to );
	}

	/**
	 * Get media edits count in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of media edits, or false if invalid dates.
	 */
	public function get_media_edits_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleMediaLogger', 'attachment_updated', $date_from, $date_to );
	}

	/**
	 * Get media deletions count in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of media deletions, or false if invalid dates.
	 */
	public function get_media_deletions_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleMediaLogger', 'attachment_deleted', $date_from, $date_to );
	}

	/**
	 * Get plugin updates count in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin updates, or false if invalid dates.
	 */
	public function get_plugin_updates_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePluginLogger', [ 'plugin_updated', 'plugin_bulk_updated' ], $date_from, $date_to );
	}

	/**
	 * Get number of plugin installations for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin installations, or false if invalid dates.
	 */
	public function get_plugin_installs_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePluginLogger', 'plugin_installed', $date_from, $date_to );
	}

	/**
	 * Get number of plugin deletions for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin deletions, or false if invalid dates.
	 */
	public function get_plugin_deletions_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePluginLogger', 'plugin_deleted', $date_from, $date_to );
	}

	/**
	 * Get number of plugin activations count for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin activations, or false if invalid dates.
	 */
	public function get_plugin_activations_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePluginLogger', 'plugin_activated', $date_from, $date_to );
	}

	/**
	 * Get number of plugin deactivations count for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin deactivations, or false if invalid dates.
	 */
	public function get_plugin_deactivations_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimplePluginLogger', 'plugin_deactivated', $date_from, $date_to );
	}

	/**
	 * Get number of plugin updates found for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin updates found, or false if invalid dates.
	 */
	public function get_plugin_updates_found( $date_from, $date_to ) {
		return $this->get_event_count( 'AvailableUpdatesLogger', 'plugin_update_available', $date_from, $date_to );
	}

	/**
	 * Get number of plugin updates found count for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin updates found, or false if invalid dates.
	 */
	public function get_plugin_updates_found_count( $date_from, $date_to ) {
		return $this->get_event_count( 'AvailableUpdatesLogger', 'plugin_update_available', $date_from, $date_to );
	}

	/**
	 * Get number of WordPress core updates for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of core updates, or false if invalid dates.
	 */
	public function get_wordpress_core_updates_count( $date_from, $date_to ) {
		return $this->get_event_count( 'SimpleCoreUpdatesLogger', [ 'core_updated', 'core_auto_updated' ], $date_from, $date_to );
	}

	/**
	 * Get number of WordPress core updates found for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of core updates found, or false if invalid dates.
	 */
	public function get_wordpress_core_updates_found_count( $date_from, $date_to ) {
		return $this->get_event_count( 'AvailableUpdatesLogger', 'core_update_available', $date_from, $date_to );
	}

	/**
	 * Get detailed user activity statistics.
	 *
	 * @param int  $date_from Start date timestamp.
	 * @param int  $date_to End date timestamp.
	 * @param int  $limit Optional. Number of entries per section. Default 50.
	 * @param bool $include_ip Optional. Whether to include IP addresses. Default false.
	 * @return array Array of detailed user activity stats.
	 */
	public function get_detailed_user_stats( $date_from, $date_to, $limit = 50, $include_ip = false ) {
		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return array(
			'successful_logins' => $this->get_successful_logins_details( $date_from, $date_to, $limit ),
			'failed_logins'     => $this->get_failed_logins_details( $date_from, $date_to, $limit, $include_ip ),
			'profile_updates'   => $this->get_profile_updates_details( $date_from, $date_to, $limit ),
			'added_users'       => $this->get_added_users_details( $date_from, $date_to, $limit ),
			'removed_users'     => $this->get_removed_users_details( $date_from, $date_to, $limit ),
		);
	}

	/**
	 * Get detailed successful login statistics.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of successful login details.
	 */
	public function get_successful_logins_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date,
					c.value as user_id,
					c2.value as user_login,
					c3.value as user_email,
					COUNT(*) as login_count
				FROM 
					%i h
				JOIN 
					%i c ON h.id = c.history_id
				JOIN 
					%i c2 ON h.id = c2.history_id
				JOIN 
					%i c3 ON h.id = c3.history_id
				WHERE 
					h.logger = 'SimpleUserLogger'
					AND c.key = '_user_id'
					AND c2.key = 'user_login'
					AND c3.key = 'user_email'
					AND h.date >= FROM_UNIXTIME(%d)
					# Placeholder 6 below
					AND h.date <= FROM_UNIXTIME(%d)
					# Exists check that the message key is either user_logged_in or user_unknown_logged_in
					AND EXISTS (
						SELECT 1 
						FROM %i c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value IN ('user_logged_in', 'user_unknown_logged_in')
					)
				GROUP BY 
					c.value
				ORDER BY 
					login_count DESC
				LIMIT %d",
				$events_table,
				$contexts_table,
				$contexts_table,
				$contexts_table, // 4
				$date_from, // 5
				$date_to, // 6
				$contexts_table, // 7
				$limit // 8
			)
		);
	}

	/**
	 * Get detailed failed login statistics.
	 *
	 * @param int  $date_from Start date timestamp.
	 * @param int  $date_to End date timestamp.
	 * @param int  $limit Number of entries to return.
	 * @param bool $include_ip Whether to include IP addresses.
	 * @return array Array of failed login details.
	 */
	public function get_failed_logins_details( $date_from, $date_to, $limit, $include_ip ) {
		global $wpdb;

		if ( $include_ip ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						h.date,
						c.value as attempted_username,
						c2.value as ip_address,
						COUNT(*) as failed_count
					FROM 
						{$wpdb->prefix}simple_history h
					JOIN 
						{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
					LEFT JOIN 
						{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id AND c2.key = 'server_remote_addr'
					WHERE 
						h.logger = 'SimpleUserLogger'
						AND c.key IN ('login', 'failed_username')
						AND h.date >= FROM_UNIXTIME(%d)
						AND h.date <= FROM_UNIXTIME(%d)
						AND EXISTS (
							SELECT 1 
							FROM {$wpdb->prefix}simple_history_contexts c_msg 
							WHERE c_msg.history_id = h.id 
							AND c_msg.key = '_message_key'
							AND c_msg.value IN ('user_login_failed', 'user_unknown_login_failed')
						)
					GROUP BY 
						c.value
					ORDER BY 
						failed_count DESC
					LIMIT %d",
					$date_from,
					$date_to,
					$limit
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date,
					c.value as attempted_username,
					COUNT(*) as failed_count
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				WHERE 
					h.logger = 'SimpleUserLogger'
					AND c.key IN ('login', 'failed_username')
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value IN ('user_login_failed', 'user_unknown_login_failed')
					)
				GROUP BY 
					c.value
				ORDER BY 
					failed_count DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed profile update statistics.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of profile update details.
	 */
	public function get_profile_updates_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date,
					c.value as user_id,
					c2.value as user_login,
					c3.value as user_email,
					COUNT(*) as update_count
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c3 ON h.id = c3.history_id
				WHERE 
					h.logger = 'SimpleUserLogger'
					AND c.key = '_user_id'
					AND c2.key = 'edited_user_login' 
					AND c3.key = 'edited_user_email'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value = 'user_updated_profile'
					)
				GROUP BY 
					c.value
				ORDER BY 
					update_count DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed statistics about added users.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of added user details.
	 */
	public function get_added_users_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date,
					c.value as user_id,
					c2.value as user_login,
					c3.value as user_email,
					c4.value as user_role,
					c5.value as added_by_id
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c3 ON h.id = c3.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c4 ON h.id = c4.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c5 ON h.id = c5.history_id
				WHERE 
					h.logger = 'SimpleUserLogger'
					AND c.key = 'created_user_id'
					AND c2.key = 'created_user_login'
					AND c3.key = 'created_user_email'
					AND c4.key = 'created_user_role'
					AND c5.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value = 'user_created'
					)
				ORDER BY 
					h.date DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed statistics about removed users.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of removed user details.
	 */
	public function get_removed_users_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date,
					c.value as user_login,
					c2.value as user_email,
					c3.value as removed_by_id
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c3 ON h.id = c3.history_id
				WHERE 
					h.logger = 'SimpleUserLogger'
					AND c.key = 'deleted_user_login'
					AND c2.key = 'deleted_user_email'
					AND c3.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value = 'user_deleted'
					)
				ORDER BY 
					h.date DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed content item statistics.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Optional. Number of entries per section. Default 50.
	 * @return array Array of detailed content item stats.
	 */
	public function get_detailed_content_stats( $date_from, $date_to, $limit = 50 ) {
		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return array(
			'content_items_created_details' => $this->get_content_created_details( $date_from, $date_to, $limit ),
			'content_items_updated_details' => $this->get_content_updated_details( $date_from, $date_to, $limit ),
			'content_items_trashed_details' => $this->get_content_trashed_details( $date_from, $date_to, $limit ),
			'content_items_deleted_details' => $this->get_content_deleted_details( $date_from, $date_to, $limit ),
		);
	}

	/**
	 * Get detailed statistics about created content items.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of created content details.
	 */
	protected function get_content_created_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date as created_date,
					c.value as post_id,
					c2.value as post_title,
					c3.value as post_type,
					c4.value as created_by_id
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c3 ON h.id = c3.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c4 ON h.id = c4.history_id
				WHERE 
					h.logger = 'SimplePostLogger'
					AND c.key = 'post_id'
					AND c2.key = 'post_title'
					AND c3.key = 'post_type'
					AND c4.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value = 'post_created'
					)
				ORDER BY 
					h.date DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed statistics about updated content items.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of updated content details.
	 */
	protected function get_content_updated_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date as updated_date,
					c.value as post_id,
					c2.value as post_title,
					c3.value as post_type,
					c4.value as updated_by_id
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c3 ON h.id = c3.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c4 ON h.id = c4.history_id
				WHERE 
					h.logger = 'SimplePostLogger'
					AND c.key = 'post_id'
					AND c2.key = 'post_title'
					AND c3.key = 'post_type'
					AND c4.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value = 'post_updated'
					)
				ORDER BY 
					h.date DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed statistics about trashed content items.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of trashed content details.
	 */
	protected function get_content_trashed_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					h.date as trashed_date,
					c.value as post_id,
					c2.value as post_title,
					c3.value as post_type,
					c4.value as trashed_by_id
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c3 ON h.id = c3.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c4 ON h.id = c4.history_id
				WHERE 
					h.logger = 'SimplePostLogger'
					AND c.key = 'post_id'
					AND c2.key = 'post_title'
					AND c3.key = 'post_type'
					AND c4.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value = 'post_trashed'
					)
				ORDER BY 
					h.date DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed statistics about deleted content items.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @param int $limit Number of entries to return.
	 * @return array Array of deleted content details.
	 */
	protected function get_content_deleted_details( $date_from, $date_to, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare(
				"SELECT 
					h.date as deleted_date,
					c.value as post_id,
					c2.value as post_title,
					c3.value as post_type,
					c4.value as deleted_by_id
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c3 ON h.id = c3.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c4 ON h.id = c4.history_id
				WHERE 
					h.logger = 'SimplePostLogger'
					AND c.key = 'post_id'
					AND c2.key = 'post_title'
					AND c3.key = 'post_type'
					AND c4.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
					AND EXISTS (
						SELECT 1 
						FROM {$wpdb->prefix}simple_history_contexts c_msg 
						WHERE c_msg.history_id = h.id 
						AND c_msg.key = '_message_key'
						AND c_msg.value = 'post_deleted'
					)
				ORDER BY 
					h.date DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get detailed information about media uploads.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return array|false Array of detailed media upload events, or false if invalid dates.
	 */
	public function get_media_uploaded_details( $date_from, $date_to ) {
		return $this->get_detailed_stats_for_logger_and_value( 'SimpleMediaLogger', '_message_key', 'attachment_created', $date_from, $date_to );
	}

	/**
	 * Get detailed information about media edits.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return array|false Array of detailed media edit events, or false if invalid dates.
	 */
	public function get_media_edited_details( $date_from, $date_to ) {
		return $this->get_detailed_stats_for_logger_and_value( 'SimpleMediaLogger', '_message_key', 'attachment_updated', $date_from, $date_to );
	}

	/**
	 * Get detailed information about media deletions.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return array|false Array of detailed media deletion events, or false if invalid dates.
	 */
	public function get_media_deleted_details( $date_from, $date_to ) {
		return $this->get_detailed_stats_for_logger_and_value( 'SimpleMediaLogger', '_message_key', 'attachment_deleted', $date_from, $date_to );
	}

	/**
	 * Get detailed stats for a specific logger and message key value.
	 *
	 * @param string $logger_slug  The logger slug (e.g. 'SimpleMediaLogger').
	 * @param string $message_key  The context key to match (e.g. '_message_key').
	 * @param string $message_value The value to match against.
	 * @param int    $date_from    Start timestamp.
	 * @param int    $date_to      End timestamp.
	 * @return array Array of detailed stats.
	 */
	protected function get_detailed_stats_for_logger_and_value( $logger_slug, $message_key, $message_value, $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$events_table   = $this->get_events_table_name();
		$contexts_table = $this->get_contexts_table_name();

		// First query: Get matching history entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$history_results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT 
						h.*
					FROM 
						%i h
					JOIN 
						%i c ON h.id = c.history_id
					WHERE 
						h.logger = %s
						AND c.key = %s
						AND c.value = %s
						AND h.date >= FROM_UNIXTIME(%d)
						AND h.date <= FROM_UNIXTIME(%d)
					ORDER BY 
						h.date DESC',
				$events_table,
				$contexts_table,
				$logger_slug,
				$message_key,
				$message_value,
				$date_from,
				$date_to
			)
		);

		if ( empty( $history_results ) ) {
			return array();
		}

		// Get all history IDs.
		$history_ids = wp_list_pluck( $history_results, 'id' );

		// Generate placeholders for the history IDs.
		// This will be a string that looks like:
		// "%d, %d, %d, %d, %d, %d, %d, %d, %d, %d" and so on.
		// This is used to prepare the SQL query.
		$history_ids_placeholders = implode( ',', array_fill( 0, count( $history_ids ), '%d' ) );

		// Second query: Get all context data for these history entries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$context_results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT 
						history_id,
						`key`,
						value
					FROM 
						%i 
					WHERE 
						history_id IN ($history_ids_placeholders)",
				array_merge( [ $contexts_table ], $history_ids )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Combine the results.
		$context_by_history_id = array();
		foreach ( $context_results as $context ) {
			if ( ! isset( $context_by_history_id[ $context->history_id ] ) ) {
				$context_by_history_id[ $context->history_id ] = array();
			}
			$context_by_history_id[ $context->history_id ][ $context->key ] = $context->value;
		}

		// Add context data to history entries.
		foreach ( $history_results as $history ) {
			$history->context = isset( $context_by_history_id[ $history->id ] )
				? $context_by_history_id[ $history->id ]
				: array();
		}

		return $history_results;
	}

	/**
	 * Get total count of plugin events (updates, bulk updates, installations, deletions, activations, deactivations).
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total count of plugin events, or false if invalid dates.
	 */
	public function get_plugin_total_count( $date_from, $date_to ) {
		$plugin_events = [
			'plugin_updated',
			'plugin_bulk_updated',
			'plugin_installed',
			'plugin_deleted',
			'plugin_activated',
			'plugin_deactivated',
			'plugin_update_available',
		];

		// Get counts from both loggers since plugin update available events are logged in AvailableUpdatesLogger.
		$simple_plugin_logger_count     = $this->get_event_count( 'SimplePluginLogger', $plugin_events, $date_from, $date_to );
		$available_updates_logger_count = $this->get_event_count( 'AvailableUpdatesLogger', [ 'plugin_update_available' ], $date_from, $date_to );

		return $simple_plugin_logger_count + $available_updates_logger_count;
	}

	/**
	 * Get total count of user events (logins, failed logins, profile updates, user creation/deletion).
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total count of user events, or false if invalid dates.
	 */
	public function get_user_total_count( $date_from, $date_to ) {
		$user_events = [
			'user_logged_in',
			'user_unknown_logged_in',
			'user_login_failed',
			'user_unknown_login_failed',
			'user_updated_profile',
			'user_created',
			'user_deleted',
		];
		return $this->get_event_count( 'SimpleUserLogger', $user_events, $date_from, $date_to );
	}

	/**
	 * Get total count of content events (posts/pages created, updated, trashed, deleted).
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total count of content events, or false if invalid dates.
	 */
	public function get_content_total_count( $date_from, $date_to ) {
		$content_events = [
			'post_created',
			'post_updated',
			'post_trashed',
			'post_deleted',
			'post_restored',
		];
		return $this->get_event_count( 'SimplePostLogger', $content_events, $date_from, $date_to );
	}

	/**
	 * Get total count of media events (uploads, edits, deletions).
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total count of media events, or false if invalid dates.
	 */
	public function get_media_total_count( $date_from, $date_to ) {
		$media_events = [
			'attachment_created',
			'attachment_updated',
			'attachment_deleted',
		];
		return $this->get_event_count( 'SimpleMediaLogger', $media_events, $date_from, $date_to );
	}

	/**
	 * Get total count of core update events.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total count of core update events, or false if invalid dates.
	 */
	public function get_core_total_count( $date_from, $date_to ) {
		$core_events = [
			'core_updated',
			'core_auto_updated',
			'core_update_available',
		];
		return $this->get_event_count( 'SimpleCoreUpdatesLogger', $core_events, $date_from, $date_to );
	}

	/**
	 * Get the oldest event.
	 *
	 * @return array|false Array of oldest event, or false if no events.
	 */
	public function get_oldest_event() {
		global $wpdb;

		$events_table = $this->get_events_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY date ASC LIMIT 1',
				$events_table
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			return false;
		}

		return $results[0];
	}

	/**
	 * Get the number of events today.
	 * Uses log_query so it respects the user's permissions,
	 * meaning that the number of events is the number
	 * of events that the current user is allowed to see.
	 *
	 * @return int
	 */
	public static function get_num_events_today() {
		$logQuery = new Log_Query();

		$logResults = $logQuery->query(
			array(
				'posts_per_page' => 1,
				'date_from'      => Date_Helper::get_today_start_timestamp(),
			)
		);

		if ( is_wp_error( $logResults ) ) {
			return 0;
		}

		return (int) $logResults['total_row_count'];
	}
}
