<?php

/**
 * Logs changes to user logins (and logouts)
 */
class SimpleUserLogger extends SimpleLogger
{

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => "User Logger",
			"description" => "Logs user logins, logouts, and failed logins",
			"capability" => "edit_users",
			"messages" => array(
				'user_login_failed' => __('{login_user_login} ({login_user_email}) failed to login because an incorrect password was entered', "simple-history"),
				'user_unknown_login_failed' => __('"{failed_login_username}" failed to login because login did not exist in system', "simple-history"),
				'user_logged_in' => __('Logged in', "simple-history"),
				'user_unknown_logged_in' => __("Unknown user logged in", "simple-history"),
				'user_logged_out' => __("Logged out", "simple-history"),
				'user_updated_profile' => __("Edited the profile for user {edited_user_login} ({edited_user_email})", "simple-history"),
				'user_created' => __("Added user {created_user_login} ({created_user_email}) with role {created_user_role}", "simple-history"),				
			)
		);
		
		return $arr_info;

	}

	/**
	 * Add actions and filters when logger is loaded by Simple History
	 */
	public function loaded() {

		// Plain logins and logouts
		add_action("wp_login", array($this, "on_wp_login" ), 10, 3 );
		add_action("wp_logout", array($this, "on_wp_logout" ) );

		// Failed login attempt to username that exists
		add_action("wp_authenticate_user", array($this, "on_wp_authenticate_user"), 10, 2);

		// Failed to login to user that did not exist (perhaps brute force)
		add_filter( 'authenticate', array($this, "on_authenticate"), 10, 3);

		// User is changed
		add_action("profile_update", array($this, "on_profile_update"), 10, 2);

		// User is created
		add_action("user_register", array($this, "on_user_register"), 10, 2);		

	}

	/**
	 * Modify row output
	 */
	public function getLogRowPlainTextOutput($row) {

		$context = $row->context;
		
		$output = parent::getLogRowPlainTextOutput($row);

		if ( "user_updated_profile" == $context["_message_key"]) {

			$wp_user = get_user_by( "id", $context["edited_user_id"] );

			// If edited_user_id and _user_id is the same then a user edited their own profile
			if ( $context["edited_user_id"] === $context["_user_id"] ) {

				if ($wp_user) {

					// User still exist, so link to their profile
					$context["edit_profile_link"] = get_edit_user_link($wp_user->ID);
					$msg = __('Edited <a href="{edit_profile_link}">their profile</a>', "simple-history");
					$output = $this->interpolate($msg, $context);

				} else {

					// User does not exist any longer
					$output = __("Edited their profile", "simple-history");

				}

			} else {

				// User edited another users profile
				if ($wp_user) {

					// Edited user still exist, so link to their profile
					$context["edit_profile_link"] = get_edit_user_link($wp_user->ID);
					$msg = __('Edited <a href="{edit_profile_link}">the profile for user {edited_user_login} ({edited_user_email})</a>', "simple-history");
					$output = $this->interpolate( $msg, $context );

				} else {

					// Edited user does not exist any longer

				}


			}


		} // if ueder_updated_profile
				
		return $output;
	}


	/**
	 * Log failed login attempt to username that exists
	 *
	 * @param object $user user object that was tried to gain access to
	 * @param string password used
	 */
	function on_wp_authenticate_user($user, $password) {

		// Only log failed attempts
		if ( ! wp_check_password($password, $user->user_pass, $user->ID) ) {
			
			// Overwrite some vars that Simple History set automagically
			$context = array(
				"_initiator" => SimpleLoggerLogInitiators::WEB_USER,
				"_user_id" => null,
				"_user_login" => null,
				"_user_email" => null,
				"login_user_id" => $user->ID,
				"login_user_email" => $user->user_email,
				"login_user_login" => $user->user_login,
				"server_http_user_agent" => $_SERVER["HTTP_USER_AGENT"],
				"_occasionsID" => __CLASSNAME__  . '/' . __FUNCTION__ . "/failed_user_login/userid:{$user->ID}"
			);

			$this->warningMessage("user_login_failed", $context);		

		}

		return $user;

	}

	/**
	 * User logs in
	 *
	 * @param string $user_login
	 * @param object $user
	 */
	function on_wp_login($user_login, $user) {

		$context = array();

		if ( $user->ID ) {

			$context = array(
				"user_id" => $user->ID,
				"user_email" => $user->user_email,
				"user_login" => $user->user_login
			);

			// Override some data that is usually set automagically by Simple History
			// Because wp_get_current_user() does not return any data yet at this point
			$context["_initiator"] = SimpleLoggerLogInitiators::WP_USER;
			$context["_user_id"] = $user->ID;
			$context["_user_login"] = $user->user_login;
			$context["_user_email"] = $user->user_email;
			$context["server_http_user_agent"] = $_SERVER["HTTP_USER_AGENT"];

			// For translation
			__("Logged in", "simple-history");

			$this->infoMessage("user_logged_in", $context);		

		} else {

			// when does this happen?
			$this->warningMessage("user_unknown_logged_in", $context );		


		}

	}

	/**
	 * User logs out
	 * http://codex.wordpress.org/Plugin_API/Action_Reference/wp_logout
	 */
	function on_wp_logout() {

		$this->infoMessage("user_logged_out");

	}

	/**
	 * User is edited
	 */
	function on_profile_update($user_id) {
		
		if ( ! $user_id || ! is_numeric($user_id))
			return;

		$wp_user_edited = get_userdata( $user_id );

		$context = array(
			"edited_user_id" => $wp_user_edited->ID,
			"edited_user_email" => $wp_user_edited->user_email,
			"edited_user_login" => $wp_user_edited->user_login,
			"server_http_user_agent" => $_SERVER["HTTP_USER_AGENT"],
		);

		$this->infoMessage("user_updated_profile", $context);		

	}

	/**
	 * User is created
	 */
	function on_user_register($user_id) {
		
		if ( ! $user_id || ! is_numeric($user_id))
			return;

		$wp_user_added = get_userdata( $user_id );

		// wp_user->roles (array) - the roles the user is part of.
		$role = null;
		if ( is_array( $wp_user_added->roles ) && ! empty( $wp_user_added->roles[0] ) ) {
			$role = $wp_user_added->roles[0];
		}

		$context = array(
			"created_user_id" => $wp_user_added->ID,
			"created_user_email" => $wp_user_added->user_email,
			"created_user_login" => $wp_user_added->user_login,
			"created_user_role" => $role,
			"server_http_user_agent" => $_SERVER["HTTP_USER_AGENT"],
		);

		$this->infoMessage("user_created", $context);		

	}

	/**
	 * Attempt to login to user that does not exist
	 */
	function on_authenticate($user, $username, $password) {

		// Don't log empty usernames
		if ( ! trim($username)) {
			return $user;
		}
		
		// If username is not a user in the system then this
		// is consideraded a failed login attempt
		$wp_user = get_user_by( "login", $username );
		
		if (false === $wp_user) {

			$context = array(
				"failed_login_username" => $username,
				"server_http_user_agent" => $_SERVER["HTTP_USER_AGENT"],
				// count all failed logins to unknown users as the same occasions, to prevent log being flooded
				// with login/hack attempts
				"_occasionsID" => __CLASSNAME__  . '/' . __FUNCTION__
			);

			$this->infoMessage("user_unknown_login_failed", $context);		

		}

		return $user;


	}

}
