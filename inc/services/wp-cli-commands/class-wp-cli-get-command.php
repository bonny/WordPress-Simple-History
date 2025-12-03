<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Log_Initiators;
use Simple_History\Log_Query;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP CLI command that display a single event.
 */
class WP_CLI_Get_Command extends WP_CLI_Command {
	/**
	 * Display a single event.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID of event to display.
	 *
	 * [--format=<format>]
	 * : Format of output. Defaults to table. Options: table, json, csv, yaml.
	 *
	 * ## Examples
	 *
	 *     wp simple-history event get 123
	 *     wp simple-history event get 123 --format=json
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function get( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'format' => 'table',
			)
		);

		$event_id = $args[0];

		if ( ! is_numeric( $event_id ) ) {
			WP_CLI::error( 'Event ID must be an integer.' );
		}

		// Override capability check: if you can run wp cli commands you can read all loggers.
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 0 );

		// Get information about event using the Simple History Log Query API.
		$query        = new Log_Query();
		$query_result = $query->query(
			array(
				'post__in' => [ $event_id ],
			)
		);

		// Handle database errors.
		if ( is_wp_error( $query_result ) ) {
			WP_CLI::error( $query_result->get_error_message() );
		}

		// Return early if no found events.
		if ( $query_result['total_row_count'] === 0 ) {
			WP_CLI::error(
				sprintf(
					/* translators: %d is the event ID */
					__( 'No event found with ID %1$d.', 'simple-history' ),
					$event_id
				)
			);
		}

		$event_row = reset( $query_result['log_rows'] );

		// Get message interpolated.
		$simple_history = Simple_History::get_instance();

		$event_logger = $simple_history->get_instantiated_logger_by_slug( $event_row->logger );

		$text_output = html_entity_decode( $simple_history->get_log_row_plain_text_output( $event_row ) );

		$text_output = $simple_history->get_log_row_plain_text_output( $event_row );
		$text_output = wp_strip_all_tags( html_entity_decode( $text_output, ENT_QUOTES, 'UTF-8' ) );

		$initiator = Log_Initiators::get_initiator_text_from_row( $event_row );

		$output_array = [
			'ID'           => $event_row->id,
			'date'         => $event_row->date,
			'initiator'    => $initiator,
			'message'      => $text_output,
			'via'          => $event_logger ? $event_logger->get_info_value_by_key( 'name_via' ) : '',
			'logger'       => $event_row->logger,
			'level'        => $event_row->level,
			'count'        => $event_row->subsequentOccasions, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			'_message_key' => $event_row->context_message_key,
			'_message'     => $event_row->message,
			'_initiator'   => $event_row->initiator,
		];

		// Fields to display.
		$fields = [
			'ID',
			'date',
			'initiator',
			'message',
			'via',
			'logger',
			'level',
			'count',
			'_message_key',
			'_message',
			'_initiator',
		];

		// Store format in variable because \WP_CLI\Formatter seems to modify it so it's gone
		// if we check it later.
		$format = $assoc_args['format'];

		// Append context. For table format = prepend each key with "context_"
		// and for JSON just add it all to a "context" key.
		if ( 'table' === $format ) {
			foreach ( $event_row->context as $key => $value ) {
				$prefixed_key                  = 'context_' . $key;
				$output_array[ $prefixed_key ] = $value;
				$fields[]                      = $prefixed_key;
			}
		} elseif ( 'json' === $format ) {
			$message_details      = $simple_history->get_log_row_details_output( $event_row );
			$message_details_json = $message_details->to_json();

			$output_array['context']       = $event_row->context;
			$output_array['event_details'] = $message_details_json;

			$fields[] = 'context';
			$fields[] = 'event_details';
		}

		$formatter = new \WP_CLI\Formatter(
			$assoc_args,
			$fields,
		);

		$formatter->display_item( $output_array );
	}
}
