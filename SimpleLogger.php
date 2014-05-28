<?php

SimpleLogger::info("User {username} edited page {pagename}");
SimpleLogger::notice("User {username} edited page {pagename}", array("username" => "bonnyerden", "pagename" => "My test page"));

/**
 * 
 */
class SimpleLogger
{

	/**
	 * Unique slug for this logger
	 * Will be saved in DB and used to associate each log row with its logger
	 */
	public $slug = "SimpleLogger";
	public $db_table = "simple_history";
	public $db_table_contexts = "simple_history_contexts";

	public function __construct() {
		
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

		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::EMERGENCY, $message, $context);

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
		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::ALERT, $message, $context);
		
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

		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::CRITICAL, $message, $context);

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

		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::ERROR, $message, $context);
		
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
		
		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::WARNING, $message, $context);

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

		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::NOTICE, $message, $context);

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

		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::INFO, $message, $context);
		
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

		$Logger = new SimpleLogger();
		$Logger->log(SimpleLoggerLogLevels::DEBUG, $message, $context);
		
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

		$db_table = $wpdb->prefix . $this->db_table;
		$db_table = apply_filters("simple_logger_db_table", $db_table);

		// date, store at utc or local time
		// anything is better than now() anyway!
		// WP seems to use the local time, so I will go with that too I think
		// GMT/UTC-time is: date_i18n($timezone_format, false, 'gmt')); 
		// local time is: date_i18n($timezone_format));
		$localtime = current_time("mysql");
		
		$data = array(
			"logger" => $this->slug,
			"date" => $localtime,
			"level" => $level,
			"message" => $message,
		);

		$result = $wpdb->insert( $db_table, $data );

		if ( false === $result ) {
			return;
		}

		$history_inserted_id = $wpdb->insert_id; 

		// Add context
		$db_table_contexts = $wpdb->prefix . $this->db_table_contexts;
		$db_table_contexts = apply_filters("simple_logger_db_table_contexts", $db_table_contexts);

		if ( is_array($context) ) {

			foreach ($context as $key => $value) {

				$data = array(
					"history_id" => $history_inserted_id,
					"key" => $key,
					"value" => $value,
				);

				$result = $wpdb->insert( $db_table_contexts, $data );

			}

		}

		echo "\nlogger: $this->slug";
		echo "\nlog level: $level";
		echo "\nlog message: $message";
		echo "\nlog context: " . print_r($context, true);

	}
	
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

