<?php

/**
 * Activated Jetpack module "Extra Sidebar Widgets"
 * Deactivated Jetpack module "Site verification"
 * 
 * $I->seeMessageInLog('Activated Jetpack module "Extra Sidebar Widgets"');
 * $I->seeInterpolatedMessageInLog('Activated Jetpack module "Extra Sidebar Widgets"');
 * $I->seeLogMessage('Activated Jetpack module "Extra Sidebar Widgets"');
 * Hämta från db och interpolera osv.
 * 
 */

use \Step\Acceptance\Admin;

class UserCest
{
    public function testYo(Admin $I) {
        $I->amOnPage('/wp-login.php');
        $I->submitForm('#loginform', array(
            'log' => 'erik',
            'pwd' => 'password',
        ));

        $I->seeLogMessage('Failed to login with username "erik" (username does not exist)');
    }
}
