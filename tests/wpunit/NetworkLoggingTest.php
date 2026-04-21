<?php

use Simple_History\Event;
use Simple_History\Export;
use Simple_History\Helpers;
use Simple_History\Log_Query;
use Simple_History\Loggers\Logger;
use Simple_History\Simple_History;

/**
 * Tests for the network-scoped routing primitives added in 5.13.0:
 *
 * - Logger::should_use_network_tables() decision gates
 * - Logger::log() routes writes to the tables registered via
 *   Simple_History::set_network_tables() (and falls back to the
 *   per-site tables when nothing is registered)
 * - Simple_History::set_network_tables() validates identifiers at
 *   registration time, not on every log call
 * - Simple_History::set_network_log_query_factory() /
 *   get_network_log_query() plumbing used by Export and the CLI
 * - Event subclass overrides of get_events_table_name() /
 *   get_contexts_table_name() actually reach Event::load_data()
 *   (regression test for the bug where query_db_for_events was
 *   static and bypassed the instance getters).
 * - Helpers::get_network_history_admin_url() short-circuits
 *   correctly off multisite.
 * - Export::set_is_network() chainable setter.
 *
 * These tests run in single-site mode. The end-to-end
 * "Network Admin request routes writes to Premium tables"
 * scenario lives in the Premium test suite because it needs
 * a multisite fixture plus the Network_Module bootstrap.
 *
 * Run with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit NetworkLoggingTest`
 */
class NetworkLoggingTest extends \Codeception\TestCase\WPTestCase {
	/** @var string Alternate events table — created once per suite, shared across tests, rows cleared in setUp. */
	private $alt_events_table;

	/** @var string Alternate contexts table — same lifecycle as alt_events_table. */
	private $alt_contexts_table;

	public function setUp(): void {
		parent::setUp();

		global $wpdb;

		$this->alt_events_table   = $wpdb->prefix . 'sh_test_alt_events';
		$this->alt_contexts_table = $wpdb->prefix . 'sh_test_alt_contexts';

		// Tables are created on first use per process. Cheaper than CREATE/DROP
		// every test (18 × 4 = 72 DDLs → 2 DDLs for the whole suite). Data is
		// cleared below so each test sees empty tables.
		$this->ensure_alt_tables_exist();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$this->alt_events_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$this->alt_contexts_table}" );

		$this->reset_network_registrations();
	}

	public function tearDown(): void {
		$this->reset_network_registrations();

		parent::tearDown();
	}

	/**
	 * Reset Simple_History's network-tables and log-query-factory slots
	 * to null between tests. Simple_History is a singleton, so anything
	 * one test registers would otherwise leak into the next.
	 */
	private function reset_network_registrations(): void {
		$sh = Simple_History::get_instance();

		foreach ( [ 'network_tables', 'network_log_query_factory', 'network_event_factory' ] as $prop_name ) {
			$prop = new ReflectionProperty( Simple_History::class, $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( $sh, null );
		}
	}

	/**
	 * Creates alt events+contexts tables if they don't already exist.
	 * Schema mirrors the per-site Simple History tables so Logger routing
	 * can write here without any schema differences.
	 */
	private function ensure_alt_tables_exist(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$this->alt_events_table} (
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
			"CREATE TABLE IF NOT EXISTS {$this->alt_contexts_table} (
				context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				history_id bigint(20) unsigned NOT NULL,
				`key` varchar(255) DEFAULT NULL,
				value longtext,
				PRIMARY KEY  (context_id),
				KEY history_id (history_id)
			)"
		);
	}

	/**
	 * Build a Logger subclass that unconditionally opts into network
	 * routing. Sidesteps the is_multisite() gate so we can verify the
	 * routing plumbing on a single-site test bed.
	 */
	private function make_forced_network_logger(): Logger {
		return new class( Simple_History::get_instance() ) extends Logger {
			public $slug = 'SH_Test_NetworkRouted';

			public function get_info() {
				return [
					'name'        => 'Test network-routed logger',
					'description' => 'Test logger that forces network routing for routing tests.',
					'messages'    => [],
				];
			}

			// Bypass the multisite / network-admin gate used in production —
			// we're testing the post-gate plumbing, not the gate itself.
			// Public (base is protected) so the test can exercise it via
			// normal method dispatch without reflection gymnastics.
			public function should_use_network_tables() {
				return true;
			}
		};
	}

	public function test_should_use_network_tables_returns_false_on_non_multisite(): void {
		$this->assertFalse( is_multisite(), 'Test bed is expected to be single-site.' );

		$logger  = SimpleLogger();
		$reflect = new ReflectionMethod( $logger, 'should_use_network_tables' );
		$reflect->setAccessible( true );

		$this->assertFalse(
			$reflect->invoke( $logger ),
			'should_use_network_tables() must return false on a single-site install, regardless of other signals.'
		);
	}

	public function test_log_routes_writes_to_registered_network_tables(): void {
		global $wpdb;

		$registered = Simple_History::get_instance()->set_network_tables(
			$this->alt_events_table,
			$this->alt_contexts_table
		);

		$this->assertTrue( $registered, 'Valid identifiers must be accepted by set_network_tables().' );

		$logger = $this->make_forced_network_logger();
		$logger->info(
			'Network-routed test event',
			[ 'routing_marker' => 'landed-in-alt-tables' ]
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->alt_events_table} WHERE message = %s",
				'Network-routed test event'
			)
		);

		$this->assertNotNull( $row, 'Event row must be present in the registered network events table.' );

		$context_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$this->alt_contexts_table} WHERE history_id = %d AND `key` = %s",
				$row->id,
				'routing_marker'
			)
		);

		$this->assertSame(
			'landed-in-alt-tables',
			$context_value,
			'Context row must land in the registered network contexts table. Verifies append_context() honors the contexts_table parameter passed by log().'
		);

		// And critically, nothing leaked into the default tables for this marker.
		$default_events_table = Simple_History::get_instance()->get_events_table_name();
		$default_leaks        = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$default_events_table} WHERE message = %s",
				'Network-routed test event'
			)
		);
		$this->assertSame( '0', (string) $default_leaks, 'Network-routed writes must not leak into the default events table.' );
	}

	public function test_log_falls_back_to_default_tables_when_no_provider_registered(): void {
		global $wpdb;

		// No set_network_tables() call — get_network_tables() returns null,
		// so log() uses the per-site tables even though the logger forces
		// should_use_network_tables() to true.
		$logger = $this->make_forced_network_logger();
		$logger->info( 'Fallback event', [ 'marker' => 'no-provider-fallback' ] );

		$default_events_table = Simple_History::get_instance()->get_events_table_name();
		$default_contexts     = Simple_History::get_instance()->get_contexts_table_name();

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$default_events_table} e
				 INNER JOIN {$default_contexts} c ON e.id = c.history_id
				 WHERE c.`key` = %s AND c.value = %s",
				'marker',
				'no-provider-fallback'
			)
		);

		$this->assertSame( '1', (string) $found, 'Logger must fall back to default tables when no network provider is registered.' );
	}

	public function test_fallback_event_carries_network_fallback_context_flag(): void {
		global $wpdb;

		// No network provider registered — the event will fall back to the
		// per-site tables. The flag is what the REST controller reads to
		// show the small "network action" note in the React log UI.
		$logger = $this->make_forced_network_logger();
		$logger->info( 'Fallback-flag event', [ 'marker' => 'flag-check' ] );
		$event_id = (int) $logger->last_insert_id;

		$default_contexts = Simple_History::get_instance()->get_contexts_table_name();

		$flag_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$default_contexts} WHERE history_id = %d AND `key` = %s",
				$event_id,
				'_network_fallback'
			)
		);

		$this->assertSame( '1', $flag_value, 'Events redirected to per-site tables must be tagged with _network_fallback so the UI can explain to the user why a network-level action appears on the per-site log.' );
	}

	public function test_network_routed_event_does_not_carry_fallback_flag(): void {
		global $wpdb;

		// Register the alt tables as network targets — event will route
		// correctly. The flag must NOT be set in this case.
		Simple_History::get_instance()->set_network_tables(
			$this->alt_events_table,
			$this->alt_contexts_table
		);

		$logger = $this->make_forced_network_logger();
		$logger->info( 'Properly-routed event', [ 'marker' => 'no-flag' ] );

		$flag_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->alt_contexts_table} WHERE `key` = %s",
				'_network_fallback'
			)
		);

		$this->assertSame( '0', (string) $flag_count, 'Events routed to the network tables via a registered provider must NOT carry the fallback flag — the explanatory note only makes sense for events that ended up on the per-site log by fallback.' );
	}

	public function test_set_network_tables_rejects_invalid_identifiers(): void {
		$sh = Simple_History::get_instance();

		// Strict [A-Za-z0-9_]+ is required because $wpdb->insert() does not
		// quote or escape table names. Anything outside that alphabet must
		// be rejected at registration time so it never reaches an INSERT.
		$invalid_pairs = [
			'sql injection events' => [ "wp_events; DROP TABLE wp_users; --", 'wp_contexts' ],
			'dash in events'       => [ 'wp-events', 'wp_contexts' ],
			'dash in contexts'     => [ 'wp_events', 'wp-contexts' ],
			'empty events'         => [ '', 'wp_contexts' ],
			'empty contexts'       => [ 'wp_events', '' ],
			'backtick in events'   => [ 'wp`events', 'wp_contexts' ],
			'space in events'      => [ 'wp events', 'wp_contexts' ],
		];

		foreach ( $invalid_pairs as $label => [ $events, $contexts ] ) {
			$this->assertFalse(
				$sh->set_network_tables( $events, $contexts ),
				sprintf( 'set_network_tables() must reject: %s', $label )
			);

			$this->assertNull(
				$sh->get_network_tables(),
				sprintf( 'State must stay null after rejected registration: %s', $label )
			);
		}

		// And a sanity-check positive case so we don't accidentally reject everything.
		$this->assertTrue( $sh->set_network_tables( 'wp_events_network', 'wp_contexts_network' ) );
		$this->assertSame(
			[ 'events' => 'wp_events_network', 'contexts' => 'wp_contexts_network' ],
			$sh->get_network_tables()
		);
	}

	public function test_get_network_log_query_returns_null_without_factory(): void {
		$this->assertNull(
			Simple_History::get_instance()->get_network_log_query(),
			'get_network_log_query() must return null before a factory is registered.'
		);
	}

	public function test_get_network_log_query_invokes_registered_factory(): void {
		$sh = Simple_History::get_instance();

		$invocations = 0;
		$sh->set_network_log_query_factory(
			function () use ( &$invocations ) {
				++$invocations;
				return new Log_Query();
			}
		);

		$first  = $sh->get_network_log_query();
		$second = $sh->get_network_log_query();

		$this->assertInstanceOf( Log_Query::class, $first );
		$this->assertInstanceOf( Log_Query::class, $second );
		$this->assertNotSame( $first, $second, 'Each call returns a fresh instance so callers can configure independently.' );
		$this->assertSame( 2, $invocations, 'Factory must be invoked once per get_network_log_query() call.' );
	}

	public function test_get_network_log_query_returns_null_when_factory_returns_wrong_type(): void {
		Simple_History::get_instance()->set_network_log_query_factory( fn() => 'not a log query' );

		$this->assertNull(
			Simple_History::get_instance()->get_network_log_query(),
			'A factory that returns a non-Log_Query value must be ignored, not cause a TypeError downstream.'
		);
	}

	public function test_get_network_event_returns_null_without_factory(): void {
		$this->assertNull(
			Simple_History::get_instance()->get_network_event( 123 ),
			'get_network_event() must return null before a factory is registered.'
		);
	}

	public function test_get_network_event_invokes_registered_factory_with_id(): void {
		$sh = Simple_History::get_instance();

		$received_ids = [];
		$sh->set_network_event_factory(
			function ( $event_id ) use ( &$received_ids ) {
				$received_ids[] = $event_id;
				return new Event( 0 );
			}
		);

		$sh->get_network_event( 42 );
		$sh->get_network_event( 99 );

		$this->assertSame( [ 42, 99 ], $received_ids, 'Factory must receive the caller-provided event ID each time.' );
	}

	public function test_get_network_event_returns_null_when_factory_returns_wrong_type(): void {
		Simple_History::get_instance()->set_network_event_factory( fn( $id ) => 'not an event' );

		$this->assertNull(
			Simple_History::get_instance()->get_network_event( 1 ),
			'A factory that returns a non-Event value must be ignored, not cause a TypeError downstream.'
		);
	}

	public function test_get_sticky_event_ids_uses_explicit_contexts_table(): void {
		global $wpdb;

		// Seed a sticky context row in the ALT contexts table — nothing
		// in the default contexts table — then verify the explicit-table
		// overload reads only from the alt table.
		$wpdb->insert(
			$this->alt_contexts_table,
			[
				'history_id' => 7777,
				'key'        => '_sticky',
				'value'      => '1',
			]
		);

		$ids_default = Helpers::get_sticky_event_ids();
		$this->assertNotContains(
			7777,
			$ids_default,
			'Default get_sticky_event_ids() must not see rows from a different contexts table.'
		);

		$ids_alt = Helpers::get_sticky_event_ids( $this->alt_contexts_table );
		$this->assertContains(
			7777,
			$ids_alt,
			'get_sticky_event_ids($alt) must read from the explicitly-provided contexts table.'
		);
	}

	public function test_append_context_respects_explicit_contexts_table(): void {
		global $wpdb;

		$logger = SimpleLogger();
		$logger->info( 'Explicit contexts table test' );
		$event_id = (int) $logger->last_insert_id;

		$logger->append_context(
			$event_id,
			[ 'explicit_target' => 'alt-table' ],
			$this->alt_contexts_table
		);

		$landed_in_alt = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$this->alt_contexts_table} WHERE history_id = %d AND `key` = %s",
				$event_id,
				'explicit_target'
			)
		);

		$default_contexts  = Simple_History::get_instance()->get_contexts_table_name();
		$landed_in_default = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$default_contexts} WHERE history_id = %d AND `key` = %s",
				$event_id,
				'explicit_target'
			)
		);

		$this->assertSame( 'alt-table', $landed_in_alt, 'append_context() must write to the explicitly-provided contexts table.' );
		$this->assertNull( $landed_in_default, 'append_context() with explicit table must NOT also write to $this->db_table_contexts.' );
	}

	public function test_append_context_defaults_to_instance_contexts_table(): void {
		global $wpdb;

		$logger = SimpleLogger();
		$logger->info( 'Default contexts table test' );
		$event_id = (int) $logger->last_insert_id;

		$logger->append_context( $event_id, [ 'default_target' => 'default-table' ] );

		$default_contexts = Simple_History::get_instance()->get_contexts_table_name();
		$landed           = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$default_contexts} WHERE history_id = %d AND `key` = %s",
				$event_id,
				'default_target'
			)
		);

		$this->assertSame( 'default-table', $landed, 'append_context() without explicit table must default to $this->db_table_contexts.' );
	}

	public function test_event_subclass_overrides_reach_load_data(): void {
		global $wpdb;

		// Insert an event row directly into the alternate tables — no
		// SimpleLogger, because we want the event to exist ONLY in the
		// alt tables. A subclass that overrides the table getters must
		// find this event; a vanilla Event must not.
		$wpdb->insert(
			$this->alt_events_table,
			[
				'date'        => gmdate( 'Y-m-d H:i:s' ),
				'logger'      => 'SimpleLogger',
				'level'       => 'info',
				'message'     => 'Alt-only event',
				'occasionsID' => 'alt-occ-' . wp_generate_password( 8, false ),
				'initiator'   => 'wp_user',
			]
		);
		$alt_event_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$this->alt_contexts_table,
			[
				'history_id' => $alt_event_id,
				'key'        => 'source',
				'value'      => 'alt-tables',
			]
		);

		$alt_events   = $this->alt_events_table;
		$alt_contexts = $this->alt_contexts_table;
		$alt_event    = new class( $alt_event_id, $alt_events, $alt_contexts ) extends Event {
			/** @var string */
			private $alt_events_table;

			/** @var string */
			private $alt_contexts_table;

			public function __construct( int $event_id, string $events_table, string $contexts_table ) {
				// Stash table overrides on the instance BEFORE calling
				// parent::__construct(), which triggers load_data() and
				// therefore our overridden getters.
				$this->alt_events_table   = $events_table;
				$this->alt_contexts_table = $contexts_table;

				parent::__construct( $event_id );
			}

			protected function get_events_table_name() {
				return $this->alt_events_table;
			}

			protected function get_contexts_table_name() {
				return $this->alt_contexts_table;
			}
		};

		$this->assertTrue(
			$alt_event->exists(),
			'Subclass that overrides get_*_table_name() must resolve alt-table-only events. If this fails, Event::query_db_for_events() is bypassing the instance getters (the exact bug the fix was meant to close).'
		);
		$this->assertSame(
			'alt-tables',
			$alt_event->get_context()['source'] ?? null,
			'Alt event context must come from the alternate contexts table — confirms the contexts JOIN in query_db_for_events also uses the subclass override.'
		);

		// And the inverse: a plain Event for the same ID must NOT find it,
		// proving the alt event really only lives in the alt tables.
		wp_cache_flush();
		$plain = new Event( $alt_event_id );
		$this->assertFalse(
			$plain->exists(),
			'Plain Event must not find an event that only exists in the alt tables. If this passes, the alt tables are leaking into the default tables and the positive assertion above is meaningless.'
		);
	}

	public function test_helpers_get_network_history_admin_url_returns_null_on_non_multisite(): void {
		$this->assertFalse( is_multisite(), 'Expected single-site test bed.' );
		$this->assertNull(
			Helpers::get_network_history_admin_url(),
			'On a single-site install, get_network_history_admin_url() must return null — there is no network scope to link to.'
		);
	}

	public function test_export_set_is_network_is_chainable_and_boolean_cast(): void {
		$export = new Export();

		$this->assertSame(
			$export,
			$export->set_is_network( true ),
			'set_is_network() must return $this for chaining with the other setters.'
		);

		// Boolean cast — truthy non-bool must become true.
		$export->set_is_network( 'yes' );
		$reflect = new ReflectionProperty( Export::class, 'is_network' );
		$reflect->setAccessible( true );
		$this->assertTrue( $reflect->getValue( $export ), 'set_is_network() must cast its argument to bool.' );

		$export->set_is_network( 0 );
		$this->assertFalse( $reflect->getValue( $export ) );
	}
}
