<?php
/**
 * Privacy data handler service for Simple History.
 *
 * @package Simple_History
 */

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Log_Initiators;
use Simple_History\Log_Query;

/**
 * Registers Simple History with WordPress's personal-data privacy tools
 * (Tools → Export/Erase Personal Data).
 *
 * The exporter is always registered. The eraser is gated behind experimental
 * features for one release cycle (see the design spec, "Release & lifecycle").
 *
 * @since 5.29.0
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
				'value' => get_date_from_gmt( $row->date ),
			),
			array(
				'name'  => __( 'Date (UTC)', 'simple-history' ),
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
	 * Register Simple History as a personal-data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers[ self::GROUP_ID ] = array(
			'eraser_friendly_name' => __( 'Simple History activity log', 'simple-history' ),
			'callback'             => array( $this, 'erase_user_data' ),
		);

		return $erasers;
	}

	/**
	 * Anonymize one batch of the user's activity-log events.
	 *
	 * Scrubs PII while preserving the event rows as an audit record.
	 *
	 * Always fetches the FIRST page, ignoring the incoming `$page`: scrubbing
	 * zeroes each event's `_user_id`, so anonymized events drop out of the
	 * `user => ID` filter. Re-querying page 1 each call therefore walks through
	 * the remaining un-erased events. (Incrementing `$page` would skip events,
	 * because the result set shrinks under us between calls.)
	 *
	 * @param string $email_address Email from the privacy request.
	 * @param int    $page          1-based page number (unused; see above).
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public function erase_user_data( $email_address, $page = 1 ) {
		$rows = $this->get_user_event_rows( $email_address, 1 );

		foreach ( $rows as $row ) {
			$this->anonymize_event( $row->id );
		}

		$count = count( $rows );
		$done  = $count < self::PAGE_SIZE;

		$messages = array();

		if ( $count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of activity-log entries anonymized. */
				_n(
					'Simple History anonymized the personal data in %d activity-log entry. The entry is retained as an audit record with personal data removed.',
					'Simple History anonymized the personal data in %d activity-log entries. The entries are retained as an audit record with personal data removed.',
					$count,
					'simple-history'
				),
				$count
			);
		}

		// Log a summary event for any batch that actually scrubbed events. For
		// most users (fewer than PAGE_SIZE events) this fires exactly once;
		// guarding on `$done` would skip it entirely for users whose event count
		// is an exact multiple of PAGE_SIZE.
		if ( $count > 0 ) {
			$this->log_erasure_summary();
		}

		return array(
			'items_removed'  => $count > 0,
			'items_retained' => $count > 0,
			'messages'       => $messages,
			'done'           => $done,
		);
	}

	/**
	 * Log a single summary event for an erasure request. Count-free, no subject PII.
	 *
	 * @return void
	 */
	private function log_erasure_summary() {
		SimpleLogger()->info(
			'Anonymized personal data in Simple History for a privacy erasure request',
			array(
				'_initiator' => Log_Initiators::WP_USER,
			)
		);
	}

	/**
	 * Anonymize all PII in a single event's context, in place.
	 *
	 * Removes login/email/user-agent/referer, zeroes the initiator user id,
	 * and fully anonymizes every stored IP-address key. The event row itself
	 * is preserved as an audit record. Idempotent.
	 *
	 * IP keys covered: `_server_remote_addr` (exact) and proxy-header variants
	 * stored as `_server_http_*_N` (REGEXP `^_server_http_.+_[0-9]+$`).
	 *
	 * @param int $history_id Event id.
	 * @return void
	 */
	private function anonymize_event( $history_id ) {
		global $wpdb;

		$contexts_table = \Simple_History\Simple_History::get_instance()->get_contexts_table_name();

		// Initiator identity + device/network keys removed entirely. `_user_role`
		// is included because on small sites a role like "administrator" is
		// linkable to a specific person.
		$keys_to_remove = array( '_user_login', '_user_email', '_user_role', 'server_http_user_agent', '_server_http_referer' );

		foreach ( $keys_to_remove as $key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				$contexts_table,
				array(
					'history_id' => $history_id,
					'key'        => $key,
				),
				array( '%d', '%s' )
			);
		}

		// Initiator user id zeroed (kept as a key so the row stays well-formed).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$contexts_table,
			array( 'value' => '0' ),
			array(
				'history_id' => $history_id,
				'key'        => '_user_id',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		// Fully anonymize every stored IP key (main + proxy-header variants).
		// Fetch all context keys for the event and filter in PHP so this works
		// identically on MySQL/MariaDB and SQLite (no REGEXP dependency).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$all_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT `key` FROM {$contexts_table} WHERE history_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$history_id
			)
		);

		foreach ( $all_keys as $key ) {
			$is_ip_key = $key === '_server_remote_addr' || preg_match( '/^_server_http_.+_[0-9]+$/', $key );

			if ( ! $is_ip_key ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$contexts_table,
				array( 'value' => '0.0.0.x' ),
				array(
					'history_id' => $history_id,
					'key'        => $key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
		}
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
				'user'                       => $user->ID,
				'posts_per_page'             => self::PAGE_SIZE,
				'paged'                      => max( 1, (int) $page ),
				'ungrouped'                  => true,
				'ignore_logger_capabilities' => true,
			)
		);

		if ( empty( $query_result['log_rows'] ) || ! is_array( $query_result['log_rows'] ) ) {
			return array();
		}

		return $query_result['log_rows'];
	}
}
