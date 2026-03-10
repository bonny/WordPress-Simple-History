<?php

namespace Simple_History\Services\WP_CLI_Commands;

use WP_CLI;
use WP_CLI_Command;
use Simple_History\Simple_History;
use Simple_History\Log_Query;
use Simple_History\Helpers;

/**
 * Benchmark search performance for Simple History.
 *
 * Only available when SIMPLE_HISTORY_DEV constant is true.
 */
class WP_CLI_Benchmark_Search_Command extends WP_CLI_Command {
	/**
	 * Benchmark search performance.
	 *
	 * Runs search queries and reports timing, comparing performance
	 * with and without the experimental optimized search.
	 *
	 * ## OPTIONS
	 *
	 * [<search>]
	 * : Search term to benchmark. Defaults to a common term.
	 *
	 * [--runs=<number>]
	 * : Number of times to run each query for averaging.
	 * ---
	 * default: 3
	 * ---
	 *
	 * [--per-page=<number>]
	 * : Number of results per page.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--explain]
	 * : Show EXPLAIN output for the search queries.
	 *
	 * ## EXAMPLES
	 *
	 *     # Benchmark with default search term
	 *     wp simple-history dev benchmark-search
	 *
	 *     # Benchmark a specific search term
	 *     wp simple-history dev benchmark-search "WooCommerce"
	 *
	 *     # Benchmark with more runs for accuracy
	 *     wp simple-history dev benchmark-search "plugin" --runs=10
	 *
	 *     # Show EXPLAIN output
	 *     wp simple-history dev benchmark-search "updated" --explain
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function benchmark_search( $args, $assoc_args ) {
		global $wpdb;

		// Set admin user so get_loggers_that_user_can_read() works.
		wp_set_current_user( 1 );

		$search_term  = $args[0] ?? 'updated plugin';
		$runs         = (int) ( $assoc_args['runs'] ?? 3 );
		$per_page     = (int) ( $assoc_args['per-page'] ?? 20 );
		$show_explain = isset( $assoc_args['explain'] );

		$simple_history = Simple_History::get_instance();
		$events_table   = $simple_history->get_events_table_name();
		$contexts_table = $simple_history->get_contexts_table_name();

		// Show table stats.
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BDatabase Stats:%n' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$context_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$contexts_table}" );

		WP_CLI::log( sprintf( '  Events table: %s rows', number_format( (int) $event_count ) ) );
		WP_CLI::log( sprintf( '  Contexts table: %s rows', number_format( (int) $context_count ) ) );
		WP_CLI::log( sprintf( '  Search term: "%s"', $search_term ) );
		WP_CLI::log( sprintf( '  Runs per test: %d', $runs ) );

		$experimental_enabled = Helpers::experimental_features_is_enabled();
		WP_CLI::log( sprintf( '  Experimental features: %s', $experimental_enabled ? 'ENABLED' : 'DISABLED' ) );

		// Benchmark: no search (baseline).
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BBaseline (no search):%n' ) );
		$baseline_times = $this->run_search_benchmark( $runs, $per_page, '' );
		$this->report_times( $baseline_times );

		// Benchmark: with search.
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BWith search:%n' ) );
		$search_times = $this->run_search_benchmark( $runs, $per_page, $search_term );
		$this->report_times( $search_times );

		// Show results count.
		$result = $this->run_search( $per_page, $search_term );
		WP_CLI::log( sprintf( '  Results found: %s', $result['total_row_count'] ?? 'unknown' ) );

		// Show EXPLAIN if requested.
		if ( $show_explain ) {
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%BEXPLAIN output:%n' ) );
			WP_CLI::log( '  (Enable SAVEQUERIES or use Query Monitor for detailed SQL analysis)' );
		}

		// Summary.
		$baseline_avg = array_sum( $baseline_times ) / count( $baseline_times );
		$search_avg   = array_sum( $search_times ) / count( $search_times );
		$slowdown     = $baseline_avg > 0 ? $search_avg / $baseline_avg : 0;

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BSummary:%n' ) );
		WP_CLI::log( sprintf( '  Baseline avg: %.1fms', $baseline_avg ) );
		WP_CLI::log( sprintf( '  Search avg:   %.1fms', $search_avg ) );
		WP_CLI::log( sprintf( '  Slowdown:     %.1fx', $slowdown ) );
		WP_CLI::log( '' );
	}

	/**
	 * Run a search benchmark multiple times.
	 *
	 * @param int    $runs Number of runs.
	 * @param int    $per_page Results per page.
	 * @param string $search_term Search term (empty for no search).
	 * @return array<float> Array of execution times in milliseconds.
	 */
	private function run_search_benchmark( $runs, $per_page, $search_term ) {
		$times = [];

		for ( $i = 0; $i < $runs; $i++ ) {
			wp_cache_flush();

			$start = microtime( true );
			$this->run_search( $per_page, $search_term );
			$elapsed = ( microtime( true ) - $start ) * 1000;
			$times[] = $elapsed;
		}

		return $times;
	}

	/**
	 * Run a single search query.
	 *
	 * @param int    $per_page Results per page.
	 * @param string $search_term Search term.
	 * @return array Query result.
	 */
	private function run_search( $per_page, $search_term ) {
		// Override capability check for CLI.
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true' );

		$query_args = [
			'paged'    => 1,
			'per_page' => $per_page,
		];

		if ( ! empty( $search_term ) ) {
			$query_args['search'] = $search_term;
		}

		$log_query = new Log_Query();
		$result    = $log_query->query( $query_args );

		remove_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true' );

		return $result;
	}

	/**
	 * Benchmark default event retrieval (no search).
	 *
	 * Tests the queries used by the dashboard widget and events page,
	 * including pagination to different pages.
	 *
	 * ## OPTIONS
	 *
	 * [--runs=<number>]
	 * : Number of times to run each query for averaging.
	 * ---
	 * default: 3
	 * ---
	 *
	 * [--per-page=<number>]
	 * : Number of results per page.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--pages=<number>]
	 * : Number of pages to test (1, 2, ..., N).
	 * ---
	 * default: 5
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Benchmark default retrieval
	 *     wp simple-history dev benchmark-query
	 *
	 *     # Benchmark with larger page size
	 *     wp simple-history dev benchmark-query --per-page=50
	 *
	 *     # Test deep pagination (10 pages)
	 *     wp simple-history dev benchmark-query --pages=10
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function benchmark_query( $args, $assoc_args ) {
		global $wpdb;

		// Set admin user so get_loggers_that_user_can_read() works.
		wp_set_current_user( 1 );

		$runs = (int) ( $assoc_args['runs'] ?? 3 );

		$simple_history = Simple_History::get_instance();
		$events_table   = $simple_history->get_events_table_name();
		$contexts_table = $simple_history->get_contexts_table_name();

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BDatabase Stats:%n' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$context_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$contexts_table}" );

		WP_CLI::log( sprintf( '  Events table: %s rows', number_format( (int) $event_count ) ) );
		WP_CLI::log( sprintf( '  Contexts table: %s rows', number_format( (int) $context_count ) ) );
		WP_CLI::log( sprintf( '  Runs per test: %d', $runs ) );

		// Benchmark: Dashboard widget (no date filter, 5 per page).
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BDashboard widget (5 per page, no date filter):%n' ) );
		$dashboard_times = $this->run_query_benchmark( $runs, 5, 1, 0 );
		$this->report_times( $dashboard_times );
		$result = $this->run_query( 5, 1, 0 );
		WP_CLI::log( sprintf( '  Total events: %s', $result['total_row_count'] ?? 'unknown' ) );

		// Benchmark: Events page (20 per page, last 7 days).
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BEvents page (20 per page, last 7 days):%n' ) );
		$events_page_times = $this->run_query_benchmark( $runs, 20, 1, 7 );
		$this->report_times( $events_page_times );
		$result = $this->run_query( 20, 1, 7 );
		WP_CLI::log( sprintf( '  Total events: %s', $result['total_row_count'] ?? 'unknown' ) );

		// Benchmark: Events page, all dates.
		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BEvents page (20 per page, all dates):%n' ) );
		$all_dates_times = $this->run_query_benchmark( $runs, 20, 1, 0 );
		$this->report_times( $all_dates_times );
		$result = $this->run_query( 20, 1, 0 );
		WP_CLI::log( sprintf( '  Total events: %s', $result['total_row_count'] ?? 'unknown' ) );

		// Summary.
		$dashboard_avg   = array_sum( $dashboard_times ) / count( $dashboard_times );
		$events_page_avg = array_sum( $events_page_times ) / count( $events_page_times );
		$all_dates_avg   = array_sum( $all_dates_times ) / count( $all_dates_times );

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BSummary:%n' ) );
		WP_CLI::log( sprintf( '  Dashboard widget avg:          %.1fms', $dashboard_avg ) );
		WP_CLI::log( sprintf( '  Events page (7 days) avg:      %.1fms', $events_page_avg ) );
		WP_CLI::log( sprintf( '  Events page (all dates) avg:   %.1fms', $all_dates_avg ) );

		if ( $events_page_avg > 0 ) {
			WP_CLI::log( sprintf( '  Dashboard vs events page (7d): %.1fx', $dashboard_avg / $events_page_avg ) );
		}

		WP_CLI::log( '' );
	}

	/**
	 * Run a query benchmark multiple times for a specific page.
	 *
	 * @param int $runs Number of runs.
	 * @param int $per_page Results per page.
	 * @param int $page Page number.
	 * @param int $lastdays Number of days to limit to (0 = no limit).
	 * @return array<float> Array of execution times in milliseconds.
	 */
	private function run_query_benchmark( $runs, $per_page, $page, $lastdays = 0 ) {
		$times = [];

		for ( $i = 0; $i < $runs; $i++ ) {
			wp_cache_flush();

			$start = microtime( true );
			$this->run_query( $per_page, $page, $lastdays );
			$elapsed = ( microtime( true ) - $start ) * 1000;
			$times[] = $elapsed;
		}

		return $times;
	}

	/**
	 * Run a single query for a specific page.
	 *
	 * @param int $per_page Results per page.
	 * @param int $page Page number.
	 * @param int $lastdays Number of days to limit to (0 = no limit).
	 * @return array Query result.
	 */
	private function run_query( $per_page, $page, $lastdays = 0 ) {
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true' );

		$query_args = [
			'paged'    => $page,
			'per_page' => $per_page,
		];

		if ( $lastdays > 0 ) {
			$query_args['date_from'] = strtotime( "-{$lastdays} days" );
			$query_args['date_to']   = time();
		}

		$log_query = new Log_Query();
		$result    = $log_query->query( $query_args );

		remove_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true' );

		return $result;
	}

	/**
	 * Report timing results.
	 *
	 * @param array<float> $times Array of execution times in milliseconds.
	 */
	private function report_times( $times ) {
		$avg = array_sum( $times ) / count( $times );
		$min = min( $times );
		$max = max( $times );

		$time_strings = array_map(
			function ( $t ) {
				return sprintf( '%.1fms', $t );
			},
			$times
		);

		WP_CLI::log( sprintf( '  Runs: %s', implode( ', ', $time_strings ) ) );
		WP_CLI::log( sprintf( '  Avg: %.1fms | Min: %.1fms | Max: %.1fms', $avg, $min, $max ) );
	}
}
