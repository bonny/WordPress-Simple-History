<?php

use Simple_History\Simple_History;
use Simple_History\Services\Email_Report_Service;

/**
 * Issue 341: the weekly email could be enabled with an empty Recipients field,
 * which made send_email_report() bail out silently and nothing was ever sent.
 *
 * get_effective_recipients() now falls back to admin_email when the stored
 * list has no valid address, both for the real weekly send and for the
 * "send test email" button, and the settings page warns when the email is
 * saved on with nothing in the Recipients field.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit:EmailReportEffectiveRecipientsTest
 */
class EmailReportEffectiveRecipientsTest extends \Codeception\TestCase\WPTestCase {
	/** @var callable */
	private $from_filter;

	public function setUp(): void {
		parent::setUp();

		reset_phpmailer_instance();

		// The test site's default sender is wordpress@localhost, which
		// PHPMailer rejects as an invalid address, so nothing would be sent.
		$this->from_filter = function () {
			return 'wordpress@example.com';
		};

		add_filter( 'wp_mail_from', $this->from_filter );

		delete_option( 'simple_history_email_report_enabled' );
		delete_option( 'simple_history_email_report_recipients' );

		global $wp_settings_errors;
		$wp_settings_errors = [];
	}

	public function tearDown(): void {
		remove_filter( 'wp_mail_from', $this->from_filter );

		reset_phpmailer_instance();

		delete_option( 'simple_history_email_report_enabled' );
		delete_option( 'simple_history_email_report_recipients' );

		unset( $_POST['simple_history_email_report_enabled'] );

		global $wp_settings_errors;
		$wp_settings_errors = [];

		parent::tearDown();
	}

	/**
	 * Get the Email_Report_Service instance under test.
	 *
	 * @return Email_Report_Service
	 */
	private function get_service() {
		$service = Simple_History::get_instance()->get_service( Email_Report_Service::class );

		$this->assertInstanceOf( Email_Report_Service::class, $service );

		return $service;
	}

	/**
	 * Call the private get_effective_recipients() method.
	 *
	 * @return string[]
	 */
	private function get_effective_recipients() {
		$method = new ReflectionMethod( Email_Report_Service::class, 'get_effective_recipients' );
		$method->setAccessible( true );

		return $method->invoke( $this->get_service() );
	}

	public function test_effective_recipients_uses_the_stored_list_when_valid() {
		update_option( 'simple_history_email_report_recipients', "one@example.com\ntwo@example.com" );

		$this->assertSame( [ 'one@example.com', 'two@example.com' ], $this->get_effective_recipients() );
	}

	public function test_effective_recipients_filters_out_invalid_entries() {
		update_option( 'simple_history_email_report_recipients', "not-an-email\nvalid@example.com" );

		$this->assertSame( [ 'valid@example.com' ], $this->get_effective_recipients() );
	}

	public function test_effective_recipients_falls_back_to_admin_email_when_stored_list_is_empty() {
		update_option( 'simple_history_email_report_recipients', '' );

		$this->assertSame( [ get_option( 'admin_email' ) ], $this->get_effective_recipients() );
	}

	public function test_effective_recipients_falls_back_to_admin_email_when_stored_list_has_no_valid_entries() {
		update_option( 'simple_history_email_report_recipients', "not-an-email\nalso not one" );

		$this->assertSame( [ get_option( 'admin_email' ) ], $this->get_effective_recipients() );
	}

	public function test_effective_recipients_is_empty_when_neither_recipients_nor_admin_email_are_valid() {
		update_option( 'simple_history_email_report_recipients', '' );

		$force_invalid_admin_email = function () {
			return 'not-an-email';
		};

		add_filter( 'pre_option_admin_email', $force_invalid_admin_email );

		$recipients = $this->get_effective_recipients();

		remove_filter( 'pre_option_admin_email', $force_invalid_admin_email );

		$this->assertSame( [], $recipients );
	}

	public function test_send_email_report_goes_to_admin_email_when_recipients_are_empty() {
		update_option( 'simple_history_email_report_enabled', '1' );
		update_option( 'simple_history_email_report_recipients', '' );

		$this->get_service()->send_email_report();

		$sent = tests_retrieve_phpmailer_instance()->get_sent();

		$this->assertNotFalse( $sent, 'The weekly report should have been sent to the site admin.' );
		$this->assertSame( get_option( 'admin_email' ), $sent->to[0][0] );
	}

	public function test_send_email_report_still_bails_when_no_valid_recipient_exists() {
		update_option( 'simple_history_email_report_enabled', '1' );
		update_option( 'simple_history_email_report_recipients', '' );

		$force_invalid_admin_email = function () {
			return 'not-an-email';
		};

		add_filter( 'pre_option_admin_email', $force_invalid_admin_email );

		$this->get_service()->send_email_report();

		remove_filter( 'pre_option_admin_email', $force_invalid_admin_email );

		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent(), 'Nothing should have been sent with no valid recipient.' );
	}

	public function test_sanitize_recipients_warns_when_saved_empty_while_enabled() {
		$_POST['simple_history_email_report_enabled'] = '1';

		$result = $this->get_service()->sanitize_email_recipients( '' );

		$this->assertSame( '', $result );

		$errors = get_settings_errors( 'simple_history_email_report_recipients' );

		$this->assertCount( 1, $errors );
		$this->assertSame( 'warning', $errors[0]['type'] );
		$this->assertStringContainsString( get_option( 'admin_email' ), $errors[0]['message'] );
	}

	public function test_sanitize_recipients_does_not_warn_twice_for_one_request() {
		$_POST['simple_history_email_report_enabled'] = '1';

		$service = $this->get_service();
		$service->sanitize_email_recipients( '' );
		$service->sanitize_email_recipients( '' );

		$this->assertCount( 1, get_settings_errors( 'simple_history_email_report_recipients' ) );
	}

	public function test_sanitize_recipients_does_not_warn_when_not_being_enabled() {
		unset( $_POST['simple_history_email_report_enabled'] );

		$this->get_service()->sanitize_email_recipients( '' );

		$this->assertCount( 0, get_settings_errors( 'simple_history_email_report_recipients' ) );
	}

	public function test_sanitize_recipients_does_not_warn_when_recipients_are_present() {
		$_POST['simple_history_email_report_enabled'] = '1';

		$this->get_service()->sanitize_email_recipients( 'someone@example.com' );

		$this->assertCount( 0, get_settings_errors( 'simple_history_email_report_recipients' ) );
	}

	public function test_admin_already_counts_as_recipient_through_the_fallback() {
		update_option( 'simple_history_email_report_enabled', '1' );

		$this->assertTrue( Email_Report_Service::is_email_in_active_recipients( get_option( 'admin_email' ) ) );
		$this->assertFalse( Email_Report_Service::is_email_in_active_recipients( 'someone-else@example.com' ) );
	}

	public function test_admin_is_not_a_recipient_once_other_recipients_are_set() {
		update_option( 'simple_history_email_report_enabled', '1' );
		update_option( 'simple_history_email_report_recipients', 'someone@example.com' );

		$this->assertFalse( Email_Report_Service::is_email_in_active_recipients( get_option( 'admin_email' ) ) );
	}

	public function test_test_email_names_recipients_it_could_not_send_to() {
		update_option( 'simple_history_email_report_recipients', "ok@example.com\nbounce@example.com" );

		// Fail only the send to bounce@example.com.
		$fail_one = function ( $return, $atts ) {
			return in_array( 'bounce@example.com', (array) $atts['to'], true ) ? false : $return;
		};

		add_filter( 'pre_wp_mail', $fail_one, 10, 2 );
		$response = $this->get_service()->rest_preview_email();
		remove_filter( 'pre_wp_mail', $fail_one, 10 );

		$this->assertNotWPError( $response );

		$message = $response->get_data()['message'];

		$this->assertStringContainsString( 'Test email sent to ok@example.com.', $message );
		$this->assertStringContainsString( 'Could not send to bounce@example.com.', $message );
	}
}
