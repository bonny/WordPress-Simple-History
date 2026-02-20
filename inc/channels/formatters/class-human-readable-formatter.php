<?php
/**
 * Backward compatibility stub.
 *
 * This class was moved to Simple_History\Channels in version 5.23.0.
 * This file exists so that code from older versions (loaded in memory
 * during a plugin update) can still autoload from the old path.
 *
 * @since 5.24.0
 */

namespace Simple_History\Channels\Formatters;

// Load the real class from its new location.
require_once dirname( __DIR__ ) . '/class-human-readable-formatter.php';

if ( ! class_exists( 'Simple_History\Channels\Formatters\Human_Readable_Formatter', false ) ) {
	class_alias( 'Simple_History\Channels\Human_Readable_Formatter', 'Simple_History\Channels\Formatters\Human_Readable_Formatter' );
}
