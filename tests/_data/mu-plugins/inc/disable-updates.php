<?php

/**
 * Must use plugin used in tests to make sure WordPress does not find any updates to
 * core, plugins, or themes, as they will add entries to the log and mess up the tests.
 */

namespace Simple_History\tests;

defined( 'ABSPATH' ) || die();

add_filter( "pre_set_site_transient_update_themes", __NAMESPACE__ . '\handle_themes_and_plugins_updated_transients', 20, 2 );
add_filter( "pre_set_site_transient_update_plugins", __NAMESPACE__ . '\handle_themes_and_plugins_updated_transients', 20, 2 );
add_filter( "pre_set_site_transient_update_core", __NAMESPACE__ . '\handle_themes_and_plugins_updated_transients', 20, 2 );

/**
 * Filters the value of a specific site transient before it is set.
 *
 * The dynamic portion of the hook name, `$transient`, refers to the transient name.
 *
 * @since 3.0.0
 * @since 4.4.0 The `$transient` parameter was added.
 *
 * @param mixed  $value     New value of site transient.
 * @param string $transient Transient name.
 */
function handle_themes_and_plugins_updated_transients( $value, $transient ) {
    // Set last checked to now to prevent the themes to be checked for updates.
    $value->last_checked = time();

    // Unset any found updated themes and plugins.
    $value->response = array();

    // Unset any found WordPress core update.
    $value->updates = array();

    return $value;
}
