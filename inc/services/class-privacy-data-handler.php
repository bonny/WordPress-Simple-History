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
 * The exporter is always registered and always exports the user's own
 * (initiator) events. Two parts are gated behind experimental features for one
 * release cycle (see the design spec, "Release & lifecycle"): the eraser, and
 * the export of subject events (activity about the user performed by others,
 * which carries the third-party redaction surface).
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
	 * Memoized subject context keys (the result of the filterable map), so the
	 * filter runs once per request instead of once per exported subject event.
	 *
	 * @var array{id:string[],login:string[],email:string[]}|null
	 */
	private $subject_context_keys_cache = null;

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
	 * (full detail, always on) plus — when experimental features are enabled —
	 * events where they are the subject of someone else's action (third-party
	 * identity redacted, per GDPR Art. 15(4)).
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

		// Resolve only this page's event ids (and each one's role) directly in
		// SQL, so we don't recompute and load the user's entire id set into
		// memory on every page WordPress requests.
		$page_result = $this->get_event_id_page( $user, $page );
		$roles       = $page_result['roles'];

		$export_items = array();

		if ( ! empty( $roles ) ) {
			$page_ids = array_keys( $roles );
			$rows     = $this->get_event_rows_by_ids( $page_ids );

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
			'done' => $page_result['done'],
		);
	}

	/**
	 * Resolve one page of the user's event ids — initiator and subject combined,
	 * de-duplicated (initiator wins), newest first — paginated entirely in SQL.
	 *
	 * @param \WP_User $user The requester.
	 * @param int      $page 1-based page number.
	 * @return array{roles:array<int,string>,done:bool} id => 'initiator'|'subject', and whether this is the last page.
	 */
	private function get_event_id_page( $user, $page ) {
		global $wpdb;

		$contexts_table = \Simple_History\Simple_History::get_instance()->get_contexts_table_name();

		$page_num = max( 1, (int) $page );
		$offset   = ( $page_num - 1 ) * self::PAGE_SIZE;

		// Initiator arm: events the user performed (is_init = 1).
		$union  = "SELECT history_id, 1 AS is_init FROM {$contexts_table} WHERE `key` = %s AND value = %s";
		$params = array( '_user_id', (string) $user->ID );

		// Subject arm(s): events performed on the user (is_init = 0).
		//
		// Subject events (activity ABOUT the user, performed by others) are matched
		// only when experimental features are enabled. Initiator events — the
		// user's own activity, their own data with no third-party leak surface —
		// are always exported. This keeps the safe compliance win always-on while
		// the third-party redaction path gets more real-world testing behind the
		// flag, mirroring the eraser's experimental gating.
		$subject_clauses = array();
		$subject_params  = array();

		if ( Helpers::experimental_features_is_enabled() ) {
			list( $subject_clauses, $subject_params ) = $this->build_subject_match( $user );
		}

		if ( ! empty( $subject_clauses ) ) {
			$union .= " UNION ALL SELECT history_id, 0 AS is_init FROM {$contexts_table} WHERE " . implode( ' OR ', $subject_clauses );
			$params = array_merge( $params, $subject_params );
		}

		// MAX(is_init) lets an id that is both initiator and subject collapse to
		// a single initiator row. Fetch one extra row to detect further pages
		// without a separate COUNT query.
		$sql      = "SELECT history_id, MAX(is_init) AS is_init FROM ( {$union} ) AS combined GROUP BY history_id ORDER BY history_id DESC LIMIT %d OFFSET %d";
		$params[] = self::PAGE_SIZE + 1;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		$has_more = count( $results ) > self::PAGE_SIZE;
		$results  = array_slice( $results, 0, self::PAGE_SIZE );

		$roles = array();

		foreach ( $results as $r ) {
			$roles[ (int) $r->history_id ] = (int) $r->is_init === 1 ? 'initiator' : 'subject';
		}

		return array(
			'roles' => $roles,
			'done'  => ! $has_more,
		);
	}

	/**
	 * Build the SQL OR-clauses (and bound params) that match events where the
	 * given user is the subject/target, by id, then login, then email.
	 *
	 * @param \WP_User $user The requester.
	 * @return array{0:string[],1:array<int,string>} [ clauses, params ].
	 */
	private function build_subject_match( $user ) {
		$keys = $this->get_subject_context_keys();

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

		return array( $clauses, $params );
	}

	/**
	 * Context keys that identify a user an event was performed *on* (the
	 * subject/target), grouped by how their value matches the requester.
	 * Filterable so other loggers can register their own subject keys.
	 *
	 * @return array{id:string[],login:string[],email:string[]}
	 */
	private function get_subject_context_keys() {
		if ( $this->subject_context_keys_cache !== null ) {
			return $this->subject_context_keys_cache;
		}

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
		$this->subject_context_keys_cache = apply_filters( 'simple_history/privacy/subject_context_keys', $keys );

		return $this->subject_context_keys_cache;
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
		// Redact third-party identifiers in the CONTEXT before rendering, so the
		// message interpolates "[redacted]" in place of each other person's
		// placeholder. This covers logins, emails AND display names, and avoids
		// corrupting unrelated text (a blind str_ireplace on the rendered string
		// would turn e.g. "latest" into "la[redacted]" for a login "test").
		//
		// IMPORTANT: redaction correctness assumes a logger's plain-text output
		// renders identity ONLY from the (now-redacted) context — i.e. via
		// message placeholders. Core loggers hold to this; the User_Logger
		// override re-fetches the user via get_user_by() but uses only the
		// numeric ID for an edit-link URL (stripped by wp_strip_all_tags below),
		// never the live name. A logger that interpolated a freshly-fetched
		// display name/email instead of the context placeholder would bypass
		// this redaction — don't do that in a logger meant for subject events.
		$redacted_row = $this->redact_third_party_context( $row, $user );

		$message = \Simple_History\Simple_History::get_instance()->get_log_row_plain_text_output( $redacted_row );
		$message = html_entity_decode( wp_strip_all_tags( $message ), ENT_QUOTES );

		// Defence in depth: scrub any third-party email a logger baked literally
		// into the message text. Emails are distinctive enough to replace without
		// corrupting surrounding words.
		$message = $this->redact_literal_emails( $message, $row->context, $user );

		$data   = $this->build_common_fields( $row );
		$data[] = array(
			'name'  => __( 'Action concerning you', 'simple-history' ),
			'value' => $message,
		);

		return array(
			'group_id'    => self::GROUP_ID . '-subject',
			'group_label' => __( 'Simple History — activity concerning you (performed by others)', 'simple-history' ),
			'item_id'     => 'sh-event-' . $row->id,
			'data'        => $data,
		);
	}

	/**
	 * Build the export fields shared by initiator and subject events: the dates,
	 * logger and level. Callers append their own message / PII fields.
	 *
	 * @param object $row Log_Query row object.
	 * @return array<int,array{name:string,value:string}>
	 */
	private function build_common_fields( $row ) {
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
		);
	}

	/**
	 * Return a copy of the row whose context has every third party's identifying
	 * value replaced with "[redacted]", leaving the requester's own identifiers
	 * intact. Redacting at the context level (before interpolation) means only
	 * the actual identity placeholders are masked — surrounding message text is
	 * never corrupted.
	 *
	 * @param object   $row  Log_Query row.
	 * @param \WP_User $user The requester.
	 * @return object Cloned row with a redacted context.
	 */
	private function redact_third_party_context( $row, $user ) {
		$context = is_array( $row->context ) ? $row->context : array();

		$own = array_filter(
			array(
				(string) $user->ID,
				(string) $user->user_login,
				(string) $user->user_email,
				(string) $user->display_name,
				(string) $user->user_nicename,
				(string) $user->first_name,
				(string) $user->last_name,
			),
			static function ( $v ) {
				return $v !== '';
			}
		);

		foreach ( $this->get_identity_context_keys() as $key ) {
			if ( ! isset( $context[ $key ] ) ) {
				continue;
			}

			$value = (string) $context[ $key ];

			if ( $value === '' || in_array( $value, $own, true ) ) {
				continue;
			}

			$context[ $key ] = '[redacted]';
		}

		$redacted_row          = clone $row;
		$redacted_row->context = $context;

		return $redacted_row;
	}

	/**
	 * Context keys whose values name a person (login, email, or display name).
	 * Used to redact third parties from subject-event messages.
	 *
	 * @return string[]
	 */
	private function get_identity_context_keys() {
		$subject = $this->get_subject_context_keys();

		$keys = array_merge(
			array( '_user_login', '_user_email' ),
			$subject['login'],
			$subject['email'],
			array( 'user_display_name', 'display_name', 'first_name', 'last_name', 'created_user_first_name', 'created_user_last_name', 'user_names', 'nickname' )
		);

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Replace any third-party email present in the context (and thus possibly
	 * baked literally into the message) with "[redacted]". The requester's own
	 * email is preserved.
	 *
	 * @param string   $message Rendered message.
	 * @param array    $context Event context (original, un-redacted).
	 * @param \WP_User $user    The requester.
	 * @return string
	 */
	private function redact_literal_emails( $message, $context, $user ) {
		$context   = is_array( $context ) ? $context : array();
		$own_email = strtolower( (string) $user->user_email );

		foreach ( $context as $value ) {
			$value = (string) $value;

			if ( strpos( $value, '@' ) === false || ! is_email( $value ) ) {
				continue;
			}

			if ( strtolower( $value ) === $own_email ) {
				continue;
			}

			$message = str_ireplace( $value, '[redacted]', $message );
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

		$data   = $this->build_common_fields( $row );
		$data[] = array(
			'name'  => __( 'Message', 'simple-history' ),
			'value' => wp_strip_all_tags( $message ),
		);
		$data[] = array(
			'name'  => __( 'IP address', 'simple-history' ),
			'value' => $context['_server_remote_addr'] ?? '',
		);
		$data[] = array(
			'name'  => __( 'User agent', 'simple-history' ),
			'value' => $context['server_http_user_agent'] ?? '',
		);

		return $data;
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
		$current_user_id = get_current_user_id();

		// Attribute to the admin who ran the erasure when there is one; otherwise
		// (wp-cron / async processing with no current user) attribute to
		// WordPress, rather than a phantom "wp_user" with no id.
		if ( $current_user_id > 0 ) {
			$context = array(
				'_initiator' => Log_Initiators::WP_USER,
				'_user_id'   => $current_user_id,
			);
		} else {
			$context = array(
				'_initiator' => Log_Initiators::WORDPRESS,
			);
		}

		SimpleLogger()->info(
			'Anonymized personal data in Simple History for a privacy erasure request',
			$context
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
