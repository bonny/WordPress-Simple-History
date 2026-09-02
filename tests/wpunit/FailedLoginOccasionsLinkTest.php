<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;

/**
 * The "Configure failed login attempts" / "Limit logged login attempts" link
 * under a group of failed logins must appear for every failed login kind,
 * including application password failures on the REST API.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit FailedLoginOccasionsLinkTest
 */
class FailedLoginOccasionsLinkTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @dataProvider failed_login_message_keys
	 */
	public function test_failed_login_group_gets_the_settings_link( string $message_key ) {
		$html = $this->render( $message_key );

		$this->assertStringContainsString( 'SimpleHistoryLogitem__occasionsAddOns', $html, "$message_key should get the failed login settings link" );
	}

	public function test_other_user_events_do_not_get_the_link() {
		$html = $this->render( 'user_logged_in' );

		$this->assertStringNotContainsString( 'SimpleHistoryLogitem__occasionsAddOns', $html );
	}

	public function failed_login_message_keys(): array {
		return array_map(
			static fn( $key ) => [ $key ],
			User_Logger::get_failed_login_message_keys()
		);
	}

	private function render( string $message_key ): string {
		$row                       = new stdClass();
		$row->id                   = 1;
		$row->logger               = 'SimpleUserLogger';
		$row->level                = 'warning';
		$row->date                 = '2026-09-02 12:00:00';
		$row->message              = 'x';
		$row->initiator            = 'web_user';
		$row->occasionsID          = 'abc';
		$row->subsequentOccasions  = 6;
		$row->context              = [
			'_message_key'    => $message_key,
			'login'           => 'someone',
			'failed_username' => 'someone',
		];

		return (string) Simple_History::get_instance()->get_log_row_html_output( $row, [] );
	}
}
