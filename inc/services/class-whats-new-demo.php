<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Demo mode for the what's new surfaces.
 *
 * Only active when the SIMPLE_HISTORY_DEV constant is true. Lets a developer
 * preview every card/strip state with mock data for the current request only,
 * via a query arg on the event log page:
 *
 * - ?sh-whats-new-demo=expired-90    Lapsed less than 90 days ago.
 * - ?sh-whats-new-demo=expired-365   Lapsed 90-365 days ago.
 * - ?sh-whats-new-demo=expired-old   Lapsed more than a year ago.
 * - ?sh-whats-new-demo=small         Small variant (full card already dismissed).
 * - ?sh-whats-new-demo=active-update Active premium with pending update strip.
 *
 * Nothing is written to the database; all state is injected through filters.
 */
class Whats_New_Demo {
	/** @var string Current demo scenario. */
	private $scenario = '';

	/**
	 * Hook up the demo filters when dev mode is on and a scenario is requested.
	 */
	public function init() {
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return;
		}

		// Read-only preview switch, no nonce needed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$scenario = isset( $_GET['sh-whats-new-demo'] ) ? sanitize_key( wp_unslash( $_GET['sh-whats-new-demo'] ) ) : '';

		$valid_scenarios = [ 'expired-90', 'expired-365', 'expired-old', 'small', 'active-update' ];

		if ( ! in_array( $scenario, $valid_scenarios, true ) ) {
			return;
		}

		$this->scenario = $scenario;

		add_filter( 'simple_history/whats_new/highlights', [ $this, 'filter_highlights' ] );
		add_filter( 'simple_history/whats_new/license_state', [ $this, 'filter_license_state' ] );
		add_filter( 'simple_history/whats_new/premium_update', [ $this, 'filter_premium_update' ] );
		add_filter( 'simple_history/whats_new/dismissal_state', [ $this, 'filter_dismissal_state' ] );
	}

	/**
	 * Inject mock feed highlights.
	 *
	 * @return array<int,array<string,mixed>> Mock highlights.
	 */
	public function filter_highlights() {
		$titles = [
			'Slack alerts',
			'Scheduled email reports',
			'Log retention up to forever',
			'Export to JSON and CSV',
			'Failed login limiting',
			'Custom log channels',
			'Syslog forwarding',
			'Granular log access control',
			'WooCommerce activity logging',
			'IP address location lookup',
			'Saved log searches',
			'Stealth mode',
			'Anomaly detection alerts',
			'REST API event feeds',
		];

		$highlights = [];

		foreach ( $titles as $index => $title ) {
			$highlights[] = [
				'version'             => '5.' . ( 24 - $index ) . '.0',
				'plugin'              => 'premium',
				'title'               => $title,
				'summary'             => 'Demo summary for ' . $title . '.',
				'url'                 => 'https://simple-history.com/add-ons/premium/',
				'min_version_to_show' => '',
				'audience'            => [ 'all' ],
			];
		}

		return $highlights;
	}

	/**
	 * Inject a simulated license state for the scenario.
	 *
	 * @return array{state:string,expires_at:?string} License state.
	 */
	public function filter_license_state() {
		switch ( $this->scenario ) {
			case 'expired-90':
				$days_ago = 45;
				break;

			case 'expired-365':
				$days_ago = 200;
				break;

			case 'expired-old':
				$days_ago = 500;
				break;

			case 'small':
				$days_ago = 200;
				break;

			default:
				// Active premium: license expires well into the future.
				return [
					'state'      => 'active',
					'expires_at' => gmdate( 'Y-m-d', time() + 200 * DAY_IN_SECONDS ),
				];
		}

		return [
			'state'      => 'expired',
			'expires_at' => gmdate( 'Y-m-d', time() - $days_ago * DAY_IN_SECONDS ),
		];
	}

	/**
	 * Simulate a pending premium update for the active-update scenario.
	 *
	 * @param array{new_version:string}|null $update Detected update.
	 * @return array{new_version:string}|null Update info.
	 */
	public function filter_premium_update( $update ) {
		if ( $this->scenario === 'active-update' ) {
			return [ 'new_version' => '5.24' ];
		}

		return $update;
	}

	/**
	 * Reset dismissal state so the scenario always renders.
	 *
	 * @return array{full_dismissed_at:int,small_dismissed_at:int,small_dismiss_count:int}
	 */
	public function filter_dismissal_state() {
		return [
			'full_dismissed_at'   => $this->scenario === 'small' ? time() : 0,
			'small_dismissed_at'  => 0,
			'small_dismiss_count' => 0,
		];
	}
}
