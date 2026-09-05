<?php

require_once __DIR__ . '/_redirection_test_trait.php';

use Simple_History\Loggers\Plugin_Redirection_Logger;

/**
 * Tests for Plugin_Redirection_Logger::get_changed_settings() — the pure
 * helper that diffs a Redirection settings-save request's params against the
 * previous option values from \Red_Options::get(), used to build the
 * before/after details table for issue 312 ("Updated redirection options"
 * event shows which settings changed).
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginRedirectionLoggerSettingsDiffTest
 */
class PluginRedirectionLoggerSettingsDiffTest extends \Codeception\TestCase\WPTestCase {
	use RedirectionTestTrait;
	/**
	 * Reset the current user after every test that changes it, so a
	 * capability-filtering test never leaks into an unrelated one.
	 */
	function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::require_redirection_capabilities();
	}

	/**
	 * Redirection ships in the wpunit fixture (tests/wpunit.suite.yml loads
	 * "redirection/redirection.php" via WPLoader, even though it is not
	 * "activated"), so \Red_Options::filter_by_capability() is the real
	 * Redirection method here, not a stand-in — this exercises the actual
	 * capability split, not an approximation of it.
	 */
	function test_filter_params_by_capability_passes_through_for_a_user_with_full_access() {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$params = [
			'monitor_post' => 1,
			'https'        => true,
		];

		$filtered = Plugin_Redirection_Logger::filter_params_by_capability( $params );

		$this->assertSame( $params, $filtered );
	}

	/**
	 * A user without `manage_options` (Redirection's default capability for
	 * both the site and option buckets) fails \Red_Options::filter_by_capability()'s
	 * `current_user_can()` check for both buckets, so every key is stripped —
	 * mirroring what Settings::route_save_settings() would actually persist
	 * for this user. Diffing the raw, unfiltered params would otherwise record
	 * a "change" Redirection never saved.
	 */
	function test_filter_params_by_capability_strips_settings_the_user_cannot_manage() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$params = [
			'monitor_post' => 1,
			'https'        => true,
		];

		$filtered = Plugin_Redirection_Logger::filter_params_by_capability( $params );

		$this->assertSame( [], $filtered );
	}

	function test_unchanged_value_is_skipped() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'ip_logging' => 0 ],
			[ 'ip_logging' => 0 ]
		);

		$this->assertSame( [], $changed );
	}

	function test_int_and_bool_equivalents_are_not_a_change() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'track_hits' => true ],
			[ 'track_hits' => 1 ]
		);

		$this->assertSame( [], $changed );
	}

	function test_array_order_is_ignored() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'monitor_types' => [ 'page', 'post' ] ],
			[ 'monitor_types' => [ 'post', 'page' ] ]
		);

		$this->assertSame( [], $changed );
	}

	function test_boolean_settings_display_as_on_and_off() {
		$this->assertSame( 'On', Plugin_Redirection_Logger::format_value_for_display( 'monitor_post', '1' ) );
		$this->assertSame( 'Off', Plugin_Redirection_Logger::format_value_for_display( 'monitor_post', '0' ) );
		$this->assertSame( 'Off', Plugin_Redirection_Logger::format_value_for_display( 'https', '' ) );
		$this->assertSame( 'On', Plugin_Redirection_Logger::format_value_for_display( 'track_hits', 'true' ) );
	}

	function test_ip_logging_levels_display_as_words() {
		$this->assertSame( 'Off', Plugin_Redirection_Logger::format_value_for_display( 'ip_logging', '0' ) );
		$this->assertSame( 'Full IP', Plugin_Redirection_Logger::format_value_for_display( 'ip_logging', '1' ) );
		$this->assertSame( 'Anonymized IP', Plugin_Redirection_Logger::format_value_for_display( 'ip_logging', '2' ) );
		$this->assertSame( '7', Plugin_Redirection_Logger::format_value_for_display( 'ip_logging', '7' ) );
	}

	function test_other_settings_display_unchanged() {
		$this->assertSame( 'page, post', Plugin_Redirection_Logger::format_value_for_display( 'monitor_types', 'page, post' ) );
		$this->assertSame( '', Plugin_Redirection_Logger::format_value_for_display( 'monitor_types', '' ) );
		$this->assertNull( Plugin_Redirection_Logger::format_value_for_display( 'monitor_types', null ) );
	}

	function test_changed_scalar_value_is_recorded() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'monitor_post' => 1 ],
			[ 'monitor_post' => 0 ]
		);

		$this->assertArrayHasKey( 'monitor_post', $changed );
		$this->assertSame( '0', $changed['monitor_post']['prev'] );
		$this->assertSame( '1', $changed['monitor_post']['new'] );
	}

	function test_changed_array_value_is_comma_joined() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'monitor_types' => [ 'post', 'page' ] ],
			[ 'monitor_types' => [] ]
		);

		$this->assertArrayHasKey( 'monitor_types', $changed );
		$this->assertSame( '', $changed['monitor_types']['prev'] );
		$this->assertSame( 'page, post', $changed['monitor_types']['new'] );
	}

	function test_token_is_redacted_but_change_is_recorded() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'token' => 'new-secret-token' ],
			[ 'token' => 'old-secret-token' ]
		);

		$this->assertArrayHasKey( 'token', $changed );
		$this->assertSame( '[redacted]', $changed['token']['prev'] );
		$this->assertSame( '[redacted]', $changed['token']['new'] );
	}

	function test_nested_array_is_counted() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[
				'modules' => [
					[ 'id' => 1 ],
					[ 'id' => 2 ],
					[ 'id' => 3 ],
				],
			],
			[ 'modules' => [] ]
		);

		$this->assertArrayHasKey( 'modules', $changed );
		$this->assertSame( '3 items', $changed['modules']['new'] );
		$this->assertSame( '0 items', $changed['modules']['prev'] );
	}

	function test_unknown_key_is_ignored() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'not_a_real_setting' => 'value' ],
			[]
		);

		$this->assertSame( [], $changed );
	}

	function test_location_is_handled() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'location' => '/var/www/.htaccess' ],
			[]
		);

		$this->assertArrayHasKey( 'location', $changed );
		$this->assertSame( '', $changed['location']['prev'] );
		$this->assertSame( '/var/www/.htaccess', $changed['location']['new'] );
	}

	function test_long_value_is_capped_at_200_characters() {
		$long_value = str_repeat( 'a', 500 );

		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[ 'preferred_domain' => $long_value ],
			[ 'preferred_domain' => '' ]
		);

		$this->assertArrayHasKey( 'preferred_domain', $changed );
		$this->assertSame( 200, strlen( $changed['preferred_domain']['new'] ) );
	}

	function test_multiple_changed_and_unchanged_settings() {
		$changed = Plugin_Redirection_Logger::get_changed_settings(
			[
				'monitor_post'  => 1,
				'ip_logging'    => 0,
				'monitor_types' => [ 'post' ],
			],
			[
				'monitor_post'  => 0,
				'ip_logging'    => 0,
				'monitor_types' => [ 'post' ],
			]
		);

		$this->assertSame( [ 'monitor_post' ], array_keys( $changed ) );
	}
}
