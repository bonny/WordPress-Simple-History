<?php

/*
Dropin Name: Settings debug
Dropin Description: Adds a tab with debug information
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

defined( 'ABSPATH' ) || die();

class SimpleHistorySettingsDebugDropin {

	private $sh;

	public function __construct( $sh ) {

		$this->sh = $sh;

		$this->sh->registerSettingsTab(
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
