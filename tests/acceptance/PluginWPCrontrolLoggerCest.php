<?php

use \Step\Acceptance\Admin;

class PluginWPCrontrolLoggerCest
{
    public function activatePlugin(Admin $I) {
        $I->loginAsAdmin();
        $I->amOnPluginsPage();
        $I->activatePlugin('wp-crontrol');
        $I->canSeePluginActivated('wp-crontrol');
        
        // "Cron Schedules" tab.
        $I->amOnAdminPage( 'options-general.php?page=crontrol_admin_options_page' );
        
        // Add a schedule, message key "added_new_event".
        $I->fillField("#crontrol_schedule_internal_name", "my_123_seconds_interval");
        $I->fillField("#crontrol_schedule_interval", 123);
        $I->fillField("#crontrol_schedule_display_name", "Every 123 seconds");
        $I->click('Add Cron Schedule');
        $I->seeLogMessage('Added cron schedule "my_123_seconds_interval"');

        // "Cron Events" tab.
        $I->amOnAdminPage('tools.php?page=crontrol_admin_manage_page');
        
        // Manually run an event. Message key 'ran_event'.
        // Manually run cron "wp_version_check" by clicking link "Run now" for that cron.
        // HTML is like this: <a href="http://wordpress-stable.test/wp-admin/tools.php?page=crontrol_admin_manage_page&amp;

        // Add class .visible to div.row-actions to make link visible.
        $I->executeJS("jQuery('div.row-actions').addClass('visible');");
        // Click "Run now" link.
        $I->click('a[href*="crontrol_action=run-cron"][href*=version]');
        $I->seeLogMessage('Manually ran cron event "wp_version_check"');
    }

    public function addNewCronEventAndEditCronEvent(Admin $I) {
        // Add cron event. Message key 'added_new_event'.
        $I->amOnAdminPage('tools.php?page=crontrol_admin_manage_page');
        $I->click('Add New', '.wrap');
        $I->fillField('#crontrol_hookname', 'A Manually Added Cron Event');
        $I->fillField('#crontrol_args', '["i","want",25,"cakes"]');
        $I->selectOption('input[name=crontrol_next_run_date_local]', 'Tomorrow');
        $I->click('Add Event');
        $I->seeLogMessage('Added cron event "A Manually Added Cron Event"');

        // Edit cron event. Message key 'edited_event'.
        $I->amOnAdminPage('tools.php?page=crontrol_admin_manage_page');
        
        $I->executeJS("jQuery('div.row-actions').addClass('visible');");
        // Click "Edit now" link.
        $I->click('a[href*="crontrol_action=edit-cron"][href*=Manually]');
        $I->click('Update Event');
        $I->seeLogMessage('Edited cron event "A Manually Added Cron Event"');
    }
}
