<?php

namespace Simple_History;

/**
 * REST API controller for network-level stats.
 *
 * Inherits all route registrations from WP_REST_Stats_Controller but scopes
 * them under /simple-history/v1/network/stats and computes every statistic
 * against the base_prefix network tables via an Events_Stats built with the
 * network flag.
 *
 * @since 5.6.0
 */
class WP_REST_Network_Stats_Controller extends WP_REST_Stats_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->rest_base = 'network/stats';
	}

	/**
	 * Return an Events_Stats configured for the network tables.
	 *
	 * @since 5.6.0
	 * @return Events_Stats
	 */
	protected function create_events_stats() {
		return new Events_Stats( true );
	}

	/**
	 * Network stats require manage_network.
	 *
	 * @param \WP_REST_Request $request Full request.
	 * @return true|\WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view network stats.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}
}
