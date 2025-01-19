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
		// Add History page as a main menu item, at the root.
		// For example Jetpack adds itself at prio 3. We add it at prio 3.5 to be below Jetpack but above posts.
		add_menu_page(
			_x( 'History', 'dashboard title name', 'simple-history' ),
			_x( 'Simple History', 'dashboard menu name', 'simple-history' ),
			Helpers::get_view_history_capability(),
			$this->simple_history::MENU_PAGE_SLUG,
			array( $this, 'history_page_output' ),
			// Logo SVG image is same as in AdminBarQuickView.scss.
			'data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBoZWlnaHQ9IjE1MiIgdmlld0JveD0iMCAwIDE1OSAxNTIiIHdpZHRoPSIxNTkiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGcgZmlsbD0iIzAwMCI+PHBhdGggZD0ibTExMS40NTYgNS45MDI4N2MtMzguMjMtMTUuNzE0OC04MS41NzkxIDEuMzM3MDItOTYuMjYxNCAzNC43MDAwM2wtMTIuMDkzNzQtNC45NzEyYy0xLjc2MTM2LS43MjQtMy41NjY1MTcuOTM0NS0yLjk5MjE4MSAyLjc0OTFsNi42NTY0OTEgMzUuODUxYy42NDczMSAyLjA0NTEgMy4wMTgxNiAyLjk3MjIgNC44ODA5MyAxLjkwODZsMjguMjk1MS0yMS40NTE1YzEuNjIxNC0uOTI1OCAxLjQ2NjItMy4zMTExLS4yNjE4LTQuMDIxNGwtMTIuOTYzMy01LjMyODZjMTIuMDY3Mi0yNy4wMDcyIDQ4LjE0MTItNDEuMDI0NzggODAuMDA4OS0yNy45MjU0IDMxLjg2OCAxMy4wOTk0IDQ3Ljg2MyA1MC4zMzgyIDM0LjU2MiA4Mi42OTY1LTEzLjMwMSAzMi4zNTgtNTAuMzQwNiA0Ny43OTctODIuNzI5NCAzNC40ODQtMTQuMDM2NS01Ljc3LTI0Ljg5MzUtMTUuOTk0LTMxLjYxMTgtMjguMzYxLTEuNjMwNC0zLjAwMS01LjIxOS00LjQ5NS04LjM3MjUtMy4xOC0zLjE5MzYgMS4zMzEtNC43MjA2IDUuMDI4LTMuMTE2MyA4LjA5MiA3Ljk4OCAxNS4yNTcgMjEuMTg3NSAyNy44OTcgMzguMzY5MSAzNC45NTkgMzguNzUxNiAxNS45MjkgODMuMDY3OS0yLjU0MyA5OC45ODE5LTQxLjI1OCAxNS45MTQtMzguNzE1Mi0yLjYtODMuMDE0MS00MS4zNTItOTguOTQzMTN6Ii8+PHBhdGggY2xpcC1ydWxlPSJldmVub2RkIiBkPSJtNzMuNjkzOSA0Ni44NDM5YzAtMy4wMTg5IDIuMzg5Ni01LjQ2NjIgNS4zMzc0LTUuNDY2MnM1LjMzNzQgMi40NDczIDUuMzM3NCA1LjQ2NjN2MzMuMDM4NWwyNC4xMDgzIDE1LjAzNThjMi41MTMgMS41NjczIDMuMjgyIDQuOTQwNSAxLjcwOCA3LjQ4NTctMS41MzYgMi40ODMtNC43MzEgMy4yNDgtNy4xODIgMS43MTlsLTI5LjMwOTEtMTguMjh6IiBmaWxsLXJ1bGU9ImV2ZW5vZGQiLz48L2c+PC9zdmc+',
			3.5
		);

		// Add a history page.
		add_submenu_page(
			// Use same name as main menu item, so it looks like a sub-page.
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
		$redirect_to_url = add_query_arg(
			[
				'page' => $this->simple_history::MENU_PAGE_SLUG,
				'simple_history_redirected_from_dashboard_menu' => '1',
			],
			admin_url( 'admin.php' )
		);

		if ( headers_sent() ) {
			// Decode the URL to prevent double encoding of ampersands.
			$js_url = html_entity_decode( esc_url( $redirect_to_url ) );
			?>
			<script>
				window.location = <?php echo wp_json_encode( $js_url ); ?>;
			</script>
			<?php
		} else {
			wp_redirect( $redirect_to_url );
			exit;
		}
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
	 * Detect when user is redirected from the old Simple History log page
	 * below the dashboard menu item or from the Settings › Simple History page
	 * and display a notice that the main page now is directly in the main admin nav.
	 */
	public static function get_old_menu_page_location_redirect_notice() {
		$redirected_from_dashboard_menu = filter_input( INPUT_GET, 'simple_history_redirected_from_dashboard_menu', FILTER_VALIDATE_BOOLEAN );
		$redirected_from_settings_menu = filter_input( INPUT_GET, 'simple_history_redirected_from_settings_menu', FILTER_VALIDATE_BOOLEAN );

		if ( ! $redirected_from_dashboard_menu && ! $redirected_from_settings_menu ) {
			return '';
		}

		$allowed_html = [
			'a' => [
				'href' => 1,
			],
		];

		$message = __( 'Hey there! Simple History has moved to the top level of the menu for easier access.', 'simple-history' );

		$icon_svg_contents = file_get_contents( SIMPLE_HISTORY_PATH . 'css/icons/moving_24dp_FILL0_wght400_GRAD0_opsz48.svg' );

		$message = sprintf(
			'
			<div class="">
				<div style="%3$s">%2$s</div>
				<p style="%4$s">
					%1$s
				</p>
			</div>
			',
			wp_kses( $message, $allowed_html ),
			$icon_svg_contents,
			'
				float: left;
				color: var(--sh-color-pink);
    			transform: scale(1.5) rotate(-115deg) translate(0.3rem, -0.1rem);
    			z-index: 999;
				margin-inline: .75rem;
			', // 3 icon styles
			'' // 4 text styles
		);

		return wp_get_admin_notice(
			$message,
			[
				'type' => 'warning',
				'is_dismissible' => false,
				'is_inline' => true,
			]
		);
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

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_old_menu_page_location_redirect_notice();

		// Output sub nav items.
		// Todo: this contains the full html output so it should not be in this header function.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $sub_nav_html;

		return ob_get_clean();
	}
}
