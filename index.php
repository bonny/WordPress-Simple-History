<?php
/*
Plugin Name: Simple History
Plugin URI: http://simple-history.com
Description: Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.
Version: 2.0.16
Author: Pär Thernström
Author URI: http://simple-history.com/
License: GPL2
*/

/*  Copyright 2014  Pär Thernström (email: par.thernstrom@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( version_compare( phpversion(), "5.3", ">=") ) {

	/** Load required files */
	require_once(__DIR__ . "/SimpleHistory.php");
	require_once(__DIR__ . "/SimpleHistoryLogQuery.php");
	require_once(__DIR__ . "/SimpleHistoryFunctions.php");

	/**
	 * Register function that is called when plugin is installed
	 *
	 * @TODO: make activatigon multi site aware, as in https://github.com/scribu/wp-proper-network-activation
	 */
	// register_activation_hook( trailingslashit(WP_PLUGIN_DIR) . trailingslashit( plugin_basename(__DIR__) ) . "index.php" , array("SimpleHistory", "on_plugin_activate" ) );

	/** Boot up */
	$GLOBALS["simple_history"] = new SimpleHistory();

} else {

	// user is running to old version of php, add admin notice about that
	add_action( 'admin_notices', 'simple_history_old_version_admin_notice' );

	function simple_history_old_version_admin_notice() {
		?>
		<div class="updated error">
			<p><?php
				printf(
					__( 'Simple History is a great plugin, but to use it your server must have at least PHP 5.3 installed (you have version %s).', 'simple-history' ),
					phpversion()
				);
				?></p>
		</div>
		<?php

	}

}

// Test log cron things
/*
wp_schedule_event( time(), "hourly", "simple_history_cron_testhook");
*/
/*
wp_clear_scheduled_hook("simple_history_cron_testhook");
add_action( 'simple_history_cron_testhook', 'simple_history_cron_testhook_function' );
function simple_history_cron_testhook_function() {
	SimpleLogger()->info("This is a message inside a cron function");
}
*/

/*
add_action("init", function() {

	global $wp_current_filter;

	$doing_cron = get_transient( 'doing_cron' );
	$const_doing_cron = defined('DOING_CRON') && DOING_CRON;

	if ($const_doing_cron) {

		$current_filter = current_filter();

		SimpleLogger()->info("This is a message inside init, trying to log crons", array(
			"doing_cron" => simpleHistory::json_encode($doing_cron),
			"current_filter" => $current_filter,
			"wp_current_filter" => $wp_current_filter,
			"wp_current_filter" => simpleHistory::json_encode( $wp_current_filter ),
			"const_doing_cron" => simpleHistory::json_encode($const_doing_cron)
		));

	}

}, 100);
*/


/*
add_action("init", function() {

	#SimpleLogger()->info("This is a regular info message" . time());

}, 100);
// */
