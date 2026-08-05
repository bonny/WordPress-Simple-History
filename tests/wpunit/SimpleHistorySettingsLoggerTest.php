<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Simple_History_Logger;
use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Group;

/**
 * Test Simple History settings change logging.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest
 */
class SimpleHistorySettingsLoggerTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History_Logger */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( 'SimpleHistoryLogger' );

		$this->assertNotNull( $this->logger, 'SimpleHistoryLogger should be instantiated' );

		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );
	}

	/**
	 * Register an option as tracked for the remainder of the test.
	 *
	 * No remove_filter is needed: the WP test case restores all hooks in tear_down().
	 *
	 * @param string $option Option name.
	 * @param string $label  Human label.
	 */
	private function track_option( string $option, string $label ): void {
		add_filter(
			'simple_history/settings/tracked_options',
			static function ( $tracked ) use ( $option, $label ) {
				$tracked[ $option ] = $label;
				return $tracked;
			}
		);
	}

	/**
	 * Register an option as redacted for the remainder of the test.
	 *
	 * @param string $option Option name.
	 */
	private function redact_option( string $option ): void {
		add_filter(
			'simple_history/settings/redacted_options',
			static function ( $redacted ) use ( $option ) {
				$redacted[] = $option;
				return $redacted;
			}
		);
	}

	/**
	 * Register an option as changed-only for the remainder of the test.
	 *
	 * @param string $option Option name.
	 */
	private function changed_only_option( string $option ): void {
		add_filter(
			'simple_history/settings/changed_only_options',
			static function ( $changed_only ) use ( $option ) {
				$changed_only[] = $option;
				return $changed_only;
			}
		);
	}

	/**
	 * Create an option and flush the buffered add, so the next change in the
	 * test is treated like a new request and logs this value as previous value.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Initial value.
	 */
	private function seed_option( string $option, $value ): void {
		add_option( $option, $value );
		$this->logger->commit_settings_changes();
	}

	/**
	 * Get the latest event's context as a key => value map.
	 *
	 * @return array<string,string>
	 */
	private function get_latest_context_map(): array {
		$context_map = [];

		foreach ( \Simple_History\tests\get_latest_context() as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		return $context_map;
	}

	public function test_core_keys_are_tracked() {
		$tracked = $this->logger->get_tracked_settings();

		$this->assertArrayHasKey( 'simple_history_show_on_dashboard', $tracked );
		$this->assertSame( 'Show on dashboard', $tracked['simple_history_show_on_dashboard'] );
		$this->assertArrayHasKey( 'simple_history_experimental_features_enabled', $tracked );
		$this->assertArrayHasKey( 'simple_history_email_report_enabled', $tracked );
		$this->assertArrayHasKey( 'simple_history_email_report_recipients', $tracked );
		$this->assertArrayHasKey( 'shp_license_key', $tracked );

		// The license key must never have its value stored.
		$this->assertContains( 'shp_license_key', $this->logger->get_redacted_settings() );
	}

	public function test_filter_can_add_tracked_keys() {
		$this->track_option( 'my_addon_option', 'My add-on option' );

		$tracked = $this->logger->get_tracked_settings();

		$this->assertArrayHasKey( 'my_addon_option', $tracked );
		$this->assertSame( 'My add-on option', $tracked['my_addon_option'] );
	}

	public function test_logs_tracked_option_change_on_commit() {
		$this->track_option( 'sh_test_tracked_option', 'Test tracked option' );
		$this->seed_option( 'sh_test_tracked_option', 'old-value' );

		update_option( 'sh_test_tracked_option', 'new-value' );

		// Commit is normally on 'shutdown'; call directly in the test.
		$this->logger->commit_settings_changes();

		$row = \Simple_History\tests\get_latest_row();
		$this->assertSame( 'SimpleHistoryLogger', $row['logger'], 'Event should be logged by the Simple History logger' );

		$context_map = $this->get_latest_context_map();

		$this->assertArrayHasKey( '_message_key', $context_map );
		$this->assertSame( 'modified_settings', $context_map['_message_key'] );
		$this->assertSame( 'old-value', $context_map['sh_test_tracked_option_prev'] );
		$this->assertSame( 'new-value', $context_map['sh_test_tracked_option_new'] );
	}

	public function test_does_not_log_untracked_option_change() {
		update_option( 'some_unrelated_option', 'whatever' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		// No tracked change accumulated, so the latest event must not be a settings change for this option.
		$this->assertArrayNotHasKey( 'some_unrelated_option_new', $context_map );
	}

	public function test_details_output_renders_tracked_key_with_label() {
		$this->track_option( 'shp_message_control', 'Message Control settings' );

		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'shp_message_control_prev' => 'a',
				'shp_message_control_new'  => 'b',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );

		// Event_Details_Group is not stringable on its own; render it through an
		// Event_Details_Container (the proper API), which applies the context and
		// exposes __toString().
		$html = (string) ( new Event_Details_Container( $output, $row->context ) );

		$this->assertStringContainsString( 'Message Control settings', $html );
	}

	public function test_details_output_skips_context_keys_without_label() {
		// Events stored by earlier plugin versions can contain unrelated
		// `*_prev`/`*_new` keys captured during the same save request
		// (e.g. `_transient_settings_errors`). Those must not be rendered.
		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'sh_unlabeled_option_prev' => 'x',
				'sh_unlabeled_option_new'  => 'y',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );
		$html   = (string) ( new Event_Details_Container( $output, $row->context ) );

		$this->assertStringNotContainsString( 'sh_unlabeled_option', $html );
	}

	public function test_details_output_renders_multiple_changed_settings() {
		$this->track_option( 'shp_one', 'Setting One' );
		$this->track_option( 'shp_two', 'Setting Two' );

		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'shp_one_prev' => '1a',
				'shp_one_new'  => '1b',
				'shp_two_prev' => '2a',
				'shp_two_new'  => '2b',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );
		$html   = (string) ( new Event_Details_Container( $output, $row->context ) );

		$this->assertStringContainsString( 'Setting One', $html );
		$this->assertStringContainsString( 'Setting Two', $html );
	}

	public function test_details_output_empty_for_non_settings_message() {
		$row = (object) [
			'context_message_key' => 'cleared_log',
			'context'             => [
				'num_rows_deleted' => '5',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );

		// An (empty) group, not a string, so the per-logger details filter
		// keeps firing for non-settings rows.
		$this->assertInstanceOf( Event_Details_Group::class, $output );
		$this->assertCount( 0, $output->items );
	}

	public function test_redacted_option_value_is_hidden() {
		$this->track_option( 'sh_test_secret_option', 'Test secret' );
		$this->redact_option( 'sh_test_secret_option' );

		// Seeding (instead of plain add_option) matters here: an option that
		// exists from before is the realistic case, and is what surfaces
		// regressions in the no-op skip, which must compare raw values, not
		// redacted placeholders.
		$this->seed_option( 'sh_test_secret_option', 'old-secret' );

		update_option( 'sh_test_secret_option', 'new-secret' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		// The change must be logged (not skipped as a no-op) as "(changed)"
		// with no values stored at all.
		$this->assertSame( 'modified_settings', $context_map['_message_key'] );
		$this->assertSame( '(changed)', $context_map['sh_test_secret_option_new'] );
		$this->assertArrayNotHasKey( 'sh_test_secret_option_prev', $context_map );
		$this->assertStringNotContainsString( 'new-secret', wp_json_encode( $context_map ) );
		$this->assertStringNotContainsString( 'old-secret', wp_json_encode( $context_map ) );
	}

	public function test_redacted_option_deletion_logs_without_value() {
		$this->track_option( 'sh_test_secret_delete', 'Test secret delete' );
		$this->redact_option( 'sh_test_secret_delete' );
		$this->seed_option( 'sh_test_secret_delete', 'secret-to-delete' );

		delete_option( 'sh_test_secret_delete' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( '(deleted)', $context_map['sh_test_secret_delete_new'] );
		$this->assertArrayNotHasKey( 'sh_test_secret_delete_prev', $context_map );
		$this->assertStringNotContainsString( 'secret-to-delete', wp_json_encode( $context_map ) );
	}

	public function test_logs_tracked_option_deletion_with_previous_value() {
		$this->track_option( 'sh_test_deletable_option', 'Test deletable option' );
		$this->seed_option( 'sh_test_deletable_option', 'value-before-delete' );

		delete_option( 'sh_test_deletable_option' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( 'value-before-delete', $context_map['sh_test_deletable_option_prev'] );
		$this->assertSame( '(deleted)', $context_map['sh_test_deletable_option_new'] );
	}

	public function test_changed_only_filter_logs_sentinel_without_value() {
		$this->track_option( 'sh_test_changed_only_scalar', 'Test changed-only scalar' );
		$this->changed_only_option( 'sh_test_changed_only_scalar' );

		add_option( 'sh_test_changed_only_scalar', 'old-value' );
		update_option( 'sh_test_changed_only_scalar', 'new-secret-detail' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( '(changed)', $context_map['sh_test_changed_only_scalar_new'] );
		$this->assertArrayNotHasKey( 'sh_test_changed_only_scalar_prev', $context_map );
		$this->assertStringNotContainsString( 'new-secret-detail', wp_json_encode( $context_map ) );
	}

	public function test_non_scalar_value_logs_as_changed_only_safety_net() {
		$this->track_option( 'sh_test_array_option', 'Test array option' );

		add_option( 'sh_test_array_option', [ 'unique_marker_aaa' => 1 ] );
		update_option( 'sh_test_array_option', [ 'unique_marker_bbb' => 2 ] );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( '(changed)', $context_map['sh_test_array_option_new'] );
		$this->assertArrayNotHasKey( 'sh_test_array_option_prev', $context_map );
		$this->assertStringNotContainsString( 'unique_marker_bbb', wp_json_encode( $context_map ) );
		$this->assertStringNotContainsString( 'unique_marker_aaa', wp_json_encode( $context_map ) );
	}

	public function test_changed_only_renders_sentinel_in_details() {
		$this->track_option( 'sh_test_changed_only_render', 'Test changed-only render' );

		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'sh_test_changed_only_render_new' => '(changed)',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );
		$html   = (string) ( new Event_Details_Container( $output, $row->context ) );

		$this->assertStringContainsString( 'Test changed-only render', $html );
		$this->assertStringContainsString( '(changed)', $html );
	}

	public function test_scalar_setting_still_logs_before_and_after() {
		$this->track_option( 'sh_test_plain_scalar', 'Test plain scalar' );
		$this->seed_option( 'sh_test_plain_scalar', 'before' );

		update_option( 'sh_test_plain_scalar', 'after' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( 'before', $context_map['sh_test_plain_scalar_prev'] );
		$this->assertSame( 'after', $context_map['sh_test_plain_scalar_new'] );
	}

	public function test_changed_only_deletion_logs_deleted_without_value() {
		$this->track_option( 'sh_test_changed_only_delete', 'Test changed-only delete' );
		$this->changed_only_option( 'sh_test_changed_only_delete' );

		add_option( 'sh_test_changed_only_delete', 'unique_delete_marker' );
		delete_option( 'sh_test_changed_only_delete' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( '(deleted)', $context_map['sh_test_changed_only_delete_new'] );
		$this->assertArrayNotHasKey( 'sh_test_changed_only_delete_prev', $context_map );
		$this->assertStringNotContainsString( 'unique_delete_marker', wp_json_encode( $context_map ) );
	}

	public function test_reverted_change_is_not_logged() {
		$this->track_option( 'sh_test_revert_option', 'Test revert option' );
		$this->seed_option( 'sh_test_revert_option', 'original' );

		$row_id_before = \Simple_History\tests\get_latest_row( false )['id'];

		// Change and revert within the same request: no visible change, so no event.
		update_option( 'sh_test_revert_option', 'changed' );
		update_option( 'sh_test_revert_option', 'original' );
		$this->logger->commit_settings_changes();

		$this->assertSame( $row_id_before, \Simple_History\tests\get_latest_row( false )['id'] );
	}

	public function test_multiple_changes_keep_first_old_value() {
		$this->track_option( 'sh_test_multi_change', 'Test multi change' );
		$this->seed_option( 'sh_test_multi_change', 'first' );

		// Two changes in the same request: the event shows the value from
		// before the request, not the intermediate one.
		update_option( 'sh_test_multi_change', 'second' );
		update_option( 'sh_test_multi_change', 'third' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( 'first', $context_map['sh_test_multi_change_prev'] );
		$this->assertSame( 'third', $context_map['sh_test_multi_change_new'] );
	}

	public function test_long_string_value_logs_as_changed_only() {
		$this->track_option( 'sh_test_long_string', 'Test long string' );
		$this->seed_option( 'sh_test_long_string', 'short' );

		update_option( 'sh_test_long_string', str_repeat( 'a', 600 ) . 'unique_long_marker' );
		$this->logger->commit_settings_changes();

		$context_map = $this->get_latest_context_map();

		$this->assertSame( '(changed)', $context_map['sh_test_long_string_new'] );
		$this->assertArrayNotHasKey( 'sh_test_long_string_prev', $context_map );
		$this->assertStringNotContainsString( 'unique_long_marker', wp_json_encode( $context_map ) );
	}

	public function test_every_registered_simple_history_setting_is_tracked() {
		// Coverage guard: every option registered to a Simple History settings
		// group must have a decided logging story — either present in the
		// tracked-settings map (so changes are logged as "Modified settings")
		// or registered to a group that handles its own logging and is
		// excluded below. Fails when someone adds a new settings tab/option
		// without deciding how it is logged.

		// Settings pages register their options on admin_menu; simulate an
		// admin request so all registrations run.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		set_current_screen( 'dashboard' );

		global $menu, $submenu;
		$menu    = [];
		$submenu = [];

		do_action( 'admin_menu' );

		// Groups whose changes are logged through their own mechanism.
		$excluded_groups = [
			// Logged as a separate "Updated Log Forwarding settings" event.
			\Simple_History\Services\Channels_Settings_Page::SETTINGS_OPTION_GROUP,
		];

		$tracked   = $this->logger->get_tracked_settings();
		$untracked = [];

		foreach ( get_registered_settings() as $option_name => $args ) {
			$group = $args['group'] ?? '';

			if ( strpos( $group, 'simple_history' ) !== 0 ) {
				continue;
			}

			if ( in_array( $group, $excluded_groups, true ) ) {
				continue;
			}

			if ( ! array_key_exists( $option_name, $tracked ) ) {
				$untracked[] = "{$option_name} (group: {$group})";
			}
		}

		$GLOBALS['current_screen'] = null;

		$this->assertSame(
			[],
			$untracked,
			'Options registered to a Simple History settings group must be logged when changed. ' .
			'Add them to the tracked-settings map (core) or the simple_history/settings/tracked_options filter (add-ons), ' .
			'or exclude their group in this test if they handle their own logging.'
		);
	}

	public function test_option_registered_to_settings_group_is_tracked() {
		// Options registered to the Simple History settings group are logged
		// even without the tracked-options filter, like in earlier versions.
		register_setting( Simple_History::SETTINGS_GENERAL_OPTION_GROUP, 'sh_test_registered_setting' );

		add_option( 'sh_test_registered_setting', 'one' );
		$this->logger->commit_settings_changes();

		update_option( 'sh_test_registered_setting', 'two' );
		$this->logger->commit_settings_changes();

		unregister_setting( Simple_History::SETTINGS_GENERAL_OPTION_GROUP, 'sh_test_registered_setting' );

		$context_map = $this->get_latest_context_map();

		$this->assertSame( 'one', $context_map['sh_test_registered_setting_prev'] );
		$this->assertSame( 'two', $context_map['sh_test_registered_setting_new'] );
	}
}
