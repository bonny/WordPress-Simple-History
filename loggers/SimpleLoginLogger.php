<?php

/**
 * Logs changes to user logins (and logouts)
 */
class SimpleLoginLogger extends SimpleLogger
{

	public $slug = "SimpleLoginLogger";

	public function __construct() {
		
		add_action("wp_login", array($this, "on_wp_login" ), 10, 3 );
		add_action("wp_logout", array($this, "on_wp_logout" ) );

		// Failed login attempt to username that exists
		add_action("wp_authenticate_user", array($this, "on_wp_authenticate_user"), 10, 2);

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
				"_user_id" => null,
				"_user_login" => null,
				"_user_email" => null,
				"login_user_id" => $user->ID,
				"login_user_email" => $user->user_email,
				"login_user_login" => $user->user_login
			);

			$message = 'A user failed to log in because wrong password was entered';
			$message = 'Failed to login user "{login_user_email}"';
			$message = 'Login failed for user "{login_user_email}"';
			$message = 'A login attempt was made for user "{login_user_email}"';
			$this->warning(
				$message,
				$context
			);		


		}

		return $user;

	}

	/**
	 * User logs in
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
			$context["_user_id"] = $user->ID;
			$context["_user_login"] = $user->user_login;
			$context["_user_email"] = $user->user_email;

			$this->info(
				'Logged in',
				$context
			);		

		} else {

			// when does this happen?
			$this->info(
				'Unknown user logged in',
				$context
			);		


		}

		

	}

	/**
	 * User logs out
	 */
	function on_wp_logout() {

		$current_user = wp_get_current_user();

		$context = array(
			"user_id" => $current_user->ID,
			"user_email" => $current_user->user_email,
			"user_login" => $current_user->user_login
		);

		$this->info(
			'Logged out',
			$context
		);		

	}

}
