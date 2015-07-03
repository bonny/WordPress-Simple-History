<?php

class SampleTest extends WP_UnitTestCase {

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

	/*
	
	# Todo
	
	- test loggers and dropins loaded
	- test logging a thing
	- test logging of different log levels
	- test context
	- test api query

	*/

}

