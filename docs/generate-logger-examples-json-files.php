#!/usr/bin/env php
<?php
/**
 * Get examples from loggers.
 */

declare(strict_types=1);


// namespace Simple_History\WPExamplesGenerator;

require_once 'vendor/autoload.php';

function _x( $str ) {
	return $str;
}

function __( $str ) {
	return $str;
}

$loggers_dir = __DIR__ . '/../loggers/';

$loggers = scandir( $loggers_dir );

// Loggers require the main logger file to be loaded since they extend it.
define( 'ABSPATH', __DIR__ );
include "{$loggers_dir}/SimpleLogger.php";

$instantiated_classes = array();

foreach ( $loggers as $logger_file ) {
	$logger_file_and_path = "{$loggers_dir}/{$logger_file}";

	if ( $logger_file === 'SimpleLogger.php' ) {
		continue;
	}

	if ( ! is_file( $logger_file_and_path ) ) {
		continue;
	}

	// https://stackoverflow.com/questions/7153000/get-class-name-from-file
	$classes = get_declared_classes();
	include $logger_file_and_path;
	$diff = array_diff( get_declared_classes(), $classes );
	$loaded_class_name = reset( $diff );

	$reflection_class = new \ReflectionClass( $loaded_class_name );

	$instantiated_class = $reflection_class->newInstanceWithoutConstructor();

	if ( ! method_exists( $instantiated_class, 'getInfo' ) ) {
		continue;
	}
	//var_dump( $instantiated_class->getInfo() );
	$instantiated_classes[] = $instantiated_class;
}

foreach ( $instantiated_classes as $one_instantiated_class ) {
	$logger_info = $one_instantiated_class->getInfo();
	if ( empty( $logger_info['examples'] ) ) {
		continue;
	}
	$name = $logger_info['name'];
	$description = $logger_info['description'];
	$messages = $logger_info['messages'];
	$examples = $logger_info['examples'];
	echo "==========";
	echo "\nlogger: $name";
	echo "\ndescription: $description";
	echo "\nmessages:\n";
	print_r( $messages );
	echo "\nexamples:\n";
	print_r( $examples );
}
