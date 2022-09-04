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
                'expected' => 'Updated option "blogname"',
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
                'expected' => 'Updated option "blogdescription"',
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
                'expected' => 'Updated option "new_admin_email"',
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
        $I->seeLogMessage('Updated option "users_can_register"');
        $I->seeLogContext([
            'option' => 'users_can_register',
            'old_value' => '0',
            'new_value' => '1',
            'option_page' => 'general',
        ]);

        $I->selectOption('#default_role', 'Editor');
        $I->click("Save Changes");
        $I->seeLogMessage('Updated option "default_role"');
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

        $I->seeLogMessage('Updated option "mailserver_pass"', 0);
        $I->seeLogContext([
            'option_page' => 'writing',
            'option' => 'mailserver_pass',
            'old_value' => 'password',
            'new_value' => 'mailpass',
        ], 0);

        $I->seeLogMessage('Updated option "mailserver_login"', 1);
        $I->seeLogContext([
            'option_page' => 'writing',
            'option' => 'mailserver_login',
            'old_value' => 'login@example.com',
            'new_value' => 'login@email.com',
        ], 1);

        $I->seeLogMessage('Updated option "mailserver_url"', 2);
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

        $I->seeLogMessage('Updated option "page_on_front"', 0);
        $I->seeLogContext([
            'option_page' => 'reading',
            'option' => 'page_on_front',
            'old_value' => '0',
            'new_value' => '2',
        ], 0);

        $I->seeLogMessage('Updated option "show_on_front"', 1);
        $I->seeLogContext([
            'option_page' => 'reading',
            'option' => 'show_on_front',
            'old_value' => 'posts',
            'new_value' => 'page',
        ], 1);

        $I->checkOption('#blog_public');
        $I->click('Save Changes');
        $I->seeLogMessage('Updated option "blog_public"');
        $I->seeLogContext([
            'option' => 'blog_public',
            'old_value' => '1',
            'new_value' => '0',
            'option_page' => 'reading',
        ]);
    }
}
