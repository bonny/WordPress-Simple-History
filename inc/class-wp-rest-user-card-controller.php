<?php

namespace Simple_History;

use Simple_History\Services\AddOns_Licences;
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
	 * Simple History instance.
	 *
	 * @var Simple_History
	 */
	protected Simple_History $simple_history;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace      = 'simple-history/v1';
		$this->rest_base      = 'users';
		$this->simple_history = Simple_History::get_instance();
	}

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

		$avatar_data  = get_avatar_data( $user_id, [ 'size' => 96 ] );
		$last_login   = $this->get_last_login( $user_id );
		$last_event   = $this->get_last_event( $user_id );

		/** @var AddOns_Licences|null */
		$addons_service = $this->simple_history->get_service( AddOns_Licences::class );
		$has_premium    = $addons_service instanceof AddOns_Licences && $addons_service->has_add_on( 'simple-history-premium' );

		// Core identity fields.
		$data = [
			'user_id'            => $user->ID,
			'display_name'       => $user->display_name,
			'user_login'         => $user->user_login,
			'user_email'         => $user->user_email,
			'avatar_url'         => $avatar_data['url'] ?? '',
			'profile_url'        => get_edit_user_link( $user->ID ),
			'roles'              => array_values( $user->roles ),
			'has_premium_add_on' => $has_premium,
		];

		// Details: key-value items shown below identity info.
		// Each item: [ 'key' => string, 'label' => string, 'value' => string ].
		$details = [];

		if ( $last_login ) {
			$details[] = [
				'key'   => 'last_login',
				'label' => __( 'Logged in', 'simple-history' ),
				'value' => $last_login,
				'type'  => 'date',
			];
		}

		if ( $last_event ) {
			$details[] = [
				'key'   => 'last_event',
				'label' => __( 'Last activity', 'simple-history' ),
				'value' => $last_event,
				'type'  => 'date',
			];
		}

		/**
		 * Filters the user card detail items.
		 *
		 * Add-ons can add, remove, or modify detail items shown in the user card popover.
		 * Each item should have: key (string), label (string), value (string), and optionally type (string).
		 * Supported types: 'text' (default), 'date' (rendered as relative time).
		 *
		 * @since 5.24.0
		 *
		 * @param array    $details Array of detail items.
		 * @param \WP_User $user    The WordPress user object.
		 */
		$data['details'] = apply_filters( 'simple_history/user_card/details', $details, $user );

		// Actions: links shown in the actions section of the card.
		// Each item: [ 'key' => string, 'label' => string, 'url' => string ].
		$actions = [
			[
				'key'   => 'view_profile',
				'label' => __( 'View user profile', 'simple-history' ),
				'url'   => get_edit_user_link( $user->ID ),
			],
			[
				'key'   => 'view_activity',
				'label' => __( 'View all user activity', 'simple-history' ),
				'url'   => Helpers::get_history_admin_url() . '&users=' . rawurlencode(
					wp_json_encode(
						[
							[
								'id'    => $user->ID,
								'value' => $user->user_email,
							],
						]
					)
				),
			],
		];

		/**
		 * Filters the user card action links.
		 *
		 * Add-ons can add, remove, or modify action links shown in the user card popover.
		 * Each item should have: key (string), label (string), url (string).
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
	 * Get the last login date for a user from Simple History logs.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null Date string in site local timezone, or null if no login found.
	 */
	private function get_last_login( $user_id ) {
		$log_query = new Log_Query();

		// Cannot use 'ungrouped' here because the simple query path
		// does not apply the 'messages' filter (it only uses inner_where).
		$query_result = $log_query->query(
			[
				'messages'         => 'SimpleUserLogger:user_logged_in',
				'user'             => $user_id,
				'posts_per_page'   => 1,
				'skip_count_query' => true,
			]
		);

		$events = $query_result['log_rows'] ?? [];

		if ( empty( $events ) ) {
			return null;
		}

		// Return local time to match how humanTimeDiff is used
		// elsewhere in the frontend (e.g. EventDate uses date_local).
		return get_date_from_gmt( $events[0]->date );
	}

	/**
	 * Get the most recent event date for a user from Simple History logs.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null Date string in site local timezone, or null if no events found.
	 */
	private function get_last_event( $user_id ) {
		$log_query = new Log_Query();

		$query_result = $log_query->query(
			[
				'user'             => $user_id,
				'posts_per_page'   => 1,
				'skip_count_query' => true,
				'ungrouped'        => true,
			]
		);

		$events = $query_result['log_rows'] ?? [];

		if ( empty( $events ) ) {
			return null;
		}

		return get_date_from_gmt( $events[0]->date );
	}
}
