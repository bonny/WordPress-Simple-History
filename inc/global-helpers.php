<?php

/**
 * Global helper functions.
 *
 * Not namespaced, so they are available everywhere.
 */

use Simple_History\Simple_History;
use Simple_History\Loggers\Simple_Logger;

/**
 * Helper function with same name as the SimpleLogger-class
 *
 * @example Log a message to the log.
 *
 * ```php
 * SimpleLogger()->info("This is a message sent to the log");
 * ```
 *
 * @return Simple_Logger
 */
if ( ! function_exists( 'SimpleLogger' ) ) {
	function SimpleLogger() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return new Simple_Logger( Simple_History::get_instance() );
	}
}

/**
 * Log variable(s) to error log.
 * Any number of variables can be passed and each variable is print_r'ed to the error log.
 *
 * Example usage:
 * sh_error_log(
 *   'rest_request_after_callbacks:',
 *   $handler,
 *   $handler['callback'][0],
 *   $handler['callback'][1]
 * );
 */
if ( ! function_exists( 'sh_error_log' ) ) {
	function sh_error_log() {
		foreach ( func_get_args() as $var ) {
			if ( is_bool( $var ) ) {
				$bool_string = $var ? 'true' : 'false';
				error_log( "$bool_string (boolean value)" );
			} elseif ( is_null( $var ) ) {
				error_log( 'null (null value)' );
			} else {
				error_log( print_r( $var, true ) );
			}
		}
	}
}

/**
 * Echoes any number of variables for debug purposes.
 *
 * Example usage:
 *
 * sh_d('Values from $_GET', $_GET);
 * sh_d('$_POST', $_POST);
 * sh_d('My vars', $varOne, $varTwo, $varXYZ);
 *
 * @mixed Vars Variables to output.
 */
if ( ! function_exists( 'sh_d' ) ) {
	function sh_d() {
		$output = '';

		foreach ( func_get_args() as $var ) {
			$loopOutput = '';
			if ( is_bool( $var ) ) {
				$bool_string = $var ? 'true' : 'false';
				$loopOutput = "$bool_string (boolean value)";
			} elseif ( is_null( $var ) ) {
				$loopOutput = ( 'null (null value)' );
			} elseif ( is_int( $var ) ) {
				$loopOutput = "$var (integer value)";
			} elseif ( is_numeric( $var ) ) {
				$loopOutput = "$var (numeric string)";
			} elseif ( is_string( $var ) && $var === '' ) {
				$loopOutput = "'' (empty string)";
			} else {
				$loopOutput = print_r( $var, true );
			}

			if ( $loopOutput !== '' ) {
				$maybe_escaped_loop_output = 'cli' === php_sapi_name() ? $loopOutput : esc_html( $loopOutput );

				$output .= sprintf(
					'
                <pre>%1$s</pre>
                ',
					$maybe_escaped_loop_output
				);
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}
}
