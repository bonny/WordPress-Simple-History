<?php
/**
 * Privacy data handler service for Simple History.
 *
 * @package Simple_History
 */

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
	 * Register hooks for the privacy export and erasure integrations.
	 *
	 * @inheritdoc
	 */
	public function loaded() {
		// Exporter — always on. Read-only; zero behavioural risk.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );

		// Eraser — gated behind experimental features for one release cycle.
		// When off, WordPress's erasure simply skips Simple History (the
		// pre-feature status quo); there is no half-built behaviour.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register Simple History as a personal-data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters[ self::GROUP_ID ] = array(
			'exporter_friendly_name' => __( 'Simple History activity log', 'simple-history' ),
			'callback'               => array( $this, 'export_user_data' ),
		);

		return $exporters;
	}

	/**
	 * Export one page of the user's activity-log events.
	 *
	 * @param string $email_address Email from the privacy request.
	 * @param int    $page          1-based page number.
	 * @return array{data:array,done:bool}
	 */
	public function export_user_data( $email_address, $page = 1 ) {
		$rows = $this->get_user_event_rows( $email_address, $page );

		$export_items = array();

		foreach ( $rows as $row ) {
			$export_items[] = array(
				'group_id'    => self::GROUP_ID,
				'group_label' => __( 'Simple History activity log', 'simple-history' ),
				'item_id'     => 'sh-event-' . $row->id,
				'data'        => $this->build_export_item_data( $row ),
			);
		}

		return array(
			'data' => $export_items,
			'done' => count( $rows ) < self::PAGE_SIZE,
		);
	}

	/**
	 * Build the name/value field list for a single exported event.
	 *
	 * @param object $row Log_Query row object.
	 * @return array<int,array{name:string,value:string}>
	 */
	private function build_export_item_data( $row ) {
		$context = is_array( $row->context ) ? $row->context : array();

		$message = \Simple_History\Simple_History::get_instance()->get_log_row_plain_text_output( $row );

		return array(
			array(
				'name'  => __( 'Date', 'simple-history' ),
				'value' => $row->date,
			),
			array(
				'name'  => __( 'Logger', 'simple-history' ),
				'value' => $row->logger,
			),
			array(
				'name'  => __( 'Level', 'simple-history' ),
				'value' => $row->level,
			),
			array(
				'name'  => __( 'Message', 'simple-history' ),
				'value' => wp_strip_all_tags( $message ),
			),
			array(
				'name'  => __( 'IP address', 'simple-history' ),
				'value' => $context['_server_remote_addr'] ?? '',
			),
			array(
				'name'  => __( 'User agent', 'simple-history' ),
				'value' => $context['server_http_user_agent'] ?? '',
			),
		);
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
			return array();
		}

		$query_result = ( new Log_Query() )->query(
			array(
				'user'           => $user->ID,
				'posts_per_page' => self::PAGE_SIZE,
				'paged'          => max( 1, (int) $page ),
				'ungrouped'      => true,
			)
		);

		if ( empty( $query_result['log_rows'] ) || ! is_array( $query_result['log_rows'] ) ) {
			return array();
		}

		return $query_result['log_rows'];
	}
}
