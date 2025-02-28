<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Simple_History;
use Simple_History\Loggers\Simple_Logger;
use WP_CLI;
use WP_CLI_Command;

/**
 * Add events to Simple History.
 */
class WP_CLI_Add_Command extends WP_CLI_Command {
	/**
	 * Simple History instance.
	 *
	 * @var Simple_History
	 */
	private $simple_history;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->simple_history = Simple_History::get_instance();
	}

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
	public function __invoke( $args, $assoc_args ) {
		$message = $args[0];
		$note = $assoc_args['note'] ?? '';
		$level = $assoc_args['level'] ?? 'info';

		$logger = new Simple_Logger( $this->simple_history );
		$context = array();

		if ( $note ) {
			$context['note'] = $note;
		}

		$method = $level;
		if ( method_exists( $logger, $method ) ) {
			$logger->$method( $message, $context );
			WP_CLI::success( 'Event logged successfully.' );
		} else {
			WP_CLI::error( 'Invalid log level specified.' );
		}
	}
} 