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
