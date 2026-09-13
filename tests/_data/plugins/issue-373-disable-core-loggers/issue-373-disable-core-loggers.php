<?php
/**
 * Plugin Name:  issue-373-disable-core-loggers
 * Description:  Plugin to test issue 373.
 * Version:      1.0
 */

// https://github.com/bonny/WordPress-Simple-History/issues/373#issuecomment-1640207847

# Disable all core loggers.
add_filter( 'simple_history/core_loggers', '__return_empty_array' );

// use Simple_History\Loggers;

// add_filter( 'simple_history/core_loggers', function() {
// 	$loggers = array(
// 		// Loggers\Available_Updates_Logger::class,
// 		// Loggers\File_Edits_Logger::class,
// 		// Loggers\Plugin_ACF_Logger::class,
// 		// Loggers\Plugin_Beaver_Builder_Logger::class,
// 		// Loggers\Plugin_Duplicate_Post_Logger::class,
// 		// Loggers\Plugin_Limit_Login_Attempts_Logger::class,
// 		// Loggers\Plugin_Redirection_Logger::class,
// 		// Loggers\Plugin_Enable_Media_Replace_Logger::class,
// 		// Loggers\Plugin_User_Switching_Logger::class,
// 		// Loggers\Plugin_WP_Crontrol_Logger::class,
// 		// Loggers\Plugin_Jetpack_Logger::class,
// 		// Loggers\Privacy_Logger::class,
// 		// Loggers\Translations_Logger::class,
// 		// Loggers\Categories_Logger::class,
// 		// Loggers\Comments_Logger::class,
// 		// Loggers\Core_Updates_Logger::class,
// 		// Loggers\Export_Logger::class,
// 		// Loggers\Simple_Logger::class,
// 		// Loggers\Media_Logger::class,
// 		// Loggers\Menu_Logger::class,
// 		// Loggers\Options_Logger::class,
// 		// Loggers\Plugin_Logger::class,
// 		// Loggers\Post_Logger::class,
// 		// Loggers\Theme_Logger::class,
// 		// Loggers\User_Logger::class,
// 	);
// 	return $loggers;
// } );
