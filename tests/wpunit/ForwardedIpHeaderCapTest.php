<?php

use Simple_History\Helpers;

/**
 * Forwarded IP headers are sent by the client. One request with a long
 * X-Forwarded-For header must not write thousands of context rows.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit ForwardedIpHeaderCapTest
 */
class ForwardedIpHeaderCapTest extends \Codeception\TestCase\WPTestCase {
	/** @var array|null Context of the last logged event. */
	private $context;

	public function setUp(): void {
		parent::setUp();

		add_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10 );
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		parent::tearDown();
	}

	public function capture_write( $data_and_context ) {
		$this->context = $data_and_context[1];

		return $data_and_context;
	}

	private function forwarded_ip_values() {
		$prefix = Helpers::get_ip_address_context_key_prefix( 'HTTP_X_FORWARDED_FOR' );
		$values = [];

		foreach ( $this->context as $key => $value ) {
			if ( str_starts_with( $key, $prefix ) ) {
				$values[] = $value;
			}
		}

		return $values;
	}

	public function test_long_header_stores_at_most_five_addresses() {
		$ips = [];

		for ( $i = 1; $i <= 200; $i++ ) {
			// Vary the third octet: anonymization zeroes the last one.
			$ips[] = '8.8.' . $i . '.1';
		}

		$_SERVER['HTTP_X_FORWARDED_FOR'] = implode( ',', $ips );

		SimpleLogger()->notice( 'Forwarded IP cap test' );

		$this->assertCount( 5, $this->forwarded_ip_values() );
	}

	public function test_repeated_address_is_stored_once() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = implode( ',', array_fill( 0, 1000, '1.1.1.1' ) ) . ',9.9.9.9';

		SimpleLogger()->notice( 'Forwarded IP dedupe test' );

		$this->assertSame( [ '1.1.1.x', '9.9.9.x' ], $this->forwarded_ip_values() );
	}
}
