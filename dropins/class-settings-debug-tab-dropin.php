<?php

namespace Simple_History\Dropins;

/**
 * Dropin Name: Settings debug
 * Dropin Description: Adds a tab with debug information
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Settings_Debug_Tab_Dropin extends Dropin {
	public function loaded() {
		$this->simple_history->register_settings_tab(
			array(
				'slug' => 'debug',
				'name' => __( 'Debug', 'simple-history' ),
				'function' => array( $this, 'output' ),
			)
		);
	}

	public function output() {
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/template-settings-tab-debug.php',
			false,
			array(
				'instantiated_loggers' => $this->simple_history->get_instantiated_loggers(),
				'instantiated_dropins' => $this->simple_history->get_instantiated_dropins(),
				'instantiated_services' => $this->simple_history->get_instantiated_services(),
				'events_table_name' => $this->simple_history->get_events_table_name(),
				'simple_history_instance' => $this->simple_history,
				'wpdb' => $GLOBALS['wpdb'],
			)
		);
	}
}
