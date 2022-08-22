<?php

use \Step\Acceptance\Admin;

class SimpleMediaLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function addMedia(Admin $I) {
        // Add image.
        $I->amOnAdminPage('media-new.php');
        $I->click("browser uploader");
        $I->attachFile('#async-upload', 'Image 1.jpg');
        $I->click("Upload");
        $I->seeLogMessage('Created attachment "Image 1"');
        $I->seeLogContext([
            'post_type' => 'attachment',
            'attachment_title' => 'Image 1',
            'attachment_mime' => 'image/jpeg',
            'attachment_filesize' => '601223',
        ]);

        // 
        $I->amOnAdminPage('upload.php?mode=list');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Edit');
        $I->fillField('#attachment_alt', 'My image alt text');
        $I->fillField('#attachment_caption', 'My image excerpt and caption');
        $I->fillField('#attachment_content', 'My image description and content');
        $I->click('Update');
        $I->seeLogMessage('Edited attachment "Image 1"');
        $I->seeLogContext([
            'post_type' => 'attachment',
            'attachment_id' => '2',
            'attachment_title' => 'Image 1',
            'attachment_mime' => 'image/jpeg',
        ]);
    }
}
