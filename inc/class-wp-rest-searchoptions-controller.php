<?php

namespace Simple_History;

use Simple_History\Services\AddOns_Licences;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * REST API controller for retrieving search options,
 * i.e. data required for the search options in the admin UI.
 * It does not search anything. A better name would be nice.
 */
class WP_REST_SearchOptions_Controller extends WP_REST_Controller {
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
		$this->rest_base      = 'search-options';
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// GET /wp-json/simple-history/v1/search-options.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			],
		);

		// GET /wp-json/simple-history/v1/search-user.
		register_rest_route(
			$this->namespace,
			'/search-user',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items_for_search_user' ],
					'permission_callback' => [ $this, 'get_items_permissions_check_for_search_user' ],
				],
			],
		);
	}

	/**
	 * Get items for search user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_items_for_search_user( $request ) {
		$data = [];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$q = trim( sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) );

		if ( empty( $q ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Please provide a search query.', 'simple-history' ),
				[ 'status' => 400 ]
			);
		}

		$data = $this->search_user( $q );

		return rest_ensure_response( $data );
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
				__( 'Sorry, you are not allowed to search options.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
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
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Checks if a given request has access to read posts.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check_for_search_user( $request ) {
		// User must be logged in.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to search users.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		// user must have list_users capability (default super admin + administrators have this).
		if ( ! current_user_can( 'list_users' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to search users.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Get items, i.e. get data for search options.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		/** @var AddOns_Licences */
		$addons_service = $this->simple_history->get_service( AddOns_Licences::class );

		$data = [
			'dates'                           => Helpers::get_data_for_date_filter(),
			'loggers'                         => $this->get_loggers_and_messages(),
			'initiators'                      => $this->get_initiator_options(),
			'pager_size'                      => [
				'page'      => (int) Helpers::get_pager_size(),
				'dashboard' => (int) Helpers::get_pager_size_dashboard(),
			],
			'new_events_check_interval'       => Helpers::get_new_events_check_interval(),
			'maps_api_key'                    => apply_filters( 'simple_history/maps_api_key', '' ),
			'addons'                          => [
				'addons'                       => $addons_service->get_addon_plugins(),
				'has_extended_settings_add_on' => $addons_service->has_add_on( 'simple-history-extended-settings' ),
				'has_premium_add_on'           => $addons_service->has_add_on( 'simple-history-premium' ),
			],
			'experimental_features_enabled'   => Helpers::experimental_features_is_enabled(),
			'events_admin_page_url'           => Helpers::get_history_admin_url(),
			'settings_page_url'               => Helpers::get_settings_page_url(),
			'current_user_id'                 => get_current_user_id(),
			'current_user_can_manage_options' => current_user_can( 'manage_options' ),
		];

		return rest_ensure_response( $data );
	}

	/**
	 * Get loggers and messages.
	 *
	 * @return array
	 */
	protected function get_loggers_and_messages() {
		$simple_history = Simple_History::get_instance();

		$loggers_and_messages  = [];
		$loggers_user_can_read = $simple_history->get_loggers_that_user_can_read();

		foreach ( $loggers_user_can_read as $logger ) {
			$logger_info        = $logger['instance']->get_info();
			$logger_slug        = $logger['instance']->get_slug();
			$logger_name        = $logger_info['name'];
			$logger_search_data = [];

			// Get labels for logger.
			if ( isset( $logger_info['labels']['search'] ) ) {

				// Create array with all search messages for this logger.
				$arr_all_search_messages = [];

				foreach ( $logger_info['labels']['search']['options'] ?? [] as $option_messages ) {
					$arr_all_search_messages = array_merge( $arr_all_search_messages, $option_messages );
				}

				foreach ( $arr_all_search_messages as $key => $val ) {
					$arr_all_search_messages[ $key ] = $logger_slug . ':' . $val;
				}

				// Label for search options, like "Users" or "Post".
				$logger_search_data['search'] = [
					'label'   => $logger_info['labels']['search']['label'],
					'options' => $arr_all_search_messages,
				];

				// Label all = "All found updates" and so on.
				if ( ! empty( $logger_info['labels']['search']['label_all'] ) ) {
					$logger_search_data['search_all'] = [
						'label'   => $logger_info['labels']['search']['label_all'],
						'options' => $arr_all_search_messages,
					];
				}

				// For each specific search option.
				$labels_search_options = $logger_info['labels']['search']['options'] ?? [];
				foreach ( $labels_search_options as $option_key => $option_messages ) {
					foreach ( $option_messages as $key => $val ) {
						$option_messages[ $key ] = $logger_slug . ':' . $val;
					}

					$logger_search_data['search_options'][] = [
						'label'   => $option_key,
						'options' => $option_messages,
					];
				}
			}

			$loggers_and_messages[] = [
				'slug'        => $logger_slug,
				'name'        => $logger_name,
				'search_data' => $logger_search_data,
			];
		}

		return $loggers_and_messages;
	}

	/**
	 * Get initiator options for search filter.
	 *
	 * @return array Array of initiator options with value and label.
	 */
	protected function get_initiator_options() {
		$options = [];

		foreach ( Log_Initiators::get_valid_initiators() as $initiator ) {
			$options[] = [
				'value' => $initiator,
				'label' => Log_Initiators::get_initiator_label( $initiator ),
			];
		}

		return $options;
	}

	/**
	 * Search for users.
	 *
	 * @param string $q Search query.
	 * @return array List of users.
	 */
	public function search_user( $q ) {
		// Search both current users and all logged rows,
		// because a user can change email
		// search in context: user_id, user_email, user_login
		// search in wp_users: login, nicename, user_email
		// search and get users. make sure to use "fields" and "number" or we can get timeout/use lots of memory if we have a large amount of users.
		return get_users(
			[
				'search' => "*{$q}*",
				'fields' => array( 'ID', 'user_login', 'user_nicename', 'user_email', 'display_name' ),
				'number' => 20,
			]
		);
	}
}
