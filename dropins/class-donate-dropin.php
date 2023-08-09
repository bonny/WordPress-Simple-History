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
		add_action( 'admin_menu', array( $this, 'add_settings' ), 50 );
		add_action( 'plugin_row_meta', array( $this, 'action_plugin_row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ), 10, 1 );
	}


	/**
	 * Add donate link to the admin footer.
	 *
	 * Called from filter 'admin_footer_text'.
	 */
	public function filter_admin_footer_text( $text ) {
		if ( $this->simple_history->is_on_our_own_pages() === false ) {
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

	public function add_settings() {
		$settings_section_id = 'simple_history_settings_section_donate';

		add_settings_section(
			$settings_section_id,
			_x( 'Donate', 'donate settings headline', 'simple-history' ), // No title __("General", "simple-history"),
			array( $this, 'settings_section_output' ),
			Simple_History::SETTINGS_MENU_SLUG // same slug as for options menu page
		);
	}

	public function settings_section_output() {
		printf(
			wp_kses(
				// translators: 1 is a link to PayPal, 2 is a link to GitHub sponsors.
				__(
					'If you find Simple History useful please <a href="%1$s">donate using PayPal</a> or <a href="%2$s">become a GitHub sponsor</a>.',
					'simple-history'
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			),
			'https://www.paypal.me/eskapism',
			'https://github.com/sponsors/bonny',
		);
	}
}
