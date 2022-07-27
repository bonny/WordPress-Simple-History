<?php

namespace SimpleHistory\Dropins;

use SimpleHistory\SimpleHistory;
use SimpleHistory\LogQuery;

/**
 * Dropin Name: Settings debug
 * Dropin Description: Adds a tab with debug information
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */

class SimpleHistorySettingsDebugDropin extends Dropin {
	public function __construct( $sh ) {

		$this->simple_history = $sh;

		$this->simple_history->registerSettingsTab(
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
