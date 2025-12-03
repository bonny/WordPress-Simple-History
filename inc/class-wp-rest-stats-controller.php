<?php

namespace Simple_History;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;
use Simple_History\Date_Helper;

/**
 * REST API controller for stats.
 */
class WP_REST_Stats_Controller extends WP_REST_Controller {
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
		$this->rest_base      = 'stats';
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// GET /wp-json/simple-history/v1/stats/summary.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_summary' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/users.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/users',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_users_stats' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/content.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/content',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_content_stats' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/media.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/media',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_media_stats' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/plugins.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/plugins',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_plugins_stats' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/core.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/core',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_core_stats' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/peak-days.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/peak-days',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_peak_days' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/peak-times.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/peak-times',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_peak_times' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/stats/activity-overview.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/activity-overview',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_activity_overview' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Checks if a given request has access to read stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		// User must be logged in and have manage_options capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to view stats.', 'simple-history' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Retrieves the query params for the collection.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['date_from'] = array(
			// translators: %d is the number of days.
			'description' => sprintf( __( 'Start date as Unix timestamp. If not provided, defaults to %d days ago.', 'simple-history' ), Date_Helper::DAYS_PER_MONTH ),
			'type'        => 'integer',
			'required'    => false,
		);

		$params['date_to'] = array(
			'description' => __( 'End date as Unix timestamp. If not provided, defaults to end of today.', 'simple-history' ),
			'type'        => 'integer',
			'required'    => false,
		);

		$params['limit'] = array(
			'description' => __( 'Maximum number of items to be returned in result set.', 'simple-history' ),
			'type'        => 'integer',
			'default'     => 50,
			'minimum'     => 1,
			'maximum'     => 100,
		);

		$params['include_details'] = array(
			'description' => __( 'Whether to include detailed stats.', 'simple-history' ),
			'type'        => 'boolean',
			'default'     => false,
		);

		return $params;
	}

	/**
	 * Get default date range for last month.
	 *
	 * Uses WordPress timezone from Settings > General.
	 *
	 * @return array Array with 'from' and 'to' timestamps for last month.
	 */
	private function get_default_date_range() {
		return Date_Helper::get_default_date_range();
	}

	/**
	 * Get date range from request or default to today.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array Array with 'from' and 'to' timestamps.
	 */
	private function get_date_range_from_request( $request ) {
		$date_from = $request->get_param( 'date_from' );
		$date_to   = $request->get_param( 'date_to' );

		if ( ! $date_from || ! $date_to ) {
			$default_range = $this->get_default_date_range();
			if ( ! $date_from ) {
				$date_from = $default_range['from'];
			}
			if ( ! $date_to ) {
				$date_to = $default_range['to'];
			}
		}

		return array(
			'from' => $date_from,
			'to'   => $date_to,
		);
	}

	/**
	 * Format date range with human-readable dates.
	 *
	 * @param int $from Unix timestamp for start date.
	 * @param int $to Unix timestamp for end date.
	 * @return array Array with timestamps, formatted dates, and duration.
	 */
	private function format_date_range( $from, $to ) {
		$date_format     = get_option( 'date_format' );
		$time_format     = get_option( 'time_format' );
		$datetime_format = $date_format . ' ' . $time_format;

		return array(
			'from'           => $from,
			'to'             => $to,
			'from_formatted' => date_i18n( $datetime_format, $from ),
			'to_formatted'   => date_i18n( $datetime_format, $to ),
			'duration_days'  => ceil( ( $to - $from ) / DAY_IN_SECONDS ),
		);
	}

	/**
	 * Get summary stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_summary( $request ) {
		$date_range = $this->get_date_range_from_request( $request );
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		$events_stats = new Events_Stats();

		$summary = array(
			'date_range'                 => $this->format_date_range( $date_from, $date_to ),
			'total_events'               => $events_stats->get_total_events( $date_from, $date_to ),
			'total_events_since_install' => Helpers::get_total_logged_events_count(),
			'totals'                     => array(
				'users'   => array(
					'logins'          => $events_stats->get_successful_logins_count( $date_from, $date_to ),
					'failed_logins'   => $events_stats->get_failed_logins_count( $date_from, $date_to ),
					'profile_updates' => $events_stats->get_user_updated_count( $date_from, $date_to ),
					'total'           => $events_stats->get_user_total_count( $date_from, $date_to ),
				),
				'content' => array(
					'created' => $events_stats->get_posts_pages_created( $date_from, $date_to ),
					'updated' => $events_stats->get_posts_pages_updated( $date_from, $date_to ),
					'deleted' => $events_stats->get_posts_pages_deleted( $date_from, $date_to ),
					'total'   => $events_stats->get_content_total_count( $date_from, $date_to ),
				),
				'media'   => array(
					'uploads'   => $events_stats->get_media_uploads_count( $date_from, $date_to ),
					'edits'     => $events_stats->get_media_edits_count( $date_from, $date_to ),
					'deletions' => $events_stats->get_media_deletions_count( $date_from, $date_to ),
					'total'     => $events_stats->get_media_total_count( $date_from, $date_to ),
				),
				'plugins' => array(
					'updates'       => $events_stats->get_plugin_updates_count( $date_from, $date_to ),
					'installations' => $events_stats->get_plugin_installs_count( $date_from, $date_to ),
					'activations'   => $events_stats->get_plugin_activations_count( $date_from, $date_to ),
					'total'         => $events_stats->get_plugin_total_count( $date_from, $date_to ),
				),
				'core'    => array(
					'updates'           => $events_stats->get_wordpress_core_updates_count( $date_from, $date_to ),
					'available_updates' => $events_stats->get_wordpress_core_updates_found_count( $date_from, $date_to ),
					'total'             => $events_stats->get_core_total_count( $date_from, $date_to ),
				),
			),
		);

		return rest_ensure_response( $summary );
	}

	/**
	 * Get user stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_users_stats( $request ) {
		$date_range      = $this->get_date_range_from_request( $request );
		$date_from       = $date_range['from'];
		$date_to         = $date_range['to'];
		$limit           = $request->get_param( 'limit' );
		$include_details = $request->get_param( 'include_details' );

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range' => $this->format_date_range( $date_from, $date_to ),
			'summary'    => array(
				'logins'          => $events_stats->get_successful_logins_count( $date_from, $date_to ),
				'failed_logins'   => $events_stats->get_failed_logins_count( $date_from, $date_to ),
				'profile_updates' => $events_stats->get_user_updated_count( $date_from, $date_to ),
				'total'           => $events_stats->get_user_total_count( $date_from, $date_to ),
			),
		);

		if ( $include_details ) {
			$stats['details'] = $events_stats->get_detailed_user_stats( $date_from, $date_to, $limit );
		}

		return rest_ensure_response( $stats );
	}

	/**
	 * Get content stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_content_stats( $request ) {
		$date_range      = $this->get_date_range_from_request( $request );
		$date_from       = $date_range['from'];
		$date_to         = $date_range['to'];
		$limit           = $request->get_param( 'limit' );
		$include_details = $request->get_param( 'include_details' );

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range' => $this->format_date_range( $date_from, $date_to ),
			'summary'    => array(
				'created' => $events_stats->get_posts_pages_created( $date_from, $date_to ),
				'updated' => $events_stats->get_posts_pages_updated( $date_from, $date_to ),
				'deleted' => $events_stats->get_posts_pages_deleted( $date_from, $date_to ),
				'total'   => $events_stats->get_content_total_count( $date_from, $date_to ),
			),
		);

		if ( $include_details ) {
			$stats['details'] = $events_stats->get_detailed_content_stats( $date_from, $date_to, $limit );
		}

		return rest_ensure_response( $stats );
	}

	/**
	 * Get media stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_media_stats( $request ) {
		$date_range      = $this->get_date_range_from_request( $request );
		$date_from       = $date_range['from'];
		$date_to         = $date_range['to'];
		$include_details = $request->get_param( 'include_details' );

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range' => $this->format_date_range( $date_from, $date_to ),
			'summary'    => array(
				'uploads'   => $events_stats->get_media_uploads_count( $date_from, $date_to ),
				'edits'     => $events_stats->get_media_edits_count( $date_from, $date_to ),
				'deletions' => $events_stats->get_media_deletions_count( $date_from, $date_to ),
				'total'     => $events_stats->get_media_total_count( $date_from, $date_to ),
			),
		);

		if ( $include_details ) {
			$stats['details'] = array(
				'uploads'   => $events_stats->get_media_uploaded_details( $date_from, $date_to ),
				'edits'     => $events_stats->get_media_edited_details( $date_from, $date_to ),
				'deletions' => $events_stats->get_media_deleted_details( $date_from, $date_to ),
			);
		}

		return rest_ensure_response( $stats );
	}

	/**
	 * Get plugins stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_plugins_stats( $request ) {
		$date_range      = $this->get_date_range_from_request( $request );
		$date_from       = $date_range['from'];
		$date_to         = $date_range['to'];
		$limit           = $request->get_param( 'limit' );
		$include_details = $request->get_param( 'include_details' );

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range' => $this->format_date_range( $date_from, $date_to ),
			'summary'    => array(
				'updates'           => $events_stats->get_plugin_updates_count( $date_from, $date_to ),
				'installations'     => $events_stats->get_plugin_installs_count( $date_from, $date_to ),
				'activations'       => $events_stats->get_plugin_activations_count( $date_from, $date_to ),
				'available_updates' => $events_stats->get_available_plugin_updates(),
				'total'             => $events_stats->get_plugin_total_count( $date_from, $date_to ),
			),
		);

		if ( $include_details ) {
			$stats['details'] = array(
				'updates'           => $events_stats->get_plugin_details( 'updated', $date_from, $date_to, $limit ),
				'installations'     => $events_stats->get_plugin_details( 'installed', $date_from, $date_to, $limit ),
				'activations'       => $events_stats->get_plugin_details( 'activated', $date_from, $date_to, $limit ),
				'available_updates' => $events_stats->get_plugins_with_updates(),
			);
		}

		return rest_ensure_response( $stats );
	}

	/**
	 * Get core stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_core_stats( $request ) {
		$date_range = $this->get_date_range_from_request( $request );
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range' => $this->format_date_range( $date_from, $date_to ),
			'summary'    => array(
				'updates'           => $events_stats->get_wordpress_core_updates_count( $date_from, $date_to ),
				'available_updates' => $events_stats->get_wordpress_core_updates_found_count( $date_from, $date_to ),
				'total'             => $events_stats->get_core_total_count( $date_from, $date_to ),
			),
		);

		return rest_ensure_response( $stats );
	}

	/**
	 * Get peak days stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_peak_days( $request ) {
		$date_range = $this->get_date_range_from_request( $request );
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range' => $this->format_date_range( $date_from, $date_to ),
			'peak_days'  => $events_stats->get_peak_days( $date_from, $date_to ),
		);

		return rest_ensure_response( $stats );
	}

	/**
	 * Get peak activity times stats.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_peak_times( $request ) {
		$date_range = $this->get_date_range_from_request( $request );
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range' => $this->format_date_range( $date_from, $date_to ),
			'peak_times' => $events_stats->get_peak_activity_times( $date_from, $date_to ),
		);

		return rest_ensure_response( $stats );
	}

	/**
	 * Get activity overview by date.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_activity_overview( $request ) {
		$date_range = $this->get_date_range_from_request( $request );
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		$events_stats = new Events_Stats();

		$stats = array(
			'date_range'       => $this->format_date_range( $date_from, $date_to ),
			'activity_by_date' => $events_stats->get_activity_overview_by_date( $date_from, $date_to ),
		);

		return rest_ensure_response( $stats );
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
			'title'      => 'simple-history-stats',
			'type'       => 'object',
			'properties' => array(
				'date_range' => array(
					'description' => __( 'Date range for the stats.', 'simple-history' ),
					'type'        => 'object',
					'properties'  => array(
						'from' => array(
							'description' => __( 'Start date as Unix timestamp.', 'simple-history' ),
							'type'        => 'integer',
						),
						'to'   => array(
							'description' => __( 'End date as Unix timestamp.', 'simple-history' ),
							'type'        => 'integer',
						),
					),
				),
				'summary'    => array(
					'description' => __( 'Summary of stats.', 'simple-history' ),
					'type'        => 'object',
				),
				'details'    => array(
					'description' => __( 'Detailed stats.', 'simple-history' ),
					'type'        => 'object',
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
