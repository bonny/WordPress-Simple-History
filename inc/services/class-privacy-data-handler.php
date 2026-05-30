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
	 * Export one page of the user's activity-log data: events they initiated
	 * (full detail) plus events where they are the subject of someone else's
	 * action (third-party identity redacted, per GDPR Art. 15(4)).
	 *
	 * @param string $email_address Email from the privacy request.
	 * @param int    $page          1-based page number.
	 * @return array{data:array,done:bool}
	 */
	public function export_user_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user instanceof \WP_User ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$initiator_ids = $this->get_initiator_event_ids( $user->ID );
		$subject_ids   = array_values( array_diff( $this->get_subject_event_ids( $user ), $initiator_ids ) );

		$roles = array();

		foreach ( $initiator_ids as $id ) {
			$roles[ (int) $id ] = 'initiator';
		}

		foreach ( $subject_ids as $id ) {
			$roles[ (int) $id ] = 'subject';
		}

		$all_ids = array_keys( $roles );
		rsort( $all_ids, SORT_NUMERIC );

		$total    = count( $all_ids );
		$page_num = max( 1, (int) $page );
		$page_ids = array_slice( $all_ids, ( $page_num - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE );

		$export_items = array();

		if ( ! empty( $page_ids ) ) {
			$rows = $this->get_event_rows_by_ids( $page_ids );

			foreach ( $page_ids as $id ) {
				if ( ! isset( $rows[ $id ] ) ) {
					continue;
				}

				if ( $roles[ $id ] === 'subject' ) {
					$export_items[] = $this->build_subject_export_item( $rows[ $id ], $user );
				} else {
					$export_items[] = $this->build_initiator_export_item( $rows[ $id ] );
				}
			}
		}

		return array(
			'data' => $export_items,
			'done' => $page_num * self::PAGE_SIZE >= $total,
		);
	}

	/**
	 * Event ids the given user initiated (matched by the `_user_id` context key).
	 *
	 * @param int $user_id User id.
	 * @return int[]
	 */
	private function get_initiator_event_ids( $user_id ) {
		global $wpdb;

		$contexts_table = \Simple_History\Simple_History::get_instance()->get_contexts_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT history_id FROM {$contexts_table} WHERE `key` = %s AND value = %s",
				'_user_id',
				(string) $user_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( 'intval', $ids );
	}

	/**
	 * Context keys that identify a user an event was performed *on* (the
	 * subject/target), grouped by how their value matches the requester.
	 * Filterable so other loggers can register their own subject keys.
	 *
	 * @return array{id:string[],login:string[],email:string[]}
	 */
	private function get_subject_context_keys() {
		$keys = array(
			'id'    => array( 'created_user_id', 'edited_user_id', 'deleted_user_id', 'old_user_id', 'reassign_user_id', 'login_id', 'user_id' ),
			'login' => array( 'created_user_login', 'edited_user_login', 'deleted_user_login', 'login', 'failed_username', 'user_login', 'user_login_to', 'user_login_from' ),
			'email' => array( 'created_user_email', 'edited_user_email', 'deleted_user_email', 'login_email', 'user_email' ),
		);

		/**
		 * Filters the context keys used to find events where a user is the
		 * subject/target, for the privacy data export.
		 *
		 * @param array $keys Keys grouped by 'id', 'login', 'email'.
		 */
		return apply_filters( 'simple_history/privacy/subject_context_keys', $keys );
	}

	/**
	 * Event ids where the given user is the subject/target of an action.
	 *
	 * @param \WP_User $user User.
	 * @return int[]
	 */
	private function get_subject_event_ids( $user ) {
		global $wpdb;

		$contexts_table = \Simple_History\Simple_History::get_instance()->get_contexts_table_name();
		$keys           = $this->get_subject_context_keys();

		$clauses = array();
		$params  = array();

		if ( ! empty( $keys['id'] ) ) {
			$clauses[] = '( `key` IN ( ' . implode( ', ', array_fill( 0, count( $keys['id'] ), '%s' ) ) . ' ) AND value = %s )';
			$params    = array_merge( $params, array_values( $keys['id'] ), array( (string) $user->ID ) );
		}

		if ( ! empty( $keys['login'] ) && (string) $user->user_login !== '' ) {
			$clauses[] = '( `key` IN ( ' . implode( ', ', array_fill( 0, count( $keys['login'] ), '%s' ) ) . ' ) AND value = %s )';
			$params    = array_merge( $params, array_values( $keys['login'] ), array( (string) $user->user_login ) );
		}

		if ( ! empty( $keys['email'] ) && (string) $user->user_email !== '' ) {
			$clauses[] = '( `key` IN ( ' . implode( ', ', array_fill( 0, count( $keys['email'] ), '%s' ) ) . ' ) AND value = %s )';
			$params    = array_merge( $params, array_values( $keys['email'] ), array( (string) $user->user_email ) );
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		$sql = "SELECT DISTINCT history_id FROM {$contexts_table} WHERE " . implode( ' OR ', $clauses );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Load event rows for a set of ids, keyed by id. Bypasses logger-capability
	 * filtering so the export is complete.
	 *
	 * @param int[] $ids Event ids.
	 * @return array<int,object>
	 */
	private function get_event_rows_by_ids( $ids ) {
		if ( empty( $ids ) ) {
			return array();
		}

		$query_result = ( new Log_Query() )->query(
			array(
				'post__in'                   => array_map( 'intval', $ids ),
				'posts_per_page'             => count( $ids ),
				'paged'                      => 1,
				'ungrouped'                  => true,
				'ignore_logger_capabilities' => true,
			)
		);

		$rows = array();

		if ( ! empty( $query_result['log_rows'] ) && is_array( $query_result['log_rows'] ) ) {
			foreach ( $query_result['log_rows'] as $row ) {
				$rows[ (int) $row->id ] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Build a full export item for an event the requester initiated.
	 *
	 * @param object $row Log_Query row.
	 * @return array
	 */
	private function build_initiator_export_item( $row ) {
		return array(
			'group_id'    => self::GROUP_ID,
			'group_label' => __( 'Simple History activity log', 'simple-history' ),
			'item_id'     => 'sh-event-' . $row->id,
			'data'        => $this->build_export_item_data( $row ),
		);
	}

	/**
	 * Build a redacted export item for an event where the requester is the
	 * subject of someone else's action. Omits the actor's IP/user-agent and
	 * redacts any third-party login/email from the message (GDPR Art. 15(4)).
	 *
	 * @param object   $row  Log_Query row.
	 * @param \WP_User $user The requester.
	 * @return array
	 */
	private function build_subject_export_item( $row, $user ) {
		$context = is_array( $row->context ) ? $row->context : array();

		$message = \Simple_History\Simple_History::get_instance()->get_log_row_plain_text_output( $row );
		$message = $this->redact_third_party_identity( wp_strip_all_tags( $message ), $context, $user );

		return array(
			'group_id'    => self::GROUP_ID . '-subject',
			'group_label' => __( 'Simple History — activity concerning you (performed by others)', 'simple-history' ),
			'item_id'     => 'sh-event-' . $row->id,
			'data'        => array(
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
					'name'  => __( 'Action concerning you', 'simple-history' ),
					'value' => $message,
				),
			),
		);
	}

	/**
	 * Remove other people's login/email (and the actor's identity) from a
	 * subject-event message, leaving the requester's own identifiers intact.
	 *
	 * @param string   $message Plain-text message.
	 * @param array    $context Event context.
	 * @param \WP_User $user    The requester.
	 * @return string
	 */
	private function redact_third_party_identity( $message, $context, $user ) {
		$own = array_filter(
			array(
				(string) $user->ID,
				(string) $user->user_login,
				(string) $user->user_email,
			),
			static function ( $v ) {
				return $v !== '';
			}
		);

		$subject_keys  = $this->get_subject_context_keys();
		$identity_keys = array_merge( array( '_user_login', '_user_email' ), $subject_keys['login'], $subject_keys['email'] );

		$secrets = array();

		foreach ( $identity_keys as $key ) {
			if ( ! isset( $context[ $key ] ) ) {
				continue;
			}

			$value = (string) $context[ $key ];

			if ( $value === '' || in_array( $value, $own, true ) ) {
				continue;
			}

			$secrets[] = $value;
		}

		$secrets = array_unique( $secrets );

		// Replace longest values first so overlapping substrings redact cleanly.
		usort(
			$secrets,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		foreach ( $secrets as $secret ) {
			$message = str_ireplace( $secret, '[redacted]', $message );
		}

		return $message;
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
