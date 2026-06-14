<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Log_Query;

/**
 * Helper for running Log_Query from WP-CLI commands.
 */
class WP_CLI_Query_Helper {
	/**
	 * Run a Log_Query with the logger read capability check overridden.
	 *
	 * If you can run WP-CLI commands you can read all loggers. The override
	 * is removed in a finally block so it never leaks into the rest of the
	 * process when query() throws on invalid arguments.
	 *
	 * @param array $query_args Arguments for Log_Query::query().
	 * @return array|\WP_Error Query results.
	 * @throws \InvalidArgumentException When query arguments are invalid.
	 */
	public static function query_with_full_read_access( $query_args ) {
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 0 );

		try {
			return ( new Log_Query() )->query( $query_args );
		} finally {
			remove_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10 );
		}
	}
}
