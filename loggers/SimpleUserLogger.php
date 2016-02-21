<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs changes to user logins (and logouts)
 */
class SimpleUserLogger extends SimpleLogger {

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(
			"name" => __("User Logger", "simple-history"),
			"description" => __("Logs user logins, logouts, and failed logins", "simple-history"),
			"capability" => "edit_users",
			"messages" => array(
				'user_login_failed' => __('Failed to login with username "{login}" (incorrect password entered)', "simple-history"),
				'user_unknown_login_failed' => __('Failed to login with username "{failed_username}" (username does not exist)', "simple-history"),
				'user_logged_in' => __('Logged in', "simple-history"),
				'user_unknown_logged_in' => __("Unknown user logged in", "simple-history"),
				'user_logged_out' => __("Logged out", "simple-history"),
				'user_updated_profile' => __("Edited the profile for user {edited_user_login} ({edited_user_email})", "simple-history"),
				'user_created' => __("Created user {created_user_login} ({created_user_email}) with role {created_user_role}", "simple-history"),
				'user_deleted' => __("Deleted user {deleted_user_login} ({deleted_user_email})", "simple-history"),
				"user_password_reseted" => __("Reset their password", "simple-history"),
				"user_requested_password_reset_link" => __("Requested a password reset link for user with login '{user_login}' and email '{user_email}'", "simple-history"),

				/*
				Text used in admin:
				Log Out of All Other Sessions
				Left your account logged in at a public computer? Lost your phone? This will log you out everywhere except your current browser
				 */
				'user_session_destroy_others' => _x(
					'Logged out from all other sessions',
					'User destroys other login sessions for themself',
					'simple-history'
				),
				/*
				Text used in admin:
				'Log %s out of all sessions' ), $profileuser->display_name );
				 */
				'user_session_destroy_everywhere' => _x(
					'Logged out "{user_display_name}" from all sessions',
					'User destroys all login sessions for a user',
					'simple-history'
				),
			),

			"labels" => array(
				"search" => array(
					"label" => _x("Users", "User logger: search", "simple-history"),
					"label_all" => _x("All user activity", "User logger: search", "simple-history"),
					"options" => array(
						_x("Successful user logins", "User logger: search", "simple-history") => array(
							"user_logged_in",
							"user_unknown_logged_in",
						),
						_x("Failed user logins", "User logger: search", "simple-history") => array(
							'user_login_failed',
							'user_unknown_login_failed',
						),
						_x('User logouts', 'User logger: search', 'simple-history') => array(
							"user_logged_out",
						),
						_x('Created users', 'User logger: search', 'simple-history') => array(
							"user_created",
						),
						_x("User profile updates", "User logger: search", "simple-history") => array(
							"user_updated_profile",
						),
						_x('User deletions', 'User logger: search', 'simple-history') => array(
							"user_deleted",
						),

					),
				), // end search

			), // end labels

		);
		#sf_d($arr_info);exit;
		return $arr_info;

	}

	/**
	 * Add actions and filters when logger is loaded by Simple History
	 */
	public function loaded() {

		// Plain logins and logouts
		add_action("wp_login", array($this, "on_wp_login"), 10, 3);
		add_action("wp_logout", array($this, "on_wp_logout"));

		// Failed login attempt to username that exists
		add_action("wp_authenticate_user", array($this, "on_wp_authenticate_user"), 10, 2);

		// Failed to login to user that did not exist (perhaps brute force)
		add_filter('authenticate', array($this, "on_authenticate"), 10, 3);

		// User is changed
		add_action("profile_update", array($this, "on_profile_update"), 10, 2);

		// User is created
		add_action("user_register", array($this, "on_user_register"), 10, 2);

		// User is deleted
		add_action('delete_user', array($this, "on_delete_user"), 10, 2);

		// User sessions is destroyed. AJAX call that we hook onto early.
		add_action("wp_ajax_destroy-sessions", array($this, "on_destroy_user_session"), 0);

		// User reaches reset password (from link or only from user created link)
		add_action( 'validate_password_reset', array( $this, "on_validate_password_reset" ), 10, 2 );

		add_action( 'retrieve_password_message', array( $this, "on_retrieve_password_message" ), 10, 4 ); 

	}

	/*
	
	user requests a reset password link

		$errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );

		elseif	( isset($_GET['checkemail']) && 'confirm' == $_GET['checkemail'] )
			$errors->add('confirm', __('Check your e-mail for the confirmation link.'), 'message');

	*/
	function on_retrieve_password_message( $message, $key, $user_login, $user_data ) {
		
		if ( isset( $_GET["action"] ) && ( "lostpassword" == $_GET["action"] ) ) {
		
			$context = array(
				"_initiator" => SimpleLoggerLogInitiators::WEB_USER,
				"message" => $message,
				"key" => $key,
				"user_login" => $user_login,
				// "user_data" => $user_data,
				"GET" => $_GET,
				"POST" => $_POST
			);

			if ( is_a( $user_data, "WP_User" ) ) {

				$context["user_email"] = $user_data->user_email;

			}

			$this->noticeMessage( "user_requested_password_reset_link", $context );

		}

		return $message;

	}

	/**
	 * Fired before the password reset procedure is validated.
	 *
	 * @param object           $errors WP Error object.
	 * @param WP_User|WP_Error $user   WP_User object if the login and reset key match. WP_Error object otherwise.
	 */
	function on_validate_password_reset( $errors, $user ) {

		/*
		User visits the forgot password screen
		$errors object are empty
		$user contains a user
		$_post is empty

		User resets password
		$errors empty
		$user user object
		$_post 

		*/

		$context = array();

		if ( is_a( $user, "WP_User") ) {
			
			$context["_initiator"] = SimpleLoggerLogInitiators::WP_USER;
			$context["_user_id"] = $user->ID;
			$context["_user_login"] = $user->user_login;
			$context["_user_email"] = $user->user_email;

		}

		if ( isset($_POST['pass1']) && $_POST['pass1'] != $_POST['pass2'] ) {
			
			// $errors->add( 'password_reset_mismatch', __( 'The passwords do not match.' ) );
			// user failed to reset password

		}


		if ( ( ! $errors->get_error_code() ) && isset( $_POST['pass1'] ) && !empty( $_POST['pass1'] ) ) {
			
			// login_header( __( 'Password Reset' ), '<p class="message reset-pass">' . __( 'Your password has been reset.' ) . ' <a href="' . esc_url( 
			$this->infoMessage( "user_password_reseted", $context );


		}


	}

	/**
	 * Called when user dessions are destroyed from admin
	 * Can be called for current logged in user = destroy all other sessions
	 * or for another user = destroy alla sessions for that user
	 * Fires from AJAX call
	 *
	 * @since 2.0.6
	 */
	function on_destroy_user_session() {

		/*
		Post params:
		nonce: a14df12195
		user_id: 1
		action: destroy-sessions
		 */

		$user = get_userdata((int) $_POST['user_id']);

		if ($user) {
			if (!current_user_can('edit_user', $user->ID)) {
				$user = false;
			} elseif (!wp_verify_nonce($_POST['nonce'], 'update-user_' . $user->ID)) {
				$user = false;
			}
		}

		if (!$user) {
			// Could not log out user sessions. Please try again.
			return;
		}

		$sessions = WP_Session_Tokens::get_instance($user->ID);

		$context = array();

		if ($user->ID === get_current_user_id()) {

			$this->infoMessage("user_session_destroy_others");

		} else {

			$context["user_id"] = $user->ID;
			$context["user_login"] = $user->user_login;
			$context["user_display_name"] = $user->display_name;

			$this->infoMessage("user_session_destroy_everywhere", $context);

		}

	}

	/**
	 * Fires before a user is deleted from the database.
	 *
	 * @param int      $user_id  ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 */
	public function on_delete_user($user_id, $reassign) {

		$wp_user_to_delete = get_userdata($user_id);

		// wp_user->roles (array) - the roles the user is part of.
		$role = null;
		if (is_array($wp_user_to_delete->roles) && !empty($wp_user_to_delete->roles[0])) {
			$role = $wp_user_to_delete->roles[0];
		}

		$context = array(
			"deleted_user_id" => $wp_user_to_delete->ID,
			"deleted_user_email" => $wp_user_to_delete->user_email,
			"deleted_user_login" => $wp_user_to_delete->user_login,
			"deleted_user_role" => $role,
			"reassign_user_id" => $reassign,
			"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null
		);

		// Let's log this as a little bit more significant that just "message"
		$this->noticeMessage("user_deleted", $context);

	}

	/**
	 * Modify row output
	 */
	public function getLogRowPlainTextOutput($row) {

		$context = $row->context;

		$output = parent::getLogRowPlainTextOutput($row);
		$current_user_id = get_current_user_id();

		if ( "user_updated_profile" == $context["_message_key"] ) {

			$wp_user = get_user_by( "id", $context["edited_user_id"] );

			// If edited_user_id and _user_id is the same then a user edited their own profile
			// Note: it's not the same thing as the currently logged in user (but.. it can be!)
			if ( ! empty( $context["_user_id"] ) && $context["edited_user_id"] === $context["_user_id"] ) {

				if ( $wp_user ) {

					$context["edit_profile_link"] = get_edit_user_link($wp_user->ID);

					$use_you = apply_filters("simple_history/user_logger/plain_text_output_use_you", true);

					// User still exist, so link to their profile
					if ( $current_user_id === $context["_user_id"] && $use_you ) {

						// User that is viewing the log is the same as the edited user
						$msg = __('Edited <a href="{edit_profile_link}">your profile</a>', "simple-history");

					} else {

						$msg = __('Edited <a href="{edit_profile_link}">their profile</a>', "simple-history");

					}

					$output = $this->interpolate($msg, $context, $row);

				} else {

					// User does not exist any longer
					$output = __("Edited your profile", "simple-history");

				}

			} else {

				// User edited another users profile
				if ($wp_user) {

					// Edited user still exist, so link to their profile
					$context["edit_profile_link"] = get_edit_user_link($wp_user->ID);
					$msg = __('Edited the profile for user <a href="{edit_profile_link}">{edited_user_login} ({edited_user_email})</a>', "simple-history");
					$output = $this->interpolate($msg, $context, $row);

				} else {

					// Edited user does not exist any longer

				}

			}

		}// if user_updated_profile

		return $output;
	}

	/**
	 * User logs in
	 *
	 * @param string $user_login
	 * @param object $user
	 */
	function on_wp_login($user_login, $user) {

		$context = array(
			"user_login" => $user_login
		);

		if ( isset( $user_login ) ) {

			$user_obj = get_user_by( "login", $user_login );
			
		} else if ( isset( $user ) && isset( $user->ID ) ) {
			
			$user_obj = get_user_by( "id", $user->ID );

		}

		if ( is_a( $user_obj, "WP_User" ) ) {

			$context = array(
				"user_id" => $user_obj->ID,
				"user_email" => $user_obj->user_email,
				"user_login" => $user_obj->user_login,
			);

			// Override some data that is usually set automagically by Simple History
			// Because wp_get_current_user() does not return any data yet at this point
			$context["_initiator"] = SimpleLoggerLogInitiators::WP_USER;
			$context["_user_id"] = $user_obj->ID;
			$context["_user_login"] = $user_obj->user_login;
			$context["_user_email"] = $user_obj->user_email;
			$context["server_http_user_agent"] = isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null;

			$this->infoMessage("user_logged_in", $context);

		} else {

			// Could not get any info about the user logging in
			$this->warningMessage("user_unknown_logged_in", $context);
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

		if (!$user_id || !is_numeric($user_id)) {
			return;
		}

		$wp_user_edited = get_userdata($user_id);

		$context = array(
			"edited_user_id" => $wp_user_edited->ID,
			"edited_user_email" => $wp_user_edited->user_email,
			"edited_user_login" => $wp_user_edited->user_login,
			"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null
		);

		$this->infoMessage("user_updated_profile", $context);

	}

	/**
	 * User is created
	 */
	function on_user_register($user_id) {

		if (!$user_id || !is_numeric($user_id)) {
			return;
		}

		$wp_user_added = get_userdata($user_id);

		// wp_user->roles (array) - the roles the user is part of.
		$role = null;
		if (is_array($wp_user_added->roles) && !empty($wp_user_added->roles[0])) {
			$role = $wp_user_added->roles[0];
		}

		$context = array(
			"created_user_id" => $wp_user_added->ID,
			"created_user_email" => $wp_user_added->user_email,
			"created_user_login" => $wp_user_added->user_login,
			"created_user_role" => $role,
			"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null
		);

		$this->infoMessage("user_created", $context);

	}

	/**
	 * Log failed login attempt to username that exists
	 *
	 * @param object $user user object that was tried to gain access to
	 * @param string password used
	 */
	function on_wp_authenticate_user($user, $password) {

		// Only log failed attempts
		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {

			// Overwrite some vars that Simple History set automagically
			$context = array(
				"_initiator" => SimpleLoggerLogInitiators::WEB_USER,
				"login_id" => $user->ID,
				"login_email" => $user->user_email,
				"login" => $user->user_login,
				"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null,
				"_occasionsID" => __CLASS__ . '/failed_user_login',
			);

			/**
			 * Maybe store password too
			 * Default is to not do this because of privacy and security
			 *
			 * @since 2.0
			 *
			 * @param bool $log_password
			 */
			$log_password = false;
			$log_password = apply_filters("simple_history/comments_logger/log_failed_password", $log_password);

			if ( $log_password ) {
				$context["login_user_password"] = $password;
			}

			$this->warningMessage("user_login_failed", $context);

		}

		return $user;

	}

	/**
	 * Attempt to login to user that does not exist
	 * 
	 * @param $user (null or WP_User or WP_Error) (required) null indicates no process has authenticated the user yet. A WP_Error object indicates another process has failed the authentication. A WP_User object indicates another process has authenticated the user.
	 * @param $username The user's username.
	 * @param $password The user's password (encrypted)
	 */
	function on_authenticate($user, $username, $password) {

		// Don't log empty usernames
		if ( ! trim($username) ) {
			return $user;
		}

		// If already auth ok
		if ( is_a( $user, 'WP_User' ) ) {
		
			$wp_user = $user;
		
		} else {

			// If username is not a user in the system then this
			// is consideraded a failed login attempt
			$wp_user = get_user_by("login", $username);

		}

		if (false === $wp_user) {

			$context = array(
				"_initiator" => SimpleLoggerLogInitiators::WEB_USER,
				"failed_username" => $username,
				"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null,
				// count all failed logins to unknown users as the same occasions,
				// to prevent log being flooded with login/hack attempts
				// "_occasionsID" => __CLASS__  . '/' . __FUNCTION__
				// Use same occasionsID as for failed login attempts to existing users,
				// because log can flood otherwise if hacker is rotating existing and non-existing usernames
				//"_occasionsID" => __CLASS__  . '/' . __FUNCTION__ . "/failed_user_login/userid:{$user->ID}"
				"_occasionsID" => __CLASS__ . '/failed_user_login',
			);

			/**
			 * Maybe store password too
			 * Default is to not do this because of privacy and security
			 *
			 * @since 2.0
			 *
			 * @param bool $log_password
			 */
			$log_password = false;
			$log_password = apply_filters("simple_history/comments_logger/log_not_existing_user_password", $log_password);
			if ($log_password) {
				$context["failed_login_password"] = $password;
			}

			$this->warningMessage("user_unknown_login_failed", $context);

		}

		return $user;

	}

}
