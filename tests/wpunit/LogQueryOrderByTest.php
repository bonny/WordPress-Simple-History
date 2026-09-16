<?php

use Simple_History\Log_Query;

/**
 * Test the orderby and order arguments of Log_Query.
 *
 * Runs on MySQL/MariaDB and on SQLite: the argument only affects
 * query_overview_simple(), which is the path SQLite always takes.
 * The exception is test_non_date_orderby_forces_ungrouped(), which also
 * asserts the grouped (date-order) path and so skips on SQLite — see
 * OccasionsGroupingDetailedTest for why grouping is MySQL-only.
 *
 * @coversDefaultClass Simple_History\Log_Query
 */
class LogQueryOrderByTest extends \Codeception\TestCase\WPTestCase {
	use \Helper\SkipsOnSqlite;

	/**
	 * Log four events: three distinct ones plus a duplicate "Alpha event"
	 * so occasion grouping has a real pair to collapse.
	 *
	 * @return void
	 */
	private function add_known_events() {
		// SimpleLogger() is the global helper every other test in this suite
		// uses to write events.
		SimpleLogger()->info( 'Alpha event' );

		// Log the same message again right away. With no explicit
		// _occasionsID, Logger::append_occasions_id_to_context() hashes
		// level/message/initiator into one, so this row gets the same
		// occasionsID as the row above and, logged back to back, the two
		// are adjacent in date order too. That gives occasion grouping
		// something real to collapse (see test_non_date_orderby_forces_ungrouped).
		SimpleLogger()->info( 'Alpha event' );

		SimpleLogger()->warning( 'Beta event' );
		SimpleLogger()->debug( 'Gamma event' );
	}

	/**
	 * Query ungrouped with the given extra args and return the rows.
	 *
	 * @param array $args Extra query args.
	 * @return array
	 */
	private function query_rows( $args ) {
		$log_query = new Log_Query();

		$result = $log_query->query(
			array_merge(
				[
					'posts_per_page' => 50,
					'ungrouped'      => true,
				],
				$args
			)
		);

		return $result['log_rows'];
	}

	public function setUp(): void {
		parent::setUp();

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->add_known_events();
	}

	/**
	 * The default is unchanged: newest first.
	 */
	public function test_default_order_is_date_desc() {
		$rows = $this->query_rows( [] );

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Default order should be newest first.' );
	}

	/**
	 * Ascending by id returns the oldest event first.
	 */
	public function test_order_by_id_asc() {
		$rows = $this->query_rows(
			[
				'orderby' => 'id',
				'order'   => 'ASC',
			]
		);

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		sort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Ascending by id should return oldest first.' );
	}

	/**
	 * Sorting by level orders alphabetically by the level column.
	 */
	public function test_order_by_level_asc() {
		$rows = $this->query_rows(
			[
				'orderby' => 'level',
				'order'   => 'ASC',
			]
		);

		$levels = wp_list_pluck( $rows, 'level' );

		$sorted = $levels;
		sort( $sorted, SORT_STRING );

		$this->assertSame( $sorted, $levels, 'Levels should come back alphabetically.' );
	}

	/**
	 * Lowercase order is accepted.
	 */
	public function test_order_is_case_insensitive() {
		$rows = $this->query_rows(
			[
				'orderby' => 'id',
				'order'   => 'asc',
			]
		);

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		sort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Lowercase "asc" should behave like "ASC".' );
	}

	/**
	 * An unknown column falls back to date rather than erroring or
	 * reaching the SQL.
	 */
	public function test_unknown_orderby_falls_back_to_date() {
		$rows = $this->query_rows( [ 'orderby' => 'id; DROP TABLE wp_posts' ] );

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Unknown orderby should behave like the default.' );
		$this->assertNotEmpty( $rows, 'The query should still return rows.' );
	}

	/**
	 * An unknown order direction falls back to DESC.
	 */
	public function test_unknown_order_falls_back_to_desc() {
		$rows = $this->query_rows(
			[
				'orderby' => 'id',
				'order'   => 'sideways',
			]
		);

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Unknown order should behave like DESC.' );
	}

	/**
	 * Sorting by a column other than date forces ungrouped, because
	 * occasion grouping depends on rows arriving in date order.
	 *
	 * With three distinct events, the grouped (default) query also returns
	 * subsequentOccasions === 1 for every row, so asserting only that on the
	 * non-date-ordered query would pass even without the routing change it
	 * is meant to catch. add_known_events() logs one event twice back to
	 * back so grouping has a real pair to collapse, which lets this test
	 * show the difference: 2 when grouped, 1 when the non-date order forces
	 * ungrouped.
	 */
	public function test_non_date_orderby_forces_ungrouped() {
		$this->skip_on_sqlite( 'occasion grouping is MySQL-only, so the grouped-query assertion below cannot pass on SQLite. See OccasionsGroupingDetailedTest.' );

		$log_query = new Log_Query();

		// Default order (date, grouped): the two "Alpha event" rows share an
		// occasionsID and are adjacent in date order, so they collapse into
		// one row reporting two occurrences.
		$grouped_result = $log_query->query( [ 'posts_per_page' => 50 ] );

		$grouped_alpha_row = null;

		foreach ( $grouped_result['log_rows'] as $row ) {
			if ( $row->message === 'Alpha event' ) {
				$grouped_alpha_row = $row;
				break;
			}
		}

		$this->assertNotNull( $grouped_alpha_row, 'Expected an "Alpha event" row in the grouped result.' );
		$this->assertEquals(
			2,
			(int) $grouped_alpha_row->subsequentOccasions,
			'The grouped, date-ordered query should collapse the two "Alpha event" rows into one occasion.'
		);

		// Sorting by level instead forces ungrouped.
		$ungrouped_result = $log_query->query(
			[
				'posts_per_page' => 50,
				'orderby'        => 'level',
				'order'          => 'ASC',
			]
		);

		$this->assertNotEmpty( $ungrouped_result['log_rows'] );

		// Every row in an ungrouped result reports a single occasion,
		// including the two "Alpha event" rows the grouped query above
		// collapsed into one.
		foreach ( $ungrouped_result['log_rows'] as $row ) {
			$this->assertEquals(
				1,
				(int) $row->subsequentOccasions,
				'An ungrouped result should report one occasion per row.'
			);
		}
	}
}
