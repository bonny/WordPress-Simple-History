<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Log_Query;

/**
 * Registers Simple History with WordPress's personal-data privacy tools
 * (Tools → Export/Erase Personal Data).
 *
 * The exporter is always registered. The eraser is gated behind experimental
 * features for one release cycle (see the design spec, "Release & lifecycle").
 *
 * @since 5.x
 */
class Privacy_Data_Handler extends Service {
	/**
	 * Privacy group / eraser id used by WordPress to bucket our data.
	 *
	 * @var string
	 */
	private const GROUP_ID = 'simple-history';

	/**
	 * Number of events processed per export/erase page.
	 *
	 * @var int
	 */
	private const PAGE_SIZE = 100;

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Exporter — always on. Read-only; zero behavioural risk.
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );

		// Eraser — gated behind experimental features for one release cycle.
		// When off, WordPress's erasure simply skips Simple History (the
		// pre-feature status quo); there is no half-built behaviour.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
	}

	/**
	 * Resolve an email to a user and fetch one page of their initiated events.
	 *
	 * Events are matched by the `_user_id` context key (initiator-only scope).
	 * Uses `ungrouped` so every individual event is returned — without it,
	 * Log_Query collapses repeated events by occasion, which would exclude
	 * duplicates from export and leave their personal data un-scrubbed on
	 * erasure. Rows come back newest-first (Log_Query's default ordering).
	 *
	 * @param string $email_address Email address from the privacy request.
	 * @param int    $page          1-based page number.
	 * @return array<int,object> Array of Log_Query row objects (may be empty).
	 */
	private function get_user_event_rows( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user instanceof \WP_User ) {
			return [];
		}

		$query_result = ( new Log_Query() )->query(
			[
				'user'           => $user->ID,
				'posts_per_page' => self::PAGE_SIZE,
				'paged'          => max( 1, (int) $page ),
				'ungrouped'      => true,
			]
		);

		if ( empty( $query_result['log_rows'] ) || ! is_array( $query_result['log_rows'] ) ) {
			return [];
		}

		return $query_result['log_rows'];
	}
}
