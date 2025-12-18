<?php

namespace Simple_History;

use stdClass;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;
use Simple_History\Event;
use Simple_History\Helpers;
use Simple_History\Log_Initiators;

/**
 * REST API controller for events.
 */
class WP_REST_Events_Controller extends WP_REST_Controller {
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
		$this->rest_base      = 'events';
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// GET /wp-json/simple-history/v1/events.
		// To get events.
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
			],
		);

		// POST /wp-json/simple-history/v1/events.
		// To create an event.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => array(
						'message' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => 'Short message to log',
						),
						'note'    => array(
							'type'        => 'string',
							'description' => 'Additional note or details about the event',
						),
						'level'   => array(
							'type'        => 'string',
							'enum'        => array( 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ),
							'default'     => 'info',
							'description' => 'Log level',
						),
						'date'    => array(
							'type'        => 'string',
							'format'      => 'date-time',
							'description' => 'Date and time for the event in MySQL datetime format (Y-m-d H:i:s). If not provided, current time will be used.',
						),
					),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			],
		);

		// GET /wp-json/simple-history/v1/events/has-updates.
		// Same args as /wp-json/simple-history/v1/events but returns only information
		// if there are new events or not.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/has-updates',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_has_updates' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params_for_has_updates(),
				],
			],
		);

		// GET /wp-json/simple-history/v1/events/<event-id>.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				'args' => [
					'id' => [
						'description' => __( 'Unique identifier for the event.', 'simple-history' ),
						'type'        => 'integer',
					],
				],
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
			],
		);

		// POST /wp-json/simple-history/v1/events/<event-id>/stick.
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
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
				],
			],
		);

		// POST /wp-json/simple-history/v1/events/<event-id>/unstick.
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
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
				],
			],
		);
	}

	/**
	 * Retrieves a single event.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$event = $this->get_single_event( $request['id'] );

		$data     = $this->prepare_item_for_response( $event, $request );
		$response = rest_ensure_response( $data );

		return $response;
	}


	/**
	 * Checks if a given request has access to read a post.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if the request has read access for the item, WP_Error object or false otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to view events.', 'simple-history' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		// Event must exist.
		if ( ! Helpers::event_exists( $request['id'] ) ) {
			return new WP_Error(
				'rest_event_invalid_id',
				__( 'Invalid event ID.', 'simple-history' ),
				array( 'status' => 404 )
			);
		}

		$log_event = $this->get_single_event( $request['id'] );

		if ( $log_event === false ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to view this event.', 'simple-history' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Get a single event using the log query API.
	 *
	 * @param int $event_id Event ID.
	 * @return false|object Event data on success, false on failure.
	 */
	protected function get_single_event( $event_id ) {
		$query_result = ( new Log_Query() )->query(
			[
				'post__in' => [ $event_id ],
			]
		);

		if ( isset( $query_result['log_rows'][0] ) ) {
			return $query_result['log_rows'][0];
		}

		return false;
	}

	/**
	 * Retrieves the query params for the posts collection for has_updates.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params_for_has_updates() {
		$query_params = $this->get_collection_params();

		// Make since_id required.
		$query_params['since_id']['required'] = true;

		return $query_params;
	}

	/**
	 * Retrieves the query params for the posts collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$query_params = parent::get_collection_params();

		$query_params['context']['default'] = 'view';

		$query_params['type'] = array(
			'description' => __( 'Type of result to return.', 'simple-history' ),
			'type'        => 'string',
			'default'     => 'overview',
			'enum'        => array( 'overview', 'occasions' ),
		);

		$query_params['logRowID'] = array(
			'description' => __( 'Limit result set to rows with id lower than this.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['occasionsID'] = array(
			'description' => __( 'Limit result set to rows with occasionsID equal to this.', 'simple-history' ),
			'type'        => 'string',
		);

		$query_params['occasionsCount'] = array(
			'description' => __( 'The number of occasions to get.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['occasionsCountMaxReturn'] = array(
			'description' => __( 'The max number of occasions to return.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['per_page'] = array(
			'description' => __( 'Maximum number of items to be returned in result set.', 'simple-history' ),
			'type'        => 'integer',
			'default'     => 10,
			'minimum'     => 1,
			'maximum'     => 100,
		);

		$query_params['page'] = array(
			'description' => __( 'Current page of the collection.', 'simple-history' ),
			'type'        => 'integer',
			'default'     => 1,
			'minimum'     => 1,
		);

		$query_params['include'] = array(
			'description' => __( 'Limit result set to specific IDs.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
			'default'     => null,
		);

		// If max_id_first_page is then then only include rows
		// with id equal to or earlier than this, i.e. older than this.
		$query_params['max_id_first_page'] = array(
			'description' => __( 'Limit result set to rows with id equal or lower than this.', 'simple-history' ),
			'type'        => 'integer',
		);

		// Add where clause for since_id,
		// to include rows with id greater than since_id, i.e. more recent than since_id.
		$query_params['since_id'] = array(
			'description' => __( 'Limit result set to rows with id greater than this, i.e. more recent than since_id.', 'simple-history' ),
			'type'        => 'integer',
		);

		// Date + ID for accurate new event detection with date ordering.
		$query_params['since_date'] = array(
			'description' => __( 'Limit result set to events with date > since_date OR (date = since_date AND id > since_id). Use together with since_id for accurate new event detection.', 'simple-history' ),
			'type'        => 'string',
			'format'      => 'date-time',
		);

		// Date to in unix timestamp format.
		$query_params['date_from'] = array(
			'description' => __( 'Limit result set to rows with date greater than or equal to this unix timestamp.', 'simple-history' ),
			'type'        => 'string',
		);

		// Date to.
		// If date_to is set it is a timestamp.
		$query_params['date_to'] = array(
			'description' => __( 'Limit result set to rows with date less than or equal to this unix timestamp.', 'simple-history' ),
			'type'        => 'string',
		);

		/**
		 * If "months" they translate to $args["months"] because we already have support for that
		 * can't use months and dates and the same time.
		 *
		 * $arr_dates can be a month:
		 * Array
		 * (
		 *  [0] => month:2021-11
		 * )
		 *
		 * $arr_dates can be a number of days:
		 * Array
		 * (
		 *  [0] => lastdays:7
		 * )
		 *
		 * $arr_dates can be allDates
		 * Array
		 * (
		 *  [0] => allDates
		 * )
		*/
		$query_params['dates'] = array(
			'description' => __( 'Limit result set to rows with date within this range.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		// lastdays = int with number of days back to show the history.
		$query_params['lastdays'] = array(
			'description' => __( 'Limit result set to rows with date within this range.', 'simple-history' ),
			'type'        => 'integer',
		);

		// months in format "Y-m"
		// array or comma separated.
		$query_params['months'] = array(
			'description' => __( 'Limit result set to rows with date within this range. Format: Y-m.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		// log levels to include. comma separated or as array. defaults to all.
		$query_params['loglevels'] = array(
			'description' => __( 'Limit result set to rows with log levels.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		// loggers to include. comma separated. defaults to all the authed user can read.
		$query_params['loggers'] = array(
			'description' => __( 'Limit result set to rows with loggers.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['messages'] = array(
			'description' => __( 'Limit result set to rows with messages. Format: LoggerSlug:message.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		// User ids, comma separated or array.
		$query_params['users'] = array(
			'description' => __( 'Limit result set to rows with user ids.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
		);

		$query_params['user'] = array(
			'description' => __( 'Limit result set to rows with user id.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['offset'] = array(
			'description' => __( 'Offset the result set by a specific number of items.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['include_sticky'] = array(
			'description' => __( 'Include sticky events in the result set.', 'simple-history' ),
			'type'        => 'boolean',
			'default'     => false,
		);

		$query_params['only_sticky'] = array(
			'description' => __( 'Only return sticky events.', 'simple-history' ),
			'type'        => 'boolean',
			'default'     => false,
		);

		$query_params['initiator'] = array(
			'description'       => __( 'Limit result set to events from specific initiator(s).', 'simple-history' ),
			'type'              => array( 'string', 'array' ),
			'items'             => array(
				'type' => 'string',
			),
			'validate_callback' => array( $this, 'validate_initiator_param' ),
			'sanitize_callback' => array( $this, 'sanitize_initiator_param' ),
		);

		$query_params['context_filters'] = array(
			'description'          => __( 'Context filters as key-value pairs to filter events by context data.', 'simple-history' ),
			'type'                 => 'object',
			'additionalProperties' => array(
				'type' => 'string',
			),
		);

		$query_params['ungrouped'] = array(
			'description' => __( 'Return ungrouped events without occasions grouping.', 'simple-history' ),
			'type'        => 'boolean',
			'default'     => false,
		);

		// Surrounding events parameters (admin only).
		$query_params['surrounding_event_id'] = array(
			'description' => __( 'Show events surrounding this event ID. Returns events chronologically before and after the specified event, regardless of other filters. Requires administrator privileges.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['surrounding_count'] = array(
			'description' => __( 'Number of events to show before AND after the surrounding_event_id. Default 5, max 50.', 'simple-history' ),
			'type'        => 'integer',
			'default'     => 5,
			'minimum'     => 1,
			'maximum'     => 50,
		);

		// Exclusion filters - hide events matching these criteria.
		// Note: When both inclusion and exclusion filters are specified for the same field, exclusion takes precedence.
		$query_params['exclude_search'] = array(
			'description' => __( 'Exclude events containing these words. Events matching this search will be hidden.', 'simple-history' ),
			'type'        => 'string',
		);

		$query_params['exclude_loglevels'] = array(
			'description' => __( 'Exclude events with these log levels.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['exclude_loggers'] = array(
			'description' => __( 'Exclude events from these loggers.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['exclude_messages'] = array(
			'description' => __( 'Exclude events with these messages. Format: LoggerSlug:message.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'string',
			),
		);

		$query_params['exclude_users'] = array(
			'description' => __( 'Exclude events from these user IDs.', 'simple-history' ),
			'type'        => 'array',
			'items'       => array(
				'type' => 'integer',
			),
		);

		$query_params['exclude_user'] = array(
			'description' => __( 'Exclude events from this user ID.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['exclude_initiator'] = array(
			'description'       => __( 'Exclude events from specific initiator(s).', 'simple-history' ),
			'type'              => array( 'string', 'array' ),
			'items'             => array(
				'type' => 'string',
			),
			'validate_callback' => array( $this, 'validate_initiator_param' ),
			'sanitize_callback' => array( $this, 'sanitize_initiator_param' ),
		);

		return $query_params;
	}

	/**
	 * Retrieves the post's schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'simple-history-event',
			'type'       => 'object',
			'properties' => array(
				'id'                         => array(
					'description' => __( 'Unique identifier for the event.', 'simple-history' ),
					'type'        => 'integer',
				),
				'type'                       => array(
					'description' => __( 'Type of result to return.', 'simple-history' ),
					'type'        => 'string',
					'enum'        => array( 'overview', 'occasions' ),
				),
				'date_local'                 => array(
					'description' => __( "The date the event was added, in the site's timezone.", 'simple-history' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
				),
				'date_gmt'                   => array(
					'description' => __( 'The date the event was added, as GMT.', 'simple-history' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
				),
				'link'                       => array(
					'description' => __( 'URL to the event.', 'simple-history' ),
					'type'        => 'string',
					'format'      => 'uri',
				),
				'message'                    => array(
					'description' => __( 'The interpolated message of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'message_html'               => array(
					'description' => __( 'The interpolated message of the event, with possible markup applied.', 'simple-history' ),
					'type'        => 'string',
				),
				'details_html'               => array(
					'description' => __( 'The details of the event, with possible markup applied.', 'simple-history' ),
					'type'        => 'string',
				),
				'details_data'               => array(
					'description' => __( 'The details of the event.', 'simple-history' ),
					'type'        => 'object',
				),
				'message_uninterpolated'     => array(
					'description' => __( 'The uninterpolated message of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'logger'                     => array(
					'description' => __( 'The logger of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'via'                        => array(
					'description' => __( 'The via of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'message_key'                => array(
					'description' => __( 'The message key of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'loglevel'                   => array(
					'description' => __( 'The log level of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'initiator'                  => array(
					'description' => __( 'The initiator of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'initiator_data'             => array(
					'description' => __( 'Details of the initiator.', 'simple-history' ),
					'type'        => 'object',
				),
				'ip_addresses'               => array(
					'description' => __( 'The IP addresses of the event.', 'simple-history' ),
					'type'        => 'array',
				),
				'occasions_id'               => array(
					'description' => __( 'The occasions ID of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'subsequent_occasions_count' => array(
					'description' => __( 'The subsequent occasions count of the event.', 'simple-history' ),
					'type'        => 'integer',
				),
				'context'                    => array(
					'description' => __( 'The context of the event.', 'simple-history' ),
					'type'        => 'object',
				),
				'permalink'                  => array(
					'description' => __( 'The permalink of the event.', 'simple-history' ),
					'type'        => 'string',
				),
				'sticky'                     => array(
					'description' => __( 'Whether the event is sticky.', 'simple-history' ),
					'type'        => 'boolean',
				),
				'sticky_appended'            => array(
					'description' => __( 'Whether the event is sticky and appended to the result set.', 'simple-history' ),
					'type'        => 'boolean',
				),
				'backfilled'                 => array(
					'description' => __( 'Whether the event was backfilled from existing WordPress data.', 'simple-history' ),
					'type'        => 'boolean',
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Checks if a given request has access to read posts.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to view events.', 'simple-history' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		// Surrounding events feature requires administrator privileges.
		// This bypasses normal logger permission checks and could expose sensitive events.
		if ( isset( $request['surrounding_event_id'] ) && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to view surrounding events. This feature requires administrator privileges.', 'simple-history' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get update info for a request.
	 *
	 * Takes the same args as get_items() but `since_id` param is required.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_has_updates( $request ) {
		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_collection_params();
		$args       = [];

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'include'                 => 'post__in',
			'offset'                  => 'offset',
			'page'                    => 'paged',
			'per_page'                => 'posts_per_page',
			'search'                  => 'search',
			'logRowID'                => 'logRowID',
			'occasionsID'             => 'occasionsID',
			'occasionsCount'          => 'occasionsCount',
			'occasionsCountMaxReturn' => 'occasionsCountMaxReturn',
			'type'                    => 'type',
			'max_id_first_page'       => 'max_id_first_page',
			'since_id'                => 'since_id',
			'since_date'              => 'since_date',
			'date_from'               => 'date_from',
			'date_to'                 => 'date_to',
			'dates'                   => 'dates',
			'lastdays'                => 'lastdays',
			'months'                  => 'months',
			'loglevels'               => 'loglevels',
			'loggers'                 => 'loggers',
			'messages'                => 'messages',
			'users'                   => 'users',
			'user'                    => 'user',
			'initiator'               => 'initiator',
			'context_filters'         => 'context_filters',
			'ungrouped'               => 'ungrouped',
		);

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		$log_query    = new Log_Query();
		$query_result = $log_query->query( $args );

		if ( is_wp_error( $query_result ) ) {
			return $query_result;
		}

		return rest_ensure_response(
			[
				'new_events_count' => $query_result['total_row_count'],
			]
		);
	}

	/**
	 * Get items.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		// Tmp slow requests to test slow response.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// sleep( 3 );

		$events = [];

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
		// Debug: return error.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// return new WP_Error( 'simple_history_error', 'Something went wrong 🤷', array( 'status' => 500 ) );

		// Retrieve the list of registered collection query parameters.
		$registered = $this->get_collection_params();
		$args       = [];

		/*
		 * This array defines mappings between public API query parameters whose
		 * values are accepted as-passed, and their internal WP_Query parameter
		 * name equivalents (some are the same). Only values which are also
		 * present in $registered will be set.
		 */
		$parameter_mappings = array(
			'include'                 => 'post__in',
			'offset'                  => 'offset',
			'page'                    => 'paged',
			'per_page'                => 'posts_per_page',
			'search'                  => 'search',
			'logRowID'                => 'logRowID',
			'occasionsID'             => 'occasionsID',
			'occasionsCount'          => 'occasionsCount',
			'occasionsCountMaxReturn' => 'occasionsCountMaxReturn',
			'type'                    => 'type',
			'max_id_first_page'       => 'max_id_first_page',
			'since_id'                => 'since_id',
			'since_date'              => 'since_date',
			'date_from'               => 'date_from',
			'date_to'                 => 'date_to',
			'dates'                   => 'dates',
			'lastdays'                => 'lastdays',
			'months'                  => 'months',
			'loglevels'               => 'loglevels',
			'loggers'                 => 'loggers',
			'messages'                => 'messages',
			'users'                   => 'users',
			'user'                    => 'user',
			'include_sticky'          => 'include_sticky',
			'only_sticky'             => 'only_sticky',
			'initiator'               => 'initiator',
			'context_filters'         => 'context_filters',
			'ungrouped'               => 'ungrouped',
			// Surrounding events parameters.
			'surrounding_event_id'    => 'surrounding_event_id',
			'surrounding_count'       => 'surrounding_count',
			// Exclusion filters.
			'exclude_search'          => 'exclude_search',
			'exclude_loglevels'       => 'exclude_loglevels',
			'exclude_loggers'         => 'exclude_loggers',
			'exclude_messages'        => 'exclude_messages',
			'exclude_users'           => 'exclude_users',
			'exclude_user'            => 'exclude_user',
			'exclude_initiator'       => 'exclude_initiator',
		);

		/*
		 * For each known parameter which is both registered and present in the request,
		 * set the parameter's value on the query $args.
		 */
		foreach ( $parameter_mappings as $api_param => $wp_param ) {
			if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
				$args[ $wp_param ] = $request[ $api_param ];
			}
		}

		$log_query    = new Log_Query();
		$query_result = $log_query->query( $args );

		if ( is_wp_error( $query_result ) ) {
			return $query_result;
		}

		foreach ( $query_result['log_rows'] as $event_row ) {
			$data     = $this->prepare_item_for_response( $event_row, $request );
			$events[] = $this->prepare_response_for_collection( $data );
		}

		$request_params = $request->get_query_params();
		$collection_url = rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) );
		$base           = add_query_arg( urlencode_deep( $request_params ), $collection_url );

		$response = rest_ensure_response( $events );

		$query_type = $request['type'] ?? 'overview';

		// Check if this is a surrounding events query.
		$is_surrounding_query = isset( $request['surrounding_event_id'] );

		if ( $is_surrounding_query ) {
			// Add surrounding events specific headers.
			$response->header( 'X-WP-Total', (int) $query_result['total_row_count'] );
			$response->header( 'X-WP-TotalPages', 1 );
			$response->header( 'X-SimpleHistory-CenterEventId', (int) $query_result['center_event_id'] );
			$response->header( 'X-SimpleHistory-EventsBefore', (int) $query_result['events_before'] );
			$response->header( 'X-SimpleHistory-EventsAfter', (int) $query_result['events_after'] );

			if ( isset( $query_result['max_id'] ) ) {
				$response->header( 'X-SimpleHistory-MaxId', (int) $query_result['max_id'] );
			}

			if ( isset( $query_result['min_id'] ) ) {
				$response->header( 'X-SimpleHistory-MinId', (int) $query_result['min_id'] );
			}

			if ( isset( $query_result['max_date'] ) ) {
				$response->header( 'X-SimpleHistory-MaxDate', $query_result['max_date'] );
			}
		} elseif ( in_array( $query_type, [ 'overview', 'single' ], true ) ) {
			// Add pagination headers to the response for overview and single queries.
			$page        = (int) $query_result['page_current'];
			$total_posts = (int) $query_result['total_row_count'];
			$max_pages   = (int) $query_result['pages_count'];

			$response->header( 'X-WP-Total', (int) $total_posts );
			$response->header( 'X-WP-TotalPages', (int) $max_pages );

			// Add max_id and max_date for has-updates detection.
			if ( isset( $query_result['max_id'] ) ) {
				$response->header( 'X-SimpleHistory-MaxId', (int) $query_result['max_id'] );
			}

			if ( isset( $query_result['max_date'] ) ) {
				$response->header( 'X-SimpleHistory-MaxDate', $query_result['max_date'] );
			}

			if ( $page > 1 ) {
				$prev_page = $page - 1;
				if ( $prev_page > $max_pages ) {
					$prev_page = $max_pages;
				}
				$prev_link = add_query_arg( 'page', $prev_page, $base );
				$response->link_header( 'prev', $prev_link );
			}

			if ( $max_pages > $page ) {
				$next_page = $page + 1;
				$next_link = add_query_arg( 'page', $next_page, $base );
				$response->link_header( 'next', $next_link );
			}
		}

		return $response;
	}

	/**
	 * Prepares a single post output for response.
	 *
	 * @param object           $item    Post object.
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data    = [];
		$context = $item->context ?? [];

		$fields = $this->get_fields_for_response( $request );

		if ( rest_is_field_included( 'id', $fields ) ) {
			$data['id'] = (int) $item->id;
		}

		// `date` column in database is the GTM date when the event was created.
		// So on my local computer with timezone stockholm an event was added when my computer
		// said "21 nov 2024 16:25" and the date in the
		// database is "2024-11-21 15:24:00".
		if ( rest_is_field_included( 'date_local', $fields ) ) {
			// Given a date in UTC or GMT timezone, returns that date in the timezone of the site.
			$data['date_local'] = get_date_from_gmt( $item->date );
		}

		if ( rest_is_field_included( 'date_gmt', $fields ) ) {
			$data['date_gmt'] = $item->date;
		}

		if ( rest_is_field_included( 'via', $fields ) ) {
			$row_logger  = $this->simple_history->get_instantiated_logger_by_slug( $item->logger );
			$data['via'] = $row_logger ? $row_logger->get_info_value_by_key( 'name_via' ) : '';
		}

		if ( rest_is_field_included( 'message', $fields ) ) {
			$message         = $this->simple_history->get_log_row_plain_text_output( $item );
			$message         = html_entity_decode( $message );
			$message         = wp_strip_all_tags( $message );
			$data['message'] = $message;
		}

		if ( rest_is_field_included( 'message_html', $fields ) ) {
			$message              = $this->simple_history->get_log_row_plain_text_output( $item );
			$data['message_html'] = $message;
		}

		if ( rest_is_field_included( 'message_uninterpolated', $fields ) ) {
			$data['message_uninterpolated'] = $item->message;
		}

		if ( rest_is_field_included( 'details_data', $fields ) ) {
			$data['details_data'] = $this->simple_history->get_log_row_details_output( $item )->to_json();
		}

		if ( rest_is_field_included( 'details_html', $fields ) ) {
			$data['details_html'] = $this->simple_history->get_log_row_details_output( $item )->to_html();
		}

		if ( rest_is_field_included( 'link', $fields ) ) {
			$data['link'] = Helpers::get_history_admin_url() . "#simple-history/event/{$item->id}";
		}

		if ( rest_is_field_included( 'logger', $fields ) ) {
			$data['logger'] = $item->logger;
		}

		if ( rest_is_field_included( 'message_key', $fields ) ) {
			$data['message_key'] = $item->context_message_key;
		}

		if ( rest_is_field_included( 'loglevel', $fields ) ) {
			$data['loglevel'] = $item->level;
		}

		if ( rest_is_field_included( 'initiator', $fields ) ) {
			$data['initiator'] = $item->initiator;
		}

		if ( rest_is_field_included( 'initiator_data', $fields ) ) {
			$user_avatar_data = get_avatar_data( $context['_user_id'] ?? null, [] );
			$user_avatar_url  = $user_avatar_data['url'] ?? '';
			$user_object      = get_user_by( 'id', $context['_user_id'] ?? null );

			$user_info = [
				'user_id'           => $context['_user_id'] ?? null,
				'user_login'        => $context['_user_login'] ?? null,
				'user_email'        => $context['_user_email'] ?? null,
				'user_image'        => $this->simple_history->get_log_row_sender_image_output( $item ),
				'user_avatar_url'   => $user_avatar_url,
				'user_profile_url'  => get_edit_user_link( $context['_user_id'] ?? null ),
				'user_display_name' => is_a( $user_object, 'WP_User' ) ? $user_object->display_name : null,
			];

			$data['initiator_data'] = $user_info;
		}

		if ( rest_is_field_included( 'ip_addresses', $fields ) ) {
			// Empty object unless we are ok to include ip addresses.
			$data['ip_addresses'] = new stdClass();

			/** This filter is documented in loggers/class-logger.php */
			$include_ip_addresses_for_event = apply_filters(
				'simple_history/row_header_output/display_ip_address',
				false,
				$item
			);

			if ( $include_ip_addresses_for_event ) {
				// Look for additional ip addresses.
				$arr_found_additional_ip_headers = Helpers::get_event_ip_number_headers( $item );

				$arr_ip_addresses = array_merge(
					// Remote addr always exists.
					[ '_server_remote_addr' => $context['_server_remote_addr'] ],
					$arr_found_additional_ip_headers
				);

				$data['ip_addresses'] = $arr_ip_addresses;
			}
		}

		if ( rest_is_field_included( 'occasions_id', $fields ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$data['occasions_id'] = $item->occasionsID;
		}

		if ( rest_is_field_included( 'subsequent_occasions_count', $fields ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$data['subsequent_occasions_count'] = (int) $item->subsequentOccasions;
		}

		if ( rest_is_field_included( 'sticky', $fields ) ) {
			$data['sticky'] = isset( $item->context['_sticky'] ) ? true : false;
		}

		if ( rest_is_field_included( 'sticky_appended', $fields ) ) {
			$data['sticky_appended'] = isset( $item->sticky_appended ) ? true : false;
		}

		if ( rest_is_field_included( 'backfilled', $fields ) ) {
			$data['backfilled'] = isset( $item->context[ Existing_Data_Importer::BACKFILLED_CONTEXT_KEY ] );
		}

		if ( rest_is_field_included( 'context', $fields ) ) {
			$data['context'] = $item->context;
		}

		if ( rest_is_field_included( 'permalink', $fields ) ) {
			$data['permalink'] = sprintf(
				'%s#simple-history/event/%d',
				Helpers::get_history_admin_url(),
				$item->id
			);
		}

		// Wrap the data in a response object.
		$response = rest_ensure_response( $data );

		return $response;
	}

	/**
	 * Check if current user can create items.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if user can create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create one item from the collection.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$message = $request->get_param( 'message' );
		$note    = $request->get_param( 'note' );
		$level   = $request->get_param( 'level' ) ?? 'info';
		$date    = $request->get_param( 'date' );

		if ( ! Log_Levels::is_valid_level( $level ) ) {
			return new WP_Error(
				'rest_invalid_log_level',
				__( 'Invalid log level specified.', 'simple-history' ),
				array( 'status' => 400 )
			);
		}

		// Validate date format if provided.
		if ( ! empty( $date ) ) {
			// Check if date is in valid MySQL datetime format (Y-m-d H:i:s).
			$parsed_date = \DateTime::createFromFormat( 'Y-m-d H:i:s', $date );
			if ( ! $parsed_date || $parsed_date->format( 'Y-m-d H:i:s' ) !== $date ) {
				return new WP_Error(
					'rest_invalid_date',
					__( 'Invalid date format. Please use Y-m-d H:i:s format (e.g., 2024-01-15 14:30:00).', 'simple-history' ),
					array( 'status' => 400 )
				);
			}
		}

		$logger = $this->simple_history->get_instantiated_logger_by_slug( 'CustomEntryLogger' );
		if ( ! $logger ) {
			return new WP_Error(
				'rest_logger_not_found',
				__( 'Custom entry logger could not be initialized.', 'simple-history' ),
				array( 'status' => 500 )
			);
		}

		$context = [
			'message' => $message,
		];

		if ( ! empty( $note ) ) {
			$context['note'] = $note;
		}

		// Add custom date if provided.
		// The _date context parameter is handled by the logger
		// and will override the default current timestamp.
		if ( ! empty( $date ) ) {
			$context['_date'] = $date;
		}

		$method = $level . '_message';
		$logger->$method( 'custom_entry_added', $context );

		return new \WP_REST_Response(
			array(
				'message' => 'Event logged successfully',
				'data'    => array(
					'status' => 201,
				),
			),
			201
		);
	}

	/**
	 * Checks if a given request has access to update an event.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if the request has access to update the item, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		// User must be logged in and have manage_options capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to update events.', 'simple-history' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		// Event must exist.
		if ( ! Helpers::event_exists( $request['id'] ) ) {
			return new WP_Error(
				'rest_event_invalid_id',
				__( 'Invalid event ID.', 'simple-history' ),
				array( 'status' => 404 )
			);
		}

		return true;
	}

	/**
	 * Sticks an event by setting its sticky status.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function stick_event( $request ) {
		$event = new Event( $request['id'] );

		if ( ! $event->exists() ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'simple-history' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $event->stick() ) {
			return new WP_Error(
				'rest_stick_event_failed',
				__( 'Failed to stick event.', 'simple-history' ),
				array( 'status' => 500 )
			);
		}

		$data = $this->prepare_item_for_response( $event->get_data(), $request );
		return rest_ensure_response( $data );
	}

	/**
	 * Unsticks an event by removing its sticky status.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function unstick_event( $request ) {
		$event = new Event( $request['id'] );

		if ( ! $event->exists() ) {
			return new WP_Error(
				'rest_event_not_found',
				__( 'Event not found.', 'simple-history' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $event->unstick() ) {
			return new WP_Error(
				'rest_unstick_event_failed',
				__( 'Failed to unstick event.', 'simple-history' ),
				array( 'status' => 500 )
			);
		}

		$data = $this->prepare_item_for_response( $event->get_data(), $request );
		return rest_ensure_response( $data );
	}

	/**
	 * Validate initiator parameter.
	 *
	 * @param mixed            $value   Value of the parameter.
	 * @param \WP_REST_Request $request REST request object.
	 * @param string           $param   Parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_initiator_param( $value, $request, $param ) {
		$valid_initiators = Log_Initiators::get_valid_initiators();

		if ( is_string( $value ) ) {
			// Single initiator.
			if ( ! in_array( $value, $valid_initiators, true ) ) {
				return new WP_Error(
					'rest_invalid_param',
					/* translators: %1$s: parameter name, %2$s: list of valid values */
					sprintf( __( '%1$s is not one of %2$s', 'simple-history' ), $param, implode( ', ', $valid_initiators ) ),
					array( 'status' => 400 )
				);
			}
		} elseif ( is_array( $value ) ) {
			// Multiple initiators.
			foreach ( $value as $initiator ) {
				if ( ! is_string( $initiator ) || ! in_array( $initiator, $valid_initiators, true ) ) {
					return new WP_Error(
						'rest_invalid_param',
						/* translators: %1$s: parameter name, %2$s: list of valid values */
						sprintf( __( '%1$s is not one of %2$s', 'simple-history' ), $param, implode( ', ', $valid_initiators ) ),
						array( 'status' => 400 )
					);
				}
			}
		} else {
			return new WP_Error(
				'rest_invalid_param',
				/* translators: %s: parameter name */
				sprintf( __( '%s must be a string or array of strings', 'simple-history' ), $param ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Sanitize initiator parameter.
	 *
	 * @param mixed            $value   Value of the parameter.
	 * @param \WP_REST_Request $request REST request object.
	 * @param string           $param   Parameter name.
	 * @return string|array Sanitized value.
	 */
	public function sanitize_initiator_param( $value, $request, $param ) {
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		} elseif ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return $value;
	}
}
