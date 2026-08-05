<?php

use Simple_History\Helpers;
use Simple_History\Loggers\Logger;
use Simple_History\Simple_History;

class LoggerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Server values to restore after each test, since the tests below drive
	 * append_remote_addr_to_context() by setting request headers.
	 *
	 * @var array
	 */
	private $server_backup;

	public function setUp(): void {
		parent::setUp();

		$this->server_backup = $_SERVER;
	}

	public function tearDown(): void {
		$_SERVER = $this->server_backup;

		parent::tearDown();
	}

	/**
	 * Call the private append_remote_addr_to_context() on a real logger.
	 *
	 * @param array $context Context to pass in.
	 * @return array Context with IP keys appended.
	 */
	private function append_remote_addr_to_context( $context = array() ) {
		$logger = new \Simple_History\Loggers\Simple_Logger( Simple_History::get_instance() );

		$method = new ReflectionMethod( $logger, 'append_remote_addr_to_context' );
		$method->setAccessible( true );

		return $method->invoke( $logger, $context );
	}

	/**
	 * The address the web server saw is stored under _server_remote_addr.
	 */
	function test_append_remote_addr_to_context_stores_remote_addr() {
		$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$context = $this->append_remote_addr_to_context();

		// Anonymization is on by default, so the last octet becomes "x".
		$this->assertEquals( '192.0.2.x', $context['_server_remote_addr'] );
	}

	/**
	 * Each address in a forwarding header gets its own indexed key.
	 *
	 * The index suffix is what lets a single header carry several comma
	 * separated addresses, and the key shape is what the query and the display
	 * path both match against — so it is pinned here.
	 */
	function test_append_remote_addr_to_context_indexes_multiple_forwarded_addresses() {
		$_SERVER['REMOTE_ADDR']            = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR']   = '192.0.2.10, 198.51.100.20, 203.0.113.30';

		$context = $this->append_remote_addr_to_context();

		$this->assertEquals( '192.0.2.x', $context['_server_http_x_forwarded_for_0'] );
		$this->assertEquals( '198.51.100.x', $context['_server_http_x_forwarded_for_1'] );
		$this->assertEquals( '203.0.113.x', $context['_server_http_x_forwarded_for_2'] );
	}

	/**
	 * Addresses from different headers are stored under their own prefixes.
	 */
	function test_append_remote_addr_to_context_stores_each_header_separately() {
		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.10';
		$_SERVER['HTTP_CLIENT_IP']       = '198.51.100.20';

		$context = $this->append_remote_addr_to_context();

		$this->assertEquals( '192.0.2.x', $context['_server_http_x_forwarded_for_0'] );
		$this->assertEquals( '198.51.100.x', $context['_server_http_client_ip_0'] );
	}

	/**
	 * The keys written must be the ones the query looks for.
	 *
	 * This is the contract that broke before: the writer and the query each had
	 * their own copy of the key naming convention, so they could drift apart and
	 * the symptom would be an address shown in the UI that the filter cannot
	 * find. Asserting the written keys against the shared prefixes keeps them
	 * honest without duplicating the format string a third time.
	 */
	function test_append_remote_addr_to_context_writes_keys_the_query_can_find() {
		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '192.0.2.10';

		$context = $this->append_remote_addr_to_context();

		$prefixes = Helpers::get_ip_address_context_key_prefixes();

		$ip_keys = array_filter(
			array_keys( $context ),
			function ( $key ) {
				// Everything the logger stores from $_SERVER, minus the keys
				// that are known not to hold an address.
				return strpos( $key, '_server_' ) === 0
					&& ! in_array( $key, array( '_server_http_referer', '_server_http_user_agent' ), true );
			}
		);

		$this->assertNotEmpty( $ip_keys, 'Sanity check: the logger should have stored some IP keys' );

		foreach ( $ip_keys as $ip_key ) {
			$matches_a_prefix = false;

			foreach ( $prefixes as $prefix ) {
				if ( strpos( $ip_key, $prefix ) === 0 ) {
					$matches_a_prefix = true;
					break;
				}
			}

			$this->assertTrue(
				$matches_a_prefix,
				"Context key {$ip_key} is written by the logger but matches no prefix the query searches, so it would be unfilterable"
			);
		}
	}

	/**
	 * A caller that already set the address keeps it, untouched.
	 */
	function test_append_remote_addr_to_context_leaves_existing_remote_addr_alone() {
		$_SERVER['REMOTE_ADDR']          = '192.0.2.10';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.20';

		$context = $this->append_remote_addr_to_context( array( '_server_remote_addr' => '203.0.113.55' ) );

		$this->assertEquals( '203.0.113.55', $context['_server_remote_addr'], 'A caller supplied address must not be overwritten' );
		$this->assertArrayNotHasKey(
			'_server_http_x_forwarded_for_0',
			$context,
			'Header collection is skipped entirely when the caller supplied the address'
		);
	}

	/**
	 * Private and reserved addresses in headers are not stored.
	 *
	 * Only public addresses are meaningful as a visitor identity, and a header
	 * is client supplied, so junk should not end up in the log.
	 */
	function test_append_remote_addr_to_context_skips_non_public_header_addresses() {
		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.5, not-an-ip, 192.0.2.10';

		$context = $this->append_remote_addr_to_context();

		$forwarded = array_filter(
			$context,
			function ( $key ) {
				return strpos( $key, '_server_http_x_forwarded_for_' ) === 0;
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->assertEquals(
			array( '_server_http_x_forwarded_for_2' => '192.0.2.x' ),
			$forwarded,
			'Only the public address should be stored, keeping its position in the header'
		);
	}
}
