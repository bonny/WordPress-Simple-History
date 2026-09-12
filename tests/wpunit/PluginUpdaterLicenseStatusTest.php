<?php
// tests/wpunit/PluginUpdaterLicenseStatusTest.php

use Simple_History\AddOn_Plugin;
use Simple_History\Plugin_Updater;

/**
 * The updater passes the update endpoint's `license` object on to the add-on,
 * and keeps working against a server that does not send one.
 */
class PluginUpdaterLicenseStatusTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG    = 'sh-test-addon';
	private const ID      = 'sh-test-addon/index.php';
	private const OPTION  = 'simple_history_plusplugin_message_' . self::SLUG;
	private const API_URL = 'https://example.test/wp-json/lsq/v1';

	/** @var array{code:int, body:string}|null Next fake HTTP reply. */
	private $fake_reply = null;

	/** @var int Number of HTTP requests seen. */
	private $requests_seen = 0;

	public function setUp(): void {
		parent::setUp();
		delete_option( self::OPTION );
		delete_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) );
		add_filter( 'pre_http_request', [ $this, 'filter_pre_http_request' ], 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'filter_pre_http_request' ] );
		delete_option( self::OPTION );
		delete_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) );
		parent::tearDown();
	}

	public function filter_pre_http_request( $preempt, $args, $url ) {
		if ( strpos( $url, self::API_URL ) === false ) {
			return $preempt;
		}

		$this->requests_seen++;

		if ( $this->fake_reply === null ) {
			return new WP_Error( 'http_request_failed', 'Fake network failure' );
		}

		return [
			'headers'  => [],
			'body'     => $this->fake_reply['body'],
			'response' => [
				'code'    => $this->fake_reply['code'],
				'message' => '',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function seed_activated_option(): void {
		update_option(
			self::OPTION,
			[
				'key'             => 'abc-123',
				'key_activated'   => true,
				'key_instance_id' => 'inst-1',
				'key_created_at'  => '2024-01-01T00:00:00.000000Z',
				'key_expires_at'  => '2025-01-01T00:00:00.000000Z',
				'product_id'      => 1,
				'product_name'    => 'Test add-on',
				'customer_name'   => 'Someone',
				'customer_email'  => 'someone@example.com',
			]
		);
	}

	private function updater(): Plugin_Updater {
		$updater = new Plugin_Updater( self::ID, self::SLUG, '1.0.0', self::API_URL );
		// Each test controls its own HTTP reply; no cross-test caching.
		$updater->cache_allowed = false;

		return $updater;
	}

	private function addon(): AddOn_Plugin {
		return new AddOn_Plugin( self::ID, self::SLUG, '1.0.0', 'Test add-on', 1 );
	}

	private function license_json( string $status, ?string $expires_at, bool $valid, string $error ): string {
		return wp_json_encode(
			[
				'valid'            => $valid,
				'status'           => $status,
				'expires_at'       => $expires_at,
				'created_at'       => '2024-01-01T00:00:00.000000Z',
				'activation_limit' => 1,
				'activation_usage' => 1,
				'product_name'     => 'Test add-on',
				'variant_name'     => '1 site',
				'error'            => $error,
				'checked_at'       => '2026-09-06T12:00:00Z',
			]
		);
	}

	public function test_no_key_makes_no_request() {
		$this->assertFalse( $this->updater()->request() );
		$this->assertSame( 0, $this->requests_seen );
	}

	public function test_200_with_license_stores_status_and_returns_update() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 200,
			'body' => '{"success":true,"error":"","error_code":"","update":{"version":"2.0.0","download_link":"https://example.test/dl.zip"},"license":' . $this->license_json( 'active', '2027-09-01T00:00:00.000000Z', true, '' ) . '}',
		];

		$remote = $this->updater()->request();

		$this->assertIsObject( $remote );
		$this->assertTrue( $remote->success );
		$this->assertSame( '2.0.0', $remote->update->version );

		$state = $this->addon()->get_license_state();
		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'update_check', $state['source'] );
		$this->assertSame( '2027-09-01T00:00:00.000000Z', $state['expires_at'] );
	}

	public function test_401_with_license_stores_expired_and_returns_no_update() {
		$this->seed_activated_option();
		$body = '{"success":false,"error":"Invalid license_key","error_code":"invalid_license_key","license":' . $this->license_json( 'expired', '2026-05-27T01:17:07.000000Z', false, 'This license key is expired.' ) . '}';
		$this->fake_reply = [
			'code' => 401,
			'body' => $body,
		];

		$remote = $this->updater()->request();

		$this->assertIsObject( $remote );
		$this->assertFalse( $remote->success );
		$this->assertObjectNotHasProperty( 'update', $remote );

		$state = $this->addon()->get_license_state();
		$this->assertSame( 'expired', $state['state'] );
		$this->assertSame( '2026-05-27T01:17:07.000000Z', $state['expires_at'] );
		$this->assertSame( 'This license key is expired.', $state['error'] );

		// cache_allowed = false only skips reading the cache; the write still
		// happens, and a 401 refusal is cached too (for a shorter window).
		$this->assertSame( $body, get_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) ) );
	}

	public function test_401_with_null_license_keeps_previous_state() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 401,
			'body' => '{"success":false,"error":"Invalid license_key","error_code":"invalid_license_key","license":null}',
		];

		$this->updater()->request();

		$state = $this->addon()->get_license_state();
		$this->assertSame( 'activation', $state['source'] );
		$this->assertSame( '2025-01-01T00:00:00.000000Z', $state['expires_at'] );
	}

	public function test_old_server_without_license_key_changes_nothing() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 200,
			'body' => '{"success":true,"error":"","error_code":"","update":{"version":"2.0.0","download_link":"https://example.test/dl.zip"}}',
		];

		$remote = $this->updater()->request();

		$this->assertSame( '2.0.0', $remote->update->version );
		$this->assertSame( 'activation', $this->addon()->get_license_state()['source'] );
		$this->assertSame( '2025-01-01T00:00:00.000000Z', get_option( self::OPTION )['key_expires_at'] );
	}

	public function test_old_server_401_without_body_json_returns_false() {
		$this->seed_activated_option();
		$this->fake_reply = [ 'code' => 401, 'body' => 'Unauthorized' ];

		$this->assertFalse( $this->updater()->request() );
		$this->assertSame( 'activation', $this->addon()->get_license_state()['source'] );
	}

	public function test_network_error_returns_false_and_keeps_state() {
		$this->seed_activated_option();
		$this->fake_reply = null;

		$this->assertFalse( $this->updater()->request() );
		$this->assertSame( 'activation', $this->addon()->get_license_state()['source'] );
	}

	public function test_cached_401_is_replayed_without_a_request_or_option_write() {
		$this->seed_activated_option();
		set_transient(
			Plugin_Updater::get_cache_key_for_slug( self::SLUG ),
			'{"success":false,"error":"Invalid license_key","error_code":"invalid_license_key","license":' . $this->license_json( 'expired', '2026-05-27T01:17:07.000000Z', false, 'This license key is expired.' ) . '}',
			HOUR_IN_SECONDS
		);

		// Leave cache_allowed at its default (true) so the cached payload is replayed.
		$updater = new Plugin_Updater( self::ID, self::SLUG, '1.0.0', self::API_URL );

		$remote = $updater->request();

		$this->assertIsObject( $remote );
		$this->assertFalse( $remote->success );
		$this->assertSame( 0, $this->requests_seen );

		$state = $this->addon()->get_license_state();
		$this->assertSame( 'activation', $state['source'] );
		$this->assertSame( '2025-01-01T00:00:00.000000Z', $state['expires_at'] );

		$transient = (object) [
			'checked'   => [ self::ID => '1.0.0' ],
			'response'  => [],
			'no_update' => [],
		];

		$transient = $updater->site_transient_update_plugins_update( $transient );

		$this->assertArrayNotHasKey( self::ID, $transient->response );
		$this->assertArrayHasKey( self::ID, $transient->no_update );
	}

	public function test_foreign_401_json_offers_no_update_without_notices() {
		$this->seed_activated_option();
		// A 401 JSON body from something other than our endpoint, e.g. a
		// WordPress REST auth error or a security plugin. It has no `success`
		// property, so reading it unguarded emits "Undefined property".
		$this->fake_reply = [
			'code' => 401,
			'body' => '{"code":"rest_forbidden","message":"Sorry, you are not allowed to do that.","data":{"status":401}}',
		];

		$transient = (object) [
			'checked'   => [ self::ID => '1.0.0' ],
			'response'  => [],
			'no_update' => [],
		];

		// WPTestCase converts PHP warnings/notices to exceptions, so simply
		// reaching the assertions below without one being thrown is itself
		// part of what this test verifies.
		$transient = $this->updater()->site_transient_update_plugins_update( $transient );

		$this->assertArrayNotHasKey( self::ID, $transient->response );
		$this->assertArrayHasKey( self::ID, $transient->no_update );

		// cache_allowed = false only skips reading the cache; the write still
		// happens, and a 401 refusal is cached too (for a shorter window).
		$this->assertSame( $this->fake_reply['body'], get_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) ) );
	}

	public function test_site_transient_filter_offers_no_update_for_expired_key() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 401,
			'body' => '{"success":false,"error":"Invalid license_key","error_code":"invalid_license_key","license":' . $this->license_json( 'expired', '2026-05-27T01:17:07.000000Z', false, 'This license key is expired.' ) . '}',
		];

		$transient = (object) [
			'checked'   => [ self::ID => '1.0.0' ],
			'response'  => [],
			'no_update' => [],
		];

		$transient = $this->updater()->site_transient_update_plugins_update( $transient );

		$this->assertArrayNotHasKey( self::ID, $transient->response );
		$this->assertArrayHasKey( self::ID, $transient->no_update );
	}
}
