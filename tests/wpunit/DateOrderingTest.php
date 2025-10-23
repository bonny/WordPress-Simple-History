<?php

use Simple_History\Log_Query;

class DateOrderingTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Test that events are ordered by date DESC, id DESC
	 * by verifying ALL events in database are in chronological order.
	 */
	function test_date_ordering_with_mixed_ids() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		// Create test events with various dates
		$dates = array(
			'2024-12-01 10:00:00',
			'2024-01-15 14:30:00',
			'2024-06-20 08:15:00',
			'2024-03-10 12:00:00',
			'2024-09-05 16:45:00',
		);

		foreach ( $dates as $index => $date ) {
			SimpleLogger()->notice(
				"Test event $index",
				array(
					'_date' => $date,
					'_occasionsID' => "test_event_$index",
				)
			);
		}

		// Query all events
		$log_query = new Log_Query();
		$query_results = $log_query->query(
			array(
				'posts_per_page' => 100, // Get many events
			)
		);

		$events = array_values( $query_results['log_rows'] );

		$this->assertGreaterThanOrEqual(
			2,
			count( $events ),
			'Should have at least 2 events to compare'
		);

		// Verify ALL events are in descending date order
		for ( $i = 0; $i < count( $events ) - 1; $i++ ) {
			$current_date = $events[ $i ]->date;
			$next_date = $events[ $i + 1 ]->date;

			$this->assertGreaterThanOrEqual(
				$next_date,
				$current_date,
				sprintf(
					'Event %d (date: %s) should be >= Event %d (date: %s)',
					$i,
					$current_date,
					$i + 1,
					$next_date
				)
			);

			// If dates are equal, verify IDs are in descending order
			if ( $current_date === $next_date ) {
				$this->assertGreaterThan(
					$events[ $i + 1 ]->id,
					$events[ $i ]->id,
					sprintf(
						'When dates are equal, ID %d should be > ID %d',
						$events[ $i ]->id,
						$events[ $i + 1 ]->id
					)
				);
			}
		}
	}
}
