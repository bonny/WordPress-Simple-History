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

                $I->click('Filters');
                $I->see('Log levels');
                $I->see('Message types');
                $I->see('Users');

                // Sidebar boxes.
                $I->see('History Insights');

                // Erik editor
                $I->loginAs('erik', 'password');
                $I->amOnAdminPage( 'index.php?page=simple_history_page' );
                $I->see( 'Simple History' );
	}
}
