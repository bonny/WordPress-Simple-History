<?php

namespace Simple_History\Dropins;

use Simple_History\Dropins\Dropin;
use Simple_History\Log_Query;
use Simple_History\Helpers;

/**
 * Class that handles the quick stats above the log.
 * I.e. the message that says "3 events today from one user and one other source."
 */
class Quick_Stats extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'simple_history/dropin/stats/before_content', array( $this, 'output_quick_stats' ), 5 );
	}

	/**
	 * Get the number of events today.
	 * Uses log_query so it respects the user's permissions,
	 * meaning that the number of events is the number
	 * of events that the current user is allowed to see.
	 *
	 * @return int
	 */
	protected function get_num_events_today() {
		$logQuery = new Log_Query();

		$logResults = $logQuery->query(
			array(
				'posts_per_page' => 1,
				'date_from' => strtotime( 'today' ),
			)
		);

		return (int) $logResults['total_row_count'];
	}

	/**
	 * Get the SQL for the loggers that the current user is allowed to read.
	 *
	 * @return string
	 */
	protected function get_sql_loggers_in() {
		return $this->simple_history->get_loggers_that_user_can_read( get_current_user_id(), 'sql' );
	}

	/**
	 * Get the number of users that have done something today.
	 *
	 * @return int
	 */
	protected function get_num_users_today() {
		global $wpdb;

		$sql_loggers_in = $this->get_sql_loggers_in();

		// Get number of users today, i.e. events with wp_user as initiator.
		$sql_users_today = sprintf(
			'
            SELECT
                DISTINCT(c.value) AS user_id
                FROM %3$s AS h
            INNER JOIN %4$s AS c
            ON c.history_id = h.id AND c.key = \'_user_id\'
            WHERE
                initiator = \'wp_user\'
                AND logger IN %1$s
                AND date > \'%2$s\'
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name()
		);

		$cache_key = 'quick_stats_users_today_' . md5( serialize( $sql_loggers_in ) );
		$cache_group = Helpers::get_cache_group();
		$results_users_today = wp_cache_get( $cache_key, $cache_group );

		if ( false === $results_users_today ) {
			$results_users_today = $wpdb->get_results( $sql_users_today ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_cache_set( $cache_key, $results_users_today, $cache_group );
		}

		$count_users_today = is_countable( $results_users_today ) ? count( $results_users_today ) : 0;

		return $count_users_today;
	}

	/**
	 * Get number of other sources (not wp_user).
	 *
	 * @return int Number of other sources.
	 */
	protected function get_other_sources_count() {
		global $wpdb;

		$cache_group = Helpers::get_cache_group();
		$sql_loggers_in = $this->get_sql_loggers_in();

		$sql_other_sources_where = sprintf(
			'
                initiator <> \'wp_user\'
                AND logger IN %1$s
                AND date > \'%2$s\'
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) )
		);

		$sql_other_sources_where = apply_filters( 'simple_history/quick_stats_where', $sql_other_sources_where );

		$sql_other_sources = sprintf(
			'
            SELECT
                DISTINCT(h.initiator) AS initiator
            FROM %3$s AS h
            WHERE
                %5$s
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name(),
			$sql_other_sources_where // 5
		);

		$cache_key = 'quick_stats_results_other_sources_today_' . md5( serialize( $sql_other_sources ) );
		$results_other_sources_today = wp_cache_get( $cache_key, $cache_group );

		if ( false === $results_other_sources_today ) {
			$results_other_sources_today = $wpdb->get_results( $sql_other_sources ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_cache_set( $cache_key, $results_other_sources_today, $cache_group );
		}

		$count_other_sources = is_countable( $results_other_sources_today ) ? count( $results_other_sources_today ) : 0;

		return $count_other_sources;
	}

	/**
	 * Output some simple quick stats.
	 */
	/**
	 * Get the template message for the quick stats.
	 *
	 * @return string The message.
	 */
	protected function get_stats_message() {
		$num_events_today = $this->get_num_events_today();
		$num_users_with_events_today = $this->get_num_users_today();
		$num_other_sources_today = $this->get_other_sources_count();

		$msg_tmpl = '';

		// No results today at all.
		if ( $num_events_today == 0 ) {
			$msg_tmpl = __( 'No events today so far.', 'simple-history' );
		} elseif ( $num_events_today == 1 && $num_users_with_events_today == 1 ) {
				// A single event existed and was from a user.
				$msg_tmpl = __( 'One event today from one user.', 'simple-history' );
		} elseif ( $num_events_today == 1 && ! $num_users_with_events_today ) {
			// A single event existed and was from another source.
			$msg_tmpl = __( 'One event today from one source.', 'simple-history' );
		} elseif ( $num_events_today > 1 && $num_users_with_events_today == 1 && ! $num_other_sources_today ) {
			// Multiple events from a single user.
			/* translators: %1$d: number of events */
			$msg_tmpl = __( '%1$d events today from one user.', 'simple-history' );
		} elseif ( $num_events_today > 1 && $num_users_with_events_today == $num_events_today ) {
			// Multiple events from only users.
			/* translators: %1$d: number of events, %2$d: number of users */
			$msg_tmpl = __( '%1$d events today from %2$d users.', 'simple-history' );
		} elseif ( $num_events_today && 1 == $num_users_with_events_today && 1 == $num_other_sources_today ) {
			// Multiple events from 1 single user and 1 single other source.
			/* translators: %1$d: number of events */
			$msg_tmpl = __( '%1$d events today from one user and one other source.', 'simple-history' );
		} elseif ( $num_events_today > 1 && $num_users_with_events_today > 1 && $num_other_sources_today == 1 ) {
			// Multiple events from multiple users but from only 1 single other source.
			/* translators: %1$d: number of events */
			$msg_tmpl = __( '%1$d events today from one user and one other source.', 'simple-history' );
		} elseif ( $num_events_today > 1 && 1 == $num_users_with_events_today && $num_other_sources_today > 1 ) {
			// Multiple events from 1 user but from multiple other sources.
			/* translators: %1$d: number of events, %3$d: number of other sources */
			$msg_tmpl = __( '%1$d events today from one user and %3$d other sources.', 'simple-history' );
		} elseif ( $num_events_today > 1 && $num_users_with_events_today > 1 && $num_other_sources_today > 1 ) {
			// Multiple events from multiple user and from multiple other sources.
			/* translators: %1$s: number of events, %2$d: number of users, %3$d: number of other sources */
			$msg_tmpl = __( '%1$s events today from %2$d users and %3$d other sources.', 'simple-history' );
		}

		if ( $msg_tmpl === '' ) {
			return '';
		}

		$final_msg = sprintf(
			esc_html( $msg_tmpl ),
			esc_html( $num_events_today ), // 1
			esc_html( $num_users_with_events_today ), // 2
			esc_html( $num_other_sources_today ) // 3
		);

		return "<p class='SimpleHistoryQuickStats'>$final_msg</p>";
	}

	/**
	 * Output some simple quick stats.
	 */
	public function output_quick_stats() {
		echo wp_kses_post( $this->get_stats_message() );
	}
}
