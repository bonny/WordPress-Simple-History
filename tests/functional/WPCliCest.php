<?php

class WPCliCest {

	public function test_wp_cli_commands( FunctionalTester $I ) {
        // Test WP Version so we are not surprised by WP version changes.
        $I->cli('--allow-root core version');
        $I->seeInShellOutput('6.8');

        // Verify main WP-CLI commands are available.
        $I->cli('--allow-root simple-history');
        $I->seeInShellOutput('wp simple-history core-files <command>');
        $I->seeInShellOutput('wp simple-history db <command>');
        $I->seeInShellOutput('wp simple-history event <command>');
        $I->seeInShellOutput('wp simple-history list');
        $I->seeInShellOutput('wp simple-history stealth-mode <command>');

        // Verify key list command options exist.
        $I->seeInShellOutput('--format=<format>');
        $I->seeInShellOutput('--count=<count>');
        $I->seeInShellOutput('--search=<term>');
        $I->seeInShellOutput('--surrounding_event_id=<id>');
        $I->seeInShellOutput('--surrounding_count=<count>');
        
        $I->haveUserInDatabase(
            'luca', 
            'editor', 
            [
                'user_email' => 'luca@example.org',
                'user_pass' => 'passw0rd',
            ]
        );
        $I->loginAs('luca', 'passw0rd');

        $I->cli('--allow-root simple-history list --count=1');
        $I->seeInShellOutput('ID	date	initiator	description	via	level	count');
        $I->seeInShellOutput('luca (luca@example.org)	Logged in		info	1');

        $result = $I->cliToString(['--allow-root', 'simple-history', 'list', '--format=json']);
        $I->assertJson($result);
        // Test part of the JSON.
        $I->seeInShellOutput('"initiator":"luca (luca@example.org)","description":"Logged in","via":null,"level":"info","count":"1"}');
        $I->seeInShellOutput('"ID":"1"');
    }
    
    public function test_wp_cron( FunctionalTester $I ) {
        $I->cli('--allow-root cron event list');
        $I->seeInShellOutput('simple_history/maybe_purge_db');
        $I->seeInShellOutput('simple_history/tests/cron');

        $I->cli('--allow-root cron test');
        $I->seeInShellOutput('Success: WP-Cron spawning is working as expected.');

        $I->cli('--allow-root cron event run simple_history/maybe_purge_db');
        $I->seeInShellOutput("Executed the cron event 'simple_history/maybe_purge_db' in");
        $I->seeInShellOutput("Success: Executed a total of 1 cron event.");

        $I->cli('--allow-root cron event run simple_history/tests/cron');
        $I->cli('--allow-root simple-history list --count=1');
        $I->seeInShellOutput('This is a log from a cron job');
        $I->seeInShellOutput('info');
        $I->seeInShellOutput('WP-CLI');
    }

    public function test_list_userid_filter( FunctionalTester $I ) {
        // Create two users and log them in to generate events.
        $I->haveUserInDatabase(
            'alice',
            'editor',
            [
                'user_email' => 'alice@example.org',
                'user_pass' => 'passw0rd',
            ]
        );
        $I->haveUserInDatabase(
            'bob',
            'author',
            [
                'user_email' => 'bob@example.org',
                'user_pass' => 'passw0rd',
            ]
        );

        $I->loginAs('alice', 'passw0rd');
        $I->loginAs('bob', 'passw0rd');

        // Get alice's user ID from the database.
        $alice_id = $I->grabUserIdFromDatabase('alice');

        // Filter events by alice's user ID — should see alice but not bob.
        $I->cli("--allow-root simple-history list --userid={$alice_id} --format=table");
        $I->seeInShellOutput('alice');
        $I->dontSeeInShellOutput('bob');

        // Verify --exclude_userid excludes alice.
        $I->cli("--allow-root simple-history list --exclude_userid={$alice_id} --format=table");
        $I->dontSeeInShellOutput('alice (alice@example.org)	Logged in');
    }

    public function test_stealth_mode( FunctionalTester $I ) {
        $I->cli('--allow-root simple-history stealth-mode status');

        $I->seeInShellOutput('Full Stealth Mode	Disabled');
        $I->seeInShellOutput('Partial Stealth Mode	Disabled');
    }

    public function test_list_search( FunctionalTester $I ) {
        $I->haveUserInDatabase(
            'carol',
            'editor',
            [
                'user_email' => 'carol@example.org',
                'user_pass' => 'passw0rd',
            ]
        );
        $I->loginAs('carol', 'passw0rd');

        // Search via the canonical list --search flag.
        $I->cli('--allow-root simple-history list --search=carol');
        $I->seeInShellOutput('carol (carol@example.org)');

        // Metadata search finds the login event by a context value
        // (login events store user_email in context).
        $I->cli('--allow-root simple-history list --metadata_search=carol@example.org');
        $I->seeInShellOutput('carol (carol@example.org)');

        // The ai_only flag is accepted and excludes regular user events.
        // (Positive AI matches are covered in wpunit SearchTest —
        // functional tests cannot create events with AI context.)
        $I->cli('--allow-root simple-history list --ai_only');
        $I->dontSeeInShellOutput('carol (carol@example.org)');
    }

    public function test_event_search_deprecated_alias( FunctionalTester $I ) {
        $I->haveUserInDatabase(
            'dave',
            'editor',
            [
                'user_email' => 'dave@example.org',
                'user_pass' => 'passw0rd',
            ]
        );
        $I->loginAs('dave', 'passw0rd');

        // The deprecated command must still return matching events.
        // Regression: it previously returned zero rows because its empty-string
        // date defaults were parsed as "now" by Log_Query.
        // The deprecation warning is not asserted here because WP_CLI::warning()
        // writes to STDERR, which $I->cli() does not capture.
        $I->cli('--allow-root simple-history event search dave');
        $I->seeInShellOutput('dave (dave@example.org)');

        // Date flags translate to list's date_from/date_to:
        // a far-future newer_than must exclude the event...
        $I->cli('--allow-root simple-history event search dave --newer_than=2099-01-01');
        $I->dontSeeInShellOutput('dave (dave@example.org)');

        // ...and a far-past older_than must too.
        $I->cli('--allow-root simple-history event search dave --older_than=2000-01-01');
        $I->dontSeeInShellOutput('dave (dave@example.org)');
    }
}
