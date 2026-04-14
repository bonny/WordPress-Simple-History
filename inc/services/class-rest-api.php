<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\WP_REST_Events_Controller;
use Simple_History\WP_REST_SearchOptions_Controller;
use Simple_History\WP_REST_Stats_Controller;
use Simple_History\WP_REST_Support_Info_Controller;
use Simple_History\WP_REST_User_Card_Controller;
use Simple_History\WP_REST_Devtools_Controller;
use Simple_History\WP_REST_Network_Events_Controller;
use Simple_History\WP_REST_Network_SearchOptions_Controller;
use Simple_History\WP_REST_Network_Stats_Controller;
use Simple_History\WP_REST_Network_User_Card_Controller;

/**
 * Load the Simple History REST API.
 */
class REST_API extends Service {
	/** @inheritDoc */
	public function loaded() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
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

		// Register network-scoped APIs on multisite.
		if ( is_multisite() ) {
			$network_events_controller = new WP_REST_Network_Events_Controller();
			$network_events_controller->register_routes();

			$network_stats_controller = new WP_REST_Network_Stats_Controller();
			$network_stats_controller->register_routes();

			$network_search_options_controller = new WP_REST_Network_SearchOptions_Controller();
			$network_search_options_controller->register_routes();

			$network_user_card_controller = new WP_REST_Network_User_Card_Controller();
			$network_user_card_controller->register_routes();
		}

		// Only register dev tools routes when dev mode is enabled.
		if ( ! Helpers::dev_mode_is_enabled() ) {
			return;
		}

		$dev_tools_controller = new WP_REST_Devtools_Controller();
		$dev_tools_controller->register_routes();
	}
}
