<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;

/**
 * Dropin Name: Sidebar with link to settings
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */
class Sidebar_Settings_Dropin extends Dropin {
	/**
	 * Init
	 */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', array( $this, 'on_sidebar_html' ), 7 );
	}

	/**
	 * Output HTML
	 */
	public function on_sidebar_html() {
		?>
		<div class="postbox">

			<h3 class="hndle"><?php esc_html_e( 'Settings', 'simple-history' ); ?></h3>

			<div class="inside">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: 1: URL to settings page */
							__( '<a href="%1$s">Visit the settings page</a> to change things like the number of events to show and to get access to the RSS feed with all events, and more.', 'simple-history' ),
							array(
								'a' => array(
									'href' => array(),
								),
							)
						),
						esc_url( menu_page_url( Simple_History::SETTINGS_MENU_SLUG, false ) )
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
