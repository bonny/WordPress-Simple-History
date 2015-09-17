<?php

class SimpleHistoryTest extends WP_UnitTestCase {

	function test_sample() {

		// replace this with some actual testing code
		$this->assertTrue( true );
	}

	function test_history_setup() {

		$this->assertTrue( defined("SIMPLE_HISTORY_VERSION") );
		$this->assertTrue( defined("SIMPLE_HISTORY_PATH") );
		$this->assertTrue( defined("SIMPLE_HISTORY_BASENAME") );
		$this->assertTrue( defined("SIMPLE_HISTORY_DIR_URL") );
		$this->assertTrue( defined("SIMPLE_HISTORY_FILE") );

		$this->assertFalse( defined("SIMPLE_HISTORY_DEV") );
		$this->assertFalse( defined("SIMPLE_HISTORY_LOG_DEBUG") );

	}

	function test_history_classes() {

		$this->assertTrue( class_exists("SimpleHistory") );
		$this->assertTrue( class_exists("SimpleHistoryLogQuery") );

		$sh = SimpleHistory::get_instance();		
		$this->assertTrue( is_object($sh) );
		$this->assertTrue( is_a($sh, "SimpleHistory") );

	}

	function test_default_loggers() {

		$sh = SimpleHistory::get_instance();
		$loggers = $sh->getInstantiatedLoggers();
		
		$arr_default_loggers = array(
			"SimpleCommentsLogger",
			"SimpleCoreUpdatesLogger",
			"SimpleExportLogger",
			"SimpleLegacyLogger",
			"SimpleLogger",
			"SimpleMediaLogger",
			"SimpleMenuLogger",
			"SimpleOptionsLogger",
			"SimplePluginLogger",
			"SimplePostLogger",
			"SimpleThemeLogger",
			"SimpleUserLogger",
		);
	
		foreach ($arr_default_loggers as $slug) {

			$this->assertArrayHasKey( $slug, $loggers );

		}

	}

	function test_default_dropins() {

		$sh = SimpleHistory::get_instance();
		$dropins = $sh->getInstantiatedDropins();
		
		$arr_default_dropins = array(
			"SimpleHistoryDonateDropin",
			"SimpleHistoryExportDropin",
			"SimpleHistoryFilterDropin",
			"SimpleHistoryIpInfoDropin",
			"SimpleHistoryNewRowsNotifier",
			"SimpleHistoryRSSDropin",
			"SimpleHistorySettingsLogtestDropin",
			"SimpleHistorySettingsStatsDropin",
			"SimpleHistorySidebarDropin",
		);
	
		foreach ($arr_default_dropins as $slug) {

			$this->assertArrayHasKey( $slug, $dropins );

		}

	}
	
	function test_default_settings_tabs() {

		$sh = SimpleHistory::get_instance();
		$settings_tabs = $sh->getSettingsTabs();
		$arr_default_settings = array(
			"settings",
			"export"
		);

		$loaded_settings_slugs = wp_list_pluck( $settings_tabs, "slug" );
		$this->assertEquals($arr_default_settings, $loaded_settings_slugs);

	}

	function test_install() {

		global $wpdb;

		// Test table simple history
		$table_name_simple_history = $wpdb->prefix . SimpleHistory::DBTABLE;
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name_simple_history ) );
		$this->assertEquals( $table_name_simple_history, $table_exists );

		$table_cols = $wpdb->get_col("DESCRIBE $table_name_simple_history");
		$expected_table_cols = array(
			"id",
			"date",
			"action",
			"object_type",
			"object_subtype",
			"user_id",
			"object_id",
			"object_name",
			"action_description",
			"logger",
			"level",
			"message",
			"occasionsID",
			"type",
			"initiator"
		);

		$this->assertEquals($expected_table_cols, $table_cols, "cols in history table should be the same");


		// Test table simple history contexts
		$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name_contexts ) );
		$this->assertEquals( $table_name_contexts, $table_exists );

		$table_cols_context = $wpdb->get_col("DESCRIBE $table_name_contexts");
		$expected_table_cols_context = array(
			"context_id",
			"history_id",
			"key",
			"value"
		);

		$this->assertEquals($expected_table_cols_context, $table_cols_context, "cols in context table should be the same");


	}

	function test_loglevels_and_initiators() {

		$refl = new ReflectionClass('SimpleLoggerLogLevels');
		$log_levels = (array) $refl->getConstants();

		$expected_log_levels = array(
			'EMERGENCY' => "emergency",
			'ALERT' => "alert",
			'CRITICAL' => "critical",
			'ERROR' => "error",
			'WARNING' => "warning",
			'NOTICE' => "notice",
			'INFO' => "info",
			'DEBUG' => "debug"
		);

		$this->assertEquals( $expected_log_levels, $log_levels, "log levels" );

		$refl = new ReflectionClass('SimpleLoggerLogInitiators');
		$log_initiators = (array) $refl->getConstants();

		$expected_log_initiators = array(
			"WP_USER" => 'wp_user',
			"WEB_USER" => 'web_user',
			"WORDPRESS" => "wp",
			"WP_CLI" => "wp_cli",
			"OTHER" => 'other'
		);

		$this->assertEquals( $expected_log_initiators, $log_initiators, "log initiators" );


	}

	function test_logging() {

		// test logging and retrieving logs
		SimpleLogger()->info("This is a message sent to the log");


	}



	/*
	
	# Todo
	
	- test logging a thing
	- test logging of different log levels
	- test context
	- test api query

	*/

}

