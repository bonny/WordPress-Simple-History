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

    public function loginAsAdminToHistorySettingsPage()
    {
        $I = $this;
        $I->loginAsAdmin();
        $I->amOnAdminPage('admin.php?page=simple_history_settings_page');
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
     * @param string $who Clear text initiator, i.e. "Anonymous web user", "Erik", "WP-CLI", ...
     * @param mixed $message Clear text message, i.e. "Logged in", "Added attachment", ...
     * @param int $child Default 1, i.e. the first row I think.
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
     * Get latest history row and context data.
     *
     * @param int $index 0 to get latest row, 1 to get second latest row, etc.
     * @return array
     */
    public function getHistory(int $index = 0): array
    {
        $history_table = $this->grabPrefixedTableNameFor('simple_history');
        $contexts_table = $this->grabPrefixedTableNameFor('simple_history_contexts');

        // Retry loop: the event row and context rows are written in separate
        // DB operations. Under load (full suite), context may lag behind.
        // Every real event has at least a _user_id context row.
        $max_attempts = 10;
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            // 1 query: get the event ID at the requested index (newest first).
            $ids = array_reverse($this->grabColumnFromDatabase($history_table, 'id', []));
            $latest_id = $ids[$index];

            // 1 query per column for the event row (5 total).
            // Codeception has no grabRow(), so this is the minimum.
            $column_values = [];
            foreach (['id', 'date', 'logger', 'message', 'initiator'] as $col) {
                $column_values[$col] = $this->grabColumnFromDatabase($history_table, $col, ['id' => $latest_id])[0];
            }

            // 2 queries: get context keys and values.
            $context_keys = $this->grabColumnFromDatabase($contexts_table, '`key`', ['history_id' => $latest_id]);
            $context_vals = $this->grabColumnFromDatabase($contexts_table, 'value', ['history_id' => $latest_id]);

            $context_keys_values = [];
            for ($i = 0; $i < count($context_keys); $i++) {
                $context_keys_values[$context_keys[$i]] = $context_vals[$i];
            }

            // If context rows exist, we're done.
            if (!empty($context_keys_values)) {
                break;
            }

            // Context not yet written — wait briefly and retry.
            if ($attempt < $max_attempts) {
                usleep(200000); // 200ms
            }
        }

        return [
            'row' => $column_values,
            'context' => $context_keys_values,
        ];
    }

    /**
     * 
     * @param mixed $initiator wp_user, web_user, ...
     * @return void 
     * @throws InjectionException 
     * @throws ConditionalAssertionFailed 
     * @throws Exception 
     */
    public function seeLogInitiator(string $initiator, int $index = 0)
    {
        $history = $this->getHistory($index);
        $this->assertEquals($initiator, $history['row']['initiator']);
    }

    /**
     * Test that the latest interpolated message in the log
     * is equal to the passed string.
     * 
     * This kinda tests that both message and context are working.
     * 
     * Example:
     * ```php
     * $I->seeLogMessage('Failed to login with username "erik" (username does not exist)');
     * ```
     * 
     * @param string $message_to_test 
     */
    public function seeLogMessage(string $message_to_test, int $index = 0)
    {
        ['row' => $row, 'context' => $context] = $this->getHistory($index);

        $interpolated_message = self::interpolate(
            $row['message'],
            $context,
        );

        $this->assertEquals($message_to_test, $interpolated_message);
    }

    /**
     * Test that the latest interpolated message in the log
     * begins with the passed string.
     * 
     * A reason to not test the full string may might be in a scenario where
     * the full message is a bit flaky during tests, for example when deleting
     * an attachment the message may be:
     * 'Deleted attachment "Image 1" ("Image-1-17.jpg")'
     * but on the next run it is
     * 'Deleted attachment "Image 1" ("Image-1-18.jpg")'
     * 
     * Example:
     * ```php
     * $I->seeLogMessage(''Deleted attachment "Image 1" ("Image-1');
     * ```
     * 
     * @param string $message_to_test 
     */
    public function seeLogMessageStartsWith(string $message_to_test, int $index = 0)
    {
        ['row' => $row, 'context' => $context] = $this->getHistory($index);

        $interpolated_message = self::interpolate(
            $row['message'],
            $context,
        );

        $this->assertStringStartsWith($message_to_test, $interpolated_message);
    }

    /**
     * Test that the last stored context matches the passed context.
     * Since the stored context can contain much data
     * the comparison is only done with the keys that are included in the
     * passed array.
     * 
     * Example:
     * ```php
     * $I->seeLogContext([
     *   'user_new_user_url' => 'https://example.com',
     *   'user_new_first_name' => 'Annaname',
     *   'user_new_last_name' => 'Doeauthor',
     *   'user_new_description' => 'Hello there, this is my description text.',
     * ]);
     * ```
     * 
     * @param array $expectedContext Array with expected key => value entries.
     */
    public function seeLogContext(array $expectedContext, int $index = 0)
    {
        ['row' => $row, 'context' => $foundContext] = $this->getHistory($index);

        // Only test the keys passed.
        $foundContext = array_intersect_key($foundContext, $expectedContext);

        $this->assertEquals($expectedContext, $foundContext);
    }

    /**
     * Assert that a log event with the given message template exists,
     * regardless of its index. Use this instead of seeLogMessage() when
     * system events (404s, wp_global_styles, etc.) may shift the index.
     *
     * @param string $messageTemplate The raw message with {placeholders}, e.g. 'Deleted {post_type} "{attachment_title}"'.
     */
    public function seeLogEventExists(string $messageTemplate)
    {
        $history_table = $this->grabPrefixedTableNameFor('simple_history');
        $row_id = $this->grabFromDatabase($history_table, 'id', [
            'message' => $messageTemplate,
        ]);
        $this->assertNotEmpty($row_id, "Log event with message '$messageTemplate' should exist");
    }

    /**
     * Debug function to output log context.
     * The function simply checks if the context is an empty array and
     * it's probably not and the function will fail and the contexts is
     * shown as the expected value.
     * 
     * @return void 
     */
    public function seeLogContextDebug(int $index = 0)
    {
        ['row' => $row, 'context' => $foundContext] = $this->getHistory($index);
        $this->assertEquals([], $foundContext);
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string $message
     * @param array  $context
     * @param array  $row Currently not always passed, because loggers need to be updated to support this...
     */
    public static function interpolate($message, $context = array(), $row = null)
    {
        if (!is_array($context)) {
            return $message;
        }

        // Build a replacement array with braces around the context keys.
        $replace = array();
        foreach ($context as $key => $val) {
            // Both key and val must be strings or number (for vals)
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
            if (is_string($key) || is_numeric($key)) {
                // key ok
            }

            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
            if (is_string($val) || is_numeric($val)) {
                // val ok
            } else {
                // not a value we can replace
                continue;
            }

            $replace['{' . $key . '}'] = $val;
        }

        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
}
