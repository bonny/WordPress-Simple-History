<?php
/*
Plugin Name: Simple History
Plugin URI: http://simple-history.com
Description: Get a log/history/audit log/version history of the changes made by users in WordPress.
Version: 2
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


/** Load required files */
require_once(__DIR__ . "/SimpleHistory.php");
require_once(__DIR__ . "/SimpleHistoryLogQuery.php");

/**
 * Register function that is called when plugin is installed
 *
 * @TODO: check that this works with wp 3.9 that have symlink support
 * @TODO: make activation multi site aware, as in https://github.com/scribu/wp-proper-network-activation
 */
register_activation_hook( trailingslashit(WP_PLUGIN_DIR) . trailingslashit( plugin_basename(__DIR__) ) . "index.php" , array("SimpleHistory", "on_plugin_activate" ) );

/**
 * Helper function with same name as the SimpleLogger-class
 *
 * Makes call like this possible:
 * SimpleLogger()->info("This is a message sent to the log");
 */
function SimpleLogger() {
	return new SimpleLogger();
}

/** Boot up */
$GLOBALS["simple_history"] = new SimpleHistory();

// Test logging
// Example usage
include( __DIR__ . "/log-tests.php" );
