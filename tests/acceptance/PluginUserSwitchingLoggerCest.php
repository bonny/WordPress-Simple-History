<?php

use \Step\Acceptance\Admin;

class PluginUserSwitchingLoggerCest
{
    public function _before(Admin $I) {
        $plugin_slug = 'user-switching';
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin($plugin_slug);
        $I->canSeePluginActivated($plugin_slug);

        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);
        $I->haveUserInDatabase('erik', 'editor', ['user_pass' => 'password']);
    }

    public function switchUser(Admin $I) {
        $I->amOnAdminPage('users.php');

        // Move over second user row with Anna.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(2)');
        // Click "Switch To" link. Can not use plain "Switch to" text because link
        // contains "&nbsp;.
        $I->click('//*[@id="user-2"]/td[1]/div/span[5]/a');
        $I->seeLogMessage('Switched to user "anna" from user "admin"');
        
        // Switch back to admin.
        $I->wait(2);
        $I->makeScreenshot();
        
        // Click "Switch back to admin" link.
        $I->click('//*[@id="user_switching"]/p/a');
        $I->seeLogMessage('Switched back to user "admin" from user "anna"');
    }
}
