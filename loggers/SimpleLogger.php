<?php

defined( 'ABSPATH' ) or die();

/**
 * A PSR-3 inspired logger class
 * This class logs + formats logs for display in the Simple History GUI/Viewer
 *
 * Extend this class to make your own logger
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md PSR-3 specification
 */
class SimpleLogger {

	/**
	 * Unique slug for this logger
	 * Will be saved in DB and used to associate each log row with its logger
	 */
	public $slug = __CLASS__;

	/**
	 * Will contain the untranslated messages from getInfo()
	 *
	 * By adding your messages here they will be stored both translated and non-translated
	 * You then log something like this:
	 * <code>
	 *   $this->info( $this->messages["POST_UPDATED"] );
	 * </code>
	 * or with the shortcut
	 * <code>
	 *   $this->infoMessage("POST_UPDATED");
	 * </code>
	 * which results in the original, untranslated, string being added to the log and database
	 * the translated string are then only used when showing the log in the GUI
	 */
	public $messages;

	/**
	 * ID of last inserted row. Used when chaining methods.
	 */
	public $lastInsertID;

	/**
	 * Constructor. Remember to call this as parent constructor if making a childlogger
	 * @param $simpleHistory history class  objectinstance
	 */
        public function __construct( $simpleHistory = null ) {

		global $wpdb;

		$this->db_table = $wpdb->prefix . SimpleHistory::DBTABLE;
		$this->db_table_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

		$this->simpleHistory = $simpleHistory;

	}

	/**
	 * Method that is called automagically when logger is loaded by Simple History
	 * Add your init stuff here
	 */
	public function loaded() {

	}

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(

			// The logger slug. Defaulting to the class name is nice and logical I think
			"slug" => __CLASS__,

			// Shown on the info-tab in settings, use these fields to tell
			// an admin what your logger is used for
			"name" => "SimpleLogger",
			"description" => "The built in logger for Simple History",

			// Capability required to view log entries from this logger
			"capability" => "edit_pages",
			"messages" => array(
				// No pre-defined variants
				// when adding messages __() or _x() must be used
			),

		);

		return $arr_info;

	}

	/**
	 * Return single array entry from the array in getInfo()
	 * Returns the value of the key if value exists, or null
	 *
	 * @since 2.5.4
	 * @return Mixed
	 */
	function getInfoValueByKey( $key ) {
		
		$arr_info = $this->getInfo();

		return isset( $arr_info[ $key ] ) ? $arr_info[ $key ] : null;

	}

	/**
	 * Returns the capability required to read log rows from this logger
	 *
	 * @return $string capability
	 */
	public function getCapability() {

		$arr_info = $this->getInfo();

		$capability = "manage_options";

		if ( ! empty( $arr_info["capability"] ) ) {
			$capability = $arr_info["capability"];
		}

		return $capability;

	}

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @param string $message
	 * @param array $context
	 * @param array $row Currently not always passed, because loggers need to be updated to support this...
	 */
	function interpolate( $message, $context = array(), $row = null ) {

		if ( ! is_array( $context ) ) {
			return $message;
		}

		/**
		 * Filter the context used to create the message from the message template
		 * 
		 * @since 2.2.4
		 */
		$context = apply_filters( "simple_history/logger/interpolate/context", $context, $message, $row );

		// Build a replacement array with braces around the context keys
		$replace = array();
		foreach ( $context as $key => $val ) {

			// Both key and val must be strings or number (for vals)
			if ( is_string( $key ) || is_numeric( $key ) ) {
				// key ok
			}

			if ( is_string( $val ) || is_numeric( $val ) ) {
				// val ok
			} else {
				// not a value we can replace
				continue;
			}

			$replace['{' . $key . '}'] = $val;

		}

		// Interpolate replacement values into the message and return
		/*
		if ( ! is_string( $message )) {
			echo "message:";
			var_dump($message);exit;
		}
		//*/
		/*
		if ( ! is_string( $replace )) {
			echo "replace: \n";
			var_dump($replace);
		}
		// */

		return strtr( $message, $replace );

	}

	/**
	 * Returns header output for a log row
	 * Format should be common for all log rows and should be like:
	 * Username (user role) · Date
	 * @return string HTML
	 */
	function getLogRowHeaderOutput($row) {

		// HTML for initiator
		$initiator_html = "";

		$initiator = $row->initiator;
		$context = $row->context;

		switch ($initiator) {

			case "wp":
				$initiator_html .= '<strong class="SimpleHistoryLogitem__inlineDivided">WordPress</strong> ';
				break;

			case "wp_cli":
				$initiator_html .= '<strong class="SimpleHistoryLogitem__inlineDivided">WP-CLI</strong> ';
				break;

			// wp_user = wordpress uses, but user may have been deleted since log entry was added
			case "wp_user":

				$user_id = isset($row->context["_user_id"]) ? $row->context["_user_id"] : null;

				if ($user_id > 0 && $user = get_user_by("id", $user_id)) {

					// Sender is user and user still exists
					$is_current_user = ($user_id == get_current_user_id()) ? true : false;

					// get user role, as done in user-edit.php
					$wp_roles = $GLOBALS["wp_roles"];
					$all_roles = (array) $wp_roles->roles;
					$user_roles = array_intersect( array_values( (array) $user->roles ), array_keys( (array) $wp_roles->roles ));
					$user_role = array_shift( $user_roles );

					$user_display_name = $user->display_name;

					/*
					 * If user who logged this is the currently logged in user
					 * skip name and email and use just "You"
					 *
					 * @param bool If you should be used
					 * @since 2.1
					 */
					$use_you = apply_filters("simple_history/header_initiator_use_you", true);

					if ( $use_you && $is_current_user ) {

						$tmpl_initiator_html = '
							<a href="%6$s" class="SimpleHistoryLogitem__headerUserProfileLink">
								<strong class="SimpleHistoryLogitem__inlineDivided">%5$s</strong>
							</a>
						';

					} else {

						$tmpl_initiator_html = '
							<a href="%6$s" class="SimpleHistoryLogitem__headerUserProfileLink">
								<strong class="SimpleHistoryLogitem__inlineDivided">%3$s</strong>
								<span class="SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__headerEmail">%2$s</span>
							</a>
						';

					}

					/**
					 * Filter the format for the user output
					 *
					 * @since 2.0
					 *
					 * @param string $format.
					 */
					$tmpl_initiator_html = apply_filters("simple_history/header_initiator_html_existing_user", $tmpl_initiator_html);

					$initiator_html .= sprintf(
						$tmpl_initiator_html,
						esc_html($user->user_login), 	// 1
						esc_html($user->user_email), 	// 2
						esc_html($user_display_name), 	// 3
						$user_role, 	// 4
						_x("You", "header output when initiator is the currently logged in user", "simple-history"),	// 5
						get_edit_user_link( $user_id ) // 6
					);

				} else if ($user_id > 0) {

					// Sender was a user, but user is deleted now
					// output all info we have
					// _user_id
					// _username
					// _user_login
					// _user_email
					$initiator_html .= sprintf(
						'<strong class="SimpleHistoryLogitem__inlineDivided">' .
						__('Deleted user (had id %1$s, email %2$s, login %3$s)', "simple-history") .
						'</strong>',
						esc_html($context["_user_id"]), // 1
						esc_html($context["_user_email"]), // 2
						esc_html($context["_user_login"]) // 3
					);

				}

				break;

			case "web_user":

				/*
				Note: server_remote_addr may not show visiting/attacking ip, if server is behind...stuff..
				Can be behind varnish cashe, or browser can for example use compression in chrome mobile
				then the real ip is behind _server_http_x_forwarded_for_0 or similar
				_server_remote_addr	66.249.81.222
				_server_http_x_forwarded_for_0	5.35.187.212
				*/

				// Check if additional IP addresses are stored, from http_x_forwarded_for and so on
				$arr_found_additional_ip_headers = $this->get_event_ip_number_headers($row);

				if ( empty( $context["_server_remote_addr"] ) ) {

					$initiator_html .= "<strong class='SimpleHistoryLogitem__inlineDivided'>" . __("Anonymous web user", "simple-history") . "</strong> ";

				} else {

					$initiator_html .= "<strong class='SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__anonUserWithIp'>";

					#if ( sizeof( $arr_found_additional_ip_headers ) ) {


						#$iplookup_link = sprintf('https://ipinfo.io/%1$s', esc_attr($context["_server_remote_addr"]));

						#$ip_numbers_joined = wp_sprintf_l('%l', array("_server_remote_addr" => $context["_server_remote_addr"]) + $arr_found_additional_ip_headers);

						/*$initiator_html .= sprintf(
							__('Anonymous user with multiple IP addresses detected: %1$s', "simple-history"),
							"<a target='_blank' href={$iplookup_link} class='SimpleHistoryLogitem__anonUserWithIp__theIp'>" . esc_html( $ip_numbers_joined ) . "</a>"
						);*/

						/*
						print_r($arr_found_additional_ip_headers);
						Array
						(
						    [_server_http_x_forwarded_for_0] => 5.35.187.212
						    [_server_http_x_forwarded_for_1] => 83.251.97.21
						)
						*/

					#} else {

						// single ip address
						$iplookup_link = sprintf('https://ipinfo.io/%1$s', esc_attr($context["_server_remote_addr"]));

						$initiator_html .= sprintf(
							__('Anonymous user from %1$s', "simple-history"),
							"<a target='_blank' href={$iplookup_link} class='SimpleHistoryLogitem__anonUserWithIp__theIp'>" . esc_html($context["_server_remote_addr"]) . "</a>"
						);

					#} // multiple ip

					$initiator_html .= "</strong> ";

					// $initiator_html .= "<strong>" . __("<br><br>Unknown user from {$context["_server_remote_addr"]}") . "</strong>";
					// $initiator_html .= "<strong>" . __("<br><br>{$context["_server_remote_addr"]}") . "</strong>";
					// $initiator_html .= "<strong>" . __("<br><br>User from IP {$context["_server_remote_addr"]}") . "</strong>";
					// $initiator_html .= "<strong>" . __("<br><br>Non-logged in user from IP  {$context["_server_remote_addr"]}") . "</strong>";

				}

				break;

			case "other":
				$initiator_html .= "<strong class='SimpleHistoryLogitem__inlineDivided'>" . _x("Other", "Event header output, when initiator is unknown", "simple-history") . "</strong>";
				break;

			// no initiator
			case null:
				// $initiator_html .= "<strong class='SimpleHistoryLogitem__inlineDivided'>Null</strong>";
				break;

			default:
				$initiator_html .= "<strong class='SimpleHistoryLogitem__inlineDivided'>" . esc_html($initiator) . "</strong>";

		}

		/**
		 * Filter generated html for the initiator row header html
		 *
		 * @since 2.0
		 *
		 * @param string $initiator_html
		 * @param object $row Log row
		 */
		$initiator_html = apply_filters("simple_history/row_header_initiator_output", $initiator_html, $row);

		// HTML for date
		// Date (should...) always exist
		// http://developers.whatwg.org/text-level-semantics.html#the-time-element
		$date_html = "";
		$str_when = "";

		// $row->date is in GMT
		$date_datetime = new DateTime( $row->date );
		
		// Current datetime in GMT
		$time_current = strtotime( current_time("mysql", 1) );
	
		/**
		 * Filter how many seconds as most that can pass since an
		 * event occured to show "nn minutes ago" (human diff time-format) instead of exact date
		 *
		 * @since 2.0
		 *
		 * @param int $time_ago_max_time Seconds
		 */
		$time_ago_max_time = DAY_IN_SECONDS * 2;
		$time_ago_max_time = apply_filters("simple_history/header_time_ago_max_time", $time_ago_max_time);

		/**
		 * Filter how many seconds as most that can pass since an
		 * event occured to show "just now" instead of exact date
		 *
		 * @since 2.0
		 *
		 * @param int $time_ago_max_time Seconds
		 */
		$time_ago_just_now_max_time = 30;
		$time_ago_just_now_max_time = apply_filters("simple_history/header_just_now_max_time", $time_ago_just_now_max_time);

		if ( $time_current - $date_datetime->getTimestamp() <= $time_ago_just_now_max_time ) {

			// show "just now" if event is very recent
			$str_when = __("Just now", "simple-history");

		} else if ( $time_current - $date_datetime->getTimestamp() > $time_ago_max_time ) {

			/* translators: Date format for log row header, see http://php.net/date */
			$datef = __('M j, Y \a\t G:i', "simple-history");
			$str_when = date_i18n( $datef, strtotime( get_date_from_gmt( $row->date ) ) );

		} else {

			// Show "nn minutes ago" when event is xx seconds ago or earlier
			$date_human_time_diff = human_time_diff($date_datetime->getTimestamp(), $time_current );
			/* translators: 1: last modified date and time in human time diff-format */
			$str_when = sprintf(__('%1$s ago', 'simple-history'), $date_human_time_diff);

		}
		
		$item_permalink = admin_url("index.php?page=simple_history_page");
		if ( ! empty( $row->id ) ) {
			$item_permalink .= "#item/{$row->id}";
		}

		$date_format = get_option('date_format') . ' - '. get_option('time_format');
		$str_datetime_title = sprintf(
			__('%1$s local time %3$s (%2$s GMT time)', "simple-history"),
			get_date_from_gmt( $date_datetime->format('Y-m-d H:i:s'), $date_format ), // 1 local time
			$date_datetime->format( $date_format ), // GMT time
			PHP_EOL // 3, new line
		);

		$date_html = "<span class='SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when SimpleHistoryLogitem__inlineDivided'>";
		$date_html .= "<a class='' href='{$item_permalink}'>";
		$date_html .= sprintf(
			'<time datetime="%3$s" title="%1$s" class="">%2$s</time>',
			esc_attr( $str_datetime_title ), // 1 datetime attribute
			esc_html( $str_when ), // 2 date text, visible in log
			$date_datetime->format( DateTime::RFC3339 ) // 3
		);
		$date_html .= "</a>";
		$date_html .= "</span>";


		/**
		 * Filter the output of the date section of the header.
		 *
		 * @since 2.5.1
		 *
		 * @param String $date_html
		 * @param array $row
		 */
		$date_html = apply_filters("simple_history/row_header_date_output", $date_html, $row);

		// Logger "via" info in header, i.e. output some extra
		// info next to the time to make it more clear what plugin etc.
		// that "caused" this event
		$via_html = "";
		$logger_name_via = $this->getInfoValueByKey("name_via");
	
		if ( $logger_name_via ) {
		
			$via_html = "<span class='SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__via'>";
			$via_html .= $logger_name_via;
			$via_html .= "</span>";
		
		}

		// Loglevel
		// SimpleHistoryLogitem--loglevel-warning
		/*
		$level_html = sprintf(
		'<span class="SimpleHistoryLogitem--logleveltag SimpleHistoryLogitem--logleveltag-%1$s">%1$s</span>',
		$row->level
		);
		 */

		// Glue together final result
		$template = '
			%1$s 
			%2$s 
			%3$s
		';
		#if ( ! $initiator_html ) {
		#	$template = '%2$s';
		#}

		$html = sprintf(
			$template,
			$initiator_html, // 1
			$date_html, // 2
			$via_html // 3
		);

		/**
		 * Filter generated html for the log row header
		 *
		 * @since 2.0
		 *
		 * @param string $html
		 * @param object $row Log row
		 */
		$html = apply_filters("simple_history/row_header_output", $html, $row);

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

		$message = $row->message;
		$message_key = $row->context["_message_key"];

		// Message is translated here, but translation must be added in
		// plain text before
		if ( empty( $message_key ) ) {

			// Message key did not exist, so check if we should translate using textdomain
			if ( ! empty( $row->context["_gettext_domain"] ) ) {
				$message = __( $message, $row->context["_gettext_domain"] );
			}

		} else {

			// Check that messages does exist
			// If we for example disable a Logger we may have references
			// to message keys that are unavailable. If so then fallback to message.
			if ( isset( $this->messages[$message_key]["translated_text"] ) ) {
				$message = $this->messages[$message_key]["translated_text"];
			} else {
				// Not message exists for message key. Just keep using message.
			}

		}

		$html = $this->interpolate($message, $row->context, $row);

		// All messages are escaped by default.
		// If you need unescaped output override this method
		// in your own logger
		$html = esc_html($html);

		/**
		 * Filter generated output for plain text output
		 *
		 * @since 2.0
		 *
		 * @param string $html
		 * @param object $row Log row
		 */
		$html = apply_filters("simple_history/row_plain_text_output", $html, $row);

		return $html;

	}

	/**
	 * Get output for image
	 * Image can be for example gravar if sender is user,
	 * or other images if sender i system, wordpress, and so on
	 */
	public function getLogRowSenderImageOutput($row) {

		$sender_image_html = "";
		$sender_image_size = 32;

		$initiator = $row->initiator;

		switch ($initiator) {

			// wp_user = wordpress uses, but user may have been deleted since log entry was added
			case "wp_user":

				$user_id = isset($row->context["_user_id"]) ? $row->context["_user_id"] : null;

				if ($user_id > 0 && $user = get_user_by("id", $user_id)) {

					// Sender was user
					$sender_image_html = $this->simpleHistory->get_avatar($user->user_email, $sender_image_size);

				} else if ($user_id > 0) {

					// Sender was a user, but user is deleted now
					$sender_image_html = $this->simpleHistory->get_avatar("", $sender_image_size);

				} else {

					$sender_image_html = $this->simpleHistory->get_avatar("", $sender_image_size);

				}

				break;

		}

		/**
		 * Filter generated output for row image (sender image)
		 *
		 * @since 2.0
		 *
		 * @param string $sender_image_html
		 * @param object $row Log row
		 */
		$sender_image_html = apply_filters("simple_history/row_sender_image_output", $sender_image_html, $row);

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

		/**
		 * Filter generated output for details
		 *
		 * @since 2.0
		 *
		 * @param string $html
		 * @param object $row Log row
		 */
		$html = apply_filters("simple_history/row_details_output", $html, $row);

		return $html;

	}

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public static function emergency($message, array $context = array()) {

		return $this->log(SimpleLoggerLogLevels::EMERGENCY, $message, $context);

	}

	/**
	 * System is unusable.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function emergencyMessage($message, array $context = array()) {

		return $this->logByMessageKey(SimpleLoggerLogLevels::EMERGENCY, $message, $context);

	}

	/**
	 * Log with message
	 * Called from infoMessage(), errorMessage(), and so on
	 *
	 * Call like this:
	 *
	 *   return $this->logByMessageKey(SimpleLoggerLogLevels::EMERGENCY, $message, $context);
	 */
	private function logByMessageKey($SimpleLoggerLogLevelsLevel, $messageKey, $context) {

		// When logging by message then the key must exist
		if ( ! isset( $this->messages[ $messageKey ]["untranslated_text"] ) ) {
			return;
		}

		/**
		 * Filter so plugins etc. can shortut logging
		 *
		 * @since 2.0.20
		 *
		 * @param true yes, we default to do the logging
		 * @param string logger slug
		 * @param string messageKey
		 * @param string log level
		 * @param array context
		 * @return bool false to abort logging
		 */
		$doLog = apply_filters("simple_history/simple_logger/log_message_key", true, $this->slug, $messageKey, $SimpleLoggerLogLevelsLevel, $context);

		if ( ! $doLog ) {
			return;
		}

		$context["_message_key"] = $messageKey;
		$message = $this->messages[ $messageKey ]["untranslated_text"];

		$this->log( $SimpleLoggerLogLevelsLevel, $message, $context );

	}


	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public static function alert($message, array $context = array()) {
		return $this->log(SimpleLoggerLogLevels::ALERT, $message, $context);

	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function alertMessage($message, array $context = array()) {

		return $this->logByMessageKey(SimpleLoggerLogLevels::ALERT, $message, $context);

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
	public static function critical($message, array $context = array()) {

		return $this->log(SimpleLoggerLogLevels::CRITICAL, $message, $context);

	}

	/**
	 * Critical conditions.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function criticalMessage($message, array $context = array()) {

		if (!isset($this->messages[$message]["untranslated_text"])) {
			return;
		}

		$context["_message_key"] = $message;
		$message = $this->messages[$message]["untranslated_text"];

		$this->log(SimpleLoggerLogLevels::CRITICAL, $message, $context);

	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function error($message, array $context = array()) {

		return $this->log(SimpleLoggerLogLevels::ERROR, $message, $context);

	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function errorMessage($message, array $context = array()) {

		return $this->logByMessageKey(SimpleLoggerLogLevels::ERROR, $message, $context);

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
	public function warning($message, array $context = array()) {

		return $this->log(SimpleLoggerLogLevels::WARNING, $message, $context);

	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function warningMessage($message, array $context = array()) {

		return $this->logByMessageKey(SimpleLoggerLogLevels::WARNING, $message, $context);

	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function notice($message, array $context = array()) {

		return $this->log(SimpleLoggerLogLevels::NOTICE, $message, $context);

	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function noticeMessage($message, array $context = array()) {

		return $this->logByMessageKey(SimpleLoggerLogLevels::NOTICE, $message, $context);

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
	public function info($message, array $context = array()) {

		return $this->log(SimpleLoggerLogLevels::INFO, $message, $context);

	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function infoMessage($message, array $context = array()) {

		return $this->logByMessageKey(SimpleLoggerLogLevels::INFO, $message, $context);

	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function debug($message, array $context = array()) {

		return $this->log(SimpleLoggerLogLevels::DEBUG, $message, $context);

	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array $context
	 * @return null
	 */
	public function debugMessage($message, array $context = array()) {

		return $this->logByMessageKey(SimpleLoggerLogLevels::DEBUG, $message, $context);

	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function log($level, $message, array $context = array()) {

		global $wpdb;

		/*
		 * Filter that makes it possible to shortcut this log.
		 * Return bool false to cancel.
		 *
		 * @since 2.3.1
		 */
		$do_log = apply_filters( "simple_history/log/do_log", true, $level, $message, $context, $this );
		
		if ( $do_log === false ) {
			return $this;
		}

		// Check if $message is a translated message, and if so then fetch original
		$sh_latest_translations = $this->simpleHistory->gettextLatestTranslations;

		if ( ! empty( $sh_latest_translations ) ) {

			if ( isset( $sh_latest_translations[ $message ] ) ) {

				// Translation of this phrase was found, so use original phrase instead of translated one

				// Store textdomain since it's required to translate
				$context["_gettext_domain"] = $sh_latest_translations[$message]["domain"];

				// These are good to keep when debugging
				// $context["_gettext_org_message"] = $sh_latest_translations[$message]["text"];
				// $context["_gettext_translated_message"] = $sh_latest_translations[$message]["translation"];

				$message = $sh_latest_translations[ $message ]["text"];
			}

		}

		/**
		 * Filter arguments passed to log funtion
		 *
		 * @since 2.0
		 *
		 * @param string $level
		 * @param string $message
		 * @param array $context
		 * @param object SimpleLogger object
		 */
		apply_filters("simple_history/log_arguments", $level, $message, $context, $this);
		$context = apply_filters("simple_history/log_argument/context", $context, $level, $message, $this);
		$level = apply_filters("simple_history/log_argument/level", $level, $context, $message, $this);
		$message = apply_filters("simple_history/log_argument/message", $message, $level, $context, $this);

		/* Store date as GMT date, i.e. not local date/time
		 * Some info here:
		 * http://www.skyverge.com/blog/down-the-rabbit-hole-wordpress-and-timezones/
		 */
		$localtime = current_time("mysql", 1);

		$db_table = $wpdb->prefix . SimpleHistory::DBTABLE;

		/**
		 * Filter db table used for simple history events
		 *
		 * @since 2.0
		 *
		 * @param string $db_table
		 */
		$db_table = apply_filters("simple_history/db_table", $db_table);

		$data = array(
			"logger" => $this->slug,
			"level" => $level,
			"date" => $localtime,
			"message" => $message,
		);

		// Allow date to be override
		// Date must be in format 'Y-m-d H:i:s'
		if (isset($context["_date"])) {
			$data["date"] = $context["_date"];
			unset($context["_date"]);
		}

		// Add occasions id
		$occasions_id = null;
		if (isset($context["_occasionsID"])) {

			// Minimize risk of similar loggers logging same messages and such and resulting in same occasions id
			// by always adding logger slug
			$occasions_data = array(
				"_occasionsID" => $context["_occasionsID"],
				"_loggerSlug" => $this->slug,
			);
			$occasions_id = md5(json_encode($occasions_data));
			unset($context["_occasionsID"]);

		} else {

			// No occasions id specified, create one bases on the data array
			$occasions_data = $data + $context;
			// error_log(simpleHistory::json_encode($occasions_data));
			// Don't include date in context data
			unset($occasions_data["date"]);

			//sf_d($occasions_data);exit;
			$occasions_id = md5(json_encode($occasions_data));

		}

		$data["occasionsID"] = $occasions_id;

		// Log event type, defaults to other if not set
		/*
		if ( isset( $context["_type"] ) ) {
		$data["type"] = $context["_type"];
		unset( $context["_type"] );
		} else {
		$data["type"] = SimpleLoggerLogTypes::OTHER;
		}
		 */

		// Log initiator, defaults to current user if exists, or other if not user exist
		if ( isset( $context["_initiator"] ) ) {

			// Manually set in context
			$data["initiator"] = $context["_initiator"];
			unset( $context["_initiator"] );

		} else {

			// No initiator set, try to determine

			// Default to other
			$data["initiator"] = SimpleLoggerLogInitiators::OTHER;

			// Check if user is responsible.
			if ( function_exists("wp_get_current_user") ) {

				$current_user = wp_get_current_user();

				if ( isset( $current_user->ID ) && $current_user->ID ) {

					$data["initiator"] = SimpleLoggerLogInitiators::WP_USER;
					$context["_user_id"] = $current_user->ID;
					$context["_user_login"] = $current_user->user_login;
					$context["_user_email"] = $current_user->user_email;

				}

			}

			// If cron then set WordPress as responsible
			if ( defined('DOING_CRON') && DOING_CRON ) {

				// Seems to be wp cron running and doing this
				$data["initiator"] = SimpleLoggerLogInitiators::WORDPRESS;
				$context["_wp_cron_running"] = true;

			}

			// If running as CLI and WP_CLI_PHP_USED is set then it is WP CLI that is doing it
			// How to log this? Is this a user, is it WordPress, or what?
			// I'm thinking:
			//  - it is a user that is manually doing this, on purpose, with intent, so not auto wordpress
			//  - it is a specific user, but we don't know who
			// - sounds like a special case, set initiator to wp_cli
			// Can be used by plugins/themes to check if WP-CLI is running or not
			if ( defined( "WP_CLI" ) && WP_CLI ) {

				$data["initiator"] = SimpleLoggerLogInitiators::WP_CLI;

			}

		}

		// Detect XML-RPC calls and append to context, if not already there
		if ( defined("XMLRPC_REQUEST") && XMLRPC_REQUEST && ! isset( $context["_xmlrpc_request"] ) ) {

			$context["_xmlrpc_request"] = true;

		}

		// Trim message
		$data["message"] = trim( $data["message"] );

		/**
		 * Filter data to be saved to db
		 *
		 * @since 2.0
		 *
		 * @param array $data
		 */
		$data = apply_filters("simple_history/log_insert_data", $data);

		// Insert data into db
		// sf_d($db_table, '$db_table');exit;

		$result = $wpdb->insert($db_table, $data);

		// Only save context if able to store row
		if (false === $result) {

			$history_inserted_id = null;

		} else {

			$history_inserted_id = $wpdb->insert_id;

			$db_table_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

			/**
			 * Filter table name for contexts
			 *
			 * @since 2.0
			 *
			 * @param string $db_table_contexts
			 */
			$db_table_contexts = apply_filters("simple_history/logger_db_table_contexts", $db_table_contexts);

			if (!is_array($context)) {
				$context = array();
			}

			// Append user id to context, if not already added
			if (!isset($context["_user_id"])) {

				// wp_get_current_user is ont available early
				// http://codex.wordpress.org/Function_Reference/wp_get_current_user
				// https://core.trac.wordpress.org/ticket/14024
				if (function_exists("wp_get_current_user")) {

					$current_user = wp_get_current_user();

					if (isset($current_user->ID) && $current_user->ID) {
						$context["_user_id"] = $current_user->ID;
						$context["_user_login"] = $current_user->user_login;
						$context["_user_email"] = $current_user->user_email;
					}

				}

			}

			// Add remote addr to context
			// Good to always have
			if ( ! isset($context["_server_remote_addr"]) ) {

				$context["_server_remote_addr"] = empty($_SERVER["REMOTE_ADDR"]) ? "" : $_SERVER["REMOTE_ADDR"];

				// If web server is behind a load balancer then the ip address will always be the same
				// See bug report: https://wordpress.org/support/topic/use-x-forwarded-for-http-header-when-logging-remote_addr?replies=1#post-6422981
				// Note that the x-forwarded-for header can contain multiple ips, comma separated
				// Also note that the header can be faked
				// Ref: http://stackoverflow.com/questions/753645/how-do-i-get-the-correct-ip-from-http-x-forwarded-for-if-it-contains-multiple-ip
				// Ref: http://blackbe.lt/advanced-method-to-obtain-the-client-ip-in-php/

				// Check for IP in lots of headers
				// Based on code found here:
				// http://blackbe.lt/advanced-method-to-obtain-the-client-ip-in-php/
				$ip_keys = $this->get_ip_number_header_keys();

				foreach ( $ip_keys as $key ) {

					if (array_key_exists($key, $_SERVER) === true) {

						// Loop through all IPs
						$ip_loop_num = 0;
						foreach (explode(',', $_SERVER[$key]) as $ip) {

							// trim for safety measures
							$ip = trim($ip);

							// attempt to validate IP
							if ($this->validate_ip($ip)) {

								// valid, add to context, with loop index appended so we can store many IPs
								$key_lower = strtolower($key);
								$context["_server_{$key_lower}_{$ip_loop_num}"] = $ip;

							}

							$ip_loop_num++;

						}

					}

				}

			}

			// Append http referer
			// Also good to always have!
			if (!isset($context["_server_http_referer"]) && isset($_SERVER["HTTP_REFERER"])) {
				$context["_server_http_referer"] = $_SERVER["HTTP_REFERER"];
			}


			/**
			 * Filter the context to store for this event/row
			 *
			 * @since 2.0.29
			 *
			 * @param array $context Array with all context data to store. Modify and return this.
			 * @param array $data Array with data used for parent row.
			 * @param array $this Reference to this logger instance
			 */
			$context = apply_filters("simple_history/log_insert_context", $context, $data, $this);
			$data_parent_row = $data;

			// Insert all context values into db
			foreach ( $context as $key => $value ) {

				// If value is array or object then use json_encode to store it
				//if ( is_object( $value ) || is_array( $value ) ) {
				//	$value = simpleHistory::json_encode($value);
				//}
				// Any reason why the check is not the other way around?
				// Everything except strings should be json_encoded
				if ( ! is_string( $value ) ) {
					$value = simpleHistory::json_encode( $value );
				}

				$data = array(
					"history_id" => $history_inserted_id,
					"key" => $key,
					"value" => $value,
				);

				$result = $wpdb->insert ($db_table_contexts, $data );

			}

		}
	
		$this->lastInsertID = $history_inserted_id;

		$this->simpleHistory->get_cache_incrementor(true);

		/**
		 * Action that is called after an event has been logged
		 *
		 * @since 2.5.1
		 *
		 * @param array $context Array with all context data to store. Modify and return this.
		 * @param array $data Array with data used for parent row.
		 * @param array $this Reference to this logger instance
		 */
		do_action( "simple_history/log/inserted", $context, $data_parent_row, $this );

		// Return $this so we can chain methods
		return $this;

	} // log

	/**
	 * Returns array with headers that may contain user IP
	 *
	 * @since 2.0.29
	 */
	public function get_ip_number_header_keys() {

		$arr = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED'
		);

		return $arr;

	}

	/**
	 * Returns additional headers with ip number from context
	 *
	 * @since 2.0.29
	 */
	function get_event_ip_number_headers($row) {

		$ip_keys = $this->get_ip_number_header_keys();
		$arr_found_additional_ip_headers = array();
		$context = $row->context;

		foreach ( $ip_keys as $one_ip_header_key ) {

			$one_ip_header_key_lower = strtolower($one_ip_header_key);

			foreach ( $context as $context_key => $context_val ) {

				#$key_check_for = "_server_" . strtolower($one_ip_header_key) . "_0";

				$match = preg_match("/^_server_{$one_ip_header_key_lower}_[\d+]/", $context_key, $matches);
				if ( $match ) {
					$arr_found_additional_ip_headers[ $context_key ] = $context_val;
				}

			} // foreach context key for this ip header key

		} // foreach ip header key

		return $arr_found_additional_ip_headers;

	}

	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 */
	function validate_ip($ip) {

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE) === false) {
			return false;
		}

		return true;

	}

	/**
	 * Override this to add CSS in <head> for your logger.
	 * The CSS that you output will only be outputed
	 * on pages where Simple History is used.
	 */
	function adminCSS() {
		/*
	?>
	<style>
	body {
	border: 2px solid red;
	}
	</style>
	<?php
	 */
	}

	/**
	 * Override this to add JavaScript in the footer for your logger.
	 * The JS that you output will only be outputed
	 * on pages where Simple History is used.
	 */
	function adminJS() {
		/*
	?>
	<script>
	console.log("This is outputed in the footer");
	</script>
	<?php
	 */
	}

}

/**
 * Describes log initiator, i.e. who caused to log event to happend
 */
class SimpleLoggerLogInitiators {

	// A wordpress user that at the log event created did exist in the wp database
	// May have been deleted when the log is viewed
	const WP_USER = 'wp_user';

	// Cron job run = wordpress initiated
	// Email sent to customer on webshop = system/wordpress/anonymous web user
	// Javascript error occured on website = anonymous web user
	const WEB_USER = 'web_user';

	// WordPress core or plugins updated automatically via wp-cron
	const WORDPRESS = "wp";

	// WP CLI / terminal
	const WP_CLI = "wp_cli";

	// I dunno
	const OTHER = 'other';
}

/**
 * Describes log event type
 * Based on the CRUD-types
 * http://en.wikipedia.org/wiki/Create,_read,_update_and_delete
 * More may be added later on if needed
 * Note: not in use at the moment
 */
class SimpleLoggerLogTypes {
	const CREATE = 'create';
	const READ = 'read';
	const UPDATE = 'update';
	const DELETE = 'delete';
	const OTHER = 'other';
}

/**
 * Describes log levels
 */
class SimpleLoggerLogLevels {
	const EMERGENCY = 'emergency';
	const ALERT = 'alert';
	const CRITICAL = 'critical';
	const ERROR = 'error';
	const WARNING = 'warning';
	const NOTICE = 'notice';
	const INFO = 'info';
	const DEBUG = 'debug';
}
