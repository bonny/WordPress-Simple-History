<?php

/**
 * 'theme_updated'             => __( 'Updated theme "{theme_name}"', 'simple-history' ),
 */

class SimpleThemeLoggerCest
{
    // theme_installed
    public function logThemeInstalled(\Step\Acceptance\Admin $I)
    {
        // Remove previously uploaded theme.
        // How to clean this if folder is empty?
        // /wordpress/wp-content/themes/twentysixteen
        $I->deleteDir('/wordpress/wp-content/themes/twentysixteen');
        // $I->cleanThemeDir('twentysixteen');
        // Remove any previously uploaded theme file.
        $I->cleanUploadsDir();

        $I->loginAsAdmin();
        $I->amOnAdminPage('/theme-install.php?browse=popular');
        $I->click('Upload Theme');
        $I->attachFile('#themezip', 'twentysixteen.2.6.zip');
        $I->click('Install Now');

        // Message key: theme_installed.
        // Flaky test, name of uploaded zip changes...
        // $I->seeLogMessage('Deleted attachment "twentysixteen.2.6.zip" ("twentysixteen.2.6-1.zip")', 0);
        $I->seeLogMessage('Installed theme "Twenty Sixteen" by the WordPress team', 1);

        // Message key: theme_switched.
        $I->click('Activate');
        $I->seeLogMessage('Switched theme to "Twenty Sixteen" from "Twenty Twenty-One"');

        // Upload Theme again to test theme_updated, does not currently work when
        // uploading zip however?
        $I->amOnAdminPage('/theme-install.php?browse=popular');
        $I->click('Upload Theme');
        $I->attachFile('#themezip', 'twentysixteen.2.7.zip');
        $I->click('Install Now');
        $I->click('Replace active with uploaded');
        // $I->seeLogMessage('Deleted attachment "twentysixteen.2.7.zip" ("twentysixteen.2.7.zip")');
        $I->seeLogMessage('Installed theme "Twenty Sixteen" by the WordPress team', 1);

        // theme_switched: Switch back theme so we can delete the uploaded one.
        $I->amOnAdminPage('/themes.php?theme=twentytwentyone');
        $I->makeScreenshot();
        $I->waitForElementVisible('.theme-wrap .button.activate');
        $I->click('.theme-wrap .button.activate');
        $I->seeLogMessage('Switched theme to "Twenty Twenty-One" from "Twenty Sixteen"');

        // theme_deleted: Theme deleted.
        $I->amOnAdminPage('/themes.php?theme=twentysixteen');
        $I->waitForElementVisible('.theme-wrap .button.delete-theme');
        $I->click('.theme-wrap .button.delete-theme');
        $I->acceptPopup();
        // Deleting takes a short while and no ok message is outputted when finishes, 
        // so we can't wait for a message or similar.
        $I->wait(1);
        $I->seeLogMessage('Deleted theme "Twenty Sixteen"');
    }
}
