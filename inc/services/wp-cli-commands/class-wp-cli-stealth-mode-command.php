<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Services\Stealth_Mode;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP CLI commands for managing Stealth Mode.
 */
class WP_CLI_Stealth_Mode_Command extends WP_CLI_Command {
	/**
	 * Get Stealth Mode status.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		/** @var Simple_History */
		$simple_history = Simple_History::get_instance();

		/** @var Stealth_Mode */
		$stealth_mode_service = $simple_history->get_service( Stealth_Mode::class );

		$full_stealth_mode_enabled    = $stealth_mode_service->is_full_stealth_mode_enabled();
		$partial_stealth_mode_enabled = $stealth_mode_service->is_stealth_mode_enabled();

		WP_CLI\Utils\format_items(
			'table',
			[
				[
					'mode'   => __( 'Full Stealth Mode', 'simple-history' ),
					'status' => $full_stealth_mode_enabled ? __( 'Enabled', 'simple-history' ) : __( 'Disabled', 'simple-history' ),
				],
				[
					'mode'   => __( 'Partial Stealth Mode', 'simple-history' ),
					'status' => $partial_stealth_mode_enabled ? __( 'Enabled', 'simple-history' ) : __( 'Disabled', 'simple-history' ),
				],
			],
			[ 'mode', 'status' ]
		);
	}
}
