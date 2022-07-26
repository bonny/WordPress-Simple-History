<?php

/**
 * Global helper functions.
 */

use SimpleHistory\SimpleHistory;
use SimpleHistory\Loggers\SimpleLogger;

/**
 * Helper function with same name as the SimpleLogger-class
 *
 * @example Log a message to the log.
 *
 * ```php
 * SimpleLogger()->info("This is a message sent to the log");
 * ```
 *
 * @return SimpleLogger
 */
if ( ! function_exists( 'SimpleLogger' ) ) {
	function SimpleLogger() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		return new SimpleLogger( SimpleHistory::get_instance() );
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
				$bool_string = true === $var ? 'true' : 'false';
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
 * sh_d('Values fromm $_GET', $_GET);
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
				$bool_string = true === $var ? 'true' : 'false';
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

			if ( $loopOutput ) {
				$maybe_escaped_loop_output = 'cli' === php_sapi_name() ? $loopOutput : esc_html( $loopOutput );

				$output = $output . sprintf(
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

/**
 * Return a name for a callable.
 *
 * Examples of return values:
 * - WP_REST_Posts_Controller::get_items
 * - WP_REST_Users_Controller::get_items"
 * - WP_REST_Server::get_index
 * - Redirection_Api_Redirect::route_bulk
 * - wpcf7_rest_create_feedback
 * - closure
 *
 * Function based on code found on stack overflow:
 * https://stackoverflow.com/questions/34324576/print-name-or-definition-of-callable-in-php
 *
 * @param callable $callable The callable thing to check.
 * @return string Name of callable.
 */
function sh_get_callable_name( $callable ) {
	if ( is_string( $callable ) ) {
		return trim( $callable );
	} elseif ( is_array( $callable ) ) {
		if ( is_object( $callable[0] ) ) {
			return sprintf( '%s::%s', get_class( $callable[0] ), trim( $callable[1] ) );
		} else {
			return sprintf( '%s::%s', trim( $callable[0] ), trim( $callable[1] ) );
		}
	} elseif ( $callable instanceof Closure ) {
		return 'closure';
	} else {
		return 'unknown';
	}
}

/**
 * Get the current screen object.
 * Returns an object with all attributes empty if functions is not found or if function
 * returns null. Makes it easier to use get_current_screen when we don't have to
 * check for function existence and or null.
 *
 * @return WP_Screen|Object Current screen object or object with empty attributes when screen not defined.
 */
function simple_history_get_current_screen() {
	if ( function_exists( 'get_current_screen' ) ) {
		$current_screen = get_current_screen();
		if ( $current_screen instanceof WP_Screen ) {
			return $current_screen;
		}
	}

	// No screen found.
	return (object) array(
		'action' => null,
		'base' => null,
		'id' => null,
		'is_network' => null,
		'is_user' => null,
		'parent_base' => null,
		'parent_file' => null,
		'post_type' => null,
		'taxonomy' => null,
		'is_block_editor' => null,
	);
}
