<?php

use Simple_History\Simple_History;

/**
 * Deprecated, use \Simple_History\Simple_History instead.
 *
 * Un-namespaced class for old loggers that call \SimpleHistory->get_instance().
 */
class SimpleHistory {
	/**
	 * Only static function in old class is get_instance().
	 *
	 * @since 4.0
	 */
	public static function get_instance() {
		return Simple_History::get_instance();
	}
}
