<?php

/**
 * Test the orderby and order query parameters of the events REST endpoint.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit RestEventsOrderByTest
 */
class RestEventsOrderByTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		SimpleLogger()->info( 'Alpha event' );
		SimpleLogger()->warning( 'Beta event' );
		SimpleLogger()->debug( 'Gamma event' );
	}

	/**
	 * Make a GET request to the events endpoint.
	 *
	 * @param array $params Query parameters.
	 * @return WP_REST_Response
	 */
	private function get_events( $params ) {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );

		$request->set_param( 'per_page', 50 );
		$request->set_param( 'ungrouped', true );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	public function test_order_by_id_asc_returns_oldest_first() {
		$response = $this->get_events(
			[
				'orderby' => 'id',
				'order'   => 'asc',
			]
		);

		$this->assertSame( 200, $response->get_status() );

		$ids = wp_list_pluck( $response->get_data(), 'id' );

		$sorted = $ids;
		sort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids );
	}

	public function test_default_is_newest_first() {
		$response = $this->get_events( [] );

		$this->assertSame( 200, $response->get_status() );

		$ids = wp_list_pluck( $response->get_data(), 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids );
	}

	public function test_invalid_orderby_is_rejected() {
		$response = $this->get_events( [ 'orderby' => 'nonsense' ] );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_invalid_order_is_rejected() {
		$response = $this->get_events( [ 'order' => 'sideways' ] );

		$this->assertSame( 400, $response->get_status() );
	}
}
