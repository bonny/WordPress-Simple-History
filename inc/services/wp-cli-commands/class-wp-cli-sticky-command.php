<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Helpers;
use Simple_History\Simple_History;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP CLI commands for managing sticky events.
 */
class WP_CLI_Sticky_Command extends WP_CLI_Command {
	/**
	 * Mark an event as sticky.
	 *
	 * ## OPTIONS
	 *
	 * <event_id>
	 * : ID of the event to make sticky.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event stick 123
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function stick( $args, $assoc_args ) {
		global $wpdb;
		$event_id = $args[0];

		if ( ! is_numeric( $event_id ) ) {
			WP_CLI::error( 'Invalid event ID.' );
		}

		$event_id = intval( $event_id );

		// Bail if event does not exist.
		if ( ! Helpers::event_exists( $event_id ) ) {
			WP_CLI::error( "Event {$event_id} does not exist." );
		}

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		// Remove any existing sticky for this event.
		$wpdb->delete(
			$contexts_table,
			[
				'history_id' => $event_id,
				'key'        => '_sticky',
			]
		);

		// Add new sticky context.
		$value = Helpers::json_encode( new \stdClass() );
		$wpdb->insert(
			$contexts_table,
			[
				'history_id' => $event_id,
				'key'        => '_sticky',
				'value'      => $value,
			]
		);

		WP_CLI::success( "Event $event_id marked as sticky." );
	}

	/**
	 * Remove sticky status from an event.
	 *
	 * ## OPTIONS
	 *
	 * <event_id>
	 * : ID of the event to unstick.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event unstick 123
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function unstick( $args, $assoc_args ) {
		global $wpdb;
		$event_id = $args[0];
		if ( ! is_numeric( $event_id ) ) {
			WP_CLI::error( 'Invalid event ID.' );
		}
		$event_id = intval( $event_id );

		// Bail if event does not exist.
		if ( ! Helpers::event_exists( $event_id ) ) {
			WP_CLI::error( "Event {$event_id} does not exist." );
		}

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		$deleted = $wpdb->delete(
			$contexts_table,
			[
				'history_id' => $event_id,
				'key'        => '_sticky',
			]
		);

		if ( $deleted ) {
			WP_CLI::success( "Event $event_id is no longer sticky." );
		} else {
			WP_CLI::warning( "Event $event_id was not sticky or already removed." );
		}
	}

	/**
	 * List all sticky events.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. table, json, csv, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event list-sticky
	 *     wp simple-history event list-sticky --format=json
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function list_sticky( $args, $assoc_args ) {
		global $wpdb;

		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'format' => 'table',
			]
		);

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT history_id, value FROM %i WHERE `key` = %s',
				$contexts_table,
				'_sticky'
			)
		);

		$output = [];
		foreach ( $results as $row ) {
			$data = [
				'event_id' => $row->history_id,
			];
			$output[] = $data;
		}

		$fields = [ 'event_id' ];

		\WP_CLI\Utils\format_items( $assoc_args['format'], $output, $fields );
	}

	/**
	 * Check if an event is sticky.
	 *
	 * ## OPTIONS
	 *
	 * <event_id>
	 * : ID of the event to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event is-sticky 123
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function is_sticky( $args, $assoc_args ) {
		global $wpdb;
		$event_id = $args[0];

		if ( ! is_numeric( $event_id ) ) {
			WP_CLI::error( 'Invalid event ID.' );
		}
		$event_id = intval( $event_id );

		// Bail if event does not exist.
		if ( ! Helpers::event_exists( $event_id ) ) {
			WP_CLI::error( "Event {$event_id} does not exist." );
		}

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT value FROM %i WHERE history_id = %d AND `key` = %s',
				$contexts_table,
				$event_id,
				'_sticky'
			)
		);
		if ( $row ) {
			WP_CLI::success( "Event $event_id is sticky." );
		} else {
			WP_CLI::success( "Event $event_id is not sticky." );
		}
	}
}
