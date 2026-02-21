<?php
/**
 * Plugin Name: Simple History
 * Plugin URI: https://simple-history.com
 * Text Domain: simple-history
 * Description: Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.
 * Version: 5.23.1
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Pär Thernström
 * Author URI: https://simple-history.com/
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
	|| version_compare( $GLOBALS['wp_version'], '6.3', '<' )
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
define( 'SIMPLE_HISTORY_VERSION', '5.23.1' );

/**
 * Filesystem path to plugin directory.
 * Example: /var/www/wp-content/plugins/simple-history/
 */
define( 'SIMPLE_HISTORY_PATH', plugin_dir_path( __FILE__ ) );

define( 'SIMPLE_HISTORY_BASENAME', plugin_basename( __FILE__ ) );

/**
 * URL to plugin directory.
 * Example: http://example.com/wp-content/plugins/simple-history/
 */
define( 'SIMPLE_HISTORY_DIR_URL', plugin_dir_url( __FILE__ ) );

define( 'SIMPLE_HISTORY_FILE', __FILE__ );

/**
 * The API URL to check for updates.
 * This should be unique to this plugin and point to a remote server
 * running the Lemon Squeezy plugin.
 */
define( 'SIMPLE_HISTORY_LICENCES_API_URL', 'https://simple-history.com/wp-json/lsq/v1' );

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
$sh_loader->add_namespace( 'Simple_History\Services', SIMPLE_HISTORY_PATH . 'inc/services/wp-cli-commands' );
$sh_loader->add_namespace( 'Simple_History\Channels', SIMPLE_HISTORY_PATH . 'inc/channels' );

// Backward compatibility aliases for moved channel classes.
// In version 5.23.0, channel interfaces and formatters were moved from subdirectories
// to the main channels directory to fix autoloader issues on some hosts (like WPEngine)
// where directory-level caching caused "class not found" errors for files in new subdirectories.
// These aliases ensure code using the old namespaces continues to work.
class_alias( 'Simple_History\Channels\Channel_Interface', 'Simple_History\Channels\Interfaces\Channel_Interface' );
class_alias( 'Simple_History\Channels\Formatter_Interface', 'Simple_History\Channels\Formatters\Formatter_Interface' );
class_alias( 'Simple_History\Channels\Formatter', 'Simple_History\Channels\Formatters\Formatter' );
class_alias( 'Simple_History\Channels\Human_Readable_Formatter', 'Simple_History\Channels\Formatters\Human_Readable_Formatter' );

// Register autoloader for deprecated classes - loaded only when actually used.
spl_autoload_register(
	function ( $class_name ) {
		// Only handle our deprecated classes, let other autoloaders handle the rest.
		$deprecated_classes = array(
			'SimpleHistory'             => __DIR__ . '/inc/deprecated/class-simplehistory.php',
			'SimpleLogger'              => __DIR__ . '/inc/deprecated/class-simplelogger.php',
			'SimpleLoggerLogInitiators' => __DIR__ . '/inc/deprecated/class-simpleloggerloginitiators.php',
			'SimpleLoggerLogLevels'     => __DIR__ . '/inc/deprecated/class-simpleloggerloglevels.php',
			'SimpleHistoryLogQuery'     => __DIR__ . '/inc/deprecated/class-simplehistorylogquery.php',
		);

		// Only handle classes we know about.
		if ( ! isset( $deprecated_classes[ $class_name ] ) ) {
			return; // Let other autoloaders handle it.
		}

		// Check class doesn't already exist and file exists.
		if ( class_exists( $class_name, false ) || ! file_exists( $deprecated_classes[ $class_name ] ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Safe: array is hardcoded above.
		require_once $deprecated_classes[ $class_name ];
	}
);

// Create singleton instance of Simple History.
// This runs constructor that calls init method.
Simple_History\Simple_History::get_instance();
