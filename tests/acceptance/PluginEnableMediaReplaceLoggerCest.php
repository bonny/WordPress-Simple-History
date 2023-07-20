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
        // Upload first image.
        $I->amOnAdminPage('/media-new.php');
        $I->click("browser uploader");
        $I->attachFile('#async-upload', 'Image 1.jpg');
        $I->click("Upload");
        $I->seeLogMessage('Created attachment "Image 1"');
        
        // Upload second image, that replaces first image.
        $I->amOnAdminPage('upload.php?mode=list');
        $I->click('Image 1');
        $I->click('Upload a new file');
        $I->attachFile('#userfile', 'Image 2.jpg');
        $I->click('Upload');
        
        $I->seeLogMessage('Replaced attachment "Image 1" with new attachment "Image 2.jpg"');        
        $I->seeLogContext([
            'prev_attachment_title' => 'Image 1',
            'new_attachment_title' => 'Image 2.jpg',
            'new_attachment_type' => 'image/jpeg',
            'new_attachment_size' => '586250',
            'replace_type' => 'replace',
        ]);
    }
}
