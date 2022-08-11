<?php
use \Step\Acceptance\Admin;

class PluginRedirectionLoggerCest
{

    public function _before( AcceptanceTester $I, FunctionalTester $I2 ) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin('redirection');
        $I->canSeePluginActivated('redirection');
        $I->amOnAdminPage('/tools.php?page=redirection.php');

        // Go through setup wizard.
        $I->click('Start Setup');
        $I->click('Continue');
        $I->click('Finish Setup');
        
        $I->wait(3); // Ajax setups tables.
        $I->click('Continue');
        $I->click('Ready to begin!');
    }

    public function testRedirects(Admin $I) {
        // Add redirect.
        $I->fillField('[name=url]', '/my-source-url');
        $I->fillField('[name=text]', '/my-target-url');
        $I->click('Add Redirect');
        
        $I->wait('1'); // wait for ajax call.
        $I->seeLogMessage('Added a redirection for URL "/my-source-url"');
        $I->seeLogContext([
            'source_url' => '/my-source-url',
            'target_url' => '/my-target-url'
        ]);

        // Modify redirect.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Edit');

        // $I->makeHtmlSnapshot();
        $I->makeScreenshot();
        
        // $I->amOnAdminPage( 'admin.php?page=jetpack#/performance' );

        // // Enable site accelerator.
        // $I->click('label[for="toggle-0"]');
        // $I->wait(1); // Toggle takes some time to be activated.        
        // $I->seeLogMessage('Activated Jetpack module "Asset CDN"');

        // // Enable Lazy Loading for images.
        // $I->click('label[for="toggle-3"]');
        // $I->wait(1); // Toggle takes some time to be activated.        
        // $I->seeLogMessage('Activated Jetpack module "Lazy Images"');
    }
}
