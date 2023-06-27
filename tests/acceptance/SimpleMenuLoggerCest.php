<?php

use \Step\Acceptance\Admin;

class SimpleMenuLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function editMenus(Admin $I) {
        // Create menu
        $I->amOnAdminPage('nav-menus.php');
        $I->fillField('#menu-name', 'My new menu');
        $I->checkOption('#auto-add-pages');
        $I->checkOption('#locations-primary');
        $I->click('#save_menu_footer');
        $I->seeLogMessage('Created menu "My new menu"');
        $I->seeLogContext([
            'menu_name' => 'My new menu'
        ]);

        // Edit menu, change title and add items
        $I->havePageInDatabase();
        $I->wantTo('Edit the menu, add a page and a custom link.');
        $I->amOnAdminPage('nav-menus.php');
        $I->checkOption('#pagechecklist-most-recent input:first-of-type');
        $I->click("#submit-posttype-page"); // Add page
        $I->click('#add-custom-links'); // Expand custom links accordion header.
        $I->waitForElementVisible('#custom-menu-item-url');
        $I->fillField("#custom-menu-item-url", 'https://texttv.nu/');
        $I->fillField("#custom-menu-item-name", 'SVT Text TV');
        $I->wait(1);
        $I->click('#submit-customlinkdiv'); // Add to Menu
        $I->wait(1);
        $I->fillField('#menu-name', 'My new menu changed');
        $I->click('Save Menu', '#nav-menu-footer');
        $I->click('Save Menu', '#nav-menu-footer'); // Yes, must click twice or will not save for some reason...
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
        $I->click('Remove', '#menu-to-edit');
        $I->wait('1');
        $I->click('Save Menu', '#nav-menu-footer');
        $I->click('Save Menu', '#nav-menu-footer'); // Yes, must click twice or will not save for some reason...
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
        $I->click('Delete Menu');
        $I->acceptPopup();
        $I->wait(1);
        $I->seeLogMessage('Deleted menu "My new menu changed"');
        $I->seeLogContext([
            'menu_term_id' => '2',
            'menu_name' => 'My new menu changed',
        ]);
    }
}
