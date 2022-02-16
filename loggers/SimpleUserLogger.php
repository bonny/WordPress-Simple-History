<?php

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
	public function getInfo() {

		$arr_info = array(
			'name'        => __( 'User Logger', 'simple-history' ),
			'description' => __( 'Logs user logins, logouts, and failed logins', 'simple-history' ),
			'capability'  => 'edit_users',
			'messages'    => array(
				'user_login_failed' => __(
					'Failed to login with username "{login}" (incorrect password entered)',
					'simple-history'
				),
				'user_unknown_login_failed' => __(
					'Failed to login with username "{failed_username}" (username does not exist)',
					'simple-history'
				),
				'user_logged_in' => __( 'Logged in', 'simple-history' ),
				'user_unknown_logged_in' => __( 'Unknown user logged in', 'simple-history' ),
				'user_logged_out' => __( 'Logged out', 'simple-history' ),
				'user_updated_profile' => __(
					'Edited the profile for user "{edited_user_login}" ({edited_user_email})',
					'simple-history'
				),
				'user_created' => __(
					'Created user {created_user_login} ({created_user_email}) with role {created_user_role}',
					'simple-history'
				),
				'user_deleted' => __( 'Deleted user {deleted_user_login} ({deleted_user_email})', 'simple-history' ),
				'user_password_reseted' => __( 'Reset their password', 'simple-history' ),
				'user_requested_password_reset_link' => __(
					"Requested a password reset link for user with login '{user_login}' and email '{user_email}'",
					'simple-history'
				),
				'user_session_destroy_others' => _x(
					'Logged out from all other sessions',
					'User destroys other login sessions for themself',
					'simple-history'
				),
				'user_session_destroy_everywhere' => _x(
					'Logged out "{user_display_name}" from all sessions',
					'User destroys all login sessions for a user',
					'simple-history'
				),
				'user_admin_email_confirm_correct_clicked' => _x(
					'Verified that administration email for website is correct',
					'User clicks confirm admin email on admin email confirm screen',
					'simple-history'
				),
				'user_role_updated' => _x(
					'Changed role for user "{edited_user_login}" to "{new_role}" from "{old_role}"',
					'User updates the role for a user',
					'simple-history'
				),
				'user_application_password_created' => _x(
					'Added application password "{application_password_name}" for user "{edited_user_login}"',
					'User add new application password',
					'simple-history'
				),
				'user_application_password_deleted' => _x(
					'Deleted application password "{application_password_name}" for user "{edited_user_login}"',
					'User deletes application password',
					'simple-history'
				),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Users', 'User logger: search', 'simple-history' ),
					'label_all' => _x( 'All user activity', 'User logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Successful user logins', 'User logger: search', 'simple-history' ) => array(
							'user_logged_in',
							'user_unknown_logged_in',
						),
						_x( 'Failed user logins', 'User logger: search', 'simple-history' ) => array(
							'user_login_failed',
							'user_unknown_login_failed',
						),
						_x( 'User logouts', 'User logger: search', 'simple-history' ) => array(
							'user_logged_out',
						),
						_x( 'Created users', 'User logger: search', 'simple-history' ) => array(
							'user_created',
						),
						_x( 'User profile updates', 'User logger: search', 'simple-history' ) => array(
							'user_updated_profile',
						),
						_x( 'User deletions', 'User logger: search', 'simple-history' ) => array(
							'user_deleted',
						),
						_x( 'User role changes', 'User logger: search', 'simple-history' ) => array(
							'user_role_updated',
						),
						_x( 'User application password created', 'User logger: search', 'simple-history' ) => array(
							'user_application_password_created',
						),
						_x( 'User application password deletion', 'User logger: search', 'simple-history' ) => array(
							'user_application_password_deleted',
						),

					),
				), // end search

			), // end labels
		);

		return $arr_info;
	}

	/**
	 * Add actions and filters when logger is loaded by Simple History
	 */
	public function loaded() {

		// Plain logins and logouts
		add_action( 'wp_login', array( $this, 'onWpLogin' ), 10, 3 );
		add_action( 'wp_logout', array( $this, 'onWpLogout' ), 10, 1 );

		// Failed login attempt to username that exists
		add_action( 'wp_authenticate_user', array( $this, 'onWpAuthenticateUser' ), 10, 2 );

		// Failed to login to user that did not exist (perhaps brute force)
		// run this later than 10 because wordpress own email login check is done with priority 20
		// so if we run at 10 we just get null
		add_filter( 'authenticate', array( $this, 'onAuthenticate' ), 30, 3 );

		// User is created
		add_action( 'user_register', array( $this, 'on_user_register' ), 10, 2 );

		// User is deleted
		add_action( 'delete_user', array( $this, 'onDeleteUser' ), 10, 2 );

		// User sessions is destroyed. AJAX call that we hook onto early.
		add_action( 'wp_ajax_destroy-sessions', array( $this, 'onDestroyUserSession' ), 0 );

		// User reaches reset password (from link or only from user created link)
		add_action( 'validate_password_reset', array( $this, 'onValidatePasswordReset' ), 10, 2 );
		add_action( 'retrieve_password_message', array( $this, 'onRetrievePasswordMessage' ), 10, 4 );

		// New way, fired before update so we can get old user data.
		add_filter( 'wp_pre_insert_user_data', array( $this, 'on_pre_insert_user_data' ), 10, 4 );

		add_action( 'set_user_role', array( $this, 'on_set_user_role' ), 10, 3 );

		// Administration email verification-screen

		// Run this to force-show the admin email confirm screen.
		// add_filter(
		// 	'option_admin_email_lifespan',
		// 	function( $value, $option ) {
		// 		return 1;
		// 	},
		// 	10,
		// 	2
		// );

		add_action( 'login_form_confirm_admin_email', array( $this, 'on_action_login_form_confirm_admin_email' ) );

		add_action( 'wp_create_application_password', array( $this, 'on_action_wp_create_application_password' ), 10, 4 );
		add_action( 'wp_delete_application_password', array( $this, 'on_action_wp_delete_application_password' ), 10, 2 );
	}

	/**
	 * Log when an Application Password is created for a user.
	 *
	 * Fired from action `wp_create_application_password`.
 *
	 * @param int    $user_id      The user ID.
	 * @param array  $item     {
	 *     The details about the created password.
	 *
	 *     @type string $uuid      The unique identifier for the application password.
	 *     @type string $app_id    A UUID provided by the application to uniquely identify it.
	 *     @type string $name      The name of the application password.
	 *     @type string $password  A one-way hash of the password.
	 *     @type int    $created   Unix timestamp of when the password was created.
	 *     @type null   $last_used Null.
	 *     @type null   $last_ip   Null.
	 * }
	 * @param string $new_password The unhashed generated application password.
	 * @param array  $args         {
	 *     Arguments used to create the application password.
	 *
	 *     @type string $name   The name of the application password.
	 *     @type string $app_id A UUID provided by the application to uniquely identify it.
	 * }
	 */
	public function on_action_wp_create_application_password( $user_id, $item, $new_password, $args ) {
		$user = get_user_by( 'ID', $user_id );

		$this->infoMessage(
			'user_application_password_created',
			array(
				'edited_user_id' => $user_id,
				'edited_user_email' => $user->user_email,
				'edited_user_login' => $user->user_login,
				'application_password_name' => $item['name'],
			)
		);
	}


	/**
	 * Log when an Application password is deleted (revoked).

	 * Fired from action `wp_delete_application_password`.
	 *
	 * @since 5.6.0
	 *
	 * @param int   $user_id The user ID.
	 * @param array $item    The data about the application password.
	 */
	public function on_action_wp_delete_application_password( $user_id, $item ) {
		$user = get_user_by( 'ID', $user_id );

		$this->infoMessage(
			'user_application_password_deleted',
			array(
				'edited_user_id' => $user_id,
				'edited_user_email' => $user->user_email,
				'edited_user_login' => $user->user_login,
				'application_password_name' => $item['name'],
			)
		);
	}


	/**
	 * Fires after the user's role has changed.
	 *
	 * @since 2.9.0
	 * @since 3.6.0 Added $old_roles to include an array of the user's previous roles.
	 *
	 * @param int      $user_id   The user ID.
	 * @param string   $role      The new role.
	 * @param string[] $old_roles An array of the user's previous roles.
	 */
	public function on_set_user_role( $user_id, $role, $old_roles ) {
		$current_screen = simple_history_get_current_screen();

		// Bail if we are not on the users screen.
		if ( $current_screen->id !== 'users' ) {
			return;
		}

		$changed_user = get_user_by( 'ID', $user_id );

		if ( ! is_array( $old_roles ) ) {
			$old_roles = array();
		}

		$old_role = (string) reset( $old_roles );

		$this->noticeMessage(
			'user_role_updated',
			array(
				'edited_user_id' => $user_id,
				'edited_user_email' => $changed_user->user_email,
				'edited_user_login' => $changed_user->user_login,
				'new_role' => $role,
				'old_role' => $old_role,
			)
		);
	}

	public function on_action_login_form_confirm_admin_email() {
		// Bail if button with name "correct-admin-email" was not clicked or if no nonce field exists.
		if ( empty( $_POST['confirm_admin_email_nonce'] ) || empty( $_POST['correct-admin-email'] ) ) {
			return;
		}

		// Bail if nonce not valid.
		$nonce_valid = wp_verify_nonce( $_POST['confirm_admin_email_nonce'], 'confirm_admin_email' );
		if ( $nonce_valid === false ) {
			return;
		}

		$this->infoMessage( 'user_admin_email_confirm_correct_clicked' );
	}

	/**
	 * Filters user data before the record is created or updated.
	 * Used to log user profile updates.
	 *
	 * It only includes data in the users table, not any user metadata.
	 *
	 * @since 4.9.0
	 * @since 5.8.0 The `$userdata` parameter was added.
	 *
	 * @param array    $data {
	 *     Values and keys for the user.
	 *
	 *     @type string $user_login      The user's login. Only included if $update == false
	 *     @type string $user_pass       The user's password.
	 *     @type string $user_email      The user's email.
	 *     @type string $user_url        The user's url.
	 *     @type string $user_nicename   The user's nice name. Defaults to a URL-safe version of user's login
	 *     @type string $display_name    The user's display name.
	 *     @type string $user_registered MySQL timestamp describing the moment when the user registered. Defaults to
	 *                                   the current UTC timestamp.
	 * }
	 * @param bool     $update   Whether the user is being updated rather than created.
	 * @param int|null $user_id  ID of the user to be updated, or NULL if the user is being created.
	 * @param array    $userdata The raw array of data passed to wp_insert_user().
	 */
	public function on_pre_insert_user_data( $data, $update, $user_id, $userdata = array() ) {
		// Bail if this is not a user update.
		if ( ! $update ) {
			return $data;
		}

		// Bail if we don't have all needed data.
		if ( ! $data || ! $user_id ) {
			return $data;
		}

		$current_screen = simple_history_get_current_screen();

		// Bail if we are not on the user-edit screen (edit other user) or profile screen (edit own user).
		if ( ! in_array( $current_screen->id, array( 'user-edit', 'profile' ) ) ) {
			return $data;
		}

		// Array with differences between old and new values.
		$user_data_diff = array();

		// Get user object that contains old/existing values.
		$user_before_update = get_user_by( 'ID', $user_id );

		$password_changed = false;

		foreach ( $userdata as $option_key => $one_maybe_updated_option_value ) {
			$prev_option_value = $user_before_update->$option_key;
			$add_diff = true;

			// Some options need special treatment.
			if ( $option_key === 'role' ) {
				// Get text name of previous role.
				$user_roles = array_intersect( array_values( $user_before_update->roles ), array_keys( get_editable_roles() ) );
				$prev_option_value = reset( $user_roles );
			} else if ( $option_key === 'user_pass' ) {
				$password_changed = $one_maybe_updated_option_value !== $prev_option_value;
				$add_diff = false;
			} else if ( $option_key === 'comment_shortcuts' ) {
				if ( empty( $one_maybe_updated_option_value ) ) {
					$one_maybe_updated_option_value = 'false';
				}
			} else if ( $option_key === 'locale' ) {
				if ( $one_maybe_updated_option_value === '' ) {
					$one_maybe_updated_option_value = 'SITE_DEFAULT';
				}
				if ( $prev_option_value === '' ) {
					$prev_option_value = 'SITE_DEFAULT';
				}
			}

			if ( $add_diff ) {
				$user_data_diff = $this->addDiff( $user_data_diff, $option_key, $prev_option_value, $one_maybe_updated_option_value );
			}
		}

		// Setup basic context.
		$context = array(
			'edited_user_id' => $user_id,
			'edited_user_email' => $user_before_update->user_email,
			'edited_user_login' => $user_before_update->user_login,
			'server_http_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null,
		);

		if ( $password_changed ) {
			$context['edited_user_password_changed'] = '1';
		}

		// Add diff to context
		if ( $user_data_diff ) {
			foreach ( $user_data_diff as $one_diff_key => $one_diff_vals ) {
				$context[ "user_prev_{$one_diff_key}" ] = $one_diff_vals['old'];
				$context[ "user_new_{$one_diff_key}" ] = $one_diff_vals['new'];
			}
		}

		$this->infoMessage( 'user_updated_profile', $context );

		return $data;
	}

	/**
	 * Fired from hook "retrieve_password_message" in "wp-login.php".
	 * Hook filters the message body of the password reset mail.
	 *
	 * If this hook is fired then WP has checked for valid username etc already.
	 *
	 * This hook is not fired when using for example WooCommerce because it has it's own reset password system.
	 * Maybe get_password_reset_key() can be used instead?
	 */
	public function onRetrievePasswordMessage( $message, $key, $user_login, $user_data = null ) {
		$context = array(
			'message' => $message,
			'user_login' => $user_login,
			'user_email' => $user_data->user_email,
		);

		// Request to send reset password link
		// can be initiated from login screen or from users-listing-page in admin.
		// Detect where from the request is coming.
		$request_origin = 'unknown';

		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && $screen->base === 'users' ) {
				$request_origin = 'wp_admin_users_admin';
			}
		} else if ( ! empty( $_POST['user_login'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$request_origin = 'login_screen';
		}

		if ( 'login_screen' === $request_origin ) {
			$context['_initiator'] = SimpleLoggerLogInitiators::WEB_USER;
		}

		$this->noticeMessage( 'user_requested_password_reset_link', $context );

		return $message;
	}

	/**
	 * Fired before the password reset procedure is validated.
	 *
	 * @param object           $errors WP Error object.
	 * @param WP_User|WP_Error $user   WP_User object if the login and reset key match. WP_Error object otherwise.
	 */
	public function onValidatePasswordReset( $errors, $user ) {
		$context = array();

		if ( is_a( $user, 'WP_User' ) ) {
			$context['_initiator'] = SimpleLoggerLogInitiators::WP_USER;
			$context['_user_id'] = $user->ID;
			$context['_user_login'] = $user->user_login;
			$context['_user_email'] = $user->user_email;
		}

		// PHPCS:ignore WordPress.Security.NonceVerification.Missing
		if ( ( ! $errors->get_error_code() ) && isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
			$this->infoMessage( 'user_password_reseted', $context );
		}
	}

	/**
	 * Called when user sessions are destroyed from admin
	 * Can be called for current logged in user = destroy all other sessions
	 * or for another user = destroy all sessions for that user
	 * Fires from AJAX call
	 *
	 * @since 2.0.6
	 */
	public function onDestroyUserSession() {
		// PHPCS:ignore WordPress.Security.NonceVerification.Missing
		$user = get_userdata( (int) $_POST['user_id'] );

		if ( $user ) {
			if ( ! current_user_can( 'edit_user', $user->ID ) ) {
				$user = false;
			} elseif ( ! wp_verify_nonce( $_POST['nonce'], 'update-user_' . $user->ID ) ) {
				$user = false;
			}
		}

		if ( ! $user ) {
			// Could not log out user sessions. Please try again.
			return;
		}

		$context = array();

		if ( $user->ID === get_current_user_id() ) {
			$this->infoMessage( 'user_session_destroy_others' );
		} else {
			$context['user_id'] = $user->ID;
			$context['user_login'] = $user->user_login;
			$context['user_display_name'] = $user->display_name;

			$this->infoMessage( 'user_session_destroy_everywhere', $context );
		}
	}

	/**
	 * Fires before a user is deleted from the database.
	 *
	 * @param int      $user_id  ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 */
	public function onDeleteUser( $user_id, $reassign ) {

		$wp_user_to_delete = get_userdata( $user_id );

		// wp_user->roles (array) - the roles the user is part of.
		$role = null;
		if ( is_array( $wp_user_to_delete->roles ) && ! empty( $wp_user_to_delete->roles[0] ) ) {
			$role = $wp_user_to_delete->roles[0];
		}

		$context = array(
			'deleted_user_id' => $wp_user_to_delete->ID,
			'deleted_user_email' => $wp_user_to_delete->user_email,
			'deleted_user_login' => $wp_user_to_delete->user_login,
			'deleted_user_role' => $role,
			'reassign_user_id' => $reassign,
			'server_http_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null,
		);

		// Let's log this as a little bit more significant that just "message"
		$this->noticeMessage( 'user_deleted', $context );
	}

	/**
	 * Modify plain text row output
	 * - adds link to user profile
	 * - change to "your profile" if you're looking at your own edit
	 */
	public function getLogRowPlainTextOutput( $row ) {

		$context = $row->context;

		$output = parent::getLogRowPlainTextOutput( $row );
		$current_user_id = get_current_user_id();

		if ( 'user_updated_profile' == $context['_message_key'] ) {
			$wp_user = get_user_by( 'id', $context['edited_user_id'] );

			// If edited_user_id and _user_id is the same then a user edited their own profile
			// Note: it's not the same thing as the currently logged in user (but.. it can be!)
			if ( ! empty( $context['_user_id'] ) && $context['edited_user_id'] === $context['_user_id'] ) {
				if ( $wp_user ) {
					$context['edit_profile_link'] = get_edit_user_link( $wp_user->ID );

					$use_you = apply_filters( 'simple_history/user_logger/plain_text_output_use_you', true );

					// User still exist, so link to their profile
					if ( (int) $current_user_id === (int) $context['_user_id'] && $use_you ) {
						// User that is viewing the log is the same as the edited user
						$msg = __( 'Edited <a href="{edit_profile_link}">your profile</a>', 'simple-history' );
					} else {
						$msg = __( 'Edited <a href="{edit_profile_link}">their profile</a>', 'simple-history' );
					}

					$output = $this->interpolate( $msg, $context, $row );
				} else {
					// User does not exist any longer
					$output = __( 'Edited your profile', 'simple-history' );
				}
			} else {
				// User edited another users profile
				if ( $wp_user ) {
					// Edited user still exist, so link to their profile
					$context['edit_profile_link'] = get_edit_user_link( $wp_user->ID );
					$msg = __( 'Edited the profile for user <a href="{edit_profile_link}">{edited_user_login} ({edited_user_email})</a>', 'simple-history' );
					$output = $this->interpolate( $msg, $context, $row );
				}
			}
		} elseif ( 'user_created' == $context['_message_key'] ) {
			// A user was created. Create link of username that goes to user profile.
			$wp_user = get_user_by( 'id', $context['created_user_id'] );

			// If edited_user_id and _user_id is the same then a user edited their own profile
			// Note: it's not the same thing as the currently logged in user (but.. it can be!)
			if ( $wp_user ) {
				$context['edit_profile_link'] = get_edit_user_link( $wp_user->ID );

				// User that is viewing the log is the same as the edited user
				$msg = __(
					'Created user <a href="{edit_profile_link}">{created_user_login} ({created_user_email})</a> with role {created_user_role}',
					'simple-history'
				);

				$output = $this->interpolate(
					$msg,
					$context,
					$row
				);
			}
		}// End if().

		return $output;
	}

	/**
	 * User logs in
	 *
	 * @param string $user_login
	 * @param object $user
	 */
	public function onWpLogin( $user_login = null, $user = null ) {

		$context = array(
			'user_login' => $user_login,
		);

		if ( isset( $user_login ) ) {
			$user_obj = get_user_by( 'login', $user_login );
		} elseif ( isset( $user ) && isset( $user->ID ) ) {
			$user_obj = get_user_by( 'id', $user->ID );
		}

		if ( is_a( $user_obj, 'WP_User' ) ) {
			$context = array(
				'user_id' => $user_obj->ID,
				'user_email' => $user_obj->user_email,
				'user_login' => $user_obj->user_login,
			);

			// Override some data that is usually set automagically by Simple History
			// Because wp_get_current_user() does not return any data yet at this point
			$context['_initiator'] = SimpleLoggerLogInitiators::WP_USER;
			$context['_user_id'] = $user_obj->ID;
			$context['_user_login'] = $user_obj->user_login;
			$context['_user_email'] = $user_obj->user_email;
			$context['server_http_user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null;

			$this->infoMessage( 'user_logged_in', $context );
		} else {
			// Could not get any info about the user logging in
			$this->warningMessage( 'user_unknown_logged_in', $context );
		}
	}

	/**
	 * User logs out
	 * http://codex.wordpress.org/Plugin_API/Action_Reference/wp_logout
	 *
	 * @param int $user_id ID of the user that was logged out. Added in WP 5.5.
	 */
	public function onWpLogout( $user_id = null ) {
		$context = array();
		$user = get_userdata( $user_id );
		if ( is_a( $user, 'WP_User' ) ) {
			$context['_initiator']  = SimpleLoggerLogInitiators::WP_USER;
			$context['_user_id']    = $user->ID;
			$context['_user_login'] = $user->user_login;
			$context['_user_email'] = $user->user_email;
		}

		$this->infoMessage( 'user_logged_out', $context );
	}

	/**
	 * User is created. Fired from action user_register.
	 * Fires immediately after a new user is registered.
	 *
	 * @param int   $user_id  User ID.
	 * (@param array $userdata The raw array of data passed to wp_insert_user(). Since WP 5.8.0.)
	 */
	public function on_user_register( $user_id, $userdata = array() ) {

		if ( ! $user_id || ! is_numeric( $user_id ) ) {
			return;
		}

		$wp_user_added = get_userdata( $user_id );

		// wp_user->roles (array) - the roles the user is part of.
		$role = null;
		if ( is_array( $wp_user_added->roles ) && ! empty( $wp_user_added->roles[0] ) ) {
			$role = $wp_user_added->roles[0];
		}

		// PHPCS:ignore WordPress.Security.NonceVerification.Missing
		$send_user_notification = (int) ( isset( $_POST['send_user_notification'] ) && $_POST['send_user_notification'] );

		$context = array(
			'created_user_id' => $wp_user_added->ID,
			'created_user_email' => $wp_user_added->user_email,
			'created_user_login' => $wp_user_added->user_login, // username
			'created_user_role' => $role,
			'created_user_first_name' => $wp_user_added->first_name,
			'created_user_last_name' => $wp_user_added->last_name,
			'created_user_url' => $wp_user_added->user_url,
			'send_user_notification' => $send_user_notification,
			'server_http_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null,
		);

		$this->infoMessage( 'user_created', $context );
	}

	/**
	 * Log failed login attempt to username that exists
	 *
	 * @param WP_User or WP_Error
	 *        $user The WP_User() object of the user being edited,
	 *        or a WP_Error() object if validation has already failed.
	 * @param string password used
	 */
	public function onWpAuthenticateUser( $userOrError, $password ) {

		// Only continue if $userOrError is a WP_user object
		if ( ! is_a( $userOrError, 'WP_User' ) ) {
			return $userOrError;
		}

		// Only log failed attempts
		if ( ! wp_check_password( $password, $userOrError->user_pass, $userOrError->ID ) ) {
			// Overwrite some vars that Simple History set automagically
			$context = array(
				'_initiator' => SimpleLoggerLogInitiators::WEB_USER,
				'login_id' => $userOrError->ID,
				'login_email' => $userOrError->user_email,
				'login' => $userOrError->user_login,
				'server_http_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null,
				'_occasionsID' => __CLASS__ . '/failed_user_login',
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
			$log_password = apply_filters( 'simple_history/comments_logger/log_failed_password', $log_password );

			if ( $log_password ) {
				$context['login_user_password'] = $password;
			}

			$this->warningMessage( 'user_login_failed', $context );
		}

		return $userOrError;
	}

	/**
	 * Attempt to login to user that does not exist
	 *
	 * @param $user (null or WP_User or WP_Error) (required)
	 *        null indicates no process has authenticated the user yet.
	 *        A WP_Error object indicates another process has failed the authentication.
	 *        A WP_User object indicates another process has authenticated the user.
	 * @param $username The user's username. since 4.5.0 `$username` now accepts an email address.
	 * @param $password The user's password (encrypted)
	 */
	public function onAuthenticate( $user, $username, $password ) {

		// Don't log empty usernames
		if ( ! trim( $username ) ) {
			return $user;
		}

		// If null then no auth done yet. Weird. But what can we do.
		if ( is_null( $user ) ) {
			return $user;
		}

		// If auth ok then $user is a wp_user object
		if ( is_a( $user, 'WP_User' ) ) {
			return $user;
		}

		// If user is a WP_Error object then auth failed
		// Error codes can be:
		// "incorrect_password" | "empty_password" | "invalid_email" | "invalid_username"
		// We only act on invalid emails and invalid usernames
		if ( is_a( $user, 'WP_Error' ) && ( $user->get_error_code() == 'invalid_username' || $user->get_error_code() == 'invalid_email' ) ) {
			$context = array(
				'_initiator' => SimpleLoggerLogInitiators::WEB_USER,
				'failed_username' => $username,
				'server_http_user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : null,
				// count all failed logins to unknown users as the same occasions,
				// to prevent log being flooded with login/hack attempts
				// "_occasionsID" => __CLASS__  . '/' . __FUNCTION__
				// Use same occasionsID as for failed login attempts to existing users,
				// because log can flood otherwise if hacker is rotating existing and non-existing usernames
				// "_occasionsID" => __CLASS__  . '/' . __FUNCTION__ . "/failed_user_login/userid:{$user->ID}"
				'_occasionsID' => __CLASS__ . '/failed_user_login',
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
			$log_password = apply_filters(
				'simple_history/comments_logger/log_not_existing_user_password',
				$log_password
			);
			if ( $log_password ) {
				$context['failed_login_password'] = $password;
			}

			$this->warningMessage( 'user_unknown_login_failed', $context );
		}

		return $user;
	}

	/**
	 * Add diff to diff array if old and new values are different.
	 *
	 * Since 2.0.29
	 *
	 * @param array $post_data_diff
	 * @param string $key
	 * @param string $old_value
	 * @param string $new_value
	 * @return array
	 */
	public function addDiff( $post_data_diff, $key, $old_value, $new_value ) {
		if ( $old_value != $new_value ) {
			$post_data_diff[ $key ] = array(
				'old' => $old_value,
				'new' => $new_value,
			);
		}

		return $post_data_diff;
	}

	/**
	 * Return more info about an logged event.
	 *
	 * @param object $row
	 */
	public function getLogRowDetailsOutput( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'];

		$out = '';
		$diff_table_output = '';

		if ( 'user_updated_profile' == $message_key ) {
			// Find all user_prev_ and user_new_ values and show them.
			$arr_user_keys_to_show_diff_for = array(
				'rich_editing' => array(
					'title' => _x( 'Visual editor', 'User logger', 'simple-history' ),
					'type' => 'checkbox',
					'value_true' => _x( 'Enable', 'User logger', 'simple-history' ),
					'value_false' => _x( 'Disable', 'User logger', 'simple-history' ),
				),
				'admin_color' => array(
					'title' => _x( 'Colour scheme', 'User logger', 'simple-history' ),
				),
				'comment_shortcuts' => array(
					'title' => _x( 'Keyboard shortcuts', 'User logger', 'simple-history' ),
					'type' => 'checkbox',
					'value_true' => _x( 'Enable', 'User logger', 'simple-history' ),
					'value_false' => _x( 'Disable', 'User logger', 'simple-history' ),
				),
				'show_admin_bar_front' => array(
					'title' => _x( 'Toolbar', 'User logger', 'simple-history' ),
					'type' => 'checkbox',
					'value_true' => _x( 'Show', 'User logger', 'simple-history' ),
					'value_false' => _x( "Don't show", 'User logger', 'simple-history' ),
				),
				'locale' => array(
					'title' => _x( 'Language', 'User logger', 'simple-history' ),
				),
				'role' => array(
					'title' => _x( 'Role', 'User logger', 'simple-history' ),
				),
				'first_name' => array(
					'title' => _x( 'First name', 'User logger', 'simple-history' ),
				),
				'last_name' => array(
					'title' => _x( 'Last name', 'User logger', 'simple-history' ),
				),
				'nickname' => array(
					'title' => _x( 'Nickname', 'User logger', 'simple-history' ),
				),
				'display_name' => array(
					'title' => _x( 'Display name', 'User logger', 'simple-history' ),
				),
				'user_email' => array(
					'title' => _x( 'Email', 'User logger', 'simple-history' ),
				),
				'user_url' => array(
					'title' => _x( 'Website', 'User logger', 'simple-history' ),
				),
				'description' => array(
					'title' => _x( 'Description', 'User logger', 'simple-history' ),
				),
				'aim' => array(
					'title' => _x( 'AIM', 'User logger', 'simple-history' ),
				),
				'yim' => array(
					'title' => _x( 'Yahoo IM', 'User logger', 'simple-history' ),
				),
				'jabber' => array(
					'title' => _x( 'Jabber / Google Talk ', 'User logger', 'simple-history' ),
				),
			);

			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			$translations = wp_get_available_translations();

			// English (United States) is not included in translations_array, add manually.
			if ( ! isset( $translations['en_US'] ) ) {
				$translations['en_US'] = array(
					'language' => 'en_US',
					'english_name' => 'English',
				);
			}

			foreach ( $arr_user_keys_to_show_diff_for as $key => $val ) {
				if ( isset( $context[ "user_prev_{$key}" ] ) && isset( $context[ "user_new_{$key}" ] ) ) {
					$user_old_value = $context[ "user_prev_{$key}" ];
					$user_new_value = $context[ "user_new_{$key}" ];

					if ( $key === 'locale' ) {
						if ( isset( $translations[ $user_old_value ] ) ) {
							$language_english_name = $translations[ $user_old_value ]['english_name'];
							$user_old_value = "{$language_english_name} ({$user_old_value})";
						} else if ( $user_old_value === 'SITE_DEFAULT' ) {
							$user_old_value = __( 'Site Default', 'simple-history' );
						}

						if ( isset( $translations[ $user_new_value ] ) ) {
							$language_english_name = $translations[ $user_new_value ]['english_name'];
							$user_new_value = "{$language_english_name} ({$user_new_value})";
						} else if ( $user_new_value === 'SITE_DEFAULT' ) {
							$user_new_value = __( 'Site Default', 'simple-history' );
						}
					}

					// Change naming for checkbox items from "true" or "false" to
					// something more user friendly "Checked" and "Unchecked".
					if ( isset( $val['type'] ) && $val['type'] === 'checkbox' ) {
						$user_old_value = ( $user_old_value === 'true' ) ? $val['value_true'] : $val['value_false'];
						$user_new_value = ( $user_new_value === 'true' ) ? $val['value_true'] : $val['value_false'];
					}

					$diff_table_output .= sprintf(
						'<tr>
                            <td>%1$s</td>
                            <td>%2$s</td>
                        </tr>',
						$val['title'],
						sprintf(
							'<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins> <del class="SimpleHistoryLogitem__keyValueTable__removedThing">%2$s</del>',
							esc_html( $user_new_value ), // 1
							esc_html( $user_old_value ) // 2
						)
					);
				}
			}

			// Check if password was changed.
			if ( isset( $context['edited_user_password_changed'] ) ) {
				$diff_table_output .= sprintf(
					'<tr>
                        <td>%1$s</td>
                        <td>%2$s</td>
                    </tr>',
					_x( 'Password', 'User logger', 'simple-history' ),
					_x( 'Changed', 'User logger', 'simple-history' )
				);
			}

			if ( $diff_table_output ) {
				$diff_table_output = '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
			}

			$out .= $diff_table_output;
		} elseif ( 'user_created' == $message_key ) {
			// Show fields for created users
			$arr_user_keys_to_show_diff_for = array(
				'created_user_first_name' => array(
					'title' => _x( 'First name', 'User logger', 'simple-history' ),
				),
				'created_user_last_name' => array(
					'title' => _x( 'Last name', 'User logger', 'simple-history' ),
				),
				'created_user_url' => array(
					'title' => _x( 'Website', 'User logger', 'simple-history' ),
				),
				'send_user_notification' => array(
					'title' => _x( 'User notification email sent', 'User logger', 'simple-history' ),
				),
			);

			foreach ( $arr_user_keys_to_show_diff_for as $key => $val ) {
				if ( isset( $context[ $key ] ) && trim( $context[ $key ] ) ) {
					if ( 'send_user_notification' == $key ) {
						if ( intval( $context[ $key ] ) == 1 ) {
							$sent_status = _x(
								'Yes, email with account details was sent',
								'User logger',
								'simple-history'
							);
						} else {
							$sent_status = '';
						}

						if ( $sent_status ) {
							$diff_table_output .= sprintf(
								'<tr>
                                    <td>%1$s</td>
                                    <td>%2$s</td>
                                </tr>',
								_x( 'Notification', 'User logger', 'simple-history' ),
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
							$val['title'],
							sprintf(
								'<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%1$s</ins>',
								esc_html( $context[ $key ] ) // 1
							)
						);
					}// End if().
				}// End if().
			}// End foreach().

			if ( $diff_table_output ) {
				$diff_table_output = '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
			}

			$out .= $diff_table_output;
		} // End if().

		return $out;
	}
}
