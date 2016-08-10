<?php

class SimpleHistoryTest extends WP_UnitTestCase {

	// https://phpunit.de/manual/current/en/fixtures.html
    public static function setUpBeforeClass() {

    }


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
			"export",
            "debug"
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
			"logger",
			"level",
			"message",
			"occasionsID",
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

	// test logging and retrieving logs
	function test_logging() {

		global $wpdb;

		$table_name_simple_history = $wpdb->prefix . SimpleHistory::DBTABLE;

		$refl_log_levels = new ReflectionClass('SimpleLoggerLogLevels');
		$log_levels = (array) $refl_log_levels->getConstants();

		$refl_log_initiators = new ReflectionClass('SimpleLoggerLogInitiators');
		$log_initiators = (array) $refl_log_initiators->getConstants();

		foreach ( $log_levels as $level_const => $level_str ) {

			foreach ( $log_initiators as $initiator_const => $initiator_str ) {

				$message = "This is a message with log level $level_str";

				SimpleLogger()->log( $level_str, $message, array(
					"_initiator" => $initiator_str
				) );

				// Last logged message in db should be the above
				$db_row = $wpdb->get_row( "SELECT logger, level, message, initiator FROM $table_name_simple_history ORDER BY id DESC LIMIT 1", ARRAY_A );

				$expected_row = array(
					'logger' => "SimpleLogger",
					'level' => $level_str,
					'message' => $message,
					'initiator' => $initiator_str
				);

				$this->assertEquals( $expected_row, $db_row, "logged event in db" );

			}

		}

		// TODO: test logging with context

	}

	function test_log_query() {

		// Add admin user
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$args = array(
			"posts_per_page" => 1
		);

		$logQuery = new SimpleHistoryLogQuery();
		$queryResults = $logQuery->query( $args );

		// The latest row should be the user we create above
		$this->assertArrayHasKey( "total_row_count", $queryResults );
		$this->assertArrayHasKey( "pages_count", $queryResults );
		$this->assertArrayHasKey( "page_current", $queryResults );
		$this->assertArrayHasKey( "page_rows_from", $queryResults );
		$this->assertArrayHasKey( "page_rows_to", $queryResults );
		$this->assertArrayHasKey( "max_id", $queryResults );
		$this->assertArrayHasKey( "min_id", $queryResults );
		$this->assertArrayHasKey( "log_rows_count", $queryResults );
		$this->assertArrayHasKey( "log_rows", $queryResults );

		$this->assertCount( 1, $queryResults["log_rows"] );

		$this->assertObjectHasAttribute( "id", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "logger", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "level", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "date", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "message", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "initiator", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "occasionsID", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "subsequentOccasions", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "rep", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "repeated", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "occasionsIDType", $queryResults["log_rows"][0] );
		$this->assertObjectHasAttribute( "context", $queryResults["log_rows"][0] );

	}

	function test_get_info() {

		$sh = SimpleHistory::get_instance();

		$postlogger = $sh->getInstantiatedLoggerBySlug( "SimplePostLogger" );
		$info = $postlogger->getInfo();

		$this->assertArrayHasKey( "name", $info );
		$this->assertArrayHasKey( "description", $info );
		$this->assertArrayHasKey( "capability", $info );
		$this->assertArrayHasKey( "messages", $info );

		$this->assertTrue( is_array( $info["messages"] ) );
		$this->assertTrue( is_array( $info["labels"] ) );
		$this->assertTrue( is_array( $info["labels"]["search"] ) );
		$this->assertTrue( is_array( $info["labels"]["search"]["options"] ) );

	}


}
