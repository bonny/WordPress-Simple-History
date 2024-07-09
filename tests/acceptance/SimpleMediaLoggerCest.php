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

        // Edit media.
        $I->amOnAdminPage('upload.php?mode=list');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Edit');
        $I->fillField('#title', 'My image title');
        $I->fillField('#attachment_alt', 'My image alt text');
        $I->fillField('#attachment_caption', 'My image excerpt and caption');
        $I->fillField('#attachment_content', 'My image description and content');
        $I->click('Update');
        $I->seeLogContext([
            'post_type' => 'attachment',
            'attachment_id' => '2',
            'attachment_title' => 'My image title',
            'attachment_title_new' => 'My image title',
            'attachment_title_prev' => 'Image 1',
            'attachment_alt_text_new' => 'My image alt text',
            'attachment_alt_text_prev' => '',
            'attachment_content_new' => 'My image description and content',
            'attachment_content_prev' => '',
            'attachment_excerpt_new' => 'My image excerpt and caption',
            'attachment_excerpt_prev' => '',
            'attachment_mime' => 'image/jpeg',
        ]);
        $I->seeLogMessage('Edited attachment "My image title"');

        // Delete media.
        $I->amOnAdminPage('upload.php?mode=list');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Delete Permanently');
        $I->acceptPopup();
        $I->waitForJqueryAjax();
        // Full image name depends on number of uploaded images...
        $I->seeLogMessageStartsWith('Deleted attachment "My image title" ("Image-1');
        $I->seeLogContext([
            'post_type' => 'attachment',
            'attachment_id' => '2',
            'attachment_title' => 'My image title',
            'attachment_mime' => 'image/jpeg',
        ]);
    }
}
