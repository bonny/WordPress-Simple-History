<?php

use Simple_History\Simple_History;
use Simple_History\Services\Email_Report_Service;

/**
 * Issue 323: the weekly email is offered as a one-click opt-in in the welcome
 * notice and the welcome log event, instead of only in the settings.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit:EmailReportOptInTest
 */
class EmailReportOptInTest extends \Codeception\TestCase\WPTestCase {
	/** @var callable */
	private $redirect_filter;

	public function setUp(): void {
		parent::setUp();

		delete_option( 'simple_history_email_report_enabled' );
		delete_option( 'simple_history_email_report_recipients' );
		delete_option( Email_Report_Service::OPTION_INCLUDE_ADMIN );
		wp_clear_scheduled_hook( 'simple_history/email_report' );

		// Stop at the redirect instead of calling exit.
		$this->redirect_filter = function ( $location ) {
			throw new \RuntimeException( 'redirect:' . $location );
		};

		add_filter( 'wp_redirect', $this->redirect_filter );
	}

	public function tearDown(): void {
		remove_filter( 'wp_redirect', $this->redirect_filter );

		unset( $_REQUEST['_wpnonce'], $_GET['_wpnonce'] );

		delete_option( 'simple_history_email_report_enabled' );
		delete_option( 'simple_history_email_report_recipients' );
		delete_option( Email_Report_Service::OPTION_INCLUDE_ADMIN );
		wp_clear_scheduled_hook( 'simple_history/email_report' );

		parent::tearDown();
	}

	/**
	 * Log in as a new user with the given role and email.
	 *
	 * @param string $role Role.
	 * @param string $email Email address.
	 */
	private function login_as( $role, $email = 'admin-optin@example.com' ) {
		$user_id = self::factory()->user->create(
			[
				'role'       => $role,
				'user_email' => $email,
			]
		);

		wp_set_current_user( $user_id );
	}

	/**
	 * Run the opt-in handler with a valid nonce and return the redirect URL.
	 *
	 * @return string
	 */
	private function run_opt_in_handler() {
		$_REQUEST['_wpnonce'] = wp_create_nonce( Email_Report_Service::OPT_IN_ACTION );

		$service = Simple_History::get_instance()->get_service( Email_Report_Service::class );

		$this->assertInstanceOf( Email_Report_Service::class, $service );

		try {
			$service->handle_opt_in();
		} catch ( \RuntimeException $e ) {
			return substr( $e->getMessage(), strlen( 'redirect:' ) );
		}

		$this->fail( 'The opt-in handler did not redirect.' );
	}

	public function test_button_is_offered_to_admins_when_reports_are_off() {
		$this->login_as( 'administrator' );

		$html = Email_Report_Service::get_opt_in_html();

		$this->assertStringContainsString( 'Turn on weekly email', $html );
		$this->assertStringContainsString( 'admin-optin@example.com', $html );
		$this->assertStringContainsString( 'action=' . Email_Report_Service::OPT_IN_ACTION, $html );
		$this->assertStringContainsString( '_wpnonce=', $html );
		$this->assertStringContainsString( 'class="button"', $html );
	}

	public function test_button_is_not_offered_to_users_who_can_not_manage_options() {
		$this->login_as( 'editor' );

		$this->assertSame( '', Email_Report_Service::get_opt_in_html() );
	}

	public function test_button_is_not_offered_when_the_user_already_gets_the_report() {
		$this->login_as( 'administrator' );

		update_option( 'simple_history_email_report_enabled', '1' );
		update_option( 'simple_history_email_report_recipients', "someone@example.com\nAdmin-OptIn@Example.com" );

		$this->assertSame( '', Email_Report_Service::get_opt_in_html() );
	}

	public function test_button_is_offered_when_reports_are_on_for_other_recipients() {
		$this->login_as( 'administrator' );

		update_option( 'simple_history_email_report_enabled', '1' );
		update_option( 'simple_history_email_report_recipients', 'someone@example.com' );

		$this->assertStringContainsString( 'Add me to the weekly email', Email_Report_Service::get_opt_in_html() );
	}

	public function test_opting_in_enables_report_keeps_recipients_and_schedules_it() {
		$this->login_as( 'administrator' );

		update_option( 'simple_history_email_report_recipients', 'someone@example.com' );

		$redirect_url = $this->run_opt_in_handler();

		$this->assertTrue( (bool) get_option( 'simple_history_email_report_enabled' ) );
		$this->assertSame( "someone@example.com\nadmin-optin@example.com", get_option( 'simple_history_email_report_recipients' ) );
		$this->assertNotFalse( wp_next_scheduled( 'simple_history/email_report' ) );
		$this->assertStringContainsString( 'simple-history-email-report-opt-in=enabled', $redirect_url );
	}

	public function test_opting_in_twice_does_not_duplicate_the_recipient() {
		$this->login_as( 'administrator' );

		$this->run_opt_in_handler();
		$this->run_opt_in_handler();

		$this->assertSame( 'admin-optin@example.com', get_option( 'simple_history_email_report_recipients' ) );
	}

	public function test_opting_in_is_refused_for_users_who_can_not_manage_options() {
		$this->login_as( 'editor', 'editor-optin@example.com' );

		$this->expectException( \WPDieException::class );

		$this->run_opt_in_handler();
	}

	public function test_site_admin_opting_in_ticks_the_checkbox_instead_of_typing_the_address() {
		$this->login_as( 'administrator' );

		// Make the logged-in user the site admin.
		$admin_email = function () {
			return 'Admin-OptIn@example.com';
		};

		add_filter( 'pre_option_admin_email', $admin_email );

		update_option( 'simple_history_email_report_recipients', 'someone@example.com' );

		$this->run_opt_in_handler();

		$included = Email_Report_Service::is_site_admin_included();

		remove_filter( 'pre_option_admin_email', $admin_email );

		$this->assertTrue( $included );
		$this->assertSame( 'someone@example.com', get_option( 'simple_history_email_report_recipients' ) );
	}

	public function test_opting_in_on_a_site_that_relied_on_the_admin_keeps_the_admin() {
		$this->login_as( 'administrator' );

		// Never saved the checkbox and no list: the admin gets the report.
		$this->assertTrue( Email_Report_Service::is_site_admin_included() );

		$this->run_opt_in_handler();

		$this->assertTrue( Email_Report_Service::is_site_admin_included(), 'Adding someone must not drop the site admin.' );
		$this->assertTrue( Email_Report_Service::is_email_in_active_recipients( get_option( 'admin_email' ) ) );
		$this->assertTrue( Email_Report_Service::is_email_in_active_recipients( 'admin-optin@example.com' ) );
	}
}
