<?php

namespace Simple_History\Services;

use Simple_History\WP_REST_Events_Controller;
use Simple_History\WP_REST_SearchOptions_Controller;
use Simple_History\WP_REST_Stats_Controller;

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
	}
}
