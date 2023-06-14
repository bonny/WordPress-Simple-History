<?php

class DropinCest {
	public function test_can_see_dropin_tab_on_settings_page( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('options-general.php?page=simple_history_settings_menu_slug');
        $I->canSee('Namespaced dropin example tab');
    }

    public function test_can_see_dropin_tab_output( FunctionalTester $I ) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('options-general.php?page=simple_history_settings_menu_slug&selected-tab=tests_dropin_settings_tab_slug');
        $I->canSee('Namespaced dropin example page output');
    }
}
