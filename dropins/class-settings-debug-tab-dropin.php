<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Log_Query;
use Simple_History\Menu_Page;

/**
 * Dropin Name: Settings debug
 * Dropin Description: Adds a tab with debug information
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Settings_Debug_Tab_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
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

		$debug_menu_page = ( new Menu_Page() )
			->set_page_title( _x( 'Simple History Help & Support', 'dashboard title name', 'simple-history' ) )
			->set_menu_slug( 'simple_history_debug' )
			->set_capability( 'manage_options' )
			->set_callback( [ $this, 'output_debug_page' ] )
			->set_icon( 'troubleshoot' )
			->set_redirect_to_first_child_on_load();

		// Set different options depending on location.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$debug_menu_page
				->set_menu_title( _x( 'Help & Support', 'settings menu name', 'simple-history' ) )
				->set_parent( Simple_History::MENU_PAGE_SLUG );
		} else if ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// If main page is shown as child to tools or dashboard then settings page is shown as child to settings main menu.
			$debug_menu_page
				->set_menu_title( _x( 'Help & Support', 'settings menu name', 'simple-history' ) )
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}

		// Add Help & Support tab.
		$help_tab = ( new Menu_Page() )
			->set_page_title( _x( 'Help & Support', 'dashboard title name', 'simple-history' ) )
			->set_menu_title( _x( 'Help & Support', 'settings menu name', 'simple-history' ) )
			->set_menu_slug( 'simple_history_help_support' )
			->set_capability( 'manage_options' )
			->set_callback( [ $this, 'output_help_page' ] )
			->set_order( 10 )
			->set_parent( $debug_menu_page );

		// Add Debug tab.
		$debug_tab = ( new Menu_Page() )
			->set_page_title( _x( 'Debug', 'dashboard title name', 'simple-history' ) )
			->set_menu_title( _x( 'Debug', 'settings menu name', 'simple-history' ) )
			->set_menu_slug( 'simple_history_debug_tab' )
			->set_capability( 'manage_options' )
			->set_callback( [ $this, 'output_debug_page' ] )
			->set_order( 20 )
			->set_parent( $debug_menu_page );

		$debug_menu_page->add();
		$help_tab->add();
		$debug_tab->add();
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
				'instantiated_loggers' => $this->simple_history->get_instantiated_loggers(),
				'instantiated_dropins' => $this->simple_history->get_instantiated_dropins(),
				'instantiated_services' => $this->simple_history->get_instantiated_services(),
				'events_table_name' => $this->simple_history->get_events_table_name(),
				'simple_history_instance' => $this->simple_history,
				'wpdb' => $GLOBALS['wpdb'],
				'plugins' => get_plugins(),
				'dropins' => get_dropins(),
				'tables_info' => Helpers::required_tables_exist(),
				'table_size_result' => Helpers::get_db_table_stats(),
				'db_engine' => Log_Query::get_db_engine(),
			)
		);
	}
}
