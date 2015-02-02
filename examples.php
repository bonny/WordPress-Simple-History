<?php

// No external calls allowed
exit;

/**
 * Some examples of filter usage and so on
 */

// Never clear the log (default is 60 days)
add_filter("simple_history/db_purge_days_interval", "__return_zero");

// Don't let anyone - even with the correct secret - view the RSS feed
add_filter("simple_history/rss_feed_show", "__return_false");

// Skip loading of a dropin completely (in this case the RSS dropin)
add_filter("simple_history/dropin/load_dropin_SimpleHistoryRSSDropin", "__return_false");

/**
 * Example of logging
 */

// Add a message to the history log
SimpleLogger()->info("This is a message sent to the log");

// Add log entries with different severities
SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
SimpleLogger()->debug("Ok, cron job is running!");

// Add a message to the history log
// and then add a second log entry with same info and Simple History
// will make these two become an "occasionGroup",
// i.e. collapsing their entries into one expandable log item
SimpleLogger()->info("This is a message sent to the log");
SimpleLogger()->info("This is a message sent to the log");

// Log entries can have placeholders and context
// This makes log entried translatable and filterable
SimpleLogger()->notice(
	"User {username} edited page {pagename}",
	array(
		"username" => "jessie",
		"pagename" => "My test page",
		"_initiator" => SimpleLoggerLogInitiators::WP_USER,
		"_user_id" => 5,
		"_user_login" => "jess",
		"_user_email" => "jessie@example.com"
	)
);

// Log entried can have custom occasionsID
// This will group items together and a log entry will only be shown once
// in the log overview, even if the logged messages are different
for ($i = 0; $i < rand(1, 50); $i++) {
	SimpleLogger()->notice("User {username} edited page {pagename}", array(
		"username" => "example_user_{$i}",
		"pagename" => "My test page",
		"_occasionsID" => "postID:24884,action:edited"
	));
}

// Events can have different "initiators",
// i.e. who was responsible for the logged event
// Initiator "WORDPRESS" means that WordPress did something on it's own
SimpleLogger()->info(
	"WordPress updated itself from version {from_version} to {to_version}",
	array(
		"from_version" => "3.8",
		"to_version" => "3.8.1",
		"_initiator" => SimpleLoggerLogInitiators::WORDPRESS
	)
);

// Initiator "WP_USER" means that a logged in user did someting
SimpleLogger()->info(
	"Updated plugin {plugin_name} from version {plugin_from_version} to version {plugin_to_version}",
	array(
		"plugin_name" => "Ninja Forms",
		"plugin_from_version" => "1.1",
		"plugin_to_version" => "1.1.2",
		"_initiator" => SimpleLoggerLogInitiators::WP_USER
	)
);

// // Initiator "WEB_USER" means that an unknown internet user did something
SimpleLogger()->warning("An attempt to login as user 'administrator' failed to login because the wrong password was entered", array(
	"_initiator" => SimpleLoggerLogInitiators::WEB_USER
));


// Use the "context array" to add  more data to your logged event
// Data can be used later on to show detailed info about a log entry
// and does not need to be shown on the overview screen
SimpleLogger()->info("Edited product '{pagename}'", array(
	"pagename" => "We are hiring!",
	"_postType" => "product",
	"_userID" => 1,
	"_userLogin" => "jessie",
	"_userEmail" => "jessie@example.com",
	"_occasionsID" => "username:1,postID:24885,action:edited"
));


// Test log cron things
/*
wp_schedule_event( time(), "hourly", "simple_history_cron_testhook");
*/
/*
wp_clear_scheduled_hook("simple_history_cron_testhook");
add_action( 'simple_history_cron_testhook', 'simple_history_cron_testhook_function' );
function simple_history_cron_testhook_function() {
	SimpleLogger()->info("This is a message inside a cron function");
}
*/

/*
add_action("init", function() {

	global $wp_current_filter;

	$doing_cron = get_transient( 'doing_cron' );
	$const_doing_cron = defined('DOING_CRON') && DOING_CRON;

	if ($const_doing_cron) {

		$current_filter = current_filter();

		SimpleLogger()->info("This is a message inside init, trying to log crons", array(
			"doing_cron" => simpleHistory::json_encode($doing_cron),
			"current_filter" => $current_filter,
			"wp_current_filter" => $wp_current_filter,
			"wp_current_filter" => simpleHistory::json_encode( $wp_current_filter ),
			"const_doing_cron" => simpleHistory::json_encode($const_doing_cron)
		));

	}

}, 100);
*/


/*
add_action("init", function() {

	#SimpleLogger()->info("This is a regular info message" . time());

}, 100);
// */
