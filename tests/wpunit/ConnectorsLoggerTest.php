<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Connectors_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Test Connectors Logger functionality.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit ConnectorsLoggerTest
 */
class ConnectorsLoggerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var Simple_History
	 */
	private $sh;

	/**
	 * @var Connectors_Logger
	 */
	private $logger;

	/**
	 * Fake connector data used to drive the logger handlers directly.
	 *
	 * @var array
	 */
	private $fake_connector = array(
		'name'           => 'Anthropic',
		'description'    => 'Text generation with Claude.',
		'type'           => 'ai_provider',
		'authentication' => array(
			'method'       => 'api_key',
			'setting_name' => 'connectors_ai_anthropic_api_key',
		),
	);

	public function setUp(): void {
		parent::setUp();

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'ConnectorsLogger' );

		$admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user_id );
	}

	public function test_logger_exists_and_is_loaded() {
		$this->assertNotNull( $this->logger, 'Connectors Logger should be instantiated' );
		$this->assertInstanceOf( Connectors_Logger::class, $this->logger );
		$this->assertEquals( 'ConnectorsLogger', $this->logger->get_slug() );
	}

	public function test_logger_info_shape() {
		$info = $this->logger->get_info();

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'name', $info );
		$this->assertArrayHasKey( 'description', $info );
		$this->assertEquals( 'manage_options', $info['capability'] );

		$this->assertArrayHasKey( 'connector_api_key_added', $info['messages'] );
		$this->assertArrayHasKey( 'connector_api_key_updated', $info['messages'] );
		$this->assertArrayHasKey( 'connector_api_key_removed', $info['messages'] );
	}

	public function test_logs_when_api_key_is_added() {
		$this->logger->on_connector_option_added( 'anthropic', $this->fake_connector, 'sk-ant-test-abcd1234EFGH' );

		$row = get_latest_row();
		$this->assertEquals( 'ConnectorsLogger', $row['logger'] );
		$this->assertEquals( 'info', $row['level'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'connector_api_key_added' );
		$this->assert_context_has( $context, 'connector_id', 'anthropic' );
		$this->assert_context_has( $context, 'connector_name', 'Anthropic' );
		$this->assert_context_has( $context, 'connector_type', 'ai_provider' );
		$this->assert_context_has( $context, 'connector_setting_name', 'connectors_ai_anthropic_api_key' );
		$this->assert_context_has( $context, 'api_key_new_last_4', 'EFGH' );

		$this->assert_context_does_not_contain_full_key( $context, 'sk-ant-test-abcd1234EFGH' );
	}

	public function test_logs_when_api_key_is_updated() {
		$this->logger->on_connector_option_updated( 'anthropic', $this->fake_connector, 'old-key-ABCD', 'new-key-WXYZ' );

		$row = get_latest_row();
		$this->assertEquals( 'ConnectorsLogger', $row['logger'] );
		$this->assertEquals( 'notice', $row['level'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'connector_api_key_updated' );
		$this->assert_context_has( $context, 'api_key_prev_last_4', 'ABCD' );
		$this->assert_context_has( $context, 'api_key_new_last_4', 'WXYZ' );

		$this->assert_context_does_not_contain_full_key( $context, 'old-key-ABCD' );
		$this->assert_context_does_not_contain_full_key( $context, 'new-key-WXYZ' );
	}

	public function test_logs_when_api_key_is_removed_via_delete() {
		$this->logger->on_connector_option_deleted( 'anthropic', $this->fake_connector, 'sk-ant-test-ZZZZ' );

		$row = get_latest_row();
		$this->assertEquals( 'ConnectorsLogger', $row['logger'] );
		$this->assertEquals( 'warning', $row['level'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'connector_api_key_removed' );
		$this->assert_context_has( $context, 'api_key_prev_last_4', 'ZZZZ' );
	}

	public function test_update_from_empty_logs_as_added() {
		$this->logger->on_connector_option_updated( 'anthropic', $this->fake_connector, '', 'fresh-key-1234' );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'connector_api_key_added' );
		$this->assert_context_has( $context, 'api_key_new_last_4', '1234' );
	}

	public function test_update_to_empty_logs_as_removed() {
		$this->logger->on_connector_option_updated( 'anthropic', $this->fake_connector, 'expiring-key-9876', '' );

		$row     = get_latest_row();
		$context = get_latest_context();

		$this->assertEquals( 'warning', $row['level'] );
		$this->assert_context_has( $context, '_message_key', 'connector_api_key_removed' );
		$this->assert_context_has( $context, 'api_key_prev_last_4', '9876' );
	}

	public function test_no_log_when_value_unchanged() {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->logger->on_connector_option_updated( 'anthropic', $this->fake_connector, 'same-key', 'same-key' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $count_before, $count_after, 'No log entry should be created when value is unchanged' );
	}

	public function test_no_log_when_empty_value_added() {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->logger->on_connector_option_added( 'anthropic', $this->fake_connector, '' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $count_before, $count_after, 'No log entry should be created for empty initial value' );
	}

	/**
	 * Short keys (≤ 4 chars) must NOT have a `last_4` field stored at all
	 * (storing `'****'` adds zero forensic value and falsely implies a
	 * partial). Instead the audit record carries length + a was-short flag.
	 */
	public function test_short_key_stores_length_not_suffix() {
		$this->logger->on_connector_option_added( 'anthropic', $this->fake_connector, 'abcd' );

		$context = get_latest_context();

		$this->assert_context_has( $context, 'api_key_new_was_short', 'true' );
		$this->assert_context_has( $context, 'api_key_new_length', '4' );
		$this->assertEmpty(
			array_filter(
				$context,
				static fn ( $row ) => ( $row['key'] ?? '' ) === 'api_key_new_last_4'
			),
			'No last_4 entry should be stored for short keys'
		);
		$this->assert_context_does_not_contain_full_key( $context, 'abcd' );
	}

	/**
	 * Regression test for the delete-path bug: earlier code hooked
	 * `delete_option_{$option}` (which fires AFTER the row is gone) and called
	 * `get_option()` inside, so it always logged nothing. Now we route via
	 * the global `delete_option`/`deleted_option` pair.
	 *
	 * Drives `handle_deleted_option` with an explicit pre-delete value via
	 * its $old_value_override test seam, avoiding reflection into the stash.
	 */
	public function test_handle_deleted_option_logs_with_explicit_old_value() {
		$setting_name = $this->fake_connector['authentication']['setting_name'];

		$this->seed_connectors_by_setting( $setting_name, 'anthropic', $this->fake_connector );

		$this->logger->handle_deleted_option( $setting_name, 'sk-ant-test-DELE' );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'connector_api_key_removed' );
		$this->assert_context_has( $context, 'api_key_prev_last_4', 'DELE' );
		$this->assert_context_does_not_contain_full_key( $context, 'sk-ant-test-DELE' );
	}

	/**
	 * `register_connector_hooks()` should no-op cleanly on WordPress versions
	 * that pre-date the Connectors API (i.e. when `wp_get_connectors()` is
	 * absent). On those versions we should bind no hooks and not error.
	 */
	public function test_register_connector_hooks_noops_when_connectors_api_missing() {
		if ( function_exists( 'wp_get_connectors' ) ) {
			$this->markTestSkipped( 'WordPress runtime exposes wp_get_connectors(); the missing-API path is not reachable here.' );
		}

		$logger = new Connectors_Logger( Simple_History::get_instance() );

		// Should not throw and should not register any hooks.
		$logger->register_connector_hooks();

		$reflection = new ReflectionClass( Connectors_Logger::class );
		$prop       = $reflection->getProperty( 'connectors_by_setting' );
		$prop->setAccessible( true );

		$this->assertSame( array(), $prop->getValue( $logger ) );
		$this->assertFalse( has_action( 'added_option', array( $logger, 'handle_added_option' ) ) );
		$this->assertFalse( has_action( 'updated_option', array( $logger, 'handle_updated_option' ) ) );
		$this->assertFalse( has_action( 'delete_option', array( $logger, 'capture_value_before_delete' ) ) );
		$this->assertFalse( has_action( 'deleted_option', array( $logger, 'handle_deleted_option' ) ) );
	}

	/**
	 * When the Connectors API is available, `register_connector_hooks()` should
	 * populate `connectors_by_setting` from `wp_get_connectors()` and bind the
	 * four global option hooks.
	 */
	public function test_register_connector_hooks_binds_global_hooks_when_connectors_exist() {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			$this->markTestSkipped( 'Connectors API not available on this WordPress version.' );
		}

		$connectors = wp_get_connectors();
		if ( empty( $connectors ) ) {
			$this->markTestSkipped( 'No connectors registered in this test runtime — cannot verify hook binding.' );
		}

		$logger = new Connectors_Logger( Simple_History::get_instance() );
		$logger->register_connector_hooks();

		$reflection = new ReflectionClass( Connectors_Logger::class );
		$prop       = $reflection->getProperty( 'connectors_by_setting' );
		$prop->setAccessible( true );

		$this->assertNotEmpty( $prop->getValue( $logger ), 'connectors_by_setting should be populated' );
		$this->assertNotFalse( has_action( 'added_option', array( $logger, 'handle_added_option' ) ) );
		$this->assertNotFalse( has_action( 'updated_option', array( $logger, 'handle_updated_option' ) ) );
		$this->assertNotFalse( has_action( 'delete_option', array( $logger, 'capture_value_before_delete' ) ) );
		$this->assertNotFalse( has_action( 'deleted_option', array( $logger, 'handle_deleted_option' ) ) );
	}

	/**
	 * Real end-to-end delete via WordPress: ensure that `delete_option()` on a
	 * connector setting produces a log entry. This is the path the reviewer
	 * flagged as broken in the original implementation.
	 */
	public function test_delete_option_on_connector_setting_logs_removal() {
		$setting_name = $this->fake_connector['authentication']['setting_name'];

		$this->seed_connectors_by_setting( $setting_name, 'anthropic', $this->fake_connector );

		remove_action( 'delete_option', array( $this->logger, 'capture_value_before_delete' ) );
		remove_action( 'deleted_option', array( $this->logger, 'handle_deleted_option' ) );
		add_action( 'delete_option', array( $this->logger, 'capture_value_before_delete' ) );
		add_action( 'deleted_option', array( $this->logger, 'handle_deleted_option' ) );

		add_option( $setting_name, 'sk-ant-real-DELL' );
		delete_option( $setting_name );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'connector_api_key_removed' );
		$this->assert_context_has( $context, 'api_key_prev_last_4', 'DELL' );
	}

	/**
	 * The event details renderer should produce a bullet-prefixed credential
	 * display (`••••7890`) from a stored last_4 suffix.
	 *
	 * On an add event (no prev), the label should be "API key", not "New API
	 * key" — there's nothing to be "new" relative to.
	 */
	public function test_event_details_renders_last_4_with_bullets_on_add() {
		$row          = new stdClass();
		$row->context = array(
			'connector_setting_name' => 'connectors_ai_anthropic_api_key',
			'connector_type'         => 'ai_provider',
			'api_key_new_last_4'     => '7890',
		);

		$output = $this->render_event_details( $row );

		$this->assertStringContainsString( "\u{2022}\u{2022}\u{2022}\u{2022}", $output );
		$this->assertStringContainsString( '7890', $output );
		// Context-aware label: just "API key" on add events.
		$this->assertStringContainsString( 'API key', $output );
		$this->assertStringNotContainsString( 'New API key', $output );
	}

	/**
	 * Connector type slugs (e.g. `ai_provider`) must be humanized at render
	 * time — admins shouldn't be confronted with raw internal slugs.
	 */
	public function test_event_details_humanizes_connector_type() {
		$row          = new stdClass();
		$row->context = array(
			'connector_type' => 'ai_provider',
		);

		$output = $this->render_event_details( $row );

		$this->assertStringContainsString( 'AI provider', $output );
		$this->assertStringNotContainsString( 'ai_provider', $output );
	}

	/**
	 * `Setting name` is internal plumbing (wp_options key). It must still be
	 * stored in context for REST/export consumers, but should NOT appear in
	 * the visible event details by default.
	 */
	public function test_event_details_omits_setting_name_row() {
		$row          = new stdClass();
		$row->context = array(
			'connector_setting_name' => 'connectors_ai_anthropic_api_key',
			'api_key_new_last_4'     => '7890',
		);

		$output = $this->render_event_details( $row );

		$this->assertStringNotContainsString( 'connectors_ai_anthropic_api_key', $output );
		$this->assertStringNotContainsString( 'Setting name', $output );
	}

	/**
	 * Short-secret rendering should produce a non-disclosing length notice
	 * rather than the bullet-credential format.
	 */
	public function test_event_details_renders_short_secret_as_length_notice() {
		$row          = new stdClass();
		$row->context = array(
			'connector_setting_name' => 'connectors_ai_anthropic_api_key',
			'api_key_new_was_short'  => 'true',
			'api_key_new_length'     => '3',
		);

		$output = $this->render_event_details( $row );

		$this->assertStringContainsString( '3 characters', $output );
		// And critically: no bullets, no false-partial.
		$this->assertStringNotContainsString( "\u{2022}", $output );
	}

	/**
	 * Both previous and new keys should render side-by-side on an update.
	 */
	public function test_event_details_renders_both_directions_on_update() {
		$row          = new stdClass();
		$row->context = array(
			'connector_setting_name' => 'connectors_ai_anthropic_api_key',
			'api_key_prev_last_4'    => 'ABCD',
			'api_key_new_last_4'     => 'WXYZ',
		);

		$output = $this->render_event_details( $row );

		$this->assertStringContainsString( 'Previous API key', $output );
		$this->assertStringContainsString( 'New API key', $output );
		$this->assertStringContainsString( 'ABCD', $output );
		$this->assertStringContainsString( 'WXYZ', $output );
	}

	/**
	 * Action link surfaces only when the admin can manage options AND the
	 * Connectors API is available — never a dead link.
	 */
	public function test_action_links_returns_link_when_capable_and_api_present() {
		if ( ! function_exists( 'wp_get_connectors' ) ) {
			$this->markTestSkipped( 'Connectors API not available on this WordPress version.' );
		}

		$row   = new stdClass();
		$links = $this->logger->get_action_links( $row );

		$this->assertCount( 1, $links );
		$this->assertEquals( 'edit', $links[0]['action'] );
		$this->assertStringContainsString( 'options-connectors.php', $links[0]['url'] );
		$this->assertStringContainsString( 'Manage connectors', $links[0]['label'] );
	}

	public function test_action_links_returns_empty_when_user_cannot_manage_options() {
		// Make sure the current user is a subscriber, not admin.
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$row   = new stdClass();
		$links = $this->logger->get_action_links( $row );

		$this->assertSame( array(), $links );
	}

	public function test_action_links_returns_empty_when_connectors_api_absent() {
		if ( function_exists( 'wp_get_connectors' ) ) {
			$this->markTestSkipped( 'Connectors API present on this runtime; the missing-API path is not reachable here.' );
		}

		$row   = new stdClass();
		$links = $this->logger->get_action_links( $row );

		$this->assertSame( array(), $links );
	}

	/**
	 * Render the logger's Event_Details_Group to HTML through its formatter.
	 *
	 * @param object $row Mock row passed into get_log_row_details_output.
	 * @return string
	 */
	private function render_event_details( $row ): string {
		$group = $this->logger->get_log_row_details_output( $row );

		return (string) $group->formatter->to_html( $group );
	}

	/**
	 * Seed the logger's connector lookup map without re-running discovery.
	 *
	 * Reflection is required because `connectors_by_setting` is protected; this
	 * helper centralises the access so individual tests stay readable.
	 *
	 * @param string $setting_name   The wp_options key.
	 * @param string $connector_id   Connector identifier (e.g. "anthropic").
	 * @param array  $connector_data Connector data shape from wp_get_connectors().
	 */
	private function seed_connectors_by_setting( string $setting_name, string $connector_id, array $connector_data ): void {
		$prop = ( new ReflectionClass( Connectors_Logger::class ) )->getProperty( 'connectors_by_setting' );
		$prop->setAccessible( true );
		$prop->setValue(
			$this->logger,
			array(
				$setting_name => array(
					'id'   => $connector_id,
					'data' => $connector_data,
				),
			)
		);
	}

	/**
	 * Assert that a key/value pair exists in the context array.
	 *
	 * @param array  $context Context rows from get_latest_context().
	 * @param string $key     Context key.
	 * @param string $value   Expected value.
	 */
	private function assert_context_has( array $context, string $key, string $value ): void {
		$this->assertContains(
			array( 'key' => $key, 'value' => $value ),
			$context,
			sprintf( 'Context should contain %s=%s', $key, $value )
		);
	}

	/**
	 * Ensure no context value contains the full API key — neither as the value
	 * itself nor as a substring of any stored value.
	 *
	 * @param array  $context  Context rows.
	 * @param string $full_key The full key value that must NOT appear.
	 */
	private function assert_context_does_not_contain_full_key( array $context, string $full_key ): void {
		foreach ( $context as $row ) {
			$value = $row['value'] ?? '';

			if ( ! is_string( $value ) ) {
				continue;
			}

			$this->assertStringNotContainsString(
				$full_key,
				$value,
				sprintf( 'Context key "%s" should not contain the full API key as a substring', $row['key'] ?? '' )
			);
		}
	}
}
