<?php

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Services\Setup_Purge_DB_Cron;

/**
 * Test the purge database functionality and the purge_db_where filter.
 *
 * @coversDefaultClass Simple_History\Services\Setup_Purge_DB_Cron
 */
class PurgeDBTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $simple_history;

	/** @var Setup_Purge_DB_Cron */
	private $purge_service;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->simple_history = Simple_History::get_instance();
		$this->purge_service = $this->get_purge_service();

		// Create admin user for logging.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		remove_all_filters( 'simple_history/purge_db_where' );
		remove_all_filters( 'simple_history/db_purge_days_interval' );
		remove_all_actions( 'simple_history/db/purge_done' );
		parent::tearDown();
	}

	/**
	 * Get the purge service instance.
	 */
	private function get_purge_service(): Setup_Purge_DB_Cron {
		$services = $this->simple_history->get_instantiated_services();
		foreach ( $services as $service ) {
			if ( $service instanceof Setup_Purge_DB_Cron ) {
				return $service;
			}
		}
		$this->fail( 'Setup_Purge_DB_Cron service not found' );
	}

	/**
	 * Helper to add events with a specific date in the past.
	 *
	 * @param string $message Message to log.
	 * @param int    $days_ago Number of days in the past.
	 * @param string $logger Logger slug to use.
	 * @param string $level Log level.
	 * @return int The inserted row ID.
	 */
	private function add_event_days_ago( string $message, int $days_ago, string $logger = 'SimpleLogger', string $level = 'info' ): int {
		global $wpdb;

		// Log the event normally first.
		$simple_logger = SimpleLogger();
		$simple_logger->info( $message );
		$row_id = $simple_logger->last_insert_id;

		// Update the date, logger, and level.
		$table_name = $this->simple_history->get_events_table_name();
		$new_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table_name,
			[
				'date'   => $new_date,
				'logger' => $logger,
				'level'  => $level,
			],
			[ 'id' => $row_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return $row_id;
	}

	/**
	 * Test that the purge_db_where filter receives correct parameters.
	 */
	public function test_purge_db_where_filter_receives_correct_parameters() {
		$filter_called = false;
		$received_where = null;
		$received_days = null;
		$received_table = null;

		add_filter(
			'simple_history/purge_db_where',
			function ( $where, $days, $table_name ) use ( &$filter_called, &$received_where, &$received_days, &$received_table ) {
				$filter_called = true;
				$received_where = $where;
				$received_days = $days;
				$received_table = $table_name;
				return $where;
			},
			10,
			3
		);

		// Add an old event to trigger purge.
		$this->add_event_days_ago( 'Old event', 100 );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertTrue( $filter_called, 'Filter should be called during purge' );
		$this->assertIsString( $received_where, 'WHERE clause should be a string' );
		$this->assertIsInt( $received_days, 'Days should be an integer' );
		$this->assertStringContainsString( 'simple_history', $received_table, 'Table name should contain simple_history' );
		$this->assertStringContainsString( 'DATE_SUB', $received_where, 'Default WHERE should contain DATE_SUB' );
	}

	/**
	 * Test that default purge behavior works without filter.
	 */
	public function test_default_purge_deletes_old_events() {
		// Set retention to 30 days for this test.
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		// Add events at different ages.
		$old_event_id = $this->add_event_days_ago( 'Old event to delete', 60 );
		$new_event_id = $this->add_event_days_ago( 'New event to keep', 10 );

		$this->assertTrue( Helpers::event_exists( $old_event_id ), 'Old event should exist before purge' );
		$this->assertTrue( Helpers::event_exists( $new_event_id ), 'New event should exist before purge' );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertFalse( Helpers::event_exists( $old_event_id ), 'Old event should be deleted after purge' );
		$this->assertTrue( Helpers::event_exists( $new_event_id ), 'New event should still exist after purge' );
	}

	/**
	 * Test that filter can exclude a specific logger from purge.
	 */
	public function test_filter_can_exclude_logger_from_purge() {
		// Set retention to 30 days.
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		// Exclude SimpleOptionsLogger from purge.
		add_filter(
			'simple_history/purge_db_where',
			function ( $where ) {
				global $wpdb;
				return $where . $wpdb->prepare( ' AND logger != %s', 'SimpleOptionsLogger' );
			},
			10,
			3
		);

		// Add old events with different loggers.
		$options_event_id = $this->add_event_days_ago( 'Options event', 60, 'SimpleOptionsLogger' );
		$other_event_id = $this->add_event_days_ago( 'Other event', 60, 'SimplePostLogger' );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertTrue( Helpers::event_exists( $options_event_id ), 'SimpleOptionsLogger event should be kept' );
		$this->assertFalse( Helpers::event_exists( $other_event_id ), 'Other logger event should be deleted' );
	}

	/**
	 * Test that filter can exclude events by level.
	 */
	public function test_filter_can_keep_warning_and_error_events_forever() {
		// Set retention to 30 days.
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		// Keep warning and error level events forever.
		add_filter(
			'simple_history/purge_db_where',
			function ( $where ) {
				return $where . " AND level NOT IN ('warning', 'error', 'critical')";
			},
			10,
			3
		);

		// Add old events with different levels.
		$info_event_id = $this->add_event_days_ago( 'Info event', 60, 'SimpleLogger', 'info' );
		$warning_event_id = $this->add_event_days_ago( 'Warning event', 60, 'SimpleLogger', 'warning' );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertFalse( Helpers::event_exists( $info_event_id ), 'Info event should be deleted' );
		$this->assertTrue( Helpers::event_exists( $warning_event_id ), 'Warning event should be kept' );
	}

	/**
	 * Test that filter can implement per-logger retention.
	 */
	public function test_filter_can_implement_per_logger_retention() {
		// Set default retention to 30 days.
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		// SimpleUserLogger: 90 days, others: 30 days.
		add_filter(
			'simple_history/purge_db_where',
			function ( $where, $days ) {
				global $wpdb;
				return $wpdb->prepare(
					'(logger = %s AND date < DATE_SUB(NOW(), INTERVAL 90 DAY))
					 OR (logger != %s AND date < DATE_SUB(NOW(), INTERVAL %d DAY))',
					'SimpleUserLogger',
					'SimpleUserLogger',
					$days
				);
			},
			10,
			3
		);

		// Add events:
		// - User event 60 days old (should be kept, within 90 day retention)
		// - User event 100 days old (should be deleted, outside 90 day retention)
		// - Post event 60 days old (should be deleted, outside 30 day retention)
		$user_event_60_id = $this->add_event_days_ago( 'User event 60 days', 60, 'SimpleUserLogger' );
		$user_event_100_id = $this->add_event_days_ago( 'User event 100 days', 100, 'SimpleUserLogger' );
		$post_event_60_id = $this->add_event_days_ago( 'Post event 60 days', 60, 'SimplePostLogger' );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertTrue( Helpers::event_exists( $user_event_60_id ), 'User event at 60 days should be kept (90 day retention)' );
		$this->assertFalse( Helpers::event_exists( $user_event_100_id ), 'User event at 100 days should be deleted' );
		$this->assertFalse( Helpers::event_exists( $post_event_60_id ), 'Post event at 60 days should be deleted (30 day retention)' );
	}

	/**
	 * Test that purge_done action fires with correct total count.
	 */
	public function test_purge_done_action_fires_with_total_count() {
		// Set retention to 30 days.
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		$action_fired = false;
		$received_days = null;
		$received_total = null;

		add_action(
			'simple_history/db/purge_done',
			function ( $days, $total_rows ) use ( &$action_fired, &$received_days, &$received_total ) {
				$action_fired = true;
				$received_days = $days;
				$received_total = $total_rows;
			},
			10,
			2
		);

		// Add 3 old events.
		$this->add_event_days_ago( 'Old event 1', 60 );
		$this->add_event_days_ago( 'Old event 2', 60 );
		$this->add_event_days_ago( 'Old event 3', 60 );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertTrue( $action_fired, 'purge_done action should fire' );
		$this->assertEquals( 30, $received_days, 'Days should match retention setting' );
		$this->assertEquals( 3, $received_total, 'Total should equal number of deleted events' );
	}

	/**
	 * Test that purge_done action fires with zero when no events to purge.
	 */
	public function test_purge_done_fires_with_zero_when_nothing_to_purge() {
		// Set retention to 30 days.
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		$received_total = null;

		add_action(
			'simple_history/db/purge_done',
			function ( $days, $total_rows ) use ( &$received_total ) {
				$received_total = $total_rows;
			},
			10,
			2
		);

		// Add only new events (within retention).
		$this->add_event_days_ago( 'New event', 10 );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertEquals( 0, $received_total, 'Total should be 0 when nothing to purge' );
	}

	/**
	 * Test that context rows are also deleted when events are purged.
	 */
	public function test_context_rows_are_deleted_with_events() {
		global $wpdb;

		// Set retention to 30 days.
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		// Add an old event (this will also create context rows).
		$event_id = $this->add_event_days_ago( 'Event with context', 60 );

		// Verify context exists.
		$contexts_table = $this->simple_history->get_contexts_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$context_count_before = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$contexts_table} WHERE history_id = %d", $event_id )
		);
		$this->assertGreaterThan( 0, $context_count_before, 'Context rows should exist before purge' );

		// Run purge.
		$this->purge_service->purge_db();

		// Verify context is deleted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$context_count_after = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$contexts_table} WHERE history_id = %d", $event_id )
		);
		$this->assertEquals( 0, $context_count_after, 'Context rows should be deleted after purge' );
	}

	/**
	 * Test that purge does nothing when retention is set to 0 (keep forever).
	 */
	public function test_purge_does_nothing_when_retention_is_zero() {
		// Set retention to 0 (keep forever).
		add_filter( 'simple_history/db_purge_days_interval', fn() => 0 );

		// Add a very old event.
		$old_event_id = $this->add_event_days_ago( 'Ancient event', 1000 );

		// Run purge.
		$this->purge_service->purge_db();

		$this->assertTrue( Helpers::event_exists( $old_event_id ), 'Event should still exist when retention is 0' );
	}

	/**
	 * Test that count_events helper works correctly.
	 */
	public function test_count_events_helper() {
		$initial_count = Helpers::count_events();
		$initial_simple_logger_count = Helpers::count_events( [ 'logger' => 'SimpleLogger' ] );
		$initial_post_logger_count = Helpers::count_events( [ 'logger' => 'SimplePostLogger' ] );

		// Add some events with different loggers.
		$this->add_event_days_ago( 'Event 1', 1, 'SimpleLogger' );
		$this->add_event_days_ago( 'Event 2', 1, 'SimpleLogger' );
		$this->add_event_days_ago( 'Event 3', 1, 'SimplePostLogger' );

		$this->assertEquals( $initial_count + 3, Helpers::count_events(), 'Total count should increase by 3' );
		$this->assertEquals( $initial_simple_logger_count + 2, Helpers::count_events( [ 'logger' => 'SimpleLogger' ] ), 'SimpleLogger count should increase by 2' );
		$this->assertEquals( $initial_post_logger_count + 1, Helpers::count_events( [ 'logger' => 'SimplePostLogger' ] ), 'SimplePostLogger count should increase by 1' );
	}

	/**
	 * Test that count_events can filter by level.
	 */
	public function test_count_events_by_level() {
		$this->add_event_days_ago( 'Info event', 1, 'SimpleLogger', 'info' );
		$this->add_event_days_ago( 'Warning event', 1, 'SimpleLogger', 'warning' );
		$this->add_event_days_ago( 'Error event', 1, 'SimpleLogger', 'error' );

		$this->assertGreaterThanOrEqual( 1, Helpers::count_events( [ 'level' => 'info' ] ), 'Should have at least 1 info event' );
		$this->assertGreaterThanOrEqual( 1, Helpers::count_events( [ 'level' => 'warning' ] ), 'Should have at least 1 warning event' );
		$this->assertGreaterThanOrEqual( 1, Helpers::count_events( [ 'level' => 'error' ] ), 'Should have at least 1 error event' );
	}
}
