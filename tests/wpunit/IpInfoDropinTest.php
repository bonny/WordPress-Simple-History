<?php

use Simple_History\Dropins\IP_Info_Dropin;
use Simple_History\Simple_History;

/**
 * Test IP_Info_Dropin filter logic — which message keys opt in to
 * surfacing IP addresses (in row headers and the REST API response).
 *
 * Failed application password authentications were previously falling
 * through to the default of false, which left `ip_addresses` empty in
 * the REST response even though `_server_remote_addr` was in context.
 * See issue 199.
 */
class IpInfoDropinTest extends \Codeception\TestCase\WPTestCase {

	/** @var IP_Info_Dropin */
	private $dropin;

	public function setUp(): void {
		parent::setUp();

		$this->dropin = new IP_Info_Dropin( Simple_History::get_instance() );
	}

	/**
	 * Build a minimal row object the filter expects.
	 */
	private function make_row( string $logger, string $message_key ): object {
		return (object) [
			'logger'              => $logger,
			'context_message_key' => $message_key,
		];
	}

	/**
	 * @dataProvider provides_keys_that_should_show_ip
	 */
	public function test_returns_true_for_message_keys_in_allow_list( string $message_key ): void {
		$row    = $this->make_row( 'SimpleUserLogger', $message_key );
		$result = $this->dropin->row_header_display_ip_address_filter( false, $row );

		$this->assertTrue(
			$result,
			"Expected IP display to be enabled for {$message_key}"
		);
	}

	public function provides_keys_that_should_show_ip(): array {
		return [
			'standard login'                          => [ 'user_logged_in' ],
			'standard login failed'                   => [ 'user_login_failed' ],
			'unknown user login failed'               => [ 'user_unknown_login_failed' ],
			'unknown user logged in'                  => [ 'user_unknown_logged_in' ],
			'app-password login failed'               => [ 'user_application_password_login_failed' ],
			'app-password unknown user login failed'  => [ 'user_application_password_unknown_login_failed' ],
		];
	}

	public function test_returns_initial_value_for_unrelated_message_key(): void {
		$row = $this->make_row( 'SimpleUserLogger', 'user_updated_profile' );

		$this->assertFalse(
			$this->dropin->row_header_display_ip_address_filter( false, $row ),
			'Unrelated message keys must not opt in to IP display'
		);
		$this->assertTrue(
			$this->dropin->row_header_display_ip_address_filter( true, $row ),
			'Filter must preserve a pre-set true value for unrelated keys'
		);
	}

	public function test_returns_initial_value_for_non_user_logger(): void {
		$row = $this->make_row( 'SimplePluginLogger', 'user_login_failed' );

		$this->assertFalse(
			$this->dropin->row_header_display_ip_address_filter( false, $row ),
			'Rows from other loggers must not opt in even if the key looks login-related'
		);
	}

	public function test_returns_initial_value_when_message_key_is_empty(): void {
		$row = $this->make_row( 'SimpleUserLogger', '' );

		$this->assertFalse(
			$this->dropin->row_header_display_ip_address_filter( false, $row )
		);
	}
}
