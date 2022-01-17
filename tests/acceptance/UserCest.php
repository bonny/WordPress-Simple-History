<?php

/**
 * 
 * 
 * Kanske använda för att hämta värden från DB:
 * grabFromDatabase($table, $column, $criteria = []) {
 * 
 * dontSee() för att testa erik inte ser logins
 * 
 * Använda
 * performOn()
 * för att testa att man ser saker i ett LogItem-element
 * 
 * 
 * Gör ofta:
 *  - Logga in med användare till Simple History-sidan
 *  - Användare ska se eller inte se vissa saker på sidan
 * 
 */

class UserCest
{
    public function logLoginAttemptFromUserThatDoesNotExist(\Step\Acceptance\Admin $I) {
        $I->amOnPage('/wp-login.php');
        $I->submitForm('#loginform', array(
            'log' => 'erik',
            'pwd' => 'password',
        ));

        $I->seeInLogAsAdmin('Anonymous web user', 'Failed to login with username "erik" (username does not exist)', 2);
    }

    public function logLoginAndLogoutFromUserThatExists(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('erik', 'editor', ['user_pass' => 'password']);
        
        $I->amOnPage('/wp-login.php');
        $I->submitForm('#loginform', array(
            'log' => 'erik',
            'pwd' => 'password',
        ));

        $I->amOnAdminPage('/');
        $I->logOut();
     
        $I->seeInLogAsAdmin('erik', 'Logged out', 2);
        $I->seeInLogAsAdmin('erik', 'Logged in', 3);
    }

    public function logFailedLoginAttemptToUserThatExists(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);
        
        $I->amOnPage('/wp-login.php');
        $I->submitForm('#loginform', array(
            'log' => 'anna',
            'pwd' => 'wrongpassword',
        ));
     
        $I->seeInLogAsAdmin('Anonymous web user', 'Failed to login with username "anna" (incorrect password entered)');
    }

    public function logUserProfileUpdated(\Step\Acceptance\Admin $I) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('/profile.php');
        $I->fillField("#first_name", "Jane");
        $I->fillField("#last_name", "Doe");
        $I->click('#submit');

        $I->seeInLog('You', 'Edited your profile', 1);
    }

    public function logUserCreated(\Step\Acceptance\Admin $I) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('/user-new.php');
        
        $I->fillField("#user_login", "NewUserLogin");
        $I->fillField("#email", "newuser@example.com");
        $I->uncheckOption('#send_user_notification');

        $I->executeJS("document.querySelector('.user-pass2-wrap').style.display = 'block';"); 
        $I->fillField('#pass2', $I->grabValueFrom('#pass1'));

        $I->click('Add New User');

        $I->seeInLog('You', 'Created user NewUserLogin (newuser@example.com) with role subscriber', 1);
    }

    /*
    To log:
    - user_deleted
    - user_password_reseted
    - user_requested_password_reset_link
    */
}
