<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Event;
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
	 * [--network]
	 * : Operate on the network event log (requires Simple History Premium on a multisite network).
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event stick 123
	 *     wp simple-history event stick 123 --network
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function stick( $args, $assoc_args ) {
		$event_id = $this->parse_event_id( $args[0] );
		$event    = $this->resolve_event( $event_id, $assoc_args );

		if ( $event->is_sticky() ) {
			WP_CLI::warning( "Event $event_id is already sticky." );
			return;
		}

		if ( $event->stick() ) {
			WP_CLI::success( "Event $event_id marked as sticky." );
		} else {
			WP_CLI::error( "Failed to mark event $event_id as sticky." );
		}
	}

	/**
	 * Remove sticky status from an event.
	 *
	 * ## OPTIONS
	 *
	 * <event_id>
	 * : ID of the event to unstick.
	 *
	 * [--network]
	 * : Operate on the network event log (requires Simple History Premium on a multisite network).
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event unstick 123
	 *     wp simple-history event unstick 123 --network
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function unstick( $args, $assoc_args ) {
		$event_id = $this->parse_event_id( $args[0] );
		$event    = $this->resolve_event( $event_id, $assoc_args );

		if ( ! $event->is_sticky() ) {
			WP_CLI::warning( "Event $event_id is not sticky." );
			return;
		}

		if ( $event->unstick() ) {
			WP_CLI::success( "Event $event_id is no longer sticky." );
		} else {
			WP_CLI::error( "Failed to unstick event $event_id." );
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
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to display. Available fields: event_id, date, logger, level, message, initiator.
	 * Default: event_id,date,logger,message.
	 *
	 * [--network]
	 * : List sticky events from the network log (requires Simple History Premium on a multisite network).
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event list-sticky
	 *     wp simple-history event list-sticky --format=json
	 *     wp simple-history event list-sticky --fields=event_id,date,message
	 *     wp simple-history event list-sticky --network
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function list_sticky( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'format'  => 'table',
				'fields'  => 'id,date,logger,message',
				'network' => false,
			]
		);

		$is_network = WP_CLI_Network_Helper::is_network_mode( $assoc_args );
		$fields     = array_map( 'trim', explode( ',', $assoc_args['fields'] ) );

		// Network scope needs the contexts table to read sticky IDs from.
		// Route through get_log_query() for a single place where the
		// "--network without Premium" error is produced.
		if ( $is_network ) {
			WP_CLI_Network_Helper::get_log_query( $is_network );
			$tables           = Simple_History::get_instance()->get_network_tables();
			$sticky_event_ids = Helpers::get_sticky_event_ids( $tables['contexts'] );
		} else {
			$sticky_event_ids = Helpers::get_sticky_event_ids();
		}

		if ( empty( $sticky_event_ids ) ) {
			\WP_CLI\Utils\format_items( $assoc_args['format'], [], $fields );
			return;
		}

		$output = [];

		foreach ( $sticky_event_ids as $event_id ) {
			$event = $is_network
				? Simple_History::get_instance()->get_network_event( $event_id )
				: Event::get( $event_id );

			// Skip events that no longer exist, or that the network factory
			// failed to materialize. list-sticky is a read-only summary, so
			// silently skipping is preferable to erroring out for one
			// dangling ID.
			if ( ! $event || ! $event->exists() ) {
				continue;
			}

			$output[] = $event;
		}

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
	 * [--format=<format>]
	 * : Output format. table, json, csv, yaml. Default: table.
	 *
	 * [--network]
	 * : Check an event in the network log (requires Simple History Premium on a multisite network).
	 *
	 * ## EXAMPLES
	 *
	 *     wp simple-history event is-sticky 123
	 *     wp simple-history event is-sticky 123 --format=json
	 *     wp simple-history event is-sticky 123 --network
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function is_sticky( $args, $assoc_args ) {
		$event_id = $this->parse_event_id( $args[0] );
		$event    = $this->resolve_event( $event_id, $assoc_args );

		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'format' => 'table',
			]
		);

		$is_sticky = $event->is_sticky();
		$status    = $is_sticky ? 'sticky' : 'not sticky';

		if ( $assoc_args['format'] === 'table' ) {
			if ( $is_sticky ) {
				WP_CLI::success( "Event $event_id is sticky." );
			} else {
				WP_CLI::success( "Event $event_id is not sticky." );
			}

			return;
		}

		$output = [
			[
				'event_id'  => $event_id,
				'is_sticky' => $is_sticky,
				'status'    => $status,
			],
		];

		$fields = [ 'event_id', 'is_sticky', 'status' ];
		\WP_CLI\Utils\format_items( $assoc_args['format'], $output, $fields );
	}

	/**
	 * Validate the <event_id> positional, errors out if not an integer.
	 *
	 * @param mixed $raw Raw CLI positional value.
	 * @return int
	 */
	private function parse_event_id( $raw ): int {
		if ( ! is_numeric( $raw ) ) {
			WP_CLI::error( 'Invalid event ID.' );
		}

		return (int) $raw;
	}

	/**
	 * Resolve a single event for stick/unstick/is-sticky, honoring the
	 * --network flag. Thin adapter over the shared CLI helper so each
	 * method doesn't have to destructure the args twice.
	 *
	 * @param int   $event_id   Validated event ID.
	 * @param array $assoc_args Command associative args (reads the network flag).
	 * @return Event Event that is guaranteed to exist.
	 */
	private function resolve_event( int $event_id, array $assoc_args ): Event {
		$is_network = WP_CLI_Network_Helper::is_network_mode( $assoc_args );
		return WP_CLI_Network_Helper::get_event( $event_id, $is_network );
	}
}
