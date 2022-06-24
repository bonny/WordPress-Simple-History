<?php

/**
 * Here:
 * 
 * - check that all messages are tested:
 *   -  user_login_failed
 *   -  user_unknown_login_failed
 *   -  user_logged_in
 *   -  user_unknown_logged_in
 *   -  user_logged_out
 *   -  user_updated_profile
 *   -  user_created
 *   -  user_deleted
 *   -  user_password_reseted
 *   -  user_requested_password_reset_link
 *   -  user_session_destroy_others
 *   -  user_session_destroy_everywhere
 *   -  user_admin_email_confirm_correct_clicked
 *   -  user_role_updated
 *   -  user_application_password_created
 *   -  user_application_password_deleted
 * 
 * - use `seeLogMessage()` to test because faster.
 * - add initiator and context tests
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

    public function logUserOwnProfileUpdated(\Step\Acceptance\Admin $I) {
        $I->loginAsAdmin();
        $I->amOnAdminPage('/profile.php');

        $I->checkOption('#rich_editing');
        $I->selectOption('input[name=admin_color]', 'light');
        $I->fillField("#first_name", "Jane");
        $I->fillField("#last_name", "Doe");
        $I->checkOption('#comment_shortcuts');
        $I->unCheckOption('#admin_bar_front');
        $I->fillField("#url", 'https://texttv.nu');
        $I->fillField("#description", 'Hello there, this is my description text.');
      
        $I->click('#submit');
        
        $I->seeInLog('You', 'Edited your profile', 1);
        $I->seeInLogKeyValueTable('Visual editor Disable enable');
        $I->seeInLogKeyValueTable('Keyboard shortcuts enable disable');
        $I->seeInLogKeyValueTable("Toolbar don't show Show");
        $I->seeInLogKeyValueTable("First name Jane");
        $I->seeInLogKeyValueTable("Last name Doe");
        $I->seeInLogKeyValueTable("Website https://texttv.nu http://wordpress");
        $I->seeInLogKeyValueTable("Description Hello there, this is my description text.");
    }

    public function logUserOtherProfileUpdated(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('annaauthor', 'author', ['user_pass' => 'password']);        
        
        $I->loginAsAdmin();

        $I->amOnAdminPage('/users.php');
        $I->click('annaauthor');

        $I->checkOption('#rich_editing');
        $I->selectOption('input[name=admin_color]', 'light');
        $I->fillField("#first_name", "Annaname");
        $I->fillField("#last_name", "Doeauthor");
        $I->checkOption('#comment_shortcuts');
        $I->unCheckOption('#admin_bar_front');
        $I->fillField("#url", 'https://brottsplatskartan.se');
        $I->fillField("#description", 'Hello there, this is my description text.');
      
        $I->click('#submit');
        
        $I->seeInLog('You', 'Edited the profile for user annaauthor (annaauthor@example.com)', 1);
        $I->seeInLogKeyValueTable('Visual editor Disable enable');
        $I->seeInLogKeyValueTable('Keyboard shortcuts enable disable');
        $I->seeInLogKeyValueTable("Toolbar don't show Show");
        $I->seeInLogKeyValueTable("First name Annaname");
        $I->seeInLogKeyValueTable("Last name Doeauthor");
        $I->seeInLogKeyValueTable("Website https://brottsplatskartan.se http://annaauthor.example.com");
        $I->seeInLogKeyValueTable("Description Hello there, this is my description text.");
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

    public function logUsersBulkEditDeleted(\Step\Acceptance\Admin $I) {
        $user_id_1 = $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'annapass']);        
        $user_id_2 = $I->haveUserInDatabase('anders', 'author', ['user_pass' => 'anderspass']);        
        
        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->checkOption("#user_{$user_id_1}");
        $I->checkOption("#user_{$user_id_2}");

        $I->selectOption('#bulk-action-selector-top', 'delete');
        
        $I->click('#doaction');
        
        $I->click("Confirm Deletion");

        $I->seeInLog('You', 'Deleted user anna (anna@example.com)');
        $I->seeInLog('You', 'Deleted user anders (anders@example.com)', 2);
    }

    public function logUserRequestPasswordReset(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);        
        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->moveMouseOver('.table-view-list tbody tr:nth-child(2)');
        $I->click('.table-view-list tbody tr:nth-child(2) a.resetpassword');

        $I->seeInLog('You', "Requested a password reset link for user with login 'anna' and email 'anna@example.com'");
    }

    public function logUsersBulkChangeRole(\Step\Acceptance\Admin $I) {
        $user_id_1 = $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'annapass']);        
        $user_id_2 = $I->haveUserInDatabase('anders', 'subscriber', ['user_pass' => 'anderspass']);        
        
        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->checkOption("#user_{$user_id_1}");
        $I->checkOption("#user_{$user_id_2}");

        $I->selectOption('#new_role', 'editor');
        
        $I->click('#changeit');

        $I->seeInLog('You', 'Changed role for user "anna" to "editor" from "author"');
        $I->seeInLog('You', 'Changed role for user "anders" to "editor" from "subscriber"', 2);
    }

    public function logUserApplicationPasswordCreated(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('annaauthor', 'author', ['user_pass' => 'password']);        
        
        $I->loginAsAdmin();

        $I->amOnAdminPage('/users.php');
        $I->click('annaauthor');

        $I->checkOption('#rich_editing');
        $I->selectOption('input[name=admin_color]', 'light');
        $I->fillField("#new_application_password_name", "My New App");

        $I->click('#do_new_application_password');
        
        $I->seeInLog('You', 'Added application password "My New App" for user "annaauthor"');
    }
    
    public function logUserApplicationPasswordDeleted(\Step\Acceptance\Admin $I) {
        $I->haveUserInDatabase('annaauthor', 'author', ['user_pass' => 'password']);        
        
        $I->loginAsAdmin();

        $I->amOnAdminPage('/users.php');
        $I->click('annaauthor');

        $I->checkOption('#rich_editing');
        $I->selectOption('input[name=admin_color]', 'light');
        $I->fillField("#new_application_password_name", "My New App");

        $I->click('#do_new_application_password');

        $I->waitForElementVisible('#new-application-password-value');

        $I->see('Your new password for My New App is:');

        $I->click("Revoke");

        $I->acceptPopup();
       
        $I->seeInLog('You', 'Deleted application password "My New App" for user "annaauthor"');

    }
}
