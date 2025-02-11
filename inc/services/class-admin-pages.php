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
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		$admin_page_location = Helpers::setting_menu_page_location();
		$admin_page_position = '';

		switch ( $admin_page_location ) {
			case 'bottom':
				$admin_page_position = 80;
				break;
			case 'top':
			default:
				$admin_page_position = 3.5;
				break;
		}

		// Add History page as a main menu item, at the root.
		// For example Jetpack adds itself at prio 3. We add it at prio 3.5 to be below Jetpack but above posts.
		add_menu_page(
			_x( 'History', 'dashboard title name', 'simple-history' ),
			_x( 'Simple History', 'dashboard menu name', 'simple-history' ),
			Helpers::get_view_history_capability(),
			$this->simple_history::MENU_PAGE_SLUG,
			array( $this, 'history_page_output' ),
			// Logo SVG image is same as in AdminBarQuickView.scss.
			// Source is 'simple-history-wp-admin-bar-icon-20x20-no-clippath.svg'.
			'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9Ii0yIC0yIDI0IDI0IiBmaWxsPSJub25lIgogICAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxwYXRoIGQ9Ik0xNC4wNjI3IDAuNzc3NTg2QzkuMjM5MDQgLTEuMjkyNTMgMy43Njk2MiAwLjk1MzcxMSAxLjkxNzEzIDUuMzQ4NjNMMC4zOTEyNCA0LjY5Mzc3QzAuMTY5MDA3IDQuNTk4NCAtMC4wNTg3NTI3IDQuODE2ODcgMC4wMTM3MTIyIDUuMDU1OUwwLjg1MzU3MyA5Ljc3ODU2QzAuOTM1MjQ1IDEwLjA0OCAxLjIzNDM4IDEwLjE3MDEgMS40Njk0MSAxMC4wM0w1LjAzOTQ0IDcuMjA0MTdDNS4yNDQwMyA3LjA4MjIxIDUuMjI0NDQgNi43NjggNS4wMDY0MiA2LjY3NDQzTDMuMzcwODIgNS45NzI0OUM0Ljg5MzM2IDIuNDE0ODQgOS40NDQ4NiAwLjU2ODMgMTMuNDY1NyAyLjI5Mzg4QzE3LjQ4NjUgNC4wMTk0NiAxOS41MDQ2IDguOTI0OTMgMTcuODI2NCAxMy4xODc1QzE2LjE0ODIgMTcuNDUwMSAxMS40NzQ5IDE5LjQ4MzggNy4zODgzMSAxNy43M0M1LjYxNzMxIDE2Ljk3IDQuMjQ3NDUgMTUuNjIzMSAzLjM5OTggMTMuOTk0MUMzLjE5NDA5IDEzLjU5ODcgMi43NDEzMSAxMy40MDIgMi4zNDM0MiAxMy41NzUxQzEuOTQwNDggMTMuNzUwNSAxLjc0NzgxIDE0LjIzNzUgMS45NTAyNCAxNC42NDEyQzIuOTU4MDkgMTYuNjUxIDQuNjIzNDkgMTguMzE2IDYuNzkxMzMgMTkuMjQ2M0MxMS42ODA3IDIxLjM0NDcgMTcuMjcyMiAxOC45MTEzIDE5LjI4IDEzLjgxMTRDMjEuMjg3OSA4LjcxMTQyIDE4Ljk1MiAyLjg3NTkxIDE0LjA2MjcgMC43Nzc1ODZaIiBmaWxsPSJibGFjayIvPgogICAgPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik05LjI5ODA4IDYuMTcwNzZDOS4yOTgwOCA1Ljc3MzA3IDkuNTk5NTkgNS40NTA2OCA5Ljk3MTUxIDUuNDUwNjhDMTAuMzQzNCA1LjQ1MDY4IDEwLjY0NDkgNS43NzMwNyAxMC42NDQ5IDYuMTcwNzZWMTAuNTIyOUwxMy42ODY3IDEyLjUwMzZDMTQuMDAzNyAxMi43MTAxIDE0LjEwMDggMTMuMTU0NCAxMy45MDIyIDEzLjQ4OTdDMTMuNzA4NCAxMy44MTY4IDEzLjMwNTMgMTMuOTE3NSAxMi45OTYxIDEzLjcxNjFMOS4yOTgwOCAxMS4zMDgxVjYuMTcwNzZaIiBmaWxsPSJibGFjayIvPgo8L3N2Zz4K',
			$admin_page_position
			// 3.5 = Below dashboard and jetpack
			// 5 = Below posts.
			// 60 = Appearance
			// 80 = Below settings.
		);

		// Add a history page.
		// Uses same name as main menu item, so it looks like a sub-page.
		add_submenu_page(
			$this->simple_history::MENU_PAGE_SLUG,
			_x( 'Event Log - Simple History', 'dashboard title name', 'simple-history' ),
			_x( 'Event Log', 'dashboard menu name', 'simple-history' ),
			Helpers::get_view_history_capability(),
			$this->simple_history::MENU_PAGE_SLUG,
			array( $this, 'history_page_output' ),
			10
		);
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
	 * @param string $main_nav_html The main navigation HTML.
	 * @param string $sub_nav_html The sub navigation HTML.
	 */
	public static function header_output( $main_nav_html = '', $sub_nav_html = '' ) {
		ob_start();

		// Wrap link around title if we have somewhere to go.
		$headline_link_target = null;
		$headline_link_start_elm = '';
		$headline_link_end_elm = '';

		if ( Helpers::setting_show_as_page() ) {
			$headline_link_target = Simple_History::get_view_history_page_admin_url();
		} else if ( Helpers::setting_show_on_dashboard() ) {
			$headline_link_target = admin_url( 'index.php' );
		}

		if ( ! is_null( $headline_link_target ) ) {
			$headline_link_start_elm = sprintf(
				'<a href="%1$s" class="sh-PageHeader-titleLink">',
				esc_url( $headline_link_target )
			);
			$headline_link_end_elm = '</a>';
		}

		$allowed_link_html = [
			'a' => [
				'href' => 1,
				'class' => 1,
			],
		];

		?>
		<header class="sh-PageHeader">
			<h1 class="sh-PageHeader-title SimpleHistoryPageHeadline">
				<?php echo wp_kses( $headline_link_start_elm, $allowed_link_html ); ?>          
				<img width="1000" height="156" class="sh-PageHeader-logo" src="<?php echo esc_attr( SIMPLE_HISTORY_DIR_URL ); ?>css/simple-history-logo.png" alt="Simple History logotype"/>
				<?php echo wp_kses( $headline_link_end_elm, $allowed_link_html ); ?>
			</h1>
			
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Helpers::get_header_add_ons_link();

			// Output main nav and subnav.
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $main_nav_html;
			?>
		</header>

		<?php
		settings_errors();

		// WordPress will add notices after element with class .wp-header-end.
		?>
		<hr class="wp-header-end">
		<?php

		// Output sub nav items.
		// Todo: this contains the full html output so it should not be in this header function.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sub_nav_html;

		return ob_get_clean();
	}
}
