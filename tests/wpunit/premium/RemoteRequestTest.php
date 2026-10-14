<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Channels\Splunk_Channel;

use function Simple_History\AddOns\Pro\is_valid_request_url;
use function Simple_History\AddOns\Pro\remote_request;

/**
 * Tests for remote_request(), the HTTP wrapper premium uses to send to
 * admin-configured destinations.
 *
 * It replaces wp_safe_remote_request(), which only allows ports 80, 443 and
 * 8080 and so rejected Splunk HEC on port 8088 with "A valid URL was not
 * provided." before any connection was made.
 *
 * @group premium
 * @group remote-request
 */
class RemoteRequestTest extends PremiumTestCase {
	/**
	 * URLs passed to the HTTP transport during the test.
	 *
	 * @var string[]
	 */
	private array $requested_urls = [];

	/**
	 * Set up each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		// Answer every request here so nothing leaves the test container.
		// WP_Http runs this filter before its own URL check, so repeat that
		// check here. Without it a wp_safe_remote_request() call would pass.
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				if ( ! empty( $args['reject_unsafe_urls'] ) && ! wp_http_validate_url( $url ) ) {
					return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
				}

				$this->requested_urls[] = $url;

				return [
					'headers'  => [],
					'body'     => '{"text":"Success","code":0}',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
				];
			},
			10,
			3
		);
	}

	/**
	 * URLs that must be accepted: any host type, any port.
	 *
	 * @return array<string, array{string}>
	 */
	public function valid_url_provider(): array {
		return [
			'public IP, Splunk port'    => [ 'https://203.0.113.10:8088' ],
			'domain, Splunk port'       => [ 'https://example.splunkcloud.com:8088/services/collector' ],
			'private IP'                => [ 'http://10.0.0.5:8088' ],
			'localhost'                 => [ 'http://localhost:9200' ],
			'IPv6 with port'            => [ 'https://[::1]:8088' ],
			'no port'                   => [ 'https://hooks.slack.com/services/T0/B0/X' ],
			'uppercase scheme'          => [ 'HTTPS://example.com' ],
			'query string'              => [ 'https://example.com/hook?key=abc' ],
		];
	}

	/**
	 * URLs that must be rejected.
	 *
	 * @return array<string, array{mixed}>
	 */
	public function invalid_url_provider(): array {
		return [
			'empty string'      => [ '' ],
			'not a string'      => [ null ],
			'no scheme'         => [ 'example.com:8088' ],
			'ftp scheme'        => [ 'ftp://example.com' ],
			'javascript scheme' => [ 'javascript:alert(1)' ],
			'file scheme'       => [ 'file:///etc/passwd' ],
			'no host'           => [ 'https://' ],
			'invalid host'      => [ 'https://bad_host!:8088' ],
			'port out of range' => [ 'https://example.com:99999' ],
			'plain text'        => [ 'not a url' ],
		];
	}

	/**
	 * @dataProvider valid_url_provider
	 *
	 * @param string $url URL to check.
	 */
	public function test_valid_urls_are_accepted( string $url ): void {
		$this->assertTrue( is_valid_request_url( $url ) );
	}

	/**
	 * @dataProvider invalid_url_provider
	 *
	 * @param mixed $url URL to check.
	 */
	public function test_invalid_urls_are_rejected( $url ): void {
		$this->assertFalse( is_valid_request_url( $url ) );
	}

	/**
	 * A port that wp_safe_remote_request() refuses is sent.
	 */
	public function test_non_standard_port_is_sent(): void {
		$response = remote_request( 'https://203.0.113.10:8088/services/collector/event', [ 'method' => 'POST' ] );

		$this->assertNotWPError( $response );
		$this->assertSame( 200, wp_remote_retrieve_response_code( $response ) );
		$this->assertSame( [ 'https://203.0.113.10:8088/services/collector/event' ], $this->requested_urls );
	}

	/**
	 * A private address is sent, so self-hosted endpoints on the internal network work.
	 */
	public function test_private_address_is_sent(): void {
		$response = remote_request( 'http://192.168.1.20:8088/services/collector/event' );

		$this->assertNotWPError( $response );
		$this->assertSame( [ 'http://192.168.1.20:8088/services/collector/event' ], $this->requested_urls );
	}

	/**
	 * An invalid URL returns an error and never reaches the transport.
	 */
	public function test_invalid_url_returns_error_without_request(): void {
		$response = remote_request( 'ftp://example.com/file' );

		$this->assertWPError( $response );
		$this->assertSame( 'http_request_failed', $response->get_error_code() );
		$this->assertSame( [], $this->requested_urls );
	}

	/**
	 * The Splunk channel sends to a HEC URL on port 8088.
	 *
	 * A HEC URL with an IP address and port 8088 used to fail with
	 * "A valid URL was not provided."
	 */
	public function test_splunk_channel_sends_to_port_8088(): void {
		$channel = new Splunk_Channel();

		update_option(
			$channel->get_settings_option_name(),
			[
				'enabled'   => true,
				'hec_url'   => 'https://203.0.113.10:8088',
				'token'     => 'test-token',
				'sslverify' => false,
			]
		);

		$result = $channel->send_event( [ 'message' => 'Test event' ], 'Test event' );

		$this->assertTrue( $result );
		$this->assertSame( [ 'https://203.0.113.10:8088/services/collector/event' ], $this->requested_urls );
	}
}
