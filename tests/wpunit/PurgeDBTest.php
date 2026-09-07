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
		remove_all_filters( 'simple_history/network/db_purge_days' );
		remove_all_filters( 'simple_history/log_argument/context' );
		remove_all_actions( 'simple_history/db/purge_done' );
		remove_all_actions( 'simple_history/db/network_purge_done' );
		remove_all_actions( 'simple_history/db/events_purged' );
		remove_all_actions( 'simple_history/db/network_events_purged' );

		delete_site_option( 'simple_history_network_retention_days' );
		$this->reset_network_tables_registration();

		parent::tearDown();
	}

	/**
	 * Simple_History is a singleton, so network tables registered by one
	 * test would otherwise leak into every test that runs after it.
	 */
	private function reset_network_tables_registration(): void {
		$prop = new ReflectionProperty( Simple_History::class, 'network_tables' );
		$prop->setAccessible( true );
		$prop->setValue( $this->simple_history, null );
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

	/**
	 * purge_done keeps its original contract: it fires exactly once per
	 * run, for the per-site tables, with ($days, $total_rows). The network
	 * tables have their own action, so a callback that counts purges or
	 * logs the result doesn't start firing twice on multisite.
	 */
	public function test_purge_done_fires_only_once_when_no_network_tables_registered() {
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		$calls = [];

		add_action(
			'simple_history/db/purge_done',
			function ( $days, $total_rows ) use ( &$calls ) {
				$calls[] = [ 'days' => $days, 'total' => $total_rows ];
			},
			10,
			2
		);

		$this->add_event_days_ago( 'Old event', 60 );

		$this->purge_service->purge_db();

		$this->assertCount( 1, $calls, 'purge_done should fire once when no network tables are registered.' );
		$this->assertSame( 30, $calls[0]['days'], 'purge_done must receive the per-site retention.' );
	}

	/**
	 * Same, but with a provider registered: purge_done must still fire
	 * exactly once. The network pair gets network_purge_done instead.
	 */
	public function test_purge_done_fires_once_when_network_tables_are_registered() {
		add_filter( 'simple_history/db_purge_days_interval', fn() => 30 );

		$this->simple_history->set_network_tables( 'wp_events_network', 'wp_contexts_network' );

		$calls = 0;

		add_action(
			'simple_history/db/purge_done',
			function () use ( &$calls ) {
				++$calls;
			}
		);

		$this->add_event_days_ago( 'Old event', 60 );

		$this->purge_service->purge_db();

		$this->assertSame( 1, $calls, 'purge_done must fire exactly once per run, also when network tables are registered.' );
	}

	/**
	 * Direct exercise of the private purge_table_pair() — the helper that
	 * does the actual work for both per-site and network table pairs.
	 *
	 * The test bed is single-site, so the multisite/main-site gates inside
	 * purge_db() can't be reached here. Reflection lets us verify the
	 * shared SQL/action plumbing on an alternate table pair (mirroring
	 * the schema the Premium network module would register) without
	 * needing a multisite fixture.
	 */
	public function test_purge_table_pair_purges_alt_tables_and_fires_the_network_actions() {
		global $wpdb;

		$alt_events   = $wpdb->prefix . 'sh_test_purge_alt_events';
		$alt_contexts = $wpdb->prefix . 'sh_test_purge_alt_contexts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$alt_events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				date datetime NOT NULL,
				logger varchar(30) DEFAULT NULL,
				level varchar(20) DEFAULT NULL,
				message varchar(255) DEFAULT NULL,
				occasionsID varchar(32) DEFAULT NULL,
				initiator varchar(16) DEFAULT NULL,
				PRIMARY KEY  (id)
			)"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$alt_contexts} (
				context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				history_id bigint(20) unsigned NOT NULL,
				`key` varchar(255) DEFAULT NULL,
				value longtext,
				PRIMARY KEY  (context_id),
				KEY history_id (history_id)
			)"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$alt_events}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$alt_contexts}" );

		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) );
		$new_date = gmdate( 'Y-m-d H:i:s', strtotime( '-5 days' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $alt_events, [ 'date' => $old_date, 'logger' => 'SimpleLogger', 'level' => 'info', 'message' => 'old' ] );
		$old_id = (int) $wpdb->insert_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $alt_contexts, [ 'history_id' => $old_id, 'key' => '_marker', 'value' => 'old-context' ] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $alt_events, [ 'date' => $new_date, 'logger' => 'SimpleLogger', 'level' => 'info', 'message' => 'new' ] );
		$new_id = (int) $wpdb->insert_id;

		$captured           = [];
		$captured_batches   = [];
		$per_site_done_runs = 0;

		add_action(
			'simple_history/db/network_purge_done',
			function ( $days, $total_rows ) use ( &$captured ) {
				$captured[] = [ 'days' => $days, 'total' => $total_rows ];
			},
			10,
			2
		);

		add_action(
			'simple_history/db/network_events_purged',
			function ( $days, $num_rows ) use ( &$captured_batches ) {
				$captured_batches[] = $num_rows;
			},
			10,
			2
		);

		add_action(
			'simple_history/db/purge_done',
			function () use ( &$per_site_done_runs ) {
				++$per_site_done_runs;
			}
		);

		$method = new ReflectionMethod( Setup_Purge_DB_Cron::class, 'purge_table_pair' );
		$method->setAccessible( true );
		$method->invoke( $this->purge_service, $alt_events, $alt_contexts, 30, true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$remaining_event_ids = $wpdb->get_col( "SELECT id FROM {$alt_events}" );
		$this->assertEquals( [ (string) $new_id ], $remaining_event_ids, 'Only the in-retention event should remain.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$orphan_contexts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$alt_contexts} WHERE history_id = {$old_id}" );
		$this->assertSame( 0, $orphan_contexts, 'Context rows for the deleted event must be removed.' );

		$this->assertCount( 1, $captured, 'network_purge_done must fire exactly once.' );
		$this->assertSame( 1, $captured[0]['total'], 'Total deleted should match the single old event.' );
		$this->assertSame( [ 1 ], $captured_batches, 'network_events_purged must fire per batch with the batch count.' );
		$this->assertSame( 0, $per_site_done_runs, 'The per-site purge_done must not fire for a network table pair.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$alt_events}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$alt_contexts}" );
	}

	/**
	 * The "X events purged" log entry written by Simple_History_Logger
	 * must carry _event_scope=network when the completed run targeted
	 * the network tables, so it routes into the network log instead of
	 * landing on whichever per-site log happened to run the cron.
	 */
	public function test_on_purge_done_tags_log_entry_with_network_event_scope() {
		$logger = $this->get_simple_history_logger();

		$captured_context = null;

		add_filter(
			'simple_history/log_argument/context',
			function ( $context, $level, $message ) use ( &$captured_context ) {
				if ( ( $context['_message_key'] ?? null ) === 'purged_events' ) {
					$captured_context = $context;
				}

				return $context;
			},
			10,
			3
		);

		// Simulate the network purge completing.
		$logger->on_purge_done( 30, 5, true );

		$this->assertIsArray( $captured_context, 'A purged_events log entry must be written.' );
		$this->assertArrayHasKey(
			\Simple_History\Loggers\Logger::EVENT_SCOPE_CONTEXT_KEY,
			$captured_context,
			'Network purge log entry must carry the event-scope hint.'
		);
		$this->assertSame(
			'network',
			$captured_context[ \Simple_History\Loggers\Logger::EVENT_SCOPE_CONTEXT_KEY ],
			'Event-scope hint must be set to "network" so routing sends the entry to the network log.'
		);

		// Per-site purge (is_network=false) must NOT add the network scope hint.
		$captured_context = null;
		$logger->on_purge_done( 30, 7, false );

		$this->assertIsArray( $captured_context, 'A purged_events log entry must be written for the per-site case too.' );
		$this->assertArrayNotHasKey(
			\Simple_History\Loggers\Logger::EVENT_SCOPE_CONTEXT_KEY,
			$captured_context,
			'Per-site purge log entry must not carry the network scope hint.'
		);
	}

	/**
	 * The shared network log is not governed by whichever site happened to
	 * run the cron job: it reads its own site option and its own filter,
	 * and the per-site retention filter must not reach it.
	 */
	public function test_network_retention_is_independent_of_the_per_site_retention() {
		add_filter( 'simple_history/db_purge_days_interval', fn() => 7 );

		$this->assertSame(
			60,
			Helpers::get_network_clear_history_interval(),
			'Network retention must fall back to its own default, not to the per-site setting.'
		);

		update_site_option( 'simple_history_network_retention_days', 120 );

		$this->assertSame(
			120,
			Helpers::get_network_clear_history_interval(),
			'Network retention must come from the simple_history_network_retention_days site option.'
		);

		add_filter( 'simple_history/network/db_purge_days', fn() => 5 );

		$this->assertSame(
			5,
			Helpers::get_network_clear_history_interval(),
			'simple_history/network/db_purge_days must be able to override the stored value.'
		);

		$this->assertSame(
			7,
			Helpers::get_clear_history_interval(),
			'The network filter must not leak into the per-site retention.'
		);
	}

	/**
	 * Helpers::clear_network_log() mirrors clear_log() for the shared
	 * network tables — and does nothing at all when core is running
	 * without a provider, because then there are no network tables.
	 */
	public function test_clear_network_log_does_nothing_without_a_provider() {
		$this->assertSame( 0, Helpers::clear_network_log(), 'Without a registered provider there is nothing to clear.' );
	}

	public function test_clear_network_log_truncates_the_registered_network_tables() {
		global $wpdb;

		[ $alt_events, $alt_contexts ] = $this->create_alt_tables( 'sh_test_clear_alt' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $alt_events, [ 'date' => gmdate( 'Y-m-d H:i:s' ), 'logger' => 'SimpleLogger', 'level' => 'info', 'message' => 'network event' ] );
		$event_id = (int) $wpdb->insert_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $alt_contexts, [ 'history_id' => $event_id, 'key' => '_marker', 'value' => 'network' ] );

		$this->simple_history->set_network_tables( $alt_events, $alt_contexts );

		$removed = Helpers::clear_network_log();

		$this->assertSame( 1, $removed, 'clear_network_log() must return the number of events removed.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$alt_events}" ), 'Network events table must be empty.' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->assertSame( '0', (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$alt_contexts}" ), 'Network contexts table must be empty.' );

		// The per-site log must be untouched.
		$this->assertGreaterThan(
			0,
			Helpers::count_events(),
			'clear_network_log() must not touch the per-site tables. (An event is logged by the setUp user creation.)'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$alt_events}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$alt_contexts}" );
	}

	/**
	 * Create an events/contexts table pair mirroring the schema a network
	 * provider would register.
	 *
	 * @param string $base_name Table base name, without the wpdb prefix.
	 * @return string[] Events table name, contexts table name.
	 */
	private function create_alt_tables( string $base_name ): array {
		global $wpdb;

		$alt_events   = $wpdb->prefix . $base_name . '_events';
		$alt_contexts = $wpdb->prefix . $base_name . '_contexts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$alt_events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				date datetime NOT NULL,
				logger varchar(30) DEFAULT NULL,
				level varchar(20) DEFAULT NULL,
				message varchar(255) DEFAULT NULL,
				occasionsID varchar(32) DEFAULT NULL,
				initiator varchar(16) DEFAULT NULL,
				PRIMARY KEY  (id)
			)"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$alt_contexts} (
				context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				history_id bigint(20) unsigned NOT NULL,
				`key` varchar(255) DEFAULT NULL,
				value longtext,
				PRIMARY KEY  (context_id),
				KEY history_id (history_id)
			)"
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$alt_events}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$alt_contexts}" );

		return [ $alt_events, $alt_contexts ];
	}

	/**
	 * Locate the Simple_History_Logger instance via the registered loggers.
	 */
	private function get_simple_history_logger(): \Simple_History\Loggers\Simple_History_Logger {
		$loggers = $this->simple_history->get_instantiated_loggers();
		foreach ( $loggers as $entry ) {
			$instance = $entry['instance'] ?? null;
			if ( $instance instanceof \Simple_History\Loggers\Simple_History_Logger ) {
				return $instance;
			}
		}
		$this->fail( 'Simple_History_Logger not registered.' );
	}
}
