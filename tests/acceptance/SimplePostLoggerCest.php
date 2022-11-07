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

        // Edit post and save as draft.
        $I->click('.wp-block-post-title');
        $I->pressKey('.wp-block-post-title', ['H', 'e', 'l', 'l', 'o']);
        $I->click('Save draft');
        $I->waitForText('Draft saved');
        $I->seeLogMessage('Created page "Hello"');
        $I->seeLogContext([
            'post_type' => 'page',
            'post_title' => 'Hello',
            '_message_key' => 'post_created',
        ]);

        // Continue editing the same post.
        $I->pressKey('.wp-block-post-title', [' ', 'w', 'o', 'r', 'l', 'd']);
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
        $I->click('button.block-editor-inserter__toggle');
        $I->click('button.block-editor-block-types-list__item.editor-block-list-item-paragraph');
        $I->type('This is text in paragraph.');
        $I->waitForText('Save draft');
        $I->click('Save draft');
        $I->waitForText('Draft saved');
        $I->seeLogMessage('Updated page "Hello world"');
        $I->seeLogContext([
            'post_prev_post_content' => '',
            'post_new_post_content' => '<!-- wp:paragraph -->' . PHP_EOL . '<p>This is text in paragraph.</p>' . PHP_EOL . '<!-- /wp:paragraph -->'
        ]);
    }
}
