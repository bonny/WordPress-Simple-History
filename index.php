<?php

/**
 * Plugin Name: Simple History
 * Plugin URI: http://simple-history.com
 * Text Domain: simple-history
 * Description: Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.
 * Version: 4.5.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
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

if (
	version_compare( phpversion(), '7.4', '<' )
	|| version_compare( $GLOBALS['wp_version'], '6.1', '<' )
) {
	// User is running to old version of php, add admin notice about that.
	require_once __DIR__ . '/inc/oldversions.php';
	add_action( 'admin_notices', 'simple_history_old_version_admin_notice' );

	return;
}

/**
 * Register function that is called when plugin is installed
 *
 * @TODO: make activation multi site aware, as in https://github.com/scribu/wp-proper-network-activation
 * register_activation_hook( trailingslashit(WP_PLUGIN_DIR) . trailingslashit( plugin_basename(__DIR__) ) . "index.php" , array("SimpleHistory", "on_plugin_activate" ) );
 */
define( 'SIMPLE_HISTORY_VERSION', '4.5.0' );
define( 'SIMPLE_HISTORY_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIMPLE_HISTORY_BASENAME', plugin_basename( __FILE__ ) );
define( 'SIMPLE_HISTORY_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'SIMPLE_HISTORY_FILE', __FILE__ );

/** Load required files. */
require_once __DIR__ . '/inc/class-autoloader.php';
require_once __DIR__ . '/inc/global-helpers.php';

/** Boot up. */
$sh_loader = new Simple_History\Autoloader();
$sh_loader->register();
$sh_loader->add_namespace( 'Simple_History', SIMPLE_HISTORY_PATH );
$sh_loader->add_namespace( 'Simple_History', SIMPLE_HISTORY_PATH . 'inc/' );
$sh_loader->add_namespace( 'Simple_History\Event_Details', SIMPLE_HISTORY_PATH . 'inc/event-details' );
$sh_loader->add_namespace( 'Simple_History\Services', SIMPLE_HISTORY_PATH . 'inc/services' );

// Load code for old, deprecated things, that does not use autoloader.
require_once __DIR__ . '/inc/deprecated/class-simplehistory.php';
require_once __DIR__ . '/inc/deprecated/class-simplelogger.php';
require_once __DIR__ . '/inc/deprecated/class-simpleloggerloginitiators.php';
require_once __DIR__ . '/inc/deprecated/class-simpleloggerloglevels.php';
require_once __DIR__ . '/inc/deprecated/class-simplehistorylogquery.php';

// Create singleton instance of Simple History.
// This runs constructor that calls init method.
Simple_History\Simple_History::get_instance();
