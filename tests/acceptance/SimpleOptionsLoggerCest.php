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
}
