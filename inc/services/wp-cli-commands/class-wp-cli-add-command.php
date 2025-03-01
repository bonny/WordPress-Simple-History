<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Simple_History;
use Simple_History\Loggers\Manual_Events_Logger;
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
	 * : Log level. One of: debug, info, warning, error, emergency.
	 * ---
	 * default: info
	 * options:
	 *   - debug
	 *   - info
	 *   - warning
	 *   - error
	 *   - emergency
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Add a simple info message
	 *     $ wp simple-history event add "User profile updated"
	 *
	 *     # Add a warning with a note
	 *     $ wp simple-history event add "Failed login attempt" --level=warning --note="IP: 192.168.1.1"
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function add( $args, $assoc_args ) {

		/**
		 * Simple History instance.
		 *
		 * @var Simple_History
		 */
		$simple_history = Simple_History::get_instance();

		$message = $args[0];
		$note = $assoc_args['note'] ?? '';
		$level = $assoc_args['level'] ?? 'info';

		WP_CLI::debug( 'Message to log: ' . $message );
		WP_CLI::debug( 'Note: ' . $note );
		WP_CLI::debug( 'Log level: ' . $level );


		// Get the instantiated logger.
		// $event_logger = $simple_history->get_instantiated_logger_by_slug( $event_row->logger );
		$manual_events_logger = $simple_history->get_instantiated_logger_by_slug( 'ManualEventsLogger' );

		$context = [
			'message' => $message,
		];

		if ( ! empty( $note ) ) {
			$context['note'] = $note;
		}

		if ( ! Log_Levels::is_valid_level( $level ) ) {
			WP_CLI::error( 'Invalid log level specified.' );
		}

		WP_CLI::debug( 'Context array: ' . print_r( $context, true ) );

		$method = $level . '_message';

		WP_CLI::debug( 'Calling method: ' . $method );

		$manual_events_logger->$method( 'added_manual_event', array_merge( $context, array(
			'message' => $message,
		) ) );

		WP_CLI::debug( 'Logger method called successfully' );
		WP_CLI::success( 'Event logged successfully.' );
	}
}