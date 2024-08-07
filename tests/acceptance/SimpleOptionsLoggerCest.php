<?php

use \Step\Acceptance\Admin;

class SimpleOptionsLoggerCest
{
    public function _before(Admin $I) {
        $I->loginAsAdmin();
    }

    public function testGeneralOptionsPage(Admin $I) {
        $I->amOnAdminPage('options-general.php');
        
        $options = [
            [
                'field' => '#blogname',
                'fill' => 'My new site title',
                'expected' => 'Updated setting "blogname" on the "general" settings page',
                'expectedContext' => [
                    'option' => 'blogname',
                    'old_value' => 'wp-tests',
                    'new_value' => 'My new site title',
                    'option_page' => 'general',        
                ]
            ],
            [
                'field' => '#blogdescription',
                'fill' => 'New site tag',
                'expected' => 'Updated setting "blogdescription" on the "general" settings page',
                'expectedContext' => [
                    'option' => 'blogdescription',
                    'old_value' => 'Just another WordPress site',
                    'new_value' => 'New site tag',
                    'option_page' => 'general',        
                ]
            ],
            [
                'field' => '#new_admin_email',
                'fill' => 'par.thernstrom@gmail.com',
                'expected' => 'Updated setting "new_admin_email" on the "general" settings page',
                'expectedContext' => [
                    'option' => 'new_admin_email',
                    'old_value' => 'test@example.com',
                    'new_value' => 'par.thernstrom@gmail.com',
                    'option_page' => 'general',        
                ]
            ],
        ];

        foreach ($options as $oneOption) {       
            $I->fillField($oneOption['field'], $oneOption['fill']);
            $I->click("Save Changes");
            $I->seeLogMessage($oneOption['expected']);
            $I->seeLogContext($oneOption['expectedContext']);
        }
        
        $I->checkOption('#users_can_register');
        $I->click("Save Changes");
        $I->seeLogMessage('Updated setting "users_can_register" on the "general" settings page');
        $I->seeLogContext([
            'option' => 'users_can_register',
            'old_value' => '0',
            'new_value' => '1',
            'option_page' => 'general',
        ]);

        $I->selectOption('#default_role', 'Editor');
        $I->click("Save Changes");
        $I->seeLogMessage('Updated setting "default_role" on the "general" settings page');
        $I->seeLogContext([
            'option' => 'default_role',
            'old_value' => 'subscriber',
            'new_value' => 'editor',
            'option_page' => 'general',
        ]);

    }

    public function testWritingOptionsPage(Admin $I) {
        $I->amOnAdminPage('options-writing.php');
        
        $I->fillField('#mailserver_url', 'smtpserver.example.com');
        $I->fillField('#mailserver_login', 'login@email.com');
        $I->fillField('#mailserver_pass', 'mailpass');
        $I->click("Save Changes");

        $I->seeLogMessage('Updated setting "mailserver_pass" on the "writing" settings page', 0);
        $I->seeLogContext([
            'option_page' => 'writing',
            'option' => 'mailserver_pass',
            'old_value' => '',
            'new_value' => '',
        ], 0);

        $I->seeLogMessage('Updated setting "mailserver_login" on the "writing" settings page', 1);
        $I->seeLogContext([
            'option_page' => 'writing',
            'option' => 'mailserver_login',
            'old_value' => 'login@example.com',
            'new_value' => 'login@email.com',
        ], 1);

        $I->seeLogMessage('Updated setting "mailserver_url" on the "writing" settings page', 2);
        $I->seeLogContext([
            'option_page' => 'writing',
            'option' => 'mailserver_url',
            'old_value' => 'mail.example.com',
            'new_value' => 'smtpserver.example.com',
        ], 2);
    }

    public function testReadingOptionsPage(Admin $I) {
        $I->havePageInDatabase(['post_title' => 'Test page']);
        $I->amOnAdminPage('options-reading.php');
        
        $I->selectOption('[name=show_on_front]', 'page');
        $I->selectOption('[name=page_on_front]', 'Test page');

        $I->click('Save Changes');

        $I->seeLogMessage('Updated setting "page_on_front" on the "reading" settings page', 0);
        $I->seeLogContext([
            'option_page' => 'reading',
            'option' => 'page_on_front',
            'old_value' => '0',
            'new_value' => '2',
        ], 0);

        $I->seeLogMessage('Updated setting "show_on_front" on the "reading" settings page', 1);
        $I->seeLogContext([
            'option_page' => 'reading',
            'option' => 'show_on_front',
            'old_value' => 'posts',
            'new_value' => 'page',
        ], 1);

        $I->checkOption('#blog_public');
        $I->click('Save Changes');
        $I->seeLogMessage('Updated setting "blog_public" on the "reading" settings page');
        $I->seeLogContext([
            'option' => 'blog_public',
            'old_value' => '1',
            'new_value' => '0',
            'option_page' => 'reading',
        ]);
    }

    public function testDiscussionOptionsPage(Admin $I) {
        
        // "Dummy" save because some values seems to be set for the first time
        $I->amOnAdminPage('options-discussion.php');
        $I->click("Save Changes");

        $I->amOnAdminPage('options-discussion.php');
        $I->uncheckOption('#default_comment_status');
        $I->click("Save Changes");
        $I->seeLogMessage('Updated setting "default_comment_status" on the "discussion" settings page');
        $I->seeLogContext([
            'option_page' => 'discussion',
            'option' => 'default_comment_status',
            'old_value' => 'open',
            'new_value' => '',
        ]);

        $I->amOnAdminPage('options-discussion.php');
        $I->checkOption('#default_comment_status');
        $I->click("Save Changes");
        $I->seeLogMessage('Updated setting "default_comment_status" on the "discussion" settings page');
        $I->seeLogContext([
            'option_page' => 'discussion',
            'option' => 'default_comment_status',
            'old_value' => '',
            'new_value' => 'open',
        ]);

        $I->amOnAdminPage('options-discussion.php');
        $I->uncheckOption('#require_name_email');
        $I->click("Save Changes");
        $I->seeLogMessage('Updated setting "require_name_email" on the "discussion" settings page');
        $I->seeLogContext([
            'option_page' => 'discussion',
            'option' => 'require_name_email',
            'old_value' => '1',
            'new_value' => '',
        ]);

        $I->amOnAdminPage('options-discussion.php');
        $I->uncheckOption('#show_avatars');
        $I->click("Save Changes");
        $I->seeLogMessage('Updated setting "show_avatars" on the "discussion" settings page');
        $I->seeLogContext([
            'option_page' => 'discussion',
            'option' => 'show_avatars',
            'old_value' => '1',
            'new_value' => '',
        ]);       
    }
}
