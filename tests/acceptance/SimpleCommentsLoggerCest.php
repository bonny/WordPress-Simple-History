<?php

use \Step\Acceptance\Admin;

class SimpleCommentsLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function editComment(Admin $I) {
        $post_id = $I->havePostInDatabase([
            'post_title' => 'My test post'
        ]);
        $I->haveManyCommentsInDatabase(1, $post_id);

        // Unapprove.
        $I->amOnAdminPage('edit-comments.php');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Unapprove');
        $I->waitForJqueryAjax();
        $I->seeLogMessage('Unapproved a comment to "My test post" by Mr WordPress ()');

        // Approve.
        $I->click('Approve');
        $I->waitForJqueryAjax();
        $I->seeLogMessage('Approved a comment to "My test post" by Mr WordPress ()');

        // Trash.
        $I->click('Trash');
        $I->waitForJqueryAjax();
        $I->seeLogMessage('Trashed a comment to "My test post" by Mr WordPress ()');

        // Untrash.
        $I->amOnAdminPage('edit-comments.php?comment_status=trash');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Restore');
        $I->waitForJqueryAjax();
        $I->seeLogMessage('Restored a comment to "My test post" by Mr WordPress () from the trash');

        // Trash and Delete permanently.
        $I->amOnAdminPage('edit-comments.php');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Trash');
        $I->amOnAdminPage('edit-comments.php?comment_status=trash');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Delete Permanently');
        $I->waitForJqueryAjax();
        $I->seeLogMessage('Deleted a comment to "My test post" by Mr WordPress ()');
    }
}
