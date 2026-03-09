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
		// Core provides "View user profile". Add-ons use the filter to add
		// links like "View all user activity".
		$actions = [
			[
				'key'   => 'view_profile',
				'label' => __( 'View user profile', 'simple-history' ),
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
		 *         'label' => __( 'View all user activity', 'my-plugin' ),
		 *         'url'   => admin_url( '...' ),
		 *     ];
		 *
		 * @since 5.24.0
		 *
		 * @param array    $actions Array of action link items.
		 * @param \WP_User $user    The WordPress user object.
		 */
		$data['actions'] = apply_filters( 'simple_history/user_card/actions', $actions, $user );

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

		// Build stats details for this initiator type.
		$details = [
			[
				'key'   => 'events_today',
				'label' => __( 'Today', 'simple-history' ),
				'value' => self::get_initiator_event_count( $type, 1 ),
				'type'  => 'stat',
			],
			[
				'key'   => 'events_7_days',
				'label' => __( 'Last 7 days', 'simple-history' ),
				'value' => self::get_initiator_event_count( $type, 7 ),
				'type'  => 'stat',
			],
			[
				'key'   => 'events_total',
				'label' => __( 'Total', 'simple-history' ),
				'value' => self::get_initiator_total_event_count( $type ),
				'type'  => 'stat',
			],
		];

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
			'details'            => $details,
			'actions'            => $actions,
		];

		return rest_ensure_response( $data );
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
	 * Get the number of events for a non-user initiator within a given number of days.
	 *
	 * @param string $initiator_type Initiator type (wp, wp_cli, web_user, other).
	 * @param int    $period_days    Number of days to look back (including today).
	 * @return int Number of events found.
	 */
	public static function get_initiator_event_count( $initiator_type, $period_days ) {
		$log_query = new Log_Query();

		$date_from = Date_Helper::get_last_n_days_start_timestamp( $period_days );

		$query_result = $log_query->query(
			[
				'initiator'      => $initiator_type,
				'posts_per_page' => 1,
				'date_from'      => $date_from,
				'ungrouped'      => true,
			]
		);

		return $query_result['total_row_count'] ?? 0;
	}

	/**
	 * Get the total number of logged events for a non-user initiator (all time).
	 *
	 * @param string $initiator_type Initiator type (wp, wp_cli, web_user, other).
	 * @return int Number of events found.
	 */
	public static function get_initiator_total_event_count( $initiator_type ) {
		$log_query = new Log_Query();

		$query_result = $log_query->query(
			[
				'initiator'      => $initiator_type,
				'posts_per_page' => 1,
				'ungrouped'      => true,
			]
		);

		return $query_result['total_row_count'] ?? 0;
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

		return get_date_from_gmt( $events[0]->date );
	}
}
