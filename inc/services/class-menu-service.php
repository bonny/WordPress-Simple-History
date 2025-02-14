<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
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
		$this->menu_manager = new Menu_Manager();
	}

	/**
	 * Called when service is loaded.
	 * Adds required filters and actions.
	 */
	public function loaded() {
		// Register the pages early.
		add_action( 'init', [ $this, 'register_pages' ], 10 );

		// Register menus late in admin_menu so other plugins can modify their menus first.
		add_action( 'admin_menu', [ $this, 'register_admin_menus' ], 100 );
	}

	/**
	 * Register core menu pages.
	 */
	public function register_pages() {
		// Main log page.
		$main_log_page = ( new \Simple_History\Menu_Page() )
				->set_menu_slug( 'simple-history-main' )
				->set_title( __( 'Simple History', 'simple-history' ) )
				->set_menu_title( __( 'Simple History', 'simple-history' ) )
				->set_capability( Helpers::get_view_history_capability() )
				->set_icon( 'dashicons-clock' )
				->set_callback( [ $this->simple_history, 'history_page_output' ] );

		// Location based on user preference.
		if ( Helpers::setting_show_as_page() ) {
			$main_log_page->set_location( 'dashboard' );
		} else {
			$main_log_page->set_location( 'menu_top' );
		}

		$this->menu_manager->add_page( $main_log_page );

		// Settings page.
		$settings_page = ( new \Simple_History\Menu_Page() )
				->set_menu_slug( 'simple-history-settings' )
				->set_title( __( 'Simple History Settings', 'simple-history' ) )
				->set_menu_title( __( 'Settings', 'simple-history' ) )
				->set_capability( Helpers::get_view_settings_capability() )
				->set_callback( [ $this->simple_history, 'settings_page_output' ] )
				->set_parent( $main_log_page );

		$this->menu_manager->add_page( $settings_page );
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
}
