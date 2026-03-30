<?php

use \Step\Acceptance\Admin;

class PluginUserSwitchingLoggerCest
{
    public function _before(Admin $I) {
        $plugin_slug = 'user-switching';
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $isActive = $I->executeJS("return !!document.getElementById('deactivate-{$plugin_slug}')");
        if (!$isActive) {
            $I->activatePlugin($plugin_slug);
        }
        $I->canSeePluginActivated($plugin_slug);

        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);
        $I->haveUserInDatabase('erik', 'editor', ['user_pass' => 'password']);
    }

    public function switchUser(Admin $I) {
        $I->amOnAdminPage('users.php');

        // Move over anna's row and click "Switch To".
        $I->moveMouseOver('//td[contains(.,"anna")]/parent::tr');
        $I->click('//td[contains(.,"anna")]/parent::tr//a[contains(@href,"switch_to_user")]');
        $I->seeLogMessage('Switched to user "anna" from user "admin"');

        // Wait for the dashboard to load after switching user.
        $I->waitForElement('#user_switching');

        // Click "Switch back to admin" link.
        $I->click('//*[@id="user_switching"]/p/a');
        $I->seeLogMessage('Switched back to user "admin" from user "anna"');
    }
}
