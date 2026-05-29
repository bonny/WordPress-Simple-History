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

		// POST /wp-json/simple-history/v1/dev-tools/toggle-experimental-features.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/toggle-experimental-features',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'toggle_experimental_features' ],
					'permission_callback' => [ $this, 'toggle_plugin_permissions_check' ],
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

		// POST /wp-json/simple-history/v1/dev-tools/set-license-key.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/set-license-key',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_license_key' ],
					'permission_callback' => [ $this, 'toggle_plugin_permissions_check' ],
					'args'                => [
						'slug' => [
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Add-on plugin slug, e.g. simple-history-premium', 'simple-history' ),
						],
						'key'  => [
							'required'    => false,
							'type'        => 'string',
							'default'     => '',
							'description' => __( 'License key. Empty string clears the key.', 'simple-history' ),
						],
					],
				],
			],
		);

		// POST /wp-json/simple-history/v1/dev-tools/reset-license-reminder-dismissals.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset-license-reminder-dismissals',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'reset_license_reminder_dismissals' ],
					'permission_callback' => [ $this, 'toggle_plugin_permissions_check' ],
				],
			],
		);

		// GET /wp-json/simple-history/v1/dev-tools/license-reminder-dismissals.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/license-reminder-dismissals',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_license_reminder_dismissals' ],
					'permission_callback' => [ $this, 'toggle_plugin_permissions_check' ],
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
	 * Toggle experimental features on or off.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function toggle_experimental_features( $request ) {
		$is_enabled = Helpers::experimental_features_is_enabled();
		$new_value  = $is_enabled ? '0' : '1';

		update_option( 'simple_history_experimental_features_enabled', $new_value );

		return rest_ensure_response(
			[
				'success'    => true,
				'is_enabled' => (bool) $new_value,
			]
		);
	}

	/**
	 * Set or clear the license key for a registered add-on plugin.
	 *
	 * Used by Playwright tests to deterministically set up the
	 * "premium installed, no license key" state before assertions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object or error.
	 */
	public function set_license_key( $request ) {
		$slug = sanitize_text_field( $request->get_param( 'slug' ) );
		$key  = sanitize_text_field( $request->get_param( 'key' ) );

		/** @var Services\AddOns_Licences|null $licences_service */
		$licences_service = $this->simple_history->get_service( Services\AddOns_Licences::class );

		if ( ! $licences_service instanceof Services\AddOns_Licences ) {
			return new WP_Error(
				'rest_service_unavailable',
				__( 'AddOns_Licences service not available.', 'simple-history' ),
				[ 'status' => 500 ]
			);
		}

		$addon = $licences_service->get_plugin( $slug );

		if ( ! $addon instanceof AddOn_Plugin ) {
			return new WP_Error(
				'rest_addon_not_registered',
				__( 'Add-on is not registered.', 'simple-history' ),
				[ 'status' => 404 ]
			);
		}

		$message        = $addon->get_license_message();
		$message['key'] = $key === '' ? null : $key;
		$addon->set_licence_message( $message );

		return rest_ensure_response(
			[
				'success' => true,
				'slug'    => $slug,
				'has_key' => $key !== '',
			]
		);
	}

	/**
	 * Clear the license reminder dismissed-addons user meta for the current user.
	 *
	 * Used by Playwright tests to reset to a "card visible" state between
	 * scenarios without poking the database directly.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function reset_license_reminder_dismissals( $request ) {
		delete_user_meta(
			get_current_user_id(),
			Services\License_Reminder_Service::USER_META_KEY
		);

		return rest_ensure_response(
			[
				'success' => true,
				'user_id' => get_current_user_id(),
			]
		);
	}

	/**
	 * Return the current user's license-reminder dismissed-addons array.
	 *
	 * Used by Playwright tests to lock in the actual user_meta state rather
	 * than only asserting downstream visibility, so a regression that hides
	 * the card for an unrelated reason still fails the test.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_license_reminder_dismissals( $request ) {
		$stored = get_user_meta(
			get_current_user_id(),
			Services\License_Reminder_Service::USER_META_KEY,
			true
		);

		return rest_ensure_response(
			[
				'user_id'          => get_current_user_id(),
				'dismissed_addons' => is_array( $stored ) ? array_values( $stored ) : [],
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
