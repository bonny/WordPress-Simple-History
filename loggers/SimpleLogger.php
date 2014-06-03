<?php

/**
 * A PSR-3 inspired logger class
 * This class logs + formats logs for display in the Simple History GUI/Viewer
 *
 * Extend this class to make your own logger
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md PSR-3 specification
 */
class SimpleLogger
{

	/**
	 * Unique slug for this logger
	 * Will be saved in DB and used to associate each log row with its logger
	 */
	public $slug = "SimpleLogger";

	/**
	 * Name of tables to use. Will be prefixed with $wpdb->prefix before use.
	 */
	public $db_table = SimpleHistory::DBTABLE;
	public $db_table_contexts = "simple_history_contexts";

	/**
	 * ID of last inserted row. Used when chaining methods.
	 */
	private $lastInsertID = null;

	public function __construct() {
		
	}

	/**
	 * Returns the plain text version of this entry
	 * Used in for example CSV-exports.
	 * Defaults to log message with context interpolated
	 */
	public function getLogRowPlainTextOutput($row) {
		sf_d(__FUNCTION__);
		sf_d($row->date);
		sf_d($row->level);
		sf_d($row->message);
		sf_d($row->subsequentOccations);
		sf_d($row->contexts);
	}

	/**
	 * Generate and return output for a row in the Simple History GUI
	 * User, date, and plain text message is outputed automatically,
	 * but extra info can be outputed here. Example: if a log is about an image, 
	 * an thumbnail of the image can be outputed here.
	 * See @TODO add link to site here for example/guidelines.
	 *
	 * @return string Formatted HTML
	 */
	public function getLogRowHTMLOutput($row) {

	}

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public static function emergency($message, array $context = array())
	{

		return $this->log(SimpleLoggerLogLevels::EMERGENCY, $message, $context);

	}
	
	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public static function alert($message, array $context = array())
	{
		return $this->log(SimpleLoggerLogLevels::ALERT, $message, $context);
		
	}
	
	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public static function critical($message, array $context = array())
	{

		return $this->log(SimpleLoggerLogLevels::CRITICAL, $message, $context);

	}
	
	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function error($message, array $context = array())
	{

		return $this->log(SimpleLoggerLogLevels::ERROR, $message, $context);
		
	}
	
	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function warning($message, array $context = array())
	{
		
		return $this->log(SimpleLoggerLogLevels::WARNING, $message, $context);

	}
	
	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function notice($message, array $context = array())
	{

		return $this->log(SimpleLoggerLogLevels::NOTICE, $message, $context);

	}
	
	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function info($message, array $context = array())
	{

		return $this->log(SimpleLoggerLogLevels::INFO, $message, $context);
		
	}
	
	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function debug($message, array $context = array())
	{

		return $this->log(SimpleLoggerLogLevels::DEBUG, $message, $context);
		
	}
	
	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function log($level, $message, array $context = array())
	{
		
		global $wpdb;

		/* Store date at utc or local time
		 * anything is better than now() anyway!
		 * WP seems to use the local time, so I will go with that too I think
		 * GMT/UTC-time is: date_i18n($timezone_format, false, 'gmt')); 
		 * local time is: date_i18n($timezone_format));
		 */
		$localtime = current_time("mysql");

		$db_table = $wpdb->prefix . $this->db_table;
		$db_table = apply_filters("simple_logger_db_table", $db_table);
		
		$data = array(
			"logger" => $this->slug,
			"date" => $localtime,
			"level" => $level,
			"message" => $message,
		);

		// Setup occasions id
		$found_occasions_id = false;
		$occasions_id = null;
		if (isset($context["_occasionsID"])) {
		
			$occasions_id = md5( $context["_occasionsID"] );
			unset( $context["_occasionsID"] );

		} else {

			// No occasions id specified, create one
			$occasions_data = array(
				"logger" => $this->slug,
				"level" => $level,
				"message" => $message,
				"context" => $context
			);
			
			$occasions_id = md5( json_encode($occasions_data) );

		}

		$data["occasionsID"] = $occasions_id;

		$result = $wpdb->insert( $db_table, $data );

		// Only save context if able to store row
		if ( false === $result ) {

			$history_inserted_id = null;

		} else {
		
			$history_inserted_id = $wpdb->insert_id; 

			// Add context
			$db_table_contexts = $wpdb->prefix . $this->db_table_contexts;
			$db_table_contexts = apply_filters("simple_logger_db_table_contexts", $db_table_contexts);

			if ( is_array($context) ) {

				// Save each context value
				foreach ($context as $key => $value) {

					$data = array(
						"history_id" => $history_inserted_id,
						"key" => $key,
						"value" => $value,
					);

					$result = $wpdb->insert( $db_table_contexts, $data );

				}

			}
		}
		
		$this->lastInsertID = $history_inserted_id;

		// Return $this so we can chain methods
		return $this;

	} // log

	
}

/**
 * Describes log levels
 */
class SimpleLoggerLogLevels
{
	const EMERGENCY = 'emergency';
	const ALERT = 'alert';
	const CRITICAL = 'critical';
	const ERROR = 'error';
	const WARNING = 'warning';
	const NOTICE = 'notice';
	const INFO = 'info';
	const DEBUG = 'debug';
}

