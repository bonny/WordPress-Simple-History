<?php

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Scope.MethodScope.Missing

use Simple_History\Simple_History;

class SimpleHistoryTest extends \Codeception\TestCase\WPTestCase {
	// https://phpunit.de/manual/current/en/fixtures.html
	function test_history_setup() {
		$this->assertTrue( defined( 'SIMPLE_HISTORY_VERSION' ), 'Assert SIMPLE_HISTORY_VERSION' );
		$this->assertTrue( defined( 'SIMPLE_HISTORY_PATH' ), 'Assert SIMPLE_HISTORY_PATH' );
		$this->assertTrue( defined( 'SIMPLE_HISTORY_BASENAME' ), 'Assert SIMPLE_HISTORY_BASENAME' );
		$this->assertTrue( defined( 'SIMPLE_HISTORY_DIR_URL' ), 'Assert SIMPLE_HISTORY_DIR_URL' );
		$this->assertTrue( defined( 'SIMPLE_HISTORY_FILE' ), 'Assert SIMPLE_HISTORY_FILE' );

		// $this->assertFalse( defined( 'SIMPLE_HISTORY_DEV' ), 'Assert SIMPLE_HISTORY_DEV' );
	}

	function test_history_classes() {

		$this->assertTrue( class_exists( 'Simple_History\Simple_History' ) );
		$this->assertTrue( class_exists( 'Simple_History\Log_Query' ) );
		$this->assertTrue( class_exists( 'Simple_History\Loggers\Logger' ) );
		$this->assertTrue( class_exists( 'Simple_History\Log_Initiators' ) );
		$this->assertTrue( class_exists( 'Simple_History\Log_Levels' ) );

		$sh = Simple_History::get_instance();
		$this->assertTrue( is_object( $sh ) );
		$this->assertTrue( is_a( $sh, 'Simple_History\Simple_History' ) );
	}

	function test_default_loggers() {

		$sh = Simple_History::get_instance();
		$loggers = $sh->get_instantiated_loggers();
		
		$arr_default_loggers = array(
			'AvailableUpdatesLogger',
			'FileEditsLogger',
			'Plugin_ACF',
			'Plugin_BeaverBuilder',
			'Plugin_DuplicatePost',
			'Plugin_LimitLoginAttempts',
			'Plugin_Redirection',
			'PluginEnableMediaReplaceLogger',
			'PluginUserSwitchingLogger',
			'PluginWPCrontrolLogger',
			'SimpleCategoriesLogger',
			'SimpleCommentsLogger',
			'SimpleCoreUpdatesLogger',
			'SimpleExportLogger',
			'SimpleLogger',
			'SimpleMediaLogger',
			'SimpleMenuLogger',
			'SimpleOptionsLogger',
			'SimplePluginLogger',
			'SimplePostLogger',
			'SimpleThemeLogger',
			'SimpleUserLogger',
			'SH_Jetpack_Logger',
			'SH_Privacy_Logger',
			'SH_Translations_Logger'
		);

		foreach ( $arr_default_loggers as $slug ) {
			$this->assertArrayHasKey( $slug, $loggers );
		}
	}

	function test_default_dropins() {

		$sh = Simple_History::get_instance();
		$dropins = $sh->get_instantiated_dropins();

		$arr_default_dropins = array(
			'Donate_Dropin',
			'Export_Dropin',
			'IP_Info_Dropin',
			'RSS_Dropin',
			'Sidebar_Dropin',
		);

		foreach ( $arr_default_dropins as $slug ) {
			$this->assertArrayHasKey( $slug, $dropins );
		}
	}

	function test_default_settings_tabs() {

		$sh = Simple_History::get_instance();
		$settings_tabs = $sh->get_settings_tabs();
		$arr_default_settings = array(
			'settings',
			'export',
			'debug',
			// Added by dropin test
			'dropin_example_tab_slug',
			'tests_dropin_settings_tab_slug',
		);
		
		$loaded_settings_slugs = wp_list_pluck( $settings_tabs, 'slug' );
	
		$this->assertEquals( $arr_default_settings, $loaded_settings_slugs );
	}

	function test_install() {

		global $wpdb;

		// Test table simple history
		$table_name_simple_history = Simple_History::get_instance()->get_events_table_name();

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name_simple_history ) );
		$this->assertEquals( $table_name_simple_history, $table_exists );

		$table_cols = $wpdb->get_col( "DESCRIBE $table_name_simple_history" ); // PHPCS:ignore
		$expected_table_cols = array(
			'id',
			'date',
			'logger',
			'level',
			'message',
			'occasionsID',
			'initiator',
		);

		$this->assertEquals( $expected_table_cols, $table_cols, 'cols in history table should be the same' );

		// Test table simple history contexts
		$table_name_contexts = Simple_History::get_instance()->get_contexts_table_name();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name_contexts ) );
		$this->assertEquals( $table_name_contexts, $table_exists );

		$table_cols_context = $wpdb->get_col( "DESCRIBE $table_name_contexts" ); // PHPCS:ignore
		$expected_table_cols_context = array(
			'context_id',
			'history_id',
			'key',
			'value',
		);

		$this->assertEquals( $expected_table_cols_context, $table_cols_context, 'cols in context table should be the same' );
	}

	function test_loglevels_and_initiators() {

		$refl = new ReflectionClass( 'Simple_History\Log_Levels' );
		$log_levels = (array) $refl->getConstants();

		$expected_log_levels = array(
			'EMERGENCY' => 'emergency',
			'ALERT' => 'alert',
			'CRITICAL' => 'critical',
			'ERROR' => 'error',
			'WARNING' => 'warning',
			'NOTICE' => 'notice',
			'INFO' => 'info',
			'DEBUG' => 'debug',
		);

		$this->assertEquals( $expected_log_levels, $log_levels, 'log levels' );

		$refl = new ReflectionClass( 'Simple_History\Log_Initiators' );
		$log_initiators = (array) $refl->getConstants();

		$expected_log_initiators = array(
			'WP_USER' => 'wp_user',
			'WEB_USER' => 'web_user',
			'WORDPRESS' => 'wp',
			'WP_CLI' => 'wp_cli',
			'OTHER' => 'other',
		);

		$this->assertEquals( $expected_log_initiators, $log_initiators, 'log initiators' );
	}

	// test logging and retrieving logs
	function test_logging() {

		global $wpdb;

		$table_name_simple_history = Simple_History::get_instance()->get_events_table_name();

		$refl_log_levels = new ReflectionClass( 'Simple_History\Log_Levels' );
		$log_levels = (array) $refl_log_levels->getConstants();

		$refl_log_initiators = new ReflectionClass( 'Simple_History\Log_Initiators' );
		$log_initiators = (array) $refl_log_initiators->getConstants();

		foreach ( $log_levels as $level_const => $level_str ) {
			foreach ( $log_initiators as $initiator_const => $initiator_str ) {
				$message = "This is a message with log level $level_str";

				SimpleLogger()->log(
					$level_str,
					$message,
					array(
						'_initiator' => $initiator_str,
					)
				);

				// Last logged message in db should be the above
				$db_row = $wpdb->get_row( "SELECT logger, level, message, initiator FROM $table_name_simple_history ORDER BY id DESC LIMIT 1", ARRAY_A ); // PHPCS:ignore

				$expected_row = array(
					'logger' => 'SimpleLogger',
					'level' => $level_str,
					'message' => $message,
					'initiator' => $initiator_str,
				);

				$this->assertEquals( $expected_row, $db_row, 'logged event in db' );
			}
		}
	}

	function test_get_info() {

		$sh = Simple_History::get_instance();

		$postlogger = $sh->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		$info = $postlogger->get_info();

		$this->assertArrayHasKey( 'name', $info );
		$this->assertArrayHasKey( 'description', $info );
		$this->assertArrayHasKey( 'capability', $info );
		$this->assertArrayHasKey( 'messages', $info );

		$this->assertTrue( is_array( $info['messages'] ) );
		$this->assertTrue( is_array( $info['labels'] ) );
		$this->assertTrue( is_array( $info['labels']['search'] ) );
		$this->assertTrue( is_array( $info['labels']['search']['options'] ) );
	}
}
