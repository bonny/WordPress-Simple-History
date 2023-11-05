<?php

namespace Simple_History\Dropins;

use WP_CLI;

/**
 * Dropin Name: WP CLI commands
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class WP_CLI_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		if ( defined( WP_CLI::class ) && WP_CLI ) {
			$this->register_commands();
		}
	}

	/**
	 * Register WP CLI commands.
	 */
	private function register_commands() {
		WP_CLI::add_command(
			'simple-history',
			__NAMESPACE__ . '\WP_CLI_Commands',
			array(
				'shortdesc' => __( 'List events from the Simple History log.', 'simple-history' ),
			)
		);
	}
}
