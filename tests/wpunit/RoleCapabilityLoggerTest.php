<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Role_Capability_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Test Role & Capability Logger functionality.
 *
 * Tests the Role_Capability_Logger class which logs changes to
 * WordPress roles and capabilities via the wp_user_roles option.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit RoleCapabilityLoggerTest
 */
class RoleCapabilityLoggerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Simple History instance
	 *
	 * @var Simple_History
	 */
	private $sh;

	/**
	 * Role & Capability Logger instance
	 *
	 * @var Role_Capability_Logger
	 */
	private $logger;

	/**
	 * Admin user ID
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Enable experimental features so the logger is registered.
		update_option( 'simple_history_experimental_features_enabled', '1' );

		$this->sh = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimpleRoleCapabilityLogger' );

		// If the logger wasn't loaded at boot (experimental flag wasn't set yet),
		// instantiate and load it manually for this test run.
		if ( ! $this->logger instanceof Role_Capability_Logger ) {
			$this->logger = new Role_Capability_Logger( $this->sh );
			$this->logger->loaded();
		}

		$this->admin_user_id = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);
		wp_set_current_user( $this->admin_user_id );
	}

	/**
	 * Clean up after each test: remove any test roles we created.
	 */
	public function tearDown(): void {
		remove_role( 'test_role' );
		remove_role( 'test_role_2' );
		remove_role( 'test_empty_role' );
		parent::tearDown();
	}

	/**
	 * Trigger the shutdown handler to flush deferred log entries,
	 * then reset internal state so subsequent tests start clean.
	 */
	private function flush_logger() {
		$this->logger->on_shutdown_log_role_changes();

		// Reset internal state via reflection so the next test starts fresh.
		$reflection = new ReflectionClass( $this->logger );

		$initial = $reflection->getProperty( 'initial_roles' );
		$initial->setAccessible( true );
		$initial->setValue( $this->logger, null );

		$captured = $reflection->getProperty( 'captured_plugin_context' );
		$captured->setAccessible( true );
		$captured->setValue( $this->logger, array() );

		$ctx = $reflection->getProperty( 'plugin_context' );
		$ctx->setAccessible( true );
		$ctx->setValue( $this->logger, array() );
	}

	/**
	 * Test that the logger exists and is loaded.
	 */
	public function test_logger_exists() {
		$this->assertNotNull( $this->logger, 'Role Capability Logger should be instantiated' );
		$this->assertInstanceOf( Role_Capability_Logger::class, $this->logger );
		$this->assertEquals( 'SimpleRoleCapabilityLogger', $this->logger->get_slug() );
	}

	/**
	 * Test that the logger has correct info.
	 */
	public function test_logger_info() {
		$info = $this->logger->get_info();

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'name', $info );
		$this->assertArrayHasKey( 'description', $info );
		$this->assertArrayHasKey( 'capability', $info );
		$this->assertArrayHasKey( 'messages', $info );

		$this->assertEquals( 'manage_options', $info['capability'] );

		$this->assertArrayHasKey( 'role_created', $info['messages'] );
		$this->assertArrayHasKey( 'role_deleted', $info['messages'] );
		$this->assertArrayHasKey( 'role_caps_added', $info['messages'] );
		$this->assertArrayHasKey( 'role_caps_removed', $info['messages'] );
		$this->assertArrayHasKey( 'role_display_name_changed', $info['messages'] );
	}

	/**
	 * Test logging when a role is created.
	 */
	public function test_role_created() {
		add_role( 'test_role', 'Test Role', [ 'read' => true, 'edit_posts' => true ] );
		$this->flush_logger();

		$latest_row = get_latest_row();

		$this->assertEquals( 'SimpleRoleCapabilityLogger', $latest_row['logger'] );
		$this->assertEquals( 'notice', $latest_row['level'] );

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'role_created' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'role_slug', 'value' => 'test_role' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'role_name', 'value' => 'Test Role' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'cap_count', 'value' => '2' ],
			$context
		);
	}

	/**
	 * Test that created role capabilities are sorted alphabetically.
	 */
	public function test_role_created_caps_sorted() {
		add_role( 'test_role', 'Test Role', [ 'upload_files' => true, 'edit_posts' => true, 'read' => true ] );
		$this->flush_logger();

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => 'capabilities', 'value' => 'edit_posts, read, upload_files' ],
			$context
		);
	}

	/**
	 * Test logging when a role is created with no capabilities.
	 */
	public function test_role_created_empty_caps() {
		add_role( 'test_empty_role', 'Empty Role', [] );
		$this->flush_logger();

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'role_created' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'cap_count', 'value' => '0' ],
			$context
		);
	}

	/**
	 * Test logging when a role is deleted.
	 */
	public function test_role_deleted() {
		// Create and flush first to establish baseline.
		add_role( 'test_role', 'Test Role', [ 'read' => true ] );
		$this->flush_logger();

		remove_role( 'test_role' );
		$this->flush_logger();

		$latest_row = get_latest_row();

		$this->assertEquals( 'SimpleRoleCapabilityLogger', $latest_row['logger'] );
		$this->assertEquals( 'warning', $latest_row['level'] );

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'role_deleted' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'role_slug', 'value' => 'test_role' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'role_name', 'value' => 'Test Role' ],
			$context
		);
	}

	/**
	 * Test logging when capabilities are added to an existing role.
	 */
	public function test_caps_added_to_role() {
		$role = get_role( 'editor' );
		$this->assertNotNull( $role );

		$role->add_cap( 'test_custom_cap' );
		$this->flush_logger();

		$latest_row = get_latest_row();

		$this->assertEquals( 'SimpleRoleCapabilityLogger', $latest_row['logger'] );
		$this->assertEquals( 'notice', $latest_row['level'] );

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'role_caps_added' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'role_slug', 'value' => 'editor' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'capabilities', 'value' => 'test_custom_cap' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'cap_count', 'value' => '1' ],
			$context
		);

		// Clean up.
		$role->remove_cap( 'test_custom_cap' );
		$this->flush_logger();
	}

	/**
	 * Test logging when capabilities are removed from an existing role.
	 */
	public function test_caps_removed_from_role() {
		// Add a cap first and flush.
		$role = get_role( 'editor' );
		$role->add_cap( 'test_custom_cap' );
		$this->flush_logger();

		$role->remove_cap( 'test_custom_cap' );
		$this->flush_logger();

		$latest_row = get_latest_row();

		$this->assertEquals( 'SimpleRoleCapabilityLogger', $latest_row['logger'] );
		$this->assertEquals( 'warning', $latest_row['level'] );

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'role_caps_removed' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'role_slug', 'value' => 'editor' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'capabilities', 'value' => 'test_custom_cap' ],
			$context
		);
	}

	/**
	 * Test logging when a role's display name is changed.
	 */
	public function test_role_display_name_changed() {
		global $wpdb;
		$role_key = $wpdb->prefix . 'user_roles';
		$roles = get_option( $role_key );

		// Change the display name of the subscriber role.
		$old_name = $roles['subscriber']['name'];
		$roles['subscriber']['name'] = 'Basic Reader';
		update_option( $role_key, $roles );
		$this->flush_logger();

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'role_display_name_changed' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'role_slug', 'value' => 'subscriber' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'old_name', 'value' => $old_name ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'new_name', 'value' => 'Basic Reader' ],
			$context
		);

		// Restore original name.
		$roles['subscriber']['name'] = $old_name;
		update_option( $role_key, $roles );
		$this->flush_logger();
	}

	/**
	 * Test that transient add/remove cycles within a single request cancel out.
	 *
	 * This tests the deferred-logging approach: if a plugin adds then removes
	 * a cap in the same request, no log entry should be created.
	 */
	public function test_transient_changes_cancel_out() {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$role = get_role( 'author' );
		$role->add_cap( 'transient_cap' );
		// Do NOT flush — simulate same request.
		$role->remove_cap( 'transient_cap' );
		$this->flush_logger();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $initial_count, $final_count, 'Transient add+remove should produce no log entry' );
	}

	/**
	 * Test that multiple capability changes in a single request are batched.
	 */
	public function test_multiple_caps_batched() {
		$role = get_role( 'contributor' );
		$role->add_cap( 'batch_cap_one' );
		$role->add_cap( 'batch_cap_two' );
		$this->flush_logger();

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'role_caps_added' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'cap_count', 'value' => '2' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'capabilities', 'value' => 'batch_cap_one, batch_cap_two' ],
			$context
		);

		// Clean up.
		$role->remove_cap( 'batch_cap_one' );
		$role->remove_cap( 'batch_cap_two' );
		$this->flush_logger();
	}

	/**
	 * Test that no log entries are created when roles are unchanged.
	 */
	public function test_no_log_when_unchanged() {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Update the option with the same value (no actual change).
		$role_key = $wpdb->prefix . 'user_roles';
		$roles = get_option( $role_key );
		update_option( $role_key, $roles );
		$this->flush_logger();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $initial_count, $final_count, 'No log entries should be created for unchanged roles' );
	}

	/**
	 * Test that non-array values are ignored.
	 */
	public function test_non_array_values_ignored() {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Call on_roles_updated directly with non-array values.
		$this->logger->on_roles_updated( 'not_an_array', 'also_not_an_array' );
		$this->flush_logger();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $initial_count, $final_count, 'Non-array values should be ignored' );
	}

	/**
	 * Test that creating and deleting a role in the same request cancels out.
	 */
	public function test_create_then_delete_cancels_out() {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		add_role( 'test_role', 'Ephemeral Role', [ 'read' => true ] );
		remove_role( 'test_role' );
		$this->flush_logger();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $initial_count, $final_count, 'Create+delete in same request should cancel out' );
	}

	/**
	 * Test that only granted (true) capabilities are counted.
	 * A capability set to false should not appear as added.
	 */
	public function test_only_granted_caps_counted() {
		add_role( 'test_role', 'Test Role', [ 'read' => true, 'edit_posts' => false ] );
		$this->flush_logger();

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => 'cap_count', 'value' => '1' ],
			$context,
			'Only granted (true) caps should be counted'
		);
		$this->assertContains(
			[ 'key' => 'capabilities', 'value' => 'read' ],
			$context
		);
	}

	/**
	 * Test that plugin context is captured during activation.
	 */
	public function test_plugin_context_captured() {
		// Simulate plugin activation context.
		$this->logger->on_plugin_activation_start( 'test-plugin/test-plugin.php' );

		add_role( 'test_role', 'Plugin Role', [ 'read' => true ] );
		$this->flush_logger();

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => 'plugin_context', 'value' => 'test-plugin/test-plugin.php' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'plugin_context_action', 'value' => 'activation' ],
			$context
		);

		// Clean up activation state.
		$this->logger->on_plugin_activation_end( 'test-plugin/test-plugin.php' );
	}

	/**
	 * Test that plugin context is cleared after activation ends.
	 */
	public function test_plugin_context_cleared_after_activation() {
		$this->logger->on_plugin_activation_start( 'test-plugin/test-plugin.php' );
		$this->logger->on_plugin_activation_end( 'test-plugin/test-plugin.php' );

		add_role( 'test_role', 'After Activation Role', [ 'read' => true ] );
		$this->flush_logger();

		$context = get_latest_context();

		// plugin_context should not appear.
		$context_keys = array_column( $context, 'key' );
		$this->assertNotContains( 'plugin_context', $context_keys );
	}

	/**
	 * Test search labels exist.
	 */
	public function test_search_labels() {
		$info = $this->logger->get_info();
		$this->assertArrayHasKey( 'labels', $info );
		$this->assertArrayHasKey( 'search', $info['labels'] );
		$this->assertArrayHasKey( 'options', $info['labels']['search'] );
	}

	/**
	 * Test get_log_row_details_output with capabilities context.
	 */
	public function test_log_row_details_with_caps() {
		add_role( 'test_role', 'Test Role', [ 'read' => true ] );
		$this->flush_logger();

		// Get the latest row as an object for get_log_row_details_output.
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		$db_table_contexts = $this->sh->get_contexts_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( "SELECT * FROM {$db_table} ORDER BY id DESC LIMIT 1" );

		// Build context array keyed by key (as the logger expects).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$context_rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT `key`, `value` FROM {$db_table_contexts} WHERE history_id = %d", $row->id ),
			ARRAY_A
		);
		$row->context = [];
		foreach ( $context_rows as $ctx ) {
			$row->context[ $ctx['key'] ] = $ctx['value'];
		}

		$output = $this->logger->get_log_row_details_output( $row );

		// Should return Event_Details_Group with capabilities item.
		$this->assertInstanceOf( \Simple_History\Event_Details\Event_Details_Group::class, $output );
	}

	/**
	 * Test get_log_row_details_output returns empty string when no details.
	 */
	public function test_log_row_details_empty_for_deleted_role() {
		// Create a mock row with role_deleted context (no caps, no plugin context).
		$row = (object) [
			'context' => [
				'_message_key' => 'role_deleted',
				'role_slug'    => 'some_role',
				'role_name'    => 'Some Role',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );

		$this->assertEmpty( $output, 'Deleted role without plugin context or caps should return empty' );
	}
}
