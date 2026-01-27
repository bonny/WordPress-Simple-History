<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Admin_Pages;

/**
 * Dropin Name: Settings debug
 * Dropin Description: Adds a tab with Help & Support and Debug information.
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Settings_Debug_Tab_Dropin extends Dropin {
	public const SUPPORT_PAGE_SLUG             = 'simple_history_help_support';
	public const SUPPORT_PAGE_GENERAL_TAB_SLUG = 'simple_history_help_support_general';
	public const SUPPORT_PAGE_DEBUG_TAB_SLUG   = 'simple_history_help_support_debug';

	/** @inheritdoc */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'add_tabs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_debug_page_scripts' ) );
	}

	/**
	 * Add submenu page for debug.
	 */
	public function add_menu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		// Add using new menu_manager.
		$admin_page_location = Helpers::get_menu_page_location();

		// Main "Help & Support" page.
		$debug_menu_page = ( new Menu_Page() )
			->set_page_title( _x( 'Simple History Help & Support', 'dashboard title name', 'simple-history' ) )
			->set_menu_slug( self::SUPPORT_PAGE_SLUG )
			->set_menu_title( _x( 'Help & Support', 'settings menu name', 'simple-history' ) )
			->set_icon( 'troubleshoot' )
			->set_callback( [ $this, 'output_help_and_support_page' ] )
			->set_redirect_to_first_child_on_load()
			->set_order( 5 );

		// Set different options depending on location.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$debug_menu_page
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} elseif ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// If main page is shown as child to tools or dashboard then settings page is shown as child to settings main menu.
			$debug_menu_page
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}

		$debug_menu_page->add();
	}

	/**
	 * Add tabs to the settings page.
	 */
	public function add_tabs() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exists (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Simple_History::SETTINGS_MENU_PAGE_SLUG ) ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			// THIS WORKS.

			// Add first "Support" tab.
			// This tab is not needed when inside tools or dashboard.
			// User will be redirected to the next, first child tab.
			( new Menu_Page() )
				->set_menu_title( _x( 'Support', 'settings menu name', 'simple-history' ) )
				->set_page_title( _x( 'Support', 'dashboard title name', 'simple-history' ) )
				->set_menu_slug( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_icon( 'settings' )
				->set_parent( self::SUPPORT_PAGE_SLUG )
				->set_callback( [ $this, 'output_help_page' ] )
				->set_order( 10 )
				->set_redirect_to_first_child_on_load()
				->add();

			// Add first tab, this is the tab that will be shown first
			// and the tab that user will be redirected to from the main tab above.
			( new Menu_Page() )
				->set_menu_title( _x( 'Help & Support', 'settings menu name', 'simple-history' ) )
				->set_page_title( _x( 'Help & Support', 'dashboard title name', 'simple-history' ) )
				->set_menu_slug( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_parent( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_callback( [ $this, 'output_help_page' ] )
				->add();

			// Add second "Debug" tab.
			( new Menu_Page() )
				->set_menu_title( _x( 'Debug', 'settings menu name', 'simple-history' ) )
				->set_page_title( _x( 'Debug', 'dashboard title name', 'simple-history' ) )
				->set_menu_slug( self::SUPPORT_PAGE_DEBUG_TAB_SLUG )
				->set_callback( [ $this, 'output_debug_page' ] )
				->set_parent( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_order( 20 )
				->add();
		} elseif ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// Add first "Support" sub tab.
			// User will be redirected to the next, first child tab.
			( new Menu_Page() )
				->set_menu_title( _x( 'Support', 'settings menu name', 'simple-history' ) )
				->set_page_title( _x( 'Support', 'dashboard title name', 'simple-history' ) )
				->set_menu_slug( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_icon( 'settings' )
				->set_parent( self::SUPPORT_PAGE_SLUG )
				->set_callback( [ $this, 'output_help_page' ] )
				->set_order( 10 )
				->set_redirect_to_first_child_on_load()
				->add();

			// Add second "Debug" tab.
			( new Menu_Page() )
				->set_menu_title( _x( 'Debug', 'settings menu name', 'simple-history' ) )
				->set_page_title( _x( 'Debug', 'dashboard title name', 'simple-history' ) )
				->set_menu_slug( self::SUPPORT_PAGE_DEBUG_TAB_SLUG )
				->set_callback( [ $this, 'output_debug_page' ] )
				->set_parent( self::SUPPORT_PAGE_SLUG )
				->set_order( 20 )
				->add();
		}
	}

	/**
	 * Enqueue scripts for the debug page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_debug_page_scripts( $hook_suffix ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking current page.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking current tab.
		$selected_sub_tab = isset( $_GET['selected-sub-tab'] ) ? sanitize_text_field( wp_unslash( $_GET['selected-sub-tab'] ) ) : '';

		// Load on the debug tab - either via direct page access or via tab navigation.
		$is_debug_page = $current_page === self::SUPPORT_PAGE_DEBUG_TAB_SLUG;
		$is_debug_tab  = $selected_sub_tab === self::SUPPORT_PAGE_DEBUG_TAB_SLUG;

		if ( ! $is_debug_page && ! $is_debug_tab ) {
			return;
		}

		wp_enqueue_script(
			'simple-history-debug-page',
			SIMPLE_HISTORY_DIR_URL . 'js/debug-page.js',
			array(),
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_localize_script(
			'simple-history-debug-page',
			'simpleHistoryDebugPage',
			array(
				'restUrl'   => rest_url( 'simple-history/v1/support-info' ),
				'healthUrl' => rest_url( 'simple-history/v1/support-info/health-check' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'checking'    => _x( 'Checking...', 'debug dropin', 'simple-history' ),
					'apiOk'       => _x( 'REST API is working correctly.', 'debug dropin', 'simple-history' ),
					'apiError'    => _x( 'REST API error:', 'debug dropin', 'simple-history' ),
					'gathering'   => _x( 'Gathering data...', 'debug dropin', 'simple-history' ),
					'gatherError' => _x( 'Error gathering data:', 'debug dropin', 'simple-history' ),
					'copied'      => _x( 'Copied!', 'debug dropin', 'simple-history' ),
					'copyError'   => _x( 'Failed to copy.', 'debug dropin', 'simple-history' ),
					'reloadData'  => _x( 'Reload Data', 'debug dropin', 'simple-history' ),
				),
			)
		);
	}

	/**
	 * Output the help and support page.
	 */
	public function output_help_and_support_page() {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();
	}

	/**
	 * Output the help tab content.
	 */
	public function output_help_page() {
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/settings-tab-help.php',
			false,
			array()
		);
	}

	/**
	 * Output the debug tab content.
	 */
	public function output_debug_page() {
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/settings-tab-debug.php',
			false,
			array(
				'tables_info' => Helpers::required_tables_exist(),
			)
		);
	}
}
