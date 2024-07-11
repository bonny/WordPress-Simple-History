<?php

namespace Simple_History;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * REST API controller for search options.
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
		$this->namespace = 'simple-history/v1';
		$this->rest_base = 'search-options';
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
	}

	/**
	 * Checks if a given request has access to read a post.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access for the item, WP_Error object or false otherwise.
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
		$data = [];

		$data['dates'] = Helpers::get_data_for_date_filter();

		$data['loggers'] = get_loggers_and_messages();

		$response = rest_ensure_response( $data );

		return $response;
	}
}

function get_loggers_and_messages() {
	$simple_history = Simple_History::get_instance();

	$loggers_and_messages = [];
	$loggers_user_can_read = $simple_history->get_loggers_that_user_can_read();

	foreach ( $loggers_user_can_read as $logger ) {
		$logger_info = $logger['instance']->get_info();
		$logger_slug = $logger['instance']->get_slug();
		$logger_name = $logger_info['name'];
		$logger_search_data = [];

		// Get labels for logger.
		if ( isset( $logger_info['labels']['search'] ) ) {

			$logger_search_data['search'] = $logger_info['labels']['search']['label'];

			// Label all = "All found updates" and so on.
			if ( ! empty( $logger_info['labels']['search']['label_all'] ) ) {
				$arr_all_search_messages = [];
				foreach ( $logger_info['labels']['search']['options'] as $option_messages ) {
					$arr_all_search_messages = array_merge( $arr_all_search_messages, $option_messages );
				}

				foreach ( $arr_all_search_messages as $key => $val ) {
					$arr_all_search_messages[ $key ] = $logger_slug . ':' . $val;
				}

				// printf( '<option value="%2$s">%1$s</option>', esc_attr( $logger_info['labels']['search']['label_all'] ), esc_attr( implode( ',', $arr_all_search_messages ) ) );
				$logger_search_data['search_all'] = [
					'label' => $logger_info['labels']['search']['label_all'],
					'options' => $arr_all_search_messages,
				];
			}

			// For each specific search option.
			foreach ( $logger_info['labels']['search']['options'] as $option_key => $option_messages ) {
				foreach ( $option_messages as $key => $val ) {
					$option_messages[ $key ] = $logger_slug . ':' . $val;
				}

				// $str_option_messages = implode( ',', $option_messages );
				// printf(
				// '<option value="%2$s">%1$s</option>',
				// esc_attr( $option_key ), // 1
				// esc_attr( $str_option_messages ) // 2
				// );
				$logger_search_data['search_options'][] = [
					'label' => $option_key,
					'options' => $option_messages,
				];
			}
		}// End if().

		$loggers_and_messages[] = [
			'slug'         => $logger_slug,
			'name'         => $logger_name,
			'search_data'  => $logger_search_data,
		];
	}

	return $loggers_and_messages;
}
