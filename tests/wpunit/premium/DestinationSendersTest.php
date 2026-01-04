<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Destinations\Email_Destination_Sender;
use Simple_History\AddOns\Pro\Destinations\Slack_Destination_Sender;
use Simple_History\AddOns\Pro\Destinations\Discord_Destination_Sender;
use Simple_History\AddOns\Pro\Destinations\Telegram_Destination_Sender;

/**
 * Tests for premium destination senders.
 *
 * @group premium
 * @group destinations
 * @group alerts
 */
class DestinationSendersTest extends PremiumTestCase {
	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();
	}

	// =========================================================================
	// Email Destination Sender Tests
	// =========================================================================

	/**
	 * Test Email sender class exists.
	 */
	public function test_email_sender_exists(): void {
		$this->assertTrue(
			class_exists( Email_Destination_Sender::class ),
			'Email_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Email sender type.
	 */
	public function test_email_sender_type(): void {
		$sender = new Email_Destination_Sender();
		$this->assertEquals( 'email', $sender->get_type() );
	}

	/**
	 * Test Email sender name.
	 */
	public function test_email_sender_name(): void {
		$sender = new Email_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	/**
	 * Test Email sender get_test_event_data returns proper structure.
	 */
	public function test_email_sender_get_test_event_data(): void {
		$sender    = new Email_Destination_Sender();
		$test_data = $sender->get_test_event_data();

		$this->assertArrayHasKey( 'event_data', $test_data );
		$this->assertArrayHasKey( 'context', $test_data );
		$this->assertArrayHasKey( 'level', $test_data['event_data'] );
		$this->assertArrayHasKey( 'message', $test_data['event_data'] );
	}

	/**
	 * Test Email sender fails with empty recipients.
	 */
	public function test_email_sender_fails_with_empty_recipients(): void {
		$sender = new Email_Destination_Sender();

		$config = [
			'config' => [
				'recipients' => '',
			],
		];

		$result = $sender->test( $config );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['message'] );
	}

	// =========================================================================
	// Slack Destination Sender Tests
	// =========================================================================

	/**
	 * Test Slack sender class exists.
	 */
	public function test_slack_sender_exists(): void {
		$this->assertTrue(
			class_exists( Slack_Destination_Sender::class ),
			'Slack_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Slack sender type.
	 */
	public function test_slack_sender_type(): void {
		$sender = new Slack_Destination_Sender();
		$this->assertEquals( 'slack', $sender->get_type() );
	}

	/**
	 * Test Slack sender name.
	 */
	public function test_slack_sender_name(): void {
		$sender = new Slack_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	// =========================================================================
	// Discord Destination Sender Tests
	// =========================================================================

	/**
	 * Test Discord sender class exists.
	 */
	public function test_discord_sender_exists(): void {
		$this->assertTrue(
			class_exists( Discord_Destination_Sender::class ),
			'Discord_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Discord sender type.
	 */
	public function test_discord_sender_type(): void {
		$sender = new Discord_Destination_Sender();
		$this->assertEquals( 'discord', $sender->get_type() );
	}

	/**
	 * Test Discord sender name.
	 */
	public function test_discord_sender_name(): void {
		$sender = new Discord_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	// =========================================================================
	// Telegram Destination Sender Tests
	// =========================================================================

	/**
	 * Test Telegram sender class exists.
	 */
	public function test_telegram_sender_exists(): void {
		$this->assertTrue(
			class_exists( Telegram_Destination_Sender::class ),
			'Telegram_Destination_Sender class should exist.'
		);
	}

	/**
	 * Test Telegram sender type.
	 */
	public function test_telegram_sender_type(): void {
		$sender = new Telegram_Destination_Sender();
		$this->assertEquals( 'telegram', $sender->get_type() );
	}

	/**
	 * Test Telegram sender name.
	 */
	public function test_telegram_sender_name(): void {
		$sender = new Telegram_Destination_Sender();
		$this->assertNotEmpty( $sender->get_name() );
	}

	// =========================================================================
	// Cross-sender Tests
	// =========================================================================

	/**
	 * Test all senders have unique types.
	 */
	public function test_all_senders_have_unique_types(): void {
		$senders = [
			new Email_Destination_Sender(),
			new Slack_Destination_Sender(),
			new Discord_Destination_Sender(),
			new Telegram_Destination_Sender(),
		];

		$types = [];
		foreach ( $senders as $sender ) {
			$type = $sender->get_type();
			$this->assertNotContains(
				$type,
				$types,
				"Duplicate sender type found: {$type}"
			);
			$types[] = $type;
		}

		$this->assertCount( 4, $types );
	}

	/**
	 * Test all senders have non-empty names.
	 */
	public function test_all_senders_have_names(): void {
		$senders = [
			new Email_Destination_Sender(),
			new Slack_Destination_Sender(),
			new Discord_Destination_Sender(),
			new Telegram_Destination_Sender(),
		];

		foreach ( $senders as $sender ) {
			$this->assertNotEmpty(
				$sender->get_name(),
				"Sender {$sender->get_type()} should have a name."
			);
		}
	}

	/**
	 * Test all senders provide test event data.
	 */
	public function test_all_senders_provide_test_event_data(): void {
		$senders = [
			new Email_Destination_Sender(),
			new Slack_Destination_Sender(),
			new Discord_Destination_Sender(),
			new Telegram_Destination_Sender(),
		];

		foreach ( $senders as $sender ) {
			$test_data = $sender->get_test_event_data();

			$this->assertArrayHasKey(
				'event_data',
				$test_data,
				"Sender {$sender->get_type()} test data should have event_data."
			);
			$this->assertArrayHasKey(
				'context',
				$test_data,
				"Sender {$sender->get_type()} test data should have context."
			);
		}
	}
}
