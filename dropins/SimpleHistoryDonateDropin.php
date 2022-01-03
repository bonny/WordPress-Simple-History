<?php

defined( 'ABSPATH' ) || die();

/*
Dropin Name: Donate things
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

/**
 * Simple History Donate dropin
 * Put some donate messages here and there
 */
class SimpleHistoryDonateDropin {
	private $simple_history;

	public function __construct( $simple_history ) {
		$this->simple_history = $simple_history;

		add_action( 'admin_menu', array( $this, 'add_settings' ), 50 );
		add_action( 'plugin_row_meta', array( $this, 'action_plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add link to the donate page in the Plugins » Installed plugins screen
	 * Called from filter 'plugin_row_meta'
	 */
	public function action_plugin_row_meta( $links, $file ) {

		if ( $file == $this->simple_history->plugin_basename ) {
			$links = array_merge(
				$links,
				array( sprintf( '<a href="https://www.paypal.me/eskapism">%1$s</a>', __( 'Donate', 'simple-history' ) ) )
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
			SimpleHistory::SETTINGS_MENU_SLUG // same slug as for options menu page
		);
	}

	public function settings_section_output() {

		printf(
			wp_kses(
				__( 'If you find Simple History useful please <a href="%1$s">donate</a>.', 'simple-history' ),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			),
			'https://www.paypal.me/eskapism'
		);
	}
}
