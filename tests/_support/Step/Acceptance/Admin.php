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
     * Activate a plugin from the plugins screen, identified by its plugin file.
     *
     * wp-browser's activatePlugin() clicks `a#activate-{slug}`, and WordPress builds
     * that id from the plugin's *display name*. A plugin renaming itself upstream —
     * "Duplicate Post" becoming "Yoast Duplicate Post", say — silently breaks every
     * test that activates it. The `data-plugin` attribute holds the plugin file path
     * instead, which stays put across renames.
     *
     * Navigates to the plugins screen itself and is idempotent, so it is safe to call
     * from a _before() that runs once per test method.
     *
     * @param string $plugin_file Plugin file relative to the plugins directory,
     *                            i.e. "duplicate-post/duplicate-post.php".
     */
    public function activatePluginByFile(string $plugin_file)
    {
        $I = $this;

        $I->amOnPluginsPage();

        // Fail with a useful message if the plugin was never copied into tests/plugins.
        $I->seeElement("tr[data-plugin='{$plugin_file}']");

        $is_active = $I->executeJS(
            "return !!document.querySelector('tr[data-plugin=\"{$plugin_file}\"].active')"
        );

        if (!$is_active) {
            $I->click("tr[data-plugin='{$plugin_file}'] span.activate a");
        }

        $I->waitForElement("tr[data-plugin='{$plugin_file}'].active", 10);
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
     * The event row and its context rows are written in separate DB
     * operations, so under load the context may lag behind the row. By
     * default the helper waits until every {placeholder} in the row's
     * message template has been written to context — this keeps the row,
     * its template, and its context self-consistent within a single
     * retry iteration (no TOCTOU between two reads).
     *
     * Pass $required_context_keys to additionally require specific keys
     * (e.g. for seeLogContext, which knows the keys upfront).
     *
     * @param int $index 0 to get latest row, 1 to get second latest row, etc.
     * @param array $required_context_keys Extra keys that must exist in context before returning.
     * @return array
     * @throws \RuntimeException When no event row appears at $index within the retry budget.
     */
    public function getHistory(int $index = 0, array $required_context_keys = []): array
    {
        $history_table = $this->grabPrefixedTableNameFor('simple_history');
        $contexts_table = $this->grabPrefixedTableNameFor('simple_history_contexts');

        $column_values = [];
        $context_keys_values = [];
        $latest_id = null;

        $max_attempts = 10;
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            // grabColumnFromDatabase returns rows in storage order, which is
            // usually but not guaranteed to be insertion order. Sort numerically
            // descending so $index 0 is always the newest event.
            $ids = $this->grabColumnFromDatabase($history_table, 'id', []);
            rsort($ids, SORT_NUMERIC);
            $latest_id = $ids[$index] ?? null;

            if ($latest_id === null) {
                if ($attempt < $max_attempts) {
                    usleep(200000);
                    continue;
                }
                break;
            }

            // Codeception has no grabRow(), so one query per column.
            $column_values = [];
            foreach (['id', 'date', 'logger', 'message', 'initiator'] as $col) {
                $column_values[$col] = $this->grabColumnFromDatabase($history_table, $col, ['id' => $latest_id])[0];
            }

            $context_keys = $this->grabColumnFromDatabase($contexts_table, '`key`', ['history_id' => $latest_id]);
            $context_vals = $this->grabColumnFromDatabase($contexts_table, 'value', ['history_id' => $latest_id]);

            $context_keys_values = [];
            $context_count = count($context_keys);
            for ($i = 0; $i < $context_count; $i++) {
                $context_keys_values[$context_keys[$i]] = $context_vals[$i];
            }

            // Auto-require the placeholders from the message template that
            // belongs to *this* iteration's row, so caller assertions never
            // see a template/context mismatch.
            $effective_required = array_merge(
                self::extractPlaceholderKeys($column_values['message']),
                $required_context_keys
            );

            $context_ready = $effective_required
                ? empty(array_diff($effective_required, array_keys($context_keys_values)))
                : ! empty($context_keys_values);

            if ($context_ready) {
                break;
            }

            if ($attempt < $max_attempts) {
                usleep(200000);
            }
        }

        if ($latest_id === null) {
            throw new \RuntimeException(sprintf(
                'getHistory(): no event row at index %d after %d attempts; the simple_history table is empty.',
                $index,
                $max_attempts
            ));
        }

        return [
            'row' => $column_values,
            'context' => $context_keys_values,
        ];
    }

    /**
     * Extract placeholder keys (the {foo} segments) from a message template.
     */
    private static function extractPlaceholderKeys(string $message): array
    {
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $message, $matches)) {
            return array_unique($matches[1]);
        }
        return [];
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
        ['row' => $row, 'context' => $foundContext] = $this->getHistory($index, array_keys($expectedContext));

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
