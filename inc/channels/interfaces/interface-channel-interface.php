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

namespace Simple_History\Channels\Interfaces;

// Load the real interface from its new location.
require_once dirname( __DIR__ ) . '/interface-channel-interface.php';

if ( ! interface_exists( 'Simple_History\Channels\Interfaces\Channel_Interface', false ) ) {
	class_alias( 'Simple_History\Channels\Channel_Interface', 'Simple_History\Channels\Interfaces\Channel_Interface' );
}
