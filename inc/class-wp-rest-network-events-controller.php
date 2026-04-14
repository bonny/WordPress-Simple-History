<?php

namespace Simple_History;

use WP_REST_Server;

/**
 * REST API controller for network events.
 *
 * Extends the main events controller but queries network tables
 * (base_prefix) instead of site tables (prefix).
 *
 * @since 5.6.0
 */
class WP_REST_Network_Events_Controller extends WP_REST_Events_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->namespace = 'simple-history/v1';
		$this->rest_base = 'network/events';
	}

	/**
	 * Register routes.
	 *
	 * Only registers the GET collection route — network events are read-only for now.
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
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Check permissions — requires manage_network capability.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has permission, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view network events.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Create a Log_Query configured for network tables.
	 *
	 * @since 5.6.0
	 * @return Log_Query
	 */
	protected function create_log_query() {
		return ( new Log_Query() )->set_network_query();
	}
}
