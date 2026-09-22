<?php

/**
 * Test that the events endpoint still groups occasions and still returns
 * sticky events.
 *
 * These are the two things the default listing does that the ungrouped query
 * cannot, and both were lost once when a `$args['ungrouped'] = true` that
 * belonged to the "has updates" check was moved into the argument mapping
 * that get_items() shares with it. Nothing caught it: the occasion-grouping
 * tests call Log_Query directly and never go through REST, so the whole
 * suite stayed green while the log lost its "+N similar events" grouping and
 * its pinned events.
 *
 * So these assertions are deliberately made at the REST layer. The point is
 * not that Log_Query can group — that is tested elsewhere — but that a plain
 * GET of the events endpoint still asks it to.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit RestEventsGroupingTest
 */
class RestEventsGroupingTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var int
	 */
	private $user_id;

	public function setUp(): void {
		parent::setUp();

		$this->user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->user_id );
	}

	/**
	 * Make a GET request to the events endpoint.
	 *
	 * @param array $params Query parameters.
	 * @return array The response data.
	 */
	private function get_events( $params = [] ) {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );

		$request->set_param( 'per_page', 50 );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request )->get_data();
	}

	/**
	 * Repeated identical events collapse into one row carrying the count.
	 */
	public function test_default_listing_groups_repeated_events() {
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->info( 'A repeated event' );
		}

		$counts = wp_list_pluck( $this->get_events(), 'subsequent_occasions_count' );

		$this->assertContains(
			5,
			array_map( 'intval', $counts ),
			'The five identical events should arrive as one row with a count of 5. Getting five rows of 1 means the listing is running ungrouped.'
		);
	}

	/**
	 * The public parameter still turns grouping off.
	 */
	public function test_ungrouped_parameter_returns_every_row_separately() {
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->info( 'A repeated event' );
		}

		$counts = array_map(
			'intval',
			wp_list_pluck( $this->get_events( [ 'ungrouped' => true ] ), 'subsequent_occasions_count' )
		);

		$this->assertSame( [ 1 ], array_values( array_unique( $counts ) ) );
	}

	/**
	 * Pinned events are only handled by the grouped query, so they are the
	 * other half of the same regression.
	 */
	public function test_sticky_events_are_included_by_default() {
		SimpleLogger()->info( 'An event that gets pinned' );

		$rows = ( new \Simple_History\Log_Query() )->query(
			[
				'posts_per_page' => 1,
				'ungrouped'      => true,
			]
		);

		$event = \Simple_History\Event::get( (int) $rows['log_rows'][0]->id );

		$this->assertTrue( $event->stick(), 'Could not pin the event, so the rest of this test proves nothing.' );

		// Push the pinned event well out of the first page.
		for ( $i = 0; $i < 60; $i++ ) {
			SimpleLogger()->info( 'Filler event ' . $i );
		}

		$ids = array_map( 'intval', wp_list_pluck( $this->get_events( [ 'include_sticky' => true ] ), 'id' ) );

		$this->assertContains(
			$event->get_id(),
			$ids,
			'A pinned event should be appended to the first page. Its absence means the listing is running ungrouped, where include_sticky is not handled at all.'
		);
	}
}
