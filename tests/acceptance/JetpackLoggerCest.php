<?php
use \Step\Acceptance\Admin;

class JetpackLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $isActive = $I->executeJS("return !!document.getElementById('deactivate-jetpack')");
        if (!$isActive) {
            $I->activatePlugin('jetpack');
        }
        $I->canSeePluginActivated('jetpack');
    }

    public function test_that_jetpack_modules_can_be_activated(Admin $I) {
        $I->amOnAdminPage( 'admin.php?page=jetpack#/performance' );

        // Enable site accelerator.
        // Jetpack uses React/fetch for toggles, not jQuery AJAX.
        $I->click('#inspector-toggle-control-0');
        $I->wait(1);
        $I->seeLogMessage('Activated Jetpack module "Asset CDN"');

        // Enable Lazy Loading for images.
        $I->click('#inspector-toggle-control-3');
        $I->wait(1);
        $I->seeLogMessage('Activated Jetpack module "Lazy Images"');
    }
}
