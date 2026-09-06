<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Menu_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Tests that Menu_Logger captures menu changes made via WP-CLI and REST API.
 *
 * Issue #185: three load-nav-menus.php hooks handle delete, item-update, and
 * location-change detection. These callbacks read $_REQUEST/$_POST directly and
 * never fire outside wp-admin.
 *
 * Unaffected (already works): wp menu create via wp_create_nav_menu (canonical).
 * Affected: delete, item updates, location assignment.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit MenuLoggerRestCliTest
 */
class MenuLoggerRestCliTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var Menu_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimpleMenuLogger' );

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_wp_cli' );
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_logger_exists_and_is_loaded() {
		$this->assertNotNull( $this->logger );
		$this->assertInstanceOf( Menu_Logger::class, $this->logger );
	}

	/**
	 * Regression: wp_create_nav_menu already works (canonical hook, no admin gate).
	 * Verify this keeps working after any fix.
	 */
	public function test_logs_menu_create_already_works() {
		$count_before = $this->get_event_count();

		wp_create_nav_menu( 'Test Menu ' . wp_generate_password( 4, false ) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'Menu creation via wp_create_nav_menu must already be logged (regression)'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'created_menu' );
	}

	/**
	 * Regression guard: when a menu is deleted from within wp-admin, the new
	 * wp_delete_nav_menu handler must not fire. Admin menu deletion is logged by
	 * the pre-existing load-nav-menus.php path. If the new handler doesn't bail
	 * on is_admin(), every admin menu deletion would log twice in production.
	 */
	public function test_admin_menu_delete_does_not_double_log() {
		set_current_screen( 'nav-menus' );
		$this->assertTrue( is_admin(), 'is_admin() must be true for this test to be meaningful' );

		$menu_name = 'Admin Delete Menu ' . wp_generate_password( 4, false );
		$menu_id   = wp_create_nav_menu( $menu_name );
		$this->assertIsInt( $menu_id );

		$count_before = $this->get_event_count();

		wp_delete_nav_menu( $menu_id );

		// This guards only the new non-admin handler; the existing admin
		// load-nav-menus.php path is not exercised here (it depends on $_REQUEST
		// which we don't simulate).
		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'Admin context must not produce a deleted_menu event from the non-admin handler (double-log guard)'
		);

		set_current_screen( 'front' );
	}

	/**
	 * WP-CLI: wp_delete_nav_menu() with WP_CLI=true must log a menu_deleted event
	 * that includes the menu name (captured before deletion).
	 *
	 * Simulates: wp menu delete <menu-name>
	 *
	 * Currently FAILS — detection runs through load-nav-menus.php + $_REQUEST.
	 * Even if that were fixed, the name must be captured pre-delete because
	 * wp_delete_nav_menu fires after the menu is gone.
	 */
	public function test_logs_menu_delete_with_name_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$menu_name = 'Menu To Delete ' . wp_generate_password( 4, false );
		$menu_id   = wp_create_nav_menu( $menu_name );
		$this->assertIsInt( $menu_id, 'Menu creation must succeed for the delete test' );

		$count_before = $this->get_event_count();

		wp_delete_nav_menu( $menu_id );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_delete_nav_menu() must produce a menu_deleted log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleMenuLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'deleted_menu' );

		// The menu name must be present even though the menu is already gone
		// when the hook fires — it must have been captured pre-delete.
		$context_values = array_column( $context, 'value' );
		$this->assertContains(
			$menu_name,
			$context_values,
			'The deleted menu name must appear in context (captured before deletion)'
		);
	}

	/**
	 * WP-CLI: wp_update_nav_menu_item() with WP_CLI=true must log an item-added
	 * or item-updated event.
	 *
	 * Simulates: wp menu item add-page <menu> <page-id>
	 *
	 * Currently FAILS — the hook is commented out with a UX reason that doesn't
	 * apply to WP-CLI (changes commit immediately).
	 */
	public function test_logs_menu_item_add_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$menu_id = wp_create_nav_menu( 'Item Test Menu ' . wp_generate_password( 4, false ) );
		$page_id = $this->factory->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$count_before = $this->get_event_count();

		wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-status'    => 'publish',
		) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_update_nav_menu_item() must produce a log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleMenuLogger', $row['logger'] );
	}

	/**
	 * WP-CLI: changing nav_menu_locations via set_theme_mod() with WP_CLI=true
	 * must log a location-change event.
	 *
	 * Simulates: wp menu location assign <menu> <location>
	 *
	 * Currently FAILS — detection runs through load-nav-menus.php + $_POST.
	 */
	public function test_logs_menu_location_change_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$menu_id  = wp_create_nav_menu( 'Location Test Menu ' . wp_generate_password( 4, false ) );
		// Register a dummy theme location so set_theme_mod has something to write.
		register_nav_menu( 'test-primary', 'Test Primary' );

		$count_before = $this->get_event_count();

		// wp menu location assign calls set_theme_mod('nav_menu_locations', ...).
		$current_locations = get_theme_mod( 'nav_menu_locations', array() );
		$current_locations['test-primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $current_locations );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI set_theme_mod(nav_menu_locations) must produce a location-change log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleMenuLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'edited_menu_locations' );
	}

	/**
	 * WP-CLI: updating an existing menu item must log an edited_menu_item event,
	 * just like adding does. wp_update_nav_menu_item fires for both add and update
	 * (the second arg is 0 for new, > 0 for updates).
	 *
	 * Tripwire for: wp menu item update <id>.
	 */
	public function test_logs_menu_item_update_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$menu_id      = wp_create_nav_menu( 'Item Update Menu ' . wp_generate_password( 4, false ) );
		$page_id      = $this->factory->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$menu_item_id = wp_update_nav_menu_item( $menu_id, 0, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-status'    => 'publish',
			'menu-item-title'     => 'Original Label',
		) );
		$this->assertIsInt( $menu_item_id );

		$count_before = $this->get_event_count();

		wp_update_nav_menu_item( $menu_id, $menu_item_id, array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page_id,
			'menu-item-status'    => 'publish',
			'menu-item-title'     => 'Renamed Label',
		) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI menu item update must produce exactly one log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleMenuLogger', $row['logger'] );
	}

	private function get_event_count(): int {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$db_table} WHERE logger = %s",
				'SimpleMenuLogger'
			)
		);
	}

	private function assert_context_has( array $context, string $key, string $value ): void {
		$this->assertContains(
			array( 'key' => $key, 'value' => $value ),
			$context,
			sprintf( 'Context should contain %s=%s', $key, $value )
		);
	}
}
