<?php

namespace Simple_History;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * REST API controller for events.
 */
class WP_REST_Events_Controller extends WP_REST_Controller {
	/** @var string */
	protected $rest_base = 'events';

	/** @var string */
	protected $namespace = 'simple-history/v1';

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => $this->get_collection_params(),
			]
		);
	}

	/**
	 * Retrieves the query params for the posts collection.
	 *
	 * @since 4.7.0
	 * @since 5.4.0 The `tax_relation` query parameter was added.
	 * @since 5.7.0 The `modified_after` and `modified_before` query parameters were added.
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

		// Get rows with id lower than logRowID, i.e. previous rows.
		// $outer_where[] = 'h.id < ' . (int) $args['logRowID'];
		$query_params['logRowID'] = array(
			'description' => __( 'Limit result set to rows with id lower than this.', 'simple-history' ),
			'type'        => 'integer',
		);

		// Get rows with occasionsID equal to occasionsID.
		// $outer_where[] = "h.occasionsID = '" . esc_sql( $args['occasionsID'] ) . "'";
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
			'default'     => array(),
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

		// Date to in unix timestamp format.
		$query_params['date_from'] = array(
			'description' => __( 'Limit result set to rows with date greater than or equal to this unix timestamp.', 'simple-history' ),
			'type'        => 'integer',
		);

		// Date to.
		// If date_to is set it is a timestamp.
		$query_params['date_to'] = array(
			'description' => __( 'Limit result set to rows with date less than or equal to this unix timestamp.', 'simple-history' ),
			'type'        => 'integer',
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

		// messages
		// [0] => SimpleCommentsLogger:anon_comment_added,SimpleCommentsLogger:user_comment_added,SimpleCommentsLogger:anon_trackback_added,SimpleCommentsLogger:user_trackback_added,SimpleCommentsLogger:anon_pingback_added,SimpleCommentsLogger:user_pingback_added,SimpleCommentsLogger:comment_edited,SimpleCommentsLogger:trackback_edited,SimpleCommentsLogger:pingback_edited,SimpleCommentsLogger:comment_status_approve,SimpleCommentsLogger:trackback_status_approve,SimpleCommentsLogger:pingback_status_approve,SimpleCommentsLogger:comment_status_hold,SimpleCommentsLogger:trackback_status_hold,SimpleCommentsLogger:pingback_status_hold,SimpleCommentsLogger:comment_status_spam,SimpleCommentsLogger:trackback_status_spam,SimpleCommentsLogger:pingback_status_spam,SimpleCommentsLogger:comment_status_trash,SimpleCommentsLogger:trackback_status_trash,SimpleCommentsLogger:pingback_status_trash,SimpleCommentsLogger:comment_untrashed,SimpleCommentsLogger:trackback_untrashed,SimpleCommentsLogger:pingback_untrashed,SimpleCommentsLogger:comment_deleted,SimpleCommentsLogger:trackback_deleted,SimpleCommentsLogger:pingback_deleted
		// [1] => SimpleCommentsLogger:SimpleCommentsLogger:comment_status_spam,SimpleCommentsLogger:trackback_status_spam,SimpleCommentsLogger:pingback_status_spam
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

		// Todo: iso8601 format should be used.
		// $query_params['after'] = array(
		// 'description' => __( 'Limit response to posts published after a given ISO8601 compliant date.', 'simple-history' ),
		// 'type'        => 'string',
		// 'format'      => 'date-time',
		// );

		$query_params['offset'] = array(
			'description' => __( 'Offset the result set by a specific number of items.', 'simple-history' ),
			'type'        => 'integer',
		);

		$query_params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'simple-history' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		$query_params['orderby'] = array(
			'description' => __( 'Sort collection by post attribute.', 'simple-history' ),
			'type'        => 'string',
			'default'     => 'date',
			'enum'        => array(
				'author',
				'date',
				'id',
				'include',
				'modified',
				'parent',
				'relevance',
				'slug',
				'include_slugs',
				'title',
			),
		);

		return $query_params;
	}

	/**
	 * Checks if a given request has access to read posts.
	 *
	 * @since 4.7.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
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

		return true;
	}

	/**
	 * Get items.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$events = [];
		$log_query = new Log_Query();
		$query_result = $log_query->query();

		return rest_ensure_response( $query_result );
	}
}
