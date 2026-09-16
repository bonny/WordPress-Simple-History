<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\WP_REST_Events_Controller;
use Simple_History\WP_REST_SearchOptions_Controller;
use Simple_History\WP_REST_Stats_Controller;
use Simple_History\WP_REST_Support_Info_Controller;
use Simple_History\WP_REST_User_Card_Controller;
use Simple_History\WP_REST_Devtools_Controller;
use WP_REST_Server;

/**
 * Load the Simple History REST API.
 */
class REST_API extends Service {
	/**
	 * User meta key holding the user's chosen event log view, "detailed" or "compact".
	 */
	const EVENTS_VIEW_USER_META_KEY = 'simple_history_events_view';

	/** @inheritDoc */
	public function loaded() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// On init, not rest_api_init, so the registered default also applies
		// when the events page reads the value outside a REST request.
		add_action( 'init', [ $this, 'register_user_meta' ] );
	}

	/**
	 * Register the user meta that stores the event log view.
	 *
	 * Not exposed in REST (no `show_in_rest`, no `auth_callback`): it used to
	 * be readable/writable through /wp/v2/users, but that also let it be
	 * saved through /wp/v2/users/me, and that endpoint always runs
	 * wp_update_user(), firing `profile_update` on every toggle. Third-party
	 * plugins act on that hook, so switching the view logged unrelated
	 * "profile updated" activity. The value is now saved through the
	 * dedicated /simple-history/v1/events-view route instead; see
	 * register_routes() and save_events_view().
	 */
	public function register_user_meta() {
		register_meta(
			'user',
			self::EVENTS_VIEW_USER_META_KEY,
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => 'detailed',
				// Collapse anything but the two known values to the default,
				// since the value no longer goes through a REST enum check.
				'sanitize_callback' => function ( $value ) {
					return $value === 'compact' ? 'compact' : 'detailed';
				},
			]
		);
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {
		$rest_api_controller = new WP_REST_Events_Controller();
		$rest_api_controller->register_routes();

		$search_options_controller = new WP_REST_SearchOptions_Controller();
		$search_options_controller->register_routes();

		$stats_controller = new WP_REST_Stats_Controller();
		$stats_controller->register_routes();

		$support_info_controller = new WP_REST_Support_Info_Controller();
		$support_info_controller->register_routes();

		$user_card_controller = new WP_REST_User_Card_Controller();
		$user_card_controller->register_routes();

		// A dedicated route instead of the core /wp/v2/users/me endpoint —
		// see register_user_meta() for why.
		register_rest_route(
			'simple-history/v1',
			'/events-view',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'save_events_view' ],
				'permission_callback' => [ $this, 'events_view_permissions_check' ],
				'args'                => [
					'view' => [
						'description' => __( 'The event log view to remember for the current user.', 'simple-history' ),
						'type'        => 'string',
						'required'    => true,
						'enum'        => [ 'detailed', 'compact' ],
					],
				],
			]
		);

		// Only register dev tools routes when dev mode is enabled.
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return;
		}

		$dev_tools_controller = new WP_REST_Devtools_Controller();
		$dev_tools_controller->register_routes();
	}

	/**
	 * Permission check for POST /simple-history/v1/events-view.
	 *
	 * The toggle only appears on the history page, so require the same
	 * capability that page requires. Returning false here also fails
	 * logged-out requests (they get a 401, a logged-in user without the
	 * capability gets a 403 — both from WP_REST_Server's own handling of a
	 * false permission callback).
	 *
	 * @return bool
	 */
	public function events_view_permissions_check() {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_history_capability().
		return current_user_can( Helpers::get_view_history_capability() );
	}

	/**
	 * Save the current user's chosen event log view.
	 *
	 * No user id parameter: this can only ever write the current user's own
	 * meta, unlike /wp/v2/users/<id>.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function save_events_view( $request ) {
		$view = $request->get_param( 'view' );

		update_user_meta( get_current_user_id(), self::EVENTS_VIEW_USER_META_KEY, $view );

		return rest_ensure_response( [ 'view' => $view ] );
	}
}
