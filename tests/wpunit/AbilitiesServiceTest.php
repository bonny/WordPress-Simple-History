<?php

use Simple_History\Simple_History;
use Simple_History\Services\Abilities_Service;

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
	 * Skip a test when the Abilities API is not present.
	 */
	private function require_abilities_api() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API requires WordPress 6.9+. Run with WORDPRESS_VERSION=6.9.' );
		}
	}

	/**
	 * Register abilities once per process.
	 *
	 * The plugin registers these during bootstrap on WordPress 6.9+, and the
	 * registry outlives individual tests, so registering again would trip
	 * _doing_it_wrong() and fail the test for the wrong reason.
	 */
	private function ensure_abilities_registered() {
		$this->require_abilities_api();

		if ( wp_get_ability( 'simple-history/get-recent-events' ) ) {
			return;
		}

		( new Abilities_Service( Simple_History::get_instance() ) )->register_abilities();
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
		remove_all_actions( 'wp_abilities_api_init' );

		$service = new Abilities_Service( Simple_History::get_instance() );
		$service->loaded();

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
	 * Reading the log requires being logged in.
	 *
	 * @covers ::check_events_permission
	 */
	public function test_events_permission_is_refused_when_logged_out() {
		wp_set_current_user( 0 );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertInstanceOf( WP_Error::class, $service->check_events_permission() );
	}

	/**
	 * A subscriber may read the log. What they actually see is filtered inside
	 * Log_Query, not here — this helper only mirrors the REST route's check.
	 *
	 * @covers ::check_events_permission
	 */
	public function test_events_permission_is_granted_to_logged_in_user() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertTrue( $service->check_events_permission() );
	}

	/**
	 * Stats are stricter than events: the stats controller requires
	 * manage_options where the events controller only requires being logged in.
	 * That asymmetry is deliberate and must survive.
	 *
	 * @covers ::check_stats_permission
	 */
	public function test_stats_permission_is_refused_for_editor() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$service = new Abilities_Service( Simple_History::get_instance() );

		$this->assertInstanceOf( WP_Error::class, $service->check_stats_permission() );
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
	 * An agent asking for a thousand events would spend its whole context on
	 * one answer, so the ceiling is enforced server-side.
	 *
	 * @covers ::execute_get_recent_events
	 */
	public function test_per_page_is_clamped_to_one_hundred() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		for ( $i = 0; $i < 105; $i++ ) {
			SimpleLogger()->info( 'Event number ' . $i );
		}

		$result = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 500 ] );

		$this->assertLessThanOrEqual( 100, count( $result ) );
	}

	/**
	 * Fetching one event by id is already the "drill in" act, so context comes
	 * back by default here where list abilities leave it out.
	 *
	 * @covers ::execute_get_event
	 */
	public function test_get_event_includes_context_by_default() {
		$this->ensure_abilities_registered();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		SimpleLogger()->info( 'Event with context', [ 'custom_key' => 'custom_value' ] );

		$recent = wp_get_ability( 'simple-history/get-recent-events' )->execute( [ 'per_page' => 1 ] );

		$this->assertNotEmpty( $recent );

		$event = wp_get_ability( 'simple-history/get-event' )->execute( [ 'id' => $recent[0]['id'] ] );

		$this->assertArrayHasKey( 'context', $event );
		$this->assertSame( $recent[0]['id'], $event['id'] );
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
}
