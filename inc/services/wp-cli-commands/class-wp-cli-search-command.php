<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Log_Initiators;
use WP_CLI;
use WP_CLI_Command;

/**
 * WP CLI command that search the history.
 */
class WP_CLI_Search_Command extends WP_CLI_Command {
	/**
	 * Search the log.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : Words to search for.
	 *
	 * [--older_than=<date>]
	 * : Only show events older than this date.
	 *
	 * [--newer_than=<date>]
	 * : Only show events newer than this date.
	 *
	 * [--format=<format>]
	 * : Format of output. Defaults to table. Options: table, json, csv, yaml.
	 *
	 * [--count=<number>]
	 * : Default 10.
	 *
	 * ## Examples
	 *
	 *     wp simple-history search "activated plugin"
	 *     wp simple-history search "created user" --count=20
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function search( $args, $assoc_args ) {
		/** @var Simple_History */
		$simple_history = Simple_History::get_instance();

		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'count'      => 10,
				'format'     => 'table',
				'older_than' => '',
				'newer_than' => '',
			)
		);

		$search = $args[0];

		$query_args = array(
			'posts_per_page' => $assoc_args['count'],
			'search'         => $search,
			'date_from'      => $assoc_args['newer_than'],
			'date_to'        => $assoc_args['older_than'],
		);

		// Override capability check: if you can run wp cli commands you can read all loggers.
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 0 );

		// Use Log_Query-class to query the log.
		$log_query = new \Simple_History\Log_Query();
		$events    = $log_query->query( $query_args );

		// A cleaned version of the events, formatted for wp cli table output.
		$events_cleaned = [];

		foreach ( $events['log_rows'] as $row ) {
			$header_output = $simple_history->get_log_row_header_output( $row );
			$text_output   = $simple_history->get_log_row_plain_text_output( $row );
			$header_output = wp_strip_all_tags( html_entity_decode( $header_output, ENT_QUOTES, 'UTF-8' ) );
			$header_output = trim( preg_replace( '/\s\s+/', ' ', $header_output ) );

			$text_output = wp_strip_all_tags( html_entity_decode( $text_output, ENT_QUOTES, 'UTF-8' ) );

			$events_cleaned[] = array(
				'date'        => get_date_from_gmt( $row->date ),
				'initiator'   => Log_Initiators::get_initiator_text_from_row( $row ),
				'logger'      => $row->logger,
				'level'       => $row->level,
				'who_when'    => $header_output,
				'description' => $text_output,
				'count'       => $row->subsequentOccasions, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			);
		}

		$fields = array(
			'date',
			'initiator',
			'description',
			'level',
			'count',
		);

		WP_CLI\Utils\format_items( $assoc_args['format'], $events_cleaned, $fields );
	}
}
