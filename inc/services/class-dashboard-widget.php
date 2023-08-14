<?php

namespace Simple_History\Services;

class Dashboard_Widget extends Service {
	public function loaded() {
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
	}

	/**
	 * Add a dashboard widget.
	 * Requires current user to have view history capability
	 * and a setting to show dashboard to be set.
	 */
	public function add_dashboard_widget() {
		if ( $this->simple_history->setting_show_on_dashboard() && current_user_can( $this->simple_history->get_view_history_capability() ) ) {
			/**
			 * Filter to determine if history page should be added to page below dashboard or not
			 *
			 * @since 2.0.23
			 *
			 * @param bool $show_dashboard_widget Show the page or not
			 */
			$show_dashboard_widget = apply_filters( 'simple_history/show_dashboard_widget', true );

			if ( $show_dashboard_widget ) {
				wp_add_dashboard_widget(
					'simple_history_dashboard_widget',
					__( 'Simple History', 'simple-history' ),
					array(
						$this,
						'dashboard_widget_output',
					)
				);
			}
		}
	}

	/**
	 * Output html for the dashboard widget
	 */
	public function dashboard_widget_output() {
		$pager_size = $this->simple_history->get_pager_size_dashboard();

		do_action( 'simple_history/dashboard/before_gui', $this );
		?>
		<div class="SimpleHistoryGui"
			 data-pager-size='<?php echo esc_attr( $pager_size ); ?>'
			 ></div>
		<?php
	}
}
