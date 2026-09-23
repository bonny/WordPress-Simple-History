<?php

require_once __DIR__ . '/_redirection_test_trait.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Plugin_Redirection_Logger;

/**
 * Tests for Plugin_Redirection_Logger::get_changed_settings() — the pure
 * helper that diffs the Redirection option values stored after a settings save
 * against the ones stored before it, used to build the before/after details
 * table for issue 312 ("Updated redirection options" event shows which
 * settings changed).
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginRedirectionLoggerSettingsDiffTest
 */
class PluginRedirectionLoggerSettingsDiffTest extends \Codeception\TestCase\WPTestCase {
	use RedirectionTestTrait;

	public function setUp(): void {
		parent::setUp();

		$this->skip_without_redirection();
	}

	/**
	 * Reset the current user after every test that changes it, so one test
	 * never leaks into an unrelated one.
	 */
	function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::require_redirection_capabilities();
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
		$this->assertSame( 'On', Plugin_Redirection_Logger::format_value_for_display( 'log_external', '1' ) );
		$this->assertSame( 'Off', Plugin_Redirection_Logger::format_value_for_display( 'log_external', '0' ) );
		$this->assertSame( 'Off', Plugin_Redirection_Logger::format_value_for_display( 'https', '' ) );
		$this->assertSame( 'On', Plugin_Redirection_Logger::format_value_for_display( 'track_hits', 'true' ) );
		$this->assertSame( 'On', Plugin_Redirection_Logger::format_value_for_display( 'flag_case', '1' ) );
		$this->assertSame( 'Off', Plugin_Redirection_Logger::format_value_for_display( 'flag_regex', '' ) );
	}

	/**
	 * `monitor_post` looks like a boolean — Redirection's default is 0 — but it
	 * holds the id of the group monitored post redirects are created in
	 * (`models/options.php`, `red_get_post_types()`/group dropdown on the
	 * options screen). Showing "On" for group 3 would be wrong, so it must pass
	 * through as the raw id.
	 */
	function test_monitor_post_displays_as_the_raw_group_id() {
		$this->assertSame( '3', Plugin_Redirection_Logger::format_value_for_display( 'monitor_post', '3' ) );
		$this->assertSame( '0', Plugin_Redirection_Logger::format_value_for_display( 'monitor_post', '0' ) );
	}

	/**
	 * `flag_query` is the one `flag_*` setting that is not a boolean: it is one
	 * of Red_Source_Flags' four query types.
	 */
	function test_flag_query_displays_as_words() {
		$this->assertSame( 'Exact match in any order', Plugin_Redirection_Logger::format_value_for_display( 'flag_query', 'exact' ) );
		$this->assertSame( 'Exact match', Plugin_Redirection_Logger::format_value_for_display( 'flag_query', 'exactorder' ) );
		$this->assertSame( 'Ignore all query parameters', Plugin_Redirection_Logger::format_value_for_display( 'flag_query', 'ignore' ) );
		$this->assertSame( 'Ignore and pass all query parameters', Plugin_Redirection_Logger::format_value_for_display( 'flag_query', 'pass' ) );
		$this->assertSame( 'something-else', Plugin_Redirection_Logger::format_value_for_display( 'flag_query', 'something-else' ) );
	}

	/**
	 * Every key Redirection 5.10.0 stores must be covered, or a change to it
	 * would be silently dropped from the event. The list mirrors
	 * `\Red_Options::get_default_options()` plus the four `flag_*` defaults it
	 * merges in, minus `last_group_id` (bumped when a group is added, not a
	 * settings change), plus the derived `location`.
	 */
	function test_every_stored_redirection_option_key_is_covered() {
		if ( ! class_exists( '\Red_Options' ) ) {
			$this->markTestSkipped( 'Redirection is not loaded.' );
		}

		$stored_keys = array_keys( \Red_Options::get_default_options() );

		$uncovered = array_diff( $stored_keys, Plugin_Redirection_Logger::OPTION_KEYS, [ 'last_group_id' ] );

		$this->assertSame( [], array_values( $uncovered ) );
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

	/**
	 * The details container drops items whose new and prev values are the same,
	 * so storing "[redacted]" on both sides of a token change made the row
	 * disappear while the "Updated N settings" count still counted it. The
	 * logger stores only a "(changed)" new value instead — the same convention
	 * class-simple-history-logger.php uses for its own redacted settings.
	 */
	function test_redacted_token_row_survives_the_details_container() {
		$row          = new stdClass();
		$row->logger  = 'Plugin_Redirection';
		$row->context = [
			'_message_key'                     => 'redirection_options_saved_count',
			'settings_changed_count'           => 1,
			'redirection_option_token_new'     => '(changed)',
		];

		$details = Simple_History::get_instance()->get_log_row_details_output( $row );

		$labels = [];
		$values = [];

		foreach ( $details->groups as $group ) {
			foreach ( $group->items as $item ) {
				$labels[] = $item->name;
				$values[] = $item->new_value;
			}
		}

		$this->assertContains( 'REST API token', $labels, 'The redacted token row must still render' );
		$this->assertContains( '(changed)', $values );
		$this->assertStringContainsString( 'REST API token', (string) $details );
		$this->assertStringNotContainsString( '[redacted]', (string) $details );
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
