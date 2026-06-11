<?php
/**
 * Blueprint runPHP step: provision a deterministic application password.
 *
 * Injected by scripts/parallel-dev.sh so REST calls against a Playground
 * instance work with plain Basic auth. Reads the SH_DEV_APP_USER and
 * SH_DEV_APP_PASSWORD constants that parallel-dev.sh injects via
 * defineWpConfigConsts, and creates the password through the core API —
 * pinning the generated value with the 'random_password' filter, so no
 * storage internals (meta shape, hashing, in-use flag) are replicated here.
 *
 * Marker: sh-parallel-dev-provision — parallel-dev.sh strips previously
 * injected copies of this step by matching that string.
 *
 * @package SimpleHistory
 */

// Fixed Playground docroot — no WP helpers exist before wp-load, and the
// path only exists inside the Playground instance, not on the host.
// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath
// @phpstan-ignore requireOnce.fileNotFound
require_once '/wordpress/wp-load.php';

if ( ! defined( 'SH_DEV_APP_USER' ) || ! defined( 'SH_DEV_APP_PASSWORD' ) ) {
	throw new Exception( 'parallel-dev: SH_DEV_APP_USER / SH_DEV_APP_PASSWORD constants missing — application password not provisioned.' );
}

$sh_dev_user = get_user_by( 'login', SH_DEV_APP_USER );

if ( ! $sh_dev_user ) {
	throw new Exception( 'parallel-dev: user "' . esc_html( SH_DEV_APP_USER ) . '" not found — application password not provisioned.' );
}

// Remove any previous parallel-dev password so re-provisioning is idempotent
// and never touches passwords created by other tools or blueprints.
foreach ( WP_Application_Passwords::get_user_application_passwords( $sh_dev_user->ID ) as $sh_dev_item ) {
	if ( $sh_dev_item['name'] !== 'parallel-dev' ) {
		continue;
	}

	WP_Application_Passwords::delete_application_password( $sh_dev_user->ID, $sh_dev_item['uuid'] );
}

$sh_dev_fixed_password = static function () {
	return SH_DEV_APP_PASSWORD;
};

add_filter( 'random_password', $sh_dev_fixed_password );
$sh_dev_created = WP_Application_Passwords::create_new_application_password( $sh_dev_user->ID, [ 'name' => 'parallel-dev' ] );
remove_filter( 'random_password', $sh_dev_fixed_password );

if ( is_wp_error( $sh_dev_created ) ) {
	throw new Exception( 'parallel-dev: could not create application password: ' . esc_html( $sh_dev_created->get_error_message() ) );
}
