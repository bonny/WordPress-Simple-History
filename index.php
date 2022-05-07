<?php

/**
 * Plugin Name: Simple History
 * Plugin URI: http://simple-history.com
 * Text Domain: simple-history
 * Domain Path: /languages
 * Description: Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.
 * Version: 3.3.0
 * Author: Pär Thernström
 * Author URI: http://simple-history.com/
 * License: GPL2

 *
 * @package Simple History
 */

/**
 * Copyright 2015  Pär Thernström (email: par.thernstrom@gmail.com)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if ( ! defined( 'WPINC' ) ) {
	die();
}

// Plugin requires at least WordPress version "4.5.1", because usage of functions like wp_get_raw_referer.
// true if version ok, false if too old version.
$ok_wp_version = version_compare( $GLOBALS['wp_version'], '5.2', '>=' );
$ok_php_version = version_compare( phpversion(), '5.6', '>=' );

if ( $ok_php_version && $ok_wp_version ) {
	/**
	 * Register function that is called when plugin is installed
	 *
	 * @TODO: make activation multi site aware, as in https://github.com/scribu/wp-proper-network-activation
	 * register_activation_hook( trailingslashit(WP_PLUGIN_DIR) . trailingslashit( plugin_basename(__DIR__) ) . "index.php" , array("SimpleHistory", "on_plugin_activate" ) );
	 */
	define( 'SIMPLE_HISTORY_VERSION', '3.3.0' );
	define( 'SIMPLE_HISTORY_PATH', plugin_dir_path( __FILE__ ) );
	define( 'SIMPLE_HISTORY_BASENAME', plugin_basename( __FILE__ ) );
	define( 'SIMPLE_HISTORY_DIR_URL', plugin_dir_url( __FILE__ ) );
	define( 'SIMPLE_HISTORY_FILE', __FILE__ );

	/** Load required files */
	require_once __DIR__ . '/inc/SimpleHistory.php';
	require_once __DIR__ . '/inc/SimpleHistoryLogQuery.php';
	require_once __DIR__ . '/inc/helpers.php';

	/** Boot up */
	SimpleHistory::get_instance();
} else {
	// User is running to old version of php, add admin notice about that.
	require_once __DIR__ . '/inc/oldversions.php';
	add_action( 'admin_notices', 'simple_history_old_version_admin_notice' );
} // End if().
