<?php

namespace Simple_History;

/**
 * Class that represents a menu page in the WordPress admin.
 */
class Menu_Page {
	/** @var string Page title shown in browser title. */
	private $title = '';

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

	/** @var Menu_Page|string|null Parent page if this is a submenu item. */
	private $parent = null;

	/** @var string Location in admin menu. One of 'menu_top', 'menu_bottom', 'dashboard', 'settings', 'tools'. */
	private $location = 'menu_top';

	/** @var string Hook suffix/page ID returned by add_menu_page() etc. */
	private $hook_suffix = '';

	/** @var Menu_Manager|null Reference to menu manager instance. */
	private $menu_manager = null;

	/**
	 * Set the page title.
	 *
	 * @param string $title Page title.
	 * @return self
	 */
	public function set_title( $title ) {
		$this->title = $title;

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

		// If no slug is set, generate one from the title.
		if ( empty( $this->menu_slug ) ) {
			$this->set_menu_slug( null );
		}

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
	 * If not provided, will generate one based on the menu title.
	 *
	 * @param string|null $menu_slug Menu slug. If null, will auto-generate from menu title.
	 * @return self Chainable method.
	 */
	public function set_menu_slug( $menu_slug = null ) {
		if ( $menu_slug === null && ! empty( $this->menu_title ) ) {
			// Generate slug from menu title if not provided.
			$menu_slug = $this->generate_menu_slug( $this->menu_title );
		} elseif ( $menu_slug === null && ! empty( $this->title ) ) {
			// Use page title as fallback if menu title is not set.
			$menu_slug = $this->generate_menu_slug( $this->title );
		} elseif ( $menu_slug === null ) {
			// Generate a unique fallback slug if no menu title or page title exists yet.
			$menu_slug = 'simple-history-' . uniqid();
		}

		$this->menu_slug = $this->sanitize_menu_slug( $menu_slug );

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

		// If string then get the actual menu page instance from the manager.
		if ( is_string( $parent ) && $this->menu_manager ) {
			$parent_page = $this->menu_manager->get_page( $parent );

			if ( ! $parent_page ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Parent page with slug "%s" not found.',
						$parent
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
	 * @return Menu_Page|string|null The parent page object, menu slug, or null if no parent.
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

		if ( $this->parent instanceof Menu_Page ) {
			return $this->parent->get_menu_slug();
		}

		// If parent is a string then it's already a menu slug.
		return $this->parent;
	}

	/**
	 * Set menu location.
	 *
	 * @param string $location Location in admin menu.
	 * @return self Chainable method.
	 */
	public function set_location( $location ) {
		$this->location = $location;

		return $this;
	}

	/**
	 * Get page title.
	 *
	 * @return string The page title.
	 */
	public function get_title() {
		return $this->title;
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

		// Ensure slug starts with 'simple-history-'.
		if ( ! str_starts_with( $slug, 'simple-history-' ) ) {
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
}
