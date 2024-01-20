<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Loggers\Plugin_Logger;
use Simple_History\Log_Initiators;

/**
 * Setup database and upgrade it if needed.
 */
class Setup_Database extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Run at prio 5 so it's run before the loggers etc. are setup.
		// Todo: Did this change when services were added?
		add_action( 'after_setup_theme', array( $this, 'run_setup_steps' ), 5 );
	}

	/**
	 * Check if plugin version have changed, i.e. has been upgraded
	 * If upgrade is detected then maybe modify database and so on for that version
	 */
	public function run_setup_steps() {
		$this->setup_new_to_version_1();
		$this->setup_version_1_to_version_2();
		$this->setup_version_2_to_version_3();
		$this->setup_version_3_to_version_4();
	}

	/**
	 * Get the current database version.
	 * Version 0 = first install or version earlier than 0.4.
	 *
	 * @return int The database version.
	 */
	private function get_db_version() {
		return (int) get_option( 'simple_history_db_version', false );
	}

	/**
	 * If no db_version is set then this
	 * is a version of Simple History < 0.4
	 * or it's a first install
	 * Fix database not using UTF-8.
	 */
	private function setup_new_to_version_1() {
		$db_version = $this->get_db_version();

		// Run step only if previous step was step before this one.
		if ( $db_version !== 0 ) {
			return;
		}

		global $wpdb;
		$table_name = $this->simple_history->get_events_table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table creation, used to be in register_activation_hook
		// We change the varchar size to add one num just to force update of encoding. dbdelta didn't see it otherwise.
		$sql =
			'CREATE TABLE ' .
			$table_name .
			' (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			date datetime NOT NULL,
			PRIMARY KEY  (id)
		) CHARACTER SET=utf8;';

		// Upgrade db / fix utf for varchars.
		dbDelta( $sql );

		// Make sure table is using UTF-8. Early versions did not.
		$sql = sprintf( 'alter table %1$s charset=utf8;', $table_name );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );

		$db_version = 1;

		update_option( 'simple_history_db_version', $db_version );

		// We are not 100% sure that this is a first install,
		// but it is at least a very old version that is being updated.
	}

	/**
	 * If db version is 1 then upgrade to 2
	 * Version 2 added the 'action_description' column,
	 * but it's not used any more so don't do it.
	 */
	private function setup_version_1_to_version_2() {
		$db_version = $this->get_db_version();

		// Run step only if previous step was step before this one.
		if ( $db_version !== 1 ) {
			return;
		}

		// Check that all options we use are set to their defaults, if they miss value
		// Each option that is missing a value will make a sql call otherwise = unnecessary.
		$arr_options = array(
			array(
				'name' => 'simple_history_show_as_page',
				'default_value' => 1,
			),
			array(
				'name' => 'simple_history_show_on_dashboard',
				'default_value' => 1,
			),
		);

		foreach ( $arr_options as $one_option ) {
			$option_value = get_option( $one_option['name'] );
			if ( false === ( $option_value ) ) {
				// Value is not set in db, so set it to a default.
				update_option( $one_option['name'], $one_option['default_value'] );
			}
		}

		update_option( 'simple_history_db_version', 2 );
	}

	/**
	 * If db_version is 2 then upgrade to 3:
	 * - Add some fields to existing table wp_simple_history_contexts
	 * - Add all new table wp_simple_history_contexts
	 *
	 * @since 2.0
	 */
	private function setup_version_2_to_version_3() {
		$db_version = $this->get_db_version();

		// Run step only if previous step was step before this one.
		if ( $db_version !== 2 ) {
			return;
		}

		global $wpdb;
		$table_name = $this->simple_history->get_events_table_name();
		$table_name_contexts = $this->simple_history->get_contexts_table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Update old table.
		$sql = "
			CREATE TABLE {$table_name} (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				date datetime NOT NULL,
				logger varchar(30) DEFAULT NULL,
				level varchar(20) DEFAULT NULL,
				message varchar(255) DEFAULT NULL,
				occasionsID varchar(32) DEFAULT NULL,
				initiator varchar(16) DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY date (date),
				KEY loggerdate (logger,date)
			) CHARSET=utf8;";

		dbDelta( $sql );

		// Add context table.
		$sql = "
			CREATE TABLE IF NOT EXISTS {$table_name_contexts} (
				context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				history_id bigint(20) unsigned NOT NULL,
				`key` varchar(255) DEFAULT NULL,
				value longtext,
				PRIMARY KEY  (context_id),
				KEY history_id (history_id),
				KEY `key` (`key`)
			) CHARSET=utf8;
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );

		// Update possible old items to use SimpleLogger.
		$sql = sprintf(
			'
				UPDATE %1$s
				SET
					logger = "SimpleLogger",
					level = "info"
				WHERE logger IS NULL
			',
			$table_name
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );

		update_option( 'simple_history_db_version', 3 );

		// Say welcome, however loggers are not added this early so we need to
		// use a filter to load it later.
		add_action( 'simple_history/loggers_loaded', array( $this, 'add_welcome_log_message' ) );
		error_log( 'xxx' );
	}

	/**
	 * If db version = 3
	 * then we need to update database to allow null values for some old columns
	 * that used to work in pre wp 4.1 beta, but since 4.1 wp uses STRICT_ALL_TABLES
	 * WordPress Commit: https://github.com/WordPress/WordPress/commit/f17d168a0f72211a9bfd9d3fa680713069871bb6
	 *
	 * @since 2.0
	 */
	private function setup_version_3_to_version_4() {

		$db_version = $this->get_db_version();

		// Run step only if previous step was step before this one.
		if ( $db_version !== 3 ) {
			return;
		}

		global $wpdb;

		$table_name = $this->simple_history->get_events_table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// If old columns exist = this is an old install, then modify the columns so we still can keep them
		// we want to keep them because user may have logged items that they want to keep.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$db_cools = $wpdb->get_col( "DESCRIBE $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( in_array( 'action', $db_cools ) ) {
			$sql = sprintf(
				'
                        ALTER TABLE %1$s
                        MODIFY `action` varchar(255) NULL,
                        MODIFY `object_type` varchar(255) NULL,
                        MODIFY `object_subtype` varchar(255) NULL,
                        MODIFY `user_id` int(10) NULL,
                        MODIFY `object_id` int(10) NULL,
                        MODIFY `object_name` varchar(255) NULL
                    ',
				$table_name
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql );
		}

		update_option( 'simple_history_db_version', 4 );
	}

	/**
	 * Greet users to version 2!
	 * Is only called after database has been upgraded, so only on first install (or upgrade).
	 * Not called after only plugin activation.
	 */
	public function add_welcome_log_message() {
		$db_data_exists = Helpers::db_has_data();
		$plugin_logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimplePluginLogger' );

		if ( ! $plugin_logger instanceof Plugin_Logger ) {
			return;
		}

		// Add plugin installed message.
		// This code is fired twice for some reason.
		$plugin_logger->info_message(
			'plugin_installed',
			[
				'plugin_name' => 'Simple History FROM SETUP',
				'plugin_description' =>
					'Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.',
				'plugin_url' => 'https://simple-history.com',
				'plugin_version' => SIMPLE_HISTORY_VERSION,
				'plugin_author' => 'Pär Thernström',
				'from_setup_database' => true,
			]
		);

		// Add plugin activated message.
		$plugin_logger->info_message(
			'plugin_activated',
			[
				'plugin_slug' => 'simple-history',
				'plugin_name' => 'Simple History FROM SETUP',
				'plugin_title' => '<a href="https://simple-history.com/">Simple History</a>',
				'from_setup_database' => true,
			]
		);

		if ( ! $db_data_exists ) {
			$welcome_message_1 = __(
				'
Welcome to Simple History!

This is the main history feed. It will contain events that this plugin has logged.
',
				'simple-history'
			);

			$welcome_message_2 = __(
				'
Because Simple History was only recently installed, this feed does not display many events yet. As long as the plugin remains activated you will soon see detailed information about page edits, plugin updates, users logging in, and much more.
',
				'simple-history'
			);

			SimpleLogger()->info(
				$welcome_message_2,
				array(
					'_initiator' => Log_Initiators::WORDPRESS,
				)
			);

			SimpleLogger()->info(
				$welcome_message_1,
				array(
					'_initiator' => Log_Initiators::WORDPRESS,
				)
			);
		}
	}
}
