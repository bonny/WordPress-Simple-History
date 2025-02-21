<?php

namespace Simple_History;

/**
 * Class that represents a menu page in the WordPress admin.
 */
class Menu_Page {
	/** @var string Page title shown in browser title. */
	private $page_title = '';

	/** @var string Menu title shown in admin menu. */
	private $menu_title = '';

	/** @var string Required capability to view page. */
	private $capability = 'manage_options';

	/** @var string Unique menu slug. */
	private $menu_slug = '';

	/** @var callable|string|array Callback to render page contents. */
	private $callback;

	/** @var string Dashicon name or URL to icon. */
	private $icon = '';

	/** @var int Order among sibling menu items. */
	private $order = 10;

	/** @var Menu_Page|null Parent page if this is a submenu item. */
	private $parent = null;

	/** @var string|null Location in admin menu. One of 'menu_top', 'menu_bottom', 'dashboard', 'settings', 'tools'. */
	private $location = null;

	/** @var string Hook suffix/page ID returned by add_menu_page() etc. */
	private $hook_suffix = '';

	/** @var Menu_Manager|null Reference to menu manager instance. */
	private $menu_manager = null;

	/** @var array<Menu_Page> Array of submenu pages. */
	private $submenu_pages = [];

	/**
	 * Locations where WordPress menus can be added.
	 *
	 * WordPress menus are added with these functions:
	 *
	 * add_menu_page()
	 * add_submenu_page()
	 * add_management_page()
	 * add_options_page()
	 * add_dashboard_page()
	 *
	 * so lets use that naming convention here to.
	 *
	 * @var array<string> WordPress menu locations.
	 */
	private $wordpress_locations = [
		'menu_top',
		'menu_bottom',
		'submenu',
		'submenu_default',
		'dashboard',
		'tools', // Management = "tools".
		'options', // Options = "settings".
	];

	/**
	 * Set the page title.
	 *
	 * @param string $page_title Page title.
	 * @return self
	 */
	public function set_page_title( $page_title ) {
		$this->page_title = $page_title;

		return $this;
	}

	/**
	 * Set the menu title and optionally generate a slug from it.
	 *
	 * @param string $menu_title Menu title.
	 * @return self Chainable method.
	 */
	public function set_menu_title( $menu_title ) {
		$this->menu_title = $menu_title;

		return $this;
	}

	/**
	 * Set required capability.
	 *
	 * @param string $capability Required capability.
	 * @return self Chainable method.
	 */
	public function set_capability( $capability ) {
		$this->capability = $capability;

		return $this;
	}

	/**
	 * Set menu slug.
	 *
	 * @param string|null $menu_slug Menu slug. If null, will auto-generate from menu title.
	 * @return self Chainable method.
	 */
	public function set_menu_slug( $menu_slug = null ) {
		if ( $menu_slug === null ) {
			// Generate a unique fallback.
			$menu_slug = 'simple-history-' . uniqid();
		}

		$this->menu_slug = sanitize_text_field( $menu_slug );

		return $this;
	}

	/**
	 * Set render callback.
	 *
	 * @param callable|string|array $callback Callback function/method.
	 * @return self Chainable method.
	 */
	public function set_callback( $callback ) {
		$this->callback = $callback;

		return $this;
	}

	/**
	 * Set menu icon.
	 *
	 * @param string $icon Icon name or URL.
	 * @return self Chainable method.
	 */
	public function set_icon( $icon ) {
		$this->icon = $icon;

		return $this;
	}

	/**
	 * Set menu order.
	 *
	 * @param int $order Order number.
	 * @return self Chainable method.
	 */
	public function set_order( $order ) {
		$this->order = $order;

		return $this;
	}

	/**
	 * Set parent page.
	 *
	 * @param Menu_Page|string $parent Parent page object or menu slug.
	 * @return self Chainable method.
	 * @throws \InvalidArgumentException If parent is not a Menu_Page object or string.
	 */
	public function set_parent( $parent ) {
		if ( ! $parent instanceof Menu_Page && ! is_string( $parent ) ) {
			throw new \InvalidArgumentException( 'Parent must be a Menu_Page object or a menu slug string.' );
		}

		// If $parents is menu page object then use it directly.
		if ( $parent instanceof Menu_Page ) {
			$this->parent = $parent;

			return $this;
		}

		// If string then get the actual menu page instance from the menu manager.
		if ( is_string( $parent ) ) {
			// Throw if menu_manager not set.
			if ( ! $this->menu_manager ) {
				throw new \InvalidArgumentException( 'Parent menu slug requires a menu manager instance.' );
			}

			$parent_page = $this->menu_manager->get_page_by_slug( $parent );

			if ( ! $parent_page ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Parent page with slug "%s" not found. All existing page slugs: %s',
						esc_html( $parent ),
						esc_html( implode( ',', $this->menu_manager->get_all_slugs() ) )
					)
				);
			}

			$this->parent = $parent_page;
		} else {
			$this->parent = $parent;
		}

		return $this;
	}

	/**
	 * Get parent page.
	 *
	 * @return Menu_Page|null The parent page object or null if no parent.
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * Get parent page menu slug.
	 *
	 * @return string|null The parent page menu slug or null if no parent.
	 */
	public function get_parent_menu_slug() {
		if ( empty( $this->parent ) ) {
			return null;
		}

		return $this->parent->get_menu_slug();
	}

	/**
	 * Set menu location.
	 *
	 * WordPress location can be:
	 * - 'menu_top'
	 * - 'top' (same as 'menu_top')
	 * - 'menu_bottom'
	 * - 'bottom' (same as 'menu_bottom')
	 * - 'dashboard'
	 * - 'inside_dashboard', (same as 'dashboard')
	 * - 'management' (= tools)
	 * - 'inside_tools' (same as management)
	 * - 'tools' (same as 'management')
	 * - 'options'
	 * - 'settings' (same as 'options')
	 * - 'submenu'
	 * - 'submenu_default' (submenu with same slug as parent, to be used as default)
	 *
	 * @param string $location Location in admin menu.
	 * @return self Chainable method.
	 */
	public function set_location( $location ) {
		// Normalize location.
		if ( in_array( $location, $this->wordpress_locations, true ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			// Use WordPress location names as-is.
		} elseif ( 'top' === $location ) {
			$location = 'menu_top';
		} elseif ( 'bottom' === $location ) {
			$location = 'menu_bottom';
		} elseif ( 'inside_dashboard' === $location ) {
			$location = 'dashboard';
		} elseif ( 'inside_tools' === $location ) {
			$location = 'tools';
		} elseif ( 'management' === $location ) {
			$location = 'tools';
		} elseif ( 'settings' === $location ) {
			$location = 'options';
		} else {
			// Default to 'menu_top' if location is not recognized.
			$location = 'menu_top';
		}

		$this->location = $location;

		return $this;
	}

	/**
	 * Get page title.
	 *
	 * @return string The page title.
	 */
	public function get_page_title() {
		return $this->page_title;
	}

	/**
	 * Get menu title.
	 *
	 * @return string The menu title.
	 */
	public function get_menu_title() {
		return $this->menu_title;
	}

	/**
	 * Get required capability.
	 *
	 * @return string The required capability.
	 */
	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Get menu slug.
	 *
	 * @return string The menu slug.
	 */
	public function get_menu_slug() {
		return $this->menu_slug;
	}

	/**
	 * Get icon.
	 *
	 * @return string The icon name or URL.
	 */
	public function get_icon() {
		return $this->icon;
	}

	/**
	 * Get order.
	 *
	 * @return int The menu order.
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Get menu location.
	 *
	 * @return string The menu location.
	 */
	public function get_location() {
		return $this->location;
	}

	/**
	 * Set the hook suffix returned by add_menu_page() etc.
	 *
	 * @param string $hook_suffix Hook suffix.
	 */
	public function set_hook_suffix( $hook_suffix ) {
		$this->hook_suffix = $hook_suffix;
	}

	/**
	 * Get the hook suffix for this page.
	 *
	 * @return string The hook suffix.
	 */
	public function get_hook_suffix() {
		return $this->hook_suffix;
	}

	/**
	 * Render the page contents.
	 */
	public function render() {
		if ( is_callable( $this->callback ) ) {
			call_user_func( $this->callback );
		} elseif ( is_string( $this->callback ) ) {
			echo wp_kses_post( $this->callback );
		}
	}

	/**
	 * Add a submenu page to this page.
	 * Sets the parent of the submenu page to this page.
	 *
	 * @param Menu_Page $submenu_page Page to add as submenu.
	 * @return Menu_Page The added submenu page.
	 */
	public function add_submenu( Menu_Page $submenu_page ) {
		$submenu_page->set_parent( $this );
		$this->submenu_pages[] = $submenu_page;

		// Pass menu manager reference if we have one.
		if ( $this->menu_manager ) {
			$submenu_page->set_menu_manager( $this->menu_manager );
			$this->menu_manager->add_page( $submenu_page );
		}

		return $submenu_page;
	}

	/**
	 * Get all submenu pages added to this page.
	 *
	 * @return array<Menu_Page> Array of submenu pages.
	 */
	public function get_submenu_pages() {
		return $this->submenu_pages;
	}

	/**
	 * Generate a menu slug from a string.
	 *
	 * @param string $string String to generate slug from.
	 * @return string The generated slug.
	 */
	private function generate_menu_slug( $string ) {
		// Convert to lowercase and replace spaces with dashes.
		$slug = strtolower( $string );
		$slug = str_replace( ' ', '-', $slug );

		// Remove any character that isn't a letter, number, or dash.
		$slug = preg_replace( '/[^a-z0-9\-]/', '', $slug );

		// Remove multiple consecutive dashes.
		$slug = preg_replace( '/-+/', '-', $slug );

		// Trim dashes from beginning and end.
		$slug = trim( $slug, '-' );

		// Ensure slug starts with 'simple-history-'.
		if ( ! str_starts_with( $slug, 'simple-history-' ) ) {
			$slug = 'simple-history-' . $slug;
		}

		return $slug;
	}

	/**
	 * Sanitize a menu slug.
	 *
	 * @param string $slug Slug to sanitize.
	 * @return string The sanitized slug.
	 */
	private function sanitize_menu_slug( $slug ) {
		// Use WordPress's sanitize_key function as base.
		$slug = sanitize_key( $slug );

		// Ensure slug starts with 'simple-history'.
		if ( ! str_starts_with( $slug, 'simple-history' ) ) {
			$slug = 'simple-history-' . $slug;
		}

		return $slug;
	}

	/**
	 * Set the menu manager instance.
	 * Used to lookup parent pages by slug.
	 *
	 * @param Menu_Manager $menu_manager Menu manager instance.
	 * @return self Chainable method.
	 */
	public function set_menu_manager( Menu_Manager $menu_manager ) {
		$this->menu_manager = $menu_manager;

		return $this;
	}

	/**
	 * Return URL to a menu page.
	 */
	public function get_url() {
		// Some URL:s can't be generated with `menu_page_url()` since it only works within the admin area.
		// But we want to be able to link to for example settings page also from front end.

		// If settings page.
		if ( 'options' === $this->location ) {
			return admin_url( 'options-general.php?page=' . $this->menu_slug );
		}

		// If location is empty and parent exists and also has empty location
		// then add as a sub-sub-tab to parent.
		if ( empty( $this->location ) && $this->parent && empty( $this->parent->get_location() ) ) {
			return add_query_arg(
				[
					'selected-sub-tab' => $this->menu_slug,
				],
				$this->parent->get_url()
			);
		}

		// If location is empty the add as tab to parent.
		if ( empty( $this->location ) && $this->parent ) {
			return add_query_arg(
				[
					'selected-tab' => $this->menu_slug,
				],
				$this->parent->get_url()
			);
		}

		// Fallback to use WP function if no special case.
		return menu_page_url( $this->menu_slug, false );
	}

	/**
	 * Determine if current URL is the selected main tab for this page.
	 *
	 * @uses $_SERVER['REQUEST_URI']
	 * @uses $_GET['selected-tab']
	 */
	public function is_current_tab() {
		return $this->menu_slug === Menu_Manager::get_current_tab_slug();
	}

	/**
	 * Determine if current URL is the selected sub tab for this page.
	 *
	 * @uses $_SERVER['REQUEST_URI']
	 * @uses $_GET['selected-tab']
	 */
	public function is_current_sub_tab() {
		return $this->menu_slug === Menu_Manager::get_current_sub_tab_slug();
	}

	/**
	 * Get admin URL for a menu page by its slug.
	 *
	 * Example usage:
	 * $settings_url = Menu_Page::get_admin_url_by_slug(Simple_History::SETTINGS_MENU_PAGE_SLUG);
	 *
	 * @param string $page_slug The slug of the menu page to get URL for.
	 * @return string Full admin URL or empty string if page not found.
	 */
	public static function get_admin_url_by_slug( string $page_slug ): string {
		$menu_manager = Simple_History::get_instance()->get_menu_manager();
		$page = $menu_manager->get_page_by_slug( $page_slug );

		if ( ! $page instanceof self ) {
			return '';
		}

		return $page->get_url();
	}

	/**
	 * Get all children to this page.
	 * I.e. get all children that have this page as the parent page.
	 */
	public function get_children() {
		$child_pages = [];

		foreach ( $this->menu_manager->get_pages() as $page ) {
			if ( $page->get_parent() === $this ) {
				$child_pages[] = $page;
			}
		}

		return $child_pages;
	}
}
