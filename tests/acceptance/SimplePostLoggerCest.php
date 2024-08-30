<?php

use \Step\Acceptance\Admin;

/**
 * post_created
 * post_updated
 * post_restored
 * post_deleted
 * post_trashed
 */
class SimplePostLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function testPostCreated(Admin $I) {
        $I->amOnAdminPage('edit.php?post_type=page');
        $I->click('Add New', '.wrap');

        // Close Welcome guide.
        $I->click('.edit-post-welcome-guide button.components-button');

        // Go down in editor frame.
        $I->switchToIFrame('editor-canvas');

        $I->waitForElement('.wp-block-post-title');

        // Edit post and save as draft.
        $I->click('.wp-block-post-title');
        $I->pressKey('.wp-block-post-title', ['H', 'e', 'l', 'l', 'o']);
        
        // Go up to parent page from editor frame.
        $I->switchToIFrame();
        
        $I->click('Save draft');
        $I->waitForText('Draft saved');
        $I->seeLogMessage('Created page "Hello"');
        $I->seeLogContext([
            'post_type' => 'page',
            'post_title' => 'Hello',
            '_message_key' => 'post_created',
        ]);

        // Continue editing the same post.
        $I->switchToIFrame('editor-canvas');
        $I->pressKey('.wp-block-post-title', [' ', 'w', 'o', 'r', 'l', 'd']);

        // Go up to parent page from editor frame.
        $I->switchToIFrame();

        $I->waitForText('Save draft');
        $I->click('Save draft');
        $I->waitForText('Draft saved');
        $I->seeLogMessage('Updated page "Hello world"');
        $I->seeLogContext([
            'post_type' => 'page',
            'post_title' => 'Hello world',
            'post_prev_post_title' => 'Hello',
            'post_new_post_title' => 'Hello world'
        ]);

        // Continue editing the post, adding a block.
        $I->switchToIFrame('editor-canvas');
        $I->click('button.block-editor-inserter__toggle');

        // Go up because that's where the button is.
        $I->switchToIFrame();
        $I->click('button.block-editor-block-types-list__item.editor-block-list-item-paragraph');

        // Go down in editor frame.
        $I->switchToIFrame('editor-canvas');
        $I->type('This is text in paragraph.');

        // Go up to parent page from editor frame.
        $I->switchToIFrame();
        $I->waitForText('Save draft');
        $I->click('Save draft');
        $I->waitForText('Draft saved');
        $I->seeLogMessage('Updated page "Hello world"');
        $I->seeLogContext([
            'post_prev_post_content' => '',
            'post_new_post_content' => '<!-- wp:paragraph -->' . PHP_EOL . '<p>This is text in paragraph.</p>' . PHP_EOL . '<!-- /wp:paragraph -->'
        ]);
    }

    /**
     * Create a blog post and then add tags and categories to it
     * and confirm that the log messages are correct.
     *
     * @param Admin $I
     */
    public function testPostCategoriesAddedAndRemoved(Admin $I) {
        // $I->amOnAdminPage('edit.php?post_type=post');
        // $I->click('Add New', '.wrap');

        // // Close Welcome guide.
        // $I->click('.edit-post-welcome-guide button.components-button');

        // // Edit post and save as draft.
        // $I->click('.wp-block-post-title');
        // $I->pressKey('.wp-block-post-title', ['H', 'e', 'l', 'l', 'o']);
        // $I->click('Save draft');
        // $I->waitForText('Draft saved');
        // $I->seeLogMessage('Created post "Hello"');
        // $I->seeLogContext([
        //     'post_type' => 'post',
        //     'post_title' => 'Hello',
        //     '_message_key' => 'post_created',
        // ]);

        /**
         *  Add tags and categories.
         */

         // Todo: this part.
         // Can't get the tags and categories panel to open.
        
        // Expand categories panel.
        // $I->click('Categories', 'button span');
        // $I->click('.components-button components-panel__body-toggle:nth-child(1)');
        // $I->findElement('.editor-post-taxonomies__hierarchical-terms-add')->click();

        // Scroll down to "Add New Category" button.
        // $I->scrollTo('.editor-post-taxonomies__hierarchical-terms-add');
        
        // $I->makeScreenshot('post-created');
    }
}
