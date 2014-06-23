<?php
#return;
//*

/*
SimpleLogger()->info("This is a message sent to the log");

// Second log entry with same info will make these two become an occasionGroup,
// collapsing their entries into one expandable log item
SimpleLogger()->info("This is a message sent to the log");

// Log entries can be of different severity
SimpleLogger()->info("User admin edited page 'About our company'");
SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
SimpleLogger()->debug("Ok, cron job is running!");
*/

// Log entries can have placeholders and context
// This makes log entried translatable and filterable
for ($i = 0; $i < rand(1, 50); $i++) {
	SimpleLogger()->notice(
		"User {username} edited page {pagename}", 
		array(
			"username" => "bonnyerden",
			"pagename" => "My test page",
			"_initiator" => SimpleLoggerLogInitiators::WP_USER,
			"_user_id" => rand(1,20),
			"_user_login" => "loginname" . rand(1,20),
			"_user_email" => "user" . rand(1,20) . "@example.com"
		)
	);
}
#return;

// Log entried can have custom occasionsID
// This will group items together and a log entry will only be shown once
// in the log overview
for ($i = 0; $i < rand(1, 50); $i++) {
	SimpleLogger()->notice("User {username} edited page {pagename}", array(
		"username" => "admin", 
		"pagename" => "My test page",
		"_occasionsID" => "username:1,postID:24884,action:edited"
	));
}

// Add more data to context array. Data can be used later on to show detailed info about a log entry.
SimpleLogger()->notice("Edited product {pagename}", array(
	"pagename" => "We are hiring!",
	"_postType" => "product",
	"_userID" => 1,
	"_userLogin" => "jessie",
	"_userEmail" => "jessie@example.com",
	"_occasionsID" => "username:1,postID:24885,action:edited"
));

SimpleLogger()->info('User "{user_login}" failed to login because they did not enter a correct password', array(
	"user_login" => "admin",
	"_userID" => null
));

// Test logging both inside and outside init-hook
// To make sure it works regardless of wp_get_current_user is avaialble or not
SimpleLogger()->warning("This is a warning log entry before init");
SimpleLogger()->error("This is an error log entry before init");
SimpleLogger()->debug("This is a debug log entry before init");

add_action("init", function() {

    SimpleLogger()->warning("This is a warning log entry (after init)");
    SimpleLogger()->error("This is an error log entry (after init)");
    SimpleLogger()->debug("This is a debug log entry (after init)");


    SimpleLogger()->info(
    	"WordPress updated itself from version {from_version} to {to_version}",
    	array(
    		"from_version" => "3.8",
    		"to_version" => "3.8.1",
    		"_initiator" => SimpleLoggerLogInitiators::WORDPRESS
    	)
    );

    SimpleLogger()->info(
    	"Plugin {plugin_name} was updated from version {plugin_from_version} to version {plugin_to_version}",
    	array(
    		"plugin_name" => "CMS Tree Page View",
    		"plugin_from_version" => "4.0",
    		"plugin_to_version" => "4.2",
    		"_initiator" => SimpleLoggerLogInitiators::WORDPRESS
    	)
    );

    SimpleLogger()->info(
    	"Updated plugin {plugin_name} from version {plugin_from_version} to version {plugin_to_version}",
    	array(
    		"plugin_name" => "Simple Fields",
    		"plugin_from_version" => "1.3.7",
    		"plugin_to_version" => "1.3.8",
    		"_initiator" => SimpleLoggerLogInitiators::WP_USER
    	)
    );

    SimpleLogger()->info(
    	"Updated plugin {plugin_name} from version {plugin_from_version} to version {plugin_to_version}",
    	array(
    		"plugin_name" => "Ninja Forms",
    		"plugin_from_version" => "1.1",
    		"plugin_to_version" => "1.1.2",
    		"_initiator" => SimpleLoggerLogInitiators::WP_USER
    	)
    );

});



//*/
