<?php
/**
 * File that is run during plugin uninstall (not just de-activate)
 *
 * @TODO: delete all tables in network if on multisite
 */

// If uninstall not called from WordPress exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/*
Go on with uninstall actions:
- Remove our database table
- Remove options:
*/

// Remove options.
$arr_options = array(
	'simple_history_db_version',
	'simple_history_pager_size',
	'simple_history_pager_size_dashboard',
	'simple_history_rss_secret',
	'simple_history_enable_rss_feed',
	'simple_history_show_on_dashboard',
	'simple_history_show_as_page',
	'simple_history_show_in_admin_bar',
	'simple_history_detective_mode_enabled',
	'simple_history_experimental_features_enabled',
	'simple_history_install_date_gmt',
	'simple_history_welcome_message_seen',
	'simple_history_auto_backfill_pending',
	'simple_history_auto_backfill_status',
	'simple_history_manual_backfill_status',
	'simple_history_core_files_integrity_results',
	'simple_history_total_logged_events_count',
	'simple_history_email_report_enabled',
	'simple_history_email_report_recipients',
);

foreach ( $arr_options as $one_option ) {
	delete_option( $one_option );
}

global $wpdb;

// Remove database tables.
$table_name = $wpdb->prefix . 'simple_history';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // PHPCS:ignore

$table_name = $wpdb->prefix . 'simple_history_contexts';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // PHPCS:ignore

// Remove scheduled events.
$timestamp = wp_next_scheduled( 'simple_history/email_report' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'simple_history/email_report' );
}


// And we are done. Simple History is ... history.
