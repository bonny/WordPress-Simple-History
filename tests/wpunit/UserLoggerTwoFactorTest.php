<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;
use Simple_History\Loggers\Plugin_Two_Factor_Logger;

/**
 * Logins on sites with a two-factor plugin (issue 287).
 *
 * The Two Factor plugin lets core's `wp_login` fire when the password is
 * correct, then destroys the session and shows its challenge. Logging
 * "Logged in" on `wp_login` therefore recorded a login that never completed.
 *
 * The Two Factor plugin is not installed in the test environment, so these
 * tests drive the User logger through its two-factor filters and call the
 * Two Factor logger's handlers with the arguments Two Factor passes.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit UserLoggerTwoFactorTest
 */
class UserLoggerTwoFactorTest extends \Codeception\TestCase\WPTestCase {
	/** @var User_Logger */
	private $logger;

	/** @var Plugin_Two_Factor_Logger */
	private $two_factor_logger;

	/** @var array List of [$data, $context] written by the User logger. */
	private $captured_logs = [];

	/** @var \WP_User */
	private $user;

	public function setUp(): void {
		parent::setUp();

		$sh = Simple_History::get_instance();

		$this->logger            = $sh->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
		$this->two_factor_logger = $sh->get_instantiated_logger_by_slug( 'PluginTwoFactorLogger' );

		$this->assertInstanceOf( User_Logger::class, $this->logger );
		$this->assertInstanceOf( Plugin_Two_Factor_Logger::class, $this->two_factor_logger );

		$this->user = self::factory()->user->create_and_get(
			[
				'user_login' => 'twofactoruser',
				'user_email' => 'twofactoruser@example.com',
				'role'       => 'administrator',
			]
		);

		$this->captured_logs = [];
		add_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_log_write' ], 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_log_write' ], 10 );
		remove_filter( 'simple_history/user_logger/login_pending_second_factor', '__return_true' );
		remove_filter( 'simple_history/user_logger/two_factor_login', [ $this, 'filter_two_factor_login' ] );
		parent::tearDown();
	}

	/**
	 * Snapshot every User logger write.
	 *
	 * @param array $data_and_context [$data, $context] tuple.
	 * @param mixed $instance         Logger writing the row.
	 * @return array Unchanged.
	 */
	public function capture_log_write( $data_and_context, $instance ) {
		if ( $instance instanceof User_Logger ) {
			$this->captured_logs[] = $data_and_context;
		}

		return $data_and_context;
	}

	/** @var array|null Returned by filter_two_factor_login(). */
	private $two_factor_login = null;

	public function filter_two_factor_login() {
		return $this->two_factor_login;
	}

	/**
	 * Message keys of the captured writes.
	 *
	 * @return string[]
	 */
	private function captured_message_keys() {
		return array_map(
			function ( $data_and_context ) {
				return $data_and_context[1]['_message_key'];
			},
			$this->captured_logs
		);
	}

	public function test_login_without_two_factor_plugin_stores_no_two_factor_keys() {
		$this->logger->on_wp_login( $this->user->user_login, $this->user );

		$this->assertSame( [ 'user_logged_in' ], $this->captured_message_keys() );

		$context = $this->captured_logs[0][1];
		$this->assertSame( $this->user->ID, $context['_user_id'] );
		$this->assertArrayNotHasKey( User_Logger::CONTEXT_TWO_FACTOR_USED, $context );
		$this->assertArrayNotHasKey( User_Logger::CONTEXT_TWO_FACTOR_PLUGIN, $context );
	}

	/**
	 * A two-factor plugin is active but this user does not use it: the login
	 * is logged straight away and says that no second factor was used.
	 */
	public function test_login_without_second_factor_on_two_factor_site_is_marked_not_used() {
		$this->two_factor_login = [ 'plugin' => 'two-factor', 'used' => false ];
		add_filter( 'simple_history/user_logger/two_factor_login', [ $this, 'filter_two_factor_login' ] );

		$this->logger->on_wp_login( $this->user->user_login, $this->user );

		$this->assertSame( [ 'user_logged_in' ], $this->captured_message_keys() );

		$context = $this->captured_logs[0][1];
		$this->assertSame( 0, $context[ User_Logger::CONTEXT_TWO_FACTOR_USED ] );
		$this->assertSame( 'two-factor', $context[ User_Logger::CONTEXT_TWO_FACTOR_PLUGIN ] );
		$this->assertArrayNotHasKey( User_Logger::CONTEXT_TWO_FACTOR_METHOD, $context );
	}

	/**
	 * A plugin that checks the code before `wp_login`, like Wordfence, reports
	 * on `wp_login` that the second factor was used and how.
	 */
	public function test_login_checked_before_wp_login_stores_method() {
		$this->two_factor_login = [ 'plugin' => 'wordfence', 'used' => true, 'method' => 'Passkey <i>' ];
		add_filter( 'simple_history/user_logger/two_factor_login', [ $this, 'filter_two_factor_login' ] );

		$this->logger->on_wp_login( $this->user->user_login, $this->user );

		$context = $this->captured_logs[0][1];
		$this->assertSame( 1, $context[ User_Logger::CONTEXT_TWO_FACTOR_USED ] );
		$this->assertSame( 'wordfence', $context[ User_Logger::CONTEXT_TWO_FACTOR_PLUGIN ] );
		$this->assertSame( 'Passkey', $context[ User_Logger::CONTEXT_TWO_FACTOR_METHOD ] );
	}

	/**
	 * Anything else than a details array with a plugin slug is ignored.
	 */
	public function test_invalid_two_factor_login_details_are_ignored() {
		add_filter( 'simple_history/user_logger/two_factor_login', [ $this, 'filter_two_factor_login' ] );

		foreach ( [ 'wordfence', [ 'used' => true ], [ 'plugin' => 5 ] ] as $details ) {
			$this->two_factor_login = $details;
			$this->logger->on_wp_login( $this->user->user_login, $this->user );
		}

		foreach ( $this->captured_logs as $log ) {
			$this->assertArrayNotHasKey( User_Logger::CONTEXT_TWO_FACTOR_USED, $log[1] );
		}
	}

	public function test_login_pending_second_factor_is_not_logged_on_wp_login() {
		add_filter( 'simple_history/user_logger/login_pending_second_factor', '__return_true' );

		$this->logger->on_wp_login( $this->user->user_login, $this->user );

		$this->assertSame( [], $this->captured_message_keys(), 'A login waiting for its second factor has not happened yet.' );
	}

	public function test_passed_second_factor_logs_login_with_two_factor_keys() {
		$provider = new class() {
			public function get_label() {
				return 'Authenticator App';
			}
		};

		$this->two_factor_logger->on_two_factor_user_authenticated( $this->user, $provider );

		$this->assertSame( [ 'user_logged_in' ], $this->captured_message_keys() );

		$context = $this->captured_logs[0][1];
		$this->assertSame( 'SimpleUserLogger', $this->captured_logs[0][0]['logger'] );
		$this->assertSame( $this->user->ID, $context['_user_id'] );
		$this->assertSame( 1, $context[ User_Logger::CONTEXT_TWO_FACTOR_USED ] );
		$this->assertSame( 'two-factor', $context[ User_Logger::CONTEXT_TWO_FACTOR_PLUGIN ] );
		$this->assertSame( 'Authenticator App', $context[ User_Logger::CONTEXT_TWO_FACTOR_METHOD ] );
	}

	public function test_plugin_loggers_report_nothing_when_plugin_is_missing() {
		$this->assertFalse( class_exists( 'Two_Factor_Core' ) );
		$this->assertNull( $this->two_factor_logger->on_two_factor_login( null ) );
		$this->assertFalse( $this->two_factor_logger->on_login_pending_second_factor( false, $this->user ) );

		$wordfence_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'PluginWordfenceLogger' );

		$this->assertFalse( class_exists( 'WordfenceLS\Controller_Users' ) );
		$this->assertNull( $wordfence_logger->on_two_factor_login( null, $this->user ) );
	}

	public function test_failed_second_factor_is_logged_as_failed_login() {
		$error = new WP_Error( 'two_factor_invalid', 'ERROR: Invalid verification code.' );

		$this->logger->on_wp_login_failed( $this->user->user_login, $error );

		$this->assertSame( [ 'user_two_factor_login_failed' ], $this->captured_message_keys() );

		$context = $this->captured_logs[0][1];
		$this->assertSame( $this->user->ID, $context['login_id'] );
		$this->assertSame( 1, $context[ User_Logger::CONTEXT_TWO_FACTOR_USED ] );
		$this->assertSame( 'two-factor', $context[ User_Logger::CONTEXT_TWO_FACTOR_PLUGIN ] );
		$this->assertSame( 'two_factor_invalid', $context[ User_Logger::CONTEXT_TWO_FACTOR_ERROR ] );
		$this->assertContains( 'user_two_factor_login_failed', User_Logger::get_failed_login_message_keys() );
	}

	/**
	 * Kadence Security (formerly Solid Security) and Wordfence use their own error codes.
	 * Wordfence passes the typed username, which can be an email address.
	 */
	public function test_failed_second_factor_from_other_plugins_is_logged() {
		$this->logger->on_wp_login_failed( $this->user->user_login, new WP_Error( 'itsec-two-factor-invalid-code', 'Invalid code.' ) );
		$this->logger->on_wp_login_failed( $this->user->user_email, new WP_Error( 'wfls_twofactor_failed', 'Code invalid.' ) );

		$this->assertSame( [ 'user_two_factor_login_failed', 'user_two_factor_login_failed' ], $this->captured_message_keys() );
		$this->assertSame( 'better-wp-security', $this->captured_logs[0][1][ User_Logger::CONTEXT_TWO_FACTOR_PLUGIN ] );
		$this->assertSame( 'wordfence', $this->captured_logs[1][1][ User_Logger::CONTEXT_TWO_FACTOR_PLUGIN ] );
		$this->assertSame( $this->user->ID, $this->captured_logs[1][1]['login_id'] );
	}

	/**
	 * Core fires `wp_login_failed` for every wrong password as well. Those are
	 * already logged by the `authenticate` handlers and must not be logged twice.
	 */
	public function test_core_wp_login_failed_is_ignored() {
		$this->logger->on_wp_login_failed( $this->user->user_login, new WP_Error( 'incorrect_password', 'Wrong password.' ) );
		$this->logger->on_wp_login_failed( 'nosuchuser', new WP_Error( 'invalid_username', 'Unknown user.' ) );
		$this->logger->on_wp_login_failed( $this->user->user_login );

		// Wordfence asks for the code after a correct password. Not a failure.
		$this->logger->on_wp_login_failed( $this->user->user_login, new WP_Error( 'wfls_twofactor_required', 'Code required.' ) );

		$this->assertSame( [], $this->captured_message_keys() );
	}

	/**
	 * Two Factor fires `wp_login_failed` with the same error codes when a
	 * logged-in user fails to confirm their code again, for example before
	 * changing security settings. That is not a failed login.
	 */
	public function test_failed_code_check_while_logged_in_is_not_logged() {
		wp_set_current_user( $this->user->ID );

		$this->logger->on_wp_login_failed( $this->user->user_login, new WP_Error( 'two_factor_invalid', 'Invalid verification code.' ) );

		wp_set_current_user( 0 );

		$this->assertSame( [], $this->captured_message_keys() );
	}

	/**
	 * The log shows that two-factor was used, with the method escaped.
	 */
	public function test_two_factor_login_text_and_details() {
		$row = (object) [
			'logger'  => 'SimpleUserLogger',
			'level'   => 'info',
			'message' => 'Logged in',
			'context' => [
				'_message_key'                         => 'user_logged_in',
				User_Logger::CONTEXT_TWO_FACTOR_USED   => '1',
				User_Logger::CONTEXT_TWO_FACTOR_METHOD => 'App <b>x</b>',
			],
		];

		$this->assertSame(
			'Logged in using two-factor authentication (App &lt;b&gt;x&lt;/b&gt;)',
			$this->logger->get_log_row_plain_text_output( $row )
		);

		$row->context[ User_Logger::CONTEXT_TWO_FACTOR_USED ] = '0';
		unset( $row->context[ User_Logger::CONTEXT_TWO_FACTOR_METHOD ] );

		$this->assertSame( 'Logged in', $this->logger->get_log_row_plain_text_output( $row ) );
	}
}
