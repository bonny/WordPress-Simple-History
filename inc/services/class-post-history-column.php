<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Log_Query;
use Simple_History\Simple_History;

/**
 * Add an optional "History" column to post/page list tables
 * showing the most recent history events for each post.
 *
 * Only active when experimental features are enabled.
 */
class Post_History_Column extends Service {

	/**
	 * Cached history data per post ID.
	 * Each entry contains up to 2 recent events with date, user, and message key.
	 *
	 * @var array<int, array<int, array{date: string, message_key: string, user_id: string}>>|null
	 */
	private $history_data = null;

	/**
	 * Whether the column is hidden via Screen Options.
	 * Cached on first check to avoid repeated get_hidden_columns() calls per row.
	 *
	 * @var bool|null
	 */
	private $is_column_hidden = null;

	/** @inheritdoc */
	public function loaded() {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'register_column_hooks' ) );
		add_action( 'admin_head', array( $this, 'print_column_styles' ) );
	}

	/**
	 * Print inline CSS for the History column.
	 * Uses tr:hover and tr:focus-within so the link is accessible
	 * via both mouse and keyboard navigation.
	 */
	public function print_column_styles() {
		$screen = get_current_screen();
		if ( ! $screen || $screen->base !== 'edit' ) {
			return;
		}

		echo '<style>
			.sh-PostHistoryColumn-actions {
				padding: 2px 0 0;
				visibility: hidden;
			}
			tr:hover .sh-PostHistoryColumn-actions,
			tr:focus-within .sh-PostHistoryColumn-actions {
				visibility: visible;
			}
			.sh-PostHistoryColumn-secondary {
				color: #50575e;
			}
			.sh-PostHistoryColumn-placeholder {
				color: #a7aaad;
			}
		</style>';
	}

	/**
	 * Register column hooks for post types with admin UI.
	 * Excludes attachment which uses manage_media_columns hooks.
	 */
	public function register_column_hooks() {
		$post_types = get_post_types( array( 'show_ui' => true ), 'names' );

		foreach ( $post_types as $post_type ) {
			if ( $post_type === 'attachment' ) {
				continue;
			}

			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_column( $columns ) {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return $columns;
		}

		$columns['simple_history_activity'] = __( 'History', 'simple-history' );
		return $columns;
	}

	/**
	 * @param string $column_name The column identifier.
	 * @param int    $post_id     The post ID.
	 */
	public function render_column( $column_name, $post_id ) {
		if ( $column_name !== 'simple_history_activity' ) {
			return;
		}

		if ( $this->is_column_hidden() ) {
			echo '<span class="sh-PostHistoryColumn-placeholder">' . esc_html__( 'Enable this column and reload to show history.', 'simple-history' ) . '</span>';
			return;
		}

		$events = $this->get_post_history( $post_id );

		if ( empty( $events ) ) {
			echo '<span aria-hidden="true">—</span>';
			return;
		}

		$url = self::get_post_history_url( $post_id );

		$events_to_show = $this->filter_events_for_display( $events );

		foreach ( $events_to_show as $index => $event ) {
			if ( $index > 0 ) {
				echo '<br>';
			}

			$text = $this->format_event_text( $event );

			echo '<span' . ( $index > 0 ? ' class="sh-PostHistoryColumn-secondary"' : '' ) . '>';
			echo esc_html( $text );
			echo '</span>';
		}

		echo '<div class="sh-PostHistoryColumn-actions">';
		printf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr(
				sprintf(
					// translators: %s is the post title.
					__( 'View full history for "%s"', 'simple-history' ),
					get_the_title( $post_id )
				)
			),
			esc_html__( 'View full history', 'simple-history' )
		);
		echo '</div>';
	}

	/**
	 * Check if the column is hidden via Screen Options.
	 * Result is cached for the page load.
	 *
	 * @return bool
	 */
	private function is_column_hidden() {
		if ( $this->is_column_hidden === null ) {
			$screen                 = get_current_screen();
			$this->is_column_hidden = $screen && in_array( 'simple_history_activity', get_hidden_columns( $screen ), true );
		}

		return $this->is_column_hidden;
	}

	/**
	 * Only show the second event if it differs in action type or actor.
	 *
	 * @param array $events Array of event data.
	 * @return array Filtered events for display.
	 */
	private function filter_events_for_display( $events ) {
		if ( count( $events ) <= 1 ) {
			return $events;
		}

		if ( $events[0]['message_key'] === $events[1]['message_key'] && $events[0]['user_id'] === $events[1]['user_id'] ) {
			return array( $events[0] );
		}

		return $events;
	}

	/**
	 * @param array $event Event data with date, message_key, user_id.
	 * @return string Formatted text like "Edited 2 days ago by Pär".
	 */
	private function format_event_text( $event ) {
		// Event dates are stored in UTC; use time() (UTC) to get correct diff.
		$time_ago = human_time_diff( strtotime( $event['date'] ), time() );

		$user_display = '';
		$user_id      = (int) $event['user_id'];
		if ( $user_id > 0 ) {
			// get_userdata() uses WP's internal cache — no N+1 concern
			// when the same user appears across multiple rows.
			$user = get_userdata( $user_id );
			if ( $user ) {
				$user_display = $user->display_name;
			}
		}

		$action = $this->get_action_label( $event['message_key'] );

		$parts   = array();
		$parts[] = $action !== '' ? $action : __( 'Modified', 'simple-history' );

		// translators: %s is a human-readable time difference, e.g. "2 hours".
		$parts[] = sprintf( __( '%s ago', 'simple-history' ), $time_ago );

		if ( $user_display ) {
			// translators: %s is a user display name.
			$parts[] = sprintf( __( 'by %s', 'simple-history' ), $user_display );
		}

		return implode( ' ', $parts );
	}

	/**
	 * @param int $post_id The post ID.
	 * @return array Array of event data (up to 2 most recent).
	 */
	private function get_post_history( $post_id ) {
		if ( $this->history_data === null ) {
			$this->load_history_data();
		}

		return $this->history_data[ $post_id ] ?? array();
	}

	/**
	 * Check if a post has any history events.
	 * Used by Post_Row_Actions to share this service's batch query.
	 *
	 * @param int $post_id The post ID to check.
	 * @return bool True if the post has history events.
	 */
	public function post_has_history( $post_id ) {
		if ( $this->history_data === null ) {
			$this->load_history_data();
		}

		return isset( $this->history_data[ $post_id ] );
	}

	/**
	 * Batch query: load the 2 most recent events for each post on the current screen.
	 *
	 * Uses $wp_query->posts which contains the posts displayed in the current
	 * list table. This is reliable on edit.php screens where both services run.
	 */
	private function load_history_data() {
		global $wp_query;

		$this->history_data = array();

		if ( empty( $wp_query->posts ) ) {
			return;
		}

		$post_ids = wp_list_pluck( $wp_query->posts, 'ID' );

		if ( empty( $post_ids ) ) {
			return;
		}

		$rows = Log_Query::get_db_engine() === 'sqlite'
			? $this->query_history_sqlite( $post_ids )
			: $this->query_history_mysql( $post_ids );

		$this->history_data = $this->group_rows_by_post_id( $rows );

		// Prime the WP user cache in one query for all unique user IDs so
		// subsequent get_userdata() calls don't hit the DB per-row.
		$user_ids = array();
		foreach ( $this->history_data as $events ) {
			foreach ( $events as $event ) {
				$uid = (int) $event['user_id'];
				if ( $uid <= 0 ) {
					continue;
				}
				$user_ids[ $uid ] = true;
			}
		}

		if ( empty( $user_ids ) ) {
			return;
		}

		get_users(
			array(
				'include' => array_keys( $user_ids ),
				'fields'  => 'all',
			)
		);
	}

	/**
	 * MySQL/MariaDB query using ROW_NUMBER() window function.
	 *
	 * @param array $post_ids Array of post IDs.
	 * @return array<int, object> Database rows: post_id, date, message_key, user_id.
	 */
	private function query_history_mysql( $post_ids ) {
		global $wpdb;

		$events_table    = Simple_History::$dbtable;
		$contexts_table  = Simple_History::$dbtable_contexts;
		$id_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare(
				"SELECT post_id, date, message_key, user_id
				FROM (
					SELECT
						c.`value` AS post_id,
						h.date,
						COALESCE(mk.`value`, '') AS message_key,
						COALESCE(ui.`value`, '') AS user_id,
						ROW_NUMBER() OVER (PARTITION BY c.`value` ORDER BY h.id DESC) AS rn
					FROM %i c
					JOIN %i h ON c.history_id = h.id
					LEFT JOIN %i mk ON h.id = mk.history_id AND mk.`key` = '_message_key'
					LEFT JOIN %i ui ON h.id = ui.history_id AND ui.`key` = '_user_id'
					WHERE c.`key` = 'post_id'
					AND c.`value` IN ({$id_placeholders})
				) ranked
				WHERE rn <= 2
				ORDER BY post_id, date DESC",
				array_merge(
					array( $contexts_table, $events_table, $contexts_table, $contexts_table ),
					$post_ids
				)
			)
		);
		// phpcs:enable

		return is_array( $results ) ? $results : array();
	}

	/**
	 * SQLite-compatible query using correlated subquery instead of window functions.
	 *
	 * @param array $post_ids Array of post IDs.
	 * @return array<int, object> Database rows: post_id, date, message_key, user_id.
	 */
	private function query_history_sqlite( $post_ids ) {
		global $wpdb;

		$events_table    = Simple_History::$dbtable;
		$contexts_table  = Simple_History::$dbtable_contexts;
		$id_placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->prepare(
				"SELECT
					c.`value` AS post_id,
					h.date,
					COALESCE(mk.`value`, '') AS message_key,
					COALESCE(ui.`value`, '') AS user_id
				FROM %i c
				JOIN %i h ON c.history_id = h.id
				LEFT JOIN %i mk ON h.id = mk.history_id AND mk.`key` = '_message_key'
				LEFT JOIN %i ui ON h.id = ui.history_id AND ui.`key` = '_user_id'
				WHERE c.`key` = 'post_id'
				AND c.`value` IN ({$id_placeholders})
				AND h.id IN (
					SELECT h2.id
					FROM %i c2
					JOIN %i h2 ON c2.history_id = h2.id
					WHERE c2.`key` = 'post_id'
					AND c2.`value` = c.`value`
					ORDER BY h2.id DESC
					LIMIT 2
				)
				ORDER BY c.`value`, h.id DESC",
				array_merge(
					array( $contexts_table, $events_table, $contexts_table, $contexts_table ),
					$post_ids,
					array( $contexts_table, $events_table )
				)
			)
		);
		// phpcs:enable

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Group DB rows into the per-post structure stored on $history_data.
	 *
	 * @param array<int, object> $rows Database rows from query_history_*.
	 * @return array<int, array<int, array{date: string, message_key: string, user_id: string}>>
	 */
	private function group_rows_by_post_id( $rows ) {
		$grouped = array();

		foreach ( $rows as $row ) {
			$pid = (int) $row->post_id;

			if ( ! isset( $grouped[ $pid ] ) ) {
				$grouped[ $pid ] = array();
			}

			$grouped[ $pid ][] = array(
				'date'        => $row->date,
				'message_key' => $row->message_key,
				'user_id'     => $row->user_id,
			);
		}

		return $grouped;
	}

	/**
	 * @param string $message_key The logger message key.
	 * @return string Short action label or empty string for unrecognized keys.
	 */
	private function get_action_label( $message_key ) {
		$labels = array(
			'post_created'        => __( 'Created', 'simple-history' ),
			'post_updated'        => __( 'Edited', 'simple-history' ),
			'post_trashed'        => __( 'Trashed', 'simple-history' ),
			'post_deleted'        => __( 'Deleted', 'simple-history' ),
			'post_restored'       => __( 'Restored', 'simple-history' ),
			'post_status_changed' => __( 'Status changed', 'simple-history' ),
		);

		return $labels[ $message_key ] ?? '';
	}

	/**
	 * Build a URL to the history page filtered by a specific post ID,
	 * with message types scoped to SimplePostLogger events.
	 *
	 * @param int $post_id The post ID to filter by.
	 * @return string Full admin URL with all filter parameters.
	 */
	public static function get_post_history_url( $post_id ) {
		return Helpers::get_filtered_history_url(
			array(
				'context'      => 'post_id:' . $post_id,
				'show_filters' => true,
				'date'         => 'allDates',
				'messages'     => array(
					array(
						// Display-only label; frontend uses search_options for filtering.
						'value'          => _x( 'All posts & pages activity', 'Post logger: search', 'simple-history' ),
						'search_options' => array(
							'SimplePostLogger:post_created',
							'SimplePostLogger:post_updated',
							'SimplePostLogger:post_trashed',
							'SimplePostLogger:post_deleted',
							'SimplePostLogger:post_restored',
						),
					),
				),
			)
		);
	}
}
