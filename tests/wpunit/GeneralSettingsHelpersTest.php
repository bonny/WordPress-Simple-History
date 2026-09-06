<?php

use Simple_History\Helpers;

/**
 * Tests for the three Helpers methods that back the "General" settings panel:
 * Show on dashboard, Show in admin bar, History menu position.
 *
 * Covers default values, stored values, and both filter chains for each option.
 */
class GeneralSettingsHelpersTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();

		delete_option( 'simple_history_show_on_dashboard' );
		delete_option( 'simple_history_show_in_admin_bar' );
		delete_option( 'simple_history_menu_page_location' );
	}

	public function tearDown(): void {
		delete_option( 'simple_history_show_on_dashboard' );
		delete_option( 'simple_history_show_in_admin_bar' );
		delete_option( 'simple_history_menu_page_location' );

		parent::tearDown();
	}

	// --- setting_show_on_dashboard ------------------------------------------

	public function test_show_on_dashboard_defaults_to_true() {
		$this->assertTrue( Helpers::setting_show_on_dashboard() );
	}

	public function test_show_on_dashboard_honors_stored_zero() {
		update_option( 'simple_history_show_on_dashboard', 0 );

		$this->assertFalse( Helpers::setting_show_on_dashboard() );
	}

	public function test_show_on_dashboard_honors_stored_one() {
		update_option( 'simple_history_show_on_dashboard', 1 );

		$this->assertTrue( Helpers::setting_show_on_dashboard() );
	}

	public function test_show_on_dashboard_legacy_filter_can_force_off() {
		add_filter( 'simple_history_show_on_dashboard', '__return_zero' );

		$this->assertFalse( Helpers::setting_show_on_dashboard() );

		remove_filter( 'simple_history_show_on_dashboard', '__return_zero' );
	}

	public function test_show_on_dashboard_new_filter_can_force_off() {
		add_filter( 'simple_history/show_on_dashboard', '__return_zero' );

		$this->assertFalse( Helpers::setting_show_on_dashboard() );

		remove_filter( 'simple_history/show_on_dashboard', '__return_zero' );
	}

	// --- setting_show_in_admin_bar ------------------------------------------

	public function test_show_in_admin_bar_defaults_to_true() {
		$this->assertTrue( Helpers::setting_show_in_admin_bar() );
	}

	public function test_show_in_admin_bar_honors_stored_zero() {
		update_option( 'simple_history_show_in_admin_bar', 0 );

		$this->assertFalse( Helpers::setting_show_in_admin_bar() );
	}

	public function test_show_in_admin_bar_honors_stored_one() {
		update_option( 'simple_history_show_in_admin_bar', 1 );

		$this->assertTrue( Helpers::setting_show_in_admin_bar() );
	}

	public function test_show_in_admin_bar_legacy_filter_can_force_off() {
		add_filter( 'simple_history_show_in_admin_bar', '__return_zero' );

		$this->assertFalse( Helpers::setting_show_in_admin_bar() );

		remove_filter( 'simple_history_show_in_admin_bar', '__return_zero' );
	}

	public function test_show_in_admin_bar_new_filter_can_force_off() {
		add_filter( 'simple_history/show_in_admin_bar', '__return_zero' );

		$this->assertFalse( Helpers::setting_show_in_admin_bar() );

		remove_filter( 'simple_history/show_in_admin_bar', '__return_zero' );
	}

	// --- get_menu_page_location ---------------------------------------------

	public function test_menu_page_location_defaults_to_top_and_persists() {
		$this->assertSame( 'top', Helpers::get_menu_page_location() );

		// Side effect: the helper writes the default back to the DB so the
		// option auto-loads. Verify that it actually did persist.
		$this->assertSame( 'top', get_option( 'simple_history_menu_page_location' ) );
	}

	/**
	 * @dataProvider provide_valid_locations
	 */
	public function test_menu_page_location_honors_stored_value( $location ) {
		update_option( 'simple_history_menu_page_location', $location );

		$this->assertSame( $location, Helpers::get_menu_page_location() );
	}

	public function provide_valid_locations() {
		return [
			'top'              => [ 'top' ],
			'bottom'           => [ 'bottom' ],
			'inside_tools'     => [ 'inside_tools' ],
			'inside_dashboard' => [ 'inside_dashboard' ],
		];
	}

	public function test_menu_page_location_filter_overrides_stored_value() {
		update_option( 'simple_history_menu_page_location', 'top' );

		add_filter(
			'simple_history/admin_menu_location',
			function () {
				return 'inside_tools';
			}
		);

		$this->assertSame( 'inside_tools', Helpers::get_menu_page_location() );

		remove_all_filters( 'simple_history/admin_menu_location' );
	}
}
