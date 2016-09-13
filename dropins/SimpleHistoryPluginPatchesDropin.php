<?php
defined( 'ABSPATH' ) or die();

/*
Dropin Name: Plugin Patches
Dropin Description: Used to patch plugins that behave wierd
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryPluginPatchesDropin {

	private $sh;

	function __construct($sh) {
		
		$this->sh = $sh;

		$this->patch_captcha_on_login();

		#$this->patch_nextgen_gallery();
	
		#$this->patch_aio_events_calendar();

	}

	/**
	 * All-in-one events calendar imports ical/rss events with a cron job
	 * which can lead to a lot of posts chnaged
	 */
	function patch_aio_events_calendar() {

		// feature/fix-AIOEventsCalendar
		add_action( "simple_history/log/do_log", array( $this, "patch_aio_events_calendar_on_log" ), 10, 5 );

	}

	
	function patch_aio_events_calendar_on_log( $doLog, $level = null, $message = null, $context = null, $loggerInstance = null ) {

		// this happens when posts are updated
		if ( ! isset( $context["_message_key"]) || $context["_message_key"] !== "post_updated" ) {
			return $doLog;
		}

		// this happens when post type is ai1ec_event
		if ( ! isset( $context["post_type"]) || $context["post_type"] !== "ai1ec_event" ) {
			return $doLog;
		}

		// we don't log when is happens in admin, only when it's a cron job
		if ( ! defined('DOING_CRON') || ! DOING_CRON || is_admin() ) {
			return $doLog;
		}

		// ok, this is a non-admin, cron-running post update for the ai1ec_event post type, so cancel the logging
		error_log("ok, cancel ai1ec_event log");
		$doLog = false;

		return $doLog;

	}


	/**
	 *
	 * Nextgen Gallery and Nextgen Gallery Plus updates posts every 30 minutes or so when accessing
	 * posts with galleries on the front
	 * 
	 * Logged messages are like "Updated nextgen gallery - display type "NextGen Pro Mosaic""
	 * and it can be a lot of them.
	 * 
	 * Support forum thread:
	 * https://wordpress.org/support/topic/non-stop-logging-nextgen-gallery-items
	 * 
	 * Note that Simple History does nothing wrong, the posts are updated, but it's just annoying
	 * and unneeded/unwanted info.
	 *
	 * We solve this by canceling logging of these events.
	 * 
	 */
	function patch_nextgen_gallery() {

		add_action( "simple_history/log/do_log", array( $this, "patch_nextgen_gallery_on_log" ), 10, 5 );

	}

	function patch_nextgen_gallery_on_log( $doLog, $level = null, $message = null, $context = null, $loggerInstance = null ) {

		// Check that NextGen is installed
		if ( ! defined("NGG_PLUGIN") ) {
			return $doLog;
		}

		if ( ! isset( $context["_message_key"]) || $context["_message_key"] !== "post_updated" ) {
			return $doLog;
		}

		if ( ! isset( $context["post_type"]) || $context["post_type"] !== "display_type" ) {
			return $doLog;
		}

		// The log spamming thingie is happening on the front, so only continue if this is not in the admin area
		if ( is_admin() ) {
			return $doLog;
		}

		// The calls must come from logger SimplePostLogger
		if ( $loggerInstance->slug !== "SimplePostLogger" ) {
			return $doLog;
		}

		// There. All checked. Now cancel the logging.
		error_log("ok, cancel nextgen gallery log");
		$doLog = false;

		#error_log(simpleHistory::json_encode( $context ));
		#error_log(simpleHistory::json_encode( $loggerInstance ));
		#error_log(simpleHistory::json_encode( is_admin() ));
		// error_log( __METHOD__ . " canceled logging" );

		return $doLog;

	}


	/**
	 * Captcha on Login
	 *
	 * Calls wp_logut() wrongly when 
	 *  - a user IP is blocked
	 *  - when max num of tries is reached
	 *  - or when the capcha is not correct
	 *
	 * So the event logged will be logged_out but should be user_login_failed or user_unknown_login_failed.
	 * Wrong events logged reported here:
	 * https://wordpress.org/support/topic/many-unknown-logged-out-entries
	 *
	 * Plugin also gives lots of errors, reported by me here:
	 * https://wordpress.org/support/topic/errors-has_cap-deprecated-strict-standards-warning
	 *
	 */
	function patch_captcha_on_login() {

		add_action( "simple_history/log/do_log", array( $this, "patch_captcha_on_login_on_log" ), 10, 5 );

	}

	// Detect that this log message is being called from Captha on login
	// and that the message is "user_logged_out"
	function patch_captcha_on_login_on_log( $doLog, $level = null, $message = null, $context = null, $loggerInstance = null ) {	

		if ( empty( $context ) || ! isset( $context["_message_key"] ) || "user_logged_out" != $context["_message_key"] ) {
			// Message key did not exist or was not "user_logged_out"
			return $doLog;
		}
		
		// 22 nov 2015: disabled this check beacuse for example robots/scripts don't pass all args
		// instead they only post "log" and "pwd"
		// codiga is the input with the captcha
		/*
		if ( ! isset( $_POST["log"], $_POST["pwd"], $_POST["wp-submit"], $_POST["codigo"] ) ) {
			// All needed post variables was not set
			return $doLog;
		}
		*/

		// The Captcha on login uses a class called 'Anderson_Makiyama_Captcha_On_Login'
		// and also a global variable called $global $anderson_makiyama
		global $anderson_makiyama;
		if ( ! class_exists("Anderson_Makiyama_Captcha_On_Login") || ! isset( $anderson_makiyama ) ) {
			return $doLog;
		}
		
		// We must come from wp-login
		// Disabled 22 nov 2015 because robots/scripts dont send referer
		/*
		$wp_referer = wp_get_referer();
		if ( ! $wp_referer || ! "wp-login.php" == basename( $wp_referer ) ) {
			return $doLog;
		}
		*/

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $doLog;
		}

		// File must be wp-login.php (can it even be another?)
		$request_uri = basename( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		if ( "wp-login.php" !== $request_uri ) {
			return $doLog;
		}

		$anderson_makiyama_indice = Anderson_Makiyama_Captcha_On_Login::PLUGIN_ID;
		$capcha_on_login_class_name = $anderson_makiyama[$anderson_makiyama_indice]::CLASS_NAME;
		
		$capcha_on_login_options = (array) get_option( $capcha_on_login_class_name . "_options", array());
		$last_100_logins = isset( $capcha_on_login_options["last_100_logins"] ) ? (array) $capcha_on_login_options["last_100_logins"] : array();
		$last_100_logins = array_reverse( $last_100_logins );

		// Possible messages
		// - Failed: IP already blocked
		// - Failed: exceeded max number of tries
		// - Failed: image code did not match
		// - Failed: Login or Password did not match
		// - Success
		$last_login_status = isset( $last_100_logins[0][2] ) ? $last_100_logins[0][2] : "";

		// If we get here we're pretty sure we come from Captcha on login
		// and that we should cancel the wp_logout message and log an failed login instead
		
		// Get the user logger
		$userLogger = $this->sh->getInstantiatedLoggerBySlug( "SimpleUserLogger" );

		if ( ! $userLogger ) {
			return $doLog;
		}

		// $userLogger->warningMessage("user_unknown_login_failed", $context);

		// Same context as in SimpleUserLogger
		$context = array(
			"_initiator" => SimpleLoggerLogInitiators::WEB_USER,
			#"login_user_id" => $user->ID,
			#"login_user_email" => $user->user_email,
			#"login_user_login" => $user->user_login,
			"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null,
			"_occasionsID" => "SimpleUserLogger" . '/failed_user_login',
			"patch_using_patch" => true,
			"patch_name" => "captcha_on_login"
		);

		// Append capcha message
		if ( $last_login_status ) {
			$context["patch_last_login_status"] = $last_login_status;
		}

		// Get user id and email and login
		// Not passed to filter, but we have it in $_POST
		$login_username = isset( $_POST["log"] ) ? $_POST["log"] : null;
		
		if ( $login_username ) {

			$context["login_user_login"] = $login_username;

			$user = get_user_by( "login", $login_username );

			if ( is_a( $user, "WP_User") ) {

				$context["login_user_id"] = $user->ID;
				$context["login_user_email"] = $user->user_email;
				
			}

		}

		$userLogger->warningMessage("user_login_failed", $context);

		// Cancel original log event
		$doLog = false;

		return $doLog;
		
	}
	
	/**
	 * Log misc useful things to the system log. Useful when developing/testing/debuging etc.
	 */
	function system_debug_log() {
		
		error_log( '$_GET: ' . SimpleHistory::json_encode( $_GET ) );
		error_log( '$_POST: ' . SimpleHistory::json_encode( $_POST ) );
		error_log( '$_FILES: ' . SimpleHistory::json_encode( $_FILES ) );
		error_log( '$_SERVER: ' . SimpleHistory::json_encode( $_SERVER ) );

		$args = func_get_args();
		$i = 0;

		foreach ( $args as $arg ) {
			error_log( "\$arg $i: " . SimpleHistory::json_encode( $arg ) ); 
			$i++;
		}

	}

} // end class
