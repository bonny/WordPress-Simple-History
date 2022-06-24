<?php
use \Step\Acceptance\Admin;

class JetpackLoggerCest
{
    public function activatePlugin(Admin $I) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin('jetpack');
        $I->canSeePluginActivated('jetpack');
        
        $I->amOnAdminPage( 'admin.php?page=jetpack#/performance' );

        // Enable site accelerator.
        $I->click('label[for="toggle-0"]');
        $I->wait(1); // Toggle takes some time to be activated.        
        $I->seeLogMessage('Activated Jetpack module "Asset CDN"');

        // Enable Lazy Loading for images.
        $I->click('label[for="toggle-3"]');
        $I->wait(1); // Toggle takes some time to be activated.        
        $I->seeLogMessage('Activated Jetpack module "Lazy Images"');
    }
}
