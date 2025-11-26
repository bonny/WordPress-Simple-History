<?php

namespace Simple_History\Services;

use Simple_History\Dropins\Sidebar_Add_Ons_Dropin;
use Simple_History\Helpers;
use Simple_History\Menu_Manager;
use Simple_History\Simple_History;
use Simple_History\Menu_Page;

/**
 * Add pages (history page and settings page).
 */
class Admin_Pages extends Service {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_main_admin_pages' ) );
		add_action( 'admin_page_access_denied', [ $this, 'on_admin_page_access_denied_redirect_prev_menu_location' ] );
	}

	/**
	 * Add main admin pages into the WordPress admin menu:
	 */
	public function add_main_admin_pages() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		// Logo SVG image is same as in AdminBarQuickView.scss.
		// Source is 'simple-history-wp-admin-bar-icon-20x20-no-clippath.svg'.
		$logo_icon = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9Ii0yIC0yIDI0IDI0IiBmaWxsPSJub25lIgogICAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxwYXRoIGQ9Ik0xNC4wNjI3IDAuNzc3NTg2QzkuMjM5MDQgLTEuMjkyNTMgMy43Njk2MiAwLjk1MzcxMSAxLjkxNzEzIDUuMzQ4NjNMMC4zOTEyNCA0LjY5Mzc3QzAuMTY5MDA3IDQuNTk4NCAtMC4wNTg3NTI3IDQuODE2ODcgMC4wMTM3MTIyIDUuMDU1OUwwLjg1MzU3MyA5Ljc3ODU2QzAuOTM1MjQ1IDEwLjA0OCAxLjIzNDM4IDEwLjE3MDEgMS40Njk0MSAxMC4wM0w1LjAzOTQ0IDcuMjA0MTdDNS4yNDQwMyA3LjA4MjIxIDUuMjI0NDQgNi43NjggNS4wMDY0MiA2LjY3NDQzTDMuMzcwODIgNS45NzI0OUM0Ljg5MzM2IDIuNDE0ODQgOS40NDQ4NiAwLjU2ODMgMTMuNDY1NyAyLjI5Mzg4QzE3LjQ4NjUgNC4wMTk0NiAxOS41MDQ2IDguOTI0OTMgMTcuODI2NCAxMy4xODc1QzE2LjE0ODIgMTcuNDUwMSAxMS40NzQ5IDE5LjQ4MzggNy4zODgzMSAxNy43M0M1LjYxNzMxIDE2Ljk3IDQuMjQ3NDUgMTUuNjIzMSAzLjM5OTggMTMuOTk0MUMzLjE5NDA5IDEzLjU5ODcgMi43NDEzMSAxMy40MDIgMi4zNDM0MiAxMy41NzUxQzEuOTQwNDggMTMuNzUwNSAxLjc0NzgxIDE0LjIzNzUgMS45NTAyNCAxNC42NDEyQzIuOTU4MDkgMTYuNjUxIDQuNjIzNDkgMTguMzE2IDYuNzkxMzMgMTkuMjQ2M0MxMS42ODA3IDIxLjM0NDcgMTcuMjcyMiAxOC45MTEzIDE5LjI4IDEzLjgxMTRDMjEuMjg3OSA4LjcxMTQyIDE4Ljk1MiAyLjg3NTkxIDE0LjA2MjcgMC43Nzc1ODZaIiBmaWxsPSJibGFjayIvPgogICAgPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik05LjI5ODA4IDYuMTcwNzZDOS4yOTgwOCA1Ljc3MzA3IDkuNTk5NTkgNS40NTA2OCA5Ljk3MTUxIDUuNDUwNjhDMTAuMzQzNCA1LjQ1MDY4IDEwLjY0NDkgNS43NzMwNyAxMC42NDQ5IDYuMTcwNzZWMTAuNTIyOUwxMy42ODY3IDEyLjUwMzZDMTQuMDAzNyAxMi43MTAxIDE0LjEwMDggMTMuMTU0NCAxMy45MDIyIDEzLjQ4OTdDMTMuNzA4NCAxMy44MTY4IDEzLjMwNTMgMTMuOTE3NSAxMi45OTYxIDEzLjcxNjFMOS4yOTgwOCAxMS4zMDgxVjYuMTcwNzZaIiBmaWxsPSJibGFjayIvPgo8L3N2Zz4K';

		// Main log page.
		$main_log_page = ( new Menu_Page() )
			->set_page_title( _x( 'History', 'dashboard title name', 'simple-history' ) )
			->set_menu_title( _x( 'Simple History', 'dashboard menu name', 'simple-history' ) )
			->set_menu_slug( Simple_History::MENU_PAGE_SLUG )
			->set_capability( Helpers::get_view_history_capability() )
			->set_icon( $logo_icon )
			->set_location( $admin_page_location )
			->set_order( 1 );

		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			// Add "Event log" page that is the first submenu item.
			// It becomes the first selected because it's the first item?
			// Only add if location is menu_top or menu_bottom.
			$main_log_page->add_submenu(
				( new Menu_Page() )
				->set_page_title( _x( 'Event Log - Simple History', 'dashboard title name', 'simple-history' ) )
				->set_menu_title( _x( 'Event Log', 'dashboard menu name', 'simple-history' ) )
				->set_menu_slug( Simple_History::VIEW_EVENTS_PAGE_SLUG )
				->set_capability( Helpers::get_view_history_capability() )
				->set_callback( [ $this, 'history_page_output' ] )
				->set_location( 'submenu_default' )
				->set_order( 2 )
			);
		} else {
			$main_log_page->set_callback( [ $this, 'history_page_output' ] );
		}

		$main_log_page->add();
	}

	/**
	 * Output for page with the history.
	 */
	public function history_page_output() {
		?>
		<div class="SimpleHistoryWrap">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::header_output();
			?>

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

	/**
	 * Output the common header HTML.
	 *
	 * Output this before the .wrap div.
	 *
	 * @param string $main_nav_html The main navigation HTML.
	 * @param string $sub_nav_html The sub navigation HTML.
	 */
	public static function header_output( $main_nav_html = '', $sub_nav_html = '' ) {
		// Bail if functions has already been called.
		// This is useful for child pages that do not need to care about the header status
		// or the callback status.
		if ( did_action( 'simple_history/admin_page/header_output' ) ) {
			return;
		}

		// Fire action to mark that this function has been called.
		do_action( 'simple_history/admin_page/header_output' );

		ob_start();

		// Wrap link around title if we have somewhere to go.
		$headline_link_target    = null;
		$headline_link_start_elm = '';
		$headline_link_end_elm   = '';

		$headline_link_target = Menu_Manager::get_admin_url_by_slug( Simple_History::MENU_PAGE_SLUG );

		if ( ! empty( $headline_link_target ) ) {
			$headline_link_start_elm = sprintf(
				'<a href="%1$s" class="sh-PageHeader-titleLink">',
				esc_url( $headline_link_target )
			);
			$headline_link_end_elm   = '</a>';
		}

		$allowed_link_html = [
			'a' => [
				'href'  => 1,
				'class' => 1,
			],
		];

		$menu_manager = Simple_History::get_instance()->get_menu_manager();

		$main_subnav_html_output          = $menu_manager->get_main_subnav_html_output();
		$main_subnav_sub_tabs_html_output = $menu_manager->get_main_main_subnav_sub_tabs_html_output();

		?>
		<header class="sh-PageHeader">
			<div class="sh-PageHeader-titleGroup">
				<h1 class="sh-PageHeader-title SimpleHistoryPageHeadline">
					<?php echo wp_kses( $headline_link_start_elm, $allowed_link_html ); ?>
					<img width="1000" height="156" class="sh-PageHeader-logo" src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL ); ?>css/simple-history-logo.png" alt="Simple History logotype"/>
					<?php echo wp_kses( $headline_link_end_elm, $allowed_link_html ); ?>
				</h1>
				
				<?php
				// Display note about dev mode when it's enabled.
				if ( Helpers::dev_mode_is_enabled() ) {
					?>
					<span class="sh-PageHeader-badge sh-PageHeader-badge--dev" title="<?php esc_attr_e( 'Developer mode is enabled via SIMPLE_HISTORY_DEV constant', 'simple-history' ); ?>"><?php esc_html_e( 'Dev', 'simple-history' ); ?></span>
					<?php
					// Display premium plugin toggle badge when dev mode is enabled.
					$is_premium_active = Helpers::is_premium_add_on_active();
					$badge_state_class = $is_premium_active ? 'is-active' : 'is-inactive';
					$badge_text        = $is_premium_active ? __( 'Premium: Active', 'simple-history' ) : __( 'Premium: Inactive', 'simple-history' );
					$badge_title       = $is_premium_active ? __( 'Click to deactivate premium add-on', 'simple-history' ) : __( 'Click to activate premium add-on', 'simple-history' );
					?>
					<button
						class="sh-PageHeader-badge sh-PageHeader-badge--premiumToggle <?php echo esc_attr( $badge_state_class ); ?>"
						id="sh-premium-toggle"
						title="<?php echo esc_attr( $badge_title ); ?>"
						data-plugin="simple-history-premium/simple-history-premium.php"
						data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
					>
						<span class="dashicons dashicons-admin-plugins"></span>
						<?php echo esc_html( $badge_text ); ?>
					</button>
					<?php
				}
				?>
			</div>

			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Helpers::get_header_add_ons_link();

			// Output main nav and subnav.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $main_nav_html;

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $main_subnav_html_output;
			?>
		</header>
		
		<?php
		/**
		 * Fires after the page header in Simple History admin pages.
		 * Use this to output content right after the header.
		 *
		 * @since 5.9
		 */
		do_action( 'simple_history/admin_page/after_header' );

		// Output sub nav items.
		// Todo: this contains the full html output so it should not be in this header function.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sub_nav_html;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $main_subnav_sub_tabs_html_output;

		// Output settings errors, below sub nav html but before main content.
		// When Simple History settings is placed in the WordPress settings menu
		// we do NOT need to output settings errors here, but when
		// Simple History settings is placed as a top level menu we need to output
		// settings errors here manually.
		// $output_settings_errors = in_array( Helpers::get_menu_page_location(), [ 'top', 'bottom' ], true );.
		$output_settings_errors = true;

		if ( $output_settings_errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			settings_errors();

			// WordPress will add notices after element with class .wp-header-end.
			?>
			<hr class="wp-header-end">
			<?php
		}

		// Run callback function for selected tab or sub-tab.
		$selected_main_tab     = $menu_manager->get_page_by_slug( $menu_manager::get_current_tab_slug() );
		$selected_sub_tab_page = $menu_manager->get_page_by_slug( $menu_manager::get_current_sub_tab_slug() );

		if ( $selected_sub_tab_page !== null ) {
			$selected_sub_tab_page->render();
		} elseif ( $selected_main_tab !== null ) {
			$selected_main_tab->render();
		}

		return ob_get_clean();
	}

	/**
	 * When user visits the classic log page under Dashboard the URL is:
	 * /wp-admin/index.php?page=simple_history_page
	 * But after changing to main menu the URL is different.
	 * Detect access to the old classic URL and redirect to a new page,
	 * so bookmarks and old links still work.
	 */
	public function on_admin_page_access_denied_redirect_prev_menu_location() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page    = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		$pagenow = $GLOBALS['pagenow'] ?? '';

		// Bail if not correct page.
		if ( $page !== 'simple_history_page' && $pagenow !== 'index.php' ) {
			return;
		}

		// Redirect to current event logs page location.
		wp_safe_redirect( Menu_Manager::get_admin_url_by_slug( Simple_History::MENU_PAGE_SLUG ) );

		exit;
	}
}
