<?php

namespace Simple_History;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * REST API controller for user card data.
 *
 * Provides user identity info for the avatar/name popover card.
 * The response is structured with filterable `details` and `actions` arrays
 * so add-ons can extend the card content.
 */
class WP_REST_User_Card_Controller extends WP_REST_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'simple-history/v1';
		$this->rest_base = 'users';
	}

	/**
	 * Valid non-user initiator types for the initiator card endpoint.
	 *
	 * @var array<string>
	 */
	const INITIATOR_TYPES = [ 'wp', 'wp_cli', 'web_user', 'other' ];

	/**
	 * Register the routes for user card data.
	 */
	public function register_routes() {
		// GET /wp-json/simple-history/v1/users/<id>/card.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/card',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_user_card' ],
					'permission_callback' => [ $this, 'get_user_card_permissions_check' ],
					'args'                => [
						'id' => [
							'description'       => __( 'WordPress user ID.', 'simple-history' ),
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// GET /wp-json/simple-history/v1/initiators/<type>/card.
		register_rest_route(
			$this->namespace,
			'/initiators/(?P<type>[a-z_]+)/card',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_initiator_card' ],
					'permission_callback' => [ $this, 'get_user_card_permissions_check' ],
					'args'                => [
						'type' => [
							'description'       => __( 'Initiator type (wp, wp_cli, web_user, other).', 'simple-history' ),
							'type'              => 'string',
							'required'          => true,
							'enum'              => self::INITIATOR_TYPES,
							'sanitize_callback' => 'sanitize_key',
						],
					],
				],
			]
		);
	}

	/**
	 * Checks if the current user has permission to view user card data.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_user_card_permissions_check( $request ) {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_history_capability().
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to view user card data.', 'simple-history' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get user card data.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_user_card( $request ) {
		$user_id = $request->get_param( 'id' );
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error(
				'rest_user_not_found',
				__( 'User not found.', 'simple-history' ),
				array( 'status' => 404 )
			);
		}

		$avatar_data = get_avatar_data( $user_id, [ 'size' => 96 ] );

		// Core identity fields.
		$data = [
			'user_id'            => $user->ID,
			'display_name'       => $user->display_name,
			'user_login'         => $user->user_login,
			'user_email'         => $user->user_email,
			'avatar_url'         => $avatar_data['url'] ?? '',
			'profile_url'        => get_edit_user_link( $user->ID ),
			'roles'              => array_values( $user->roles ),
			'has_premium_add_on' => Helpers::is_premium_add_on_active(),
		];

		// Details: key-value items shown below identity info.
		// Each item: [ 'key' => string, 'label' => string, 'value' => string, 'type' => string ].
		// Core provides no details; add-ons use the filter to add items like
		// last login time, last activity, login count, IP address, etc.
		// Supported types: 'text' (default), 'date' (rendered as relative time on the frontend).
		$details = [];

		/**
		 * Filters the user card detail items.
		 *
		 * Add-ons can add detail items shown in the user card popover.
		 * Each item should have: key (string), label (string), value (string), and optionally type (string).
		 *
		 * Example — adding last login time:
		 *     $details[] = [
		 *         'key'   => 'last_login',
		 *         'label' => __( 'Logged in', 'simple-history' ),
		 *         'value' => '2026-03-01 14:30:00', // Local time.
		 *         'type'  => 'date',
		 *     ];
		 *
		 * @since 5.24.0
		 *
		 * @param array    $details Array of detail items.
		 * @param \WP_User $user    The WordPress user object.
		 */
		$data['details'] = apply_filters( 'simple_history/user_card/details', $details, $user );

		// Actions: links shown in the actions section of the card.
		// Each item: [ 'key' => string, 'label' => string, 'url' => string ].
		// Core ships "User profile" — a basic WP utility, not premium content.
		// Add-ons use the filter below to add premium-specific links like
		// "View all user activity". Visual separation between the upsell
		// teaser block (cream) and the actions section is what makes the
		// mixed-tier nature read clearly to free users.
		$actions = [
			[
				'key'   => 'view_profile',
				'label' => __( 'User profile', 'simple-history' ),
				'url'   => get_edit_user_link( $user->ID ),
			],
		];

		/**
		 * Filters the user card action links.
		 *
		 * Add-ons can add action links shown in the user card popover.
		 * Each item should have: key (string), label (string), url (string).
		 *
		 * Example — adding an activity filter link:
		 *     $actions[] = [
		 *         'key'   => 'view_activity',
		 *         'label' => __( 'All user activity', 'my-plugin' ),
		 *         'url'   => admin_url( '...' ),
		 *     ];
		 *
		 * @since 5.24.0
		 *
		 * @param array    $actions Array of action link items.
		 * @param \WP_User $user    The WordPress user object.
		 */
		$filtered_actions = apply_filters( 'simple_history/user_card/actions', $actions, $user );

		$data['details'] = self::sanitize_filter_array( $data['details'], true );
		$data['actions'] = self::sanitize_filter_array( $filtered_actions, false );

		return rest_ensure_response( $data );
	}

	/**
	 * Get card data for a non-user initiator type.
	 *
	 * Returns a filterable response so add-ons can extend it
	 * (e.g., adding "View all activity" action links).
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_initiator_card( $request ) {
		$type = $request->get_param( 'type' );

		$actions = [];

		/**
		 * Filters the initiator card action links.
		 *
		 * Add-ons can add action links shown in the non-user initiator card popover.
		 * Each item should have: key (string), label (string), url (string).
		 *
		 * @since 5.24.0
		 *
		 * @param array  $actions Array of action link items.
		 * @param string $type    The initiator type (wp, wp_cli, web_user, other).
		 */
		$actions = apply_filters( 'simple_history/initiator_card/actions', $actions, $type );

		// Details: key-value items shown below identity info.
		// Core provides no details; other plugins and add-ons (including
		// the premium add-on) can use the filter below to populate items
		// like Today / Last 7 days / Total event counts. This keeps the
		// initiator card aligned with the user card, where event stats
		// are added via a filter rather than shipped in core.
		$details = [];

		/**
		 * Filters the initiator card detail items.
		 *
		 * Add-ons can add detail items shown in the non-user initiator card popover.
		 * Each item should have: key (string), label (string), value (string|int),
		 * and optionally type (string: 'text', 'date', or 'stat').
		 *
		 * @since 5.25.0
		 *
		 * @param array  $details Array of detail items.
		 * @param string $type    The initiator type (wp, wp_cli, web_user, other).
		 */
		$details = apply_filters( 'simple_history/initiator_card/details', $details, $type );

		$data = [
			'initiator'          => $type,
			'has_premium_add_on' => Helpers::is_premium_add_on_active(),
			'details'            => self::sanitize_filter_array( $details, true ),
			'actions'            => self::sanitize_filter_array( $actions, false ),
		];

		return rest_ensure_response( $data );
	}

	/**
	 * Ensure a filtered array is actually an array, optionally deduplicating
	 * entries by `key` so a misbehaving filter (returns null, an object, a
	 * string) can't crash the React consumer and so two plugins pushing the
	 * same `key` don't trigger React's duplicate-key warning. First-write
	 * wins on dedup — third-party filters running at lower priorities can
	 * reserve a key before premium appends.
	 *
	 * @param mixed $value      Whatever the filter returned.
	 * @param bool  $dedup_keys Whether to deduplicate entries by their `key` field.
	 * @return array
	 */
	private static function sanitize_filter_array( $value, $dedup_keys ) {
		if ( ! is_array( $value ) ) {
			return [];
		}

		if ( ! $dedup_keys ) {
			return array_values( $value );
		}

		$seen = [];
		$out  = [];

		foreach ( $value as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['key'] ) ) {
				continue;
			}

			if ( isset( $seen[ $item['key'] ] ) ) {
				continue;
			}

			$seen[ $item['key'] ] = true;
			$out[]                = $item;
		}

		return $out;
	}

	/**
	 * Get the last login date for a user from Simple History logs.
	 *
	 * Useful for add-ons that want to show login time in the user card
	 * via the 'simple_history/user_card/details' filter.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null Date string in site local timezone, or null if no login found.
	 */
	public static function get_last_login( $user_id ) {
		return self::get_most_recent_event_date( $user_id, 'SimpleUserLogger:user_logged_in' );
	}

	/**
	 * Get the number of events for a user within a given number of days.
	 *
	 * Useful for add-ons that want to show activity counts in the user card
	 * via the 'simple_history/user_card/details' filter.
	 *
	 * @param int $user_id    WordPress user ID.
	 * @param int $period_days Number of days to look back (including today).
	 * @return int Number of events found.
	 */
	public static function get_user_event_count( $user_id, $period_days ) {
		$log_query = new Log_Query();

		$date_from = Date_Helper::get_last_n_days_start_timestamp( $period_days );

		$query_result = $log_query->query(
			[
				'user'           => $user_id,
				'posts_per_page' => 1,
				'date_from'      => $date_from,
				'ungrouped'      => true,
			]
		);

		return $query_result['total_row_count'] ?? 0;
	}

	/**
	 * Get the total number of logged events for a user (all time).
	 *
	 * @param int $user_id WordPress user ID.
	 * @return int Number of events found.
	 */
	public static function get_user_total_event_count( $user_id ) {
		$log_query = new Log_Query();

		$query_result = $log_query->query(
			[
				'user'           => $user_id,
				'posts_per_page' => 1,
				'ungrouped'      => true,
			]
		);

		return $query_result['total_row_count'] ?? 0;
	}

	/**
	 * Get the most recent event date for a user from Simple History logs.
	 *
	 * Useful for add-ons that want to show last activity in the user card
	 * via the 'simple_history/user_card/details' filter.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null Date string in site local timezone, or null if no events found.
	 */
	public static function get_last_event( $user_id ) {
		return self::get_most_recent_event_date( $user_id );
	}

	/**
	 * Get last-event date plus today / last-7-days / all-time event counts for
	 * a non-user initiator, in a single SQL query.
	 *
	 * Replaces four separate `Log_Query` calls — three of them full
	 * `COUNT(*)` aggregates — with one round trip using conditional
	 * aggregation. Both MySQL/MariaDB and SQLite support this `SUM(CASE
	 * WHEN ...)` pattern, so the helper works regardless of the database
	 * backend Simple History is running on.
	 *
	 * @param string $initiator_type Initiator type (wp, wp_cli, web_user, other).
	 * @return array { last_event: string|null (ISO 8601 UTC), today: int, last_7_days: int, total: int }
	 */
	public static function get_initiator_card_stats( $initiator_type ) {
		global $wpdb;

		$table           = \Simple_History\Simple_History::get_instance()->get_events_table_name();
		$today_start_gmt = gmdate( 'Y-m-d H:i:s', Date_Helper::get_last_n_days_start_timestamp( 1 ) );
		$week_start_gmt  = gmdate( 'Y-m-d H:i:s', Date_Helper::get_last_n_days_start_timestamp( Date_Helper::DAYS_PER_WEEK ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					MAX(date) AS last_event,
					SUM(CASE WHEN date >= %s THEN 1 ELSE 0 END) AS today_count,
					SUM(CASE WHEN date >= %s THEN 1 ELSE 0 END) AS week_count,
					COUNT(*) AS total_count
				FROM {$table}
				WHERE initiator = %s",
				$today_start_gmt,
				$week_start_gmt,
				$initiator_type
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $row ) {
			return [
				'last_event'  => null,
				'today'       => 0,
				'last_7_days' => 0,
				'total'       => 0,
			];
		}

		return [
			'last_event'  => $row['last_event'] ? self::to_iso_utc( $row['last_event'] ) : null,
			'today'       => (int) $row['today_count'],
			'last_7_days' => (int) $row['week_count'],
			'total'       => (int) $row['total_count'],
		];
	}

	/**
	 * Format a user-agent string into a short human-readable label.
	 *
	 * Tiny pattern-matching parser covering the common browsers and OSes seen
	 * in WordPress admin (Chrome / Safari / Firefox / Edge × macOS / Windows /
	 * Linux / iOS / Android). Avoids a composer dependency.
	 *
	 * @param string|null $ua_string Raw user-agent string.
	 * @return string|null Label like "Chrome on macOS", or null if the input is empty.
	 */
	public static function format_user_agent_label( $ua_string ) {
		if ( empty( $ua_string ) ) {
			return null;
		}

		// Order matters: Edge UA contains "Chrome", Chrome UA contains "Safari".
		$browser = null;
		if ( preg_match( '#\bEdg[e]?/#', $ua_string ) ) {
			$browser = 'Edge';
		} elseif ( strpos( $ua_string, 'Firefox/' ) !== false ) {
			$browser = 'Firefox';
		} elseif ( strpos( $ua_string, 'Chrome/' ) !== false ) {
			$browser = 'Chrome';
		} elseif ( strpos( $ua_string, 'Safari/' ) !== false ) {
			$browser = 'Safari';
		}

		$os = null;
		if ( strpos( $ua_string, 'iPhone' ) !== false ) {
			$os = 'iPhone';
		} elseif ( strpos( $ua_string, 'iPad' ) !== false ) {
			$os = 'iPad';
		} elseif ( strpos( $ua_string, 'Android' ) !== false ) {
			$os = 'Android';
		} elseif ( strpos( $ua_string, 'Mac OS X' ) !== false || strpos( $ua_string, 'Macintosh' ) !== false ) {
			$os = 'macOS';
		} elseif ( strpos( $ua_string, 'Windows' ) !== false ) {
			$os = 'Windows';
		} elseif ( strpos( $ua_string, 'Linux' ) !== false ) {
			$os = 'Linux';
		}

		if ( $browser && $os ) {
			// U+00A0 NBSP between words keeps the label as one unbreakable
			// unit so line-wrapping happens at outer separators only.
			return sprintf( "%s\xC2\xA0on\xC2\xA0%s", $browser, $os );
		}

		return $browser ?? $os;
	}

	/**
	 * Get date + IP + user-agent for the user's most recent login event in a
	 * single query.
	 *
	 * Faster path for callers that need all three (the premium user-card
	 * module is the main one). Pulls the date, IP, and user-agent from the
	 * same login event, so the three always describe the same session.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|null { date: string, ip: string|null, user_agent: string|null }
	 *                   or null if no login event was found.
	 */
	public static function get_last_login_session( $user_id ) {
		$log_query = new Log_Query();

		$query_result = $log_query->query(
			[
				'user'             => $user_id,
				'messages'         => 'SimpleUserLogger:user_logged_in',
				'posts_per_page'   => 1,
				'skip_count_query' => true,
				'ungrouped'        => true,
			]
		);

		$events = $query_result['log_rows'] ?? [];

		if ( empty( $events ) ) {
			return null;
		}

		$event = $events[0];

		return [
			'date'       => self::to_iso_utc( $event->date ),
			'ip'         => $event->context['_server_remote_addr'] ?? null,
			'user_agent' => $event->context['server_http_user_agent'] ?? null,
		];
	}

	/**
	 * Get the most recent event date for a user, optionally filtered by message type.
	 *
	 * @param int         $user_id  WordPress user ID.
	 * @param string|null $messages Optional message filter (e.g. 'SimpleUserLogger:user_logged_in').
	 * @return string|null Date string in site local timezone, or null if no events found.
	 */
	private static function get_most_recent_event_date( $user_id, $messages = null ) {
		$log_query = new Log_Query();

		$args = [
			'user'             => $user_id,
			'posts_per_page'   => 1,
			'skip_count_query' => true,
			'ungrouped'        => true,
		];

		if ( $messages !== null ) {
			$args['messages'] = $messages;
		}

		$query_result = $log_query->query( $args );
		$events       = $query_result['log_rows'] ?? [];

		if ( empty( $events ) ) {
			return null;
		}

		return self::to_iso_utc( $events[0]->date );
	}

	/**
	 * Convert a database datetime string (stored as GMT, no TZ marker) into
	 * an ISO-8601 string with an explicit UTC offset.
	 *
	 * The React side renders these via `humanTimeDiff()`, which delegates to
	 * `moment()`. A naked "Y-m-d H:i:s" is parsed in the *browser's* local
	 * timezone, so admin browsers running in a different TZ than the site
	 * would see relative times skewed by the offset (e.g. "9 hours ago" for
	 * an event that just happened). Returning an offset-marked ISO string
	 * makes the parse unambiguous everywhere.
	 *
	 * @param string $db_date Date string from the `simple_history` table.
	 * @return string ISO-8601 datetime with `+00:00` offset.
	 */
	private static function to_iso_utc( $db_date ) {
		return gmdate( 'c', strtotime( $db_date . ' UTC' ) );
	}
}
