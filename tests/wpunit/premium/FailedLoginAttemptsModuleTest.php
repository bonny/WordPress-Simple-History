<?php

use Helper\PremiumTestCase;
use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;
use Simple_History\AddOns\Pro\Extended_Settings;
use Simple_History\AddOns\Pro\Modules\Failed_Login_Attempts_Settings_Module;

/**
 * Issue 274: the premium "Failed login attempts" setting must apply to failed
 * application password authentications too, and those must not reset the
 * counters for ordinary failed logins.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit premium/FailedLoginAttemptsModuleTest
 *
 * @group premium
 */
class FailedLoginAttemptsModuleTest extends PremiumTestCase {
	/** Matches Failed_Login_Attempts_Settings_Module::CONSECUTIVE_ATTEMPTS_THRESHOLD. */
	private const THRESHOLD = 5;

	/** @var User_Logger */
	private $logger;

	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		$this->logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		$this->ensure_module_filters_are_registered();
		$this->reset_options();
	}

	/**
	 * The WordPress test case restores all hooks after each test, but the
	 * premium Extended_Settings singleton survives, so the module's do_log
	 * filters exist only for the first test that activated premium. Re-add
	 * them from the live module instance.
	 */
	private function ensure_module_filters_are_registered(): void {
		foreach ( Extended_Settings::get_instance()->get_instantiated_modules() as $module ) {
			if ( ! $module instanceof Failed_Login_Attempts_Settings_Module ) {
				continue;
			}

			if ( has_filter( 'simple_history/log/do_log', [ $module, 'filter_do_log' ] ) === false ) {
				add_filter( 'simple_history/log/do_log', [ $module, 'filter_do_log' ], 10, 5 );
				add_filter( 'simple_history/log/do_log', [ $module, 'filter_do_log_reset' ], 10, 5 );
			}
		}
	}

	public function tearDown(): void {
		$this->reset_options();
		parent::tearDown();
	}

	private function reset_options(): void {
		delete_option( Failed_Login_Attempts_Settings_Module::OPTION_NAME_EXISTING_USERS );
		delete_option( Failed_Login_Attempts_Settings_Module::OPTION_NAME_UNKNOWN_USERS );
		delete_option( Failed_Login_Attempts_Settings_Module::OPTION_NAME_COMBINE_CONSECUTIVE_ATTEMPTS );
		delete_option( 'sh_existing_users_failed_login_attempts_count' );
		delete_option( 'sh_unknown_users_failed_login_attempts_count' );
	}

	public function test_log_n_attempts_setting_applies_to_application_password_failures_for_existing_users() {
		update_option( Failed_Login_Attempts_Settings_Module::OPTION_NAME_EXISTING_USERS, 'log_n_failed_attempts' );

		$count_before = $this->get_event_count();

		for ( $i = 0; $i < self::THRESHOLD + 3; $i++ ) {
			$this->log_failed_login( 'user_application_password_login_failed' );
		}

		$this->assertEquals(
			$count_before + self::THRESHOLD,
			$this->get_event_count(),
			'Only the first N application password failures for existing users should be logged'
		);
	}

	public function test_log_nothing_setting_applies_to_application_password_failures_for_unknown_users() {
		update_option( Failed_Login_Attempts_Settings_Module::OPTION_NAME_UNKNOWN_USERS, 'log_nothing' );

		$count_before = $this->get_event_count();

		$this->log_failed_login( 'user_application_password_unknown_login_failed' );

		$this->assertEquals( $count_before, $this->get_event_count() );
	}

	public function test_application_password_failure_does_not_reset_regular_failed_login_counter() {
		update_option( Failed_Login_Attempts_Settings_Module::OPTION_NAME_UNKNOWN_USERS, 'log_n_failed_attempts' );

		$count_before = $this->get_event_count();

		// Existing-user setting is log_all, so the app-password failure itself logs.
		for ( $i = 0; $i < 3; $i++ ) {
			$this->log_failed_login( 'user_unknown_login_failed' );
		}
		$this->log_failed_login( 'user_application_password_login_failed' );
		for ( $i = 0; $i < 4; $i++ ) {
			$this->log_failed_login( 'user_unknown_login_failed' );
		}

		$this->assertEquals(
			$count_before + self::THRESHOLD + 1,
			$this->get_event_count(),
			'Unknown-user failures must stay throttled across an interleaved app-password failure'
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
