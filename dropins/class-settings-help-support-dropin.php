<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Admin_Pages;

/**
 * Dropin Name: Settings Help & Support
 * Dropin Description: Adds a Help & Support page with system information.
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Settings_Help_Support_Dropin extends Dropin {
	public const SUPPORT_PAGE_SLUG             = 'simple_history_help_support';
	public const SUPPORT_PAGE_GENERAL_TAB_SLUG = 'simple_history_help_support_general';

	/** @inheritdoc */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 10 );
		add_action( 'admin_menu', array( $this, 'add_tabs' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_help_page_scripts' ) );
	}

	/**
	 * Add submenu page for Help & Support.
	 */
	public function add_menu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		// Add using new menu_manager.
		$admin_page_location = Helpers::get_menu_page_location();

		// Main "Help & Support" page.
		$help_menu_page = ( new Menu_Page() )
			->set_page_title( _x( 'Simple History Help & Support', 'dashboard title name', 'simple-history' ) )
			->set_menu_slug( self::SUPPORT_PAGE_SLUG )
			->set_menu_title( _x( 'Help & Support', 'settings menu name', 'simple-history' ) )
			->set_icon( 'troubleshoot' )
			->set_callback( [ $this, 'output_help_and_support_page' ] )
			->set_redirect_to_first_child_on_load()
			->set_order( 5 );

		// Set different options depending on location.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$help_menu_page
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} elseif ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// If main page is shown as child to tools or dashboard then settings page is shown as child to settings main menu.
			$help_menu_page
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}

		$help_menu_page->add();
	}

	/**
	 * Add tabs to the settings page.
	 *
	 * Note: This page no longer has tabs since the Debug tab was merged into
	 * the Help & Support page. The structure is kept for menu system compatibility.
	 */
	public function add_tabs() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exists (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Simple_History::SETTINGS_MENU_PAGE_SLUG ) ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			// Add "Support" tab that redirects to Help & Support.
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

			// Help & Support tab content.
			( new Menu_Page() )
				->set_menu_title( _x( 'Help & Support', 'settings menu name', 'simple-history' ) )
				->set_page_title( _x( 'Help & Support', 'dashboard title name', 'simple-history' ) )
				->set_menu_slug( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_parent( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_callback( [ $this, 'output_help_page' ] )
				->add();
		} elseif ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// Add "Support" sub tab.
			( new Menu_Page() )
				->set_menu_title( _x( 'Support', 'settings menu name', 'simple-history' ) )
				->set_page_title( _x( 'Support', 'dashboard title name', 'simple-history' ) )
				->set_menu_slug( self::SUPPORT_PAGE_GENERAL_TAB_SLUG )
				->set_icon( 'settings' )
				->set_parent( self::SUPPORT_PAGE_SLUG )
				->set_callback( [ $this, 'output_help_page' ] )
				->set_order( 10 )
				->add();
		}
	}

	/**
	 * Enqueue scripts for the Help & Support page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_help_page_scripts( $hook_suffix ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking current page.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only checking current tab.
		$selected_sub_tab = isset( $_GET['selected-sub-tab'] ) ? sanitize_text_field( wp_unslash( $_GET['selected-sub-tab'] ) ) : '';

		// Load on Help & Support page - either via direct page access or via tab navigation.
		$is_help_page = $current_page === self::SUPPORT_PAGE_GENERAL_TAB_SLUG;
		$is_help_tab  = $selected_sub_tab === self::SUPPORT_PAGE_GENERAL_TAB_SLUG;
		$is_main_page = $current_page === self::SUPPORT_PAGE_SLUG;

		if ( ! $is_help_page && ! $is_help_tab && ! $is_main_page ) {
			return;
		}

		wp_enqueue_script(
			'simple-history-help-support-page',
			SIMPLE_HISTORY_DIR_URL . 'js/help-support-page.js',
			array(),
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_localize_script(
			'simple-history-help-support-page',
			'simpleHistoryHelpPage',
			array(
				'restUrl'   => rest_url( 'simple-history/v1/support-info' ),
				'healthUrl' => rest_url( 'simple-history/v1/support-info/health-check' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'checking'    => _x( 'Checking...', 'help page', 'simple-history' ),
					'apiOk'       => _x( 'Simple History is connected and working', 'help page', 'simple-history' ),
					'apiError'    => _x( 'Connection error:', 'help page', 'simple-history' ),
					'gathering'   => _x( 'Gathering data...', 'help page', 'simple-history' ),
					'gatherError' => _x( 'Error gathering data:', 'help page', 'simple-history' ),
					'copied'      => _x( 'Copied!', 'help page', 'simple-history' ),
					'copyError'   => _x( 'Failed to copy.', 'help page', 'simple-history' ),
					'refresh'     => _x( 'Refresh', 'help page', 'simple-history' ),
					'copyButton'  => _x( 'Copy to Clipboard', 'help page', 'simple-history' ),
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
	 * Output the help page content.
	 */
	public function output_help_page() {
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/settings-tab-help.php',
			false,
			array(
				'tables_info' => Helpers::required_tables_exist(),
			)
		);
	}
}
