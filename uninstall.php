<?php
/**
 * File that is run during plugin uninstall (not just de-activate).
 */

// If uninstall not called from WordPress exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * Remove all Simple History data for the current site:
 * options, database tables, and scheduled cron events.
 */
function simple_history_cleanup_site() {
	global $wpdb;

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
		'simple_history_channel_file',
	);

	foreach ( $arr_options as $one_option ) {
		delete_option( $one_option );
	}

	// Remove database tables.
	$table_name = $wpdb->prefix . 'simple_history';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	$table_name = $wpdb->prefix . 'simple_history_contexts';
	$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Remove all scheduled cron events.
	$cron_hooks = array(
		'simple_history/email_report',
		'simple_history/maybe_purge_db',
		'simple_history/core_files_integrity_check',
		'simple_history_cleanup_log_files',
	);

	foreach ( $cron_hooks as $hook ) {
		wp_unschedule_hook( $hook );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		) 
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		simple_history_cleanup_site();
		restore_current_blog();
	}
} else {
	simple_history_cleanup_site();
}
