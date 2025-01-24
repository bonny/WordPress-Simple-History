<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Log_Query;

/**
 * Dropin Name: Settings debug
 * Dropin Description: Adds a tab with debug information
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Settings_Debug_Tab_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
	}

	/**
	 * Add submenu page for debug.
	 */
	public function add_submenu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		add_submenu_page(
			Simple_History::MENU_PAGE_SLUG,
			__( 'Debug', 'simple-history' ),
			__( 'Debug', 'simple-history' ),
			'manage_options',
			'simple_history_debug',
			array( $this, 'output_debug_page' ),
			50
		);
	}

	/**
	 * Output the tab.
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
