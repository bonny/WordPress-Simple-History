<?php

use Helper\PremiumTestCase;

/**
 * Tests for Alerts_Module::get_destination() method.
 *
 * Requires Simple History Premium plugin to be available.
 *
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit AlertsModuleGetDestinationTest`
 */
class AlertsModuleGetDestinationTest extends PremiumTestCase {

	/**
	 * @var array Test destinations to use across tests.
	 */
	private $test_destinations;

	public function setUp(): void {
		parent::setUp();

		$this->activate_premium();

		// Set up test destinations.
		$this->test_destinations = [
			'dest_test_email'   => [
				'type'   => 'email',
				'name'   => 'Test Email',
				'config' => [
					'recipients' => 'test@example.com',
				],
			],
			'dest_test_slack'   => [
				'type'   => 'slack',
				'name'   => 'Test Slack',
				'config' => [
					'webhook_url' => 'https://hooks.slack.com/services/test',
				],
			],
			'dest_test_discord' => [
				'type'   => 'discord',
				'name'   => 'Test Discord',
				'config' => [
					'webhook_url' => 'https://discord.com/api/webhooks/test',
				],
			],
		];

		// Save test destinations.
		\Simple_History\AddOns\Pro\Modules\Alerts_Module::save_destinations( $this->test_destinations );
	}

	public function tearDown(): void {
		\Simple_History\AddOns\Pro\Modules\Alerts_Module::save_destinations( [] );
		parent::tearDown();
	}

	/**
	 * Test that get_destination returns the correct destination for a valid ID.
	 */
	public function test_get_destination_returns_destination_for_valid_id() {
		$destination = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( 'dest_test_email' );

		$this->assertIsArray( $destination, 'Should return an array for valid ID.' );
		$this->assertEquals( 'email', $destination['type'], 'Destination type should match.' );
		$this->assertEquals( 'Test Email', $destination['name'], 'Destination name should match.' );
		$this->assertEquals( 'test@example.com', $destination['config']['recipients'], 'Destination config should match.' );
	}

	/**
	 * Test that get_destination returns null for an invalid ID.
	 */
	public function test_get_destination_returns_null_for_invalid_id() {
		$destination = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( 'dest_nonexistent' );

		$this->assertNull( $destination, 'Should return null for invalid ID.' );
	}

	/**
	 * Test that get_destination returns null for empty string ID.
	 */
	public function test_get_destination_returns_null_for_empty_id() {
		$destination = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( '' );

		$this->assertNull( $destination, 'Should return null for empty ID.' );
	}

	/**
	 * Test that get_destination works for all destination types.
	 */
	public function test_get_destination_works_for_all_types() {
		// Test email destination.
		$email = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( 'dest_test_email' );
		$this->assertEquals( 'email', $email['type'], 'Email destination type should match.' );

		// Test Slack destination.
		$slack = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( 'dest_test_slack' );
		$this->assertEquals( 'slack', $slack['type'], 'Slack destination type should match.' );

		// Test Discord destination.
		$discord = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( 'dest_test_discord' );
		$this->assertEquals( 'discord', $discord['type'], 'Discord destination type should match.' );
	}

	/**
	 * Test that get_destination is consistent with get_destinations.
	 */
	public function test_get_destination_consistent_with_get_destinations() {
		$all_destinations = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destinations();

		foreach ( $all_destinations as $id => $expected_destination ) {
			$actual_destination = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( $id );
			$this->assertEquals(
				$expected_destination,
				$actual_destination,
				"get_destination('$id') should return the same data as get_destinations()['$id']."
			);
		}
	}

	/**
	 * Test that get_destination returns null when no destinations exist.
	 */
	public function test_get_destination_returns_null_when_no_destinations() {
		// Clear all destinations.
		\Simple_History\AddOns\Pro\Modules\Alerts_Module::save_destinations( [] );

		$destination = \Simple_History\AddOns\Pro\Modules\Alerts_Module::get_destination( 'dest_test_email' );

		$this->assertNull( $destination, 'Should return null when no destinations exist.' );
	}
}
