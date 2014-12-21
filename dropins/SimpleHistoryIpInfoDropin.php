<?php

/*
Dropin Name: IP Info
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryIpInfoDropin {

	private $sh;

	function __construct($sh) {

		$this->sh = $sh;

		// Since it's not quite done yet, it's for da devs only for now
		if ( ! defined("SIMPLE_HISTORY_DEV") || ! SIMPLE_HISTORY_DEV ) {
			return;
		}

		add_action("simple_history/enqueue_admin_scripts", array($this, "enqueue_admin_scripts"));

	}

	public function enqueue_admin_scripts() {

		$file_url = plugin_dir_url(__FILE__);
		
		wp_enqueue_script("simple_history_IpInfoDropin", $file_url . "SimpleHistoryIpInfoDropin.js", array("jquery"), SimpleHistory::VERSION, true);

		wp_enqueue_style("simple_history_IpInfoDropin", $file_url . "SimpleHistoryIpInfoDropin.css", null, SimpleHistory::VERSION);

	}

} // end class

