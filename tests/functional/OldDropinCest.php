<?php

class OldDropinCest {
        public function test_that_old_dropin_works( FunctionalTester $I ) {
                $I->loginAsAdmin();
                $I->amOnAdminPage('admin.php?page=simple_history_settings_page');
                $I->canSee('Dropin example tab');
        }
}
