<?php

defined( 'ABSPATH' ) || die();

/**
 * Dropin Name: Debug
 * Dropin Description: Add some extra info to each logged context when SIMPLE_HISTORY_LOG_DEBUG is set and true
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */
class SimpleHistoryDebugDropin {

	public function __construct( $sh ) {
		// Bail if Simple History debug mode is not active.
		if ( ! defined( 'SIMPLE_HISTORY_LOG_DEBUG' ) || ! SIMPLE_HISTORY_LOG_DEBUG ) {
			return;
		}

		add_action( 'simple_history/log_argument/context', array( $this, 'onLogArgumentContext' ), 10, 4 );
	}

	/**
	 * Modify the context to add debug information.
	 *
	 * @param array $context
	 * @param string $level
	 * @param string $message
	 * @param SimpleLogger $logger
	 */
	public function onLogArgumentContext( $context, $level, $message, $logger ) {
		$sh = SimpleHistory::get_instance();
		$context['_debug_get'] = $sh->json_encode( $_GET );
		$context['_debug_post'] = $sh->json_encode( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$context['_debug_server'] = $sh->json_encode( $_SERVER );
		$context['_debug_files'] = $sh->json_encode( $_FILES );
		$context['_debug_php_sapi_name'] = php_sapi_name();

		global $argv;
		$context['_debug_argv'] = $sh->json_encode( $argv );

		$consts = get_defined_constants( true );
		$consts = $consts['user'];
		$context['_debug_user_constants'] = $sh->json_encode( $consts );

		$postdata = file_get_contents( 'php://input' );
		$context['_debug_http_raw_post_data'] = $sh->json_encode( $postdata );

		$context['_debug_wp_debug_backtrace_summary'] = wp_debug_backtrace_summary();
		$context['_debug_is_admin'] = json_encode( is_admin() );
		$context['_debug_is_ajax'] = json_encode( defined( 'DOING_AJAX' ) && DOING_AJAX );
		$context['_debug_is_doing_cron'] = json_encode( defined( 'DOING_CRON' ) && DOING_CRON );

		global $wp_current_filter;
		$context['_debug_current_filter_array'] = $wp_current_filter;
		$context['_debug_current_filter'] = current_filter();

		return $context;
	}
}
