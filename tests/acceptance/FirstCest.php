<?php

class FirstCest {

	public function visitPluginPage( AcceptanceTester $I ) {
        $I->loginAsAdmin();
        
        // Kör inte filters etc. så loggas inte.
        $I->haveUserInDatabase('erik', 'editor', ['user_pass' => 'password']);

        $I->amOnAdminPage( 'index.php?page=simple_history_page' );
        //$I->waitForElementVisible('.SimpleHistoryLogitemsWrap');
        $I->see( 'Simple History' );
        $I->see('Logged in', '.SimpleHistoryLogitem__text');
        // $I->amOnPage('/');
        //$I->makeHtmlSnapshot();

        // Expanded search filters.
        $I->click('Show search options');
        $I->see('Log levels:');
        $I->see('Message types:');
        $I->see('Users:');

        $I->loginAs('erik', 'password'); 

        $I->makeScreenshot();
        // $I->pause();
	}
}
