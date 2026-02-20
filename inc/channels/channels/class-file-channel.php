<?php
/**
 * Backward compatibility stub.
 *
 * In version 5.21.0 this class lived at Simple_History\Channels\Channels.
 * It was moved to Simple_History\Channels in version 5.22.0.
 * This file exists so that code from 5.21.0 (loaded in memory during
 * a plugin update) can still autoload from the old path.
 *
 * @since 5.24.0
 */

namespace Simple_History\Channels\Channels;

// Load the real class from its new location.
require_once dirname( __DIR__ ) . '/class-file-channel.php';

if ( ! class_exists( 'Simple_History\Channels\Channels\File_Channel', false ) ) {
	class_alias( 'Simple_History\Channels\File_Channel', 'Simple_History\Channels\Channels\File_Channel' );
}
