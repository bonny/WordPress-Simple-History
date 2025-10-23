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

		$occasions_id = 'test_same_date_consecutive';
		$date = '2024-06-15 10:00:00';

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

		// Create 5 events with dates 1 second apart
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->notice(
				"Event $i",
				array(
					'_date' => "2024-06-15 10:00:" . str_pad( $i, 2, '0', STR_PAD_LEFT ),
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

		// Create events in this order:
		// Event A1 (10:00:00)
		// Event A2 (10:00:01)
		// Event B  (10:00:02) - Different occasionsID
		// Event A3 (10:00:03) - Same as A1/A2 but separated
		SimpleLogger()->notice( 'Event A1', array( '_date' => '2024-06-15 10:00:00', '_occasionsID' => $occasions_id_a ) );
		SimpleLogger()->notice( 'Event A2', array( '_date' => '2024-06-15 10:00:01', '_occasionsID' => $occasions_id_a ) );
		SimpleLogger()->notice( 'Event B',  array( '_date' => '2024-06-15 10:00:02', '_occasionsID' => $occasions_id_b ) );
		SimpleLogger()->notice( 'Event A3', array( '_date' => '2024-06-15 10:00:03', '_occasionsID' => $occasions_id_a ) );

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

		// Should find 2 groups (A1+A2, then A3 separately)
		$this->assertCount(
			2,
			$events_with_id_a,
			'Should have 2 separate groups because Event B separates them'
		);

		// First group should have 2 occasions (A1+A2)
		$this->assertEquals(
			2,
			$events_with_id_a[0]->subsequentOccasions,
			'First group should have A1+A2 (2 occasions)'
		);

		// Second group should have 1 occasion (A3 alone)
		$this->assertEquals(
			1,
			$events_with_id_a[1]->subsequentOccasions,
			'Second group should have A3 alone (1 occasion)'
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

		// Simulate 10 failed login attempts within 1 minute
		for ( $i = 0; $i < 10; $i++ ) {
			SimpleLogger()->warning(
				'Failed login attempt',
				array(
					'_date' => "2024-06-15 10:00:" . str_pad( $i * 5, 2, '0', STR_PAD_LEFT ), // Every 5 seconds
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

		// Create a complex scenario:
		// - Group A: 3 events (10:00:00, 10:00:01, 10:00:02)
		// - Group B: 2 events (10:00:03, 10:00:04)
		// - Group A: 2 more events (10:00:05, 10:00:06) - Should be separate from first Group A
		// - Group C: 1 event (10:00:07)

		SimpleLogger()->notice( 'A1', array( '_date' => '2024-06-15 10:00:00', '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'A2', array( '_date' => '2024-06-15 10:00:01', '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'A3', array( '_date' => '2024-06-15 10:00:02', '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'B1', array( '_date' => '2024-06-15 10:00:03', '_occasionsID' => 'group_b' ) );
		SimpleLogger()->notice( 'B2', array( '_date' => '2024-06-15 10:00:04', '_occasionsID' => 'group_b' ) );
		SimpleLogger()->notice( 'A4', array( '_date' => '2024-06-15 10:00:05', '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'A5', array( '_date' => '2024-06-15 10:00:06', '_occasionsID' => 'group_a' ) );
		SimpleLogger()->notice( 'C1', array( '_date' => '2024-06-15 10:00:07', '_occasionsID' => 'group_c' ) );

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

		// Group A should appear twice: first with 3 occasions, then with 2
		$this->assertCount( 2, $found_groups['a'] ?? array(), 'Group A should appear twice (separated by Group B)' );
		$this->assertEquals( 3, $found_groups['a'][0], 'First Group A should have 3 occasions' );
		$this->assertEquals( 2, $found_groups['a'][1], 'Second Group A should have 2 occasions' );

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

		// Create 5 events
		$created_ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->notice(
				"Occasion event $i",
				array(
					'_date' => "2024-06-15 10:00:" . str_pad( $i, 2, '0', STR_PAD_LEFT ),
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

		// Now query the occasions for this event
		$log_query = new Log_Query();
		$occasions = $log_query->query_occasions(
			array(
				'occasions_id' => $grouped_event->occasionsID,
				'min_id' => $grouped_event->id,
			)
		);

		$this->assertCount(
			5,
			$occasions,
			'query_occasions should return all 5 events in the group'
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
