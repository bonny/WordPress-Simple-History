<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Helpers;

class Setup_Purge_DB_Cron extends Service {
	public function loaded() {
		add_action( 'after_setup_theme', array( $this, 'setup_cron' ) );
	}

	/**
	 * Setup a wp-cron job that daily checks if the database should be cleared.
	 */
	public function setup_cron() {
		add_filter( 'simple_history/maybe_purge_db', array( $this, 'maybe_purge_db' ) );

		if ( ! wp_next_scheduled( 'simple_history/maybe_purge_db' ) ) {
			wp_schedule_event( time(), 'daily', 'simple_history/maybe_purge_db' );
		}
	}

	/**
	 * Runs the purge_db() method sometimes.
	 *
	 * Fired from filter `simple_history/maybe_purge_db``
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

		if ( $current_day_of_week === $day_of_week_to_purge_db ) {
			$this->purge_db();
		}
	}

	/**
	 * Removes old entries from the db.
	 */
	public function purge_db() {
		$do_purge_history = true;

		$do_purge_history = apply_filters( 'simple_history_allow_db_purge', $do_purge_history );
		$do_purge_history = apply_filters( 'simple_history/allow_db_purge', $do_purge_history );

		if ( ! $do_purge_history ) {
			return;
		}

		$days = $this->simple_history->get_clear_history_interval();

		// Never clear log if days = 0.
		if ( 0 == $days ) {
			return;
		}

		$table_name = $this->simple_history->get_events_table_name();
		$table_name_contexts = $this->simple_history->get_contexts_table_name();

		global $wpdb;

		while ( 1 > 0 ) {
			// Get id of rows to delete.
			$sql = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				"SELECT id FROM $table_name WHERE DATE_ADD(date, INTERVAL %d DAY) < now() LIMIT 100000",
				$days
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$ids_to_delete = $wpdb->get_col( $sql );

			if ( empty( $ids_to_delete ) ) {
				// Nothing to delete.
				return;
			}

			$sql_ids_in = implode( ',', $ids_to_delete );

			// Add number of deleted rows to total_rows option.
			$prev_total_rows = (int) get_option( 'simple_history_total_rows', 0 );
			$total_rows = $prev_total_rows + ( is_countable( $ids_to_delete ) ? count( $ids_to_delete ) : 0 );
			update_option( 'simple_history_total_rows', $total_rows );

			// Remove rows + contexts.
			$sql_delete_history = "DELETE FROM {$table_name} WHERE id IN ($sql_ids_in)";
			$sql_delete_history_context = "DELETE FROM {$table_name_contexts} WHERE history_id IN ($sql_ids_in)";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql_delete_history );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql_delete_history_context );

			$num_rows_purged = is_countable( $ids_to_delete ) ? count( $ids_to_delete ) : 0;

			/**
			 * Fires after events have been purged from the database.
			 *
			 * @param int $days Number of days to keep events.
			 * @param int $num_rows_purged Number of rows deleted.
			 */
			do_action( 'simple_history/db/events_purged', $days, $num_rows_purged );

			Helpers::get_cache_incrementor( true );
		}
	}
}
