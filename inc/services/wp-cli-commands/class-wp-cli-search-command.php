<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Log_Initiators;
use Simple_History\Services\WP_CLI_Commands\WP_CLI_Network_Helper;
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
	 * [--network]
	 * : Search the network log (requires Simple History Premium on a multisite network).
	 *
	 * ## Examples
	 *
	 *     wp simple-history search "activated plugin"
	 *     wp simple-history search "created user" --count=20
	 *     wp simple-history search "created site" --network
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
				'network'    => false,
			)
		);

		$search = $args[0];

		$query_args = array(
			'posts_per_page' => $assoc_args['count'],
			'search'         => $search,
			'ungrouped'      => true,
		);

		// Log_Query parses any non-null `date_from` / `date_to` string
		// through DateTimeImmutable — and `new DateTimeImmutable('')`
		// silently becomes "now", which filters out every past event.
		// Only pass the keys when the user actually supplied a date.
		if ( ! empty( $assoc_args['newer_than'] ) ) {
			$query_args['date_from'] = $assoc_args['newer_than'];
		}

		if ( ! empty( $assoc_args['older_than'] ) ) {
			$query_args['date_to'] = $assoc_args['older_than'];
		}

		$is_network = WP_CLI_Network_Helper::is_network_mode( $assoc_args );

		// Override capability check: if you can run wp cli commands you can read all loggers.
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 0 );

		$log_query = WP_CLI_Network_Helper::get_log_query( $is_network );

		$events = $log_query->query( $query_args );

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
