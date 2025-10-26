<?php

use Simple_History\Log_Query;

/**
 * Isolated test for occasions grouping with unique timestamps
 * to avoid interference from other test data.
 */
class OccasionsGroupingIsolatedTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test basic grouping with isolated timestamps.
	 */
	function test_basic_grouping_isolated() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id = 'test_isolated_' . uniqid();
		$base_timestamp = '2010-01-01 10:00:00'; // Far in the past to avoid collisions

		// Create exactly 5 events with same occasionsID, consecutive timestamps
		for ( $i = 0; $i < 5; $i++ ) {
			SimpleLogger()->notice(
				"Isolated event $i",
				array(
					'_date' => date( 'Y-m-d H:i:s', strtotime( $base_timestamp ) + $i ),
					'_occasionsID' => $occasions_id,
				)
			);
		}

		// Query ONLY our events using ungrouped mode first to verify they exist
		$log_query = new Log_Query();
		$ungrouped_results = $log_query->query(
			array(
				'posts_per_page' => 100,
				'ungrouped' => true,
			)
		);

		// Count our events
		$our_events_count = 0;
		foreach ( $ungrouped_results['log_rows'] as $event ) {
			if ( strpos( $event->message, 'Isolated event' ) !== false ) {
				$our_events_count++;
			}
		}

		$this->assertEquals( 5, $our_events_count, 'Should have created 5 events' );

		// Now query with grouping
		$log_query = new Log_Query();
		$grouped_results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		// Find our grouped event
		$expected_occasions_id = md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) );

		$grouped_event = null;
		foreach ( $grouped_results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id ) {
				$grouped_event = $event;
				break;
			}
		}

		$this->assertNotNull( $grouped_event, 'Should find grouped event' );
		$this->assertEquals(
			5,
			$grouped_event->subsequentOccasions,
			'Should group all 5 isolated events together'
		);
	}

	/**
	 * Test that grouping breaks when events are NOT consecutive in time.
	 */
	function test_grouping_breaks_with_time_gap() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id_a = 'test_gap_a_' . uniqid();
		$occasions_id_b = 'test_gap_b_' . uniqid();

		// Create events with time-based separation
		// Group A: 2 events at 2010-01-01 10:00:00 and 10:00:01
		// Group B: 1 event at  2010-01-01 10:00:02 (separates A)
		// Group A: 2 events at 2010-01-01 10:00:03 and 10:00:04
		SimpleLogger()->notice( 'A1', array( '_date' => '2010-01-01 10:00:00', '_occasionsID' => $occasions_id_a ) );
		SimpleLogger()->notice( 'A2', array( '_date' => '2010-01-01 10:00:01', '_occasionsID' => $occasions_id_a ) );
		SimpleLogger()->notice( 'B1', array( '_date' => '2010-01-01 10:00:02', '_occasionsID' => $occasions_id_b ) );
		SimpleLogger()->notice( 'A3', array( '_date' => '2010-01-01 10:00:03', '_occasionsID' => $occasions_id_a ) );
		SimpleLogger()->notice( 'A4', array( '_date' => '2010-01-01 10:00:04', '_occasionsID' => $occasions_id_a ) );

		// Query with grouping
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 100,
			)
		);

		// Find all events with Group A occasionsID
		$expected_occasions_id_a = md5( json_encode( array( '_occasionsID' => $occasions_id_a, '_loggerSlug' => 'SimpleLogger' ) ) );

		$group_a_events = array();
		foreach ( $results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id_a ) {
				$group_a_events[] = array(
					'subsequentOccasions' => $event->subsequentOccasions,
					'date' => $event->date,
				);
			}
		}

		// With date ordering, query returns in descending date order:
		// A4, A3, B1, A2, A1
		// Grouping should be: [A4+A3] (2), [B1] (1), [A2+A1] (2)
		// So we should find 2 groups for A: first with 2, second with 2

		$this->assertCount(
			2,
			$group_a_events,
			'Should have 2 separate groups for A because B separates them chronologically'
		);

		// Both groups should have 2 events
		$this->assertEquals( 2, $group_a_events[0]['subsequentOccasions'], 'First A group should have 2 events (A4+A3)' );
		$this->assertEquals( 2, $group_a_events[1]['subsequentOccasions'], 'Second A group should have 2 events (A2+A1)' );
	}

	/**
	 * Test real-world scenario: 100 failed logins in 5 minutes.
	 */
	function test_realistic_failed_login_burst() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id = 'failed_login_burst_' . uniqid();
		$base_time = strtotime( '2010-06-15 14:30:00' );

		// Create 100 failed login attempts over 5 minutes (one every 3 seconds)
		for ( $i = 0; $i < 100; $i++ ) {
			SimpleLogger()->warning(
				'Failed login attempt',
				array(
					'_date' => date( 'Y-m-d H:i:s', $base_time + ( $i * 3 ) ),
					'_occasionsID' => $occasions_id,
					'username' => 'admin',
				)
			);
		}

		// Query with grouping
		$log_query = new Log_Query();
		$results = $log_query->query(
			array(
				'posts_per_page' => 200,
			)
		);

		// Find our event
		$expected_occasions_id = md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) );

		$found_event = null;
		foreach ( $results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id ) {
				$found_event = $event;
				break;
			}
		}

		$this->assertNotNull( $found_event, 'Should find grouped failed login event' );
		$this->assertEquals(
			100,
			$found_event->subsequentOccasions,
			'Should group all 100 consecutive failed login attempts'
		);
	}

	/**
	 * Test that date ordering doesn't break grouping for events at same second.
	 */
	function test_grouping_events_same_second_different_ids() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$occasions_id = 'same_second_' . uniqid();
		$exact_time = '2010-03-15 11:22:33';

		// Create 10 events at the EXACT same timestamp
		// They will have different IDs but same date
		for ( $i = 0; $i < 10; $i++ ) {
			SimpleLogger()->notice(
				"Same second event $i",
				array(
					'_date' => $exact_time,
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
		$expected_occasions_id = md5( json_encode( array( '_occasionsID' => $occasions_id, '_loggerSlug' => 'SimpleLogger' ) ) );

		$found_event = null;
		foreach ( $results['log_rows'] as $event ) {
			if ( $event->occasionsID === $expected_occasions_id ) {
				$found_event = $event;
				break;
			}
		}

		$this->assertNotNull( $found_event, 'Should find grouped event' );
		$this->assertEquals(
			10,
			$found_event->subsequentOccasions,
			'Should group all 10 events even though they have same timestamp (ID tiebreaker ensures consecutive order)'
		);
	}
}
