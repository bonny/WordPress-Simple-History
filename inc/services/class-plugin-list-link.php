<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

class Plugin_List_Link extends Service {
	public function loaded() {
		add_filter( 'plugin_action_links', array( $this, 'on_plugin_action_links' ), 10, 4 );
	}

	/**
	 * Add a link to the History Settings Page on the Plugins -> Installed Plugins screen.
	 *
	 * @param array $actions
	 */
	public function on_plugin_action_links( $actions, $plugin_file, $plugin_data, $context ) {
		if ( 'simple-history/index.php' !== $plugin_file ) {
			return $actions;
		}

		// Only add link if user has the right to view the settings page
		if ( ! current_user_can( $this->simple_history->get_view_settings_capability() ) ) {
			return $actions;
		}

		$settings_page_url = menu_page_url( $this->simple_history::SETTINGS_MENU_SLUG, false );

		if ( empty( $actions ) ) {
			// Create array if actions is empty (and therefore is assumed to be a string by PHP & results in PHP 7.1+ fatal error due to trying to make array modifications on what's assumed to be a string)
			$actions = [];
		} elseif ( is_string( $actions ) ) {
			// Convert the string (which it might've been retrieved as) to an array for future use as an array
			$actions = [ $actions ];
		}

		$actions[] = "<a href='$settings_page_url'>" . __( 'Settings', 'simple-history' ) . '</a>';

		return $actions;
	}
}
