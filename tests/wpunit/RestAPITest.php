<?php

class RestAPITest extends \Codeception\TestCase\WPTestCase {
	private $events_endpoint = '/simple-history/v1/events';

    public function test_events_endpoint_unauthorized() {
		$response = $this->dispatch_request( 'GET', $this->events_endpoint );
        
		$this->assertEquals( 401, $response->get_status(), 'Status from REST API should be 401 since we are not authenticated.' );
	}
	

	public function test_events_endpoint_authorized() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$response = $this->dispatch_request( 'GET', $this->events_endpoint );
        
		$this->assertEquals( 200, $response->get_status(), 'Status from REST API should be 200 since we are authenticated.' );
    }

	public function test_events_endpoint_authorized_data() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->factory->user->create( [ 'role' => 'editor' ] );

		$response = $this->dispatch_request( 
			'GET', 
			$this->events_endpoint, [
				'per_page' => 5,
			] 
		);
        
        // Check the response data.
        $data = $response->get_data();

        $this->assertNotEmpty( $data, 'REST API data should not be empty.' );

		// This uses to work because the example db contained more items, but it's cleaned sometimes now in another test.
		$this->assertCount( 5, $data, 'REST API data should contain 5 items.' );

		$this->assertStringContainsString( 'Created user', $data[0]['message'], 'First message should contain "created user".' );
    }

	/**
	 * Test that backdated events are NOT counted as new.
	 *
	 * Scenario: User backdates an entry. It gets a high ID but old date.
	 * Expected: Should NOT be counted as new event since date is older.
	 */
	public function test_has_updates_backdated_event_not_counted() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Create a baseline event
		\SimpleLogger()->info( 'Baseline event' );

		// Get the baseline event using Log_Query
		$log_query = new \Simple_History\Log_Query();
		$query_results = $log_query->query( [ 'posts_per_page' => 1 ] );
		$baseline_event = $query_results['log_rows'][0];
		$baseline_max_id = $baseline_event->id;
		$baseline_max_date = $baseline_event->date;

		// Create a backdated event (simulating imported historical data)
		// This will have a higher ID but older date
		$old_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'simple_history',
			[
				'date' => $old_date,
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'message' => 'Backdated event',
				'initiator' => 'wp_user',
			]
		);

		// Check for new events using has-updates endpoint
		$response = $this->dispatch_request(
			'GET',
			$this->events_endpoint . '/has-updates',
			[
				'since_id' => $baseline_max_id,
				'since_date' => $baseline_max_date,
			]
		);

		$data = $response->get_data();
		$this->assertEquals( 0, $data['new_events_count'], 'Backdated event should NOT be counted as new' );
	}

	/**
	 * Test that future events ARE counted as new.
	 */
	public function test_has_updates_future_event_counted() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Create a baseline event
		\SimpleLogger()->info( 'Baseline event' );

		// Get the baseline event using Log_Query
		$log_query = new \Simple_History\Log_Query();
		$query_results = $log_query->query( [ 'posts_per_page' => 1 ] );
		$baseline_event = $query_results['log_rows'][0];
		$baseline_max_id = $baseline_event->id;
		$baseline_max_date = $baseline_event->date;

		// Wait a moment to ensure different timestamp
		sleep( 1 );

		// Create a new event with current timestamp
		\SimpleLogger()->info( 'New event' );

		// Check for new events
		$response = $this->dispatch_request(
			'GET',
			$this->events_endpoint . '/has-updates',
			[
				'since_id' => $baseline_max_id,
				'since_date' => $baseline_max_date,
			]
		);

		$data = $response->get_data();
		$this->assertEquals( 1, $data['new_events_count'], 'New event should be counted' );
	}

	/**
	 * Test that events with same date but higher ID ARE counted as new.
	 */
	public function test_has_updates_same_date_higher_id_counted() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Create baseline event
		\SimpleLogger()->info( 'Baseline event' );

		// Get the baseline event using Log_Query
		$log_query = new \Simple_History\Log_Query();
		$query_results = $log_query->query( [ 'posts_per_page' => 1 ] );
		$baseline_event = $query_results['log_rows'][0];
		$baseline_max_id = $baseline_event->id;
		$baseline_max_date = $baseline_event->date;

		// Create another event with the exact same date but higher ID
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'simple_history',
			[
				'date' => $baseline_max_date,
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'message' => 'Same date event',
				'initiator' => 'wp_user',
			]
		);

		// Check for new events
		$response = $this->dispatch_request(
			'GET',
			$this->events_endpoint . '/has-updates',
			[
				'since_id' => $baseline_max_id,
				'since_date' => $baseline_max_date,
			]
		);

		$data = $response->get_data();
		$this->assertEquals( 1, $data['new_events_count'], 'Event with same date but higher ID should be counted' );
	}

	/**
	 * Test backward compatibility: when only since_id is provided.
	 */
	public function test_has_updates_backward_compatibility_id_only() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Create baseline event
		\SimpleLogger()->info( 'Baseline event' );

		// Get the baseline event using Log_Query
		$log_query = new \Simple_History\Log_Query();
		$query_results = $log_query->query( [ 'posts_per_page' => 1 ] );
		$baseline_event = $query_results['log_rows'][0];
		$baseline_max_id = $baseline_event->id;

		// Wait and create new event
		sleep( 1 );
		\SimpleLogger()->info( 'New event' );

		// Check for new events using only since_id (old behavior)
		$response = $this->dispatch_request(
			'GET',
			$this->events_endpoint . '/has-updates',
			[
				'since_id' => $baseline_max_id,
			]
		);

		$data = $response->get_data();
		$this->assertEquals( 1, $data['new_events_count'], 'Backward compatibility: should still work with only since_id' );
	}

    // Utility method to dispatch REST API requests
    private function dispatch_request( $method, $route, $params = [] ) {
        $request = new WP_REST_Request( $method, $route );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }
        return rest_do_request( $request );
    }
}
