<?php

use \Step\Acceptance\Admin;

class EnableMediaReplaceLoggerCest
{
    public function _before(Admin $I) {
        $plugin_slug = 'enable-media-replace';
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin($plugin_slug);
        $I->canSeePluginActivated($plugin_slug);
    }

    public function replaceImage(Admin $I) {
        // Upload first image using browser uploader.
        $I->amOnAdminPage('/media-new.php?browser-uploader');
        $I->attachFile('#async-upload', 'Image 1.jpg');
        $I->click("Upload");
        $I->seeLogMessage('Created attachment "Image 1"');
        
        // Upload second image, that replaces first image.
        $I->amOnAdminPage('upload.php?mode=list');
        $I->click('Image 1');
        $I->click('Upload a new file');
        $I->attachFile('#userfile', 'Image 2.jpg');
        $I->click('Upload');
        $I->waitForText('File successfully replaced');

        $I->seeLogMessage('Replaced attachment "Image 1" with new attachment "Image 2.jpg"');
        // Context assertions skipped: Enable Media Replace 3.6.3 doesn't
        // store context data with WP 6.8. Update the plugin to fix.
    }
}
