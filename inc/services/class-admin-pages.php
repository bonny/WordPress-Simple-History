<?php

namespace Simple_History\Services;

class Admin_Pages extends Service {
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
	}

	/**
	 * Add pages (history page and settings page)
	 */
	public function add_admin_pages() {
		// Add a history page as a sub-page below the Dashboard menu item
		if ( $this->simple_history->setting_show_as_page() ) {
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
					$this->simple_history->get_view_history_capability(),
					'simple_history_page',
					array( $this, 'history_page_output' )
				);
			}
		}
	}

	/**
	 * Output for page with the history
	 */
	public function history_page_output() {
		$pager_size = $this->simple_history->get_pager_size();

		/**
		 * Filter the pager size setting for the history page
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/page_pager_size', $pager_size );
		?>

		<div class="wrap SimpleHistoryWrap">

			<h1 class="SimpleHistoryPageHeadline">
				<div class="dashicons dashicons-backup SimpleHistoryPageHeadline__icon"></div>
				<?php echo esc_html_x( 'Simple History', 'history page headline', 'simple-history' ); ?>
			</h1>

			<?php
			/**
			 * Fires before the gui div
			 *
			 * @since 2.0
			 *
			 * @param Simple_History $instance This class.
			 */
			do_action( 'simple_history/history_page/before_gui', $this );
			?>

			<div class="SimpleHistoryGuiWrap">

				<div class="SimpleHistoryGui" data-pager-size='<?php echo esc_attr( $pager_size ); ?>'>
				</div>
				<?php
				/**
				 * Fires after the gui div
				 *
				 * @since 2.0
				 *
				 * @param Simple_History $instance This class.
				 */
				do_action( 'simple_history/history_page/after_gui', $this );
				?>
			</div>

		</div>
		<?php
	}
}
