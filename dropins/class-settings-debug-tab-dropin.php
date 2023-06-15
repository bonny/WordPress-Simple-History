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
		include SIMPLE_HISTORY_PATH . 'templates/template-settings-tab-debug.php';
	}
}
