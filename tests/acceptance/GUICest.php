<?php

class GUICest {

    public function test_basic_log_gui( AcceptanceTester $I ) {
                // Does not fire filters, so will not be logged.
                $I->haveUserInDatabase('erik', 'editor', ['user_pass' => 'password']);

                $I->loginAsAdmin();
                $I->amOnAdminPage( 'index.php?page=simple_history_page' );

                $I->see( 'Simple History' );
                $I->see('Logged in', '.SimpleHistoryLogitem__text');

                // Search filters, unexpanded and expanded.
                $I->dontSee('Log levels:');
                $I->dontSee('Message types:');
                $I->dontSee('users:');

                $I->click('Show search options');
                $I->see('Log levels:');
                $I->see('Message types:');
                $I->see('Users:');

                // Sidebar boxes.
                $I->see('Stats');
                $I->see('Number of events per day.');
                $I->see('Settings');
                $I->see('Visit the settings page');
                $I->see('Review this plugin if you like it');
                $I->see('Visit the support forum');
                $I->see('Donate to support development');

                $I->loginAs('erik', 'password');

                $I->amOnAdminPage( 'index.php?page=simple_history_page' );
                $I->see( 'Simple History' );
    }
}
