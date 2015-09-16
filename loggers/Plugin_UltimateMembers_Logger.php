<?php

defined( 'ABSPATH' ) or die();

/**
 *
 * Plugin URL: https://sv.wordpress.org/plugins/ultimate-member/
 *
 * @since 2.2
 */
class Plugin_UltimateMembers_Logger extends SimpleLogger {

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(
			"name" => _x("Ultimate Members Logger", "PluginUltimateMembersLogger", "simple-history"),
			"description" => _x("Logs actions from the Ultimate Members plugin", "PluginUltimateMembersLogger", "simple-history"),
			"capability" => "edit_users",
			"messages" => array(
				'logged_in' => _x('Logged in', "PluginUltimateMembersLogger", "simple-history"),
			),
		);

		return $arr_info;

	}

	function loaded() {

		// Action that is called when Enable Media Replace loads it's admin options page (both when viewing and when posting new file to it)
		add_action( 'um_on_login_before_redirect', array( $this, "on_um_on_login_before_redirect" ), 10, 1 );
	}

	function on_um_on_login_before_redirect( $user_id ) {

		$this->infoMessage("logged_in", array(
			//"user_id" => $user_id,
			/*
			"get" => $_GET,
			"post" => $_POST,
			"files" => $_FILES,
			"old_attachment_post" => $prev_attachment_post,
			"old_attachment_meta" => $prev_attachment_meta
			*/
		));

	}

}
