<?php

use \Step\Acceptance\Admin;

class PluginDuplicatePostLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
        $I->activatePluginByFile('duplicate-post/duplicate-post.php');
    }
    
    public function clonePage(Admin $I) {
        $I->loginAsAdmin();
        $I->havePageInDatabase(['post_title' => 'Test page']);
        $I->amOnAdminPage('edit.php?post_type=page');
        $I->moveMouseOver('.table-view-list tbody tr:nth-child(1)');
        $I->click('Clone');
        $I->seeLogMessage('Cloned "Test page" to a new post');
    }
}
