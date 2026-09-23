<?php

use Simple_History\Simple_History;
use Simple_History\Services\Email_Report_Service;

/**
 * Issue 322: the weekly summary went out HTML-only, which SpamAssassin
 * penalises and which some gateways turn into a blank message. It now carries
 * a text/plain part built from templates/email-summary-report-text.php and
 * attached through phpmailer_init.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit:EmailReportTextPartTest
 */
class EmailReportTextPartTest extends \Codeception\TestCase\WPTestCase {
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

		update_option( 'simple_history_email_report_enabled', '1' );
		update_option( 'simple_history_email_report_recipients', 'recipient@example.com' );
	}

	public function tearDown(): void {
		remove_filter( 'wp_mail_from', $this->from_filter );

		reset_phpmailer_instance();

		delete_option( 'simple_history_email_report_enabled' );
		delete_option( 'simple_history_email_report_recipients' );

		parent::tearDown();
	}

	/**
	 * Send the weekly report and return what the mailer was asked to send.
	 *
	 * @return object
	 */
	private function send_report() {
		$service = Simple_History::get_instance()->get_service( Email_Report_Service::class );

		$this->assertInstanceOf( Email_Report_Service::class, $service );

		$service->send_email_report();

		return tests_retrieve_phpmailer_instance()->get_sent();
	}

	public function test_report_is_sent_as_multipart_with_a_text_part() {
		$sent = $this->send_report();

		$this->assertNotFalse( $sent, 'The weekly report should have been sent.' );
		$this->assertStringContainsString( 'multipart/alternative', $sent->header );
		$this->assertStringContainsString( 'Content-Type: text/plain', $sent->body );
		$this->assertStringContainsString( 'Content-Type: text/html', $sent->body );
	}

	public function test_text_part_holds_the_report_and_no_markup() {
		$this->send_report();

		$text = tests_retrieve_phpmailer_instance()->AltBody;

		$this->assertStringContainsString( 'Website activity summary', $text );
		$this->assertStringContainsString( 'Total events', $text );
		$this->assertStringContainsString( 'Event count by day', $text );

		// A stray tag would mean the HTML leaked in, which is the thing this
		// part exists to avoid.
		$this->assertStringNotContainsString( '<', $text );
		$this->assertStringNotContainsString( '&amp;', $text );
	}

	public function test_the_text_part_does_not_leak_into_later_emails() {
		$this->send_report();

		reset_phpmailer_instance();

		wp_mail( 'someone@example.com', 'Unrelated', 'Body of an unrelated email.' );

		$mailer = tests_retrieve_phpmailer_instance();

		$this->assertSame( '', $mailer->AltBody, 'phpmailer_init callback was left attached after the report was sent.' );
		$this->assertStringNotContainsString( 'Website activity summary', $mailer->get_sent()->body );
	}

	public function test_the_hook_is_removed_even_when_sending_is_short_circuited() {
		$short_circuit = function () {
			return true;
		};

		add_filter( 'pre_wp_mail', $short_circuit );

		$service = Simple_History::get_instance()->get_service( Email_Report_Service::class );
		$service->send_email_report();

		remove_filter( 'pre_wp_mail', $short_circuit );

		$this->assertFalse( has_action( 'phpmailer_init' ), 'phpmailer_init callback outlived a short-circuited send.' );
	}
}
