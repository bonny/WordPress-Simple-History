<?php

class OldDropinCest {
        public function test_that_old_dropin_works( FunctionalTester $I ) {
                $I->loginAsAdmin();
                $I->amOnAdminPage('options-general.php?page=simple_history_settings_menu_slug');
                $I->canSee('Dropin example tab');
        }
}
