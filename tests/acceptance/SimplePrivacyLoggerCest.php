<?php
use \Step\Acceptance\Admin;

/**
 * 'privacy_data_export_emailed'           => _x( 'Sent email with personal data export download info for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
 * 'privacy_data_export_request_confirmed' => _x( 'Confirmed data export request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
 * 'data_erasure_request_sent'             => _x( 'Sent data erasure request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
 * 'data_erasure_request_confirmed'        => _x( 'Confirmed data erasure request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
 */

class SimplePrivacyLoggerCest
{
    /**
     * Go to privacy page and create a new privacy page.
     *
     * privacy_page_created
     */
    public function logPrivacyPageCreated(Admin $I)
    {
        $I->loginAsAdmin();
        $I->amOnAdminPage('options-privacy.php');

        $I->click('Create');

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Created a new privacy page "Privacy Policy"', 0);
        $I->seeLogContext([
            'new_post_title' => 'Privacy Policy',
            'prev_post_id' => 0,
            'new_post_id' => 2,
        ]);
    }

    /**
     * Go to privacy page and select a new privacy page.
     *
     * privacy_page_set
     */
    public function logPrivacyPageSet(Admin $I)
    {
        $I->havePageInDatabase([
            'post_title' => 'My new privacy page',
        ]);

        $I->loginAsAdmin();
        $I->amOnAdminPage('options-privacy.php');

        $I->selectOption('#page_for_privacy_policy', 'My new privacy page');
        $I->click('Use This Page');

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Set privacy page to page "My new privacy page"');
    }

    public function logDataExportRequest(Admin $I)
    {
        // Message key: privacy_data_export_requested.
        // Go to export personal data page and add data export request.
        $I->haveUserInDatabase('myNewUser');

        $I->loginAsAdmin();
        $I->amOnAdminPage('export-personal-data.php');
        $I->fillField('#username_or_email_for_privacy_request', 'myNewUser');
        $I->click('Send Request');

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Requested a personal privacy data export for user "myNewUser@example.com"');
        $I->seeLogContext([
            'send_confirmation_email' => 1,
        ]);

        /**
         * User downloads a User Data Export from the Tools > Export Personal Data admin page.
         * Message key: privacy_data_export_admin_downloaded
         * Waits for Ajax call so is time consuming.
         */
        $I->amOnAdminPage('export-personal-data.php');
        $I->moveMouseOver('.table-view-list tbody tr:nth-child(1)');
        $I->click('Download personal data');
        $I->waitForText('This user’s personal data export file was downloaded.');
        $I->seeLogMessage('Downloaded personal data export file for user "myNewUser@example.com"');

        /**
         * privacy_data_export_emailed
         * When data export request is generated, the user
         * gets and email with a confirm link. We need to click that link.
         * We maybe can use filter 'user_request_action_email_subject' to catch the confirm url.
         * `$subject = apply_filters( 'user_request_action_email_subject', $subject, $email_data['sitename'], $email_data );`
         */
        // TODO: this test


        /**
         * privacy_data_export_completed
         */
        $I->amOnAdminPage('export-personal-data.php');
        $I->moveMouseOver('.table-view-list tbody tr:nth-child(1)');
        $I->click('Complete request');
        $I->seeLogMessage('Marked personal data export request as complete for "myNewUser@example.com"');

        /**
         * Remove request after it's done/completed.
         * privacy_data_export_removed
         */
        $I->amOnAdminPage('export-personal-data.php');
        $I->moveMouseOver('.table-view-list tbody tr:nth-child(1)');
        $I->click('Remove request');
        $I->seeLogMessage('Removed data export request for "myNewUser@example.com"');
    }

    /**
     * Tools > Erase Personal Data
     */
    public function logDataErasure(Admin $I)
    {
        $I->haveUserInDatabase('myNewUser');

        // data_erasure_request_added
        $I->loginAsAdmin();
        $I->amOnAdminPage('erase-personal-data.php');
        $I->fillField('#username_or_email_for_privacy_request', 'myNewUser');
        $I->click('Send Request');

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Added personal data erasure request for "myNewUser@example.com"');
        $I->seeLogContext([
            'send_confirmation_email' => 1,
        ]);

        // data_erasure_request_completed
        $I->amOnAdminPage('erase-personal-data.php');
        $I->moveMouseOver('.table-view-list tbody tr:nth-child(1)');
        $I->click('Complete request');
        $I->seeLogMessage('Marked personal data erasure request as complete for "myNewUser@example.com"');

        // data_erasure_request_removed
        /*
        action: wp-privacy-erase-personal-data
        eraser: 1
        id: 74
        page: 1
        security: c22f7c8669
        */
        $I->amOnAdminPage('erase-personal-data.php');
        $I->moveMouseOver('.table-view-list tbody tr:nth-child(1)');
        $I->click('Force erase personal data');
        $I->waitForText('Erasure complete');
        $I->seeLogMessage('Erased personal data for "myNewUser@example.com"');

        // data_erasure_request_removed
        $I->amOnAdminPage('erase-personal-data.php');
        $I->moveMouseOver('.table-view-list tbody tr:nth-child(1)');
        $I->click('Remove request');
        $I->seeLogMessage('Removed personal data removal request for "myNewUser@example.com"');

    }
}
