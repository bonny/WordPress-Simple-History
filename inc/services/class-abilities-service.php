<?php

namespace Simple_History\Services;

use Simple_History\Abilities_Event_Presenter;
use Simple_History\WP_REST_Events_Controller;
use Simple_History\WP_REST_Stats_Controller;

/**
 * Register Simple History abilities with the WordPress Abilities API.
 *
 * The Abilities API lets AI agents and automation tools discover what a site
 * can do. It landed in WordPress 6.9; Simple History supports 6.3+, so
 * registration is conditional and silently does nothing on older versions.
 *
 * Every ability here is read-only. Simple History deliberately registers no
 * write or destructive abilities: the value of an audit log is that it is
 * tamper-evident, and an agent that can purge the log destroys that.
 *
 * Abilities delegate to existing REST routes through rest_do_request() rather
 * than querying directly. Simple History's per-logger visibility filtering
 * happens inside Log_Query, not in the permission callback, so delegating is
 * what keeps abilities from over-exposing events.
 */
class Abilities_Service extends Service {
	/**
	 * Category slug that Simple History abilities are grouped under.
	 *
	 * @var string
	 */
	private const CATEGORY = 'simple-history';

	/** @inheritDoc */
	public function loaded() {
		// Abilities API is WordPress 6.9+. Bail quietly on older versions.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register every Simple History ability.
	 */
	public function register_abilities() {
		$this->register_category();

		// Abilities are added by later tasks.
	}

	/**
	 * Register the category Simple History abilities are grouped under.
	 */
	private function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Simple History', 'simple-history' ),
				'description' => __( 'Read the site activity log.', 'simple-history' ),
			]
		);
	}

	/**
	 * Run a Simple History REST route internally and return its data.
	 *
	 * rest_do_request() runs the target route's own permission_callback, so
	 * authorization is enforced even though this request never travelled over
	 * HTTP. That is the point: the per-logger visibility filtering that decides
	 * what a given user may see lives inside Log_Query, further down this path,
	 * and reimplementing it here would be a way to get it wrong.
	 *
	 * @param string $route  REST route, e.g. '/simple-history/v1/events'.
	 * @param array  $params Query parameters.
	 * @return array|\WP_Error
	 */
	private function dispatch( string $route, array $params = [] ) {
		$request = new \WP_REST_Request( 'GET', $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		if ( $response->is_error() ) {
			return $response->as_error();
		}

		return $response->get_data();
	}

	/**
	 * Whether the current user may read events.
	 *
	 * Delegates to the events controller so there is exactly one definition of
	 * who may read the log. Constructing a controller is cheap — its
	 * constructor only assigns three properties.
	 *
	 * @return true|\WP_Error
	 */
	public function check_events_permission() {
		$controller = new WP_REST_Events_Controller();

		return $controller->get_items_permissions_check(
			new \WP_REST_Request( 'GET', '/simple-history/v1/events' )
		);
	}

	/**
	 * Whether the current user may read statistics.
	 *
	 * Stats are stricter than events: the stats controller requires
	 * manage_options, so a user who may read the log still may not read
	 * aggregate statistics. That asymmetry is intentional and preserved here.
	 *
	 * @return true|\WP_Error
	 */
	public function check_stats_permission() {
		$controller = new WP_REST_Stats_Controller();

		return $controller->get_items_permissions_check(
			new \WP_REST_Request( 'GET', '/simple-history/v1/stats/summary' )
		);
	}

	/**
	 * Clamp a caller-supplied result count.
	 *
	 * An agent asking for a thousand events would spend its whole context on
	 * one answer, so the ceiling is enforced here rather than trusted to the
	 * caller.
	 *
	 * @param mixed $per_page Requested count.
	 * @return int
	 */
	private function clamp_per_page( $per_page ): int {
		$per_page = is_numeric( $per_page ) ? (int) $per_page : 20;

		return max( 1, min( 100, $per_page ) );
	}

	/**
	 * Present a list of REST events for an agent.
	 *
	 * @param array|\WP_Error $events          Result of a dispatch() call.
	 * @param bool            $include_context Whether to include event context.
	 * @return array|\WP_Error
	 */
	private function present_events( $events, bool $include_context = false ) {
		if ( is_wp_error( $events ) ) {
			return $events;
		}

		return array_map(
			static function ( $event ) use ( $include_context ) {
				return Abilities_Event_Presenter::present( (array) $event, $include_context );
			},
			(array) $events
		);
	}
}
