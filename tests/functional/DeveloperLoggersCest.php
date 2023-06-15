<?php

class DeveloperLoggerCest {
    public function _before( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin('developer-loggers-for-simple-history');
    }

	public function test_that_developer_loggers_can_be_activated( FunctionalTester $I ) {
        $I->canSeePluginActivated('developer-loggers-for-simple-history');
    }

    public function test_that_developer_loggers_settings_tab_exist( FunctionalTester $I ) {
        $I->amOnAdminPage('options-general.php?page=simple_history_settings_menu_slug');
        $I->canSee('Developer loggers');
    }

    public function test_that_developer_loggers_tab_contents_exist( FunctionalTester $I ) {
        $I->amOnAdminPage('options-general.php?page=simple_history_settings_menu_slug&selected-tab=DeveloperLoggers');
        $I->canSee('Enabled loggers and plugins');
        $I->canSee('HTTP API logger');
        $I->canSee('WP Mail Logger');
    }
}
