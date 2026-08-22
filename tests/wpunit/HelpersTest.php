<?php

use Simple_History\Simple_History;
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

	function test_constant_simple_history_dev_mode_is_not_defined() {
		$this->assertFalse( defined( 'SIMPLE_HISTORY_DEV' ) );
		$this->assertFalse( Helpers::dev_mode_is_enabled() );
	}

	function test_constant_simple_history_dev_mode_is_defined() {
		define( 'SIMPLE_HISTORY_DEV', true );
		$this->assertTrue( defined( 'SIMPLE_HISTORY_DEV' ) );
		$this->assertTrue( Helpers::dev_mode_is_enabled() );
	}

	// Test helper function Helpers:get_event_ip_number_headers()
	function test_get_event_ip_number_headers() {
		$event_row = new stdClass();
		$event_row->context = array(
			"_not_a_ip_address_header" => 'blah',
			"_server_http_x_forwarded_for_0" => '5.35.187.212',
			"_server_http_x_forwarded_for_1" => '5.35.187.x',
			"_server_http_x_forwarded_for_2" => '5.35.187.0',
			"_server_http_x_cluster_client_ip_0" => '5.35.187.0',
			"_server_http_x_cluster_client_ip_1" => '5.35.187.0',
			"another_key_that_is_not_an_ip_address_header" => 'more blah',
			"_server_http_x_forwarded_for_99" => '5.35.87.0',	
		);

		$this->assertEquals(
			array(
				"_server_http_x_forwarded_for_0" => '5.35.187.212',
				"_server_http_x_forwarded_for_1" => '5.35.187.x',
				"_server_http_x_forwarded_for_2" => '5.35.187.0',	
				"_server_http_x_forwarded_for_99" => '5.35.87.0',	
				"_server_http_x_cluster_client_ip_0" => '5.35.187.0',
				"_server_http_x_cluster_client_ip_1" => '5.35.187.0',	
			),
			Helpers::get_event_ip_number_headers( $event_row ),
			'Found IP address headers'
		);
			
	}

	function test_sanitize_checkbox_input() {
		$this->assertEquals( '1', Helpers::sanitize_checkbox_input( '1' ) );
		$this->assertEquals( '0', Helpers::sanitize_checkbox_input( '' ) );
		$this->assertEquals( '0', Helpers::sanitize_checkbox_input( null ) );
	}

	function test_required_tables_exist() {
		$expected = [
			[
				'table_name' => 'wp_tests_simple_history',
				'table_exists' => true,
			],
			[
				'table_name' => 'wp_tests_simple_history_contexts',
				'table_exists' => true,

			],
		];

		
		$actual = Helpers::required_tables_exist();

		$this->assertEquals(
			$expected,
			$actual,
			'Expected tables to exist'
		);
	}

	function test_get_class_short_name() {
		$this->assertEquals( 'Simple_History', Helpers::get_class_short_name( Simple_History::get_instance() ) );
	}

	function test_is_valid_ip_address_filter_accepts_valid_ipv4() {
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '192.168.1.1' ) );
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '127.0.0.1' ) );
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '10.0.0.1' ) );
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '255.255.255.255' ) );
	}

	function test_is_valid_ip_address_filter_accepts_anonymized_ipv4() {
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '192.168.1.x' ) );
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '10.0.x.x' ) );
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '127.0.0.x' ) );
	}

	function test_is_valid_ip_address_filter_accepts_valid_ipv6() {
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '2a03:2880:f12f:83:face:b00c::25de' ) );
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '::1' ) );
		$this->assertTrue( Helpers::is_valid_ip_address_filter( '2001:db8:3c4d:15::' ) );
	}

	function test_is_valid_ip_address_filter_rejects_invalid_values() {
		$this->assertFalse( Helpers::is_valid_ip_address_filter( '' ) );
		$this->assertFalse( Helpers::is_valid_ip_address_filter( '....' ) );
		$this->assertFalse( Helpers::is_valid_ip_address_filter( 'xxxx' ) );
		$this->assertFalse( Helpers::is_valid_ip_address_filter( 'not-an-ip' ) );
		$this->assertFalse( Helpers::is_valid_ip_address_filter( '192.168.1' ) );
		$this->assertFalse( Helpers::is_valid_ip_address_filter( '<script>alert(1)</script>' ) );
		$this->assertFalse( Helpers::is_valid_ip_address_filter( '1 OR 1=1' ) );
		$this->assertFalse( Helpers::is_valid_ip_address_filter( '192.168.1.1; DROP TABLE' ) );
	}

	/**
	 * Test strip_4_byte_chars removes emojis and other 4-byte UTF-8 characters.
	 *
	 * @see https://github.com/bonny/WordPress-Simple-History/issues/607
	 */
	function test_strip_4_byte_chars() {
		// Emojis should be stripped.
		$this->assertEquals( 'Hello ', Helpers::strip_4_byte_chars( 'Hello 👋' ) );
		$this->assertEquals( 'Test  content', Helpers::strip_4_byte_chars( 'Test 📡 content' ) );
		$this->assertEquals( 'Multiple  emojis ', Helpers::strip_4_byte_chars( 'Multiple 🎉 emojis 🙂' ) );

		// String without emojis should remain unchanged.
		$this->assertEquals( 'Normal text', Helpers::strip_4_byte_chars( 'Normal text' ) );
		$this->assertEquals( 'Special chars: åäö éè', Helpers::strip_4_byte_chars( 'Special chars: åäö éè' ) );

		// Empty string should return empty string.
		$this->assertEquals( '', Helpers::strip_4_byte_chars( '' ) );

		// Non-string values should be returned unchanged.
		$this->assertEquals( 123, Helpers::strip_4_byte_chars( 123 ) );
		$this->assertEquals( null, Helpers::strip_4_byte_chars( null ) );
		$this->assertEquals( true, Helpers::strip_4_byte_chars( true ) );

		// Array should be returned unchanged.
		$test_array = [ 'key' => 'value', 'emoji' => '🎉' ];
		$this->assertEquals( $test_array, Helpers::strip_4_byte_chars( $test_array ) );

		// Object should be returned unchanged.
		$test_object = new stdClass();
		$test_object->name = 'Test';
		$test_object->emoji = '👋';
		$this->assertEquals( $test_object, Helpers::strip_4_byte_chars( $test_object ) );
	}

	function test_mask_secret_returns_last_four_for_long_values() {
		$this->assertEquals( '7890', Helpers::mask_secret( 'sk-ant-test-1234567890' ) );
		$this->assertEquals( 'EFGH', Helpers::mask_secret( 'abcdEFGH' ) );
		$this->assertEquals( 'cdef', Helpers::mask_secret( 'abcdef' ) );
	}

	function test_mask_secret_returns_null_when_secret_too_short_to_expose_suffix() {
		// At or below visible_suffix length: returning the suffix would *be* the secret.
		// Returning null signals callers to record only presence/length, not a useless mask.
		$this->assertNull( Helpers::mask_secret( 'abcd' ) );
		$this->assertNull( Helpers::mask_secret( 'abc' ) );
		$this->assertNull( Helpers::mask_secret( 'a' ) );
	}

	function test_mask_secret_returns_null_for_empty_input() {
		$this->assertNull( Helpers::mask_secret( '' ) );
	}

	function test_mask_secret_casts_non_strings() {
		// Integer cast to "1234567" (length 7) → last 4 chars exposed.
		$this->assertEquals( '4567', Helpers::mask_secret( 1234567 ) );
		// null casts to '' → null in, null out.
		$this->assertNull( Helpers::mask_secret( null ) );
		// Short integer cast to "12" (length 2) → null (too short).
		$this->assertNull( Helpers::mask_secret( 12 ) );
	}

	function test_mask_secret_respects_custom_visible_suffix() {
		$this->assertEquals( '90', Helpers::mask_secret( 'abc1234567890', 2 ) );
		$this->assertEquals( '4567890', Helpers::mask_secret( 'abc1234567890', 7 ) );
	}

	function test_mask_secret_returns_null_when_visible_suffix_zero_or_negative() {
		$this->assertNull( Helpers::mask_secret( 'longstring', -1 ) );
		$this->assertNull( Helpers::mask_secret( 'longstring', 0 ) );
	}

	function test_format_masked_secret_for_display_prepends_bullets() {
		// U+2022 BULLET. Default mask length is 12.
		$this->assertEquals(
			"\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}\u{2022}7890",
			Helpers::format_masked_secret_for_display( '7890' )
		);
	}

	function test_format_masked_secret_for_display_respects_custom_mask_length() {
		$this->assertEquals(
			"\u{2022}\u{2022}\u{2022}\u{2022}EFGH",
			Helpers::format_masked_secret_for_display( 'EFGH', 4 )
		);
	}

	function test_format_masked_secret_for_display_returns_empty_for_empty_input() {
		$this->assertEquals( '', Helpers::format_masked_secret_for_display( '' ) );
		$this->assertEquals( '', Helpers::format_masked_secret_for_display( null ) );
	}

	function test_format_masked_secret_for_display_clamps_negative_mask_length() {
		// Negative or zero mask length: just the suffix, no bullets.
		$this->assertEquals( '7890', Helpers::format_masked_secret_for_display( '7890', 0 ) );
		$this->assertEquals( '7890', Helpers::format_masked_secret_for_display( '7890', -5 ) );
	}

	function test_snake_case_to_sentence_case_capitalizes_first_word_only() {
		$this->assertEquals( 'Spam filtering', Helpers::snake_case_to_sentence_case( 'spam_filtering' ) );
		$this->assertEquals( 'A long multi word slug', Helpers::snake_case_to_sentence_case( 'a_long_multi_word_slug' ) );
	}

	function test_snake_case_to_sentence_case_handles_already_clean_input() {
		$this->assertEquals( 'Hello', Helpers::snake_case_to_sentence_case( 'hello' ) );
		$this->assertEquals( '', Helpers::snake_case_to_sentence_case( '' ) );
	}

	/**
	 * The key prefix is the single definition of how addresses from a header are
	 * stored. Logger writes with it, the display path and the query read with it,
	 * so pinning the exact string keeps those three from drifting apart.
	 */
	function test_get_ip_address_context_key_prefix() {
		$this->assertEquals(
			'_server_http_x_forwarded_for_',
			Helpers::get_ip_address_context_key_prefix( 'HTTP_X_FORWARDED_FOR' )
		);

		$this->assertEquals(
			'_server_http_client_ip_',
			Helpers::get_ip_address_context_key_prefix( 'HTTP_CLIENT_IP' )
		);
	}

	function test_get_ip_address_context_key_prefixes_includes_remote_addr_and_headers() {
		$prefixes = Helpers::get_ip_address_context_key_prefixes();

		$this->assertContains( '_server_remote_addr', $prefixes, 'The address the web server saw must be included' );
		$this->assertContains( '_server_http_x_forwarded_for_', $prefixes );
		$this->assertContains( '_server_http_client_ip_', $prefixes );
	}

	/**
	 * Guard against a prefix that would sweep in context keys holding no address.
	 *
	 * "_server_http_referer" and "_server_http_user_agent" share the
	 * "_server_http_" prefix, so a broad pattern would let an IP filter match a
	 * referer URL. Asserted on the prefixes themselves so the contract is pinned
	 * here as well as at the query level.
	 */
	function test_get_ip_address_context_key_prefixes_do_not_match_non_ip_keys() {
		$prefixes = Helpers::get_ip_address_context_key_prefixes();

		$non_ip_keys = array( '_server_http_referer', '_server_http_user_agent' );

		foreach ( $non_ip_keys as $non_ip_key ) {
			foreach ( $prefixes as $prefix ) {
				$this->assertStringStartsNotWith(
					$prefix,
					$non_ip_key,
					"Prefix {$prefix} must not match {$non_ip_key}, which holds no IP address"
				);
			}
		}
	}

	/**
	 * A site that registers an extra header gets it stored and queryable.
	 */
	function test_get_ip_address_context_key_prefixes_honors_header_names_filter() {
		$add_header = function ( $headers ) {
			$headers[] = 'HTTP_CF_CONNECTING_IP';
			return $headers;
		};

		add_filter( 'simple_history/ip_number_header_names', $add_header );
		$prefixes = Helpers::get_ip_address_context_key_prefixes();
		remove_filter( 'simple_history/ip_number_header_names', $add_header );

		$this->assertContains( '_server_http_cf_connecting_ip_', $prefixes );
	}
}
