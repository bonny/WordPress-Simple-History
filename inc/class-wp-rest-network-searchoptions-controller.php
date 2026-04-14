<?php

namespace Simple_History;

use WP_REST_Server;

/**
 * REST API controller for search options on the Network Admin page.
 *
 * Inherits the full response shape from WP_REST_SearchOptions_Controller
 * but scopes the dates/stats fields to the network-level tables so the
 * "By month" date filter shows months with network events (not site
 * events) and the stats counters reflect network activity.
 *
 * @since 5.6.0
 */
class WP_REST_Network_SearchOptions_Controller extends WP_REST_SearchOptions_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->rest_base = 'network/search-options';
	}

	/**
	 * Register routes. We don't mirror /search-user on the network side —
	 * user search is not surfaced on the network admin UI yet.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function is_network_scope() {
		return true;
	}

	/**
	 * Requires super admin.
	 *
	 * @param \WP_REST_Request $request Full request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view network search options.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}
}
