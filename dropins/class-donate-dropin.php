<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Dropin Name: Add donate links
 * Dropin Description: Add donate links to Installed Plugins listing screen and to Simple History settings screen.
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */

/**
 * Simple History Donate dropin
 * Put some donate messages here and there
 */
class Donate_Dropin extends Dropin {
	/** @inheritDoc */
	public function loaded() {
		// Prio 50 so it's added after the built in settings.
		add_action( 'admin_menu', array( $this, 'add_settings' ), 50 );
		add_filter( 'plugin_row_meta', array( $this, 'action_plugin_row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ), 10, 1 );
	}


	/**
	 * Add donate link to the admin footer.
	 *
	 * Called from filter 'admin_footer_text'.
	 *
	 * @param string $text Admin footer text.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		if ( Helpers::is_on_our_own_pages() === false ) {
			return $text;
		}

		if ( Helpers::get_current_screen()->base === 'dashboard' ) {
			return $text;
		}

		$text .= ' | ' . sprintf(
			/* translators: 1 is a link to the WordPress.org plugin review page for Simple History. */
			__( 'Consider giving Simple History <a href="%1$s" target="_blank">a nice review at WordPress.org</a> if you find it useful.', 'simple-history' ),
			'https://wordpress.org/support/plugin/simple-history/reviews/?filter=5#new-post',
		);

		return $text;
	}

	/**
	 * Add link to the donate page in the Plugins » Installed plugins screen.
	 *
	 * Called from filter 'plugin_row_meta'.
	 *
	 * @param array<string,string> $links with added links.
	 * @param string               $file plugin file.
	 * @return array<string,string> $links with added links
	 */
	public function action_plugin_row_meta( $links, $file ) {
		if ( $file == $this->simple_history->plugin_basename ) {
			$links[] = sprintf(
				'<a href="https://www.paypal.me/eskapism">%1$s</a>',
				__( 'Donate using PayPal', 'simple-history' )
			);

			$links[] = sprintf(
				'<a href="https://github.com/sponsors/bonny">%1$s</a>',
				__( 'Become a GitHub sponsor', 'simple-history' )
			);
		}

		return $links;
	}

	/**
	 * Add settings section.
	 */
	public function add_settings() {
		Helpers::add_settings_section(
			'simple_history_settings_section_donate',
			[ _x( 'Support development', 'donate settings headline', 'simple-history' ), 'volunteer_activism' ],
			array( $this, 'settings_section_output' ),
			Simple_History::SETTINGS_MENU_SLUG // same slug as for options menu page.
		);
	}

	/**
	 * Output settings section HTML.
	 */
	public function settings_section_output() {
		echo '<p>';
		printf(
			wp_kses(
				// translators: 1 is a link to PayPal, 2 is a link to GitHub sponsors.
				__(
					'If you find Simple History useful please <a href="%1$s" target="_blank" class="sh-ExternalLink">donate using PayPal</a> or <a href="%2$s" target="_blank" class="sh-ExternalLink">become a GitHub sponsor</a>.',
					'simple-history'
				),
				array(
					'a' => array(
						'href' => array(),
						'class' => [],
						'target' => [],
					),
				)
			),
			'https://www.paypal.me/eskapism',
			'https://github.com/sponsors/bonny',
		);
		echo '</p>';
	}
}
