<?php

use Simple_History\Log_Query;

class DateOrderingDataIntegrityTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Test that no events are lost after changing ORDER BY.
	 * Verifies that:
	 * - Total count remains accurate
	 * - All event IDs are accessible
	 * - Pagination returns all events
	 * - No duplicates appear
	 */
	function test_no_events_lost_with_date_ordering() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		// Create 50 test events with various dates
		$created_ids = array();
		for ( $i = 0; $i < 50; $i++ ) {
			// Random dates between 2024-01-01 and 2024-12-31
			$random_month = rand( 1, 12 );
			$random_day = rand( 1, 28 );
			$date = sprintf( '2024-%02d-%02d 10:00:00', $random_month, $random_day );

			SimpleLogger()->notice(
				"Test event $i",
				array(
					'_date' => $date,
					'_occasionsID' => "test_integrity_event_$i",
				)
			);
		}

		// Get total count from query
		$log_query = new Log_Query();
		$query_results = $log_query->query(
			array(
				'posts_per_page' => 1, // Just get count
			)
		);

		$total_count = $query_results['total_row_count'];

		$this->assertGreaterThanOrEqual(
			50,
			$total_count,
			'Should have at least 50 events (our test events)'
		);

		// Now paginate through ALL events and collect IDs
		$collected_ids = array();
		$page = 1;
		$per_page = 10;
		$max_pages = ceil( $total_count / $per_page );

		while ( $page <= $max_pages ) {
			$log_query = new Log_Query();
			$page_results = $log_query->query(
				array(
					'posts_per_page' => $per_page,
					'paged' => $page,
				)
			);

			foreach ( $page_results['log_rows'] as $event ) {
				$collected_ids[] = $event->id;
			}

			$page++;
		}

		// Verify counts match
		$this->assertEquals(
			$total_count,
			count( $collected_ids ),
			'Number of collected events should match total_row_count'
		);

		// Verify no duplicates
		$unique_ids = array_unique( $collected_ids );
		$this->assertEquals(
			count( $collected_ids ),
			count( $unique_ids ),
			'Should have no duplicate event IDs across pagination'
		);

		// Verify all IDs are in descending date order (with ID as tiebreaker)
		$log_query = new Log_Query();
		$all_events = $log_query->query(
			array(
				'posts_per_page' => $total_count,
			)
		);

		$events = array_values( $all_events['log_rows'] );

		for ( $i = 0; $i < count( $events ) - 1; $i++ ) {
			$current = $events[ $i ];
			$next = $events[ $i + 1 ];

			// Date should be descending (current >= next)
			$this->assertGreaterThanOrEqual(
				$next->date,
				$current->date,
				"Event $i (ID: {$current->id}, date: {$current->date}) should have date >= Event " . ( $i + 1 ) . " (ID: {$next->id}, date: {$next->date})"
			);

			// If dates are equal, ID should be descending
			if ( $current->date === $next->date ) {
				$this->assertGreaterThan(
					$next->id,
					$current->id,
					"When dates equal, Event $i (ID: {$current->id}) should have ID > Event " . ( $i + 1 ) . " (ID: {$next->id})"
				);
			}
		}
	}

	/**
	 * Test that min_id and max_id pagination references still work correctly.
	 */
	function test_pagination_with_mixed_dates() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		// Create events with dates that don't match ID order
		SimpleLogger()->notice( 'New event, recent date', array( '_date' => '2024-12-01 10:00:00' ) );
		SimpleLogger()->notice( 'Old event, old date', array( '_date' => '2024-01-01 10:00:00' ) );
		SimpleLogger()->notice( 'Middle event, middle date', array( '_date' => '2024-06-01 10:00:00' ) );

		// Get page 1
		$log_query = new Log_Query();
		$page1 = $log_query->query(
			array(
				'posts_per_page' => 2,
				'paged' => 1,
			)
		);

		$this->assertEquals( 2, count( $page1['log_rows'] ), 'Page 1 should have 2 events' );

		// Get page 2
		$log_query = new Log_Query();
		$page2 = $log_query->query(
			array(
				'posts_per_page' => 2,
				'paged' => 2,
			)
		);

		$this->assertGreaterThanOrEqual( 1, count( $page2['log_rows'] ), 'Page 2 should have at least 1 event' );

		// Verify no overlap between pages
		$page1_ids = array_map( function( $e ) { return $e->id; }, array_values( $page1['log_rows'] ) );
		$page2_ids = array_map( function( $e ) { return $e->id; }, array_values( $page2['log_rows'] ) );

		$intersection = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty(
			$intersection,
			'Pages should not contain duplicate events. Found overlap: ' . implode( ', ', $intersection )
		);
	}

	/**
	 * Test that occasions grouping doesn't cause events to disappear.
	 */
	function test_grouped_events_not_lost() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		// Create 5 events with same occasionsID but different dates
		$occasions_id = 'test_grouped_events_123';
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->notice(
				"Grouped event $i",
				array(
					'_date' => "2024-06-" . str_pad( $i + 1, 2, '0', STR_PAD_LEFT ) . " 10:00:00",
					'_occasionsID' => $occasions_id,
				)
			);
		}

		// Query with grouping (default)
		$log_query = new Log_Query();
		$grouped_results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		// Find our grouped event
		$our_grouped_event = null;
		foreach ( $grouped_results['log_rows'] as $event ) {
			if ( $event->occasionsID === md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) ) ) {
				$our_grouped_event = $event;
				break;
			}
		}

		$this->assertNotNull( $our_grouped_event, 'Should find our grouped event' );
		$this->assertEquals( 5, $our_grouped_event->subsequentOccasions, 'Should show 5 occasions grouped together' );

		// Query without grouping to verify all 5 are still in database
		$log_query = new Log_Query();
		$ungrouped_results = $log_query->query(
			array(
				'posts_per_page' => 100,
				'ungrouped' => true,
			)
		);

		$ungrouped_count = 0;
		foreach ( $ungrouped_results['log_rows'] as $event ) {
			if ( strpos( $event->message, 'Grouped event' ) !== false ) {
				$ungrouped_count++;
			}
		}

		$this->assertEquals( 5, $ungrouped_count, 'Should find all 5 individual events when ungrouped' );
	}
}
