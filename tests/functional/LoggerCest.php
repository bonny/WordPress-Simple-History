<?php

class LoggerCest {
	public function test_can_get_logger_output( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('admin.php?page=simple_history_help_support&selected-tab=simple_history_help_support_general&selected-sub-tab=simple_history_help_support_debug');
        $I->canSee('Tests logger');
        $I->canSee('Output in footer from the logger with slug tests');
    }
}

