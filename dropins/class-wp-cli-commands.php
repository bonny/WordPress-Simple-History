<?php

namespace Simple_History\Dropins;

use Simple_History\Log_Initiators;
use Simple_History\Simple_History;
use Simple_History\Log_Query;
use WP_CLI;

/**
 * WP CLI commands for Simple History.
 */
class WP_CLI_Commands {
	/** @var Simple_History */
	private $simple_history;

	/**
	 * Constructor.
	 */
	public function __construct() {
		 $this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Display the latest events from the history.
	 *
	 * ## Options
	 *
	 * [--format=<format>]
	 * : Format to output log in.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *
	 * [--count=<count>]
	 * : How many events to show.
	 * ---
	 * default: 10
	 *
	 * ## Examples
	 *
	 *     wp simple-history list --count=20 --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function list( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'format' => 'table',
				'count' => 10,
			)
		);

		if ( ! is_numeric( $assoc_args['count'] ) ) {
			WP_CLI::error( __( 'Error: parameter "count" must be a number', 'simple-history' ) );
		}

		// Override capability check: if you can run wp cli commands you can read all loggers.
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 0 );

		$query = new Log_Query();

		$query_args = array(
			'posts_per_page' => $assoc_args['count'],
		);

		$events = $query->query( $query_args );

		// A cleaned version of the events, formatted for wp cli table output.
		$eventsCleaned = array();

		foreach ( $events['log_rows'] as $row ) {
			$header_output = $this->simple_history->get_log_row_header_output( $row );
			$header_output = strip_tags( html_entity_decode( $header_output, ENT_QUOTES, 'UTF-8' ) );
			$header_output = trim( preg_replace( '/\s\s+/', ' ', $header_output ) );

			$text_output = $this->simple_history->get_log_row_plain_text_output( $row );
			$text_output = strip_tags( html_entity_decode( $text_output, ENT_QUOTES, 'UTF-8' ) );

			$row_logger = $this->simple_history->get_instantiated_logger_by_slug( $row->logger );

			$eventsCleaned[] = array(
				'ID' => $row->id,
				'date' => get_date_from_gmt( $row->date ),
				'initiator' => Log_Initiators::get_initiator_text_from_row( $row ),
				'logger' => $row->logger,
				'level' => $row->level,
				'who_when' => $header_output,
				'description' => $text_output,
				'via' => $row_logger ? $row_logger->get_info_value_by_key( 'name_via' ) : '',
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'count' => $row->subsequentOccasions,
			);
		}

		$fields = array(
			'ID',
			'date',
			'initiator',
			'description',
			'via',
			'level',
			'count',
		);

		WP_CLI\Utils\format_items( $assoc_args['format'], $eventsCleaned, $fields );
	}
}
