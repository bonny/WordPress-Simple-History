<?php

use Simple_History\Log_Query;

/**
 * Test Log_Query::query_aggregate(), which counts matching events by bucket.
 *
 * Runs on MySQL/MariaDB and on SQLite. Only the hour interval differs between
 * the two engines (DATE_FORMAT versus strftime), and the test for it asserts
 * the shape of the bucket rather than the function that produced it.
 *
 * @coversDefaultClass Simple_History\Log_Query
 */
class LogQueryAggregateTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Log in as an administrator before each test.
	 *
	 * get_inner_where() limits every query to the loggers the current user
	 * can read, so without a user there are no readable loggers and every
	 * aggregate comes back empty — which looks like a broken query rather
	 * than a missing login.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Log a known mix of levels and loggers to count.
	 *
	 * @return void
	 */
	private function add_known_events() {
		SimpleLogger()->info( 'Aggregate info one' );
		SimpleLogger()->info( 'Aggregate info two' );
		SimpleLogger()->warning( 'Aggregate warning one' );
		SimpleLogger()->error( 'Aggregate error one' );
	}

	/**
	 * Sum the counts of every bucket carrying a given level.
	 *
	 * @param array  $buckets Result of query_aggregate().
	 * @param string $level   Level to total.
	 * @return int
	 */
	private function total_for_level( $buckets, $level ) {
		$total = 0;

		foreach ( $buckets as $bucket ) {
			if ( $bucket['level'] === $level ) {
				$total += $bucket['count'];
			}
		}

		return $total;
	}

	/**
	 * Grouping by level counts each level once, and the counts are at least
	 * what this test logged.
	 *
	 * Asserted as "at least" rather than an exact number: the suite database
	 * carries events from every other test that ran before this one, and a
	 * fixed expectation would make this test depend on the order the suite
	 * happens to run in.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_group_by_level_counts_each_level_once() {
		$this->add_known_events();

		$buckets = ( new Log_Query() )->query_aggregate( [ 'group_by' => 'level' ] );

		$this->assertIsArray( $buckets );

		$levels = wp_list_pluck( $buckets, 'bucket' );
		$this->assertSame( array_unique( $levels ), $levels, 'Each level should appear as exactly one bucket.' );

		$by_level = array_combine( $levels, wp_list_pluck( $buckets, 'count' ) );

		$this->assertGreaterThanOrEqual( 2, $by_level['info'] );
		$this->assertGreaterThanOrEqual( 1, $by_level['warning'] );
		$this->assertGreaterThanOrEqual( 1, $by_level['error'] );
	}

	/**
	 * A level filter applies to the counts, the same way it applies to a
	 * listing. A histogram that ignored the filters it was drawn beside
	 * would be worse than no histogram.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_filters_apply_to_the_counts() {
		$this->add_known_events();

		$buckets = ( new Log_Query() )->query_aggregate(
			[
				'group_by'  => 'level',
				'loglevels' => [ 'warning' ],
			]
		);

		$this->assertSame( [ 'warning' ], wp_list_pluck( $buckets, 'bucket' ) );
	}

	/**
	 * split_by_level returns one row per bucket and level, so a date
	 * histogram can be stacked.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_split_by_level_reports_a_level_per_bucket() {
		$this->add_known_events();

		$buckets = ( new Log_Query() )->query_aggregate(
			[
				'group_by'       => 'date',
				'split_by_level' => true,
			]
		);

		$this->assertNotEmpty( $buckets );

		foreach ( $buckets as $bucket ) {
			$this->assertNotNull( $bucket['level'], 'Every row should name its level when split_by_level is set.' );
		}

		$this->assertGreaterThanOrEqual( 1, $this->total_for_level( $buckets, 'warning' ) );
		$this->assertGreaterThanOrEqual( 1, $this->total_for_level( $buckets, 'error' ) );
	}

	/**
	 * Without split_by_level every row's level is null, rather than an
	 * arbitrary one of the levels in the bucket.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_level_is_null_when_not_splitting() {
		$this->add_known_events();

		$buckets = ( new Log_Query() )->query_aggregate( [ 'group_by' => 'date' ] );

		$this->assertNotEmpty( $buckets );

		foreach ( $buckets as $bucket ) {
			$this->assertNull( $bucket['level'] );
		}
	}

	/**
	 * A day bucket is a date, and an hour bucket is a date and an hour.
	 *
	 * The two use different SQL functions on MySQL and SQLite, so this
	 * asserts the format rather than the expression.
	 *
	 * @covers ::query_aggregate
	 * @covers ::get_aggregate_bucket_expression
	 */
	public function test_date_buckets_have_the_shape_the_interval_asks_for() {
		$this->add_known_events();

		$log_query = new Log_Query();

		$days = $log_query->query_aggregate( [ 'group_by' => 'date' ] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $days[0]['bucket'] );

		$hours = $log_query->query_aggregate(
			[
				'group_by' => 'date',
				'interval' => 'hour',
			]
		);
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/', $hours[0]['bucket'] );
	}

	/**
	 * An unknown grouping is refused rather than interpolated into the
	 * statement. This is the guard that keeps the caller from choosing the
	 * SQL, so it is worth a test of its own.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_unknown_group_by_is_refused() {
		$result = ( new Log_Query() )->query_aggregate( [ 'group_by' => 'level; DROP TABLE wp_posts' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'simple_history_invalid_group_by', $result->get_error_code() );
	}

	/**
	 * The result is cached, and a second identical call does not hit the
	 * database again.
	 *
	 * This is a GROUP BY with no index behind most of the groupings, and the
	 * histogram above the table refires on every filter change — so without
	 * a cache, typing in the search box is one full-table scan per keystroke
	 * from an account that only needs the view-history capability.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_the_result_is_cached() {
		global $wpdb;

		$this->add_known_events();

		$log_query = new Log_Query();
		$args      = [ 'group_by' => 'level' ];

		$first = $log_query->query_aggregate( $args );

		$queries_before = $wpdb->num_queries;
		$second         = $log_query->query_aggregate( $args );

		$this->assertSame( $first, $second );
		$this->assertSame(
			$queries_before,
			$wpdb->num_queries,
			'The second identical call should be served from the cache.'
		);
	}

	/**
	 * A different question is a different cache entry, so the cache cannot
	 * answer one grouping with another one's counts.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_the_cache_is_keyed_on_the_query() {
		$this->add_known_events();

		$log_query = new Log_Query();

		$by_level  = $log_query->query_aggregate( [ 'group_by' => 'level' ] );
		$by_logger = $log_query->query_aggregate( [ 'group_by' => 'logger' ] );

		$this->assertNotSame(
			wp_list_pluck( $by_level, 'bucket' ),
			wp_list_pluck( $by_logger, 'bucket' )
		);
	}

	/**
	 * max_buckets caps the result, so a query over years of events cannot
	 * return an unbounded list.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_max_buckets_caps_the_result() {
		$this->add_known_events();

		$buckets = ( new Log_Query() )->query_aggregate(
			[
				'group_by'    => 'level',
				'max_buckets' => 1,
			]
		);

		$this->assertCount( 1, $buckets );
	}

	/**
	 * The cap counts buckets, not rows.
	 *
	 * Splitting by level turns one bucket into up to one row per level, so a
	 * row-based cap made max_buckets mean roughly an eighth of what it says —
	 * which is how the histogram, which always splits by level, drew about
	 * two months of a chart that was asked for 500 days.
	 *
	 * @covers ::query_aggregate
	 */
	public function test_max_buckets_counts_buckets_not_rows_when_split_by_level() {
		$this->add_known_events();

		$buckets = ( new Log_Query() )->query_aggregate(
			[
				'group_by'       => 'level',
				'split_by_level' => true,
				'max_buckets'    => 3,
			]
		);

		$distinct = array_unique( wp_list_pluck( $buckets, 'bucket' ) );

		$this->assertGreaterThan(
			1,
			count( $distinct ),
			'A cap of 3 buckets should not collapse to a single bucket just because each one also splits by level.'
		);

		$this->assertLessThanOrEqual( 3, count( $distinct ) );
	}
}
