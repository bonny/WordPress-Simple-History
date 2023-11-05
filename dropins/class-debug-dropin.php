<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin Name: Debug
 * Dropin Description: Add some extra info to each logged context when SIMPLE_HISTORY_LOG_DEBUG is set and true
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */
class Debug_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		// Bail if Simple History debug mode is not active.
		if ( false === Helpers::log_debug_is_enabled() ) {
			return;
		}

		add_action( 'simple_history/log_argument/context', array( $this, 'onLogArgumentContext' ), 10, 4 );
	}

	/**
	 * Modify the context to add debug information.
	 *
	 * @param array                                 $context Context array.
	 * @param string                                $level Log level.
	 * @param string                                $message Log message.
	 * @param \Simple_History\Loggers\Simple_Logger $logger Logger instance.
	 */
	public function onLogArgumentContext( $context, $level, $message, $logger ) {
		$context['_debug_get'] = Helpers::json_encode( $_GET );
		$context['_debug_post'] = Helpers::json_encode( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$context['_debug_server'] = Helpers::json_encode( $_SERVER );
		$context['_debug_files'] = Helpers::json_encode( $_FILES ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$context['_debug_php_sapi_name'] = php_sapi_name();

		global $argv;
		$context['_debug_argv'] = Helpers::json_encode( $argv );

		$consts = get_defined_constants( true );
		$consts = $consts['user'];
		$context['_debug_user_constants'] = Helpers::json_encode( $consts );

		$postdata = file_get_contents( 'php://input' );
		$context['_debug_http_raw_post_data'] = Helpers::json_encode( $postdata );

		$context['_debug_wp_debug_backtrace_summary'] = wp_debug_backtrace_summary();
		$context['_debug_is_admin'] = json_encode( is_admin() );
		$context['_debug_is_ajax'] = json_encode( defined( 'DOING_AJAX' ) && DOING_AJAX );
		$context['_debug_is_doing_cron'] = json_encode( defined( 'DOING_CRON' ) && DOING_CRON );
		$context['_debug_is_multisite'] = is_multisite();

		global $wp_current_filter;
		$context['_debug_current_filter_array'] = $wp_current_filter;
		$context['_debug_current_filter'] = current_filter();

		return $context;
	}
}
