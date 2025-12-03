<?php

namespace Simple_History\Services;

use WP_CLI;
use Simple_History\Helpers;
use Simple_History\Services\WP_CLI_Commands\WP_CLI_Add_Command;
use Simple_History\Services\WP_CLI_Commands\WP_CLI_Sticky_Command;
/**
 * Module that loads WP-CLI commands.
 */
class WP_CLI_Commands extends Service {
	/**
	 * Called when module is loaded.
	 */
	public function loaded() {
		if ( defined( WP_CLI::class ) && WP_CLI ) {
			$this->register_commands();
		}
	}

	/**
	 * Register WP-CLI commands.
	 */
	protected function register_commands() {
		// Backward compatibility alias for simple-history list.
		WP_CLI::add_command(
			'simple-history',
			WP_CLI_List_Command::class,
		);

		WP_CLI::add_command(
			'simple-history db',
			WP_CLI_Db_Command::class,
		);

		// Add command `wp event list`.
		WP_CLI::add_command(
			'simple-history event',
			WP_CLI_List_Command::class,
		);

		// Add command `wp event search`.
		WP_CLI::add_command(
			'simple-history event',
			WP_CLI_Search_Command::class,
		);

		// Add command `wp event get <id>`.
		WP_CLI::add_command(
			'simple-history event',
			WP_CLI_Get_Command::class,
		);

		// Add command `wp simple-history event add`.
		WP_CLI::add_command(
			'simple-history event',
			WP_CLI_Add_Command::class
		);

		// Add command `wp simple-history event sticky` commands (stick, unstick, list-sticky, is-sticky).
		WP_CLI::add_command(
			'simple-history event',
			WP_CLI_Sticky_Command::class
		);

		// Add command `wp stealth-mode status`.
		WP_CLI::add_command(
			'simple-history stealth-mode',
			WP_CLI_Stealth_Mode_Command::class,
		);

		// Add command `wp simple-history core-files` commands (check, list-stored).
		WP_CLI::add_command(
			'simple-history core-files',
			WP_CLI_Commands\WP_CLI_Core_Files_Command::class,
		);

		// Add command `wp simple-history dev` commands (reset).
		// Only available when SIMPLE_HISTORY_DEV constant is true.
		if ( Helpers::dev_mode_is_enabled() ) {
			WP_CLI::add_command(
				'simple-history dev',
				WP_CLI_Commands\WP_CLI_Dev_Command::class,
			);
		}
	}
}
