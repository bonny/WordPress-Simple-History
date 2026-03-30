<?php
use \Step\Acceptance\Admin;

/**
 * Redirection plugin uses React/fetch for AJAX, not jQuery.
 * waitForJqueryAjax() won't work — wait(1) is needed after actions.
 */
class PluginRedirectionLoggerCest
{

    public function _before( AcceptanceTester $I, FunctionalTester $I2 ) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $isActive = $I->executeJS("return !!document.getElementById('deactivate-redirection')");
        if (!$isActive) {
            $I->activatePlugin('redirection');
        }
        $I->canSeePluginActivated('redirection');
        $I->amOnAdminPage('/tools.php?page=redirection.php');

        // Go through setup wizard.
        $I->click('Start Setup');
        $I->click('Continue');
        $I->click('Finish Setup');

        // Wait for AJAX table setup to complete (shows progress bar then "Continue").
        $I->waitForText('Continue', 30);
        $I->click('Continue');
        $I->waitForText('Ready to begin!');
        $I->click('Ready to begin!');
    }

    public function testRedirects(Admin $I) {
        // Add redirect.
        $I->fillField('[name=url]', '/my-source-url');
        $I->fillField('[name=text]', '/my-target-url');
        $I->click('Add Redirect');
        $I->wait(1);
        $I->seeLogMessage('Added a redirection for URL "/my-source-url"');
        $I->seeLogContext([
            'source_url' => '/my-source-url',
            'target_url' => '/my-target-url'
        ]);

        // Modify redirect.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Edit');
        $I->fillField('.redirect-edit [name=url]', '/my-modified-source-url');
        $I->fillField('.redirect-edit [name=text]', '/my-modified-target-url');
        $I->click("Save");
        $I->wait(1);
        $I->seeLogMessage('Edited redirection for URL "/my-source-url"');

        // Disable redirect.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Disable');
        $I->wait(1);
        $I->seeLogMessage('Disabled redirection for 1 URL(s)');

        // Enable redirect.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Enable');
        $I->wait(1);
        $I->seeLogMessage('Enabled redirection for 1 URL(s)');

        // Delete redirect.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Delete');
        $I->acceptPopup();
        $I->wait(1);
        $I->seeLogMessage('Deleted redirection for 1 URL(s)');
    }

    public function testGroups(Admin $I) {

        // Go to page before some actions because the order is different
        // directly after adding via Ajax and after a page reload.
        $admin_page = '/tools.php?page=redirection.php&sub=groups&direction=asc';

        // Add group.
        $I->amOnAdminPage($admin_page);
        $I->fillField('[name=name]', 'A new group');
        $I->click('Add');
        $I->wait(1);
        $I->seeLogMessage('Added redirection group "A new group"');

        // Edit group.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Edit');
        $I->fillField('.edit-groups [name=name]', 'A new group modified');
        $I->click("Save");
        $I->wait(1);
        $I->seeLogMessage('Edited redirection group "A new group"');
        $I->seeLogContext([
            'group_id' => '3',
            'new_group_name' => 'A new group modified',
            'new_group_module_id' => '1',
            'prev_group_name' => 'A new group',
            'prev_group_module_id' => '1',
        ]);

        // Disable group.
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Disable');
        $I->wait(1);
        $I->seeLogMessage('Disabled 1 redirection group(s)');

        // Enable group.
        $I->amOnAdminPage($admin_page);
        $I->wait(1);
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click('Enable');
        $I->wait(1);
        $I->seeLogMessage('Enabled 1 redirection group(s)');
    }
}
