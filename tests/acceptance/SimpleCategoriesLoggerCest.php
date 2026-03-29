<?php

use \Step\Acceptance\Admin;

class SimpleCategoriesLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function addTerm(Admin $I) {
        $I->amOnAdminPage('edit-tags.php?taxonomy=category');
        $I->fillField("#tag-name", 'My new category');
        $I->fillField("#tag-description", 'Category description');
        $I->click("Add Category");
        $I->waitForElement('.notice.notice-success');
        $I->seeLogMessage('Added term "My new category" in taxonomy "category"');
        $I->seeLogContext([
            'term_name' => 'My new category',
            'term_taxonomy' => 'category'
        ]);
    }

    public function editTerm(Admin $I) {
        $term_ids = $I->haveTermInDatabase('My edit category', 'category', ['description' => 'Original description']);
        $term_id = $term_ids[0];

        $I->amOnAdminPage("term.php?taxonomy=category&tag_ID={$term_id}");
        $I->fillField("#name", 'My edit category changed');
        $I->fillField("#description", 'Changed description');
        $I->click("Update");
        $I->seeLogMessage('Edited term "My edit category changed" in taxonomy "category"');
        $I->seeLogContext([
            'from_term_name' => 'My edit category',
            'from_term_taxonomy' => 'category',
            'from_term_slug' => 'my-edit-category',
            'from_term_description' => 'Original description',
            'to_term_name' => 'My edit category changed',
            'to_term_taxonomy' => 'category',
            'to_term_slug' => 'my-edit-category',
            'to_term_description' => 'Changed description',
        ]);
    }

    public function deleteTerm(Admin $I) {
        $term_ids = $I->haveTermInDatabase('My delete category', 'category');
        $term_id = $term_ids[0];

        $I->amOnAdminPage("term.php?taxonomy=category&tag_ID={$term_id}");
        $I->click("Delete");
        $I->acceptPopup();
        $I->waitForElement('.wp-list-table');
        $I->seeLogMessage('Deleted term "My delete category" from taxonomy "category"');
        $I->seeLogContext([
            'term_name' => 'My delete category',
            'term_taxonomy' => 'category',
        ]);
    }
}
