#!/usr/bin/env php
<?php
/**
 * Generate Simple History autoloader classmap.
 *
 * This standalone script generates the classmap file without requiring
 * WordPress or WP-CLI to be loaded. Useful for CI/build pipelines.
 *
 * Usage:
 *   npm run classmap:generate
 *
 * @package Simple_History
 * @since 5.23.0
 */

// Ensure running from command line.
if ( php_sapi_name() !== 'cli' ) {
	exit( 'This script must be run from the command line.' );
}

// Define plugin path constant.
define( 'SIMPLE_HISTORY_PATH', dirname( __DIR__ ) . '/' );

// Load the classmap generator.
require_once SIMPLE_HISTORY_PATH . 'inc/class-classmap-generator.php';

echo "Simple History Classmap Generator\n";
echo str_repeat( '-', 40 ) . "\n";

// Create generator and generate classmap.
$generator = new Simple_History\Classmap_Generator( SIMPLE_HISTORY_PATH );

echo "Scanning directories:\n";
foreach ( $generator->get_directories() as $dir ) {
	echo "  - {$dir}/\n";
}
echo "\n";

$classmap = $generator->generate();

printf( "Found %d classes.\n", count( $classmap ) );

// Write to file.
$output_file = SIMPLE_HISTORY_PATH . 'inc/classmap-generated.php';

if ( $generator->write_classmap_file( $output_file, $classmap ) ) {
	printf( "Generated classmap at: %s\n", $output_file );
	echo "\nDone!\n";
	echo "\nTo enable the optimized autoloader, set the WordPress option:\n";
	echo "  wp option update simple_history_optimized_autoloader_enabled 1\n";
	exit( 0 );
} else {
	echo "ERROR: Failed to write classmap file.\n";
	echo "Check file permissions.\n";
	exit( 1 );
}
