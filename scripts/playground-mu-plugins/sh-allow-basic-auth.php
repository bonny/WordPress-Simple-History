<?php
/**
 * Plugin Name: Simple History Dev — Basic Auth Passthrough
 * Description: Lets REST and Basic-auth requests through Playground's auto-login redirect. Only loaded in parallel-dev Playground instances — this file is mounted as an mu-plugin by scripts/parallel-dev.sh and is never shipped with the plugin.
 *
 * Playground's internal auto-login mu-plugin 302-redirects every request
 * that lacks its marker cookie — including cookie-less REST calls — before
 * WordPress routes them. Real mu-plugins load before Playground's internal
 * ones, so pre-setting the marker cookie here skips the redirect for
 * requests that carry credentials or target the REST API. Plain browser
 * requests still get auto-logged-in as before.
 *
 * The cookie name is a Playground internal; keeping it in this one file
 * centralizes that coupling so curl recipes stay cookie-free.
 *
 * @package SimpleHistoryDev
 */

// phpcs:disable WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification, WordPressVIPMinimum.Variables.ServerVariables -- read-only request inspection in a dev-instance mu-plugin.
$sh_dev_is_rest_request = ( isset( $_SERVER['REQUEST_URI'] ) && strpos( (string) $_SERVER['REQUEST_URI'], '/wp-json/' ) === 0 )
	|| isset( $_GET['rest_route'] );
$sh_dev_has_credentials = isset( $_SERVER['HTTP_AUTHORIZATION'] ) || isset( $_SERVER['PHP_AUTH_USER'] );
// phpcs:enable

if ( $sh_dev_is_rest_request || $sh_dev_has_credentials ) {
	// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- no page cache in Playground; this pre-sets Playground's own marker cookie.
	$_COOKIE['playground_auto_login_already_happened'] = '1';
}
