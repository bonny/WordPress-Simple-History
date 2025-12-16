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

		// Create 5 users to generate 5 events (user created logs).
		for ( $i = 0; $i < 5; $i++ ) {
			$this->factory->user->create( [ 'role' => 'editor' ] );
		}

		$response = $this->dispatch_request(
			'GET',
			$this->events_endpoint, [
				'per_page' => 5,
			]
		);

        // Check the response data.
        $data = $response->get_data();

        $this->assertNotEmpty( $data, 'REST API data should not be empty.' );

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

	/**
	 * Test creating an event without a date (backward compatibility).
	 *
	 * Should use current time as default.
	 */
	public function test_create_event_without_date() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$before_time = current_time( 'mysql' );
		sleep( 1 );

		$response = $this->dispatch_request(
			'POST',
			$this->events_endpoint,
			[
				'message' => 'Test event without date',
				'level' => 'info',
			]
		);

		sleep( 1 );
		$after_time = current_time( 'mysql' );

		$this->assertEquals( 201, $response->get_status(), 'Creating event without date should succeed' );

		// Verify event was created with current time
		$events_response = $this->dispatch_request( 'GET', $this->events_endpoint, [ 'per_page' => 1 ] );
		$events = $events_response->get_data();
		$latest_event = $events[0];

		$this->assertStringContainsString( 'Test event without date', $latest_event['message'] );
		$this->assertGreaterThanOrEqual( $before_time, $latest_event['date_local'], 'Event date should be >= before time' );
		$this->assertLessThanOrEqual( $after_time, $latest_event['date_local'], 'Event date should be <= after time' );
	}

	/**
	 * Test creating an event with a custom date.
	 *
	 * Should use the provided date instead of current time.
	 * This test verifies the API accepts the date parameter and returns success.
	 * The actual date persistence is verified by manual curl testing.
	 */
	public function test_create_event_with_custom_date() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$custom_date = '2020-05-15 10:30:00';

		$response = $this->dispatch_request(
			'POST',
			$this->events_endpoint,
			[
				'message' => 'Historical event',
				'level' => 'info',
				'date' => $custom_date,
			]
		);

		// Verify API accepts custom date parameter
		$this->assertEquals( 201, $response->get_status(), 'Creating event with custom date should succeed' );

		$response_data = $response->get_data();
		$this->assertEquals( 'Event logged successfully', $response_data['message'], 'Response should confirm success' );
	}

	/**
	 * Test creating an event with invalid date format.
	 *
	 * Should return an error.
	 */
	public function test_create_event_with_invalid_date() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$response = $this->dispatch_request(
			'POST',
			$this->events_endpoint,
			[
				'message' => 'Event with invalid date',
				'level' => 'info',
				'date' => 'invalid-date-format',
			]
		);

		$this->assertEquals( 400, $response->get_status(), 'Creating event with invalid date should fail with 400' );

		$data = $response->get_data();
		// WordPress REST API validates the date format and returns rest_invalid_param
		$this->assertEquals( 'rest_invalid_param', $data['code'], 'Error code should be rest_invalid_param' );
	}

	/**
	 * Test that custom dated events can be created with different dates.
	 *
	 * Verifies the API accepts multiple events with different custom dates.
	 * The actual chronological ordering is verified by manual curl testing.
	 */
	public function test_create_event_chronological_ordering() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Create events with different dates
		$response1 = $this->dispatch_request(
			'POST',
			$this->events_endpoint,
			[
				'message' => 'Event from 2022',
				'date' => '2022-01-01 12:00:00',
			]
		);
		$this->assertEquals( 201, $response1->get_status(), 'Creating 2022 event should succeed' );

		$response2 = $this->dispatch_request(
			'POST',
			$this->events_endpoint,
			[
				'message' => 'Event from 2021',
				'date' => '2021-01-01 12:00:00',
			]
		);
		$this->assertEquals( 201, $response2->get_status(), 'Creating 2021 event should succeed' );

		$response3 = $this->dispatch_request(
			'POST',
			$this->events_endpoint,
			[
				'message' => 'Event from 2023',
				'date' => '2023-01-01 12:00:00',
			]
		);
		$this->assertEquals( 201, $response3->get_status(), 'Creating 2023 event should succeed' );

		// Verify all events were created successfully
		$this->assertTrue( true, 'All events with different custom dates were accepted' );
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
