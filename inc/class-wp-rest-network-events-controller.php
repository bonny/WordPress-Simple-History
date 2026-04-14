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

		// Single event endpoint for the event modal.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				'args'   => [
					'id' => [
						'description' => __( 'Unique identifier for the event.', 'simple-history' ),
						'type'        => 'integer',
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
					'args'                => [
						'context' => $this->get_context_param( [ 'default' => 'view' ] ),
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// POST /network/events/{id}/stick.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/stick',
			[
				'args' => [
					'id' => [
						'description' => __( 'Unique identifier for the event.', 'simple-history' ),
						'type'        => 'integer',
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'stick_event' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);

		// POST /network/events/{id}/unstick.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/unstick',
			[
				'args' => [
					'id' => [
						'description' => __( 'Unique identifier for the event.', 'simple-history' ),
						'type'        => 'integer',
					],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'unstick_event' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);

		$reaction_type_arg = [
			'description' => __( 'Reaction type, e.g. "thumbsup".', 'simple-history' ),
			'type'        => 'string',
			'required'    => true,
			'enum'        => $this->get_allowed_reaction_types(),
		];

		// POST /network/events/{id}/react.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/react',
			[
				'args' => [
					'id'   => [
						'description' => __( 'Unique identifier for the event.', 'simple-history' ),
						'type'        => 'integer',
					],
					'type' => $reaction_type_arg,
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'react_to_event' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);

		// POST /network/events/{id}/unreact.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/unreact',
			[
				'args' => [
					'id'   => [
						'description' => __( 'Unique identifier for the event.', 'simple-history' ),
						'type'        => 'integer',
					],
					'type' => $reaction_type_arg,
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'unreact_to_event' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Check permissions for single event — same rule as collection.
	 *
	 * @param \WP_REST_Request $request Full request.
	 * @return true|\WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
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

	/**
	 * Create an Event instance scoped to the network tables.
	 *
	 * @since 5.6.0
	 * @param int $event_id Event ID.
	 * @return Event
	 */
	protected function create_event( $event_id ) {
		return new Event( $event_id, true );
	}
}
