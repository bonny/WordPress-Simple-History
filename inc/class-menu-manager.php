<?php

namespace Simple_History;

/**
 * Menu manager class that handles registration and organization of admin menu pages.
 */
class Menu_Manager {
	/** @var array<string,Menu_Page> Array of all registered menu pages. */
	private $pages = [];

	/**
	 * Add a page to be managed.
	 *
	 * @param Menu_Page $page Page object to add.
	 * @return self
	 */
	public function add_page( Menu_Page $page ) {
		// Set reference to this menu manager instance so page can lookup parent pages.
		$page->set_menu_manager( $this );

		$this->pages[ $page->get_menu_slug() ] = $page;
		return $this;
	}

	/**
	 * Get a page by its slug.
	 *
	 * @param string $slug Page slug.
	 * @return Menu_Page|null Menu page if found, null if not found.
	 */
	public function get_page( $slug ) {
		return $this->pages[ $slug ] ?? null;
	}

	/**
	 * Get all registered pages.
	 *
	 * @return array<string,Menu_Page>
	 */
	public function get_pages() {
		return $this->pages;
	}

	/**
	 * Register all menu pages with WordPress.
	 * Called during admin_menu.
	 */
	public function register_pages() {
		foreach ( $this->pages as $page ) {
			$location = $page->get_location();

			switch ( $location ) {
				case 'menu_top':
					$this->add_top_level_menu_page( $page );
					break;
				case 'menu_bottom':
					$this->add_top_level_menu_page( $page, 'bottom' );
					break;
				case 'dashboard':
					$this->add_dashboard_page( $page );
					break;
				case 'settings':
					$this->add_settings_page( $page );
					break;
				case 'tools':
					$this->add_tools_page( $page );
					break;
				default:
					// Handle sub-pages that have parent set but no explicit location.
					if ( $page->get_parent() ) {
						$this->add_submenu_page( $page );
					}
			}
		}
	}

	/**
	 * Add a top level menu page.
	 *
	 * @param Menu_Page $page Page to add.
	 * @param string    $position 'top' or 'bottom', determines menu position.
	 */
	private function add_top_level_menu_page( Menu_Page $page, $position = 'top' ) {
		$menu_position = $position === 'bottom' ? 100 : 3;

		$hook_suffix = add_menu_page(
			$page->get_title(),
			$page->get_menu_title(),
			$page->get_capability(),
			$page->get_menu_slug(),
			[ $page, 'render' ],
			$page->get_icon(),
			$menu_position
		);

		$page->set_hook_suffix( $hook_suffix );

		// Add any child pages.
		foreach ( $this->get_child_pages( $page ) as $child_page ) {
			$this->add_submenu_page( $child_page );
		}
	}

	/**
	 * Add a dashboard page.
	 *
	 * @param Menu_Page $page Page to add.
	 */
	private function add_dashboard_page( Menu_Page $page ) {
		$hook_suffix = add_dashboard_page(
			$page->get_title(),
			$page->get_menu_title(),
			$page->get_capability(),
			$page->get_menu_slug(),
			[ $page, 'render' ]
		);

		$page->set_hook_suffix( $hook_suffix );
	}

	/**
	 * Add a settings/options page.
	 *
	 * @param Menu_Page $page Page to add.
	 */
	private function add_settings_page( Menu_Page $page ) {
		$hook_suffix = add_options_page(
			$page->get_title(),
			$page->get_menu_title(),
			$page->get_capability(),
			$page->get_menu_slug(),
			[ $page, 'render' ]
		);

		$page->set_hook_suffix( $hook_suffix );
	}

	/**
	 * Add a tools page.
	 *
	 * @param Menu_Page $page Page to add.
	 */
	private function add_tools_page( Menu_Page $page ) {
		$hook_suffix = add_management_page(
			$page->get_title(),
			$page->get_menu_title(),
			$page->get_capability(),
			$page->get_menu_slug(),
			[ $page, 'render' ]
		);

		$page->set_hook_suffix( $hook_suffix );
	}

	/**
	 * Add a submenu page.
	 *
	 * @param Menu_Page $page Page to add.
	 */
	private function add_submenu_page( Menu_Page $page ) {
		$parent = $page->get_parent();

		if ( ! $parent ) {
			return;
		}

		$hook_suffix = add_submenu_page(
			$parent->get_menu_slug(),
			$page->get_title(),
			$page->get_menu_title(),
			$page->get_capability(),
			$page->get_menu_slug(),
			[ $page, 'render' ]
		);

		$page->set_hook_suffix( $hook_suffix );
	}

	/**
	 * Get all child pages of a parent page.
	 *
	 * @param Menu_Page $parent_page Parent page to get children for.
	 * @return array<Menu_Page>
	 */
	private function get_child_pages( Menu_Page $parent_page ) {
		$children = [];

		foreach ( $this->pages as $page ) {
			if ( $page->get_parent() === $parent_page ) {
				$children[] = $page;
			}
		}

		// Sort children by order.
		usort(
			$children,
			function ( $a, $b ) {
				return $a->get_order() - $b->get_order();
			}
		);

		return $children;
	}
}
