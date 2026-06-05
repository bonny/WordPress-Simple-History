<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;

/**
 * Test failed application-password authentication logging.
 *
 * Two code paths fire `application_password_failed_authentication`:
 *
 * 1. **XML-RPC**: `class-wp-xmlrpc-server.php` calls `wp_authenticate()` which runs the
 *    `authenticate` filter chain. The standard failed-login loggers (`onAuthenticate` /
 *    `onWpAuthenticateUser`) run on that chain and already record the attempt — so the
 *    app-password handler must stay SILENT here, otherwise every XML-RPC attempt produces
 *    a duplicate row. Core fires this action even for plain logins that never used an
 *    application password, so the "application password" label can't be trusted over
 *    XML-RPC anyway. The username travels in the XML body, so `$_SERVER['PHP_AUTH_USER']`
 *    is empty on this path.
 *
 * 2. **REST API**: WP's `wp_validate_application_password` is hooked on
 *    `determine_current_user` and calls `wp_authenticate_application_password()` *directly*
 *    — bypassing the `authenticate` filter chain. No other logger watches this path, so the
 *    app-password handler logs it. Core gates that call on
 *    `isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])`, so PHP_AUTH_USER is
 *    guaranteed present whenever the handler fires on REST.
 *
 * `$_SERVER['PHP_AUTH_USER']` is therefore the path marker: present => REST => log;
 * absent => XML-RPC (or no creds) => bail and let the regular loggers cover it.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit UserLoggerAppPasswordTest
 */
class UserLoggerAppPasswordTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var User_Logger */
	private $logger;

	/**
	 * Captured data from the most recent `simple_history/log_insert_data_and_context`
	 * filter invocation. Lets the tests assert on what the logger tried to write
	 * without relying on the events table — which is unreliable under the wpunit
	 * transactional setup.
	 *
	 * @var array{0:array,1:array}|null
	 */
	private $captured_log = null;

	public function setUp(): void {
		parent::setUp();

		// The action handler is only registered when this filter returns true.
		add_filter( 'simple_history/log_failed_app_password_auth', '__return_true' );

		$this->sh = Simple_History::get_instance();
		$logger = $this->sh->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		if ( ! $logger instanceof User_Logger ) {
			$logger = new User_Logger( $this->sh );
			$logger->loaded();
		}

		$this->logger = $logger;

		// Reset the per-request dedupe guard so each test starts clean.
		$reflection = new ReflectionClass( $this->logger );

		$logged = $reflection->getProperty( 'app_password_failure_logged' );
		$logged->setAccessible( true );
		$logged->setValue( $this->logger, false );

		// Clear any PHP_AUTH_USER from the test environment — leftover values
		// would mask bugs (or accidentally pass tests that should fail).
		unset( $_SERVER['PHP_AUTH_USER'] );

		// Capture what the logger tries to write via the late-stage filter.
		$this->captured_log = null;
		add_filter(
			'simple_history/log_insert_data_and_context',
			[ $this, 'capture_log_write' ],
			10,
			2
		);
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_log_write' ], 10 );
		remove_filter( 'simple_history/log_failed_app_password_auth', '__return_true' );
		unset( $_SERVER['PHP_AUTH_USER'] );
		parent::tearDown();
	}

	/**
	 * Filter callback that snapshots the most recent SimpleUserLogger log write.
	 *
	 * @param array{0:array,1:array} $data_and_context [$data, $context] tuple.
	 * @param mixed                  $instance         Logger instance writing the row.
	 * @return array{0:array,1:array} Unchanged — we only observe.
	 */
	public function capture_log_write( $data_and_context, $instance ) {
		if ( $instance instanceof User_Logger ) {
			$this->captured_log = $data_and_context;
		}

		return $data_and_context;
	}

	/**
	 * XML-RPC, unknown-user branch (`invalid_username`). PHP_AUTH_USER is absent
	 * (the username is in the XML body), so the handler must stay silent — the
	 * regular failed-login loggers already record this attempt.
	 */
	public function test_xmlrpc_failure_is_not_logged_to_avoid_duplicate() {
		// XML-RPC: PHP_AUTH_USER stays unset (cleared in setUp).
		$this->assertArrayNotHasKey( 'PHP_AUTH_USER', $_SERVER );

		$error = new WP_Error( 'invalid_username', 'Unknown username.' );
		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNull(
			$this->captured_log,
			'XML-RPC app-password failures must not be logged here — the regular failed-login loggers already cover them.'
		);
	}

	/**
	 * XML-RPC, existing-user / wrong-password branch (`incorrect_password`). Same
	 * duplicate-suppression rule: `onWpAuthenticateUser` already logs `user_login_failed`
	 * for this attempt, and PHP_AUTH_USER is absent on the XML-RPC path.
	 */
	public function test_xmlrpc_incorrect_password_is_not_logged() {
		// XML-RPC: PHP_AUTH_USER stays unset (cleared in setUp).
		$this->assertArrayNotHasKey( 'PHP_AUTH_USER', $_SERVER );

		$error = new WP_Error( 'incorrect_password', 'The provided password is an invalid application password.' );
		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNull(
			$this->captured_log,
			'XML-RPC app-password failures must not be logged here — onWpAuthenticateUser already covers them.'
		);
	}

	/**
	 * REST path, existing-user / wrong-password branch. PHP_AUTH_USER is set by the web
	 * server, no other logger watches this path, so the handler logs the attempt and
	 * reads the username from PHP_AUTH_USER.
	 */
	public function test_rest_path_logs_with_php_auth_user() {
		$_SERVER['PHP_AUTH_USER'] = 'rest_target_user';

		$error = new WP_Error( 'incorrect_password', 'The provided password is an invalid application password.' );
		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNotNull( $this->captured_log, 'Expected the logger to write a row.' );

		[ $data, $context ] = $this->captured_log;

		$this->assertSame( 'SimpleUserLogger', $data['logger'] );
		$this->assertSame( 'warning', $data['level'] );
		$this->assertSame( 'user_application_password_login_failed', $context['_message_key'] );
		$this->assertSame( 'rest_target_user', $context['login'] );
		$this->assertSame( 'incorrect_password', $context['error_code'] );
	}

	/**
	 * REST path, unknown-user branch (`invalid_username`): the attempted username is
	 * logged under `failed_username` (not `login`), sourced from PHP_AUTH_USER.
	 */
	public function test_unknown_user_branch_rest() {
		$_SERVER['PHP_AUTH_USER'] = 'no_such_rest_user';

		$error = new WP_Error( 'invalid_username', 'Unknown username.' );
		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNotNull( $this->captured_log );

		[ , $context ] = $this->captured_log;

		$this->assertSame( 'user_application_password_unknown_login_failed', $context['_message_key'] );
		$this->assertSame( 'no_such_rest_user', $context['failed_username'] );
	}

	/**
	 * Dedupe guard. WP fires the action twice per request (once via `authenticate`,
	 * once via `determine_current_user`); we only want one log row per request. Tested
	 * on the REST path, the path that actually logs.
	 */
	public function test_recursion_guard_dedupes_second_call() {
		$_SERVER['PHP_AUTH_USER'] = 'dedupe_user';

		$error = new WP_Error( 'incorrect_password', 'The provided password is an invalid application password.' );
		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNotNull( $this->captured_log, 'Expected the first call to write a row.' );

		// Reset capture so a second write would be visible.
		$this->captured_log = null;

		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNull( $this->captured_log, 'Second invocation must be guarded as a no-op.' );
	}

	/**
	 * Realistic combined path: an attacker brute-forces both XML-RPC and REST.
	 * The XML-RPC request (no PHP_AUTH_USER) must stay silent — the regular loggers
	 * cover it. The REST request (PHP_AUTH_USER set) must log, since REST bypasses the
	 * `authenticate` chain and has no other logger watching it.
	 */
	public function test_xmlrpc_bails_then_rest_logs() {
		// First request: XML-RPC — must be suppressed (no PHP_AUTH_USER).
		$error = new WP_Error( 'incorrect_password', 'The provided password is an invalid application password.' );
		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNull( $this->captured_log, 'XML-RPC attempt must not be logged here.' );

		// Simulate end-of-request: reset the per-request dedupe guard as setUp does.
		$reflection = new ReflectionClass( $this->logger );
		$logged = $reflection->getProperty( 'app_password_failure_logged' );
		$logged->setAccessible( true );
		$logged->setValue( $this->logger, false );
		$this->captured_log = null;

		// Second request: REST API — must log.
		$_SERVER['PHP_AUTH_USER'] = 'attacker_target_rest';
		$this->logger->on_application_password_failed_authentication( $error );

		$this->assertNotNull( $this->captured_log, 'REST attempt must be logged.' );

		[ , $second_context ] = $this->captured_log;
		$this->assertSame( 'attacker_target_rest', $second_context['login'] );
	}
}
