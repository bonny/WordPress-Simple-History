<?php

/*
Dropin Name: Settings debug
Dropin Description: Adds a tab with debug information
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

defined( 'ABSPATH' ) or die();

class SimpleHistorySettingsDebugDropin {


	private $sh;

	public function __construct( $sh ) {

		$this->sh = $sh;

		// How do we register this to the settings array?
		$sh->registerSettingsTab(
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
