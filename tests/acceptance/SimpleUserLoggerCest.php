<?php

/**
 * Here:
 * 
 * - check that all messages are tested:
 *   -  user_unknown_logged_in (not sure how to test)
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

class SimpleUserLoggerCest
{
    // user_unknown_login_failed
    public function logLoginAttemptFromUserThatDoesNotExist(\Step\Acceptance\Admin $I)
    {
        $I->amOnPage('/wp-login.php');
        $I->submitForm('#loginform', array(
            'log' => 'erik',
            'pwd' => 'password',
        ));

        $I->seeLogInitiator('web_user');
        $I->seeLogMessage('Failed to login with username "erik" (username does not exist)');
    }

    // user_logged_in and user_logged_out.
    public function logLoginAndLogoutFromUserThatExists(\Step\Acceptance\Admin $I)
    {
        $I->haveUserInDatabase('erik', 'editor', ['user_pass' => 'password']);

        $I->amOnPage('/wp-login.php');
        $I->submitForm('#loginform', array(
            'log' => 'erik',
            'pwd' => 'password',
        ));

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Logged in');

        $I->amOnAdminPage('/');
        $I->logOut();

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Logged out');
    }

    // user_login_failed
    public function logFailedLoginAttemptToUserThatExists(\Step\Acceptance\Admin $I)
    {
        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);

        $I->amOnPage('/wp-login.php');
        $I->submitForm('#loginform', array(
            'log' => 'anna',
            'pwd' => 'wrongpassword',
        ));

        $I->seeLogInitiator('web_user');
        $I->seeLogMessage('Failed to login with username "anna" (incorrect password entered)');
    }

    public function logUserOwnProfileUpdated(\Step\Acceptance\Admin $I)
    {
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

    // user_updated_profile
    public function logUserOtherProfileUpdated(\Step\Acceptance\Admin $I)
    {
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

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Edited the profile for user "annaauthor" (annaauthor@example.com)');
        $I->seeLogContext([
            'user_new_user_url' => 'https://brottsplatskartan.se',
            'user_new_first_name' => 'Annaname',
            'user_new_last_name' => 'Doeauthor',
            'user_prev_first_name' => '',
            'user_prev_last_name' => '',
            'user_prev_description' => '',
            'user_new_description' => 'Hello there, this is my description text.',
        ]);
    }

    // user_created
    public function logUserCreated(\Step\Acceptance\Admin $I)
    {
        $I->loginAsAdmin();
        $I->amOnAdminPage('/user-new.php');

        // Needed for the admin JS to have time to generate a password and duplicate it to the hidden password field.
        $I->wait(0.1);

        $I->fillField("#user_login", "NewUserLogin");
        $I->fillField("#email", "newuser@example.com");
        $I->uncheckOption('#send_user_notification');

        $I->click('Add New User');

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Created user NewUserLogin (newuser@example.com) with role subscriber');
        $I->seeLogContext([
            'created_user_email' => 'newuser@example.com',
            'created_user_login' => 'NewUserLogin',
            'created_user_role' => 'subscriber',
        ]);
    }

    // user_deleted
    public function logUserDeleted(\Step\Acceptance\Admin $I)
    {
        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);
        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->moveMouseOver('.table-view-list tbody tr:nth-child(2)');
        $I->click('.table-view-list tbody tr:nth-child(2) .submitdelete');
        $I->click("Confirm Deletion");

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Deleted user anna (anna@example.com)');
    }

    // user_deleted
    public function logUsersBulkEditDeleted(\Step\Acceptance\Admin $I)
    {
        $user_id_1 = $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'annapass']);
        $user_id_2 = $I->haveUserInDatabase('anders', 'author', ['user_pass' => 'anderspass']);

        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->checkOption("#user_{$user_id_1}");
        $I->checkOption("#user_{$user_id_2}");

        $I->selectOption('#bulk-action-selector-top', 'delete');

        $I->click('#doaction');

        $I->click("Confirm Deletion");

        $I->seeLogInitiator('wp_user');
        $I->seeLogMessage('Deleted user anna (anna@example.com)');

        // todo: need to check second row in db
        $I->seeLogInitiator('wp_user', 1);
        $I->seeLogMessage('Deleted user anders (anders@example.com)', 1);
    }

    public function logUserRequestPasswordReset(\Step\Acceptance\Admin $I)
    {
        $I->haveUserInDatabase('anna', 'author', ['user_pass' => 'password']);
        $I->loginAsAdmin();
        $I->amOnAdminPage('users.php');

        $I->moveMouseOver('.table-view-list tbody tr:nth-child(2)');
        $I->click('.table-view-list tbody tr:nth-child(2) a.resetpassword');

        $I->seeInLog('You', "Requested a password reset link for user with login 'anna' and email 'anna@example.com'");
    }

    public function logUsersBulkChangeRole(\Step\Acceptance\Admin $I)
    {
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

    public function logUserApplicationPasswordCreated(\Step\Acceptance\Admin $I)
    {
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

    public function logUserApplicationPasswordDeleted(\Step\Acceptance\Admin $I)
    {
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
