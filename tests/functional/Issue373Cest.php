<?php

/**
 * Test for issue 373: "Suggestion: Allow for a cleaner way to selectively shortcut loggings"
 * https://github.com/bonny/WordPress-Simple-History/issues/373.
 * 
 * Tests that the core loggers can be disabled.
 * 
 * Run with Docker Compose:
 * `$ docker-compose run --rm php-cli vendor/bin/codecept run functional:test_issue_373`
 */
class Issue373Cest {
	public function test_issue_373( FunctionalTester $I ) {        
        // Log a 404 error to the 404 test logger,
        // so we have something in the db/log.
        $I->amOnPage('index.php?p=404');
        $I->seeResponseCodeIs(404);
        
        $I->loginAsAdmin();
        $I->amOnPluginsPage();   
        $I->activatePlugin('issue-373-disable-core-loggers');
        
        $I->amGoingTo('See if any loggers are active on the debug tab');
        
        // Go to debug tab/Help & Support » Debug
        $I->amOnAdminPage('admin.php?page=simple_history_help_support&selected-tab=simple_history_help_support_general&selected-sub-tab=simple_history_help_support_debug');
        $I->dontSee('There has been a critical error on this website.');       
        $I->see('Listing 2 loggers');
        
        // Check that main feed works.
        $I->amGoingTo('Check that the main history feed works');
        $I->amOnPage('/wp-admin/index.php?page=simple_history_page');
        $I->seeResponseCodeIsSuccessful('Response code is successful when visiting simple history page');
        
        $I->amGoingTo('Deactivate the test plugin');
        $I->amOnPluginsPage();   
        $I->deactivatePlugin('issue-373-disable-core-loggers');
    }
}
