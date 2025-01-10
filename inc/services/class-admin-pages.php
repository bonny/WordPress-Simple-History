<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Add pages (history page and settings page).
 */
class Admin_Pages extends Service {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_dashboard_subpage' ) );
		add_action( 'admin_menu', array( $this, 'add_main_admin_pages' ) );
	}

	/**
	 * Add main admin pages into the WordPress admin menu:
	 *
	 * - Simple History
	 *  - History/Event log/Timeline
	 *  - Settings
	 *  - Export (move to new Tools submenu?)
	 *  - Debug
	 *  - Statistics/Reports (in the future).
	 */
	public function add_main_admin_pages() {
		$menu_slug = $this->simple_history::MENU_PAGE_SLUG;

		// Add History page as a main menu item, at the root.
		// For example Jetpack adds itself at prio 3. We add it at prio 3.5 to be below Jetpack but above posts.
		add_menu_page(
			_x( 'History', 'dashboard title name', 'simple-history' ),
			_x( 'Simple History', 'dashboard menu name', 'simple-history' ),
			Helpers::get_view_history_capability(),
			$menu_slug,
			array( $this, 'history_page_output' ),
			'data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBoZWlnaHQ9IjE5NiIgdmlld0JveD0iMCAwIDIyNSAxOTYiIHdpZHRoPSIyMjUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0iIzAwMCI+PHBhdGggZD0ibTEyNS44MzQgMGMtNTMuNjI0IDAtOTcuMjI4MSA0MS43MDI1LTk4LjM4OTkgODguODE5NmgtMjQuNTI2NzNjLTIuNDcwNTQ2IDAtMy44MTg1NTUgMi44NzA4LTIuMjM0NDIxIDQuNzU4NmwzMy4yMzMxNTEgMzkuNjAyOGMxLjc4NTQgMi4xMjcgNS4wODc1IDIuMDcxIDYuNzk4LS4xMTdsMzAuOTM0NC0zOS41NjM4YzEuNDg4OS0xLjkwNDMuMTI2MS00LjY4MDYtMi4yOTc2LTQuNjgwNmgtMjUuNzQ2M2MxLjE1ODktMzguMjMwNCAzNy41MzAxLTcyLjcyNzkgODIuMjI5NC03Mi43Mjc5IDQ0LjY5OSAwIDgyLjI1OCAzNi42NzE3IDgyLjI1OCA4MS45MDgzIDAgNDUuMjM3LTM2LjgyOSA4MS45MDgtODIuMjU4IDgxLjkwOC0xOS42ODkgMC0zNy43NTg3LTYuODktNTEuOTE5NS0xOC4zNzctMy40MzY1LTIuNzg4LTguNDc5Mi0yLjgxLTExLjYxNDguMzEzLTMuMTc1NCAzLjE2MS0zLjE4NDEgOC4zMzQuMjUyNCAxMS4yMSAxNy4xMDk4IDE0LjMxOSAzOS4xODE5IDIyLjk0NiA2My4yODE5IDIyLjk0NiA1NC4zNTQgMCA5OC40MTgtNDMuODc3IDk4LjQxOC05OCAwLTU0LjEyMzMtNDQuMDY0LTk4LTk4LjQxOC05OHoiLz48cGF0aCBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Im0xMTMuMDA5IDU3LjEwNDRjMC00LjIzMjEgMy40MzEtNy42NjI5IDcuNjYzLTcuNjYyOXM3LjY2MyAzLjQzMDggNy42NjMgNy42NjI5djQ2LjMxNTZsMzQuNjExIDIxLjA3OGMzLjYwOCAyLjE5NyA0LjcxMyA2LjkyNSAyLjQ1MyAxMC40OTQtMi4yMDUgMy40OC02Ljc5MiA0LjU1Mi0xMC4zMTEgMi40MDlsLTQyLjA3OS0yNS42MjZ6IiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PC9zdmc+',
			3.5
		);

		// Add a history page.
		add_submenu_page(
			// Use same name as main menu item, so it looks like a sub-page.
			$menu_slug,
			_x( 'Event Log - Simple History', 'dashboard title name', 'simple-history' ),
			_x( 'Event Log', 'dashboard menu name', 'simple-history' ),
			Helpers::get_view_history_capability(),
			$menu_slug,
			array( $this, 'history_page_output' )
		);

		// Add a settings page.
		// add_submenu_page(
		// $menu_slug,
		// _x( 'Simple History Settings', 'settings title name', 'simple-history' ),
		// _x( 'Settings', 'settings menu name', 'simple-history' ),
		// Helpers::get_view_settings_capability(),
		// 'simple_history_settings_page',
		// array( $this, 'settings_page_output' )
		// );

		// Add Tools page.
		// add_submenu_page(
		// $menu_slug,
		// _x( 'Simple History Tools', 'tools title name', 'simple-history' ),
		// _x( 'Tools', 'tools menu name', 'simple-history' ),
		// Helpers::get_view_settings_capability(),
		// 'simple_history_tools_page',
		// array( $this, 'tools_page_output' )
		// );

		// Add a export page.
		// add_submenu_page(
		// $menu_slug,
		// _x( 'Simple History Export', 'export title name', 'simple-history' ),
		// _x( 'Export', 'export menu name', 'simple-history' ),
		// Helpers::get_view_settings_capability(),
		// 'simple_history_export_page',
		// array( $this, 'export_page_output' )
		// );

		// Add a debug page.
		// add_submenu_page(
		// $menu_slug,
		// _x( 'Simple History Debug', 'debug title name', 'simple-history' ),
		// _x( 'Debug', 'debug menu name', 'simple-history' ),
		// Helpers::get_view_settings_capability(),
		// 'simple_history_debug_page',
		// array( $this, 'debug_page_output' )
		// );
	}

	/**
	 * Add dashboard admin page.
	 */
	public function add_dashboard_subpage() {
		if ( Helpers::setting_show_as_page() ) {
			/**
			 * Filter to determine if history page should be added to page below dashboard.
			 *
			 * @since 2.0.23
			 *
			 * @param bool $show_dashboard_page Show the page or not
			 */
			$show_dashboard_page = apply_filters( 'simple_history/show_dashboard_page', true );

			// Add a history page as a sub-page below the Dashboard menu item.
			// This only redirects to the new admin menu page.
			if ( $show_dashboard_page ) {
				add_submenu_page(
					apply_filters( 'simple_history/admin_location', 'index' ) . '.php',
					_x( 'Simple History', 'dashboard title name', 'simple-history' ),
					_x( 'Simple History', 'dashboard menu name', 'simple-history' ),
					Helpers::get_view_history_capability(),
					'simple_history_page',
					array( $this, 'history_page_output_redirect_to_main_page' )
				);
			}
		}
	}

	/**
	 * Redirect to main page when user visits the old main log page
	 * at /wp-admin/index.php?page=simple_history_page
	 */
	public function history_page_output_redirect_to_main_page() {
		wp_redirect( admin_url( 'admin.php?page=' . $this->simple_history::MENU_PAGE_SLUG ) );
		exit;
	}

	/**
	 * Output for page with the history.
	 */
	public function history_page_output() {
		?>
		<div class="SimpleHistoryWrap">

			<header class="sh-PageHeader">
				<h1 class="sh-PageHeader-title SimpleHistoryPageHeadline">
					<img width="1100" height="156" class="sh-PageHeader-logo" src="<?php echo esc_attr( SIMPLE_HISTORY_DIR_URL ); ?>css/simple-history-logo.png" alt="Simple History logotype"/>
				</h1>

				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Helpers::get_header_add_ons_link();
				?>
				
				<?php
				// Add link to settings.
				if ( current_user_can( Helpers::get_view_settings_capability() ) ) {
					?>
					<a href="<?php echo esc_url( Helpers::get_settings_page_url() ); ?>" class="sh-PageHeader-rightLink">
						<span class="sh-PageHeader-settingsLinkIcon sh-Icon sh-Icon--settings"></span>
						<span class="sh-PageHeader-settingsLinkText"><?php esc_html_e( 'Settings', 'simple-history' ); ?></span>
					</a>
					<?php
				}
				?>
			</header>

			<?php // WordPress will add notices after element with class .wp-header-end. ?>
			<hr class="wp-header-end">

			<div class="wrap">

				<?php
				/**
				 * Fires before the gui div.
				 *
				 * @since 2.0
				 *
				 * @param Simple_History $instance This class.
				 */
				do_action( 'simple_history/history_page/before_gui', $this->simple_history );
				?>

				<div class="SimpleHistoryGuiWrap">
					<?php
					/**
					 * Fires at top of the gui div wrap.
					 *
					 * @since 5.0
					 *
					 * @param Simple_History $instance This class.
					 */
					do_action( 'simple_history/history_page/gui_wrap_top', $this->simple_history );

					/**
					 * Fires after the gui div.
					 * (Bad name, since it's actually at the bottom of the gui div.)
					 *
					 * @since 2.0
					 *
					 * @param Simple_History $instance This class.
					 */
					do_action( 'simple_history/history_page/after_gui', $this->simple_history );
					?>
				</div>

			</div>

		</div>
		<?php
	}
}
