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

        // Needed for the admin JS to have time to generate a password and duplicate it to the hidden password field.
        $I->wait(0.1);
        
        $I->fillField("#user_login", "NewUserLogin");
        $I->fillField("#email", "newuser@example.com");
        $I->uncheckOption('#send_user_notification');

        $I->click('Add New User');
        $I->makeScreenshot();

        $I->seeInLog('You', 'Created user NewUserLogin (newuser@example.com) with role subscriber', 1);
    }

    public function logUserDeleted(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);        
        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->moveMouseOver('.table-view-list tbody tr:nth-child(2)');
        $I->click('.table-view-list tbody tr:nth-child(2) .submitdelete');
        $I->click("Confirm Deletion");

        $I->seeInLog('You', 'Deleted user anna (anna@example.com)');
    }

    public function logUserRequestPasswordReset(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);        
        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->moveMouseOver('.table-view-list tbody tr:nth-child(2)');
        $I->click('.table-view-list tbody tr:nth-child(2) a.resetpassword');

        $I->seeInLog('You', "Requested a password reset link for user with login 'anna' and email 'anna@example.com'");
    }


    /*
    To log:
    - user_password_reseted
    - user_requested_password_reset_link
    */
}
