<?php
/**
 * File that is run during plugin uninstall (not just de-activate)
 */

// If uninstall not called from WordPress exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit ();

/*
Go on with uninstall actions:
 - Remove our database table
 - Remove options:
*/

// Remove options
$arr_options = array(
 	"simple_history_pager_size",
 	"simple_history_db_version",
 	"simple_history_rss_secret",
 	"simple_history_show_on_dashboard",
 	"simple_history_show_as_page"
);

foreach ($arr_options as $one_option) {
	delete_option( $one_option );
}

// Remove database table
global $wpdb;
$table_name = $wpdb->prefix . "simple_history";
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// And we are done. Simple History is ...  history.
