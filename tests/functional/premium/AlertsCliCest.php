<?php

use PHPUnit\Framework\SkippedTestError;

/**
 * Functional tests for the alerts WP-CLI commands.
 *
 * These tests verify that the CLI commands are properly registered and work as expected.
 * Requires the Simple History Premium plugin to be installed and active.
 *
 * @group premium
 * @group alerts
 * @group cli
 */
class AlertsCliCest {

	/** @var bool Whether premium is available. */
	private static $premium_checked = false;

	/** @var bool Whether premium is available. */
	private static $premium_available = false;

	/**
	 * Check if premium plugin is available before running tests.
	 *
	 * @param FunctionalTester $I The tester.
	 * @throws SkippedTestError If premium plugin is not available.
	 */
	public function _before( FunctionalTester $I ) {
		// Only check once per test run.
		if ( ! self::$premium_checked ) {
			self::$premium_checked = true;

			// Check if premium plugin is active by looking at the plugin list.
			try {
				$output = $I->cliToString( [ '--allow-root', 'plugin', 'list', '--status=active', '--format=csv' ] );
				self::$premium_available = strpos( $output, 'simple-history-premium' ) !== false;
			} catch ( \Exception $e ) {
				self::$premium_available = false;
			}
		}

		if ( ! self::$premium_available ) {
			throw new SkippedTestError( 'Simple History Premium plugin is not installed in the test environment.' );
		}
	}

	/**
	 * Test that alerts subcommands are available.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_alerts_commands_available( FunctionalTester $I ) {
		$I->cli( '--allow-root simple-history alerts' );
		$I->seeInShellOutput( 'wp simple-history alerts <command>' );
		$I->seeInShellOutput( 'destinations' );
		$I->seeInShellOutput( 'rules' );
	}

	/**
	 * Test destinations subcommands are available.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_subcommands_available( FunctionalTester $I ) {
		$I->cli( '--allow-root simple-history alerts destinations' );
		$I->seeInShellOutput( 'delete' );
		$I->seeInShellOutput( 'get' );
		$I->seeInShellOutput( 'list' );
		$I->seeInShellOutput( 'test' );
	}

	/**
	 * Test rules subcommands are available.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_rules_subcommands_available( FunctionalTester $I ) {
		$I->cli( '--allow-root simple-history alerts rules' );
		$I->seeInShellOutput( 'disable' );
		$I->seeInShellOutput( 'enable' );
		$I->seeInShellOutput( 'list' );
	}

	/**
	 * Test destinations list command with no destinations.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_list_empty( FunctionalTester $I ) {
		// Clear any existing destinations.
		$I->cli( '--allow-root option delete simple_history_alert_destinations' );

		$I->cli( '--allow-root simple-history alerts destinations list' );
		$I->seeInShellOutput( 'No destinations configured' );
	}

	/**
	 * Test destinations list command with destinations.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_list_with_data( FunctionalTester $I ) {
		// Create test destinations via WP option.
		// Note: config values are stored under 'config' key; recipients is a string (newline-separated).
		$destinations = [
			'dest_test_123' => [
				'type'   => 'email',
				'name'   => 'Test Email Dest',
				'config' => [
					'recipients' => 'test@example.com',
				],
			],
			'dest_test_456' => [
				'type'   => 'slack',
				'name'   => 'Test Slack Dest',
				'config' => [
					'webhook_url' => 'https://hooks.slack.com/services/xxx',
				],
			],
		];

		$I->haveOptionInDatabase( 'simple_history_alert_destinations', $destinations );

		$I->cli( '--allow-root simple-history alerts destinations list' );
		$I->seeInShellOutput( 'dest_test_123' );
		$I->seeInShellOutput( 'Test Email Dest' );
		$I->seeInShellOutput( 'email' );
		$I->seeInShellOutput( 'dest_test_456' );
		$I->seeInShellOutput( 'Test Slack Dest' );
		$I->seeInShellOutput( 'slack' );
	}

	/**
	 * Test destinations list with type filter.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_list_with_type_filter( FunctionalTester $I ) {
		$destinations = [
			'dest_email_1' => [
				'type'   => 'email',
				'name'   => 'Email One',
				'config' => [
					'recipients' => 'one@test.com',
				],
			],
			'dest_slack_1' => [
				'type'   => 'slack',
				'name'   => 'Slack One',
				'config' => [
					'webhook_url' => 'https://hooks.slack.com/xxx',
				],
			],
		];

		$I->haveOptionInDatabase( 'simple_history_alert_destinations', $destinations );

		$I->cli( '--allow-root simple-history alerts destinations list --type=email' );
		$I->seeInShellOutput( 'Email One' );
		$I->dontSeeInShellOutput( 'Slack One' );
	}

	/**
	 * Test destinations list with JSON format.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_list_json_format( FunctionalTester $I ) {
		$destinations = [
			'dest_json_test' => [
				'type'   => 'email',
				'name'   => 'JSON Test',
				'config' => [
					'recipients' => 'json@test.com',
				],
			],
		];

		$I->haveOptionInDatabase( 'simple_history_alert_destinations', $destinations );

		$result = $I->cliToString( [ '--allow-root', 'simple-history', 'alerts', 'destinations', 'list', '--format=json' ] );
		$I->assertJson( $result );
		$I->seeInShellOutput( '"id":"dest_json_test"' );
		$I->seeInShellOutput( '"name":"JSON Test"' );
	}

	/**
	 * Test destinations get command.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_get( FunctionalTester $I ) {
		$destinations = [
			'dest_get_test' => [
				'type'   => 'email',
				'name'   => 'Get Test Dest',
				'config' => [
					'recipients' => "a@test.com\nb@test.com",
				],
			],
		];

		$I->haveOptionInDatabase( 'simple_history_alert_destinations', $destinations );

		$I->cli( '--allow-root simple-history alerts destinations get dest_get_test' );
		$I->seeInShellOutput( 'dest_get_test' );
		$I->seeInShellOutput( 'Get Test Dest' );
		$I->seeInShellOutput( 'email' );
	}

	/**
	 * Test destinations get command with non-existent ID.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_get_not_found( FunctionalTester $I ) {
		$I->cli( '--allow-root simple-history alerts destinations get nonexistent_id', false );
		$I->seeInShellOutput( 'not found' );
	}

	/**
	 * Test rules list command.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_rules_list( FunctionalTester $I ) {
		$I->cli( '--allow-root simple-history alerts rules list' );
		$I->seeInShellOutput( 'security' );
		$I->seeInShellOutput( 'content' );
		$I->seeInShellOutput( 'plugins' );
		$I->seeInShellOutput( 'preset' );
	}

	/**
	 * Test rules enable command.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_rules_enable( FunctionalTester $I ) {
		$I->cli( '--allow-root simple-history alerts rules enable security' );
		$I->seeInShellOutput( 'Enabled alert rule' );

		// Verify it's enabled.
		$I->cli( '--allow-root simple-history alerts rules list' );
		$I->seeInShellOutput( 'yes' );
	}

	/**
	 * Test rules disable command.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_rules_disable( FunctionalTester $I ) {
		// First enable it.
		$I->cli( '--allow-root simple-history alerts rules enable security' );

		// Then disable it.
		$I->cli( '--allow-root simple-history alerts rules disable security' );
		$I->seeInShellOutput( 'Disabled alert rule' );
	}

	/**
	 * Test rules enable with invalid preset.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_rules_enable_invalid_preset( FunctionalTester $I ) {
		$I->cli( '--allow-root simple-history alerts rules enable invalid_preset', false );
		$I->seeInShellOutput( 'Invalid preset' );
	}

	/**
	 * Test destinations delete command.
	 *
	 * @param FunctionalTester $I The tester.
	 */
	public function test_destinations_delete( FunctionalTester $I ) {
		$destinations = [
			'dest_to_delete' => [
				'type'   => 'email',
				'name'   => 'Delete Me',
				'config' => [
					'recipients' => 'delete@test.com',
				],
			],
		];

		$I->haveOptionInDatabase( 'simple_history_alert_destinations', $destinations );

		$I->cli( '--allow-root simple-history alerts destinations delete dest_to_delete --yes' );
		$I->seeInShellOutput( 'Deleted destination' );

		// Verify it's deleted.
		$I->cli( '--allow-root simple-history alerts destinations list' );
		$I->dontSeeInShellOutput( 'Delete Me' );
	}
}
