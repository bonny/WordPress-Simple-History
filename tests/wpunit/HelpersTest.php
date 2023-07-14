<?php

use Simple_History\Helpers;

class HelpersTest extends \Codeception\TestCase\WPTestCase {
	function test_privacy_anonymize_ip() {
		$ip_address = '127.0.0.1';
		$ip_address_expected = '127.0.0.x';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );

		$ip_address = '142.250.74.46';
		$ip_address_expected = '142.250.74.x';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );

		$ip_address = '2a03:2880:f12f:83:face:b00c::25de';
		$ip_address_expected = '2a03:2880:f12f:83::';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );		

		$ip_address = '2001:0db8:3c4d:0015:0000:0000:1a2f:1a2b';
		$ip_address_expected = '2001:db8:3c4d:15::';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );		
	}

	function test_privacy_anonymize_ip_without_char() {
		add_filter(
			'simple_history/privacy/add_char_to_anonymized_ip_address',
			'__return_false'
		);

		$ip_address = '127.0.0.1';
		$ip_address_expected = '127.0.0.0';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );

		$ip_address = '142.250.74.46';
		$ip_address_expected = '142.250.74.0';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );

		$ip_address = '2a03:2880:f12f:83:face:b00c::25de';
		$ip_address_expected = '2a03:2880:f12f:83::';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );		

		$ip_address = '2001:0db8:3c4d:0015:0000:0000:1a2f:1a2b';
		$ip_address_expected = '2001:db8:3c4d:15::';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );		
	}

	function test_privacy_anonymize_ip_disabled() {
		add_filter(
			'simple_history/privacy/anonymize_ip_address',
			'__return_false'
		);

		$ip_address = '127.0.0.1';
		$ip_address_expected = '127.0.0.1';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );

		$ip_address = '2a03:2880:f12f:83:face:b00c::25de';
		$ip_address_expected = '2a03:2880:f12f:83:face:b00c::25de';
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );
		$this->assertEquals( $ip_address_expected, Helpers::privacy_anonymize_ip( $ip_address ) );		
	}

	function test_get_valid_ip_address_from_anonymized() {
		$ip_address_expected = '127.0.0.0';
		$ip_address = '127.0.0.x';
		$this->assertEquals( $ip_address_expected, Helpers::get_valid_ip_address_from_anonymized( $ip_address ) );

		$ip_address_expected = '142.250.74.0';
		$ip_address = '142.250.74.x';
		$this->assertEquals( $ip_address_expected, Helpers::get_valid_ip_address_from_anonymized( $ip_address ) );
	}

	function test_constant_simple_history_log_debug_is_not_defined() {
		$this->assertFalse( defined( 'SIMPLE_HISTORY_LOG_DEBUG' ) );
		$this->assertFalse( Helpers::log_debug_is_enabled() );
	}

	function test_constant_simple_history_log_debug_is_defined() {
		define( 'SIMPLE_HISTORY_LOG_DEBUG', true );
		$this->assertTrue( defined( 'SIMPLE_HISTORY_LOG_DEBUG' ) );
		$this->assertTrue( Helpers::log_debug_is_enabled() );
	}
}
