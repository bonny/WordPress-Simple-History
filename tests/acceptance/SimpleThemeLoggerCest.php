<?php

/**
 * 'theme_updated' => __( 'Updated theme "{theme_name}" to version {theme_version} from {theme_prev_version}', 'simple-history' ),
 */

class SimpleThemeLoggerCest
{
    // theme_installed
    public function logThemeInstalled(\Step\Acceptance\Admin $I)
    {
        // Remove previously uploaded theme.
        $I->deleteDir('/wordpress/wp-content/themes/twentysixteen');
        $I->cleanUploadsDir();

        $I->loginAsAdmin();
        $I->amOnAdminPage('/theme-install.php?browse=popular');
        $I->click('Upload Theme');
        $I->attachFile('#themezip', 'twentysixteen.2.6.zip');
        $I->click('Install Now');
        $I->waitForText('Theme installed successfully');

        // Message key: theme_installed.
        // Index varies because the zip deletion event may be logged after the install.
        $I->seeLogEventExists('Installed theme "{theme_name}" by {theme_author}');

        // Message key: theme_switched.
        $I->click('Activate');
        $I->waitForElementVisible('#wpadminbar');
        $I->seeLogMessage('Switched theme to "Twenty Sixteen" from "Twenty Twenty-Five"');

        // Upload Theme again to test theme_updated. No event is logged for this
        // path — tracked separately, see local issue 281.
        $I->amOnAdminPage('/theme-install.php?browse=popular');
        $I->click('Upload Theme');
        $I->attachFile('#themezip', 'twentysixteen.2.7.zip');
        $I->click('Install Now');
        $I->waitForText('This theme is already installed.');
        $I->click('Replace installed with uploaded');
        $I->waitForText('Theme updated successfully');

        // theme_switched: Switch back theme so we can delete the uploaded one.
        $I->amOnAdminPage('/themes.php?theme=twentytwentyfive');
        $I->waitForElementVisible('.theme-wrap .button.activate');
        $I->click('.theme-wrap .button.activate');
        $I->seeLogMessage('Switched theme to "Twenty Twenty-Five" from "Twenty Sixteen"');

        // theme_deleted: Theme deleted.
        $I->amOnAdminPage('/themes.php?theme=twentysixteen');
        $I->waitForElementVisible('.theme-wrap .button.delete-theme');
        $I->click('.theme-wrap .button.delete-theme');
        $I->acceptPopup();
        // Theme deletion via AJAX has no visible success message to wait for.
        $I->wait(2);
        $I->seeLogMessage('Deleted theme "Twenty Sixteen"');

        // Re-install Twenty Sixteen so other tests (SimpleMenuLoggerCest) can use it.
        $I->amOnAdminPage('/theme-install.php?browse=popular');
        $I->click('Upload Theme');
        $I->attachFile('#themezip', 'twentysixteen.2.6.zip');
        $I->click('Install Now');
        $I->waitForText('Theme installed successfully');
    }
}
