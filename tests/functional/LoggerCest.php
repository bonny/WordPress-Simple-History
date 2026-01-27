<?php

/**
 * Tests that a custom logger is loaded and its loaded() method is called.
 * The test logger outputs a message in the admin footer.
 */
class LoggerCest {
	public function test_can_get_logger_output( FunctionalTester $I ) {
        $I->loginAsAdmin();

        // Go to any admin page - the test logger outputs in the footer.
        $I->amOnAdminPage('admin.php?page=simple_history_page');
        $I->dontSee('There has been a critical error on this website.');

        // Verify the test logger's footer output is present.
        $I->canSee('Output in footer from the logger with slug tests');
    }
}

