<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\WP_REST_Events_Controller;
use Simple_History\WP_REST_SearchOptions_Controller;
use Simple_History\WP_REST_Stats_Controller;
use Simple_History\WP_REST_Support_Info_Controller;
use Simple_History\WP_REST_User_Card_Controller;
use Simple_History\WP_REST_Devtools_Controller;

/**
 * Load the Simple History REST API.
 */
class REST_API extends Service {
	/**
	 * User meta key holding the user's chosen event log view, "detailed" or "compact".
	 */
	const EVENTS_VIEW_USER_META_KEY = 'simple_history_events_view';

	/** @inheritDoc */
	public function loaded() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// On init, not rest_api_init, so the registered default also applies
		// when the events page reads the value outside a REST request.
		add_action( 'init', [ $this, 'register_user_meta' ] );
		add_action( 'init', [ $this, 'apply_default_meta_filter' ] );
	}

	/**
	 * Register the user meta that stores the event log view.
	 *
	 * Saved by the events page through the core /wp/v2/users/me endpoint,
	 * so no route of our own is needed.
	 */
	public function register_user_meta() {
		register_meta(
			'user',
			self::EVENTS_VIEW_USER_META_KEY,
			[
				'type'          => 'string',
				'single'        => true,
				'default'       => 'detailed',
				'show_in_rest'  => [
					'schema' => [
						'type' => 'string',
						'enum' => [ 'detailed', 'compact' ],
					],
				],
				// Users may only change their own view.
				'auth_callback' => function ( $allowed, $meta_key, $object_id ) {
					return (int) $object_id === (int) get_current_user_id();
				},
			]
		);
	}

	/**
	 * Apply a filter to return the registered default when the meta hasn't been set.
	 */
	public function apply_default_meta_filter() {
		add_filter(
			'get_user_metadata',
			function ( $meta_value, $object_id, $meta_key, $single ) {
				if ( $meta_key === self::EVENTS_VIEW_USER_META_KEY && $single && ( $meta_value === false || $meta_value === '' ) ) {
					return 'detailed';
				}

				return $meta_value;
			},
			10,
			4
		);
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_routes() {
		$rest_api_controller = new WP_REST_Events_Controller();
		$rest_api_controller->register_routes();

		$search_options_controller = new WP_REST_SearchOptions_Controller();
		$search_options_controller->register_routes();

		$stats_controller = new WP_REST_Stats_Controller();
		$stats_controller->register_routes();

		$support_info_controller = new WP_REST_Support_Info_Controller();
		$support_info_controller->register_routes();

		$user_card_controller = new WP_REST_User_Card_Controller();
		$user_card_controller->register_routes();

		// Only register dev tools routes when dev mode is enabled.
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return;
		}

		$dev_tools_controller = new WP_REST_Devtools_Controller();
		$dev_tools_controller->register_routes();
	}
}
