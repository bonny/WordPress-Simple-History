<?php

use \Step\Acceptance\Admin;

/**
 * Missing tests for:
 * 
 * - plugin_auto_updates_disabled - unable to test, not sure why (message not logged)
 * - plugin_auto_updates_enabled - unable to test, not sure why (message not logged)
 * - add_filter( 'plugins_auto_update_enabled', '__return_true' );
 * - plugin_disabled_because_error - not sure how to test
 */
class SimplePluginLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function testPluginActivation(Admin $I) {
        // plugin_activated
        $I->amOnAdminPage('plugins.php');
        // $I->click("#activate-akismet");
        $I->click("#activate-akismet-anti-spam-spam-protection");
        $I->seeLogMessage('Activated plugin "Akismet Anti-spam: Spam Protection"');
        $I->seeLogContext(array(
            'plugin_name' => 'Akismet Anti-spam: Spam Protection',
            'plugin_slug' => 'akismet',
            'plugin_title' => '<a href="https://akismet.com/">Akismet Anti-spam: Spam Protection</a>',
            'plugin_description' => 'Used by millions, Akismet is quite possibly the best way in the world to <strong>protect your blog from spam</strong>. Akismet Anti-spam keeps your site protected even while you sleep. To get started: activate the Akismet plugin and then go to your Akismet Settings page to set up your API key. <cite>By <a href="https://automattic.com/wordpress-plugins/">Automattic - Anti-spam Team</a>.</cite>',
            'plugin_author' => '<a href="https://automattic.com/wordpress-plugins/">Automattic - Anti-spam Team</a>',
            'plugin_version' => '5.5',
            'plugin_url' => 'https://akismet.com/',
        ));
        
        $I->amOnAdminPage('plugins.php');
        $I->click("#activate-hello-dolly");
        $I->seeLogMessage('Activated plugin "Hello Dolly"');
        $I->seeLogContext(array(
            'plugin_name' => 'Hello Dolly',
            'plugin_slug' => '.',
            'plugin_version' => '1.7.2',
            'plugin_url' => 'http://wordpress.org/plugins/hello-dolly/',
            'plugin_title' => '<a href="http://wordpress.org/plugins/hello-dolly/">Hello Dolly</a>',
            'plugin_description' => 'This is not just a plugin, it symbolizes the hope and enthusiasm of an entire generation summed up in two words sung most famously by Louis Armstrong: Hello, Dolly. When activated you will randomly see a lyric from Hello, Dolly in the upper right of your admin screen on every page. <cite>By <a href="http://ma.tt/">Matt Mullenweg</a>.</cite>',
            'plugin_author' => '<a href="http://ma.tt/">Matt Mullenweg</a>',
        ));

        // plugin_deactivated
        $I->amOnAdminPage('plugins.php');
        // $I->click("#deactivate-akismet");
        $I->click("#deactivate-akismet-anti-spam-spam-protection");
        $I->seeLogMessage('Deactivated plugin "Akismet Anti-spam: Spam Protection"');
        $I->seeLogContext(array(
            'plugin_name' => 'Akismet Anti-spam: Spam Protection',
            'plugin_slug' => 'akismet',
            'plugin_title' => '<a href="https://akismet.com/">Akismet Anti-spam: Spam Protection</a>',
            'plugin_description' => 'Used by millions, Akismet is quite possibly the best way in the world to <strong>protect your blog from spam</strong>. Akismet Anti-spam keeps your site protected even while you sleep. To get started: activate the Akismet plugin and then go to your Akismet Settings page to set up your API key. <cite>By <a href="https://automattic.com/wordpress-plugins/">Automattic - Anti-spam Team</a>.</cite>',
            'plugin_author' => '<a href="https://automattic.com/wordpress-plugins/">Automattic - Anti-spam Team</a>',
            'plugin_version' => '5.5',
            'plugin_url' => 'https://akismet.com/',
        ));

    }
    
    /**
     * WP 6.8 shows "Replace current with uploaded" option instead
     * of failing when uploading a plugin that's already installed.
     * The plugin_installed_failed event is no longer triggered.
     */
    public function testPluginInstallFail(Admin $I) {
        \PHPUnit\Framework\Assert::markTestSkipped(
            'WP 6.8 no longer triggers plugin_installed_failed — shows "Replace" option instead.'
        );
    }
    
    // Can't get to work because there is always a left over folder or something.
    // Would need a "->cleanPluginDirIfExists"
    public function testPluginInstallSuccess(Admin $I) {
        // - plugin_installed
        $I->cleanUploadsDir();
        $I->deleteDir('/wordpress/wp-content/plugins/limit-login-attempts-reloaded');

        $I->amOnAdminPage('plugin-install.php');
        
        // $x = $I->canSeePluginFileFound('limit-login-attempts-reloadedx/readme.txt');
        // var_dump($x);
        // $x = $I->canSeePluginFileFound('limit-login-attempts-reloaded/readme.txt');
        // var_dump($x);exit;

        $I->click("Upload Plugin");
        

        $I->attachFile('#pluginzip', 'limit-login-attempts-reloaded.2.25.5.zip');
        $I->click('Install Now');
        $I->waitForText('Plugin installed successfully');
        $I->seeLogMessage('Installed plugin "Limit Login Attempts Reloaded"');
        $I->seeLogContext(array(
            'plugin_slug' => 'limit-login-attempts-reloaded',
            'plugin_name' => 'Limit Login Attempts Reloaded',
            'plugin_version' => '2.25.5',
            'plugin_author' => 'Limit Login Attempts Reloaded',
            'plugin_requires_wp' => '',
            'plugin_requires_php' => '',
            'plugin_install_source' => 'upload',
            'plugin_upload_name' => 'limit-login-attempts-reloaded.2.25.5.zip',
            // 'plugin_description' => 'Block excessive login attempts and protect your site against brute force attacks. Simple, yet powerful tools to improve site performance. <cite>By <a href="https://www.limitloginattempts.com/">Limit Login Attempts Reloaded</a>.</cite>'
            'plugin_url' => '',
        ));

        // Not sure how to test:
        // - plugin_updated
        // - plugin_update_failed
        // - plugin_bulk_updated
    }

    public function testPluginDeleted(Admin $I) {
        $I->deleteDir('/wordpress/wp-content/plugins/classic-widgets');
        $I->amOnAdminPage('plugin-install.php');
        $I->click("Upload Plugin");
        $I->attachFile('#pluginzip', 'classic-widgets.0.3.zip');
        $I->click('Install Now');
        $I->waitForText('Plugin installed successfully');

        // plugin_deleted
        $I->amOnAdminPage('plugins.php');
        $I->checkOption('[value="classic-widgets/classic-widgets.php"]');                
        $I->selectOption("#bulk-action-selector-top", 'Delete');
        $I->click("#doaction");
        $I->acceptPopup();
        $I->waitForText('was successfully deleted');
        $I->seeLogMessage('Deleted plugin "Classic Widgets"');
        $I->seeLogContext(array(
            'plugin' => 'classic-widgets/classic-widgets.php',
            'plugin_name' => 'Classic Widgets',
            'plugin_title' => '<a href="https://wordpress.org/plugins/classic-widgets/">Classic Widgets</a>',
            'plugin_description' => 'Enables the classic widgets settings screens in Appearance &#8211; Widgets and the Customizer. Disables the block editor from managing widgets. <cite>By <a href="https://github.com/WordPress/classic-widgets/">WordPress Contributors</a>.</cite>',
            'plugin_author' => '<a href="https://github.com/WordPress/classic-widgets/">WordPress Contributors</a>',
            'plugin_version' => '0.3',
            'plugin_url' => 'https://wordpress.org/plugins/classic-widgets/',
        ));
    }

    // public function testPluginAutoUpdatesEnableDisable(Admin $I) {
    //     // - plugin_auto_updates_disabled
    //     // - plugin_auto_updates_enabled        
    //     $I->amOnAdminPage('plugins.php');
    //     $I->click('[data-slug=classic-editor] .toggle-auto-update');
    //     $I->wait(2);
    //     $I->seeLogMessage('Enable auto updated');
    // }
}
