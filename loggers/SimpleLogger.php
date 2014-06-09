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
	* Interpolates context values into the message placeholders.
	*/
	function interpolate($message, $context = array())
	{

		if ( ! is_array($context) ) {
			return $message;
		}

		// build a replacement array with braces around the context keys
		$replace = array();
		foreach ($context as $key => $val) {
			$replace['{' . $key . '}'] = $val;
		}

		// interpolate replacement values into the message and return
		return strtr($message, $replace);

	}

	/**
	 * Returns header output for a log row
	 * Format should be common for all log rows and should be like:
	 * Username (user role) · Date
	 */
	function getLogRowHeaderOutput($row) {
		
		// HTML for sender
		$sender_html = "";
		$user_id = isset($row->context["_user_id"]) ? $row->context["_user_id"] : null;

		if ( $user_id > 0 && $user = get_user_by("id", $user_id) ) {

			// Sender is user and user still exists

			// get user role, as done in user-edit.php
			$user_roles = array_intersect( array_values( $user->roles ), array_keys( get_editable_roles() ) );
			$user_role  = array_shift( $user_roles );
			$user_display_name = $user->display_name;

			$sender_html = sprintf(
				'
				<strong>%3$s</strong>
				<!-- <span class="discrete">(%2$s)</span> -->
				',
				$user->user_login,
				$user->user_email,
				$user_display_name
			);

		} else if ($user_id > 0) {
				
			// Sender was a user, but user is deleted now
			$sender_html = sprintf(
				'<strong>Deleted user</strong>'
			);

		} else {

			// Not a user
			$sender_html = sprintf(
				'<strong>System</strong>'
			);

		}

		// HTML for date
		// Date (should...) always exist
		// http://developers.whatwg.org/text-level-semantics.html#the-time-element
		$date_html = "";
		$str_when = "";
		$date_datetime = new DateTime($row->date);
		
		// Show "ago"-time when event is xx seconds ago or earlier
		$time_ago_max_time = DAY_IN_SECONDS * 2;
		if ( time() - $date_datetime->getTimestamp() > $time_ago_max_time ) {
			/* translators: Publish box date format, see http://php.net/date */
			$datef = __( 'M j, Y @ G:i' );
			$str_when = date_i18n( $datef, $date_datetime->getTimestamp() );

		} else {
			$date_human_time_diff = human_time_diff( $date_datetime->getTimestamp(), time() );
			/* translators: 1: last modified date and time in human time diff-format */
			$str_when = sprintf( __( '%1$s ago', 'simple-history/log-header-output-time-ago' ), $date_human_time_diff );
		}

		$date_html = sprintf(
			'
				<time datetime="%1$s">
					%2$s
				</time>
			',
			$date_datetime->format(DateTime::RFC3339), // 1 datetime attribute
			$str_when
		);

		// Glue together final result
		$html = sprintf(
			'
			%1$s · %2$s
			'
			,
			$sender_html,
			$date_html
		);

		return $html;

	}

	/**
	 * Returns the plain text version of this entry
	 * Used in for example CSV-exports.
	 * Defaults to log message with context interpolated.
	 * Keep format as plain and simple as possible.
	 * Links are ok, for example to link to users or posts.
	 * Tags will be stripped when text is used for CSV-exports and so on.
	 * Keep it on a single line. No <p> or <br> and so on.
	 *
	 * Example output:
	 * Edited post "About the company"
	 *
	 * Message should sound like it's coming from the user.
	 * Image that the name of the user is added in front of the text:
	 * Jessie James: Edited post "About the company"
	 */
	public function getLogRowPlainTextOutput($row) {
	
		$message = $this->interpolate($row->message, $row->context);

		// All messages are escaped by default. 
		// If you need unescaped output override this method
		// in your own logger
		$message = esc_html($message);

		return $message;

	}

	/**
	 * Get output for image
	 * Image can be for example gravar if sender is user,
	 * or other images if sender i system, wordpress, and so on
	 */
	public function getLogRowSenderImageOutput($row) {

		$sender_image_html = "";
		$sender_image_size = 38; // 32

		$user_id = isset($row->context["_user_id"]) ? $row->context["_user_id"] : null;

		if ( $user_id > 0 && $user = get_user_by("id", $user_id) ) {

			// Sender was user
			$sender_image_html = get_avatar( $user->user_email, $sender_image_size );	

		} else if ($user_id > 0) {
				
			// Sender was a user, but user is deleted now
			$sender_image_html = get_avatar( "", $sender_image_size );	

		} else {

			$sender_image_html = get_avatar( "", $sender_image_size );	

		}
	
		return $sender_image_html;

	}

	/**
	 * Use this method to output detailed output for a log row
	 * Example usage is if a user has uploaded an image then a
	 * thumbnail of that image can bo outputed here
	 *
	 * @param object $row 
	 * @return string HTML-formatted output
	 */
	public function getLogRowDetailsOutput($row) {

		$html = "";
		return $html;

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

			if ( ! is_array($context) ) {
				$context = array();
			}

			// Automatically append some context
			// If they are not already set
			$current_user = wp_get_current_user();
			
			if ( ! isset( $context["_user_id"] ) ) {
				
				if ( isset($current_user->ID) && $current_user->ID) {
					$context["_user_id"] = $current_user->ID;
					$context["_user_login"] = $current_user->user_login;
					$context["_user_email"] = $current_user->user_email;
				}

			}

			if ( ! isset( $context["_user_agent"] ) ) {
				$context["_http_user_agent"] = $_SERVER["HTTP_USER_AGENT"];
			}

			if ( ! isset( $context["_remote_addr"] ) ) {
				$context["_remote_addr"] = $_SERVER["REMOTE_ADDR"];
			}

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

