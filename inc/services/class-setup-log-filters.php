<?php

namespace Simple_History\Services;

/**
 * Class that setups logging using WP hooks.
 */
class Setup_Log_Filters extends Service {
	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		/**
		 * Action that is used to log things, without the need to check that simple history is available
		 * i.e. you can have simple history activated and log things and then you can disable the plugin
		 * and no errors will occur
		 *
		 * Usage:
		 * do_action("simple_history_log", "This is the log message");
		 * do_action("simple_history_log", "This is the log message with some extra data/info", ["extraThing1" => $variableWIihThing]);
		 * do_action("simple_history_log", "This is the log message with severity debug", null, "debug");
		 * do_action("simple_history_log", "This is the log message with severity debug and with some extra info/data logged", ["userData" => $userData, "shoppingCartDebugData" => $shopDebugData], "debug",);
		 *
		 * @since 2.13
		 */
		add_action( 'simple_history_log', array( $this, 'on_filter_simple_history_log' ), 10, 3 );

		/**
		 * Actions to log with specific log level, for example:
		 * do_action('simple_history_log_debug', 'My debug message');
		 * do_action('simple_history_log_warning', 'My warning message');
		 *
		 * @since 2.17
		 */
		add_action( 'simple_history_log_emergency', array( $this, 'on_filter_simple_history_log_emergency' ), 10, 2 );
		add_action( 'simple_history_log_alert', array( $this, 'on_filter_simple_history_log_alert' ), 10, 2 );
		add_action( 'simple_history_log_critical', array( $this, 'on_filter_simple_history_log_critical' ), 10, 2 );
		add_action( 'simple_history_log_error', array( $this, 'on_filter_simple_history_log_error' ), 10, 2 );
		add_action( 'simple_history_log_warning', array( $this, 'on_filter_simple_history_log_warning' ), 10, 2 );
		add_action( 'simple_history_log_notice', array( $this, 'on_filter_simple_history_log_notice' ), 10, 2 );
		add_action( 'simple_history_log_info', array( $this, 'on_filter_simple_history_log_info' ), 10, 2 );
		add_action( 'simple_history_log_debug', array( $this, 'on_filter_simple_history_log_debug' ), 10, 2 );
	}

	/**
	 * Log a message
	 *
	 * Function called when running filter "simple_history_log"
	 *
	 * @since 2.13
	 * @param string $message The message to log.
	 * @param array  $context Optional context to add to the logged data.
	 * @param string $level The log level. Must be one of the existing ones. Defaults to "info".
	 */
	public function on_filter_simple_history_log( $message = null, $context = null, $level = 'info' ) {
		SimpleLogger()->log( $level, $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_emergency'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_emergency( $message = null, $context = null ) {
		SimpleLogger()->log( 'emergency', $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_alert'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_alert( $message = null, $context = null ) {
		SimpleLogger()->log( 'alert', $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_critical'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_critical( $message = null, $context = null ) {
		SimpleLogger()->log( 'critical', $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_error'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_error( $message = null, $context = null ) {
		SimpleLogger()->log( 'error', $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_warning'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_warning( $message = null, $context = null ) {
		SimpleLogger()->log( 'warning', $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_notice'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_notice( $message = null, $context = null ) {
		SimpleLogger()->log( 'notice', $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_info'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_info( $message = null, $context = null ) {
		SimpleLogger()->log( 'info', $message, $context );
	}

	/**
	 * Log a message, triggered by filter 'on_filter_simple_history_log_debug'.
	 *
	 * @param string $message The message to log.
	 * @param array  $context The context (optional).
	 */
	public function on_filter_simple_history_log_debug( $message = null, $context = null ) {
		SimpleLogger()->log( 'debug', $message, $context );
	}
}
