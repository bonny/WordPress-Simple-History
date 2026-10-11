<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Channels\External_Database_Channel;
use Simple_History\AddOns\Pro\Channels\Remote_Syslog_Channel;

/**
 * Remote Syslog and External Database open connections to any host the
 * settings name. On multisite only network administrators may set that host,
 * or a subsite administrator could probe the internal network.
 *
 * The test suite runs single site, so the multisite decision is simulated by
 * overriding current_user_can_configure_channel().
 *
 * @group premium
 * @group channels
 */
class NetworkAdminOnlyChannelTest extends PremiumTestCase {
	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Channels whose current user may not configure them.
	 *
	 * @return array
	 */
	public function restricted_channels(): array {
		return [
			'syslog'      => [
				new class() extends Remote_Syslog_Channel {
					protected function current_user_can_configure_channel() {
						return false;
					}
				},
				[ 'host' => '169.254.169.254' ],
				'host',
			],
			'external db' => [
				new class() extends External_Database_Channel {
					protected function current_user_can_configure_channel() {
						return false;
					}
				},
				[ 'db_host' => '10.0.0.5' ],
				'db_host',
			],
		];
	}

	public function test_admin_can_configure_on_single_site() {
		$channel = new Remote_Syslog_Channel();

		$sanitized = $channel->sanitize_settings( [ 'host' => 'logs.example.com' ] );

		$this->assertSame( 'logs.example.com', $sanitized['host'] );
	}

	public function test_restricted_user_cannot_change_host() {
		foreach ( $this->restricted_channels() as $label => [ $channel, $input, $key ] ) {
			$stored = $channel->get_settings();

			$sanitized = $channel->sanitize_settings( $input );

			$this->assertSame( $stored, $sanitized, "$label: stored settings must be kept." );
			$this->assertNotSame( $input[ $key ], $sanitized[ $key ] ?? null, "$label: the new host must not be saved." );
		}
	}
}
