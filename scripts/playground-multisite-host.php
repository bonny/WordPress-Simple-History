<?php
/**
 * Blueprint runPHP step: pin $_SERVER['HTTP_HOST'] in wp-config.php.
 *
 * Injected by scripts/parallel-dev.sh when `up` runs with --multisite.
 * Playground's internal requests (blueprint steps, auto-login, wp-cli)
 * arrive as 127.0.0.1:<port>, which is not the network's domain, so a
 * multisite WordPress redirects every one of them to the network home
 * and later steps fail. Playground's own enableMultisite step solves
 * this by hardcoding HTTP_HOST at the top of wp-config.php; this does
 * the same, with the port kept.
 *
 * Marker "sh-parallel-dev-multisite" lets parallel-dev.sh find and strip
 * this step when re-generating a blueprint.
 *
 * The host is not read from a constant: whether Playground's
 * defineWpConfigConsts constants are visible inside a bare runPHP step
 * (which does not bootstrap WordPress) is not established. Instead
 * parallel-dev.sh substitutes the literal placeholder below into this
 * file's code before it ever reaches Playground — see
 * blueprint_add_multisite_steps().
 *
 * The substituted host can never legitimately be an empty string; the
 * guard below instead checks for the placeholder itself surviving
 * un-substituted, which means parallel-dev.sh's substitution failed.
 *
 * @package SimpleHistoryDev
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPressVIPMinimum.Performance.FetchingRemoteData, WordPressVIPMinimum.Functions.RestrictedFunctions -- runs before WordPress loads, as a CLI script writing a local file.

$sh_dev_config_path = '/wordpress/wp-config.php';
$sh_dev_host        = '__SH_DEV_MULTISITE_HOST__';

if ( $sh_dev_host === '' || strpos( $sh_dev_host, '__SH_DEV' ) === 0 ) {
	echo "sh-parallel-dev-multisite: host placeholder was not substituted by parallel-dev.sh\n";
	exit( 1 );
}

$sh_dev_config = file_get_contents( $sh_dev_config_path );

if ( $sh_dev_config === false ) {
	echo "sh-parallel-dev-multisite: cannot read {$sh_dev_config_path}\n";
	exit( 1 );
}

// Idempotent: a re-run of the blueprint must not stack a second assignment.
if ( strpos( $sh_dev_config, "\$_SERVER['HTTP_HOST']" ) === false ) {
	$sh_dev_line   = "\$_SERVER['HTTP_HOST'] = '" . addslashes( $sh_dev_host ) . "';\n";
	$sh_dev_config = preg_replace( '/^<\?php\s*/i', "<?php\n" . $sh_dev_line, $sh_dev_config, 1 );

	if ( file_put_contents( $sh_dev_config_path, $sh_dev_config ) === false ) {
		echo "sh-parallel-dev-multisite: cannot write {$sh_dev_config_path}\n";
		exit( 1 );
	}
}
