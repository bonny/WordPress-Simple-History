<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;

/**
 * Failed logins through the login form and through an application password on
 * the REST API are the same kind of event. They should collapse into one
 * occasions group in the log, and one search filter should find both.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit UserLoggerFailedLoginOccasionsTest
 */
class UserLoggerFailedLoginOccasionsTest extends \Codeception\TestCase\WPTestCase {
	/** @var User_Logger */
	private $logger;

	/** @var array<array{0:array,1:array}> Every SimpleUserLogger write this test saw. */
	private $writes = [];

	public function setUp(): void {
		parent::setUp();

		add_filter( 'simple_history/log_failed_app_password_auth', '__return_true' );

		$sh     = Simple_History::get_instance();
		$logger = $sh->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		if ( ! $logger instanceof User_Logger ) {
			$logger = new User_Logger( $sh );
			$logger->loaded();
		}

		$this->logger = $logger;

		$guard = ( new ReflectionClass( $this->logger ) )->getProperty( 'app_password_failure_logged' );
		$guard->setAccessible( true );
		$guard->setValue( $this->logger, false );

		unset( $_SERVER['PHP_AUTH_USER'] );

		$this->writes = [];
		add_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10 );
		remove_filter( 'simple_history/log_failed_app_password_auth', '__return_true' );
		unset( $_SERVER['PHP_AUTH_USER'] );
		parent::tearDown();
	}

	public function capture_write( $data_and_context, $instance ) {
		if ( $instance instanceof User_Logger ) {
			$this->writes[] = $data_and_context;
		}

		return $data_and_context;
	}

	public function test_app_password_failure_shares_occasions_id_with_form_failure() {
		// Login form, unknown user.
		$this->logger->onAuthenticate( new WP_Error( 'invalid_username', 'Unknown username.' ), 'no_such_user', 'x' );

		// REST API, wrong application password.
		$_SERVER['PHP_AUTH_USER'] = 'no_such_user';
		$this->logger->on_application_password_failed_authentication( new WP_Error( 'incorrect_password', 'Invalid application password.' ) );

		$this->assertCount( 2, $this->writes );

		[ $form_data, $form_context ]       = $this->writes[0];
		[ $rest_data, $rest_context ]       = $this->writes[1];

		$this->assertSame( 'user_unknown_login_failed', $form_context['_message_key'] );
		$this->assertSame( 'user_application_password_login_failed', $rest_context['_message_key'] );
		$this->assertSame(
			$form_data['occasionsID'],
			$rest_data['occasionsID'],
			'A burst mixing form and application password failures should collapse into one group'
		);
	}

	public function test_failed_user_logins_filter_covers_app_password_failures() {
		$options = $this->logger->get_info()['labels']['search']['options'];
		$keys    = $options['Failed user logins'];

		foreach ( User_Logger::get_failed_login_message_keys() as $key ) {
			$this->assertContains( $key, $keys, "\"Failed user logins\" should match $key" );
		}
	}
}
