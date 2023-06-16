<?php
use \Step\Acceptance\Admin;

class JetpackLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin('jetpack');
        $I->canSeePluginActivated('jetpack');
    }

    public function test_that_jetpack_modules_can_be_activated(Admin $I) {        
        $I->amOnAdminPage( 'admin.php?page=jetpack#/performance' );

        // Enable site accelerator.
        $I->click('#inspector-toggle-control-0');
        $I->wait(1); // Toggle takes some time to be activated.    
        $I->seeLogMessage('Activated Jetpack module "Asset CDN"');

        // Enable Lazy Loading for images.
        $I->click('#inspector-toggle-control-3');
        $I->wait(1); // Toggle takes some time to be activated.        
        $I->seeLogMessage('Activated Jetpack module "Lazy Images"');
    }
}
