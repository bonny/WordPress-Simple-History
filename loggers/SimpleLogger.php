<?php

/*
@TODO:

In old code this was how we detected occasions:

&& $one_row->action == $prev_row->action
&& $one_row->object_type == $prev_row->object_type
&& $one_row->object_type == $prev_row->object_type
&& $one_row->object_subtype == $prev_row->object_subtype
&& $one_row->user_id == $prev_row->user_id
&& (
		(!empty($one_row->object_id) && !empty($prev_row->object_id))
		&& ($one_row->object_id == $prev_row->object_id)
		|| ($one_row->object_name == $prev_row->object_name)
)

How should we do that in the new version?

Common keys include:
 - level (notice)
 - logger (SimplePostsLogger)
 - message (Post {postname} was updated by user {username})
 - userID (13) @TODO: add this when saving

Example: post edited: the same post, should be edited by the same user, with no other entried logged between
Currently that can not be determined. Solution: each logger stores a "key" that determines if an
event and another event can be considered the same. 

For example posts: create a key that is a combination of:
userID + postID + status changed

Login attempts:
loginUserEmail + status failed

Logga 404-errors
status404 + document URI



*/

/**
 * Helper function with same name as our class
 * Makes call like this possible:
 * SimpleLogger()->info("This is a message sent to the log");
 */
function SimpleLogger() {
	return new SimpleLogger();
}

// Example usage
SimpleLogger()->info("This is a message sent to the log")->withOccasionID("user" . 56423 . "edited" . "post" . 45346);
#SimpleLogger()->info("This is a message sent to the log");
#SimpleLogger()->info("User admin edited page 'About our company'");

// Example usage with context
#SimpleLogger()->notice("User {username} edited page {pagename}", array("username" => "bonnyerden", "pagename" => "My test page"));

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
	public $db_table = "simple_history";
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
	public function getLogRowPlainTextOutput($level, $message, array $context = array()) {

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
	public function getLogRowHTMLOutput($level, $message, array $context = array()) {

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

	/**
	 * Store an occasion id for the last inserted log row
	 */
	public function withOccasionID($idString) {

		// sf_d( $this->lastInsertID );
		global $wpdb;
		//  <?php $wpdb->update( $table, $data, $where, $format = null, $where_format = null ); 

		$db_table = $wpdb->prefix . $this->db_table;
		$db_table = apply_filters("simple_logger_db_table", $db_table);

		$data = array(
			"occasionID" => $idString
		);

		$where = array(
			"id" => $this->lastInsertID
		);

		$wpdb->update($db_table, $data, $where);

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

