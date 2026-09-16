<?php

use Simple_History\Log_Query;

/**
 * Test the orderby and order arguments of Log_Query.
 *
 * Runs on MySQL/MariaDB and on SQLite: the argument only affects
 * query_overview_simple(), which is the path SQLite always takes.
 *
 * @coversDefaultClass Simple_History\Log_Query
 */
class LogQueryOrderByTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Log three events with known, different levels and loggers.
	 *
	 * @return void
	 */
	private function add_known_events() {
		// SimpleLogger() is the global helper every other test in this suite
		// uses to write events.
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
	 */
	public function test_non_date_orderby_forces_ungrouped() {
		$log_query = new Log_Query();

		$result = $log_query->query(
			[
				'posts_per_page' => 50,
				'orderby'        => 'level',
				'order'          => 'ASC',
			]
		);

		$this->assertNotEmpty( $result['log_rows'] );

		// Every row in an ungrouped result reports a single occasion.
		foreach ( $result['log_rows'] as $row ) {
			$this->assertEquals(
				1,
				(int) $row->subsequentOccasions,
				'An ungrouped result should report one occasion per row.'
			);
		}
	}
}
