<?php

use \Step\Acceptance\Admin;

class SimpleExportsLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function editComment(Admin $I) {
        $I->amOnAdminPage('export.php');
        $I->click("Download Export File");
        $I->seeLogMessage('Created XML export');
        $I->seeLogContextDebug();
        $I->makeScreenshot();
    }
}
