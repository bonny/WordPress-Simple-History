<?php

use Simple_History\Events_Stats;
use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;

/**
 * Failed logins with an application password log under their own message
 * keys. The stats must count them together with the login-form failures.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit EventsStatsFailedLoginsTest
 */
class EventsStatsFailedLoginsTest extends \Codeception\TestCase\WPTestCase {
	/** @var Events_Stats */
	private $stats;

	/** @var User_Logger */
	private $user_logger;

	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$this->stats       = new Events_Stats();
		$this->user_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
	}

	public function test_failed_login_counts_include_application_password_failures() {
		$from = time() - HOUR_IN_SECONDS;
		$to   = time() + HOUR_IN_SECONDS;

		$count_before   = $this->stats->get_failed_logins_count( $from, $to );
		$details_before = count( $this->stats->get_failed_logins_details( $from, $to, 100, false ) );

		$this->user_logger->warning_message(
			'user_application_password_login_failed',
			array( 'login' => 'erik-app-password' )
		);
		$this->user_logger->warning_message(
			'user_application_password_unknown_login_failed',
			array( 'failed_username' => 'nobody-app-password' )
		);

		$this->assertSame( $count_before + 2, $this->stats->get_failed_logins_count( $from, $to ) );

		$usernames = array_column( $this->stats->get_failed_logins_details( $from, $to, 100, false ), 'attempted_username' );
		$this->assertContains( 'erik-app-password', $usernames );
		$this->assertContains( 'nobody-app-password', $usernames );
		$this->assertCount( $details_before + 2, $usernames );
	}
}
