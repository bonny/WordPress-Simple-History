<?php

use Simple_History\Simple_History;
use Simple_History\Services\Email_Report_Service;

/**
 * Issue 341: the weekly email could be enabled with an empty Recipients field,
 * which made send_email_report() bail out silently and nothing was ever sent.
 *
 * Issue 351 replaced that hidden fallback with a "Site admin" checkbox. On a
 * site that never saved it, the admin is included when the stored list is
 * empty, so behaviour is unchanged until someone saves the form. The settings
 * form refuses to turn the email on with nobody to send to.
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
		delete_option( Email_Report_Service::OPTION_INCLUDE_ADMIN );

		global $wp_settings_errors;
		$wp_settings_errors = [];
	}

	public function tearDown(): void {
		remove_filter( 'wp_mail_from', $this->from_filter );

		reset_phpmailer_instance();

		delete_option( 'simple_history_email_report_enabled' );
		delete_option( 'simple_history_email_report_recipients' );
		delete_option( Email_Report_Service::OPTION_INCLUDE_ADMIN );

		unset(
			$_POST['option_page'],
			$_POST['simple_history_email_report_enabled'],
			$_POST[ Email_Report_Service::OPTION_INCLUDE_ADMIN ],
			$_POST['simple_history_email_report_recipients']
		);

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

	/**
	 * Fake a submit of the email report settings form.
	 *
	 * @param bool   $include_admin Whether "Site admin" is ticked.
	 * @param string $recipients    The "Also send to" textarea.
	 */
	private function post_settings_form( $include_admin, $recipients ) {
		$_POST['option_page']                            = 'simple_history_settings_group_email_reports';
		$_POST['simple_history_email_report_enabled']    = '1';
		$_POST['simple_history_email_report_recipients'] = $recipients;

		if ( $include_admin ) {
			$_POST[ Email_Report_Service::OPTION_INCLUDE_ADMIN ] = '1';
		} else {
			unset( $_POST[ Email_Report_Service::OPTION_INCLUDE_ADMIN ] );
		}
	}

	public function test_form_refuses_to_enable_with_no_recipients() {
		$this->post_settings_form( false, '' );

		$this->assertFalse( $this->get_service()->sanitize_enabled( '1' ) );

		$errors = get_settings_errors( 'simple_history_email_report_enabled' );

		$this->assertCount( 1, $errors );
		$this->assertSame( 'error', $errors[0]['type'] );
	}

	public function test_form_adds_the_no_recipients_error_once_per_request() {
		$this->post_settings_form( false, 'not-an-email' );

		$service = $this->get_service();
		$service->sanitize_enabled( '1' );
		$service->sanitize_enabled( '1' );

		$this->assertCount( 1, get_settings_errors( 'simple_history_email_report_enabled' ) );
	}

	public function test_form_enables_with_only_site_admin_ticked() {
		$this->post_settings_form( true, '' );

		$this->assertTrue( $this->get_service()->sanitize_enabled( '1' ) );
		$this->assertCount( 0, get_settings_errors( 'simple_history_email_report_enabled' ) );
	}

	public function test_form_enables_with_only_typed_recipients() {
		$this->post_settings_form( false, 'someone@example.com' );

		$this->assertTrue( $this->get_service()->sanitize_enabled( '1' ) );
	}

	public function test_enabling_outside_the_settings_form_is_not_checked() {
		// The one-click opt-in sets its own recipient, and other code may
		// call update_option() directly. Only the form is validated.
		$this->assertTrue( $this->get_service()->sanitize_enabled( '1' ) );
	}

	public function test_site_admin_is_included_on_sites_that_never_saved_the_checkbox_and_have_no_list() {
		$this->assertTrue( Email_Report_Service::is_site_admin_included() );
	}

	public function test_site_admin_is_not_included_on_sites_that_never_saved_the_checkbox_but_list_recipients() {
		update_option( 'simple_history_email_report_recipients', 'someone@example.com' );

		$this->assertFalse( Email_Report_Service::is_site_admin_included() );
		$this->assertSame( [ 'someone@example.com' ], $this->get_effective_recipients() );
	}

	public function test_ticked_site_admin_is_sent_to_together_with_the_list() {
		update_option( Email_Report_Service::OPTION_INCLUDE_ADMIN, 'yes' );
		update_option( 'simple_history_email_report_recipients', 'someone@example.com' );

		$this->assertSame( [ get_option( 'admin_email' ), 'someone@example.com' ], $this->get_effective_recipients() );
	}

	public function test_unticked_site_admin_with_empty_list_has_no_recipients() {
		update_option( Email_Report_Service::OPTION_INCLUDE_ADMIN, $this->get_service()->sanitize_include_admin( null ) );

		$this->assertFalse( Email_Report_Service::is_site_admin_included() );
		$this->assertSame( [], $this->get_effective_recipients() );
	}

	public function test_site_admin_follows_admin_email_changes() {
		update_option( Email_Report_Service::OPTION_INCLUDE_ADMIN, 'yes' );

		$new_admin_email = function () {
			return 'new-admin@example.com';
		};

		add_filter( 'pre_option_admin_email', $new_admin_email );
		$recipients = $this->get_effective_recipients();
		remove_filter( 'pre_option_admin_email', $new_admin_email );

		$this->assertSame( [ 'new-admin@example.com' ], $recipients );
	}

	public function test_admin_address_ticked_and_typed_is_sent_to_once() {
		update_option( Email_Report_Service::OPTION_INCLUDE_ADMIN, 'yes' );
		update_option( 'simple_history_email_report_recipients', strtoupper( get_option( 'admin_email' ) ) . "\nsomeone@example.com" );

		$this->assertSame( [ get_option( 'admin_email' ), 'someone@example.com' ], $this->get_effective_recipients() );
	}

	public function test_unticked_checkbox_is_saved_on_a_site_that_never_saved_it() {
		// The form sends nothing for an unticked box, and the Settings API saves null.
		update_option( Email_Report_Service::OPTION_INCLUDE_ADMIN, $this->get_service()->sanitize_include_admin( null ) );

		$this->assertSame( 'no', get_option( Email_Report_Service::OPTION_INCLUDE_ADMIN ) );
		$this->assertFalse( Email_Report_Service::is_site_admin_included() );
	}

	public function test_typed_admin_address_is_not_converted_to_the_checkbox() {
		update_option( 'simple_history_email_report_recipients', get_option( 'admin_email' ) );

		// No magic: the typed address stays a typed address.
		$this->assertFalse( Email_Report_Service::is_site_admin_included() );
		$this->assertSame( get_option( 'admin_email' ), get_option( 'simple_history_email_report_recipients' ) );
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
