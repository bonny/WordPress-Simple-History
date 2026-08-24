<?php

use Simple_History\Simple_History;
use Simple_History\Services\Abilities_Service;
use Simple_History\Helpers;

/**
 * Registering Simple History abilities with the WordPress Abilities API.
 *
 * The Abilities API is WordPress 6.9+. Simple History supports 6.3+, so the
 * service has to no-op cleanly on older versions rather than fatal. The test
 * suite defaults to WP 6.8, so the registration tests skip unless the suite is
 * run with WORDPRESS_VERSION=6.9.
 *
 * @coversDefaultClass Simple_History\Services\Abilities_Service
 */
class AbilitiesServiceTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Turn the abilities on for the duration of these tests.
	 *
	 * They are gated behind the experimental features setting, which is off by
	 * default and off in the test fixture. The gate is evaluated when the
	 * abilities registry first initialises, so filtering here is early enough
	 * as long as nothing has asked the registry for an ability yet — these are
	 * the only tests that do.
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
	}

	/**
	 * @inheritDoc
	 */
	public function tearDown(): void {
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );

		parent::tearDown();
	}

	/**
	 * Skip a test when the Abilities API is not present.
	 */
	private function require_abilities_api() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API requires WordPress 6.9+. Run with WORDPRESS_VERSION=6.9.' );
		}
	}

	/**
	 * Assert the plugin registered its abilities during bootstrap.
	 *
	 * Registering here instead would trip _doing_it_wrong — WordPress requires
	 * wp_register_ability() to run on wp_abilities_api_init and
	 * wp_register_ability_category() on wp_abilities_api_categories_init.
	 */
	private function ensure_abilities_registered() {
		$this->require_abilities_api();

		$this->assertNotNull(
			wp_get_ability( 'simple-history/get-recent-events' ),
			'The plugin should register its abilities during bootstrap.'
		);
	}

	/**
	 * The hook is only added when the API exists, and is always added when it does.
	 *
	 * This assertion is meaningful on both WP 6.8 and 6.9, which is why it does
	 * not skip.
	 *
	 * @covers ::loaded
	 */
	public function test_hook_is_registered_only_when_abilities_api_exists() {
		// The plugin already hooked this during bootstrap. Clearing it isolates
		// the assertion, so put it back afterwards rather than leaving another
		// test to discover the hook missing.
		global $wp_filter;
		$original = $wp_filter['wp_abilities_api_init'] ?? null;

		remove_all_actions( 'wp_abilities_api_init' );

		$service = new Abilities_Service( Simple_History::get_instance() );
		$service->loaded();

		try {
			if ( function_exists( 'wp_register_ability' ) ) {
				$this->assertNotFalse(
					has_action( 'wp_abilities_api_init', [ $service, 'register_abilities' ] ),
					'Abilities should be registered on WordPress 6.9+.'
				);
			} else {
				$this->assertFalse(
					has_action( 'wp_abilities_api_init' ),
					'Nothing should hook the abilities init on WordPress below 6.9.'
				);
			}
		} finally {
			remove_all_actions( 'wp_abilities_api_init' );

			if ( null !== $original ) {
				$wp_filter['wp_abilities_api_init'] = $original;
			}
		}
	}

	/**
	 * The service must be wired into the plugin, not merely exist.
	 *
	 * @covers ::loaded
	 */
	public function test_service_is_registered_with_the_plugin() {
		$found = false;

		foreach ( Simple_History::get_instance()->get_instantiated_services() as $service ) {
			if ( $service instanceof Abilities_Service ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Abilities_Service should be in the plugin service list.' );
	}

	/**
	 * The category must actually be registered, not merely referenced by the
	 * abilities — this is precisely what broke when registration ran on the
	 * wrong hook.
	 *
	 * @covers ::register_category
	 */
	public function test_category_is_registered() {
		$this->ensure_abilities_registered();

		$category = wp_get_ability_category( 'simple-history' );

		$this->assertNotNull( $category, 'The simple-history ability category should be registered.' );
		$this->assertSame( 'Simple History', $category->get_label() );
	}

	/**
	 * An audit log an agent can erase is worse than no audit log, so Simple
	 * History registers read abilities only — no create, update, delete or purge.
	 *
	 * @covers ::register_abilities
	 */
	public function test_registers_no_write_or_destructive_abilities() {
		$this->ensure_abilities_registered();

		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();

			if ( strpos( $name, 'simple-history/' ) !== 0 ) {
				continue;
			}

			// Split on both '/' and '-' so only whole tokens are checked, not
			// substrings — a legitimately read-only name could otherwise
			// false-positive, e.g. "offset" containing "set".
			$tokens = preg_split( '/[\/-]/', $name );

			foreach ( [ 'create', 'update', 'delete', 'purge', 'set', 'remove' ] as $verb ) {
				$this->assertNotContains(
					$verb,
					$tokens,
					'Simple History registers read abilities only.'
				);
			}
		}
	}

	/**
	 * Reading the log requires being logged in and holding the view-history
	 * capability.
	 *
	 * @covers ::check_events_permission
	 */
	public function test_events_permission_is_refused_when_logged_out() {
		wp_set_current_user( 0 );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertFalse( $service->check_events_permission() );
	}

	/**
	 * Being logged in is not enough. The events route requires the same
	 * capability as the history page itself, so a subscriber is refused even
	 * though they are authenticated.
	 *
	 * @covers ::check_events_permission
	 */
	public function test_events_permission_is_refused_without_view_history_capability() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertFalse( $service->check_events_permission() );
	}

	/**
	 * A user who may view the history page may read the log through an ability.
	 * What they actually see is filtered inside Log_Query, not here — this
	 * helper only mirrors the REST route's check.
	 *
	 * @covers ::check_events_permission
	 */
	public function test_events_permission_is_granted_with_view_history_capability() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertTrue(
			current_user_can( Helpers::get_view_history_capability() ),
			'An editor is expected to hold the default view-history capability.'
		);

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertTrue( $service->check_events_permission() );
	}

	/**
	 * Stats are stricter than events: the stats controller requires
	 * manage_options where the events controller requires the view-history
	 * capability, which an editor holds. That asymmetry is deliberate and must
	 * survive.
	 *
	 * @covers ::check_stats_permission
	 */
	public function test_stats_permission_is_refused_for_editor() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertFalse( $service->check_stats_permission() );
	}

	/**
	 * @covers ::check_stats_permission
	 */
	public function test_stats_permission_is_granted_to_administrator() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertTrue( $service->check_stats_permission() );
	}

	/**
	 * A burst of failed logins must be countable.
	 *
	 * The obvious expectation is one row per attempt, and that is wrong here:
	 * the user logger gives every failed login to an unknown user the same
	 * _occasionsID so a brute-force run cannot flood the log. The count
	 * therefore lives in occasions, and per_page would truncate a row-per-
	 * attempt response long before an agent could add it up.
	 *
	 * @covers ::execute_get_failed_logins
	 */
	public function test_failed_login_burst_is_counted_in_occasions() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$attempts = 6;
		for ( $i = 0; $i < $attempts; $i++ ) {
			wp_authenticate( 'occasions_probe_user', 'wrong-password' );
		}

		$result = wp_get_ability( 'simple-history/get-failed-logins' )->execute( [ 'per_page' => 20 ] );

		$this->assertNotWPError( $result );
		$this->assertNotEmpty( $result, 'Failed logins should be logged and returned.' );

		$counted = array_sum( array_map( static fn( $row ) => (int) ( $row['occasions'] ?? 1 ), $result ) );

		$this->assertGreaterThanOrEqual(
			$attempts,
			$counted,
			'Summing occasions should account for every attempt, even when they collapse into one row.'
		);

		$this->assertLessThan(
			$counted,
			count( $result ) + 1,
			'Counting rows should not be mistaken for counting attempts.'
		);
	}

	/**
	 * The grouping above is only discoverable if the schema says so.
	 *
	 * @covers ::register_abilities
	 */
	public function test_occasions_field_documents_the_grouping() {
		$this->ensure_abilities_registered();

		$schema = wp_get_ability( 'simple-history/get-recent-events' )->get_output_schema();

		$this->assertNotEmpty(
			$schema['items']['properties']['occasions']['description'] ?? '',
			'occasions is the only place an agent can learn that a row may stand for several events.'
		);
	}

	/**
	 * A permission callback must answer with a bool. Core reads a WP_Error as
	 * the ability author's mistake and routes it through _doing_it_wrong() so
	 * the reason cannot leak, which means every denied call would raise a
	 * notice — on a WP_DEBUG site, once per refused request.
	 *
	 * @covers ::check_events_permission
	 * @covers ::check_stats_permission
	 */
	public function test_permission_callbacks_answer_with_a_bool() {
		$this->require_abilities_api();

		$service = new Abilities_Service( Simple_History::get_instance() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertIsBool( $service->check_events_permission() );
		$this->assertIsBool( $service->check_stats_permission() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertIsBool( $service->check_events_permission() );
		$this->assertIsBool( $service->check_stats_permission() );
	}

	/**
	 * Calling an ability with no input at all is the first thing any client
	 * does, and it used to fail: WordPress hands the ability null, and null
	 * is not an object, so validation rejected it before the callback ran.
	 * The input schemas declare a default of {} so that call resolves.
	 *
	 * Every test here passes input explicitly, which is exactly why this
	 * shipped unnoticed — so assert the bare call directly.
	 *
	 * @covers ::register_abilities
	 */
	public function test_abilities_without_required_input_can_be_called_with_no_input() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'An event for the bare ability call to find' );

		foreach ( [ 'get-recent-events', 'get-failed-logins', 'get-stats-summary' ] as $slug ) {
			$result = wp_get_ability( 'simple-history/' . $slug )->execute();

			$this->assertNotWPError(
				$result,
				sprintf( 'Ability %s should be callable with no input, since every one of its properties is optional.', $slug )
			);
		}
	}

	/**
	 * The counterpart: an ability that does require input must still refuse a
	 * bare call, and say which property is missing rather than complaining
	 * that the input is not an object.
	 *
	 * @covers ::register_abilities
	 */
	public function test_abilities_with_required_input_name_the_missing_property() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$expected = [
			'get-event'         => 'id',
			'search-events'     => 'query',
			'get-user-activity' => 'user_id',
		];

		foreach ( $expected as $slug => $property ) {
			$result = wp_get_ability( 'simple-history/' . $slug )->execute();

			$this->assertWPError( $result, sprintf( 'Ability %s requires input and should refuse a bare call.', $slug ) );
			$this->assertStringContainsString(
				$property,
				$result->get_error_message(),
				sprintf( 'Ability %s should name the missing property, not just reject the input shape.', $slug )
			);
		}
	}

	/**
	 * The abilities are meant to be found by REST and MCP clients, which is
	 * the entire point of registering them. Discovery is opt-in and defaults
	 * to off, so a missing flag makes the feature invisible while every test
	 * that calls execute() directly still passes.
	 *
	 * @covers ::register_abilities
	 */
	public function test_abilities_declare_themselves_discoverable() {
		$this->ensure_abilities_registered();

		$names = [
			'simple-history/get-recent-events',
			'simple-history/get-event',
			'simple-history/search-events',
			'simple-history/get-user-activity',
			'simple-history/get-failed-logins',
			'simple-history/get-stats-summary',
		];

		foreach ( $names as $name ) {
			$meta = wp_get_ability( $name )->get_meta();

			$this->assertTrue( $meta['show_in_rest'] ?? false, sprintf( '%s should be exposed to REST clients.', $name ) );
			$this->assertTrue( $meta['public'] ?? false, sprintf( '%s should set the unified public flag.', $name ) );
			$this->assertTrue( $meta['mcp']['public'] ?? false, sprintf( '%s should be discoverable by MCP clients.', $name ) );
		}
	}

	/**
	 * @covers ::execute_get_recent_events
	 */
	public function test_get_recent_events_returns_presented_events() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'An event for the ability to find' );

		$result = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 5 ] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
		$this->assertArrayHasKey( 'message', $result[0] );
		$this->assertArrayNotHasKey( 'message_html', $result[0], 'Ability output must be presented, not raw REST data.' );
	}

	/**
	 * The whole permission model rests on this.
	 *
	 * Simple History's per-logger visibility filtering lives inside Log_Query,
	 * not in the permission callback — get_items_permissions_check() only
	 * asserts that someone is logged in. Delegating through rest_do_request()
	 * is what makes a subscriber see a filtered log instead of everything.
	 *
	 * @covers ::execute_get_recent_events
	 */
	public function test_subscriber_sees_fewer_events_than_administrator() {
		$this->ensure_abilities_registered();

		SimpleLogger()->info( 'Routine event' );
		SimpleLogger()->warning( 'Sensitive event' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$admin_events = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 100 ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$subscriber_events = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 100 ] );

		$this->assertIsArray( $admin_events );
		$this->assertNotEmpty( $admin_events, 'An administrator should see events.' );

		$subscriber_count = is_wp_error( $subscriber_events ) ? 0 : count( $subscriber_events );

		$this->assertLessThan(
			count( $admin_events ),
			$subscriber_count,
			'A subscriber must not see the same log an administrator sees.'
		);
	}

	/**
	 * The input schema's `maximum` on per_page makes WP_Ability::validate_input()
	 * refuse an out-of-range request before execute_callback ever runs. That is
	 * better for an agent than silently clamping and returning fewer rows than
	 * asked for: the agent is told the real limit instead of misreading a short
	 * result as the complete answer.
	 *
	 * clamp_per_page() remains in the service as defence-in-depth for direct PHP
	 * callers that invoke execute_get_recent_events() directly and so bypass
	 * schema validation entirely.
	 *
	 * @covers ::execute_get_recent_events
	 */
	public function test_per_page_above_maximum_is_rejected() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$result = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 500 ] );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Context is withheld until asked for, on every ability including this one.
	 *
	 * Fetching one event by id looks like the "drill in" act and used to
	 * return context by default, but context carries the initiator's email
	 * address and IP, and handing those to an agent that only asked what an
	 * event was is more than it needs. Opting in is one parameter.
	 *
	 * @covers ::execute_get_event
	 */
	public function test_get_event_omits_context_by_default() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'Event with context', [ 'custom_key' => 'custom_value' ] );

		$recent = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 1 ] );

		$this->assertNotEmpty( $recent );

		$event = wp_get_ability( 'simple-history/get-event' )->execute( [ 'id' => $recent[0]['id'] ] );

		$this->assertArrayNotHasKey( 'context', $event );
		$this->assertSame( $recent[0]['id'], $event['id'] );
	}

	/**
	 * The context an agent opts into is the same on every ability, and it does
	 * contain identity data — so the description has to say so.
	 *
	 * @covers ::register_abilities
	 */
	public function test_include_context_defaults_to_false_everywhere() {
		$this->ensure_abilities_registered();

		$names = [
			'simple-history/get-recent-events',
			'simple-history/get-event',
			'simple-history/search-events',
			'simple-history/get-user-activity',
			'simple-history/get-failed-logins',
		];

		foreach ( $names as $name ) {
			$property = wp_get_ability( $name )->get_input_schema()['properties']['include_context'] ?? null;

			$this->assertNotNull( $property, sprintf( '%s should offer include_context.', $name ) );
			$this->assertFalse( $property['default'], sprintf( '%s should withhold context until asked.', $name ) );
			$this->assertStringContainsString(
				'email',
				$property['description'],
				sprintf( '%s should say that context carries identity data.', $name )
			);
		}
	}

	/**
	 * @covers ::execute_get_event
	 */
	public function test_get_event_can_omit_context() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'Another event' );

		$recent = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 1 ] );

		$this->assertNotEmpty( $recent );

		$event = wp_get_ability( 'simple-history/get-event' )->execute(
			[
				'id'              => $recent[0]['id'],
				'include_context' => false,
			]
		);

		$this->assertArrayNotHasKey( 'context', $event );
	}

	/**
	 * @covers ::execute_get_event
	 */
	public function test_get_event_returns_error_for_unknown_id() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$result = wp_get_ability( 'simple-history/get-event' )->execute( [ 'id' => 99999999 ] );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	/**
	 * An ability without `show_in_rest` registers fine in PHP but is
	 * unreachable by any REST or MCP client, which is the only way an agent
	 * ever reaches an ability — so this is the test that would have caught
	 * that bug.
	 *
	 * @covers ::get_read_only_meta
	 */
	public function test_abilities_are_exposed_to_rest() {
		$this->ensure_abilities_registered();

		foreach ( wp_get_abilities() as $ability ) {
			if ( strpos( $ability->get_name(), 'simple-history/' ) !== 0 ) {
				continue;
			}

			$this->assertTrue(
				$ability->get_meta_item( 'show_in_rest' ),
				sprintf( 'Ability "%s" must set show_in_rest, or it is invisible to REST and MCP clients.', $ability->get_name() )
			);
		}
	}

	/**
	 * Every Simple History ability must declare itself read-only and
	 * non-destructive in machine-readable form, not only in prose — an audit
	 * log an agent can alter or purge is worse than no audit log.
	 *
	 * @covers ::get_read_only_meta
	 */
	public function test_abilities_declare_read_only_annotations() {
		$this->ensure_abilities_registered();

		foreach ( wp_get_abilities() as $ability ) {
			if ( strpos( $ability->get_name(), 'simple-history/' ) !== 0 ) {
				continue;
			}

			$annotations = $ability->get_meta_item( 'annotations' );

			$this->assertTrue(
				$annotations['readonly'] ?? false,
				sprintf( 'Ability "%s" must declare annotations.readonly = true.', $ability->get_name() )
			);

			$this->assertFalse(
				$annotations['destructive'] ?? true,
				sprintf( 'Ability "%s" must declare annotations.destructive = false.', $ability->get_name() )
			);
		}
	}

	/**
	 * All six abilities must actually be reachable by name, not merely
	 * registered somewhere under the simple-history/ prefix — a typo in a
	 * wp_register_ability() call would otherwise pass unnoticed.
	 *
	 * @covers ::register_abilities
	 */
	public function test_all_six_abilities_are_registered_by_name() {
		$this->ensure_abilities_registered();

		foreach (
			[
				'simple-history/get-recent-events',
				'simple-history/get-event',
				'simple-history/search-events',
				'simple-history/get-user-activity',
				'simple-history/get-failed-logins',
				'simple-history/get-stats-summary',
			] as $name
		) {
			$this->assertNotNull(
				wp_get_ability( $name ),
				sprintf( 'Ability "%s" should be registered.', $name )
			);
		}
	}

	/**
	 * A convenience preset over get-recent-events for when an agent has a
	 * search term rather than a logger or severity in mind.
	 *
	 * @covers ::execute_search_events
	 */
	public function test_search_events_matches_message_text() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'A wildly distinctive phrase for search to find' );
		SimpleLogger()->info( 'An unrelated event' );

		$result = wp_get_ability( 'simple-history/search-events' )->execute(
			[ 'query' => 'wildly distinctive phrase' ]
		);

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		foreach ( $result as $event ) {
			$this->assertStringContainsString( 'wildly distinctive phrase', $event['message'] );
		}
	}

	/**
	 * The input schema declares `query` as required, which stops an agent
	 * calling through wp_get_ability()->execute() from ever reaching this
	 * with a missing query. A direct PHP caller invoking the execute
	 * method itself bypasses that validation entirely: without a guard, a
	 * null/empty search silently becomes no search parameter at all, and the
	 * ability returns the entire recent log instead of erroring — the
	 * opposite of what "search for nothing" should do.
	 *
	 * @covers ::execute_search_events
	 */
	public function test_search_events_requires_a_query() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertInstanceOf( WP_Error::class, $service->execute_search_events( [] ) );
		$this->assertInstanceOf( WP_Error::class, $service->execute_search_events( [ 'query' => '' ] ) );
	}

	/**
	 * Uses the `users` REST parameter, not `user` — the two are different
	 * parameters, and using the wrong one would silently return an unfiltered
	 * log instead of one user's activity.
	 *
	 * @covers ::execute_get_user_activity
	 */
	public function test_get_user_activity_is_scoped_to_one_user() {
		$this->ensure_abilities_registered();

		$user_a = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user_b = self::factory()->user->create( [ 'role' => 'administrator' ] );

		wp_set_current_user( $user_a );
		SimpleLogger()->info( 'Event by user A' );

		wp_set_current_user( $user_b );
		SimpleLogger()->info( 'Event by user B' );

		wp_set_current_user( $user_a );

		$result = wp_get_ability( 'simple-history/get-user-activity' )->execute( [ 'user_id' => $user_a ] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );

		foreach ( $result as $event ) {
			$this->assertSame( $user_a, $event['user']['id'] );
		}
	}

	/**
	 * A direct PHP caller bypassing schema validation and omitting user_id
	 * already failed safe here — (int) null becomes users => [0], matching
	 * no real user — but a clear error is better than a result an agent
	 * could misread as "this user did nothing".
	 *
	 * @covers ::execute_get_user_activity
	 */
	public function test_get_user_activity_requires_a_user_id() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertInstanceOf( WP_Error::class, $service->execute_get_user_activity( [] ) );
	}

	/**
	 * The presenter deliberately drops the raw `_message_key` context (see
	 * Abilities_Event_Presenter::present()), so the message key itself cannot
	 * be asserted on here. The logger slug is the closest available signal
	 * that a returned event is genuinely a failed login rather than some
	 * other SimpleUserLogger event, so this asserts that instead.
	 *
	 * Also logs an application-password failure (see
	 * User_Logger::on_application_password_failed_authentication(), which
	 * fires this via the same warning_message() path) alongside the two
	 * ordinary login-form failures. A site under attack through application
	 * passwords or the REST API must still show up here — this is the test
	 * that would have caught filtering on only the two login-form keys.
	 *
	 * @covers ::execute_get_failed_logins
	 */
	public function test_get_failed_logins_returns_only_login_failures() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$user_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
		$user_logger->warning_message( 'user_login_failed', [ 'login' => 'attacker' ] );
		$user_logger->warning_message( 'user_unknown_login_failed', [ 'failed_username' => 'nonexistent' ] );
		$user_logger->warning_message( 'user_application_password_login_failed', [ 'login' => 'attacker' ] );

		SimpleLogger()->info( 'A routine, unrelated event' );

		$result = wp_get_ability( 'simple-history/get-failed-logins' )->execute( [] );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result, 'All three failed-login events should be returned, including the application-password one.' );

		foreach ( $result as $event ) {
			$this->assertSame( 'SimpleUserLogger', $event['logger'] );
		}
	}

	/**
	 * search-events, get-user-activity and get-failed-logins share
	 * get_event_list_schema(), whose `context` property claims it is "only
	 * present when include_context was requested" — but until this was
	 * fixed, none of the three accepted that input, so the field could never
	 * actually come back and the promise was false for all three. This pins
	 * get-failed-logins specifically, since seeing the full context of a
	 * failed login (the attempted username, IP, user agent) is exactly what
	 * makes the ability useful for its stated brute-force/credential-stuffing
	 * purpose.
	 *
	 * @covers ::execute_get_failed_logins
	 */
	public function test_get_failed_logins_can_include_context() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$user_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
		$user_logger->warning_message( 'user_login_failed', [ 'login' => 'attacker' ] );

		$without = wp_get_ability( 'simple-history/get-failed-logins' )->execute( [] );

		$this->assertNotEmpty( $without );
		$this->assertArrayNotHasKey( 'context', $without[0], 'Context should stay excluded by default.' );

		$with = wp_get_ability( 'simple-history/get-failed-logins' )->execute( [ 'include_context' => true ] );

		$this->assertNotEmpty( $with );
		$this->assertArrayHasKey( 'context', $with[0], 'include_context should now be honoured by get-failed-logins.' );
	}

	/**
	 * @covers ::execute_get_stats_summary
	 */
	public function test_get_stats_summary_returns_data_for_administrator() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'An event for the stats summary to count' );

		$result = wp_get_ability( 'simple-history/get-stats-summary' )->execute( [] );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * Pins the events/stats permission asymmetry at the ability layer, not
	 * merely at the service-method layer that
	 * test_stats_permission_is_refused_for_editor() already covers: an editor
	 * may call get-recent-events but must be refused get-stats-summary via the
	 * ability's own check_permissions(), which is what an agent or MCP client
	 * actually calls before executing.
	 *
	 * @covers ::execute_get_stats_summary
	 * @covers ::check_stats_permission
	 */
	public function test_stats_summary_ability_refuses_editor() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$ability = wp_get_ability( 'simple-history/get-stats-summary' );

		$this->assertFalse( $ability->check_permissions( [] ) );
	}

	/**
	 * A schema an agent cannot rely on is worse than none: every ability must
	 * declare a usable label, description and a typed output_schema, not a
	 * bare untyped object.
	 *
	 * @covers ::register_abilities
	 */
	public function test_all_abilities_declare_label_description_and_typed_output_schema() {
		$this->ensure_abilities_registered();

		foreach ( wp_get_abilities() as $ability ) {
			if ( strpos( $ability->get_name(), 'simple-history/' ) !== 0 ) {
				continue;
			}

			$this->assertNotEmpty(
				$ability->get_label(),
				sprintf( 'Ability "%s" must have a non-empty label.', $ability->get_name() )
			);

			$this->assertNotEmpty(
				$ability->get_description(),
				sprintf( 'Ability "%s" must have a non-empty description.', $ability->get_name() )
			);

			$output_schema = $ability->get_output_schema();

			$this->assertIsArray(
				$output_schema,
				sprintf( 'Ability "%s" must declare an output_schema.', $ability->get_name() )
			);

			$this->assertArrayHasKey(
				'type',
				$output_schema,
				sprintf( 'Ability "%s" output_schema must declare a type.', $ability->get_name() )
			);
		}
	}

	/**
	 * Simple History logs attacker-controlled strings by design — a
	 * failed-login username is whatever was typed at the login form — so every
	 * ability that returns events must tell an agent that returned content is
	 * untrusted data, not instructions. This stops that warning being quietly
	 * dropped in a future edit. get-stats-summary is exempt: it returns only
	 * aggregate counts, never event content.
	 *
	 * @covers ::register_abilities
	 */
	public function test_every_event_returning_ability_warns_content_is_untrusted() {
		$this->ensure_abilities_registered();

		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();

			if ( strpos( $name, 'simple-history/' ) !== 0 ) {
				continue;
			}

			if ( 'simple-history/get-stats-summary' === $name ) {
				continue;
			}

			$this->assertStringContainsString(
				'untrusted',
				$ability->get_description(),
				sprintf( 'Ability "%s" must warn that returned content is untrusted.', $name )
			);
		}
	}
}
