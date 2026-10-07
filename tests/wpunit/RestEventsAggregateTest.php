<?php

/**
 * Test the aggregate events REST endpoint.
 *
 * The point of these is that the counts and the rows answer the same
 * question. A histogram drawn beside a table has to be counting the events
 * that table would show, or it is worse than no histogram — and in table
 * view it is the only count on screen.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit RestEventsAggregateTest
 */
class RestEventsAggregateTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		SimpleLogger()->info( 'Aggregate info event' );
		SimpleLogger()->warning( 'Aggregate warning event' );
		SimpleLogger()->error( 'Aggregate error event' );
	}

	/**
	 * Make a GET request to the aggregate endpoint.
	 *
	 * @param array $params Query parameters.
	 * @return array Buckets, keyed by bucket name.
	 */
	private function get_aggregate( $params ) {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events/aggregate' );

		$request->set_param( 'dates', 'allDates' );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );

		$buckets = [];

		foreach ( $response->get_data() as $bucket ) {
			$buckets[ $bucket['bucket'] ] = $bucket['count'];
		}

		return $buckets;
	}

	public function test_groups_by_level() {
		$buckets = $this->get_aggregate( [ 'group_by' => 'level' ] );

		$this->assertArrayHasKey( 'info', $buckets );
		$this->assertArrayHasKey( 'warning', $buckets );
		$this->assertArrayHasKey( 'error', $buckets );
	}

	/**
	 * A positive filter narrows the counts.
	 */
	public function test_a_level_filter_applies_to_the_counts() {
		$buckets = $this->get_aggregate(
			[
				'group_by'  => 'level',
				'loglevels' => [ 'warning' ],
			]
		);

		$this->assertSame( [ 'warning' ], array_keys( $buckets ) );
	}

	/**
	 * Regression: the endpoint used to share a parameter mapping that had
	 * been lifted from the has-updates route rather than from the listing,
	 * so every exclusion filter was accepted by the schema and then thrown
	 * away. The counts stayed at their unfiltered values while the rows
	 * below them narrowed — and clicking "Exclude this logger" on a cell is
	 * a one-click action, so it happened constantly.
	 */
	public function test_an_exclusion_filter_applies_to_the_counts() {
		$before = $this->get_aggregate( [ 'group_by' => 'level' ] );

		$this->assertArrayHasKey( 'info', $before );

		$after = $this->get_aggregate(
			[
				'group_by'          => 'level',
				'exclude_loglevels' => [ 'info' ],
			]
		);

		$this->assertArrayNotHasKey( 'info', $after );
		$this->assertArrayHasKey( 'warning', $after );
	}

	/**
	 * The counts and the rows have to agree. Asserted against the listing
	 * rather than against a literal, so this keeps holding as the suite's
	 * shared database grows.
	 */
	public function test_the_counts_match_what_the_listing_returns() {
		$buckets = $this->get_aggregate(
			[
				'group_by'          => 'level',
				'exclude_loglevels' => [ 'info', 'debug' ],
			]
		);

		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'dates', 'allDates' );
		$request->set_param( 'ungrouped', true );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'exclude_loglevels', [ 'info', 'debug' ] );
		$request->set_param( '_fields', 'loglevel' );

		$levels = wp_list_pluck( rest_do_request( $request )->get_data(), 'loglevel' );

		$this->assertNotEmpty( $levels );

		foreach ( array_unique( $levels ) as $level ) {
			$this->assertArrayHasKey(
				$level,
				$buckets,
				"The listing returned a {$level} event that the counts do not include."
			);
		}

		$this->assertArrayNotHasKey( 'info', $buckets );
		$this->assertArrayNotHasKey( 'debug', $buckets );
	}

	public function test_an_unknown_grouping_is_refused() {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events/aggregate' );
		$request->set_param( 'group_by', 'level; DROP TABLE wp_posts' );

		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}
}
