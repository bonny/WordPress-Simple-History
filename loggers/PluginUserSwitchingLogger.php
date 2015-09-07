<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs user switching from the great User Switching plugin
 * Plugin URL: https://wordpress.org/plugins/user-switching/
 * 
 * @since 2.1.x
 */
class PluginUserSwitchingLogger extends SimpleLogger {

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => _x("User Switching Logger", "PluginUserSwitchingLogger", "simple-history"),
			"description" => _x("Logs user switches", "PluginUserSwitchingLogger", "simple-history"),
			"capability" => "export",
			"messages" => array(
				'switched_to_user' => _x('Switched user from {username_from} to {username_to}', "PluginUserSwitchingLogger", "simple-history"),
				'switched_back_user' => _x('Switched back to user {username_to} to {username_from}', "PluginUserSwitchingLogger", "simple-history"),
				'switched_off_user' => _x('Switched off user {username}', "PluginUserSwitchingLogger", "simple-history"),
			),
			/*"labels" => array(
				"search" => array(
					"label" => _x("Export", "Export logger: search", "simple-history"),
					"options" => array(
						_x("Created exports", "Export logger: search", "simple-history") => array(
							"created_export"
						),						
					)
				) // end search array
			) */ // end labels
		);
		
		return $arr_info;

	}

	function loaded() {

		add_action( 'switch_to_user', array( $this, "on_switch_to_user" ), 10, 2 );
		add_action( 'switch_back_user', array( $this, "on_switch_back_user" ), 10, 2 );
		add_action( 'switch_off_user', array( $this, "on_switch_off_user" ), 10, 1 );
 
	}

	function on_switch_to_user( $user_id, $old_user_id ) {

		$this->infoMessage(
			"switched_to_user",
			array(
				"user_id" => $user_id,
				"old_user_id" => $old_user_id
			)
		);

	}

	function on_switch_back_user( $user_id, $old_user_id ) {

		$this->infoMessage(
			"switched_back_user",
			array(
				"user_id" => $user_id,
				"old_user_id" => $old_user_id
			)
		);

	}

	function on_switch_off_user( $user_id ) {

		$this->infoMessage(
			"switched_off_user",
			array(
				"user_id" => $user_id,
			)
		);

	}


}
