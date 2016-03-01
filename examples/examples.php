<?php

// No external calls allowed
exit;


/**
 * Misc
 */

// Add $_GET, $_POST, and more info to each logged event
define("SIMPLE_HISTORY_LOG_DEBUG", true);


/**
 * Some examples of filter usage and so on
 */

// Disable all logging
add_filter( "simple_history/log/do_log", "__return_false" );

/**
 * Example that modifies the parameters sent to the message template
 * This example will change the post type from "post" or "page" or similar to "my own page type"
 */
add_filter( "simple_history/logger/interpolate/context", function($context, $message, $row) {

        if ( empty( $row ) ) {
                return $context;
        }

        if ( $row->logger == "SimplePostLogger" && $row->context_message_key == "post_updated" ) {
                $context["post_type"] = "my own page type";
        }

        return $context;

}, 10, 3);



/**
 * Change capability required to manage the options page of simple history.
 * Default capability is "manage_options"
 */
add_filter("simple_history/view_settings_capability", function($capability) {
    
    $capability = "manage_options";
    return $capability;

});


/**
 * Change capability required to view main simple history page.
 * Default capability is "edit_pages". Change to for example "manage options" 
 * to only allow admins to view the history log.
 */
add_filter("simple_history/view_history_capability", function($capability) {
    
    $capability = "manage_options";
    return $capability;

});


// Skip adding things to the context table during logging. 
// Useful if you don't want to add cool and possible super useful info to your logged events.
// Also nice to have if you want to make sure your database does not grow.
add_filter("simple_history/log_insert_context", function($context, $data) {

	unset($context["_user_id"]);
	unset($context["_user_login"]);
	unset($context["_user_email"]);
	unset($context["server_http_user_agent"]);

	return $context;

}, 10, 2);

// Hide some columns from the detailed context view popup window
add_filter("simple_history/log_html_output_details_table/row_keys_to_show", function($logRowKeysToShow, $oneLogRow) {
	
	$logRowKeysToShow["id"] = false;
	$logRowKeysToShow["logger"] = false;
	$logRowKeysToShow["level"] = false;
	$logRowKeysToShow["message"] = false;

	return $logRowKeysToShow;

}, 10, 2);


// Hide some more columns from the detailed context view popup window
add_filter("simple_history/log_html_output_details_table/context_keys_to_show", function($logRowContextKeysToShow, $oneLogRow) {
	
	$logRowContextKeysToShow["plugin_slug"] = false;
	$logRowContextKeysToShow["plugin_name"] = false;
	$logRowContextKeysToShow["plugin_title"] = false;
	$logRowContextKeysToShow["plugin_description"] = false;

	return $logRowContextKeysToShow;

}, 10, 2);



// Allow only the users specified in $allowed_users to show the history page, the history widget on the dashboard, or the history settings page
add_filter("simple_history/show_dashboard_page", "function_show_history_dashboard_or_page");
add_filter("simple_history/show_dashboard_widget", "function_show_history_dashboard_or_page");
add_filter("simple_history/show_settings_page", "function_show_history_dashboard_or_page");
function function_show_history_dashboard_or_page($show) {

	$allowed_users = array(
		"user1@example.com",
		"anotheruser@example.com"
	);

	$user = wp_get_current_user();
	
	if ( ! in_array( $user->user_email, $allowed_users ) ) {
		$show = false;
	}

	return $show;

}


// Skip loading of loggers
add_filter("simple_history/logger/load_logger", function($load_logger, $oneLoggerFile) {

	// Don't load loggers for comments or menus, i.e. don't log changes to comments or to menus
	if ( in_array($oneLoggerFile, array("SimpleCommentsLogger", "SimpleMenuLogger")) ) {
		$load_logger = false;
	}

	return $load_logger;

}, 10, 2);

/**
 * Load only the loggers that are specified in the $do_log_us array
 */
add_filter("simple_history/logger/load_logger", function($load_logger, $logger_basename) {

	$load_logger = false;
	$do_log_us = array("SimplePostLogger", "SimplePluginLogger", "SimpleLogger");

	if ( in_array( $logger_basename, $do_log_us ) ) {
		$load_logger = true;
	}

	return $load_logger;

}, 10, 2 );


// Skip the loading of dropins
add_filter("simple_history/dropin/load_dropin", function($load_dropin, $dropinFileBasename) {
	
	// Don't load the RSS feed dropin
	if ( $dropinFileBasename == "SimpleHistoryRSSDropin" ) {
		$load_dropin = false;
	}

	// Don't load the dropin that polls for changes
	if ( $dropinFileBasename == "SimpleHistoryNewRowsNotifier" ) {
		$load_dropin = false;
	}

	return $load_dropin;

}, 10, 2);


// Don't log failed logins
add_filter("simple_history/simple_logger/log_message_key", function($doLog, $loggerSlug, $messageKey, $SimpleLoggerLogLevelsLevel, $context) {

	// Don't log login attempts to non existing users
	if ( "SimpleUserLogger" == $loggerSlug && "user_unknown_login_failed" == $messageKey ) {
		$doLog = false;
	}

	// Don't log failed logins to existing users
	if ( "SimpleUserLogger" == $loggerSlug && "user_login_failed" == $messageKey ) {
		$doLog = false;
	}

	return $doLog;

}, 10, 5);

// Never clear the log (default is 60 days)
add_filter("simple_history/db_purge_days_interval", "__return_zero");

// Clear items that are older than a 7 days (i.e. keep only the most recent 7 days in the log)
add_filter( "simple_history/db_purge_days_interval", function( $days ) {
	
	$days = 7;
	
	return $days;

} );

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
