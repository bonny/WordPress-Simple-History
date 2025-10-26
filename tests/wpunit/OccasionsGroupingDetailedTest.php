<?php

use Simple_History\Log_Query;

/**
 * Comprehensive test for occasions grouping logic after changing to date ordering.
 *
 * The grouping uses MySQL variables to count consecutive rows with same occasionsID:
 * IF(@a=occasionsID, @counter:=@counter+1, @counter:=1)
 *
 * This only works if rows with same occasionsID are consecutive in the result set.
 */
class OccasionsGroupingDetailedTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test 1: Same occasionsID, same date, consecutive IDs
	 * This should definitely group.
	 */
	function test_grouping_same_date_consecutive_ids() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );


		// Use unique ID and RECENT date so events appear on first page of results
		$occasions_id = 'test_same_date_' . uniqid();
		$date = gmdate( 'Y-m-d H:i:s', time() + 10 ); // Use recent date (slightly in future to ensure top of list)

		// Create 5 events with same date and occasionsID
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->notice(
				"Event $i",
				array(
					'_date' => $date,
					'_occasionsID' => $occasions_id,
				)
			);
		}

		// Query with grouping
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		// Find our event
		$found_event = null;
		$expected_occasions_id = md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) );

		$event_count = 0;
		foreach ( $results['log_rows'] as $event ) {
			if ( strpos( $event->message, 'Event' ) !== false ) {
				$event_count++;
			}
			if ( $event->occasionsID === $expected_occasions_id ) {
				$found_event = $event;
				break;
			}
		}

		$this->assertNotNull( $found_event, 'Should find grouped event' );
		$this->assertEquals(
			5,
			$found_event->subsequentOccasions,
			'Should group all 5 events with same date and occasionsID'
		);
	}

	/**
	 * Test 2: Same occasionsID, different dates (seconds apart)
	 * With date ordering, these should still be consecutive and group.
	 */
	function test_grouping_different_dates_seconds_apart() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id = 'test_seconds_apart';

		// Create 5 events with dates 1 second apart - use recent dates
		$base_time = time() + 10;
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->notice(
				"Event $i",
				array(
					'_date' => gmdate( 'Y-m-d H:i:s', $base_time + $i ),
					'_occasionsID' => $occasions_id,
				)
			);
		}

		// Query with grouping
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		// Find our event
		$found_event = null;
		$expected_occasions_id = md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) );

		foreach ( $results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id ) {
				$found_event = $event;
				break;
			}
		}

		$this->assertNotNull( $found_event, 'Should find grouped event' );
		$this->assertEquals(
			5,
			$found_event->subsequentOccasions,
			'Should group all 5 events even with different timestamps (consecutive in date order)'
		);
	}

	/**
	 * Test 3: Same occasionsID, but separated by OTHER event
	 * This should NOT group because they're not consecutive.
	 */
	function test_no_grouping_when_separated_by_other_event() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id_a = 'test_separated_a';
		$occasions_id_b = 'test_separated_b';

		// Create events in this order - use recent dates:
		// Event A1 (T+10)
		// Event A2 (T+11)
		// Event B  (T+12) - Different occasionsID
		// Event A3 (T+13) - Same as A1/A2 but separated
		$base_time = time() + 10;
		SimpleLogger()->notice( 'Event A1', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time ), '_occasionsID' => $occasions_id_a ) );
		SimpleLogger()->notice( 'Event A2', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 1 ), '_occasionsID' => $occasions_id_a ) );
		SimpleLogger()->notice( 'Event B',  array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 2 ), '_occasionsID' => $occasions_id_b ) );
		SimpleLogger()->notice( 'Event A3', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 3 ), '_occasionsID' => $occasions_id_a ) );

		// Query with grouping
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		// Find events with occasionsID A
		$expected_occasions_id_a = md5( json_encode( array( '_occasionsID' => $occasions_id_a, '_loggerSlug' => 'SimpleLogger' ) ) );

		$events_with_id_a = array();
		foreach ( $results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id_a ) {
				$events_with_id_a[] = $event;
			}
		}

		// Should find 2 groups (A3 first due to DESC ordering, then A1+A2)
		$this->assertCount(
			2,
			$events_with_id_a,
			'Should have 2 separate groups because Event B separates them'
		);

		// With DESC date ordering, newest comes first
		// First group (index 0) should be A3 alone (1 occasion)
		$this->assertEquals(
			1,
			$events_with_id_a[0]->subsequentOccasions,
			'First group (newest) should be A3 alone (1 occasion)'
		);

		// Second group (index 1) should be A1+A2 (2 occasions)
		$this->assertEquals(
			2,
			$events_with_id_a[1]->subsequentOccasions,
			'Second group (oldest) should be A1+A2 (2 occasions)'
		);
	}

	/**
	 * Test 4: Real-world scenario - failed login attempts over time
	 */
	function test_failed_login_grouping_scenario() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id = 'failed_login_attack_123';

		// Simulate 10 failed login attempts within 1 minute - use recent dates
		$base_time = time() + 10;
		for ( $i = 0; $i < 10; $i++ ) {
			SimpleLogger()->warning(
				'Failed login attempt',
				array(
					'_date' => gmdate( 'Y-m-d H:i:s', $base_time + ( $i * 5 ) ), // Every 5 seconds
					'_occasionsID' => $occasions_id,
					'username' => 'admin',
				)
			);
		}

		// Query with grouping
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		// Find our grouped event
		$found_event = null;
		$expected_occasions_id = md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) );

		foreach ( $results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id ) {
				$found_event = $event;
				break;
			}
		}

		$this->assertNotNull( $found_event, 'Should find grouped failed login event' );
		$this->assertEquals(
			10,
			$found_event->subsequentOccasions,
			'Should group all 10 failed login attempts'
		);
	}

	/**
	 * Test 5: Mixed scenario - multiple groups with interleaved events
	 */
	function test_complex_grouping_scenario() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		// Create a complex scenario - use recent dates:
		// - Group A: 3 events (T+10, T+11, T+12)
		// - Group B: 2 events (T+13, T+14)
		// - Group A: 2 more events (T+15, T+16) - Should be separate from first Group A
		// - Group C: 1 event (T+17)
		$base_time = time() + 10;

		SimpleLogger()->notice( 'A1', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time ), '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'A2', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 1 ), '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'A3', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 2 ), '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'B1', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 3 ), '_occasionsID' => 'group_b' ) );
		SimpleLogger()->notice( 'B2', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 4 ), '_occasionsID' => 'group_b' ) );
		SimpleLogger()->notice( 'A4', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 5 ), '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'A5', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 6 ), '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'C1', array( '_date' => gmdate( 'Y-m-d H:i:s', $base_time + 7 ), '_occasionsID' => 'group_c' ) );

		// Query with grouping
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		$events = array_values( $results['log_rows'] );

		// Verify the groups
		$group_a_id = md5( json_encode( array( '_occasionsID' => 'group_a', '_loggerSlug' => 'SimpleLogger' ) ) );
		$group_b_id = md5( json_encode( array( '_occasionsID' => 'group_b', '_loggerSlug' => 'SimpleLogger' ) ) );
		$group_c_id = md5( json_encode( array( '_occasionsID' => 'group_c', '_loggerSlug' => 'SimpleLogger' ) ) );

		$found_groups = array();
		foreach ( $events as $event ) {
			if ( $event->occasionsID === $group_a_id ) {
				$found_groups['a'][] = $event->subsequentOccasions;
			} elseif ( $event->occasionsID === $group_b_id ) {
				$found_groups['b'][] = $event->subsequentOccasions;
			} elseif ( $event->occasionsID === $group_c_id ) {
				$found_groups['c'][] = $event->subsequentOccasions;
			}
		}

		// Group A should appear twice: with DESC date ordering, newest (A4+A5=2) comes first, then oldest (A1+A2+A3=3)
		$this->assertCount( 2, $found_groups['a'] ?? array(), 'Group A should appear twice (separated by Group B)' );
		$this->assertEquals( 2, $found_groups['a'][0], 'First Group A (newest: A4+A5) should have 2 occasions' );
		$this->assertEquals( 3, $found_groups['a'][1], 'Second Group A (oldest: A1+A2+A3) should have 3 occasions' );

		// Group B should appear once with 2 occasions
		$this->assertCount( 1, $found_groups['b'] ?? array(), 'Group B should appear once' );
		$this->assertEquals( 2, $found_groups['b'][0], 'Group B should have 2 occasions' );

		// Group C should appear once with 1 occasion
		$this->assertCount( 1, $found_groups['c'] ?? array(), 'Group C should appear once' );
		$this->assertEquals( 1, $found_groups['c'][0], 'Group C should have 1 occasion' );
	}

	/**
	 * Test 6: Verify query_occasions() returns correct subsequent events
	 */
	function test_query_occasions_returns_all_events() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id = 'test_query_occasions';

		// Create 5 events - use recent dates
		$base_time = time() + 10;
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->notice(
				"Occasion event $i",
				array(
					'_date' => gmdate( 'Y-m-d H:i:s', $base_time + $i ),
					'_occasionsID' => $occasions_id,
				)
			);
		}

		// Get the grouped event
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		$expected_occasions_id = md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) );

		$grouped_event = null;
		foreach ( $results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id ) {
				$grouped_event = $event;
				break;
			}
		}

		$this->assertNotNull( $grouped_event, 'Should find grouped event' );

		// Now query the occasions for this event using public API
		$log_query = new Log_Query();
		$occasions_result = $log_query->query(
			array(
				'type' => 'occasions',
				'logRowID' => $grouped_event->id,
				'occasionsID' => $grouped_event->occasionsID,
				'occasionsCount' => $grouped_event->subsequentOccasions - 1, // -1 because logRowID is the first one
				'occasionsCountMaxReturn' => 100,
			)
		);

		$occasions = $occasions_result['log_rows'];

		$this->assertCount(
			4,
			$occasions,
			'query occasions should return 4 events (the 5th is the logRowID itself)'
		);

		// Verify they're in descending date order
		for ( $i = 0; $i < count( $occasions ) - 1; $i++ ) {
			$this->assertGreaterThanOrEqual(
				$occasions[ $i + 1 ]->date,
				$occasions[ $i ]->date,
				'Occasions should be in descending date order'
			);
		}
	}
}
