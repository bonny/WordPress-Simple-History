<?php

use Simple_History\Log_Query;

/**
 * Test the exact scenario from issue #583/#584:
 * Imported historical data has high IDs but old dates.
 * Verify they appear in correct chronological order.
 */
class ImportedDataScenarioTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Simulate the exact scenario: existing events + imported old events.
	 */
	function test_imported_historical_data_displays_chronologically() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		// Step 1: Create "existing" events from 2024 (normal operation)
		$existing_events = array();
		for ( $i = 0; $i < 10; $i++ ) {
			SimpleLogger()->notice(
				"Existing 2024 event $i",
				array(
					'_date' => "2024-0" . ( $i % 9 + 1 ) . "-15 10:00:00",
					'_occasionsID' => "existing_event_$i",
				)
			);
		}

		// Step 2: Simulate importing "old" historical data from 2020-2023
		// These will have HIGHER IDs but OLDER dates (the problem case!)
		$imported_events = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$year = 2020 + ( $i % 4 ); // 2020-2023
			SimpleLogger()->notice(
				"Imported historical event from $year",
				array(
					'_date' => "$year-06-15 10:00:00",
					'_occasionsID' => "imported_event_$i",
					'_imported' => true, // Mark as imported for testing
				)
			);
		}

		// Step 3: Query all events
		$log_query = new Log_Query();
		$query_results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		$events = array_values( $query_results['log_rows'] );

		$this->assertGreaterThanOrEqual( 20, count( $events ), 'Should have at least 20 events' );

		// Step 4: Verify chronological order (newest first)
		// Expected: 2024 events should appear BEFORE 2020-2023 events
		$first_2024_index = null;
		$last_2024_index = null;
		$first_2020s_index = null;
		$last_2020s_index = null;

		foreach ( $events as $index => $event ) {
			$year = intval( substr( $event->date, 0, 4 ) );

			if ( $year === 2024 ) {
				if ( $first_2024_index === null ) {
					$first_2024_index = $index;
				}
				$last_2024_index = $index;
			} elseif ( $year >= 2020 && $year <= 2023 ) {
				if ( $first_2020s_index === null ) {
					$first_2020s_index = $index;
				}
				$last_2020s_index = $index;
			}
		}

		// Critical assertion: ALL 2024 events must come BEFORE ALL 2020-2023 events
		if ( $last_2024_index !== null && $first_2020s_index !== null ) {
			$this->assertLessThan(
				$first_2020s_index,
				$last_2024_index,
				sprintf(
					'All 2024 events (last at index %d) must appear BEFORE 2020-2023 events (first at index %d). This is the core requirement for issue #584!',
					$last_2024_index,
					$first_2020s_index
				)
			);
		}

		// Step 5: Verify entire list is in descending date order
		for ( $i = 0; $i < count( $events ) - 1; $i++ ) {
			$current_date = $events[ $i ]->date;
			$next_date = $events[ $i + 1 ]->date;

			$this->assertGreaterThanOrEqual(
				$next_date,
				$current_date,
				sprintf(
					'Event %d (date: %s, msg: %s) must have date >= Event %d (date: %s, msg: %s)',
					$i,
					$current_date,
					substr( $events[ $i ]->message, 0, 30 ),
					$i + 1,
					$next_date,
					substr( $events[ $i + 1 ]->message, 0, 30 )
				)
			);
		}

		// Step 6: Verify IDs don't dictate order
		// If we sorted by ID DESC (old behavior), imported events would appear first
		// because they have higher IDs
		$event_ids = array_map( function( $e ) { return $e->id; }, $events );
		$sorted_by_id = $event_ids;
		rsort( $sorted_by_id ); // Sort descending by ID

		$this->assertNotEquals(
			$sorted_by_id,
			$event_ids,
			'Events should NOT be in ID order (that would be the bug!)'
		);
	}

	/**
	 * Test that pagination works correctly with mixed old/new dates.
	 */
	function test_imported_data_pagination() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		// Create events: recent events, then imported old events
		SimpleLogger()->notice( 'Recent event 1', array( '_date' => '2024-12-01 10:00:00' ) );
		SimpleLogger()->notice( 'Recent event 2', array( '_date' => '2024-11-01 10:00:00' ) );
		SimpleLogger()->notice( 'Imported old event 1', array( '_date' => '2020-01-01 10:00:00' ) ); // High ID, old date
		SimpleLogger()->notice( 'Imported old event 2', array( '_date' => '2019-01-01 10:00:00' ) ); // High ID, old date

		// Query page 1 (should have most recent events)
		$log_query = new Log_Query();
		$page1 = $log_query->query(
			array(
				'posts_per_page' => 2,
				'paged' => 1,
			)
		);

		$page1_events = array_values( $page1['log_rows'] );

		// Page 1 should have most recent events (2024 or newer)
		$page1_year = intval( substr( $page1_events[0]->date, 0, 4 ) );
		$this->assertGreaterThanOrEqual( 2024, $page1_year, 'Page 1 first event should be from 2024 or newer' );

		// Query page 2 (should have older events)
		$log_query = new Log_Query();
		$page2 = $log_query->query(
			array(
				'posts_per_page' => 2,
				'paged' => 2,
			)
		);

		$page2_events = array_values( $page2['log_rows'] );

		// Page 2 might have 2020/2019 events or other old events
		if ( count( $page2_events ) > 0 ) {
			// Just verify no event from page 2 is newer than page 1's last event
			$page1_last_date = end( $page1_events )->date;
			$page2_first_date = $page2_events[0]->date;

			$this->assertLessThanOrEqual(
				$page1_last_date,
				$page2_first_date,
				'Page 2 events should not be newer than page 1 events'
			);
		}
	}
}
