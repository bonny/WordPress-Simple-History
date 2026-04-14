<?php

namespace Simple_History;

use WP_REST_Server;

/**
 * REST API controller for user / initiator cards rendered on the Network
 * Admin Simple History page.
 *
 * Inherits the response shape and filters from WP_REST_User_Card_Controller
 * but scopes the activity counters (today / 7 days / total) to the network
 * event tables. The WP user data itself is global across a network, so the
 * identity section doesn't need any change.
 *
 * @since 5.6.0
 */
class WP_REST_Network_User_Card_Controller extends WP_REST_User_Card_Controller {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->rest_base = 'network/users';
	}

	/**
	 * Register the network card routes. Duplicates the parent shape but
	 * scopes the URL path so add-ons targeting the site endpoint stay
	 * untouched.
	 */
	public function register_routes() {
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

		register_rest_route(
			$this->namespace,
			'/network/initiators/(?P<type>[a-z_]+)/card',
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
	 * @inheritDoc
	 */
	protected function is_network_scope() {
		return true;
	}

	/**
	 * Viewing network user cards requires super admin.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error
	 */
	public function get_user_card_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_network' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to view network user cards.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}
}
