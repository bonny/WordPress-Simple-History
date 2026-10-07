<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Demo mode for the "What's new" surfaces.
 *
 * Only with the SIMPLE_HISTORY_DEV constant on. Add a query arg to the event
 * log page to preview a state with made-up data, for that request only:
 *
 * - ?sh-whats-new-demo=whats-new       "What's new" box for core and Premium.
 * - ?sh-whats-new-demo=payment-failed  Billing message: payment didn't go through.
 * - ?sh-whats-new-demo=ends            Billing message: subscription ends.
 * - ?sh-whats-new-demo=expired-recent  Lapsed card, expired under 90 days ago.
 * - ?sh-whats-new-demo=expired-year    Lapsed card, expired 90–365 days ago.
 * - ?sh-whats-new-demo=expired-old     Lapsed card, expired over a year ago.
 *
 * Writes nothing; dismissing in demo mode is stored as usual, so reload
 * without the dismissal by clearing the user meta if needed.
 */
class Whats_New_Demo {
	/** @var string Current demo scenario. */
	private $scenario = '';

	/**
	 * Hook up the demo filters when a scenario is requested.
	 */
	public function init() {
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return;
		}

		// Read-only preview switch, no nonce needed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scenario = isset( $_GET['sh-whats-new-demo'] ) ? sanitize_key( wp_unslash( $_GET['sh-whats-new-demo'] ) ) : '';

		$scenarios = [ 'whats-new', 'payment-failed', 'ends', 'expired-recent', 'expired-year', 'expired-old' ];

		if ( ! in_array( $scenario, $scenarios, true ) ) {
			return;
		}

		$this->scenario = $scenario;

		add_filter( 'simple_history/whats_new/billing_state', [ $this, 'filter_billing_state' ] );
		add_filter( 'simple_history/whats_new/lapse', [ $this, 'filter_lapse' ] );
		add_filter( 'get_user_metadata', [ $this, 'filter_user_meta' ], 10, 3 );
		add_filter( 'simple_history/whats_new/plugins', [ $this, 'filter_plugins' ] );
	}

	/**
	 * Billing state for the billing scenarios, none otherwise.
	 *
	 * @return array{type:string,key:string,ends_timestamp:?int}|null
	 */
	public function filter_billing_state() {
		if ( $this->scenario === 'payment-failed' ) {
			return [
				'type'           => 'payment_failed',
				'key'            => 'demo-payment-failed',
				'ends_timestamp' => null,
			];
		}

		if ( $this->scenario === 'ends' ) {
			return [
				'type'           => 'ends',
				'key'            => 'demo-ends',
				'ends_timestamp' => time() + 27 * DAY_IN_SECONDS,
			];
		}

		return null;
	}

	/**
	 * Lapse data for the expired scenarios, none otherwise.
	 *
	 * @return array{expires_timestamp:int,features:array<int,array<string,mixed>>}|null
	 */
	public function filter_lapse() {
		$days = [
			'expired-recent' => 45,
			'expired-year'   => 200,
			'expired-old'    => 500,
		];

		if ( ! isset( $days[ $this->scenario ] ) ) {
			return null;
		}

		$names = [ 'Table view', 'Splunk forwarding', 'Notes on events', 'Datadog forwarding', 'Saved views', 'Post activity panel', 'Syslog forwarding', 'Manual backfill' ];

		$features = [];

		foreach ( $names as $index => $name ) {
			$features[] = [
				'version' => '1.' . ( 16 - $index ) . '.0',
				'name'    => $name,
				'date'    => gmdate( 'Y-m-d', time() - ( 30 + $index * 40 ) * DAY_IN_SECONDS ),
			];
		}

		return [
			'expires_timestamp' => time() - $days[ $this->scenario ] * DAY_IN_SECONDS,
			'features'          => $features,
		];
	}

	/**
	 * Pretend the user last saw older versions, and has dismissed nothing.
	 *
	 * @param mixed  $value Meta value, null to read from the database.
	 * @param int    $object_id User ID.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public function filter_user_meta( $value, $object_id, $meta_key ) {
		if ( $meta_key === Whats_New_Service::META_SEEN && $this->scenario === 'whats-new' ) {
			return [
				[
					'simple-history'         => [
						'version' => '5.33.0',
						'views'   => 0,
					],
					'simple-history-premium' => [
						'version' => '1.13.0',
						'views'   => 0,
					],
				],
			];
		}

		if ( in_array( $meta_key, [ Whats_New_Service::META_BILLING_DISMISSED, Whats_New_Service::META_LAPSED_DISMISSED ], true ) ) {
			return [ '' ];
		}

		return $value;
	}

	/**
	 * Show a Premium line even where Premium isn't installed.
	 *
	 * @param array<string,array<string,string>> $plugins Plugins keyed by slug.
	 * @return array<string,array<string,string>>
	 */
	public function filter_plugins( $plugins ) {
		if ( $this->scenario !== 'whats-new' || isset( $plugins['simple-history-premium'] ) ) {
			return $plugins;
		}

		$plugins['simple-history-premium'] = [
			'slug'    => 'simple-history-premium',
			'name'    => 'Premium',
			'version' => '1.15.0',
			'readme'  => WP_PLUGIN_DIR . '/simple-history-premium/readme.txt',
		];

		return $plugins;
	}
}
