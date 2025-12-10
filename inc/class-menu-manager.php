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
		$pages = $this->pages;

		/**
		 * Filter pages returned.
		 *
		 * @param array<int,Menu_Page> $pages Array of all registered menu pages.
		 */
		$pages = apply_filters( 'simple_history/menu_manager/get_pages', $pages );

		return $pages;
	}

	/**
	 * Get pages sorted by their order.
	 */
	public function get_pages_ordered() {
		$pages = $this->get_pages();

		// Sort pages by order.
		usort(
			$pages,
			function ( $a, $b ) {
				return $a->get_order() - $b->get_order();
			}
		);

		return $pages;
	}

	/**
	 * Register all menu pages with WordPress.
	 * Called during admin_menu.
	 */
	public function register_pages() {
		foreach ( $this->get_pages_ordered() as $page ) {
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
				case 'tools':
					$this->add_tools_page( $page );
					break;
				case 'submenu':
				case 'submenu_default':
					$this->add_submenu_page( $page );
					break;
				default:
					// Handle sub-pages that have parent set but no explicit location.
					if ( $page->get_parent() ) {
						$parent_location                     = $page->get_parent()->get_location();
						$parents_where_children_becomes_tabs = [ 'tools', 'dashboard', 'options' ];

						// If parent of a page is "tools", "dashboard", or "options"
						// Then it can not be added using wp default functions
						// it can only be added as a simple history tab and then subtab.
						// So check that parent is not in $non_wp_locations.
						if ( ! in_array( $parent_location, $parents_where_children_becomes_tabs, true ) ) {
							$this->add_submenu_page( $page );
						}
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
		// Add History page as a main menu item, at the root.
		// For example Jetpack adds itself at prio 3. We add it at prio 3.5 to be below Jetpack but above posts.
		$menu_position = $position === 'bottom' ? 80 : 3.5;

		$hook_suffix = add_menu_page(
			$page->get_page_title(),
			$page->get_menu_title(),
			$page->get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Filterable.
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
			$page->get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Filterable.
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
			$page->get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Filterable.
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
			$page->get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Filterable.
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
			$page->get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Filterable.
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

	/**
	 * Return all slugs for all pages.
	 *
	 * @return array<string> Array of all slugs for all pages.
	 */
	public function get_all_slugs() {
		$slugs = [];

		foreach ( $this->get_pages() as $page ) {
			$slugs[] = $page->get_menu_slug();
		}

		return $slugs;
	}

	/**
	 * Get the slug of current tab.
	 *
	 * @return string The current tab. Empty string if not set.
	 */
	public static function get_current_tab_slug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_text_field( wp_unslash( $_GET['selected-tab'] ?? '' ) );
	}

	/**
	 * Get the slug of the current sub-tab.
	 *
	 * @return string The current sub-tab. Empty string if not set.
	 */
	public static function get_current_sub_tab_slug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_text_field( wp_unslash( $_GET['selected-sub-tab'] ?? '' ) );
	}

	/**
	 * Get menu pages that are subpages to a tools, dashboard or options page.
	 * I.e. the pages that are to be shown as main tabs.
	 *
	 * @return array<Menu_Page> Array of main tabs for page with tabs.
	 */
	public function get_main_tabs_for_page_with_tabs() {
		$menu_page_location = Helpers::get_menu_page_location();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : null;

		$current_menu_page_root = $this->get_page_by_slug( $page );

		// Bail if no menu page found.
		if ( ! $current_menu_page_root ) {
			return [];
		}

		// Skip if menu page object has it's location at top level, i.e. menu_bottom or menu_top.
		if ( in_array( $current_menu_page_root->get_location(), [ 'menu_top', 'menu_bottom' ], true ) ) {
			return [];
		}

		// If "top" or "bottom" then use "Event Log" sub menu item or we will get too many tabs.
		if ( in_array( $menu_page_location, [ 'top', 'bottom' ], true ) ) {
			if ( $page === Simple_History::MENU_PAGE_SLUG ) {
				$page = Simple_History::VIEW_EVENTS_PAGE_SLUG;
			}
		}

		// Should this now just be the children of any page? Just as long as it has children.
		$current_menu_page_root = $this->get_page_by_slug( $page );

		// Bail if no menu page found after potential page slug change.
		if ( ! $current_menu_page_root ) {
			return [];
		}

		$current_menu_page_root_children = $current_menu_page_root->get_children();

		return $current_menu_page_root_children;
	}

	/**
	 * Output main nav link list with all sub menu pages.
	 *
	 * @return string
	 */
	public function get_main_subnav_html_output() {
		// Output main nav link list with all sub menu pages.
		$submenu_pages = $this->get_main_tabs_for_page_with_tabs();

		$num_pages_class = 'sh-PageNav--count-' . count( $submenu_pages );

		ob_start();
		?>
		<nav class="sh-PageNav <?php echo esc_attr( $num_pages_class ); ?>">
			<?php
			foreach ( $submenu_pages as $one_submenu_page ) {
				$is_current_tab = $one_submenu_page->is_current_tab();

				$icon_html = '';
				if ( ! empty( $one_submenu_page->get_icon() ) ) {
					$icon_html = sprintf(
						'<span class="sh-PageNav-icon sh-Icon--%1$s"></span>',
						esc_attr( $one_submenu_page->get_icon() )
					);
				}

				$is_active_class = $is_current_tab ? 'is-active' : '';
				?>
				<a href="<?php echo esc_url( $one_submenu_page->get_url() ); ?>" class="sh-PageNav-tab <?php echo esc_attr( $is_active_class ); ?>">
					<?php
					echo wp_kses(
						$icon_html,
						[
							'span' => [
								'class' => [],
							],
						]
					);
					?>
					<?php
					echo wp_kses(
						$one_submenu_page->get_menu_title(),
						[
							'span' => [
								'class' => [],
							],
						]
					);
					?>
				</a>
				<?php
			}
			?>
		</nav>
		<?php

		return ob_get_clean();
	}

	/**
	 * Output sub nav tabs for the selected tab.
	 *
	 * @return string HTML output of sub nav tabs.
	 */
	public function get_main_main_subnav_sub_tabs_html_output() {
		ob_start();

		// Output child pages/sub-sub tabs to the selected tab.
		$selected_tab_menu_page = $this->get_page_by_slug( $this::get_current_tab_slug() );

		// Bail if no menu found for selected tab.
		if ( ! $selected_tab_menu_page ) {
			return '';
		}

		$child_pages = $selected_tab_menu_page->get_children();

		if ( ! empty( $child_pages ) ) {
			?>
			<nav class="sh-SettingsTabs">
				<ul class="sh-SettingsTabs-tabs">
				<?php
				foreach ( $child_pages as $child_page ) {
					$is_current_sub_tab = $child_page->is_current_sub_tab();
					$is_active_class    = $is_current_sub_tab ? 'is-active' : '';
					$class_page_prio    = $child_page->get_order() ? 'sh-SettingsTabs-tab--prio-' . $child_page->get_order() : '';
					?>
					<li class="sh-SettingsTabs-tab <?php echo esc_attr( $class_page_prio ); ?>">
						<a href="<?php echo esc_url( $child_page->get_url() ); ?>" class="sh-SettingsTabs-link <?php echo esc_attr( $is_active_class ); ?>">
						<?php
						echo wp_kses(
							$child_page->get_menu_title(),
							[
								'span' => [
									'class' => [],
								],
							]
						);
						?>
						</a>
					</li>
					<?php
				}
				?>
				</ul>
			</nav>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Check if current request is for a menu page that should be redirected to its first child.
	 */
	public function redirect_menu_pages() {
		// Check if current request is for a request to any of our pages.
		// If so, redirect to the first child page.
		$all_menu_pages_slugs = $this->get_all_slugs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : null;

		// Bail if page is not among our pages.
		if ( ! in_array( $page, $all_menu_pages_slugs, true ) ) {
			return;
		}

		// Bail if we are on a sub-tab already.
		$selected_sub_tab = $this::get_current_sub_tab_slug();

		if ( ! empty( $selected_sub_tab ) ) {
			return;
		}

		// Get selected tab.
		$selected_tab = $this::get_current_tab_slug();

		if ( $selected_tab ) {
			$this->redirect_to_first_sub_tab( $selected_tab );
		} else {
			$this->redirect_to_first_main_tab();
		}
	}

	/**
	 * Redirect to first main tab, if page is set to redirect to first on load.
	 * Only redirect if no sub-tab is selected.
	 */
	protected function redirect_to_first_main_tab() {
		// Ensure we only act on our own pages.
		if ( ! Helpers::is_on_our_own_pages() ) {
			return;
		}

		$selected_tab     = $this::get_current_tab_slug();
		$selected_sub_tab = $this::get_current_sub_tab_slug();

		// Only act on main page, so no sub-tab or tab must be selected.
		if ( ! empty( $selected_tab ) || ! empty( $selected_sub_tab ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page              = sanitize_text_field( wp_unslash( $_GET['page'] ?? null ) );
		$current_menu_page = $this->get_page_by_slug( $page );

		// Bail if page is not a Menu_Page instance.
		if ( ! $current_menu_page instanceof Menu_Page ) {
			return;
		}

		$redirect_to_first_child_on_load = $current_menu_page->get_redirect_to_first_child_on_load();

		// Bail if no redirect is wanted.
		if ( ! $redirect_to_first_child_on_load ) {
			return;
		}

		// Get first tab to redirect to.
		$main_tabs = $this->get_main_tabs_for_page_with_tabs();

		if ( empty( $main_tabs ) ) {
			return;
		}

		$first_main_tab     = reset( $main_tabs );
		$first_main_tab_url = $first_main_tab->get_url();

		// Redirect to first main tab.
		wp_safe_redirect( $first_main_tab_url );

		exit;
	}

	/**
	 * Redirect to first child page of selected tab,
	 * if page is set to redirect to first child on load.
	 *
	 * @param string $selected_tab The selected tab.
	 */
	protected function redirect_to_first_sub_tab( $selected_tab ) {
		// Get page object for selected tab.
		$selected_tab_menu_page = $this->get_page_by_slug( $selected_tab );

		// Bail if page should not be redirected to first child on load.
		if ( ! $selected_tab_menu_page instanceof Menu_Page || ! $selected_tab_menu_page->get_redirect_to_first_child_on_load() ) {
			return;
		}

		// If we get here we are go for a redirect.
		$first_child_page     = $selected_tab_menu_page->get_children()[0] ?? null;
		$first_child_page_url = $first_child_page ? $first_child_page->get_url() : '';

		if ( ! $first_child_page_url ) {
			return;
		}

		wp_safe_redirect( $first_child_page_url );

		exit;
	}

	/**
	 * Check if a page with slug $slug exists.
	 *
	 * @param string $slug The slug to check for.
	 * @return bool True if page exists, false otherwise.
	 */
	public function page_exists( $slug ) {
		$page = $this->get_page_by_slug( $slug );
		return $page !== null;
	}

	/**
	 * Get admin URL for a menu page by its slug.
	 *
	 * Example usage:
	 * $settings_url = Menu_Manager::get_admin_url_by_slug(Simple_History::SETTINGS_MENU_PAGE_SLUG);
	 *
	 * @param string $page_slug The slug of the menu page to get URL for.
	 * @return string Full admin URL or empty string if page not found.
	 */
	public static function get_admin_url_by_slug( string $page_slug ): string {
		$menu_manager = Simple_History::get_instance()->get_menu_manager();
		$page         = $menu_manager->get_page_by_slug( $page_slug );

		if ( ! $page instanceof Menu_Page ) {
			return '';
		}

		return $page->get_url();
	}
}
