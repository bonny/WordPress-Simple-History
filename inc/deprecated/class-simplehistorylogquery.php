<?php

use Simple_History\Log_Query;

/**
 * Deprecated, use Simple_History\Log_Query instead.
 *
 * Un-namespaced class for old loggers that call \SimpleHistoryLogQuery.
 */
class SimpleHistoryLogQuery {
	/**
	 * Only function "query" exists on old class.
	 *
	 * @since 4.0
	 * @param array $args Query args.
	 * @return array
	 */
	public function query( $args ) {
		return ( new Log_Query() )->query( $args );
	}
}
