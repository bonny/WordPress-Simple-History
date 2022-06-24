<?php

use \Step\Acceptance\Admin;

class PluginWPCrontrolLoggerCest
{
    public function activatePlugin(Admin $I) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin('wp-crontrol');
        $I->canSeePluginActivated('wp-crontrol');
    }
}
