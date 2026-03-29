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
        // Export triggers a file download — no page navigation or success message to wait for.
        $I->wait(1);
        $I->seeLogMessage('Created XML export');
    }
}
