<?php

/**
 * Logs WordPress menu edits
 */
class SimpleMenuLogger extends SimpleLogger
{

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => "Export Logger",
			"description" => "Logs menu edits",
			"capability" => "edit_theme_options",
			"messages" => array(
				'edited_menu' => __('Edited menu "{menu_name}"', "simple-history"),
				'created_menu' => __('Created menu "{menu_name}"', "simple-history"),
				'deleted_menu' => __('Deleted menu "{menu_name}"', "simple-history"),
				'edited_menu_item' => __('Edited a menu item', "simple-history"),
			)
		);
		
		return $arr_info;

	}

	function loaded() {

		/*
		 * Fires after a navigation menu has been successfully deleted.
		 *
		 * @since 3.0.0
		 *
		 * @param int $term_id ID of the deleted menu.
		do_action( 'wp_delete_nav_menu', $menu->term_id );
		*/
		//add_action("wp_delete_nav_menu", array($this, "on_wp_delete_nav_menu"), 10, 1 );
		add_action("load-nav-menus.php", array($this, "on_load_nav_menus_page"));

		/*
		 * Fires after a navigation menu is successfully created.
		 *
		 * @since 3.0.0
		 *
		 * @param int   $term_id   ID of the new menu.
		 * @param array $menu_data An array of menu data.
		do_action( 'wp_create_nav_menu', $_menu['term_id'], $menu_data );
		*/
		add_action("wp_create_nav_menu", array($this, "on_wp_create_nav_menu"), 10, 2 );


		/*
		 * Fires after a navigation menu item has been updated.
		 *
		 * @since 3.0.0
		 *
		 * @see wp_update_nav_menu_items()
		 *
		 * @param int   $menu_id         ID of the updated menu.
		 * @param int   $menu_item_db_id ID of the updated menu item.
		 * @param array $args            An array of arguments used to update a menu item.
		do_action( 'wp_update_nav_menu_item', $menu_id, $menu_item_db_id, $args );
		*/

		// This is fired when adding nav items in the editor, not at save, so not
		// good to log because user might not end up saving the changes
		// add_action("wp_update_nav_menu_item", array($this, "on_wp_update_nav_menu_item"), 10, 3 );


		/*
		 * Fires after a navigation menu has been successfully updated.
		 *
		 * @since 3.0.0
		 *
		 * @param int   $menu_id   ID of the updated menu.
		 * @param array $menu_data An array of menu data.
		do_action( 'wp_update_nav_menu', $menu_id, $menu_data );
		*/
		add_action("wp_update_nav_menu", array($this, "on_wp_update_nav_menu"), 10, 2 );

	}

	function on_load_nav_menus_page() {
		
		/*
		http://playground-root.ep/wp-admin/nav-menus.php?menu=22&action=delete&0=http%3A%2F%2Fplayground-root.ep%2Fwp-admin%2F&_wpnonce=f52e8a31ba
		$_REQUEST:
		Array
		(
		    [menu] => 22
		    [action] => delete
		    [0] => http://playground-root.ep/wp-admin/
		    [_wpnonce] => f52e8a31ba
		)
		*/

		// Check that needed vars are set
		#echo 111;exit;
		if ( ! isset( $_REQUEST["menu"], $_REQUEST["action"] ) ) {
			return;
		}

		if ( "delete" !== $_REQUEST["action"]) {
			return;
		}

		$menu_id = $_REQUEST["menu"];
		if ( ! is_nav_menu( $menu_id) ) {
			return;
		}

		$menu = wp_get_nav_menu_object( $menu_id );
		

		$this->infoMessage(
			"deleted_menu",
			array(
				"menu_term_id" => $menu_id,
				"menu_name" => $menu->name,
			)
		);

	}

	/**
	 * Fired after menu is deleted, so we don't have the name in this action
	 * So that's why we can't use this only
	 */
	/*
	function on_wp_delete_nav_menu($menu_term_id) {

		$this->infoMessage(
			"deleted_menu",
			array(
				"menu_term_id" => $menu_term_id,
				"menu" => print_r($menu, true),
				"request" => print_r($_REQUEST, true),
			)
		);

	}
	*/

	function on_wp_create_nav_menu($term_id, $menu_data) {

		$menu = wp_get_nav_menu_object( $term_id );

		if ( ! $menu ) {
			return;
		}

		$this->infoMessage(
			"created_menu",
			array(
				"term_id" => $term_id,
				"menu_name" => $menu->name
			)
		);

	}

	/*
	function on_wp_update_nav_menu_item($menu_id, $menu_item_db_id, $args) {
		
		$this->infoMessage(
			"edited_menu_item",
			array(
				"menu_id" => $menu_id,
				"menu_item_db_id" => $menu_item_db_id,
				"args" => $this->simpleHistory->json_encode($args),
				"request" => $this->simpleHistory->json_encode($_REQUEST)
			)
		);

	}
	*/

	/** 
	 * This seems to get called twice
	 * one time with menu_data, a second without
	 */
	function on_wp_update_nav_menu($menu_id, $menu_data = array()) {
		
		if (empty($menu_data)) {
			return;
		}

		$this->infoMessage(
			"edited_menu",
			array(
				"menu_id" => $menu_id,
				"menu_name" => $menu_data["menu-name"],
				"menu_data" => $this->simpleHistory->json_encode($menu_data),
				"request" => $this->simpleHistory->json_encode($_REQUEST)
			)
		);

	}

	/**
	 * Get detailed output
	 */
	/*
	function getLogRowDetailsOutput($row) {
	
		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";

		return $output;

	}
	*/

}
