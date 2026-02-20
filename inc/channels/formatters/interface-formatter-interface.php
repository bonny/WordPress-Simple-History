<?php
/**
 * Backward compatibility stub.
 *
 * This interface was moved to Simple_History\Channels in version 5.23.0.
 * This file exists so that code from older versions (loaded in memory
 * during a plugin update) can still autoload from the old path.
 *
 * @since 5.24.0
 */

namespace Simple_History\Channels\Formatters;

// Load the real interface from its new location.
require_once dirname( __DIR__ ) . '/interface-formatter-interface.php';

// This file intentionally has no class/interface declaration.
// The class_alias in index.php handles the namespace mapping,
// and during updates the real interface is now loaded so PHP
// can resolve the alias when index.php runs in the new version.
// For mid-update scenarios where OLD index.php (no aliases) is
// in memory, we need to create the alias here too.
if ( ! interface_exists( 'Simple_History\Channels\Formatters\Formatter_Interface', false ) ) {
	class_alias( 'Simple_History\Channels\Formatter_Interface', 'Simple_History\Channels\Formatters\Formatter_Interface' );
}
