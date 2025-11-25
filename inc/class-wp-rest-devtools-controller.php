<?php

namespace Simple_History;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST API controller for developer tools.
 * Provides endpoints for toggling plugins and other development utilities.
 */
class WP_REST_Devtools_Controller extends WP_REST_Controller {
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
		$this->rest_base      = 'dev-tools';
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// POST /wp-json/simple-history/v1/dev-tools/toggle-plugin.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/toggle-plugin',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'toggle_plugin' ],
					'permission_callback' => [ $this, 'toggle_plugin_permissions_check' ],
					'args'                => [
						'plugin' => [
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Plugin file path', 'simple-history' ),
							'validate_callback' => [ $this, 'validate_plugin_path' ],
						],
					],
				],
			],
		);

		// GET /wp-json/simple-history/v1/dev-tools/plugin-status.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/plugin-status',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_plugin_status' ],
					'permission_callback' => [ $this, 'get_plugin_status_permissions_check' ],
					'args'                => [
						'plugin' => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Plugin file path', 'simple-history' ),
						],
					],
				],
			],
		);
	}

	/**
	 * Validate plugin path.
	 *
	 * @param string          $value The plugin path.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param The parameter name.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_plugin_path( $value, $request, $param ) {
		// Only allow specific plugins for security.
		$allowed_plugins = [
			'simple-history-premium/simple-history-premium.php',
		];

		if ( ! in_array( $value, $allowed_plugins, true ) ) {
			return new WP_Error(
				'rest_invalid_param',
				__( 'Invalid plugin path.', 'simple-history' ),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Check permissions for toggling plugin.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if has permission, WP_Error otherwise.
	 */
	public function toggle_plugin_permissions_check( $request ) {
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Dev mode must be enabled to toggle plugins.', 'simple-history' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to toggle plugins.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Check permissions for getting plugin status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if has permission, WP_Error otherwise.
	 */
	public function get_plugin_status_permissions_check( $request ) {
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Dev mode must be enabled to check plugin status.', 'simple-history' ),
				[ 'status' => 403 ]
			);
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to check plugin status.', 'simple-history' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}

	/**
	 * Toggle a plugin on or off.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function toggle_plugin( $request ) {
		$plugin = sanitize_text_field( $request->get_param( 'plugin' ) );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$is_active = is_plugin_active( $plugin );

		if ( $is_active ) {
			$result = deactivate_plugins( $plugin );
		} else {
			$result = activate_plugin( $plugin );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'plugin_toggle_failed',
				$result->get_error_message(),
				[ 'status' => 500 ]
			);
		}

		$new_status = ! $is_active;

		return rest_ensure_response(
			[
				'success'    => true,
				'plugin'     => $plugin,
				'is_active'  => $new_status,
				'was_active' => $is_active,
			]
		);
	}

	/**
	 * Get plugin status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_plugin_status( $request ) {
		$plugin = sanitize_text_field( $request->get_param( 'plugin' ) );

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$is_active = is_plugin_active( $plugin );

		return rest_ensure_response(
			[
				'plugin'    => $plugin,
				'is_active' => $is_active,
			]
		);
	}
}
