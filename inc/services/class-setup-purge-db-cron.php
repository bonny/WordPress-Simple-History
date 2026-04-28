<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Setup a wp-cron job that daily checks if the database should be cleared.
 */
class Setup_Purge_DB_Cron extends Service {
	/**
	 * Site-transient key used to dedupe the network purge across the
	 * network. The transient lives for one day so a second cron tick
	 * within the same window can't re-run the network purge.
	 */
	private const NETWORK_PURGE_LOCK_TRANSIENT = 'simple_history_network_purge_lock';

	/** @inheritdoc */
	public function loaded() {
		add_action( 'after_setup_theme', array( $this, 'setup_cron' ) );

		// phpcs:disable
		// Uncomment the next lines to force add the events purged message (without actually purging the db).
		// add_action(
		// 	'init',
		// 	function () {
		// 		do_action( 'simple_history/db/events_purged', 60, 5000 );
		// 	}
		// );
		// phpcs:enable
	}

	/**
	 * Setup a wp-cron job that daily checks if the database should be cleared.
	 */
	public function setup_cron() {
		add_action( 'simple_history/maybe_purge_db', array( $this, 'maybe_purge_db' ) );

		if ( wp_next_scheduled( 'simple_history/maybe_purge_db' ) ) {
			return;
		}

		wp_schedule_event( time(), 'daily', 'simple_history/maybe_purge_db' );
	}

	/**
	 * Runs the purge_db() method sometimes.
	 *
	 * Fired from action `simple_history/maybe_purge_db``
	 * that is scheduled to run once a day.
	 *
	 * The db is purged only on Sundays by default,
	 * this is to keep the history clean. If it was done
	 * every day it could pollute the log with a lot of
	 * "Simple History removed X events that were older than Y days".
	 *
	 * @since 2.0.17
	 */
	public function maybe_purge_db() {
		/**
		 * Day of week today.
		 *
		 * @int $current_day_of_week
		 */
		$current_day_of_week = (int) gmdate( 'N' );

		/**
		 * Day number to purge db on.
		 *
		 * @int $day_of_week_to_purge_db
		 */
		$day_of_week_to_purge_db = 7;

		/**
		 * Filter to change day of week to purge db on.
		 * Default is 7 (sunday).
		 *
		 * @param int $day_of_week_to_purge_db
		 * @since 4.1.0
		 */
		$day_of_week_to_purge_db = apply_filters( 'simple_history/day_of_week_to_purge_db', $day_of_week_to_purge_db );

		if ( $current_day_of_week !== $day_of_week_to_purge_db ) {
			return;
		}

		$this->purge_db();
	}

	/**
	 * Removes old entries from the db.
	 *
	 * Removes in batches of 100 000 rows.
	 */
	public function purge_db() {
		$do_purge_history = true;

		$do_purge_history = apply_filters( 'simple_history_allow_db_purge', $do_purge_history );
		$do_purge_history = apply_filters( 'simple_history/allow_db_purge', $do_purge_history );

		if ( ! $do_purge_history ) {
			return;
		}

		$days = Helpers::get_clear_history_interval();

		// Never clear log if days = 0.
		if ( $days === 0 ) {
			return;
		}

		$this->purge_table_pair(
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name(),
			$days,
			false
		);

		// Also purge the network event tables when an add-on (Premium) has
		// registered them. Without this, the shared network log grows
		// unbounded — the per-site purge above only touches per-site tables.
		// Run from the main site only, with a site-transient lock for a
		// network-wide same-day dedupe.
		$network_tables = $this->simple_history->get_network_tables();

		if (
			$network_tables !== null
			&& is_multisite()
			&& is_main_site()
			&& get_site_transient( self::NETWORK_PURGE_LOCK_TRANSIENT ) === false
		) {
			set_site_transient( self::NETWORK_PURGE_LOCK_TRANSIENT, 1, DAY_IN_SECONDS );

			$this->purge_table_pair(
				$network_tables['events'],
				$network_tables['contexts'],
				$days,
				true
			);
		}
	}

	/**
	 * Run the batched purge against a single events/contexts table pair.
	 *
	 * @param string $events_table   Events table name.
	 * @param string $contexts_table Contexts table name.
	 * @param int    $days           Retention in days.
	 * @param bool   $is_network     True when purging the shared network tables.
	 */
	private function purge_table_pair( $events_table, $contexts_table, $days, $is_network ) {
		global $wpdb;

		// Track total rows deleted across all batches.
		$total_rows = 0;

		// Build the WHERE clause for selecting events to purge.
		$where = $this->get_purge_where_clause( $days, $events_table );

		// Process deletions in batches of 100,000 rows to avoid memory exhaustion,
		// query timeouts, and long table locks. Loop continues until no old events remain.
		while ( 1 > 0 ) {
			// Get id of rows to delete.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$sql = "SELECT id FROM {$events_table} WHERE {$where} LIMIT 100000";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$ids_to_delete = $wpdb->get_col( $sql );

			if ( empty( $ids_to_delete ) ) {
				// Nothing more to delete.
				break;
			}

			$sql_ids_in = implode( ',', $ids_to_delete );

			// Remove rows + contexts.
			$sql_delete_history         = "DELETE FROM {$events_table} WHERE id IN ($sql_ids_in)";
			$sql_delete_history_context = "DELETE FROM {$contexts_table} WHERE history_id IN ($sql_ids_in)";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $sql_delete_history );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $sql_delete_history_context );

			$num_rows_purged_in_batch = is_countable( $ids_to_delete ) ? count( $ids_to_delete ) : 0;
			$total_rows              += $num_rows_purged_in_batch;

			/**
			 * Fires after a batch of events have been purged from the database.
			 * Note: This fires for each batch of 100,000 rows.
			 *
			 * @param int  $days                     Number of days to keep events.
			 * @param int  $num_rows_purged_in_batch Number of rows deleted in this batch.
			 * @param bool $is_network               True when the batch came from the network tables.
			 */
			do_action( 'simple_history/db/events_purged', $days, $num_rows_purged_in_batch, $is_network );

			Helpers::clear_cache();
		}

		/**
		 * Fires after all events have been purged from the database.
		 * This fires once when the entire purge operation is complete.
		 * Total rows can be 0 if no events were purged.
		 *
		 * @param int  $days       Number of days to keep events.
		 * @param int  $total_rows Total number of rows deleted across all batches.
		 * @param bool $is_network True when the completed run targeted the network tables.
		 * @since 5.21.0
		 */
		do_action( 'simple_history/db/purge_done', $days, $total_rows, $is_network );
	}

	/**
	 * Build the WHERE clause for the purge query.
	 *
	 * @param int    $days       Number of days to keep events.
	 * @param string $table_name Events table name.
	 * @return string SQL WHERE clause (without the WHERE keyword).
	 */
	private function get_purge_where_clause( $days, $table_name ) {
		global $wpdb;

		// Default: delete events older than X days.
		// Compare date column directly to allow index usage.
		$where = $wpdb->prepare(
			'date < DATE_SUB(NOW(), INTERVAL %d DAY)',
			$days
		);

		/**
		 * Filter the SQL WHERE clause used when purging old events.
		 *
		 * This filter allows advanced customization of which events to delete.
		 * Use it to implement per-logger retention, keep certain events forever,
		 * or add any custom deletion criteria.
		 *
		 * Available columns in the events table:
		 * - id (bigint)
		 * - logger (varchar) - e.g. 'SimpleUserLogger', 'SimplePostLogger'
		 * - level (varchar) - 'debug', 'info', 'warning', 'error', 'critical'
		 * - date (datetime)
		 * - message (varchar)
		 * - initiator (varchar) - 'wp_user', 'web_user', 'wp_cli', 'wp', 'other'
		 *
		 * IMPORTANT: You are responsible for returning valid SQL.
		 * Always use $wpdb->prepare() for dynamic values.
		 *
		 * Note: on multisite with the network log enabled, this filter runs
		 * twice — once for the per-site events table and once for the shared
		 * network events table. Branch on $table_name if the customization
		 * should only apply to one of them.
		 *
		 * @since 5.21.0
		 *
		 * @param string $where      SQL WHERE clause (without "WHERE" keyword).
		 * @param int    $days       Default retention days from settings.
		 * @param string $table_name Events table name (per-site or network) the WHERE will run against.
		 *
		 * @example Keep SimpleOptionsLogger events forever (exclude from purge).
		 *
		 * ```php
		 * add_filter( 'simple_history/purge_db_where', function( $where, $days, $table ) {
		 *     global $wpdb;
		 *     return $where . $wpdb->prepare( ' AND logger != %s', 'SimpleOptionsLogger' );
		 * }, 10, 3 );
		 * ```
		 *
		 * @example Keep events with level "warning" or higher forever.
		 *
		 * ```php
		 * add_filter( 'simple_history/purge_db_where', function( $where, $days, $table ) {
		 *     return $where . " AND level NOT IN ('warning', 'error', 'critical')";
		 * }, 10, 3 );
		 * ```
		 *
		 * @example Different retention per logger (replaces default WHERE).
		 *
		 * ```php
		 * add_filter( 'simple_history/purge_db_where', function( $where, $days, $table ) {
		 *     global $wpdb;
		 *
		 *     // Define custom retention per logger (in days, 0 = keep forever).
		 *     $retention = [
		 *         'SimpleUserLogger'    => 365, // Login events: 1 year.
		 *         'SimplePostLogger'    => 180, // Post changes: 6 months.
		 *         'SimpleOptionsLogger' => 0,   // Settings changes: forever.
		 *     ];
		 *
		 *     $conditions = [];
		 *
		 *     foreach ( $retention as $logger => $logger_days ) {
		 *         // Skip loggers that should be kept forever.
		 *         if ( $logger_days === 0 ) {
		 *             continue;
		 *         }
		 *         $conditions[] = $wpdb->prepare(
		 *             '(logger = %s AND date < DATE_SUB(NOW(), INTERVAL %d DAY))',
		 *             $logger,
		 *             $logger_days
		 *         );
		 *     }
		 *
		 *     // All other loggers: use default retention, but exclude "keep forever" loggers.
		 *     $keep_forever = array_keys( array_filter( $retention, fn( $d ) => $d === 0 ) );
		 *     if ( ! empty( $keep_forever ) ) {
		 *         $placeholders = implode( ',', array_fill( 0, count( $keep_forever ), '%s' ) );
		 *         $conditions[] = $wpdb->prepare(
		 *             "(logger NOT IN ($placeholders) AND $where)",
		 *             ...$keep_forever
		 *         );
		 *     } else {
		 *         $conditions[] = "($where)";
		 *     }
		 *
		 *     return '(' . implode( ' OR ', $conditions ) . ')';
		 * }, 10, 3 );
		 * ```
		 */
		$where = apply_filters( 'simple_history/purge_db_where', $where, $days, $table_name );

		return $where;
	}
}
