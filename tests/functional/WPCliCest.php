<?php

class WPCliCest {

	public function test_wp_cli_commands( FunctionalTester $I ) {
        // Test WP Version so we are not surprised by WP version changes.
        $I->cli('core version');
        $I->seeInShellOutput('5.8.3');

        $I->cli('simple-history');
        $I->seeInShellOutput('usage: wp simple-history list [--format=<format>] [--count=<count>]');
        
        $I->haveUserInDatabase(
            'luca', 
            'editor', 
            [
                'user_email' => 'luca@example.org',
                'user_pass' => 'passw0rd',
            ]
        );
        $I->loginAs('luca', 'passw0rd');

        $I->cli('simple-history list --count=1');
        $I->seeInShellOutput('date	initiator	description	level	count');
        $I->seeInShellOutput('luca (luca@example.org)	Logged in	info	1');

        $result = $I->cliToString('simple-history list --format=json');
        $I->assertJson($result);
        // Test part of the JSON.
        $I->seeInShellOutput('"initiator":"luca (luca@example.org)","description":"Logged in","level":"info","count":"1"}');
    }
}

