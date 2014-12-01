<?php

/**
 * Helper function with same name as the SimpleLogger-class
 *
 * Makes call like this possible:
 * SimpleLogger()->info("This is a message sent to the log");
 */
function SimpleLogger() {
	return new SimpleLogger( $GLOBALS["simple_history"] );
}

/**
 * Add event to history table
 * This is here for backwards compatibility
 * If you use this please consider using
 * SimpleHistory()->info();
 * instead
 */
function simple_history_add($args) {

	$defaults = array(
		"action" => null,
		"object_type" => null,
		"object_subtype" => null,
		"object_id" => null,
		"object_name" => null,
		"user_id" => null,
		"description" => null
	);

	$context = wp_parse_args( $args, $defaults );

	$message = "{$context["object_type"]} {$context["object_name"]} {$context["action"]}";

	SimpleLogger()->info($message, $context);

} // simple_history_add
