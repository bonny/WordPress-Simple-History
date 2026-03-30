<?php

use \Step\Acceptance\Admin;

class SimpleMediaLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function addMedia(Admin $I) {
        // Add image using browser uploader.
        $I->amOnAdminPage('media-new.php?browser-uploader');
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
        $I->executeJS('document.getElementById("publish").click()');
        $I->seeLogContext([
            'post_type' => 'attachment',
            'attachment_id' => '3',
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
        // Can't use seeLogMessage at index 0 because 404 events from
        // deleted thumbnail requests may be logged after the delete event.
        $I->seeLogEventExists('Deleted {post_type} "{attachment_title}" ("{attachment_filename}")');
    }
}
