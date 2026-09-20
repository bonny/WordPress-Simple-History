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
	 * User meta key holding the user's chosen event log view, "detailed", "compact" or "table".
	 */
	const EVENTS_VIEW_USER_META_KEY = 'simple_history_events_view';

	/**
	 * User meta key holding the views the user wants the page sidebar hidden in.
	 *
	 * A comma-separated list of view names rather than one boolean, because
	 * the three views want different things: a table can use the sidebar's
	 * width for columns, while the detailed list is a column of text that
	 * gains nothing from being wider. Stored per view so hiding it in one
	 * does not quietly hide it in the others.
	 *
	 * Stored as a string, not an array: a scalar meta needs no serialization
	 * and cannot come back from the database as something unexpected.
	 */
	const HIDDEN_SIDEBAR_VIEWS_USER_META_KEY = 'simple_history_hidden_sidebar_views';

	/**
	 * The views the event log page offers.
	 */
	const EVENTS_VIEWS = [ 'detailed', 'compact', 'table' ];

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
			self::HIDDEN_SIDEBAR_VIEWS_USER_META_KEY,
			[
				'type'              => 'string',
				'single'            => true,
				// The table view starts without the sidebar and the other two
				// start with it. Measured at 1280px: with the sidebar in place
				// the table overflows its column by 69px, and without it the
				// Message column goes from 304px to 484. The detailed and
				// compact lists have no such problem, so nothing is taken away
				// from anyone who has not asked.
				'default'           => 'table',
				'sanitize_callback' => function ( $value ) {
					$views = is_string( $value ) ? explode( ',', $value ) : [];
					$views = array_intersect( $views, self::EVENTS_VIEWS );

					// Sorted and de-duplicated so the same set of views always
					// stores as the same string, whatever order it arrived in.
					$views = array_unique( $views );
					sort( $views );

					return implode( ',', $views );
				},
			]
		);

		register_meta(
			'user',
			self::EVENTS_VIEW_USER_META_KEY,
			[
				'type'              => 'string',
				'single'            => true,
				'default'           => 'detailed',
				// Collapse anything but the known values to the default,
				// since the value no longer goes through a REST enum check.
				'sanitize_callback' => function ( $value ) {
					return in_array( $value, [ 'compact', 'table' ], true )
						? $value
						: 'detailed';
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
						'enum'        => [ 'detailed', 'compact', 'table' ],
					],
				],
			]
		);

		// The whole set in one call, rather than one view at a time. Writing
		// the set is idempotent, so a request that arrives twice, or out of
		// order with another, still leaves the stored value describing what
		// the reader last saw.
		register_rest_route(
			'simple-history/v1',
			'/sidebar-visibility',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'save_sidebar_visibility' ],
				'permission_callback' => [ $this, 'events_view_permissions_check' ],
				'args'                => [
					'views' => [
						'description' => __( 'The event log views to hide the page sidebar in.', 'simple-history' ),
						'type'        => 'array',
						'required'    => true,
						'items'       => [
							'type' => 'string',
							'enum' => self::EVENTS_VIEWS,
						],
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

	/**
	 * Save the views the current user wants the page sidebar hidden in.
	 *
	 * No user id parameter, for the same reason save_events_view() has none:
	 * this can only ever write the current user's own meta.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response
	 */
	public function save_sidebar_visibility( $request ) {
		$views = (array) $request->get_param( 'views' );

		// The registered sanitize_callback does the filtering, so whatever
		// survived the route's own enum check is narrowed again on the way in.
		update_user_meta(
			get_current_user_id(),
			self::HIDDEN_SIDEBAR_VIEWS_USER_META_KEY,
			implode( ',', $views )
		);

		return rest_ensure_response( [ 'views' => $views ] );
	}
}
