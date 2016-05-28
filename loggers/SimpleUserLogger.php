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
		#add_action("profile_update", array($this, "on_profile_update"), 10, 2);

		// User is created
		add_action("user_register", array($this, "on_user_register"), 10, 2);

		// User is deleted
		add_action('delete_user', array($this, "on_delete_user"), 10, 2);

		// User sessions is destroyed. AJAX call that we hook onto early.
		add_action("wp_ajax_destroy-sessions", array($this, "on_destroy_user_session"), 0);

		// User reaches reset password (from link or only from user created link)
		add_action( 'validate_password_reset', array( $this, "on_validate_password_reset" ), 10, 2 );

		add_action( 'retrieve_password_message', array( $this, "on_retrieve_password_message" ), 10, 4 ); 

		add_filter( 'insert_user_meta', array( $this, "on_insert_user_meta" ), 10, 3 );


	}

	 /*
 	 * Called before the user is updated
 	 * 
 	 * Filter a user's meta values and keys before the user is created or updated.
 	 *
 	 * Does not include contact methods. These are added using `wp_get_user_contact_methods( $user )`.
 	 * 
 	 * @param array $meta {
 	 *     Default meta values and keys for the user.
 	 *
 	 *     @type string   $nickname             The user's nickname. Default is the user's username.
	 *     @type string   $first_name           The user's first name.
	 *     @type string   $last_name            The user's last name.
	 *     @type string   $description          The user's description.
	 *     @type bool     $rich_editing         Whether to enable the rich-editor for the user. False if not empty.
	 *     @type bool     $comment_shortcuts    Whether to enable keyboard shortcuts for the user. Default false.
	 *     @type string   $admin_color          The color scheme for a user's admin screen. Default 'fresh'.
	 *     @type int|bool $use_ssl              Whether to force SSL on the user's admin area. 0|false if SSL is
	 *                                          not forced.
	 *     @type bool     $show_admin_bar_front Whether to show the admin bar on the front end for the user.
	 *                                          Default true.
 	 * }
	 * @param WP_User $user   User object.
	 * @param bool    $update Whether the user is being updated rather than created.
	 */
	function on_insert_user_meta( $meta, $user, $update ) {

		// We only log updates here
		if ( ! $update ) {
			return $meta;
		}

		// $user should be set, but check just in case
		if ( empty( $user ) || ! is_object( $user ) ) {
			return $meta;
		}
	
		// Make of copy of the posted data, because we change the keys
		$posted_data = $_POST;
		$posted_data = stripslashes_deep( $posted_data );

		// Paranoid mode, just in case some other plugin fires the "insert_user_meta" filter and the user.php file is not loaded for some super wierd reason
		if ( ! function_exists( "_get_additional_user_keys" ) ) {
			return $meta;
		}
		
		// Get the default fields to include. This includes contact methods (including filter, so more could have been added)
		$arr_keys_to_check = _get_additional_user_keys( $user );

		// Somehow some fields are not include above, so add them manually
		$arr_keys_to_check = array_merge( $arr_keys_to_check, array("user_email", "user_url", "display_name") );

		// Skip some keys, because to much info or I don't know what they are
		$arr_keys_to_check = array_diff( $arr_keys_to_check, array("use_ssl") );

		// Some keys have different ways of getting data from user
		// so change posted object to match those
		$posted_data["user_url"] = isset( $posted_data["url"] ) ? $posted_data["url"] : null;
		$posted_data["show_admin_bar_front"] = isset( $posted_data["admin_bar_front"] ) ? true : null;
		$posted_data["user_email"] = isset( $posted_data["email"] ) ? $posted_data["email"] : null;
		
		// Display name publicly as	= POST "display_name"
		#var_dump($user->display_name);

		// Set vals for Enable keyboard shortcuts for comment moderation
		$posted_data['comment_shortcuts'] = isset( $posted_data['comment_shortcuts'] ) ? "true" : "false";
		
		// Set vals for Disable the visual editor when writing
		// posted val = string "false" = yes, disable
		$posted_data['rich_editing'] = isset( $posted_data['rich_editing'] ) ? "false" : "true";
		
		// Set vals for Show Toolbar when viewing site
		$posted_data['show_admin_bar_front'] = isset( $posted_data['admin_bar_front'] ) ? "true" : "false";
		
		// if checkbox is checked in admin then this is the saved value on the user object
		// @todo:

		// Check if password was updated
		$password_changed = false;
		if ( ! empty( $posted_data['pass1'] ) && ! empty( $posted_data['pass2'] ) && $posted_data['pass1'] == $posted_data['pass2']  ) {
			$password_changed = 1;
		}
	
		// Check if role was changed
	    //[role] => bbp_moderator
	    $role_changed = false;

	    // if user is network admin then role dropdown does not exist and role is not posted here
	    $new_role = isset( $posted_data["role"] ) ? $posted_data["role"] : null;

	    if ( $new_role ) {
		    // as done in user-edit.php
		    // Compare user role against currently editable roles
			$user_roles = array_intersect( array_values( $user->roles ), array_keys( get_editable_roles() ) );
			$old_role  = reset( $user_roles );
			
		    $role_changed = $new_role != $old_role;
		}
		
		// Will contain the differences
		$user_data_diff = array();

		// Check all keys for diff values
		foreach  ( $arr_keys_to_check as $one_key_to_check ) {

			$old_val = $user->$one_key_to_check;
			$new_val = isset( $posted_data[ $one_key_to_check ] ) ? $posted_data[ $one_key_to_check ] : null;

			#echo "<hr>key: $one_key_to_check";
			#echo "<br>old val: $old_val";
			#echo "<br>new val: $new_val";

			// new val must be set, because otherwise we are not setting anything
			if ( ! isset( $new_val ) ) {
				continue;
			}

			$user_data_diff = $this->add_diff($user_data_diff, $one_key_to_check, $old_val, $new_val);
			
		}

		// Setup basic context
		$context = array(
			"edited_user_id" => $user->ID,
			"edited_user_email" => $user->user_email,
			"edited_user_login" => $user->user_login,
			"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null,
		);

		if ( $password_changed ) {
			$context["edited_user_password_changed"] = "1";
		}

		if ( $role_changed ) {
			$context["user_prev_role"] = $old_role;
			$context["user_new_role"] = $new_role;
		}

		// Add diff to context
		if ( $user_data_diff ) {

			foreach ( $user_data_diff as $one_diff_key => $one_diff_vals ) {
				/*
				One diff looks like:
			    "nickname": {
			        "old": "MyOldNick",
			        "new": "MyNewNick"
			    }
				*/
				$context["user_prev_{$one_diff_key}"] = $one_diff_vals["old"];
				$context["user_new_{$one_diff_key}"] = $one_diff_vals["new"];
			}

		}

	
		$this->infoMessage("user_updated_profile", $context);

		return $meta;

	}

	/**
	 *
	 * user requests a reset password link
	 *
	 */
	function on_retrieve_password_message( $message, $key, $user_login, $user_data ) {
		
		if ( isset( $_GET["action"] ) && ( "lostpassword" == $_GET["action"] ) ) {
		
			$context = array(
				"_initiator" => SimpleLoggerLogInitiators::WEB_USER,
				"message" => $message,
				"key" => $key,
				"user_login" => $user_login,
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
	 * Modify plain text row output
	 * - adds link to user profil
	 * - change to "your profile" if you're looking at your own edit
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

					$context["edit_profile_link"] = get_edit_user_link( $wp_user->ID );

					$use_you = apply_filters("simple_history/user_logger/plain_text_output_use_you", true);
					
					//error_log( serialize( $current_user_id) ); // int 1
					//error_log( serialize( $context["_user_id"]) ); // string 1

					// User still exist, so link to their profile
					if ( (int) $current_user_id === (int) $context["_user_id"] && $use_you ) {

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
				if ( $wp_user ) {

					// Edited user still exist, so link to their profile
					$context["edit_profile_link"] = get_edit_user_link($wp_user->ID);
					$msg = __('Edited the profile for user <a href="{edit_profile_link}">{edited_user_login} ({edited_user_email})</a>', "simple-history");
					$output = $this->interpolate($msg, $context, $row);

				} else {

					// Edited user does not exist any longer

				}

			}

			// if user_updated_profile
		
		} else if ( "user_created" == $context["_message_key"] ) {

			// A user was created. Create link of username that goes to user profile.
			$wp_user = get_user_by( "id", $context["created_user_id"] );

			// If edited_user_id and _user_id is the same then a user edited their own profile
			// Note: it's not the same thing as the currently logged in user (but.. it can be!)

			if ( $wp_user ) {

				$context["edit_profile_link"] = get_edit_user_link( $wp_user->ID );

				// User that is viewing the log is the same as the edited user
				$msg = __('Created user <a href="{edit_profile_link}">{created_user_login} ({created_user_email})</a> with role {created_user_role}', "simple-history");

				$output = $this->interpolate(
							$msg, 
							$context, 
							$row
						);

			} else {
				
				// User does not exist any longer, keep original message


			}

		}


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
	 * 
	 * Called immediately after an existing user is updated.
	 * @param int    $user_id       User ID.
	 * @param object $old_user_data Object containing user's data prior to update.
	 */
	function on_profile_update( $user_id, $old_user_data ) {

		/*
		if (!$user_id || !is_numeric($user_id)) {
			return;
		}

		$wp_user_edited = get_userdata($user_id);

		$context = array(
			"edited_user_id" => $wp_user_edited->ID,
			"edited_user_email" => $wp_user_edited->user_email,
			"edited_user_login" => $wp_user_edited->user_login,
			"server_http_user_agent" => isset( $_SERVER["HTTP_USER_AGENT"] ) ? $_SERVER["HTTP_USER_AGENT"] : null,
			"old_user_data" => $old_user_data
		);

	
		$this->infoMessage("user_updated_profile", $context);
		*/

	}

	/**
	 * User is created
	 *
	 * "This action hook allows you to access data for a new user immediately after they are added to the database.
	 *  The user id is passed to hook as an argument."
	 *
	 */
	function on_user_register( $user_id ) {

		if ( ! $user_id || ! is_numeric( $user_id )) {
			return;
		}

		$wp_user_added = get_userdata($user_id);

		// wp_user->roles (array) - the roles the user is part of.
		$role = null;
		if ( is_array( $wp_user_added->roles ) && ! empty( $wp_user_added->roles[0]) ) {
			$role = $wp_user_added->roles[0];
		}

		$send_user_notification = (int) ( isset( $_POST["send_user_notification"] ) && $_POST["send_user_notification"] );

		$context = array(
			"created_user_id" => $wp_user_added->ID,
			"created_user_email" => $wp_user_added->user_email,
			"created_user_login" => $wp_user_added->user_login, // username
			"created_user_role" => $role,
			"created_user_first_name" => $wp_user_added->first_name,
			"created_user_last_name" => $wp_user_added->last_name,
			"created_user_url" => $wp_user_added->user_url,
			"send_user_notification" => $send_user_notification,
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
	
	/**
	 * Add diff to array if old and new values are different
	 *
	 * Since 2.0.29
	 */
	function add_diff($post_data_diff, $key, $old_value, $new_value) {

		if ( $old_value != $new_value ) {

			$post_data_diff[$key] = array(
				"old" => $old_value,
				"new" => $new_value
			);

		}

		return $post_data_diff;

	}

	/**
	 * Return more info about an logged event
	 * Supports so far: 
	 */
	function getLogRowDetailsOutput( $row ) {

		$context = $row->context;
		$message_key = $context["_message_key"];

		$out = "";

		if ( "user_updated_profile" == $message_key ) {

			// Find all user_prev_ and user_new_ values and show them
			$arr_user_keys_to_show_diff_for = array(
				"first_name" => array(
					"title" => _x("First name", "User logger", "simple-history")
				),
				"last_name" => array(
					"title" => _x("Last name", "User logger", "simple-history")
				),
				"nickname" => array(
					"title" => _x("Nickname", "User logger", "simple-history")
				),
				"description" => array(
					"title" => _x("Description", "User logger", "simple-history"),
				),
				"rich_editing" => array(
					"title" => _x("Visual editor", "User logger", "simple-history") // Disable visual editor
				),
				"comment_shortcuts" => array(
					"title" => _x("Keyboard shortcuts", "User logger", "simple-history") // Enable keyboard shortcuts for comment moderation
				),
				"show_admin_bar_front" => array(
					"title" => _x("Show Toolbar", "User logger", "simple-history") //  Show Toolbar when viewing site
				),
				"admin_color" => array(
					"title" => _x("Colour Scheme", "User logger", "simple-history") // Admin Colour Scheme
				),
				"aim" => array(
					"title" => _x("AIM", "User logger", "simple-history")
				),
				"yim" => array(
					"title" => _x("Yahoo IM", "User logger", "simple-history")
				),
				"jabber" => array(
					"title" => _x("Jabber / Google Talk	", "User logger", "simple-history")
				),
				/*"user_nicename" => array(
					"title" => _x("Nicename", "User logger", "simple-history")
				),*/
				"user_email" => array(
					"title" => _x("Email", "User logger", "simple-history")
				),
				"display_name" => array(
					//"title" => _x("Display name publicly as", "User logger", "simple-history")
					"title" => _x("Display name", "User logger", "simple-history")
				),
				"user_url" => array(
					"title" => _x("Website", "User logger", "simple-history")
				),
				"role" => array(
					//"title" => _x("Display name publicly as", "User logger", "simple-history")
					"title" => _x("Role", "User logger", "simple-history")
				)
			);

			$diff_table_output = "";

			foreach ( $arr_user_keys_to_show_diff_for as $key => $val ) {

				if ( isset( $context["user_prev_{$key}"] ) && isset( $context["user_new_{$key}"] ) ) {
					
					$user_old_value = $context["user_prev_{$key}"];
					$user_new_value = $context["user_new_{$key}"];

					$diff_table_output .= sprintf(
						'<tr>
							<td>%1$s</td>
							<td>%2$s</td>
						</tr>', 
						$val["title"],
						sprintf( 
							'<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins> <del class="SimpleHistoryLogitem__keyValueTable__removedThing">%2$s</del>',
							 esc_html( $user_new_value ), // 1
							 esc_html( $user_old_value ) // 2
						)
					);

				}

			}

			// check if password was changed
			if ( isset( $context["edited_user_password_changed"] ) ) {

				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>', 
					_x("Password", "User logger", "simple-history"),
					_x("Changed", "User logger", "simple-history")
				);

			}

			if ( $diff_table_output ) {
				$diff_table_output = '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
			}

			$out .= $diff_table_output;

		} else if ( "user_created" == $message_key ) {

			// Show fields for created users
			$arr_user_keys_to_show_diff_for = array(
				"created_user_first_name" => array(
					"title" => _x("First name", "User logger", "simple-history")
				),
				"created_user_last_name" => array(
					"title" => _x("Last name", "User logger", "simple-history")
				),
				"created_user_url" => array(
					"title" => _x("Website", "User logger", "simple-history")
				),
				"send_user_notification" => array(
					"title" => _x("User notification email sent", "User logger", "simple-history")
				)
			);

			foreach ( $arr_user_keys_to_show_diff_for as $key => $val ) {

				if ( isset( $context[ $key ] ) && trim( $context[ $key ] ) ) {

					if ( "send_user_notification" == $key ) {

						if ( intval( $context[ $key ] ) == 1 ) {
							$sent_status = _x("Yes, email with account details was sent", "User logger", "simple-history");
						} else {
							// $sent_status = _x("No, no email with account details was sent", "User logger", "simple-history");
							$sent_status = "";
						}

						if ( $sent_status ) {

							$diff_table_output .= sprintf(
								'<tr>
									<td>%1$s</td>
									<td>%2$s</td>
								</tr>', 
								_x("Notification", "User logger", "simple-history"),
								sprintf( 
									'<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>',
									 esc_html( $sent_status ) // 1
								)
							);

						}

					} else {

						$diff_table_output .= sprintf(
							'<tr>
								<td>%1$s</td>
								<td>%2$s</td>
							</tr>', 
							$val["title"],
							sprintf( 
								'<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>',
								 esc_html( $context[ $key ] ) // 1
							)
						);

					}

				}

			}

			if ( $diff_table_output ) {
				$diff_table_output = '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
			}

			$out .= $diff_table_output;

		} // message key

		return $out;


	}

}
