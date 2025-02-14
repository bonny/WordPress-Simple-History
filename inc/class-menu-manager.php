<?php

namespace Simple_History;

/**
 * Menu manager class that handles registration and organization of admin menu pages.
 */
class Menu_Manager {
	/** @var array<int,Menu_Page> Array of all registered menu pages. */
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

		$this->pages[] = $page;

		return $this;
	}

	/**
	 * Get a page by its slug.
	 *
	 * @param string $slug Page slug.
	 * @return Menu_Page|null Menu page if found, null if not found.
	 */
	public function get_page_by_slug( $slug ) {
		foreach ( $this->pages as $page ) {
			if ( $page->get_menu_slug() === $slug ) {
				return $page;
			}
		}

		return null;
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
			// sh_error_log( '-----' );
			// sh_error_log( 'register pages register one page' );
			// sh_error_log( 'page name: ' . $page->get_page_title() );
			// sh_error_log( 'page slug: ' . $page->get_menu_slug() );
			// sh_error_log( 'page location: ' . $page->get_location() );

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
				case 'options':
					$this->add_options_page( $page );
					break;
				case 'management':
					$this->add_tools_page( $page );
					break;
				case 'submenu':
				case 'submenu_default':
					$this->add_submenu_page( $page );
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
		$menu_position = $position === 'bottom' ? 80 : 3.5;

		$hook_suffix = add_menu_page(
			$page->get_page_title(),
			$page->get_menu_title(),
			$page->get_capability(),
			$page->get_menu_slug(),
			[ $page, 'render' ],
			$page->get_icon(),
			$menu_position
		);

		$page->set_hook_suffix( $hook_suffix );
	}

	/**
	 * Add a dashboard page.
	 *
	 * @param Menu_Page $page Page to add.
	 */
	private function add_dashboard_page( Menu_Page $page ) {
		$hook_suffix = add_dashboard_page(
			$page->get_page_title(),
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
	private function add_options_page( Menu_Page $page ) {
		$hook_suffix = add_options_page(
			$page->get_page_title(),
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
			$page->get_page_title(),
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

		if ( $page->get_location() === 'submenu_default' ) {
			$menu_slug = $parent->get_menu_slug();
		} else {
			$menu_slug = $page->get_menu_slug();
		}

		// Use parent hook suffix to add sub-menu page
		// that will be the first selected item in the submenu.
		$hook_suffix = add_submenu_page(
			$parent->get_menu_slug(),
			$page->get_page_title(),
			$page->get_menu_title(),
			$page->get_capability(),
			$menu_slug,
			[ $page, 'render' ],
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
