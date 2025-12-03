<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Menu_Manager;

/**
 * Service class that handles menu registration and organization.
 */
class Menu_Service extends Service {
	/** @var Menu_Manager Menu manager instance. */
	private $menu_manager;

	/**
	 * Constructor.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	public function __construct( $simple_history ) {
		$this->simple_history = $simple_history;
		$this->menu_manager   = new Menu_Manager();
	}

	/**
	 * Called when service is loaded.
	 * Adds required filters and actions.
	 */
	public function loaded() {
		// Register the pages early.
		// add_action( 'init', [ $this, 'register_pages' ], 10 );
		// Services handle their page registration where appropriate.

		// Register menus late in admin_menu so other plugins can modify their menus first.
		add_action( 'admin_menu', [ $this, 'register_admin_menus' ], 100 );
		add_action( 'current_screen', [ $this, 'redirect_menu_pages' ] );
	}

	/**
	 * Register WordPress admin menus.
	 */
	public function register_admin_menus() {
		$this->menu_manager->register_pages();
	}

	/**
	 * Get the menu manager instance.
	 *
	 * @return Menu_Manager
	 */
	public function get_menu_manager() {
		return $this->menu_manager;
	}

	/**
	 * Checks if current request is for a menu page
	 * that should be redirected to it's first child.
	 * This is usually used to select the first sub-tab.
	 */
	public function redirect_menu_pages() {
		$this->menu_manager->redirect_menu_pages();
	}
}
