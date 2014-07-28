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

	$args = wp_parse_args( $args, $defaults );

	$action = esc_sql($args["action"]);
	$object_type = esc_sql($args["object_type"]);
	$object_subtype = esc_sql($args["object_subtype"]);
	$object_id = esc_sql($args["object_id"]);
	$object_name = esc_sql($args["object_name"]);
	$user_id = $args["user_id"];
	$description = esc_sql($args["description"]);

	global $wpdb;
	$tableprefix = $wpdb->prefix;
	if ($user_id) {
		$current_user_id = $user_id;
	} else {
		$current_user = wp_get_current_user();
		$current_user_id = (int) $current_user->ID;
	}

	// date, store at utc or local time
	// anything is better than now() anyway!
	// WP seems to use the local time, so I will go with that too I think
	// GMT/UTC-time is: date_i18n($timezone_format, false, 'gmt')); 
	// local time is: date_i18n($timezone_format));
	$localtime = current_time("mysql");
	$sql = "
		INSERT INTO {$tableprefix}simple_history 
		SET 
			date = '$localtime', 
			action = '$action', 
			object_type = '$object_type', 
			object_subtype = '$object_subtype', 
			user_id = '$current_user_id', 
			object_id = '$object_id', 
			object_name = '$object_name',
			action_description = '$description'
		";
	$wpdb->query($sql);

} // simple_history_add

