<?php

namespace Simple_History\Loggers;

/**
 * A PSR-3 inspired logger class
 * This class logs + formats logs for display in the Simple History GUI/Viewer
 *
 * Extend this class to make your own logger
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md PSR-3 specification
 */
class SimpleLogger extends Logger {
	/**
	 * Unique slug for this logger
	 * Will be saved in DB and used to associate each log row with its logger.
	 *
	 * @var string
	 */
	public $slug = 'SimpleLogger';

	/**
	 * Get array with information about this logger.
	 *
	 * @return array
	 */
	public function getInfo() {
		$arr_info = array(
			// Shown on the info-tab in settings, use these fields to tell
			// an admin what your logger is used for.
			'name'        => 'SimpleLogger',
			'description' => __( 'The built in logger for Simple History', 'simple-history' ),

			// Capability required to view log entries from this logger
			'capability' => 'edit_pages',
			'messages'   => array(
				// No pre-defined variants
				// when adding messages __() or _x() must be used
			),
		);

		return $arr_info;
	}
}
