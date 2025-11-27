<?php

namespace Simple_History\Services\WP_CLI_Commands;

use WP_CLI;
use WP_CLI_Command;
use Simple_History\Simple_History;

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
		$this->drop_tables();

		// 3. Delete all options.
		$this->delete_options();

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
	private function drop_tables() {
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
	private function delete_options() {
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
}
