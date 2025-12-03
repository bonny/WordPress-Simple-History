<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Dropin Name: Premium promotion
 * Dropin Description: Promote premium version on Installed Plugins listing screen and Simple History settings screen.
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */

/**
 * Simple History Premium Promotion dropin
 * Promote premium version in various places
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
	 * @param string|bool $text Admin footer text. Can be false apparently because it was once.
	 * @return string
	 */
	public function filter_admin_footer_text( $text ) {
		if ( Helpers::is_on_our_own_pages() === false ) {
			return $text;
		}

		if ( Helpers::get_current_screen()->base === 'dashboard' ) {
			return $text;
		}

		if ( is_bool( $text ) ) {
			$text = '';
		}

		// Bail if not string because have no idea what's going on then.
		if ( ! is_string( $text ) ) {
			return $text;
		}

		// Add divider if text is not empty.
		$divider = '';
		if ( ! empty( $text ) ) {
			$divider = ' | ';
		}

		$text .= $divider . sprintf(
			/* translators: 1 is a link to the WordPress.org plugin review page for Simple History. */
			__( 'Consider giving Simple History <a href="%1$s" target="_blank">a nice review at WordPress.org</a> if you find it useful.', 'simple-history' ),
			'https://wordpress.org/support/plugin/simple-history/reviews/?filter=5#new-post',
		);

		return $text;
	}

	/**
	 * Add link to premium version in the Plugins » Installed plugins screen.
	 *
	 * Called from filter 'plugin_row_meta'.
	 *
	 * @param array<string,string> $links with added links.
	 * @param string               $file plugin file.
	 * @return array<string,string> $links with added links
	 */
	public function action_plugin_row_meta( $links, $file ) {
		if ( $file === $this->simple_history->plugin_basename ) {
			// Only show premium link if promo boxes should be shown.
			if ( Helpers::show_promo_boxes() ) {
				$links[] = sprintf(
					'<a href="%1$s" target="_blank"><strong>%2$s</strong></a>',
					esc_url( Helpers::get_tracking_url( 'https://simple-history.com/premium/', 'premium_pluginspage_upgrade' ) ),
					__( '✨ Upgrade to Premium', 'simple-history' )
				);
			}
		}

		return $links;
	}

	/**
	 * Add settings section.
	 */
	public function add_settings() {
		// Only show premium promotion if promo boxes should be shown.
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		Helpers::add_settings_section(
			'simple_history_settings_section_donate',
			[ _x( 'Unlock more features', 'premium settings headline', 'simple-history' ), 'workspace_premium' ],
			array( $this, 'settings_section_output' ),
			Simple_History::SETTINGS_MENU_SLUG // same slug as for options menu page.
		);

		// Add a dummy settings field, required to make the after_section-html be output due to bug in do_settings_sections().
		add_settings_field(
			'simple_history_settings_field_donate',
			'',
			'__return_empty_string',
			Simple_History::SETTINGS_MENU_SLUG,
			'simple_history_settings_section_donate'
		);
	}

	/**
	 * Output settings field HTML.
	 */
	public function settings_field_output() {
		echo '';
	}

	/**
	 * Output settings section HTML.
	 */
	public function settings_section_output() {
		echo '<p>';
		printf(
			wp_kses(
				// translators: %s is a link to the premium version.
				__(
					'Love Simple History? ✨ <a href="%s" target="_blank" class="sh-ExternalLink"><strong>Upgrade to Premium</strong></a> and unlock Sticky Events, Custom Manual Entries, Detailed Stats & Summaries, Stealth Mode, Export to CSV/JSON, and more!',
					'simple-history'
				),
				array(
					'a'      => array(
						'href'   => array(),
						'class'  => [],
						'target' => [],
					),
					'strong' => array(),
				)
			),
			esc_url( Helpers::get_tracking_url( 'https://simple-history.com/premium/', 'premium_settings_upgrade' ) )
		);
		echo '</p>';
	}
}
