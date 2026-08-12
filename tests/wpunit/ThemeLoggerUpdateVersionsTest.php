<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Theme_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Tests that Theme_Logger records the previous and new version numbers when
 * a theme is updated (local issue #245).
 *
 * Covers:
 * - save_versions_before_update() snapshots installed theme versions to an
 *   option and passes its first argument through unchanged (it runs on the
 *   `upgrader_pre_install` filter, so breaking the return value would break
 *   theme/plugin upgrades).
 * - on_upgrader_process_complete_theme_update() reads that snapshot and adds
 *   `theme_prev_version` to the logged context, for both the single-theme
 *   and bulk-update code paths.
 * - When no snapshot is available the event still logs, just without
 *   `theme_prev_version`.
 * - get_log_row_plain_text_output() renders both cases without leaking
 *   unresolved `{placeholder}` tokens.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit ThemeLoggerUpdateVersionsTest
 */
class ThemeLoggerUpdateVersionsTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var Theme_Logger */
	private $logger;

	/** @var string Option name the logger stores the pre-update version snapshot in. */
	private $option_name = 'SimpleThemeLogger_theme_info_before_update';

	public function setUp(): void {
		parent::setUp();

		// Theme_Upgrader lives in wp-admin and is not loaded on a normal front-end
		// bootstrap, so pull it in before any test tries to instantiate it.
		if ( ! class_exists( '\Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimpleThemeLogger' );

		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );
	}

	public function tearDown(): void {
		// Don't leak the version snapshot between tests.
		delete_option( $this->option_name );

		parent::tearDown();
	}

	/**
	 * Picks a theme slug that actually exists in the test install, since the
	 * bundled default theme differs between WordPress versions.
	 *
	 * @return string Theme stylesheet slug.
	 */
	private function get_installed_theme_slug(): string {
		$themes = wp_get_themes();

		$this->assertNotEmpty( $themes, 'Test install must have at least one theme for this test to be meaningful.' );

		return array_key_first( $themes );
	}

	/**
	 * Converts the list-of-rows shape returned by get_latest_context() into a
	 * simple key => value map, which is easier to assert against.
	 *
	 * @param array<int,array<string,mixed>> $context Context rows.
	 * @return array<string,mixed>
	 */
	private function context_to_assoc( array $context ): array {
		$assoc = [];

		foreach ( $context as $row ) {
			$assoc[ $row['key'] ] = $row['value'];
		}

		return $assoc;
	}

	/**
	 * save_versions_before_update() runs on the `upgrader_pre_install` filter.
	 * If it stops returning its first argument unchanged, it breaks every
	 * plugin and theme upgrade that fires that filter — so the pass-through
	 * behavior is a hard requirement, not an implementation detail.
	 */
	public function test_save_versions_before_update_passes_first_argument_through_unchanged() {
		delete_option( $this->option_name );

		$result_null = $this->logger->save_versions_before_update( null, [] );
		$this->assertNull( $result_null, 'Filter must return its first argument unchanged when it is null.' );

		$result_true = $this->logger->save_versions_before_update( true, [] );
		$this->assertTrue( $result_true, 'Filter must return its first argument unchanged when it is true.' );
	}

	/**
	 * save_versions_before_update() must store a slug => version map of the
	 * currently installed themes, so on_upgrader_process_complete_theme_update()
	 * has something to diff against later.
	 */
	public function test_save_versions_before_update_stores_installed_theme_versions() {
		delete_option( $this->option_name );

		$this->logger->save_versions_before_update( null, [] );

		$stored = json_decode( get_option( $this->option_name ), true );

		$this->assertIsArray( $stored, 'Option must decode to an array.' );
		$this->assertNotEmpty( $stored, 'Option must contain at least one theme.' );

		$theme_slug = $this->get_installed_theme_slug();

		$this->assertArrayHasKey( $theme_slug, $stored, 'Stored map must contain the installed theme slug.' );
		$this->assertNotEmpty( $stored[ $theme_slug ], 'Stored version for the theme must not be empty.' );
	}

	/**
	 * Single-theme update path (themes.php, one theme updated at a time):
	 * when a version snapshot exists, the logged event must contain both the
	 * previous version (from the snapshot) and the new version (read live
	 * from the theme after the update).
	 */
	public function test_single_theme_update_records_previous_version() {
		$theme_slug = $this->get_installed_theme_slug();
		$real_version = wp_get_theme( $theme_slug )->get( 'Version' );

		update_option( $this->option_name, wp_json_encode( [ $theme_slug => '0.0.1' ] ), false );

		$this->logger->on_upgrader_process_complete_theme_update(
			new \Theme_Upgrader(),
			[
				'type'   => 'theme',
				'action' => 'update',
				'theme'  => $theme_slug,
			]
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleThemeLogger', $row['logger'] );

		$context = $this->context_to_assoc( get_latest_context() );

		$this->assertSame( 'theme_updated', $context['_message_key'] );
		$this->assertSame( '0.0.1', $context['theme_prev_version'] );
		$this->assertSame( $real_version, $context['theme_version'] );
	}

	/**
	 * Bulk update path (Dashboard > Updates, several themes updated at once):
	 * same expectations as the single-theme path, but $arr_data shapes the
	 * theme list differently (`themes` array + `bulk` flag instead of a
	 * single `theme` key), so it is a separate branch worth covering.
	 */
	public function test_bulk_theme_update_records_previous_version() {
		$theme_slug = $this->get_installed_theme_slug();
		$real_version = wp_get_theme( $theme_slug )->get( 'Version' );

		update_option( $this->option_name, wp_json_encode( [ $theme_slug => '0.0.1' ] ), false );

		$this->logger->on_upgrader_process_complete_theme_update(
			new \Theme_Upgrader(),
			[
				'type'   => 'theme',
				'action' => 'update',
				'bulk'   => 1,
				'themes' => [ $theme_slug ],
			]
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleThemeLogger', $row['logger'] );

		$context = $this->context_to_assoc( get_latest_context() );

		$this->assertSame( 'theme_updated', $context['_message_key'] );
		$this->assertSame( '0.0.1', $context['theme_prev_version'] );
		$this->assertSame( $real_version, $context['theme_version'] );
	}

	/**
	 * When no version snapshot is stored for the theme (e.g. events logged
	 * before this feature existed, or a theme updater that never fires
	 * `upgrader_pre_install`), the event must still log the new version, but
	 * must not add a `theme_prev_version` key to the context at all.
	 */
	public function test_theme_update_without_stored_snapshot_omits_previous_version() {
		$theme_slug = $this->get_installed_theme_slug();
		$real_version = wp_get_theme( $theme_slug )->get( 'Version' );

		delete_option( $this->option_name );

		$this->logger->on_upgrader_process_complete_theme_update(
			new \Theme_Upgrader(),
			[
				'type'   => 'theme',
				'action' => 'update',
				'theme'  => $theme_slug,
			]
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleThemeLogger', $row['logger'] );

		$context = $this->context_to_assoc( get_latest_context() );

		$this->assertSame( 'theme_updated', $context['_message_key'] );
		$this->assertSame( $real_version, $context['theme_version'] );
		$this->assertArrayNotHasKey( 'theme_prev_version', $context, 'Context must not contain theme_prev_version when no snapshot was stored.' );
	}

	/**
	 * Regression guard: when theme_prev_version is present, rendering must
	 * not leak the literal `{theme_prev_version}` (or any other) placeholder
	 * into the output, and both version numbers must be readable.
	 */
	public function test_render_with_previous_version_contains_both_versions_and_no_placeholders() {
		$row = (object) [
			'id'        => 1,
			'logger'    => 'SimpleThemeLogger',
			'level'     => 'info',
			'date'      => current_time( 'mysql' ),
			'initiator' => 'wp_user',
			'message'   => 'Updated theme "{theme_name}" to version {theme_version} from {theme_prev_version}',
			'context'   => [
				'_message_key'       => 'theme_updated',
				'theme_name'         => 'Twenty Test',
				'theme_version'      => '2.0.0',
				'theme_prev_version' => '1.0.0',
			],
		];

		$output = $this->sh->get_log_row_plain_text_output( $row );

		$this->assertStringNotContainsString( '{theme_prev_version}', $output, 'Unresolved placeholder must not leak into output.' );
		$this->assertStringNotContainsString( '{theme_version}', $output, 'Unresolved placeholder must not leak into output.' );
		$this->assertStringNotContainsString( '{theme_name}', $output, 'Unresolved placeholder must not leak into output.' );

		$this->assertStringContainsString( '2.0.0', $output, 'New version must be present in output.' );
		$this->assertStringContainsString( '1.0.0', $output, 'Previous version must be present in output.' );
	}

	/**
	 * Regression guard: when theme_prev_version is absent, rendering must
	 * not leak the literal `{theme_prev_version}` placeholder (this is the
	 * exact bug this feature's fallback branch in
	 * get_log_row_plain_text_output() guards against), and the output must
	 * not claim a "from" version that isn't known.
	 */
	public function test_render_without_previous_version_contains_no_placeholders_and_no_from() {
		$row = (object) [
			'id'        => 1,
			'logger'    => 'SimpleThemeLogger',
			'level'     => 'info',
			'date'      => current_time( 'mysql' ),
			'initiator' => 'wp_user',
			'message'   => 'Updated theme "{theme_name}" to version {theme_version} from {theme_prev_version}',
			'context'   => [
				'_message_key'  => 'theme_updated',
				'theme_name'    => 'Twenty Test',
				'theme_version' => '2.0.0',
			],
		];

		$output = $this->sh->get_log_row_plain_text_output( $row );

		$this->assertStringNotContainsString( '{theme_prev_version}', $output, 'Unresolved placeholder must not leak into output.' );
		$this->assertStringNotContainsString( '{theme_version}', $output, 'Unresolved placeholder must not leak into output.' );
		$this->assertStringNotContainsString( '{theme_name}', $output, 'Unresolved placeholder must not leak into output.' );

		$this->assertStringContainsString( '2.0.0', $output, 'New version must be present in output.' );
		$this->assertStringNotContainsString( 'from', $output, 'Output must not claim an unknown previous version.' );
	}
}
