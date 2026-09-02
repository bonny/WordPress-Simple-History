<?php

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;

/**
 * Issue 274: failed application password authentications are logged under
 * their own message keys, and the failed login suppression did not know
 * about them. They were never throttled, and they reset the counter for
 * ordinary failed logins so those were never throttled either.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit FailedLoginLimitServiceTest
 */
class FailedLoginLimitServiceTest extends \Codeception\TestCase\WPTestCase {
	private const THRESHOLD = 3;

	/** @var User_Logger */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		delete_option( 'sh_core_failed_login_count' );
		delete_option( 'sh_core_failed_login_total_suppressed' );

		add_filter( 'simple_history/failed_login_limit/threshold', [ $this, 'filter_threshold' ] );
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/failed_login_limit/threshold', [ $this, 'filter_threshold' ] );
		delete_option( 'sh_core_failed_login_count' );
		delete_option( 'sh_core_failed_login_total_suppressed' );
		parent::tearDown();
	}

	public function filter_threshold() {
		return self::THRESHOLD;
	}

	public function test_user_logger_exposes_all_four_failed_login_message_keys() {
		$keys = User_Logger::get_failed_login_message_keys();

		$this->assertEqualsCanonicalizing(
			[
				'user_login_failed',
				'user_unknown_login_failed',
				'user_application_password_login_failed',
				'user_application_password_unknown_login_failed',
			],
			$keys
		);
	}

	/**
	 * @dataProvider app_password_keys
	 */
	public function test_application_password_failures_are_throttled( string $message_key ) {
		$count_before = $this->get_event_count();

		for ( $i = 0; $i < self::THRESHOLD + 2; $i++ ) {
			$this->log_failed_login( $message_key );
		}

		$this->assertEquals(
			$count_before + self::THRESHOLD,
			$this->get_event_count(),
			"Only the first threshold attempts of {$message_key} should be logged"
		);
	}

	public function app_password_keys(): array {
		return [
			'existing user' => [ 'user_application_password_login_failed' ],
			'unknown user'  => [ 'user_application_password_unknown_login_failed' ],
		];
	}

	public function test_application_password_failure_does_not_reset_the_counter() {
		$count_before = $this->get_event_count();

		$this->log_failed_login( 'user_login_failed' );
		$this->log_failed_login( 'user_login_failed' );
		$this->log_failed_login( 'user_application_password_login_failed' );
		$this->log_failed_login( 'user_login_failed' );
		$this->log_failed_login( 'user_login_failed' );

		$this->assertEquals(
			$count_before + self::THRESHOLD,
			$this->get_event_count(),
			'An interleaved application password failure must not reset the counter'
		);
	}

	public function test_a_successful_event_still_resets_the_counter() {
		$count_before = $this->get_event_count();

		$this->log_failed_login( 'user_login_failed' );
		$this->log_failed_login( 'user_login_failed' );
		$this->logger->info_message( 'user_logged_in', [ 'user_id' => 1 ] );
		$this->log_failed_login( 'user_login_failed' );
		$this->log_failed_login( 'user_login_failed' );

		$this->assertEquals(
			$count_before + 5,
			$this->get_event_count(),
			'A non-failed-login event resets the counter so later failures log again'
		);
	}

	private function log_failed_login( string $message_key ): void {
		$this->logger->warning_message(
			$message_key,
			[
				'login'               => 'someone',
				'_server_remote_addr' => '203.0.113.10',
			]
		);
	}

	private function get_event_count(): int {
		global $wpdb;
		$table = Simple_History::get_instance()->get_events_table_name();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
