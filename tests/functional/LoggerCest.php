<?php

class LoggerCest {
	public function test_can_get_logger_output( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('admin.php?page=simple_history_debug');
        $I->canSee('Tests logger');
        $I->canSee('Output in footer from the logger with slug tests');
    }
}
//selected-sub-tab
