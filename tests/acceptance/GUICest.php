<?php

class GUICest {

	public function test_basic_log_gui( AcceptanceTester $I ) {
                // Does not fire filters, so will not be logged.
                $I->haveUserInDatabase('erik', 'editor', ['user_pass' => 'password']);

                $I->loginAsAdmin();
                $I->amOnAdminPage( 'admin.php?page=simple_history_admin_menu_page' );

                $I->see( 'Simple History' );
               
                // Wait for items to be loaded, or it will catch the skeleton loading items.
                $I->waitForElement( '.SimpleHistoryLogitems.is-loaded' );

                $I->waitForElement( '.SimpleHistoryLogitem__text' );

                $I->see('Logged in', '.SimpleHistoryLogitem__text' );

                // Search filters, unexpanded and expanded.
                $I->dontSee('Log levels');
                $I->dontSee('Message types');
                $I->dontSee('Enter 2 or more characters to search for users.');

                $I->click('Show search options');
                $I->see('Log levels');
                $I->see('Message types');
                $I->see('Users');

                // Sidebar boxes.
                $I->see('History Insights');
                $I->see('Most active users');
                $I->see('Unlock more features with Simple History Premium');
                
                $I->see('Review this plugin if you like it');
                $I->see('Visit the support forum');
                $I->see('Donate to support development');

                // Erik editor
                $I->loginAs('erik', 'password'); 
                $I->amOnAdminPage( 'index.php?page=simple_history_page' );
                $I->see( 'Simple History' );
	}
}
