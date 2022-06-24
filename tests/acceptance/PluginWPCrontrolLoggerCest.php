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
    }
}
