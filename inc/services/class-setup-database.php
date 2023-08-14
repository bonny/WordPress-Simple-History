<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Loggers\Plugin_Logger;
use Simple_History\Log_Initiators;

class Setup_Database extends Service {
	public function loaded() {
		// Run at prio 5 so it's run before the loggers etc. are setup.
		add_action( 'after_setup_theme', array( $this, 'check_for_upgrade' ), 5 );
	}

	/**
	 * Check if plugin version have changed, i.e. has been upgraded
	 * If upgrade is detected then maybe modify database and so on for that version
	 */
	public function check_for_upgrade() {
		global $wpdb;

		/** @var string $db_version Version number of the Simple History database. */
		$db_version = get_option( 'simple_history_db_version' );

		$table_name = $this->simple_history->get_events_table_name();
		$table_name_contexts = $this->simple_history->get_contexts_table_name();

		$first_install = false;

		// If no db_version is set then this
		// is a version of Simple History < 0.4
		// or it's a first install
		// Fix database not using UTF-8
		if ( false === $db_version || (int) $db_version == 0 ) {
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

			// Upgrade db / fix utf for varchars
			dbDelta( $sql );

			// Fix UTF-8 for table
			$sql = sprintf( 'alter table %1$s charset=utf8;', $table_name );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql );

			$db_version = 1;

			update_option( 'simple_history_db_version', $db_version );

			// We are not 100% sure that this is a first install,
			// but it is at least a very old version that is being updated
			$first_install = true;
		} // End if().

		// If db version is 1 then upgrade to 2
		// Version 2 added the action_description column
		if ( 1 == (int) $db_version ) {
			// V2 used to add column "action_description"
			// but it's not used any more so don't do it.
			$db_version = 2;

			update_option( 'simple_history_db_version', $db_version );
		}

		// Check that all options we use are set to their defaults, if they miss value
		// Each option that is missing a value will make a sql call otherwise = unnecessary
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
				// Value is not set in db, so set it to a default
				update_option( $one_option['name'], $one_option['default_value'] );
			}
		}

		/**
		 * If db_version is 2 then upgrade to 3:
		 * - Add some fields to existing table wp_simple_history_contexts
		 * - Add all new table wp_simple_history_contexts
		 *
		 * @since 2.0
		 */
		if ( 2 == (int) $db_version ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Update old table
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

			// Add context table
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

			$db_version = 3;
			update_option( 'simple_history_db_version', $db_version );

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

			// Say welcome, however loggers are not added this early so we need to
			// use a filter to load it later
			add_action( 'simple_history/loggers_loaded', array( $this, 'add_welcome_log_message' ) );
		} // End if().

		/**
		 * If db version = 3
		 * then we need to update database to allow null values for some old columns
		 * that used to work in pre wp 4.1 beta, but since 4.1 wp uses STRICT_ALL_TABLES
		 * WordPress Commit: https://github.com/WordPress/WordPress/commit/f17d168a0f72211a9bfd9d3fa680713069871bb6
		 *
		 * @since 2.0
		 */
		if ( 3 == (int) $db_version ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// If old columns exist = this is an old install, then modify the columns so we still can keep them
			// we want to keep them because user may have logged items that they want to keep
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

			$db_version = 4;

			update_option( 'simple_history_db_version', $db_version );
		} // End if().

		// Some installs on 2.2.2 got failed installs
		// We detect these by checking for db_version and then running the install stuff again
		if ( 4 == (int) $db_version ) {
			/** @noRector \Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector */
			if ( ! $this->simple_history->does_database_have_data() ) {
				// not ok, decrease db number so installs will run again and hopefully fix things
				$db_version = 0;
			} else {
				// all looks ok, upgrade to db version 5, so this part is not done again
				$db_version = 5;
			}

			update_option( 'simple_history_db_version', $db_version );
		}
	}

	/**
	 * Greet users to version 2!
	 * Is only called after database has been upgraded, so only on first install (or upgrade).
	 * Not called after only plugin activation.
	 */
	public function add_welcome_log_message() {
		$db_data_exists = $this->simple_history->does_database_have_data();
		$plugin_logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimplePluginLogger' );

		if ( ! $plugin_logger instanceof Plugin_Logger ) {
			return;
		}

		if ( $plugin_logger instanceof Plugin_Logger ) {
			// Add plugin installed message.
			$plugin_logger->info_message(
				'plugin_installed',
				[
					'plugin_name' => 'Simple History',
					'plugin_description' =>
						'Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.',
					'plugin_url' => 'https://simple-history.com',
					'plugin_version' => SIMPLE_HISTORY_VERSION,
					'plugin_author' => 'Pär Thernström',
				]
			);

			// Add plugin activated message
			$plugin_logger->info_message(
				'plugin_activated',
				[
					'plugin_slug' => 'simple-history',
					'plugin_title' => '<a href="https://simple-history.com/">Simple History</a>',
				]
			);
		}

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
