<?php
/**
 * Keep the test site off the network and off the core dashboard widgets.
 *
 * The acceptance suite re-imports tests/_data/dump.sql before every test and
 * every transient in that dump is long expired, so each test starts with a cold
 * cache and WordPress re-fetches the wordpress.org news feeds, the community
 * events and the browser/PHP version checks. loginAsAdmin() lands on the
 * dashboard, so that happened 49 times a run and cost ~2.6s each time.
 *
 * disable-updates.php only sanitises the *result* of the update checks; it does
 * not stop the requests being made.
 */

namespace Simple_History\tests;

defined( 'ABSPATH' ) || die();

/**
 * Hosts the test site may still talk to, beyond its own.
 *
 * The site's own host is added at runtime by is_allowed_http_host(), because
 * some admin screens and `wp cron test` do a loopback request back to it and
 * TEST_SITE_WP_URL is not fixed.
 */
const EXTRA_ALLOWED_HTTP_HOSTS = [ 'wordpress', 'localhost', '127.0.0.1' ];

/**
 * Update endpoints that must answer rather than fail.
 *
 * wp_version_check(), wp_update_plugins() and wp_update_themes() treat a
 * WP_Error from an https request as a broken server: they call
 * wp_trigger_error() and retry over plain http. With WORDPRESS_DEBUG=1 that
 * warning prints into the page and breaks text assertions. An empty 200 keeps
 * them on the quiet path.
 */
const QUIET_HTTP_PATHS = [ '/core/version-check/', '/plugins/update-check/', '/themes/update-check/' ];

/**
 * Whether a host belongs to the test stack itself.
 *
 * @param string|null $host Host from the request URL.
 * @return bool
 */
function is_allowed_http_host( $host ) {
	if ( ! is_string( $host ) || '' === $host ) {
		return false;
	}

	$allowed = EXTRA_ALLOWED_HTTP_HOSTS;

	// Derive the site's own host so loopback keeps working whatever
	// TEST_SITE_WP_URL is set to.
	$own_host = wp_parse_url( home_url(), PHP_URL_HOST );

	if ( is_string( $own_host ) && '' !== $own_host ) {
		$allowed[] = $own_host;
	}

	return in_array( strtolower( $host ), array_map( 'strtolower', $allowed ), true );
}

/**
 * Short-circuit outbound HTTP to anything outside the test stack.
 *
 * @param false|array|\WP_Error $preempt     Whether to preempt the request.
 * @param array                 $parsed_args Request arguments.
 * @param string                $url         Request URL.
 * @return false|array|\WP_Error
 */
function block_external_http( $preempt, $parsed_args, $url ) {
	if ( is_allowed_http_host( wp_parse_url( $url, PHP_URL_HOST ) ) ) {
		return $preempt;
	}

	$path = (string) wp_parse_url( $url, PHP_URL_PATH );

	foreach ( QUIET_HTTP_PATHS as $quiet_path ) {
		if ( 0 === strpos( $path, $quiet_path ) ) {
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [] ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}
	}

	return new \WP_Error(
		'http_request_blocked',
		sprintf( 'External HTTP is blocked in the test environment (%s).', $url )
	);
}

add_filter( 'pre_http_request', __NAMESPACE__ . '\block_external_http', 1, 3 );

/**
 * Core dashboard widgets to drop.
 *
 * These query posts and comments and render the wordpress.org feeds. Listed
 * explicitly rather than emptying $wp_meta_boxes, so Simple History's own
 * dashboard widget survives — tests/playwright covers that one.
 */
const CORE_DASHBOARD_WIDGETS = [
	'dashboard_primary',
	'dashboard_activity',
	'dashboard_right_now',
	'dashboard_quick_press',
	'dashboard_site_health',
	'dashboard_php_nag',
	'dashboard_browser_nag',
	'network_dashboard_right_now',
];

/**
 * Remove the core dashboard widgets on whichever dashboard is rendering.
 *
 * wp_add_dashboard_widget() keys the box off the current screen id, which is
 * "dashboard" in a site admin and "dashboard-network" in a network admin, so
 * read it rather than assuming.
 */
function remove_core_dashboard_widgets() {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return;
	}

	foreach ( CORE_DASHBOARD_WIDGETS as $widget_id ) {
		foreach ( [ 'normal', 'side', 'column3', 'column4' ] as $context ) {
			remove_meta_box( $widget_id, $screen->id, $context );
		}
	}

	remove_action( 'welcome_panel', 'wp_welcome_panel' );
}

add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\remove_core_dashboard_widgets', 99 );
add_action( 'wp_network_dashboard_setup', __NAMESPACE__ . '\remove_core_dashboard_widgets', 99 );
