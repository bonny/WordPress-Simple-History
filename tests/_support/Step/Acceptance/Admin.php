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

    /**
     * Test that the latest interpolated message in the log
     * is equal to the passed string.
     * 
     * This kinda tests that both message and context are working.
     * 
     * @param string $message_to_test 
     */
    public function seeLogMessage(string $message_to_test) {
        // I can't find any "grabRow"-method so I will get the columns one by one.        
        $history_table = $this->grabPrefixedTableNameFor('simple_history');
        $contexts_table = $this->grabPrefixedTableNameFor('simple_history_contexts');
        $latest_id = max( $this->grabColumnFromDatabase($history_table, 'id', []) );
        $where = ['id' => $latest_id];
        $contexts_where = ['history_id' => $latest_id];

        $columns = [
            'id',
            'date',
            'logger',
            'message',
            'initiator'
        ];

        $context_columns = [
            'history_id',
            '`key`',
            'value',
        ];

        $column_values = [];
        $context_values = [];

        foreach ($columns as $column_name) {
            $column_values[$column_name] = $this->grabColumnFromDatabase($history_table, $column_name, $where)[0];
        }

        foreach ($context_columns as $column_name) {
            $column_name_key = str_replace('`key`', 'key', $column_name);
            
            if (!isset($context_values[$column_name_key])) {
                $context_values[$column_name_key] = [];
            }
            
            $context_values[$column_name_key][] = $this->grabColumnFromDatabase($contexts_table, $column_name, $contexts_where);
        }

        $context_keys_values = [];
        for ($i = 0; $i < count($context_values['key'][0]); $i++) {
            $context_key = $context_values['key'][0][$i];
            $context_value = $context_values['value'][0][$i];
            $context_keys_values[$context_key] = $context_value;
        }


        $interpolated_message = self::interpolate(
            $column_values['message'],
            $context_keys_values
        );

        $this->assertEquals($message_to_test, $interpolated_message);
    }

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param array  $row Currently not always passed, because loggers need to be updated to support this...
	 */
	public static function interpolate( $message, $context = array(), $row = null ) {
		if ( ! is_array( $context ) ) {
			return $message;
		}

		// Build a replacement array with braces around the context keys.
		$replace = array();
		foreach ( $context as $key => $val ) {
			// Both key and val must be strings or number (for vals)
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( is_string( $key ) || is_numeric( $key ) ) {
				// key ok
			}

			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( is_string( $val ) || is_numeric( $val ) ) {
				// val ok
			} else {
				// not a value we can replace
				continue;
			}

			$replace[ '{' . $key . '}' ] = $val;
		}

		// Interpolate replacement values into the message and return
		return strtr( $message, $replace );
	}

}
