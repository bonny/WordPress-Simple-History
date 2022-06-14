<?php

namespace Step\Acceptance;

use Codeception\Exception\InjectionException;
use Codeception\Exception\ConditionalAssertionFailed;
use \Codeception\Module\WPWebDriver;
use \Codeception\Module\WebDriver;
use Exception;

class Admin extends \AcceptanceTester
{

    public function loginAsAdminToHistoryPage()
    {
        $I = $this;
        $I->loginAsAdmin();
        $I->amOnAdminPage('index.php?page=simple_history_page');
    }

    public function loginAsToHistoryPage(string $username, string $password)
    {
        $I = $this;
        $I->loginAs($username, $password);
        $I->amOnAdminPage('index.php?page=simple_history_page');
    }

    /**
     * Check log entry for a message.
     * 
     * @param string $who Clear text initator, i.e. "Anonymous web user", "Erik", "WP-CLI", ...
     * @param mixed $message Clear text message, i.e. "Logged in", "Added attachment", ...
     */
    public function seeInLog($who, $message, $child = 1)
    {
        $I = $this;

        $I->amOnAdminPage('index.php?page=simple_history_page');
        
        $I->waitForElementVisible('.SimpleHistoryLogitems');

        $I->see($who, ".SimpleHistoryLogitem:nth-child({$child}) .SimpleHistoryLogitem__header");
        $I->see($message, ".SimpleHistoryLogitem:nth-child({$child}) .SimpleHistoryLogitem__text");
    }

    /**
     * Check a log entry returned for value in keyValueTable
     * 
     * @param string $who Text that contain text key and both the new and old value, i.e. "First name Hanna Anna" (where Anna is the removed name and Hanna the added).
     * @param mixed $message Clear text message, i.e. "Logged in", "Added attachment", ...
     */
    public function seeInLogKeyValueTable($text, $child = 1)
    {
        $I = $this;

        $I->amOnAdminPage('index.php?page=simple_history_page');
        
        $I->waitForElementVisible('.SimpleHistoryLogitems');

        $I->see($text, ".SimpleHistoryLogitem:nth-child({$child}) .SimpleHistoryLogitem__details tr");
    }

    /**
     * @param mixed $who 
     * @param mixed $message 
     * @param int $child Default 2 because num 1 is the logged event for the admin logging in.
     */
    public function seeInLogAsAdmin($who, $message, $child = 2)
    {
        $I = $this;

        $I->loginAsAdmin();

        $I->amOnAdminPage('index.php?page=simple_history_page');

        $I->waitForElementVisible('.SimpleHistoryLogitems');

        $I->see($who, ".SimpleHistoryLogitem:nth-child({$child}) .SimpleHistoryLogitem__header");
        $I->see($message, ".SimpleHistoryLogitem:nth-child({$child}) .SimpleHistoryLogitem__text");
    }
}
