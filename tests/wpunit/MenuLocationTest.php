<?php

use Simple_History\Helpers;
use Simple_History\Menu_Manager;
use Simple_History\Menu_Page;
use Simple_History\Simple_History;

/**
 * Contract test for the four "History menu position" values:
 * top, bottom, inside_tools, inside_dashboard.
 *
 * Every service and dropin that registers a menu page branches on
 * Helpers::get_menu_page_location(). It's easy for one branch to be
 * updated and another to lag behind. For each of the four locations this
 * test fires admin_menu and asserts the complete tree shape — every
 * service/dropin's main sub-page resolves to the expected parent slug and
 * the top-level main page lands in the expected WP menu slot.
 */
class MenuLocationTest extends \Codeception\TestCase\WPTestCase {

	/** @var int */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );

		set_current_screen( 'dashboard' );
	}

	public function tearDown(): void {
		delete_option( 'simple_history_menu_page_location' );

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	public function test_top_location_registers_full_tree() {
		$this->register_menu_with_location( 'top' );

		$this->assert_main_page_location( 'menu_top' );
		$this->assert_subpages_parents(
			Simple_History::MENU_PAGE_SLUG,
			$this->expected_subpage_slugs_under_main()
		);
		$this->assert_event_log_subpage_exists();
	}

	public function test_bottom_location_registers_full_tree() {
		$this->register_menu_with_location( 'bottom' );

		$this->assert_main_page_location( 'menu_bottom' );
		$this->assert_subpages_parents(
			Simple_History::MENU_PAGE_SLUG,
			$this->expected_subpage_slugs_under_main()
		);
		$this->assert_event_log_subpage_exists();
	}

	public function test_inside_tools_location_registers_full_tree() {
		$this->register_menu_with_location( 'inside_tools' );

		$this->assert_main_page_location( 'tools' );
		$this->assert_subpages_parents(
			Simple_History::SETTINGS_MENU_PAGE_SLUG,
			$this->expected_subpage_slugs_under_settings()
		);
		$this->assert_no_event_log_subpage();
	}

	public function test_inside_dashboard_location_registers_full_tree() {
		$this->register_menu_with_location( 'inside_dashboard' );

		$this->assert_main_page_location( 'dashboard' );
		$this->assert_subpages_parents(
			Simple_History::SETTINGS_MENU_PAGE_SLUG,
			$this->expected_subpage_slugs_under_settings()
		);
		$this->assert_no_event_log_subpage();
	}

	// --- expected shape ------------------------------------------------------

	/**
	 * Sub-pages whose parent should be MENU_PAGE_SLUG when location is top/bottom.
	 * One entry per service or dropin that registers a page in the main menu tree.
	 */
	private function expected_subpage_slugs_under_main() {
		return [
			Simple_History::SETTINGS_MENU_PAGE_SLUG, // class-setup-settings-page
			'simple_history_stats_page',             // class-stats-service
			'simple_history_help_support',           // class-settings-help-support-dropin (SUPPORT_PAGE_SLUG)
			'simple_history_tools',                  // class-tools-menu-dropin (MENU_SLUG)
		];
	}

	/**
	 * Sub-pages whose parent should be SETTINGS_MENU_PAGE_SLUG when location
	 * is inside_tools / inside_dashboard. The main page goes under tools or
	 * dashboard directly; everything else lives under the settings page.
	 */
	private function expected_subpage_slugs_under_settings() {
		return [
			'simple_history_stats_page',             // class-stats-service
			'simple_history_help_support',           // class-settings-help-support-dropin
			'simple_history_tools',                  // class-tools-menu-dropin
		];
	}

	// --- helpers -------------------------------------------------------------

	/**
	 * Reset captured menu state and fire admin_menu with the given location.
	 *
	 * Each test runs in the same process, so we explicitly clear the
	 * Menu_Manager's internal pages array (via reflection) and the WP $menu
	 * / $submenu globals before re-firing admin_menu.
	 */
	private function register_menu_with_location( $location ) {
		update_option( 'simple_history_menu_page_location', $location );

		$this->reset_menu_manager();
		$this->reset_wp_menu_globals();

		do_action( 'admin_menu' );
	}

	private function reset_menu_manager() {
		$menu_manager = Simple_History::get_instance()->get_menu_manager();

		$this->assertInstanceOf( Menu_Manager::class, $menu_manager );

		$reflection = new \ReflectionObject( $menu_manager );
		$pages      = $reflection->getProperty( 'pages' );
		$pages->setAccessible( true );
		$pages->setValue( $menu_manager, [] );
	}

	private function reset_wp_menu_globals() {
		$GLOBALS['menu']               = [];
		$GLOBALS['submenu']            = [];
		$GLOBALS['_wp_real_parent_file']    = [];
		$GLOBALS['_wp_submenu_nopriv']      = [];
		$GLOBALS['_registered_pages']       = [];
		$GLOBALS['admin_page_hooks']        = [];
	}

	private function get_menu_manager() {
		return Simple_History::get_instance()->get_menu_manager();
	}

	private function assert_main_page_location( $expected_wp_location ) {
		$main_page = $this->get_menu_manager()->get_page_by_slug( Simple_History::MENU_PAGE_SLUG );

		$this->assertInstanceOf(
			Menu_Page::class,
			$main_page,
			'Main page should always be registered.'
		);

		$this->assertSame(
			$expected_wp_location,
			$main_page->get_location(),
			sprintf(
				'Main page should land in WP location "%s".',
				$expected_wp_location
			)
		);
	}

	/**
	 * Each slug should be registered and have the given parent slug.
	 */
	private function assert_subpages_parents( $expected_parent_slug, array $slugs ) {
		$menu_manager = $this->get_menu_manager();

		foreach ( $slugs as $slug ) {
			$page = $menu_manager->get_page_by_slug( $slug );

			$this->assertInstanceOf(
				Menu_Page::class,
				$page,
				sprintf( 'Sub-page "%s" should be registered.', $slug )
			);

			$this->assertSame(
				$expected_parent_slug,
				$page->get_parent_menu_slug(),
				sprintf(
					'Sub-page "%s" should have parent "%s" but had "%s".',
					$slug,
					$expected_parent_slug,
					(string) $page->get_parent_menu_slug()
				)
			);
		}
	}

	/**
	 * The "Event Log" subpage is only added for top/bottom locations, where
	 * the main menu page itself doesn't render the log — its first child does.
	 */
	private function assert_event_log_subpage_exists() {
		$page = $this->get_menu_manager()->get_page_by_slug( Simple_History::VIEW_EVENTS_PAGE_SLUG );

		$this->assertInstanceOf(
			Menu_Page::class,
			$page,
			'Event Log sub-page should exist when location is top or bottom.'
		);

		$this->assertSame(
			Simple_History::MENU_PAGE_SLUG,
			$page->get_parent_menu_slug(),
			'Event Log sub-page should be parented to the main menu page.'
		);
	}

	private function assert_no_event_log_subpage() {
		$page = $this->get_menu_manager()->get_page_by_slug( Simple_History::VIEW_EVENTS_PAGE_SLUG );

		$this->assertNull(
			$page,
			'Event Log sub-page should not exist when location is inside_tools or inside_dashboard.'
		);
	}
}
