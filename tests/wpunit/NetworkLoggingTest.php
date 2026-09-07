<?php

use Simple_History\Event;
use Simple_History\Export;
use Simple_History\Helpers;
use Simple_History\Log_Initiators;
use Simple_History\Log_Query;
use Simple_History\Loggers\Logger;
use Simple_History\Simple_History;
use Simple_History\WP_REST_User_Card_Controller;

/**
 * Tests for the network-scoped routing primitives added in 5.13.0:
 *
 * - Logger::should_use_network_tables() decision gates (the per-event
 *   _event_scope hint and the class-level $is_network_logger flag — the
 *   only two signals; routing never sniffs the current screen)
 * - Logger::mark_as_network_scoped(), used by the plugin and theme
 *   handlers to declare filesystem-level events network-scoped
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
			public function should_use_network_tables( $context = [] ) {
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

	public function test_semantic_scope_hint_routes_to_network_tables(): void {
		global $wpdb;

		// Register network tables + a logger that follows the normal
		// should_use_network_tables() logic (no forced override). With
		// only the EVENT_SCOPE_CONTEXT_KEY hint in $context, Logger::log()
		// should route to the network tables.
		$sh = Simple_History::get_instance();
		$sh->set_network_tables( $this->alt_events_table, $this->alt_contexts_table );

		$logger = new class( $sh ) extends Logger {
			public $slug = 'SH_Test_ScopeHint';

			public function get_info() {
				return [
					'name'        => 'Test scope-hint logger',
					'description' => '',
					'messages'    => [],
				];
			}

			// Force the multisite gate open for this test. Normal request
			// context detection otherwise bails before the hint is checked.
			protected function should_use_network_tables( $context = [] ) {
				if ( isset( $context[ self::EVENT_SCOPE_CONTEXT_KEY ] ) ) {
					return $context[ self::EVENT_SCOPE_CONTEXT_KEY ] === 'network';
				}
				return false;
			}
		};

		// Network-scoped event via the hint.
		$logger->info(
			'Scope-hint event',
			[
				Logger::EVENT_SCOPE_CONTEXT_KEY => 'network',
				'marker'                        => 'hinted-network',
			]
		);

		$alt_row = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->alt_events_table} WHERE message = %s",
				'Scope-hint event'
			)
		);
		$this->assertSame( '1', (string) $alt_row, 'Event tagged with _event_scope=network must land in the registered network events table.' );

		// Same logger, no hint → stays on per-site tables.
		$logger->info( 'No-hint event', [ 'marker' => 'unhinted' ] );

		$default_events = $sh->get_events_table_name();
		$default_row    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$default_events} WHERE message = %s",
				'No-hint event'
			)
		);
		$this->assertSame( '1', (string) $default_row, 'Event without _event_scope must stay on the per-site tables.' );
	}

	public function test_event_scope_context_key_takes_precedence_on_multisite(): void {
		// Verify the semantic-scope hint wins over request-context heuristics.
		// Can't flip is_multisite() from within a single-site test bed, but we
		// can confirm that without is_multisite() the hint is still ignored —
		// the multisite gate remains the outer guard.
		$this->assertFalse( is_multisite(), 'Single-site test bed.' );

		$logger  = SimpleLogger();
		$reflect = new ReflectionMethod( $logger, 'should_use_network_tables' );
		$reflect->setAccessible( true );

		$network_hint = [ Logger::EVENT_SCOPE_CONTEXT_KEY => 'network' ];
		$site_hint    = [ Logger::EVENT_SCOPE_CONTEXT_KEY => 'site' ];

		$this->assertFalse(
			$reflect->invoke( $logger, $network_hint ),
			'Multisite gate must block even a network-scope hint on a single-site install.'
		);
		$this->assertFalse(
			$reflect->invoke( $logger, $site_hint ),
			'Site-scope hint should obviously also return false on single-site.'
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
		// show the small "network action" note in the React log UI. It is
		// only written for events a person caused, so log in first.
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

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

	/**
	 * The teaser page, admin bar routing and "network-level" markers must
	 * key off a registered provider, not off the Premium plugin being active:
	 * a Premium too old to carry the network module is active but provides
	 * nothing, and the user should still see the teaser in that case.
	 */
	public function test_network_log_provider_registered_reflects_set_network_tables(): void {
		$this->assertFalse( Helpers::network_log_provider_registered(), 'No provider before set_network_tables().' );

		Simple_History::get_instance()->set_network_tables( 'wp_events_network', 'wp_contexts_network' );

		$this->assertTrue( Helpers::network_log_provider_registered(), 'Provider registered after set_network_tables().' );
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
		// proving the alt event really only lives in the alt tables. No cache
		// flush needed: the cache key carries the events table name, so the
		// two reads cannot share an entry.
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

	/**
	 * Background events — available updates, core file checks, translation
	 * updates — are logged by whole-logger network loggers on every site,
	 * on a schedule, with nobody watching. Flagging them would put a
	 * "this belongs in the network log" note on a steady stream of events
	 * no one acted on, so the flag is only written for events a person
	 * caused.
	 */
	public function test_fallback_flag_is_only_written_for_events_a_person_caused(): void {
		global $wpdb;

		// Logged out, and an explicit non-user initiator: this is what a
		// cron-driven logger looks like.
		wp_set_current_user( 0 );

		$logger = $this->make_forced_network_logger();
		$logger->info(
			'Background fallback event',
			[
				'_initiator' => Log_Initiators::WORDPRESS,
				'marker'     => 'background-flag-check',
			]
		);
		$event_id = (int) $logger->last_insert_id;

		$default_contexts = Simple_History::get_instance()->get_contexts_table_name();

		$flag_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT value FROM {$default_contexts} WHERE history_id = %d AND `key` = %s",
				$event_id,
				'_network_fallback'
			)
		);

		$this->assertNull( $flag_value, 'Events not caused by a user must not be tagged with _network_fallback — the note is advice for a person, and background loggers would flood the log with it.' );
	}

	/**
	 * Routing is deterministic: the only signals are the per-event scope
	 * hint and the class-level flag. The message-key allowlist that used
	 * to route by message key is gone, so a logger cannot silently opt
	 * events into the network log by name.
	 */
	public function test_message_key_allowlist_is_gone(): void {
		$classes = [
			Logger::class,
			\Simple_History\Loggers\Plugin_Logger::class,
			\Simple_History\Loggers\Theme_Logger::class,
		];

		foreach ( $classes as $class_name ) {
			$this->assertFalse(
				( new ReflectionClass( $class_name ) )->hasProperty( 'network_scoped_message_keys' ),
				sprintf( '%s must not declare $network_scoped_message_keys — routing by message key was replaced by the explicit _event_scope hint.', $class_name )
			);
		}
	}

	/**
	 * The scope hint is a multisite concept. On a single-site install the
	 * helper must leave the context alone, so single-site logs don't grow
	 * an _event_scope context row that means nothing there.
	 */
	public function test_mark_as_network_scoped_is_a_noop_on_single_site(): void {
		$this->assertFalse( is_multisite(), 'Single-site test bed.' );

		$logger  = SimpleLogger();
		$reflect = new ReflectionMethod( $logger, 'mark_as_network_scoped' );
		$reflect->setAccessible( true );

		$context = [ 'plugin_name' => 'Some plugin' ];

		$this->assertSame(
			$context,
			$reflect->invoke( $logger, $context ),
			'mark_as_network_scoped() must return the context unchanged on a single-site install.'
		);
	}

	/**
	 * Filesystem-level plugin and theme events are network-scoped, and the
	 * handlers say so themselves instead of routing being guessed from the
	 * current screen. is_multisite() is false in this test bed, so the
	 * subclasses below force the helper open; what is under test is that
	 * the handler runs its context through the helper at all.
	 */
	public function test_plugin_deletion_marks_its_context_as_network_scoped(): void {
		$logger = new class( Simple_History::get_instance() ) extends \Simple_History\Loggers\Plugin_Logger {
			protected function mark_as_network_scoped( $context ) {
				$context[ self::EVENT_SCOPE_CONTEXT_KEY ] = 'network';

				return $context;
			}

			public function seed_plugin_data( $plugin_file, $plugin_data ) {
				$this->plugins_data[ $plugin_file ] = $plugin_data;
			}
		};

		$logger->seed_plugin_data(
			'sh-probe/sh-probe.php',
			[
				'Name'        => 'SH Probe',
				'Title'       => 'SH Probe',
				'Description' => '',
				'Author'      => '',
				'Version'     => '1.0.0',
				'PluginURI'   => '',
			]
		);

		$context = $this->capture_logged_context(
			function () use ( $logger ) {
				$logger->on_action_deleted_plugin( 'sh-probe/sh-probe.php', true );
			},
			'plugin_deleted'
		);

		$this->assertIsArray( $context, 'Deleting a plugin must log a plugin_deleted event.' );
		$this->assertSame(
			'network',
			$context[ Logger::EVENT_SCOPE_CONTEXT_KEY ] ?? null,
			'Deleting plugin files affects the whole network, so the handler must mark the event as network-scoped.'
		);
	}

	public function test_theme_deletion_marks_its_context_as_network_scoped(): void {
		$logger = new class( Simple_History::get_instance() ) extends \Simple_History\Loggers\Theme_Logger {
			protected function mark_as_network_scoped( $context ) {
				$context[ self::EVENT_SCOPE_CONTEXT_KEY ] = 'network';

				return $context;
			}

			public function seed_theme_data( $stylesheet, $theme_data ) {
				$this->themes_data[ $stylesheet ] = $theme_data;
			}
		};

		$logger->seed_theme_data(
			'sh-probe-theme',
			[
				'name'        => 'SH Probe Theme',
				'version'     => '1.0.0',
				'author'      => '',
				'description' => '',
			]
		);

		$context = $this->capture_logged_context(
			function () use ( $logger ) {
				$logger->on_action_deleted_theme( 'sh-probe-theme', true );
			},
			'theme_deleted'
		);

		$this->assertIsArray( $context, 'Deleting a theme must log a theme_deleted event.' );
		$this->assertSame(
			'network',
			$context[ Logger::EVENT_SCOPE_CONTEXT_KEY ] ?? null,
			'Deleting theme files affects the whole network, so the handler must mark the event as network-scoped.'
		);
	}

	/**
	 * Run $callback and return the context of the event logged with
	 * $message_key, or null if no such event was logged.
	 *
	 * @param callable $callback    Code that is expected to log an event.
	 * @param string   $message_key Message key to capture.
	 * @return array|null
	 */
	private function capture_logged_context( callable $callback, string $message_key ): ?array {
		$captured = null;

		$filter = function ( $context ) use ( &$captured, $message_key ) {
			if ( ( $context['_message_key'] ?? null ) === $message_key ) {
				$captured = $context;
			}

			return $context;
		};

		add_filter( 'simple_history/log_insert_context', $filter );

		try {
			$callback();
		} finally {
			remove_filter( 'simple_history/log_insert_context', $filter );
		}

		return $captured;
	}

	/**
	 * Build an Event subclass pointed at the alternate tables. Stands in
	 * for Premium's Network_Event, which does exactly this.
	 */
	private function make_alt_event( int $event_id ): Event {
		return new class( $event_id, $this->alt_events_table, $this->alt_contexts_table ) extends Event {
			/** @var string */
			private $alt_events_table;

			/** @var string */
			private $alt_contexts_table;

			public function __construct( int $event_id, string $events_table, string $contexts_table ) {
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
	}

	/**
	 * Build a Log_Query subclass pointed at the alternate tables. Stands in
	 * for Premium's Network_Log_Query.
	 */
	private function make_alt_log_query(): Log_Query {
		return new class( $this->alt_events_table, $this->alt_contexts_table ) extends Log_Query {
			/** @var string */
			private $alt_events_table;

			/** @var string */
			private $alt_contexts_table;

			public function __construct( string $events_table, string $contexts_table ) {
				$this->alt_events_table   = $events_table;
				$this->alt_contexts_table = $contexts_table;
			}

			protected function get_events_table_name() {
				return $this->alt_events_table;
			}

			protected function get_contexts_table_name() {
				return $this->alt_contexts_table;
			}

			// Exposed so the test can assert on the routing decision itself.
			public function is_network_query(): bool {
				return parent::is_network_query();
			}
		};
	}

	/**
	 * Event IDs restart at 1 in every table, so the per-site event 57 and
	 * the network event 57 are different events. They must not share a
	 * cache entry.
	 */
	public function test_event_cache_key_carries_the_events_table(): void {
		global $wpdb;

		// A real per-site event, then a different event with the SAME id in
		// the alt tables. Explicit id, because the collision only happens
		// when the numbers match.
		$per_site_logger = SimpleLogger();
		$per_site_logger->info( 'Per-site event', [ 'marker' => 'per-site' ] );
		$event_id = (int) $per_site_logger->last_insert_id;

		$wpdb->insert(
			$this->alt_events_table,
			[
				'id'          => $event_id,
				'date'        => gmdate( 'Y-m-d H:i:s' ),
				'logger'      => 'SimpleLogger',
				'level'       => 'info',
				'message'     => 'Network event',
				'occasionsID' => 'alt-occ-' . wp_generate_password( 8, false ),
				'initiator'   => 'wp_user',
			]
		);

		$wpdb->insert(
			$this->alt_contexts_table,
			[
				'history_id' => $event_id,
				'key'        => 'marker',
				'value'      => 'network',
			]
		);

		// Load the per-site one FIRST so it is the one sitting in the cache.
		$per_site_event = new Event( $event_id );
		$this->assertSame( 'per-site', $per_site_event->get_context()['marker'] ?? null );

		// Same numeric id, different table. No cache flush in between: this
		// is the whole point.
		$network_event = $this->make_alt_event( $event_id );

		$this->assertSame(
			'network',
			$network_event->get_context()['marker'] ?? null,
			'An Event reading the network tables must not be served the cached per-site event of the same id. If this fails, Event::load_data() built its cache key without the table name.'
		);

		// And the reverse direction, to prove the per-site entry was not
		// overwritten by the network read either.
		$per_site_again = new Event( $event_id );
		$this->assertSame(
			'per-site',
			$per_site_again->get_context()['marker'] ?? null,
			'Reading the network event must not evict or overwrite the cached per-site event of the same id.'
		);
	}

	/**
	 * Log_Query knows it is reading someone else's tables by comparing them
	 * with the per-site ones — no constructor flag to forget to set.
	 */
	public function test_is_network_query_is_derived_from_the_events_table(): void {
		$this->assertTrue(
			$this->make_alt_log_query()->is_network_query(),
			'A Log_Query subclass pointed at other tables must report itself as a network query.'
		);

		$reflect = new ReflectionMethod( Log_Query::class, 'is_network_query' );
		$reflect->setAccessible( true );

		$this->assertFalse(
			$reflect->invoke( new Log_Query() ),
			'A plain Log_Query reads the per-site tables and must not report itself as a network query.'
		);
	}

	/**
	 * A write to the shared network tables has to invalidate the cached
	 * network queries — the per-site incrementor that Helpers::clear_cache()
	 * bumps is a different key, and on a real network a different site's
	 * key entirely.
	 */
	public function test_network_write_invalidates_a_cached_network_query(): void {
		// An administrator, so Log_Query does not filter the rows away on
		// logger capabilities.
		wp_set_current_user( $this->factory()->user->create( [ 'role' => 'administrator' ] ) );

		Simple_History::get_instance()->set_network_tables(
			$this->alt_events_table,
			$this->alt_contexts_table
		);

		$logger = $this->make_forced_network_logger();

		// Log_Query only returns rows from loggers the current user may
		// read, and the anonymous test logger is not registered. Borrow
		// SimpleLogger's slug so the rows survive that filter.
		$logger->slug = 'SimpleLogger';

		$logger->info( 'First network event' );

		$query_args = [
			'posts_per_page' => 10,
			'ungrouped'      => true,
		];

		$first = $this->make_alt_log_query()->query( $query_args );
		$this->assertSame( 1, (int) $first['total_row_count'], 'Sanity: the network query must see the first event.' );

		// Prove the result really is cached, so the assertion below is about
		// invalidation and not about the cache never being used.
		$cached = $this->make_alt_log_query()->query( $query_args );
		$this->assertTrue( $cached['cached_result'] ?? false, 'Sanity: the second identical network query must be served from cache.' );

		$logger->info( 'Second network event' );

		$after_write = $this->make_alt_log_query()->query( $query_args );

		$this->assertArrayNotHasKey( 'cached_result', $after_write, 'A network write must invalidate the cached network query, so the next query goes to the database.' );
		$this->assertSame(
			2,
			(int) $after_write['total_row_count'],
			'The network query must see the event that was just written to the network tables. If this fails, Logger::log() did not bump the network cache incrementor.'
		);
	}

	/**
	 * The per-site cache and the network cache are separate: a per-site
	 * write must not throw away cached network results, and the two live
	 * in different groups.
	 */
	public function test_network_and_per_site_cache_groups_are_separate(): void {
		$this->assertNotSame(
			Helpers::get_cache_group(),
			Helpers::get_cache_group( true ),
			'Network reads must be cached in their own group.'
		);

		$network_group_before = Helpers::get_cache_group( true );

		Helpers::clear_cache();

		$this->assertSame(
			$network_group_before,
			Helpers::get_cache_group( true ),
			'Clearing the per-site cache must leave the network cache alone — nothing in the shared network tables changed.'
		);

		$per_site_group_before = Helpers::get_cache_group();

		Helpers::clear_network_cache();

		$this->assertNotSame(
			$network_group_before,
			Helpers::get_cache_group( true ),
			'clear_network_cache() must bump the network incrementor.'
		);
		$this->assertSame(
			$per_site_group_before,
			Helpers::get_cache_group(),
			'Clearing the network cache must leave the per-site cache alone.'
		);
	}

	/**
	 * The network incrementor lives in a global cache group so every site
	 * in a network reads the same value. Without that registration a write
	 * on site 3 would leave site 1 serving stale network results.
	 */
	public function test_network_cache_incrementor_lives_in_a_global_group(): void {
		global $wp_object_cache;

		Helpers::register_cache_groups();

		$this->assertNotEmpty(
			$wp_object_cache->global_groups[ Helpers::NETWORK_CACHE_GLOBAL_GROUP ] ?? null,
			'The network cache group must be registered as global, or wp_cache_* prefixes its keys with the current blog id and each site keeps its own incrementor.'
		);
	}

	/**
	 * Core must never run its own per-site table recovery for a missing
	 * network table: that path deletes simple_history_db_version and re-runs
	 * the per-site setup, which does not create the network table and does
	 * quietly reset this site's schema state.
	 */
	public function test_failed_network_insert_leaves_the_per_site_db_version_alone(): void {
		global $wpdb;

		$missing_events_table = $wpdb->prefix . 'sh_test_missing_network_events';

		Simple_History::get_instance()->set_network_tables(
			$missing_events_table,
			$wpdb->prefix . 'sh_test_missing_network_contexts'
		);

		$db_version_before = get_option( 'simple_history_db_version' );
		$this->assertNotFalse( $db_version_before, 'Sanity: the per-site schema version option must exist before the failed write.' );

		// Suppress the expected "table doesn't exist" error output.
		$suppress_before = $wpdb->suppress_errors( true );

		$logger = $this->make_forced_network_logger();
		$logger->info( 'Event for a network table that does not exist' );

		$wpdb->suppress_errors( $suppress_before );

		$this->assertSame(
			$db_version_before,
			get_option( 'simple_history_db_version' ),
			'A failed write to the network tables must not touch the per-site schema version. If this fails, log() ran Setup_Database::recreate_tables_if_missing() for someone else\'s table.'
		);
	}

	/**
	 * With a provider-supplied recovery callback, the missing network table
	 * is handed back to the provider, and the write is retried once.
	 */
	public function test_missing_network_table_is_recreated_through_the_provider_callback(): void {
		global $wpdb;

		$recovery_events_table   = $wpdb->prefix . 'sh_test_recovered_events';
		$recovery_contexts_table = $wpdb->prefix . 'sh_test_recovered_contexts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$recovery_events_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$recovery_contexts_table}" );

		$calls = 0;

		Simple_History::get_instance()->set_network_tables(
			$recovery_events_table,
			$recovery_contexts_table,
			function () use ( $recovery_events_table, $recovery_contexts_table, &$calls ) {
				++$calls;

				global $wpdb;

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query(
					"CREATE TABLE IF NOT EXISTS {$recovery_events_table} (
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
					"CREATE TABLE IF NOT EXISTS {$recovery_contexts_table} (
						context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						history_id bigint(20) unsigned NOT NULL,
						`key` varchar(255) DEFAULT NULL,
						value longtext,
						PRIMARY KEY  (context_id),
						KEY history_id (history_id)
					)"
				);

				return true;
			}
		);

		$suppress_before = $wpdb->suppress_errors( true );

		$logger = $this->make_forced_network_logger();
		$logger->info( 'Event that recreates its own table' );

		$wpdb->suppress_errors( $suppress_before );

		$this->assertSame( 1, $calls, 'The provider recovery callback must be called exactly once for the missing network table.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$recovery_events_table} WHERE message = %s", 'Event that recreates its own table' ) );

		$this->assertSame( '1', (string) $found, 'The write must be retried once after the provider recreated the table.' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$recovery_events_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$recovery_contexts_table}" );
	}

	/**
	 * The user card filters hand over the controller that is serving the
	 * card, so a callback can ask it for numbers instead of guessing which
	 * log the card belongs to.
	 */
	public function test_user_card_details_filter_receives_the_controller(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$received = null;

		$capture = function ( $details, $user, $controller = null ) use ( &$received ) {
			$received = $controller;

			return $details;
		};

		add_filter( 'simple_history/user_card/details', $capture, 10, 3 );

		$controller = new WP_REST_User_Card_Controller();
		$request    = new WP_REST_Request( 'GET', '/simple-history/v1/users/' . $user_id . '/card' );
		$request->set_param( 'id', $user_id );
		$controller->get_user_card( $request );

		remove_filter( 'simple_history/user_card/details', $capture, 10 );

		$this->assertSame( $controller, $received, 'The controller instance must reach the user card details filter as the third argument.' );
	}

	/**
	 * A controller subclass that overrides the two seams reads its own log:
	 * this is what Premium's network user card controller does.
	 */
	public function test_user_card_controller_seams_reroute_the_counters(): void {
		global $wpdb;

		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Two events for this user in the alt ("network") tables only.
		foreach ( [ 'Alt event one', 'Alt event two' ] as $message ) {
			$wpdb->insert(
				$this->alt_events_table,
				[
					'date'        => gmdate( 'Y-m-d H:i:s' ),
					'logger'      => 'SimpleLogger',
					'level'       => 'info',
					'message'     => $message,
					'occasionsID' => 'alt-occ-' . wp_generate_password( 8, false ),
					'initiator'   => 'wp_user',
				]
			);

			$wpdb->insert(
				$this->alt_contexts_table,
				[
					'history_id' => (int) $wpdb->insert_id,
					'key'        => '_user_id',
					'value'      => (string) $user_id,
				]
			);
		}

		$alt_events   = $this->alt_events_table;
		$alt_contexts = $this->alt_contexts_table;

		$network_controller = new class( $alt_events, $alt_contexts ) extends WP_REST_User_Card_Controller {
			/** @var string */
			private $alt_events_table;

			/** @var string */
			private $alt_contexts_table;

			public function __construct( string $events_table, string $contexts_table ) {
				parent::__construct();

				$this->alt_events_table   = $events_table;
				$this->alt_contexts_table = $contexts_table;
			}

			protected function create_log_query() {
				return new class( $this->alt_events_table, $this->alt_contexts_table ) extends Log_Query {
					/** @var string */
					private $alt_events_table;

					/** @var string */
					private $alt_contexts_table;

					public function __construct( string $events_table, string $contexts_table ) {
						$this->alt_events_table   = $events_table;
						$this->alt_contexts_table = $contexts_table;
					}

					protected function get_events_table_name() {
						return $this->alt_events_table;
					}

					protected function get_contexts_table_name() {
						return $this->alt_contexts_table;
					}
				};
			}

			protected function get_events_table_name() {
				return $this->alt_events_table;
			}
		};

		$this->assertSame(
			2,
			(int) $network_controller->user_total_event_count( $user_id ),
			'A controller subclass overriding create_log_query() must count events from its own tables. If this returns the per-site number, the helper is still building its own Log_Query.'
		);

		$this->assertSame(
			2,
			(int) $network_controller->initiator_card_stats( 'wp_user' )['total'],
			'initiator_card_stats() must read the table from get_events_table_name(), not from the per-site Simple_History instance.'
		);

		$this->assertSame(
			0,
			(int) ( new WP_REST_User_Card_Controller() )->user_total_event_count( $user_id ),
			'Sanity: the alt-table events must be invisible to the per-site controller, or the assertions above prove nothing.'
		);
	}

	/**
	 * The five card helpers were public statics before 5.33.0 and Premium
	 * 1.15 and older still calls them that way. They must keep working, and
	 * keep returning exactly what the instance methods return.
	 */
	public function test_deprecated_static_card_helpers_match_the_instance_methods(): void {
		global $wpdb;

		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$events_table   = Simple_History::get_instance()->get_events_table_name();
		$contexts_table = Simple_History::get_instance()->get_contexts_table_name();

		// A login event (so the login helpers have something to find) and a
		// plain one, both attributed to this user.
		$events = [
			[
				'logger'   => 'SimpleUserLogger',
				'message'  => 'Logged in',
				'contexts' => [
					'_user_id'               => (string) $user_id,
					'_message_key'           => 'user_logged_in',
					'_server_remote_addr'    => '192.0.2.10',
					'server_http_user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/120.0',
				],
			],
			[
				'logger'   => 'SimpleLogger',
				'message'  => 'Did a thing',
				'contexts' => [
					'_user_id' => (string) $user_id,
				],
			],
		];

		foreach ( $events as $event ) {
			$wpdb->insert(
				$events_table,
				[
					'date'        => gmdate( 'Y-m-d H:i:s' ),
					'logger'      => $event['logger'],
					'level'       => 'info',
					'message'     => $event['message'],
					'occasionsID' => 'bc-occ-' . wp_generate_password( 8, false ),
					'initiator'   => 'wp_user',
				]
			);

			$history_id = (int) $wpdb->insert_id;

			foreach ( $event['contexts'] as $key => $value ) {
				$wpdb->insert(
					$contexts_table,
					[
						'history_id' => $history_id,
						'key'        => $key,
						'value'      => $value,
					]
				);
			}
		}

		$controller = new WP_REST_User_Card_Controller();

		$this->assertNotNull(
			$controller->user_last_login( $user_id ),
			'Sanity: the fixture login event must be findable, or the comparisons below compare two nulls.'
		);

		$this->assertSame(
			$controller->user_last_login( $user_id ),
			WP_REST_User_Card_Controller::get_last_login( $user_id ),
			'The deprecated get_last_login() wrapper must return what user_last_login() returns.'
		);

		$this->assertSame(
			$controller->user_event_count( $user_id, 7 ),
			WP_REST_User_Card_Controller::get_user_event_count( $user_id, 7 ),
			'The deprecated get_user_event_count() wrapper must return what user_event_count() returns.'
		);

		$this->assertSame(
			2,
			(int) $controller->user_total_event_count( $user_id ),
			'Sanity: both fixture events must be counted.'
		);

		$this->assertSame(
			$controller->user_total_event_count( $user_id ),
			WP_REST_User_Card_Controller::get_user_total_event_count( $user_id ),
			'The deprecated get_user_total_event_count() wrapper must return what user_total_event_count() returns.'
		);

		$this->assertSame(
			$controller->user_last_event( $user_id ),
			WP_REST_User_Card_Controller::get_last_event( $user_id ),
			'The deprecated get_last_event() wrapper must return what user_last_event() returns.'
		);

		$this->assertSame(
			$controller->user_last_login_session( $user_id ),
			WP_REST_User_Card_Controller::get_last_login_session( $user_id ),
			'The deprecated get_last_login_session() wrapper must return what user_last_login_session() returns.'
		);

		$this->assertSame(
			'192.0.2.10',
			WP_REST_User_Card_Controller::get_last_login_session( $user_id )['ip'],
			'Sanity: the session helper must actually read the login event context.'
		);

		$this->assertSame(
			$controller->initiator_card_stats( 'wp_user' ),
			WP_REST_User_Card_Controller::get_initiator_card_stats( 'wp_user' ),
			'The deprecated get_initiator_card_stats() wrapper must return what initiator_card_stats() returns.'
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
