<?php

/**
 * Must use plugin and dropin without namespace.
 */

// We use the function "register_logger" to tell tell SimpleHistory that our custom logger exists.
add_action(
	'simple_history/add_custom_logger',
	function ( $simple_history ) {
        require_once __DIR__ . '/inc/class-example-logger.php';
		$simple_history->register_logger( 'Example_Logger' );
	}
);

// We use the function "register_dropin" to tell tell Simple History that our custom logger exists.
add_action(
	'simple_history/add_custom_dropin',
	function ( $simpleHistory ) {
		require_once __DIR__ . '/inc/class-example-dropin.php';
		$simpleHistory->register_dropin( 'Example_Dropin' );
	}
);
