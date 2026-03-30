<?php

use \Step\Acceptance\Admin;

class SimpleMenuLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
        // Activate a classic theme that supports nav menus.
        // Default WP 6.8 theme (Twenty Twenty-Five) is a block theme.
        $I->amOnAdminPage('/themes.php?theme=twentysixteen');
        // Only activate if not already the active theme.
        $hasActivateButton = $I->executeJS(
            "return !!document.querySelector('.theme-wrap .button.activate')"
        );
        if ($hasActivateButton) {
            $I->click('.theme-wrap .button.activate');
        }
    }


    public function editMenus(Admin $I) {
        // Create menu
        $I->amOnAdminPage('nav-menus.php');
        $I->fillField('#menu-name', 'My new menu');
        $I->checkOption('#auto-add-pages');
        $I->checkOption('#locations-primary');
        $I->scrollTo('#save_menu_footer');
        $I->executeJS('document.getElementById("save_menu_footer").click()');
        $I->waitForElementVisible('#nav-menu-header');
        $I->seeLogMessage('Created menu "My new menu"');
        $I->seeLogContext([
            'menu_name' => 'My new menu'
        ]);

        // Edit menu, change title and add items
        $I->havePageInDatabase();
        $I->wantTo('Edit the menu, add a page and a custom link.');
        $I->amOnAdminPage('nav-menus.php');
        $I->checkOption('#pagechecklist-most-recent input:first-of-type');
        $I->click("#submit-posttype-page"); // Click add to Menu

        $I->click('#add-custom-links'); // Expand custom links accordion header.
        $I->waitForElementVisible('#custom-menu-item-url');

        $I->fillField("#custom-menu-item-url", 'https://texttv.nu/');
        $I->fillField("#custom-menu-item-name", 'SVT Text TV');
        // Click Add to Menu
        $I->click('#submit-customlinkdiv');
        
        // Give menu a name.
        $I->fillField('#menu-name', 'My new menu changed');
        
        // Save the menu.
        $I->scrollTo('#save_menu_footer');
        $I->executeJS('document.getElementById("save_menu_footer").click()');
        $I->waitForElementVisible('#nav-menu-header');

        $I->seeLogMessage('Edited menu "My new menu changed"');
        $I->seeLogContext([
            'menu_id' => '2',
            'menu_name' => 'My new menu changed',
            'menu_items_added' => '2',
            'menu_items_removed' => '0',
        ]);

        // Remove items.
        $I->amOnAdminPage('nav-menus.php');
        $I->click('#menu-to-edit .item-edit');
        $I->waitForElementVisible('#menu-to-edit .item-delete');
        $I->scrollTo('#menu-to-edit .item-delete');
        $I->click('Remove', '#menu-to-edit');
        // Wait for the animated removal to complete before saving.
        $I->wait(1);
        $I->scrollTo('#save_menu_footer');
        $I->executeJS('document.getElementById("save_menu_footer").click()');
        $I->waitForElementVisible('#nav-menu-header');

        $I->seeLogMessage('Edited menu "My new menu changed"');
        $I->seeLogContext([
            'menu_id' => '2',
            'menu_name' => 'My new menu changed',
            'menu_items_added' => '0',
            'menu_items_removed' => '1',
        ]);
    
        // Delete menu.
        $I->amOnAdminPage('nav-menus.php');
        $I->click('Delete Menu');
        $I->acceptPopup();
        // Menu deletion redirects via JS — wait for the page to load.
        $I->waitForElement('#menu-name');
        $I->seeLogMessage('Deleted menu "My new menu changed"');
        $I->seeLogContext([
            'menu_term_id' => '2',
            'menu_name' => 'My new menu changed',
        ]);
    }
}
