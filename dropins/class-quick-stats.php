<?php

namespace Simple_History\Dropins;

use Simple_History\Dropins\Dropin;
use Simple_History\Log_Query;
use Simple_History\Helpers;

/**
 * Class that handles the quick stats above the log.
 */
class Quick_Stats extends Dropin {
	public function loaded() {
		add_action( 'simple_history/history_page/before_gui', array( $this, 'output_quick_stats' ), 5 );
		add_action( 'simple_history/dashboard/before_gui', array( $this, 'output_quick_stats' ), 5 );
	}

	/**
	 * Quick stats above the log
	 * Uses filter "simple_history/history_page/before_gui" to output its contents
	 */
	public function output_quick_stats() {
		global $wpdb;

		// Get number of events today
		$logQuery = new Log_Query();
		$logResults = $logQuery->query(
			array(
				'posts_per_page' => 1,
				'date_from' => strtotime( 'today' ),
			)
		);

		$total_row_count = (int) $logResults['total_row_count'];

		// Get sql query for where to read only loggers current user is allowed to read/view
		$sql_loggers_in = $this->simple_history->get_loggers_that_user_can_read( get_current_user_id(), 'sql' );

		// Get number of users today, i.e. events with wp_user as initiator
		$sql_users_today = sprintf(
			'
            SELECT
                DISTINCT(c.value) AS user_id
                FROM %3$s AS h
            INNER JOIN %4$s AS c
            ON c.history_id = h.id AND c.key = "_user_id"
            WHERE
                initiator = "wp_user"
                AND logger IN %1$s
                AND date > "%2$s"
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name()
		);

		$cache_key = 'quick_stats_users_today_' . md5( serialize( $sql_loggers_in ) );
		$cache_group = 'simple-history-' . Helpers::get_cache_incrementor();
		$results_users_today = wp_cache_get( $cache_key, $cache_group );

		if ( false === $results_users_today ) {
			$results_users_today = $wpdb->get_results( $sql_users_today ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_cache_set( $cache_key, $results_users_today, $cache_group );
		}

		$count_users_today = is_countable( $results_users_today ) ? count( $results_users_today ) : 0;

		// Get number of other sources (not wp_user).
		$sql_other_sources_where = sprintf(
			'
                initiator <> "wp_user"
                AND logger IN %1$s
                AND date > "%2$s"
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
		?>
		<div class="SimpleHistoryQuickStats">
			<p>
				<?php
				$msg_tmpl = '';

				// No results today at all
				if ( $total_row_count == 0 ) {
					$msg_tmpl = __( 'No events today so far.', 'simple-history' );
				} else {
					/*
					Type of results
					x1 event today from 1 user.
					x1 event today from 1 source.
					3 events today from 1 user.
					x2 events today from 2 users.
					x2 events today from 1 user and 1 other source.
					x3 events today from 2 users and 1 other source.
					x3 events today from 1 user and 2 other sources.
					x4 events today from 2 users and 2 other sources.
					 */

					// A single event existed and was from a user
					// 1 event today from 1 user.
					if ( $total_row_count == 1 && $count_users_today == 1 ) {
						$msg_tmpl .= __( 'One event today from one user.', 'simple-history' );
					}

					// A single event existed and was from another source
					// 1 event today from 1 source.
					if ( $total_row_count == 1 && ! $count_users_today ) {
						$msg_tmpl .= __( 'One event today from one source.', 'simple-history' );
					}

					// Multiple events from a single user
					// 3 events today from one user.
					if ( $total_row_count > 1 && $count_users_today == 1 && ! $count_other_sources ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user.', 'simple-history' );
					}

					// Multiple events from only users
					// 2 events today from 2 users.
					if ( $total_row_count > 1 && $count_users_today == $total_row_count ) {
						// translators: 1 is number of events. 2 is number of users.
						$msg_tmpl .= __( '%1$d events today from %2$d users.', 'simple-history' );
					}

					// Multiple events from 1 single user and 1 single other source
					// 2 events today from 1 user and 1 other source.
					if ( $total_row_count && 1 == $count_users_today && 1 == $count_other_sources ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user and one other source.', 'simple-history' );
					}

					// Multiple events from multiple users but from only 1 single other source
					// 3 events today from 2 users and 1 other source.
					if ( $total_row_count > 1 && $count_users_today > 1 && $count_other_sources == 1 ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user and one other source.', 'simple-history' );
					}

					// Multiple events from 1 user but from multiple  other source
					// 3 events today from 1 user and 2 other sources.
					if ( $total_row_count > 1 && 1 == $count_users_today && $count_other_sources > 1 ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user and %3$d other sources.', 'simple-history' );
					}

					// Multiple events from multiple user and from multiple other sources
					// 4 events today from 2 users and 2 other sources.
					if ( $total_row_count > 1 && $count_users_today > 1 && $count_other_sources > 1 ) {
						// translators: 1 is number of events, 2 is number of users, 3 is number of other sources.
						$msg_tmpl .= __( '%1$s events today from %2$d users and %3$d other sources.', 'simple-history' );
					}
				} // End if().

				// Show stats if we have something to output.
				if ( $msg_tmpl !== '' ) {
					printf(
						esc_html( $msg_tmpl ),
						(int) $logResults['total_row_count'], // 1
						esc_html( $count_users_today ), // 2
						esc_html( $count_other_sources ) // 3
					);
				}
				?>
			</p>
		</div>
		<?php
	}
}
