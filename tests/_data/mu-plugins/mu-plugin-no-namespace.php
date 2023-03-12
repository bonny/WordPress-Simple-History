<?php

/**
 * Must use plugin without namespace.
 */

 // We use the function "register_logger" to tell tell SimpleHistory that our custom logger exists.
// We call it from inside the filter "simple_history/add_custom_logger".
add_action(
	'simple_history/add_custom_logger',
	function ( $simple_history ) {
        require_once __DIR__ . '/inc/class-example-404-logger.php';
		$simple_history->register_logger( 'FourOhFourLogger' );
	}
);
