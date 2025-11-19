<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Simple_History;
use Simple_History\Loggers\Custom_Entry_Logger;
use Simple_History\Log_Levels;
use WP_CLI;
use WP_CLI_Command;

/**
 * Add events to Simple History.
 */
class WP_CLI_Add_Command extends WP_CLI_Command {
	/**
	 * Add a new event to the log.
	 *
	 * ## OPTIONS
	 *
	 * <message>
	 * : The message to log.
	 *
	 * [--note=<note>]
	 * : Additional note or details about the event.
	 *
	 * [--level=<level>]
	 * : Log level. One of: emergency, alert, critical, error, warning, notice, info, debug.
	 * ---
	 * default: info
	 * options:
	 *   - emergency
	 *   - alert
	 *   - critical
	 *   - error
	 *   - warning
	 *   - notice
	 *   - info
	 *   - debug
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Add a simple info message
	 *     $ wp simple-history event add "Deployed a new version of the website"
	 *
	 *     # Add a warning with a note
	 *     $ wp simple-history event add "Failed login attempt" --level=warning --note="IP: 192.168.1.1"
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function add( $args, $assoc_args ) {
		/** @var Simple_History $simple_history */
		$simple_history = Simple_History::get_instance();

		$message = $args[0];
		$note    = $assoc_args['note'] ?? '';
		$level   = $assoc_args['level'] ?? 'info';

		// Get the instantiated logger.
		$custom_entry_logger = $simple_history->get_instantiated_logger_by_slug( 'CustomEntryLogger' );

		$context = [
			'message' => $message,
		];

		if ( ! empty( $note ) ) {
			$context['note'] = $note;
		}

		if ( ! Log_Levels::is_valid_level( $level ) ) {
			WP_CLI::error( 'Invalid log level specified.' );
		}

		$method = $level . '_message';

		$custom_entry_logger->$method( 'custom_entry_added', $context );

		WP_CLI::success( 'Event logged successfully.' );
	}
}
