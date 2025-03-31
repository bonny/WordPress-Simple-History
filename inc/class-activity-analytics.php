<?php

namespace Simple_History;

use WP_Session_Tokens;

/**
 * Class that handles stats functionality for Simple History insights.
 */
class Activity_Analytics {
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
					'user' => get_userdata( $one_user_id ),
					'sessions_count' => count( $all_user_sessions ),
					'sessions' => $all_user_sessions,
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

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(*)
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)",
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

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(DISTINCT c.value)
				FROM 
					{$wpdb->prefix}simple_history_contexts c
				JOIN 
					{$wpdb->prefix}simple_history h ON h.id = c.history_id
				WHERE 
					c.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)",
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

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					h.*,
					c.value as user_id,
					u.display_name
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				LEFT JOIN 
					{$wpdb->users} u ON u.ID = CAST(c.value AS UNSIGNED)
				WHERE 
					c.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				ORDER BY 
					h.date DESC
				LIMIT 1",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get top users by activity count.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @param int $limit      Optional. Number of users to return. Default 10.
	 * @return array|false Array of users with their activity counts, or false if invalid dates.
	 */
	public function get_top_users( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					c.value as user_id,
					COUNT(*) as count,
					u.display_name
				FROM 
					{$wpdb->prefix}simple_history_contexts c
				JOIN 
					{$wpdb->prefix}simple_history h ON h.id = c.history_id
				LEFT JOIN 
					{$wpdb->users} u ON u.ID = CAST(c.value AS UNSIGNED)
				WHERE 
					c.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				GROUP BY 
					c.value
				ORDER BY 
					count DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get activity overview by date.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @return array|false Array of dates with their activity counts, or false if invalid dates. Dates are in MySQL format (YYYY-MM-DD).
	 */
	public function get_activity_overview( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DATE(date) as date,
					COUNT(*) as count
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY 
					DATE(date)
				ORDER BY 
					date ASC",
				$date_from,
				$date_to
			)
		);
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

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					HOUR(date) as hour,
					COUNT(*) as count
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY 
					HOUR(date)
				ORDER BY 
					hour ASC",
				$date_from,
				$date_to
			)
		);
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

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DAYOFWEEK(date) - 1 as day,
					COUNT(*) as count
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY 
					DAYOFWEEK(date)
				ORDER BY 
					day ASC",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get number of failed logins for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of failed logins, or false if invalid dates.
	 */
	public function get_failed_logins( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(*)
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				WHERE 
					h.logger = 'SimpleUserLogger'
					AND c.key = '_message_key'
					AND (
						c.value = 'user_login_failed'
						OR c.value = 'user_unknown_login_failed'
					)
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get number of users added for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of users added, or false if invalid dates.
	 */
	public function get_users_added( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimpleUserLogger', '_message_key', 'user_created', $date_from, $date_to );
	}

	/**
	 * Get number of users removed for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of users removed, or false if invalid dates.
	 */
	public function get_users_removed( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimpleUserLogger', '_message_key', 'user_deleted', $date_from, $date_to );
	}

	/**
	 * Get number of users updated for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of users updated, or false if invalid dates.
	 */
	public function get_users_updated( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimpleUserLogger', '_message_key', 'user_updated_profile', $date_from, $date_to );
	}

	/**
	 * Get number of successful logins for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of successful logins, or false if invalid dates.
	 */
	public function get_successful_logins( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(*)
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				WHERE 
					h.logger = 'SimpleUserLogger'
					AND c.key = '_message_key'
					AND (
						c.value = 'user_logged_in'
						OR c.value = 'user_unknown_logged_in'
					)
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get user's last activity time.
	 *
	 * @param int $user_id User ID to get last activity for.
	 * @return string|false MySQL datetime string of last activity, or false if no activity found.
	 */
	public function get_user_last_activity( $user_id ) {
		global $wpdb;

		if ( ! $user_id ) {
			return false;
		}

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					h.date
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				WHERE 
					c.key = '_user_id'
					AND c.value = %s
				ORDER BY 
					h.date DESC
				LIMIT 1",
				$user_id
			)
		);
	}

	/**
	 * Get number of WordPress core updates for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of core updates, or false if invalid dates.
	 */
	public function get_wordpress_core_updates( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(*)
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				WHERE 
					h.logger = 'SimpleCoreUpdatesLogger'
					AND c.key = '_message_key'
					AND (
						c.value = 'core_updated'
						OR c.value = 'core_auto_updated'
					)
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get number of plugin updates for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin updates, or false if invalid dates.
	 */
	public function get_plugin_updates( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(*)
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				WHERE 
					h.logger = 'SimplePluginLogger'
					AND c.key = '_message_key'
					AND (
						c.value = 'plugin_updated'
						OR c.value = 'plugin_bulk_updated'
					)
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get number of plugin installations for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin installations, or false if invalid dates.
	 */
	public function get_plugin_installs( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimplePluginLogger', '_message_key', 'plugin_installed', $date_from, $date_to );
	}

	/**
	 * Get number of plugin deletions for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of plugin deletions, or false if invalid dates.
	 */
	public function get_plugin_deletions( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimplePluginLogger', '_message_key', 'plugin_deleted', $date_from, $date_to );
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
	 * Get number of posts and pages created in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of posts and pages created, or false if invalid dates.
	 */
	public function get_posts_pages_created( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimplePostLogger', '_message_key', 'post_created', $date_from, $date_to );
	}

	/**
	 * Get number of posts and pages updated in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of posts and pages updated, or false if invalid dates.
	 */
	public function get_posts_pages_updated( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimplePostLogger', '_message_key', 'post_updated', $date_from, $date_to );
	}

	/**
	 * Get number of posts and pages deleted in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of posts and pages deleted, or false if invalid dates.
	 */
	public function get_posts_pages_deleted( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimplePostLogger', '_message_key', 'post_deleted', $date_from, $date_to );
	}

	/**
	 * Get most edited posts and pages in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @param int $limit     Optional. Number of posts to return. Default 5.
	 * @return array|false Array of most edited posts with their edit counts, or false if invalid dates.
	 */
	public function get_most_edited_posts( $date_from, $date_to, $limit = 5 ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					c2.value as post_title,
					COUNT(*) as edit_count
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				JOIN 
					{$wpdb->prefix}simple_history_contexts c2 ON h.id = c2.history_id
				WHERE 
					h.logger = 'SimplePostLogger'
					AND c.key = '_message_key'
					AND c.value = 'post_updated'
					AND c2.key = 'post_title'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				GROUP BY 
					c2.value
				ORDER BY 
					edit_count DESC
				LIMIT %d",
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
		return $this->get_stats_for_logger_and_value( 'SimpleMediaLogger', '_message_key', 'attachment_created', $date_from, $date_to );
	}

	/**
	 * Get number of media edits in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of media edits, or false if invalid dates.
	 */
	public function get_media_edits( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimpleMediaLogger', '_message_key', 'attachment_updated', $date_from, $date_to );
	}

	/**
	 * Get number of media deletions in a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Number of media deletions, or false if invalid dates.
	 */
	public function get_media_deletions( $date_from, $date_to ) {
		return $this->get_stats_for_logger_and_value( 'SimpleMediaLogger', '_message_key', 'attachment_deleted', $date_from, $date_to );
	}

	/**
	 * Get stats for a specific logger and message value.
	 *
	 * @param string $logger_slug   The logger slug (e.g. 'SimpleMediaLogger').
	 * @param string $message_key   The context key to match (e.g. '_message_key').
	 * @param string $message_value The value to match for the message key.
	 * @param int    $date_from     Required. Start date as Unix timestamp.
	 * @param int    $date_to       Required. End date as Unix timestamp.
	 * @return int|false Number of matching events, or false if invalid dates.
	 */
	protected function get_stats_for_logger_and_value( $logger_slug, $message_key, $message_value, $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(*)
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				WHERE 
					h.logger = %s
					AND c.key = %s
					AND c.value = %s
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)",
				$logger_slug,
				$message_key,
				$message_value,
				$date_from,
				$date_to
			)
		);
	}
}
