<?php

/**
 * Test for issue 373: "Suggestion: Allow for a cleaner way to selectively shortcut loggings"
 * https://github.com/bonny/WordPress-Simple-History/issues/373.
 *
 * Tests that the core loggers can be disabled via filter.
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

        $I->amGoingTo('Verify plugin activation does not cause errors');

        // Go to Help & Support page to verify no critical errors.
        $I->amOnAdminPage('admin.php?page=simple_history_help_support');
        $I->dontSee('There has been a critical error on this website.');

        // Check that main feed works with reduced loggers.
        $I->amGoingTo('Check that the main history feed works');
        $I->amOnPage('/wp-admin/index.php?page=simple_history_page');
        $I->seeResponseCodeIsSuccessful('Response code is successful when visiting simple history page');
        $I->dontSee('There has been a critical error on this website.');

        $I->amGoingTo('Deactivate the test plugin');
        $I->amOnPluginsPage();
        $I->deactivatePlugin('issue-373-disable-core-loggers');
    }
}
