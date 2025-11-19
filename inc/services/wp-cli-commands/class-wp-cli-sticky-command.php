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
	 * ## EXAMPLES
	 *
	 *     wp simple-history event stick 123
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function stick( $args, $assoc_args ) {
		$event_id = $args[0];

		if ( ! is_numeric( $event_id ) ) {
			WP_CLI::error( 'Invalid event ID.' );
		}

		$event_id = intval( $event_id );

		$event = Event::get( $event_id );

		if ( ! $event ) {
			WP_CLI::error( "Event $event_id does not exist." );
		}

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
	 * ## EXAMPLES
	 *
	 *     wp simple-history event unstick 123
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function unstick( $args, $assoc_args ) {
		$event_id = $args[0];
		if ( ! is_numeric( $event_id ) ) {
			WP_CLI::error( 'Invalid event ID.' );
		}
		$event_id = intval( $event_id );

		$event = Event::get( $event_id );

		if ( ! $event ) {
			WP_CLI::error( "Event $event_id does not exist." );
		}

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
	 * ## EXAMPLES
	 *
	 *     wp simple-history event list-sticky
	 *     wp simple-history event list-sticky --format=json
	 *     wp simple-history event list-sticky --fields=event_id,date,message
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function list_sticky( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'format' => 'table',
				'fields' => 'id,date,logger,message',
			]
		);

		$sticky_event_ids = Helpers::get_sticky_event_ids();

		$output = [];
		$fields = explode( ',', $assoc_args['fields'] );
		$fields = array_map( 'trim', $fields );

		if ( empty( $sticky_event_ids ) ) {
			// Return empty output - this is the WP-CLI way.
			\WP_CLI\Utils\format_items( $assoc_args['format'], [], $fields );
			return;
		}

		foreach ( $sticky_event_ids as $event_id ) {
			$event = Event::get( $event_id );

			if ( ! $event ) {
				// Skip events that no longer exist.
				continue;
			}

			$output[] = $event;
		}

		if ( empty( $output ) ) {
			// Return empty output - this is the WP-CLI way.
			\WP_CLI\Utils\format_items( $assoc_args['format'], [], $fields );
			return;
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
	 * ## EXAMPLES
	 *
	 *     wp simple-history event is-sticky 123
	 *     wp simple-history event is-sticky 123 --format=json
	 *
	 * @param array $args Command arguments.
	 * @param array $assoc_args Command options.
	 */
	public function is_sticky( $args, $assoc_args ) {
		$event_id = $args[0];

		if ( ! is_numeric( $event_id ) ) {
			WP_CLI::error( 'Invalid event ID.' );
		}
		$event_id = intval( $event_id );

		$event = Event::get( $event_id );

		if ( ! $event ) {
			WP_CLI::error( "Event $event_id does not exist." );
		}

		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'format' => 'table',
			]
		);

		$is_sticky = $event->is_sticky();
		$status    = $is_sticky ? 'sticky' : 'not sticky';

		if ( 'table' === $assoc_args['format'] ) {
			if ( $is_sticky ) {
				WP_CLI::success( "Event $event_id is sticky." );
			} else {
				WP_CLI::success( "Event $event_id is not sticky." );
			}
		} else {
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
	}
}
