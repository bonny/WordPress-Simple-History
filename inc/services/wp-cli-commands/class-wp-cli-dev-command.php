<?php

namespace Simple_History\Services\WP_CLI_Commands;

use WP_CLI;
use WP_CLI_Command;
use Simple_History\Simple_History;
use Simple_History\Loggers\Plugin_Logger;

/**
 * Development commands for Simple History.
 *
 * Only available when SIMPLE_HISTORY_DEV constant is true.
 *
 * @since 5.x
 */
class WP_CLI_Dev_Command extends WP_CLI_Command {
	/**
	 * Reset Simple History to simulate a fresh install.
	 *
	 * Removes all events, database tables, options, and scheduled events,
	 * then deactivates the plugin. After reset, reactivating the plugin
	 * will trigger fresh install behavior including auto backfill.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reset Simple History with confirmation
	 *     wp simple-history dev reset
	 *
	 *     # Reset without confirmation (useful for scripts)
	 *     wp simple-history dev reset --yes
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function reset( $args, $assoc_args ) {
		// Script exits if user does not confirm.
		WP_CLI::confirm(
			__(
				'This will delete all Simple History events, tables, options, and deactivate the plugin. Continue?',
				'simple-history'
			),
			$assoc_args
		);

		// 1. Deactivate plugin first to prevent errors from hooks
		// trying to use resources we're about to delete.
		$this->deactivate_plugin();

		// 2. Drop database tables.
		$this->do_drop_tables();

		// 3. Delete all options.
		$this->do_delete_options();

		// 4. Clear scheduled cron events.
		$this->clear_cron_events();

		WP_CLI::success(
			__(
				'Simple History has been reset. Reactivate the plugin to trigger fresh install behavior.',
				'simple-history'
			)
		);
	}

	/**
	 * Drop Simple History database tables.
	 */
	private function do_drop_tables() {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$events_table   = $simple_history->get_events_table_name();
		$contexts_table = $simple_history->get_contexts_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$events_table}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$contexts_table}" );

		WP_CLI::log( __( 'Database tables dropped.', 'simple-history' ) );
	}

	/**
	 * Delete all Simple History options.
	 */
	private function do_delete_options() {
		$options_to_delete = [
			'simple_history_db_version',
			'simple_history_pager_size',
			'simple_history_rss_secret',
			'simple_history_show_on_dashboard',
			'simple_history_show_as_page',
			'simple_history_detective_mode_enabled',
			'simple_history_experimental_features_enabled',
			'simple_history_show_in_admin_bar',
			'simple_history_install_date_gmt',
			'simple_history_auto_backfill_status',
		];

		$deleted_count = 0;

		foreach ( $options_to_delete as $option ) {
			if ( delete_option( $option ) ) {
				++$deleted_count;
			}
		}

		WP_CLI::log(
			sprintf(
				/* translators: %d: number of options deleted */
				__( '%d options deleted.', 'simple-history' ),
				$deleted_count
			)
		);
	}

	/**
	 * Clear all Simple History scheduled cron events.
	 */
	private function clear_cron_events() {
		$hooks_to_clear = [
			'simple_history/auto_backfill',
			'simple_history/email_report',
			'simple_history/maybe_purge_db',
		];

		foreach ( $hooks_to_clear as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		WP_CLI::log( __( 'Scheduled cron events cleared.', 'simple-history' ) );
	}

	/**
	 * Deactivate the Simple History plugin.
	 */
	private function deactivate_plugin() {
		deactivate_plugins( 'simple-history/index.php' );

		WP_CLI::log( __( 'Plugin deactivated.', 'simple-history' ) );
	}

	/**
	 * Drop Simple History database tables only.
	 *
	 * Useful for testing table creation on fresh installs or simulating
	 * site duplication where tables are not copied.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history dev drop-tables --yes
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function drop_tables( $args, $assoc_args ) {
		WP_CLI::confirm(
			__( 'This will delete all Simple History database tables. Continue?', 'simple-history' ),
			$assoc_args
		);

		$this->do_drop_tables();
		WP_CLI::success( __( 'Database tables dropped.', 'simple-history' ) );
	}

	/**
	 * Delete all Simple History options only.
	 *
	 * Useful for testing fresh install behavior.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history dev delete-options --yes
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function delete_options( $args, $assoc_args ) {
		WP_CLI::confirm(
			__( 'This will delete all Simple History options. Continue?', 'simple-history' ),
			$assoc_args
		);

		$this->do_delete_options();
		WP_CLI::success( __( 'Options deleted.', 'simple-history' ) );
	}

	/**
	 * Show current Simple History database state.
	 *
	 * Displays tables existence, row counts, and option values.
	 * Useful for debugging table creation issues.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history dev status
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$events_table   = $simple_history->get_events_table_name();
		$contexts_table = $simple_history->get_contexts_table_name();

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BEnvironment:%n' ) );
		$dev_mode = defined( 'SIMPLE_HISTORY_DEV' ) && SIMPLE_HISTORY_DEV;
		WP_CLI::log( sprintf( '  Dev mode: %s', $dev_mode ? WP_CLI::colorize( '%gENABLED%n' ) : WP_CLI::colorize( '%ydisabled%n' ) ) );

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BDatabase Tables:%n' ) );
		WP_CLI::log( sprintf( '  Prefix: %s', $wpdb->prefix ) );

		// Check events table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$events_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$events_table}'" ) === $events_table;
		if ( $events_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$events_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$events_table}" );
			WP_CLI::log( sprintf( '  %s: %s (%s rows)', $events_table, WP_CLI::colorize( '%gEXISTS%n' ), $events_count ) );
		} else {
			WP_CLI::log( sprintf( '  %s: %s', $events_table, WP_CLI::colorize( '%rMISSING%n' ) ) );
		}

		// Check contexts table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$contexts_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$contexts_table}'" ) === $contexts_table;
		if ( $contexts_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$contexts_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$contexts_table}" );
			WP_CLI::log( sprintf( '  %s: %s (%s rows)', $contexts_table, WP_CLI::colorize( '%gEXISTS%n' ), $contexts_count ) );
		} else {
			WP_CLI::log( sprintf( '  %s: %s', $contexts_table, WP_CLI::colorize( '%rMISSING%n' ) ) );
		}

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%BOptions:%n' ) );

		$options = [
			'simple_history_db_version',
			'simple_history_pager_size',
			'simple_history_show_on_dashboard',
			'simple_history_show_as_page',
			'simple_history_detective_mode_enabled',
			'simple_history_experimental_features_enabled',
			'simple_history_show_in_admin_bar',
			'simple_history_install_date_gmt',
		];

		foreach ( $options as $option ) {
			$value = get_option( $option, null );
			if ( $value === null ) {
				WP_CLI::log( sprintf( '  %s: %s', $option, WP_CLI::colorize( '%ynot set%n' ) ) );
			} else {
				WP_CLI::log( sprintf( '  %s: %s', $option, WP_CLI::colorize( '%g' . $value . '%n' ) ) );
			}
		}

		WP_CLI::log( '' );
	}

	/**
	 * Add a plugin update message to the log for testing the "What's new" feature.
	 *
	 * Creates a log entry as if Simple History was updated, allowing you to test
	 * and preview the update details message.
	 *
	 * ## OPTIONS
	 *
	 * [--prev-version=<version>]
	 * : The previous version to simulate updating from.
	 * ---
	 * default: 5.18.0
	 * ---
	 *
	 * [--version=<version>]
	 * : The target version to simulate updating to. Defaults to current installed version.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add a plugin update message with default versions
	 *     wp simple-history dev add-plugin-update-message
	 *
	 *     # Add a plugin update message simulating update from specific version
	 *     wp simple-history dev add-plugin-update-message --prev-version=5.17.0
	 *
	 *     # Add a plugin update message simulating update to specific version
	 *     wp simple-history dev add-plugin-update-message --version=5.22.0
	 *
	 *     # Add a plugin update message with both versions specified
	 *     wp simple-history dev add-plugin-update-message --prev-version=5.20.0 --version=5.22.0
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function add_plugin_update_message( $args, $assoc_args ) {
		$simple_history = Simple_History::get_instance();

		// Get the Plugin Logger instance.
		$plugin_logger = $simple_history->get_instantiated_logger_by_slug( 'SimplePluginLogger' );

		if ( ! $plugin_logger ) {
			WP_CLI::error( __( 'Could not find Plugin Logger.', 'simple-history' ) );
			return;
		}

		// Get Simple History plugin data.
		$plugin_file = 'simple-history/index.php';
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, true, false );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP-CLI command, no nonce needed.
		$prev_version = $assoc_args['prev-version'] ?? '5.18.0';
		$version      = $assoc_args['version'] ?? $plugin_data['Version'];

		$context = [
			'plugin_slug'         => 'simple-history',
			'plugin_name'         => $plugin_data['Name'],
			'plugin_title'        => $plugin_data['Title'],
			'plugin_description'  => $plugin_data['Description'],
			'plugin_author'       => $plugin_data['Author'],
			'plugin_version'      => $version,
			'plugin_prev_version' => $prev_version,
			'plugin_url'          => $plugin_data['PluginURI'],
		];

		$plugin_logger->info_message( 'plugin_updated', $context );

		WP_CLI::success(
			sprintf(
				/* translators: 1: previous version, 2: current version */
				__( 'Added plugin update message: Simple History %1$s → %2$s', 'simple-history' ),
				$prev_version,
				$version
			)
		);
	}
}
