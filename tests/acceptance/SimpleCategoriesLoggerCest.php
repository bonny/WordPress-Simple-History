<?php

use \Step\Acceptance\Admin;

class SimpleCategoriesLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function addTerms(Admin $I) {
        // Add category.
        $I->amOnAdminPage('edit-tags.php?taxonomy=category');
        $I->fillField("#tag-name", 'My new category');
        $I->fillField("#tag-description", 'Category description');
        $I->click("Add New Category");
        // Wait for "Category added."-notification message.
        $I->waitForElement('.notice.notice-success');
        $I->seeLogMessage('Added term "My new category" in taxonomy "category"');
        $I->seeLogContext([
            'term_name' => 'My new category',
            'term_taxonomy' => 'category'
        ]);

        // Edit category.
        $I->amOnAdminPage('edit-tags.php?taxonomy=category');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click("Edit");
        $I->fillField("#name", 'My new category changed');
        $I->fillField("#description", 'Changed description');
        $I->click("Update");
        $I->seeLogMessage('Edited term "My new category changed" in taxonomy "category"');
        $I->seeLogContext([
            'from_term_name' => 'My new category',
            'from_term_taxonomy' => 'category',
            'from_term_slug' => 'my-new-category',
            'from_term_description' => 'Category description',
            'to_term_name' => 'My new category changed',
            'to_term_taxonomy' => 'category',
            'to_term_slug' => 'my-new-category',
            'to_term_description' => 'Changed description',
        ]);

        // Delete category.
        $I->amOnAdminPage('edit-tags.php?taxonomy=category');
        $I->moveMouseOver('.wp-list-table tbody tr:nth-child(1)');
        $I->click("Edit");
        $I->click("Delete");
        $I->acceptPopup();
        $I->wait(1);
        $I->seeLogMessage('Deleted term "My new category changed" from taxonomy "category"');
        $I->seeLogContext([
            'term_name' => 'My new category changed',
            'term_taxonomy' => 'category',
        ]);
    }
}
