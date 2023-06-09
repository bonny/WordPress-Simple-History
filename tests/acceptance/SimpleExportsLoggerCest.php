<?php

use \Step\Acceptance\Admin;

class SimpleExportsLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function exportXml(Admin $I) {
        $I->amOnAdminPage('export.php');
        $I->click("Download Export File");
        $I->seeLogMessage('Created XML export');
    }
}
