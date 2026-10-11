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
}
