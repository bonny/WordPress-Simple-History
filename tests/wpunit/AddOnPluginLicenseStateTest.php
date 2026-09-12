<?php
// tests/wpunit/AddOnPluginLicenseStateTest.php

use Simple_History\AddOn_Plugin;
use Simple_History\Plugin_Updater;

/**
 * License state derived from the per-add-on license option, refreshed by update checks.
 */
class AddOnPluginLicenseStateTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG = 'sh-test-addon';
	private const OPTION = 'simple_history_plusplugin_message_' . self::SLUG;

	/** @var callable|null Filter callback added by test_deactivation_clears_status_and_updater_cache(), if any. */
	private $pre_http_request_filter = null;

	public function setUp(): void {
		parent::setUp();
		delete_option( self::OPTION );
	}

	public function tearDown(): void {
		delete_option( self::OPTION );

		if ( $this->pre_http_request_filter !== null ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter );
			$this->pre_http_request_filter = null;
		}

		parent::tearDown();
	}

	private function addon(): AddOn_Plugin {
		return new AddOn_Plugin( self::SLUG . '/index.php', self::SLUG, '1.0.0', 'Test add-on', 1 );
	}

	/** Option as written by an older core at activation time: no license_* keys at all. */
	private function seed_activated_option( ?string $expires_at ): void {
		update_option(
			self::OPTION,
			[
				'key'             => 'abc-123',
				'key_activated'   => true,
				'key_instance_id' => 'inst-1',
				'key_created_at'  => '2024-01-01T00:00:00.000000Z',
				'key_expires_at'  => $expires_at,
				'product_id'      => 1,
				'product_name'    => 'Test add-on',
				'customer_name'   => 'Someone',
				'customer_email'  => 'someone@example.com',
			]
		);
	}

	public function test_no_key_gives_state_none() {
		$state = $this->addon()->get_license_state();

		$this->assertSame( 'none', $state['state'] );
		$this->assertSame( 'none', $state['source'] );
		$this->assertSame( '', $this->addon()->get_license_state_description() );
	}

	public function test_key_that_failed_activation_gives_state_none() {
		update_option( self::OPTION, [ 'key' => 'abc-123', 'key_activated' => false ] );

		$this->assertSame( 'none', $this->addon()->get_license_state()['state'] );
	}

	public function test_activation_only_option_with_future_expiry_is_active() {
		$this->seed_activated_option( gmdate( 'Y-m-d\TH:i:s.000000\Z', time() + 30 * DAY_IN_SECONDS ) );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'activation', $state['source'] );
		$this->assertFalse( $state['is_lifetime'] );
		$this->assertNull( $state['checked_at'] );
	}

	public function test_activation_only_option_with_past_expiry_stays_active_without_a_check() {
		$this->seed_activated_option( '2024-01-23T13:25:53.000000Z' );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'activation', $state['source'] );
		$this->assertSame( strtotime( '2024-01-23T13:25:53.000000Z' ), $state['expires_timestamp'] );
	}

	public function test_activation_only_option_without_expiry_is_lifetime() {
		$this->seed_activated_option( null );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertTrue( $state['is_lifetime'] );
	}

	public function test_update_check_refreshes_a_stale_activation_expiry() {
		// Activated a year ago with a one-year key; renewed since, but core never knew.
		// The stale activation-time expiry has passed, but with no check yet
		// the state stays "active" rather than guessing "expired".
		$this->seed_activated_option( '2025-09-01T00:00:00.000000Z' );
		$this->assertSame( 'active', $this->addon()->get_license_state()['state'] );

		$written = $this->addon()->update_license_status_from_response(
			[
				'valid'            => true,
				'status'           => 'active',
				'expires_at'       => '2027-09-01T00:00:00.000000Z',
				'created_at'       => '2024-01-01T00:00:00.000000Z',
				'activation_limit' => 5,
				'activation_usage' => 2,
				'product_name'     => 'Test add-on',
				'variant_name'     => '5 sites',
				'error'            => '',
				'checked_at'       => '2026-09-06T12:00:00Z',
			]
		);

		$this->assertTrue( $written );

		$state  = $this->addon()->get_license_state();
		$option = get_option( self::OPTION );

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'update_check', $state['source'] );
		$this->assertSame( '2027-09-01T00:00:00.000000Z', $state['expires_at'] );
		$this->assertSame( '2026-09-06T12:00:00Z', $state['checked_at'] );
		$this->assertSame( 5, $state['activation_limit'] );
		$this->assertSame( 2, $state['activation_usage'] );
		// Activation-time fields survive the merge.
		$this->assertSame( 'abc-123', $option['key'] );
		$this->assertTrue( $option['key_activated'] );
		$this->assertSame( 'inst-1', $option['key_instance_id'] );
		$this->assertSame( 'someone@example.com', $option['customer_email'] );
		// key_expires_at is the refreshed value, not the activation-time one.
		$this->assertSame( '2027-09-01T00:00:00.000000Z', $option['key_expires_at'] );
	}

	public function test_update_check_reporting_expired_wins_over_activation_data() {
		$this->seed_activated_option( null );

		$this->addon()->update_license_status_from_response(
			[
				'valid'            => false,
				'status'           => 'expired',
				'expires_at'       => '2026-05-27T01:17:07.000000Z',
				'created_at'       => '2025-05-19T17:34:22.000000Z',
				'activation_limit' => 1,
				'activation_usage' => 1,
				'product_name'     => 'Test add-on',
				'variant_name'     => '1 site',
				'error'            => 'This license key is expired.',
				'checked_at'       => '2026-09-06T12:00:00Z',
			]
		);

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'expired', $state['state'] );
		$this->assertSame( 'This license key is expired.', $state['error'] );
		$this->assertFalse( $state['is_lifetime'] );
	}

	public function test_disabled_and_not_found_have_their_own_states() {
		$this->seed_activated_option( null );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'disabled', 'expires_at' => null, 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertSame( 'disabled', $this->addon()->get_license_state()['state'] );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => null, 'expires_at' => null, 'error' => 'license_key not found.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertSame( 'invalid', $this->addon()->get_license_state()['state'] );
	}

	public function test_inactive_status_has_its_own_state() {
		$this->seed_activated_option( null );

		$this->addon()->update_license_status_from_response( [ 'valid' => true, 'status' => 'inactive', 'expires_at' => null, 'error' => '', 'checked_at' => '2026-09-06T12:00:00Z' ] );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'inactive', $state['state'] );
		$this->assertFalse( $state['is_lifetime'] );
		$this->assertStringContainsString( 'not activated on any site', $this->addon()->get_license_state_description( $state ) );
	}

	public function test_null_or_malformed_license_keeps_previous_state() {
		$this->seed_activated_option( null );
		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'expired', 'expires_at' => '2026-05-27T01:17:07.000000Z', 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );

		$this->assertFalse( $this->addon()->update_license_status_from_response( null ) );
		$this->assertFalse( $this->addon()->update_license_status_from_response( 'yes' ) );
		$this->assertFalse( $this->addon()->update_license_status_from_response( [ 'status' => 'active' ] ) ); // no checked_at
		$this->assertFalse( $this->addon()->update_license_status_from_response( [ 'status' => [ 'nested' ], 'checked_at' => '2026-09-06T12:00:00Z' ] ) );

		$this->assertSame( 'expired', $this->addon()->get_license_state()['state'] );
	}

	public function test_update_check_never_creates_a_license_for_a_site_without_one() {
		// No option at all, yet a response arrives (should not happen, but be safe).
		$this->assertFalse( $this->addon()->update_license_status_from_response( [ 'valid' => true, 'status' => 'active', 'expires_at' => null, 'error' => '', 'checked_at' => '2026-09-06T12:00:00Z' ] ) );

		$this->assertFalse( get_option( self::OPTION ) );
	}

	public function test_description_text_per_state() {
		$this->seed_activated_option( null );
		$this->assertSame( 'Lifetime license.', $this->addon()->get_license_state_description() );

		$this->addon()->update_license_status_from_response( [ 'valid' => true, 'status' => 'active', 'expires_at' => '2027-09-01T00:00:00.000000Z', 'activation_limit' => 5, 'activation_usage' => 2, 'error' => '', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertStringContainsString( 'Valid until', $this->addon()->get_license_state_description() );
		$this->assertStringContainsString( wp_date( get_option( 'date_format' ), strtotime( '2027-09-01T00:00:00.000000Z' ) ), $this->addon()->get_license_state_description() );
		$this->assertStringContainsString( '2 of 5 sites', $this->addon()->get_license_state_description() );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'expired', 'expires_at' => '2026-05-27T01:17:07.000000Z', 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertStringContainsString( 'Expired on', $this->addon()->get_license_state_description() );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => null, 'expires_at' => null, 'error' => 'license_key not found.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertStringContainsString( 'no longer valid', $this->addon()->get_license_state_description() );
	}

	public function test_deactivation_clears_status_and_updater_cache() {
		$this->seed_activated_option( null );
		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'expired', 'expires_at' => '2026-05-27T01:17:07.000000Z', 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		set_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ), '{"success":false}', HOUR_IN_SECONDS );
		// deactivate_license() calls the license server; fake a 200 so the reset branch runs.
		$this->pre_http_request_filter = static function () {
			return [ 'headers' => [], 'body' => '{}', 'response' => [ 'code' => 200, 'message' => '' ], 'cookies' => [], 'filename' => null ];
		};
		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );

		$this->addon()->deactivate_license();

		$this->assertSame( 'none', $this->addon()->get_license_state()['state'] );
		$this->assertFalse( get_transient( Plugin_Updater::get_cache_key_for_slug( self::SLUG ) ) );
	}

	public function test_update_check_active_with_past_expiry_stays_active() {
		// Lemon Squeezy extends expires_at on renewal and the site only learns
		// that at the next check, so a stored, already-past expiry alongside
		// an "active" verdict must not be second-guessed into "expired" here.
		$this->seed_activated_option( null );
		$this->addon()->update_license_status_from_response(
			[
				'valid'      => true,
				'status'     => 'active',
				'expires_at' => '2026-01-01T00:00:00.000000Z',
				'error'      => '',
				'checked_at' => '2026-09-06T12:00:00Z',
			]
		);

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'update_check', $state['source'] );
		$this->assertSame( strtotime( '2026-01-01T00:00:00.000000Z' ), $state['expires_timestamp'] );
	}

	public function test_empty_expires_at_is_lifetime() {
		$this->seed_activated_option( null );
		$written = $this->addon()->update_license_status_from_response(
			[
				'valid'      => true,
				'status'     => 'active',
				'expires_at' => '',
				'error'      => '',
				'checked_at' => '2026-09-06T12:00:00Z',
			]
		);

		$this->assertTrue( $written );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertTrue( $state['is_lifetime'] );
	}

	public function test_activation_time_expiry_makes_no_expiry_claim_in_description() {
		$this->seed_activated_option( '2024-01-23T13:25:53.000000Z' );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'activation', $state['source'] );
		$this->assertSame( '', $this->addon()->get_license_state_description() );
	}

	public function test_activation_source_future_expiry_yields_valid_until_description() {
		$this->seed_activated_option( gmdate( 'Y-m-d\TH:i:s.000000\Z', time() + 30 * DAY_IN_SECONDS ) );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'activation', $state['source'] );
		$this->assertStringContainsString( 'Valid until', $this->addon()->get_license_state_description( $state ) );
	}

	public function test_unparsable_expires_at_is_rejected() {
		$this->seed_activated_option( null );
		$this->addon()->update_license_status_from_response(
			[
				'valid'      => false,
				'status'     => 'expired',
				'expires_at' => '2026-05-27T01:17:07.000000Z',
				'error'      => 'This license key is expired.',
				'checked_at' => '2026-09-06T12:00:00Z',
			]
		);

		$written = $this->addon()->update_license_status_from_response(
			[
				'valid'      => true,
				'status'     => 'active',
				'expires_at' => 'soon',
				'error'      => '',
				'checked_at' => '2026-09-07T12:00:00Z',
			]
		);

		$this->assertFalse( $written );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'expired', $state['state'] );
		$this->assertSame( '2026-05-27T01:17:07.000000Z', $state['expires_at'] );
		$this->assertSame( '2026-09-06T12:00:00Z', $state['checked_at'] );
	}
}
