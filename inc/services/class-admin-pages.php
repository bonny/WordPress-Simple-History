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
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
	}

	/**
	 * Add pages (history page and settings page)
	 */
	public function add_admin_pages() {
		// Add a history page as a sub-page below the Dashboard menu item.
		if ( Helpers::setting_show_as_page() ) {
			/**
			 * Filter to determine if history page should be added to page below dashboard or not
			 *
			 * @since 2.0.23
			 *
			 * @param bool $show_dashboard_page Show the page or not
			 */
			$show_dashboard_page = apply_filters( 'simple_history/show_dashboard_page', true );

			if ( $show_dashboard_page ) {
				add_submenu_page(
					apply_filters( 'simple_history/admin_location', 'index' ) . '.php',
					_x( 'Simple History', 'dashboard title name', 'simple-history' ),
					_x( 'Simple History', 'dashboard menu name', 'simple-history' ),
					Helpers::get_view_history_capability(),
					'simple_history_page',
					array( $this, 'history_page_output' )
				);
			}
		}
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
				// Add link to settings & tools.
				if ( current_user_can( Helpers::get_view_settings_capability() ) ) {
					?>
					<a href="<?php echo esc_url( Helpers::get_settings_page_url() ); ?>" class="sh-PageHeader-rightLink">
						<span class="sh-PageHeader-settingsLinkIcon sh-Icon sh-Icon--settings"></span>
						<span class="sh-PageHeader-settingsLinkText"><?php esc_html_e( 'Settings & Tools', 'simple-history' ); ?></span>
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
