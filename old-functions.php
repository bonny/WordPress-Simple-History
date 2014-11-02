<?php

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



// called when saving an options page
/*
function simple_history_add_update_option_page($capability = NULL, $option_page = NULL) {

	$arr_options_names = array(
		"general" 		=> __("General Settings"),
		"writing"		=> __("Writing Settings"),
		"reading"		=> __("Reading Settings"),
		"discussion"	=> __("Discussion Settings"),
		"media"			=> __("Media Settings"),
		"privacy"		=> __("Privacy Settings")
	);
	
	$option_page_name = "";
	if (isset($arr_options_names[$option_page])) {
		$option_page_name = $arr_options_names[$option_page];
		simple_history_add("action=modified&object_type=settings page&object_id=$option_page&object_name=$option_page_name");
	}

	return $capability;
}
*/