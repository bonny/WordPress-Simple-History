<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Channels\External_Database_Channel;
use Simple_History\AddOns\Pro\Channels\Remote_Syslog_Channel;

/**
 * Remote Syslog and External Database open connections to any host the
 * settings name. On multisite only network administrators may set that host,
 * or a subsite administrator could probe the internal network.
 *
 * The test suite runs single site, so multisite is simulated by overriding
 * is_network_install(). The permission check itself is the real one.
 *
 * @group premium
 * @group channels
 */
class NetworkAdminOnlyChannelTest extends PremiumTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		$GLOBALS['wp_settings_errors'] = [];
	}

	public function tearDown(): void {
		remove_all_filters( 'user_has_cap' );
		parent::tearDown();
	}

	/**
	 * The channels, running as if on a multisite network.
	 *
	 * @return array<string, array{0: object, 1: string, 2: string, 3: string}> Channel, setting key, stored value, attempted value.
	 */
	private function network_channels(): array {
		return [
			'syslog'      => [
				new class() extends Remote_Syslog_Channel {
					protected function is_network_install() {
						return true;
					}
				},
				'host',
				'logs.example.com',
				'169.254.169.254',
			],
			'external db' => [
				new class() extends External_Database_Channel {
					protected function is_network_install() {
						return true;
					}
				},
				'db_host',
				'db.example.com',
				'10.0.0.5',
			],
		];
	}

	/**
	 * Store a known value first, so "kept" can't pass because both sides are empty.
	 *
	 * @param object $channel Channel.
	 * @param string $key     Setting key.
	 * @param string $value   Value to store.
	 */
	private function store_setting( $channel, $key, $value ) {
		$settings         = $channel->get_settings();
		$settings[ $key ] = $value;
		update_option( $channel->get_settings_option_name(), $settings );
	}

	/**
	 * Codes of the settings errors added for an option.
	 *
	 * @param string $option_name Option name the error was added for.
	 * @return string[]
	 */
	private function get_settings_error_codes( $option_name ) {
		$errors = array_filter(
			$GLOBALS['wp_settings_errors'] ?? [],
			function ( $error ) use ( $option_name ) {
				return $error['setting'] === $option_name;
			}
		);

		return array_values( array_column( $errors, 'code' ) );
	}

	public function test_admin_can_configure_on_single_site() {
		$channel = new Remote_Syslog_Channel();

		$sanitized = $channel->sanitize_settings( [ 'host' => 'logs.example.com' ] );

		$this->assertSame( 'logs.example.com', $sanitized['host'] );
	}

	public function test_non_admin_cannot_change_host_on_single_site() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$channel = new Remote_Syslog_Channel();
		$this->store_setting( $channel, 'host', 'logs.example.com' );

		$sanitized = $channel->sanitize_settings( [ 'host' => '169.254.169.254' ] );

		$this->assertSame( 'logs.example.com', $sanitized['host'] );
		$this->assertSame( [ 'network_admin_only' ], $this->get_settings_error_codes( $channel->get_settings_option_name() ), 'The user is told why the change was not saved.' );
	}

	/**
	 * A site administrator has manage_options but not manage_network_options.
	 */
	public function test_site_admin_cannot_change_host_on_multisite() {
		$this->assertFalse( current_user_can( 'manage_network_options' ), 'Precondition: a site admin is not a network admin.' );

		foreach ( $this->network_channels() as $label => [ $channel, $key, $stored, $attempted ] ) {
			$this->store_setting( $channel, $key, $stored );

			$sanitized = $channel->sanitize_settings( [ $key => $attempted ] );

			$this->assertSame( $stored, $sanitized[ $key ], "$label: the stored host must be kept." );
			$this->assertSame( [ 'network_admin_only' ], $this->get_settings_error_codes( $channel->get_settings_option_name() ), "$label: the user is told why." );
		}
	}

	public function test_network_admin_can_change_host_on_multisite() {
		add_filter(
			'user_has_cap',
			function ( $allcaps ) {
				$allcaps['manage_network_options'] = true;
				return $allcaps;
			}
		);

		foreach ( $this->network_channels() as $label => [ $channel, $key, $stored, $attempted ] ) {
			$this->store_setting( $channel, $key, $stored );

			$sanitized = $channel->sanitize_settings( [ $key => $attempted ] );

			$this->assertSame( $attempted, $sanitized[ $key ], "$label: a network admin may change the host." );
			$this->assertNotContains( 'network_admin_only', $this->get_settings_error_codes( $channel->get_settings_option_name() ), "$label: no restriction notice." );
		}
	}

	/**
	 * Register the channel's setting the way core's Log Forwarding page does on
	 * admin_menu, so update_option() runs sanitize_settings() like it does on
	 * every wp-admin request.
	 *
	 * @param object $channel Channel.
	 */
	private function register_channel_setting( $channel ) {
		register_setting(
			'simple_history_settings_group_log_forwarding',
			$channel->get_settings_option_name(),
			[ 'sanitize_callback' => [ $channel, 'sanitize_settings' ] ]
		);
	}

	/**
	 * Record three failed sends through the channel's own handle_error().
	 *
	 * @param object $channel Channel.
	 */
	private function record_error( $channel ) {
		$handle_error = new \ReflectionMethod( $channel, 'handle_error' );
		$handle_error->setAccessible( true );

		for ( $i = 0; $i < 3; $i++ ) {
			$handle_error->invoke( $channel, 'Connection refused', 0 );
		}
	}

	/**
	 * A channel records its own errors with save_settings(). In wp-admin that
	 * runs through sanitize_settings(), which must not throw the record away.
	 */
	public function test_error_tracking_is_saved_while_setting_is_registered_on_single_site() {
		$channel = new Remote_Syslog_Channel();
		$this->store_setting( $channel, 'host', 'logs.example.com' );
		$this->register_channel_setting( $channel );

		$this->record_error( $channel );

		$stored = get_option( $channel->get_settings_option_name() );
		$this->assertSame( 'Connection refused', $stored['last_error']['message'] ?? null, 'The error is recorded.' );
		$this->assertSame( 3, $stored['consecutive_errors'] ?? null, 'The error count is recorded, so auto-disable can trigger.' );

		unregister_setting( 'simple_history_settings_group_log_forwarding', $channel->get_settings_option_name() );
	}

	/**
	 * Same, for a subsite admin on multisite: an event they cause is forwarded
	 * and the channel records the result. That is not the subsite admin
	 * changing the settings, so no restriction and no notice.
	 */
	public function test_error_tracking_is_saved_for_site_admin_on_multisite() {
		foreach ( $this->network_channels() as $label => [ $channel, $key, $stored ] ) {
			$this->store_setting( $channel, $key, $stored );
			$this->register_channel_setting( $channel );

			$this->record_error( $channel );

			$saved = get_option( $channel->get_settings_option_name() );
			$this->assertSame( 'Connection refused', $saved['last_error']['message'] ?? null, "$label: the error is recorded." );
			$this->assertSame( 3, $saved['consecutive_errors'] ?? null, "$label: the error count is recorded." );
			$this->assertSame( $stored, $saved[ $key ], "$label: the host is unchanged." );
			$this->assertSame( [], $this->get_settings_error_codes( $channel->get_settings_option_name() ), "$label: no restriction notice." );

			unregister_setting( 'simple_history_settings_group_log_forwarding', $channel->get_settings_option_name() );
		}
	}

	/**
	 * The Log Forwarding form saves every channel at once, and still shows the
	 * restricted channels' fields to a subsite admin. Saving the form without
	 * touching them posts their stored values back, which is not an attempt
	 * to change them.
	 */
	public function test_site_admin_saving_form_without_changing_restricted_channel_gets_no_notice() {
		foreach ( $this->network_channels() as $label => [ $channel, $key, $stored ] ) {
			$this->store_setting( $channel, $key, $stored );

			$sanitized = $channel->sanitize_settings( [ $key => $stored ] );

			$this->assertSame( $stored, $sanitized[ $key ], "$label: the stored host is kept." );
			$this->assertSame( [], $this->get_settings_error_codes( $channel->get_settings_option_name() ), "$label: no notice for a channel the user did not change." );
		}
	}
}
