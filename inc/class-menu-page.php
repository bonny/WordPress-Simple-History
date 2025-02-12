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

	/** @var Menu_Page|null Parent page if this is a submenu item. */
	private $parent = null;

	/** @var string Location in admin menu. One of 'menu_top', 'menu_bottom', 'dashboard', 'settings', 'tools'. */
	private $location = 'menu_top';

	/** @var string Hook suffix/page ID returned by add_menu_page() etc. */
	private $hook_suffix = '';

	/**
	 * Set the page title.
	 *
	 * @param string $title Page title.
	 * @return self
	 */
	public function title( $title ) {
		$this->title = $title;
		return $this;
	}

	/**
	 * Set the menu title.
	 *
	 * @param string $menu_title Menu title.
	 * @return self
	 */
	public function menu_title( $menu_title ) {
		$this->menu_title = $menu_title;
		return $this;
	}

	/**
	 * Set required capability.
	 *
	 * @param string $capability Required capability.
	 * @return self
	 */
	public function capability( $capability ) {
		$this->capability = $capability;
		return $this;
	}

	/**
	 * Set menu slug.
	 *
	 * @param string $menu_slug Menu slug.
	 * @return self
	 */
	public function menu_slug( $menu_slug ) {
		$this->menu_slug = $menu_slug;
		return $this;
	}

	/**
	 * Set render callback.
	 *
	 * @param callable|string|array $callback Callback function/method.
	 * @return self
	 */
	public function callback( $callback ) {
		$this->callback = $callback;
		return $this;
	}

	/**
	 * Set menu icon.
	 *
	 * @param string $icon Icon name or URL.
	 * @return self
	 */
	public function icon( $icon ) {
		$this->icon = $icon;
		return $this;
	}

	/**
	 * Set menu order.
	 *
	 * @param int $order Order number.
	 * @return self
	 */
	public function order( $order ) {
		$this->order = $order;
		return $this;
	}

	/**
	 * Set parent page.
	 *
	 * @param Menu_Page $parent Parent page.
	 * @return self
	 */
	public function parent( Menu_Page $parent ) {
		$this->parent = $parent;
		return $this;
	}

	/**
	 * Set menu location.
	 *
	 * @param string $location Location in admin menu.
	 * @return self
	 */
	public function location( $location ) {
		$this->location = $location;
		return $this;
	}

	/**
	 * Get page title.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get menu title.
	 *
	 * @return string
	 */
	public function get_menu_title() {
		return $this->menu_title;
	}

	/**
	 * Get required capability.
	 *
	 * @return string
	 */
	public function get_capability() {
		return $this->capability;
	}

	/**
	 * Get menu slug.
	 *
	 * @return string
	 */
	public function get_menu_slug() {
		return $this->menu_slug;
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return $this->icon;
	}

	/**
	 * Get order.
	 *
	 * @return int
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Get parent page.
	 *
	 * @return Menu_Page|null
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * Get menu location.
	 *
	 * @return string
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
	 * @return string
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
}
