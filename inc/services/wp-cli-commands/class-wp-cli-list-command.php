<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Log_Initiators;
use Simple_History\Log_Levels;
use WP_CLI;
use WP_CLI_Command;
use Simple_History\Log_Query;

/**
 * Interact with the Simple History log via WP-CLI.
 */
class WP_CLI_List_Command extends WP_CLI_Command {

	/**
	 * Simple_History instance.
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
	 * Parse comma-separated values into array and validate.
	 *
	 * @param string $value Comma-separated string.
	 * @param array  $valid_values Array of valid values to check against.
	 * @param string $param_name Parameter name for error messages.
	 * @return array|false Array of valid values or false on error.
	 */
	private function parse_comma_separated_values( $value, $valid_values = array(), $param_name = '' ) {
		if ( empty( $value ) ) {
			return array();
		}

		$values = array_map( 'trim', explode( ',', $value ) );

		// If valid values are provided, validate each value.
		if ( ! empty( $valid_values ) ) {
			$invalid_values = array_diff( $values, $valid_values );
			if ( ! empty( $invalid_values ) ) {
				WP_CLI::error(
					sprintf(
						// translators: %1$s: parameter name, %2$s: invalid values, %3$s: valid values.
						__( 'Error: Invalid %1$s values: %2$s. Valid values are: %3$s', 'simple-history' ),
						$param_name,
						implode( ', ', $invalid_values ),
						implode( ', ', $valid_values )
					)
				);
				return false;
			}
		}

		return $values;
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
	 * [--initiator=<initiators>]
	 * : Filter by who/what initiated the event. Comma-separated list.
	 * ---
	 * options:
	 *   - wp_user
	 *   - web_user
	 *   - wp
	 *   - wp_cli
	 *   - other
	 *
	 * [--log_level=<levels>]
	 * : Filter by log levels. Comma-separated list.
	 * ---
	 * options:
	 *   - emergency
	 *   - alert
	 *   - critical
	 *   - error
	 *   - warning
	 *   - notice
	 *   - info
	 *   - debug
	 *
	 * [--logger=<loggers>]
	 * : Filter by specific loggers. Comma-separated list.
	 *
	 * [--message=<messages>]
	 * : Filter by specific message types in format "LoggerSlug:MessageKey". Comma-separated list.
	 *
	 * [--user=<users>]
	 * : Filter by user IDs. Comma-separated list.
	 *
	 * [--search=<term>]
	 * : Text search across message content, logger names, and context values.
	 *
	 * [--date_from=<date>]
	 * : Show events from this date onwards. Accepts Unix timestamp or Y-m-d H:i:s format.
	 *
	 * [--date_to=<date>]
	 * : Show events up to this date. Accepts Unix timestamp or Y-m-d H:i:s format.
	 *
	 * [--months=<months>]
	 * : Filter by months in Y-m format. Comma-separated list.
	 *
	 * [--include_sticky]
	 * : Include sticky events in results.
	 *
	 * [--only_sticky]
	 * : Show only sticky events.
	 *
	 * ## Surrounding Events
	 *
	 * Show events chronologically before and after a specific event. Useful for debugging
	 * to see the full context of what happened around a specific event. Requires administrator
	 * privileges and bypasses normal logger permission filters.
	 *
	 * [--surrounding_event_id=<id>]
	 * : Show events surrounding this event ID. Returns events before and after the specified event.
	 *
	 * [--surrounding_count=<count>]
	 * : Number of events to show before AND after the center event.
	 * ---
	 * default: 5
	 *
	 * ## Exclusion Filters
	 *
	 * These parameters exclude events matching the criteria. When both inclusion and exclusion
	 * filters are specified for the same field, exclusion takes precedence.
	 *
	 * [--exclude_search=<term>]
	 * : Exclude events containing this text.
	 *
	 * [--exclude_log_level=<levels>]
	 * : Exclude events with these log levels. Comma-separated list.
	 *
	 * [--exclude_logger=<loggers>]
	 * : Exclude events from these loggers. Comma-separated list.
	 *
	 * [--exclude_message=<messages>]
	 * : Exclude events with these message types in format "LoggerSlug:MessageKey". Comma-separated list.
	 *
	 * [--exclude_user=<users>]
	 * : Exclude events from these user IDs. Comma-separated list.
	 *
	 * [--exclude_initiator=<initiators>]
	 * : Exclude events from these initiators. Comma-separated list.
	 *
	 * ## Examples
	 *
	 *     # Basic usage
	 *     wp simple-history list --count=20 --format=json
	 *
	 *     # Filter by initiator and log level
	 *     wp simple-history list --initiator=wp_user,web_user --log_level=info,debug
	 *
	 *     # Search with date range
	 *     wp simple-history list --search="login failed" --date_from="2024-01-01"
	 *
	 *     # Filter by specific users and loggers
	 *     wp simple-history list --user=1,2,3 --logger=SimpleUserLogger,SimplePluginLogger
	 *
	 *     # Show only sticky events
	 *     wp simple-history list --only_sticky --format=json
	 *
	 *     # Exclude debug level events
	 *     wp simple-history list --exclude_log_level=debug --count=50
	 *
	 *     # Exclude events containing "cron"
	 *     wp simple-history list --exclude_search=cron --count=50
	 *
	 *     # Exclude WordPress-initiated events (cron jobs, automatic updates)
	 *     wp simple-history list --exclude_initiator=wp --count=50
	 *
	 *     # Combine positive and negative filters
	 *     wp simple-history list --log_level=info --exclude_search=cron --count=50
	 *
	 *     # Exclude multiple log levels and initiators
	 *     wp simple-history list --exclude_log_level=debug,info --exclude_initiator=wp,wp_cli --count=100
	 *
	 *     # Show surrounding events around event ID 123 (5 before + event + 5 after = 11 total)
	 *     wp simple-history list --surrounding_event_id=123
	 *
	 *     # Show 10 events before and after event ID 456
	 *     wp simple-history list --surrounding_event_id=456 --surrounding_count=10
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
				'format'               => 'table',
				'count'                => 10,
				'initiator'            => '',
				'log_level'            => '',
				'logger'               => '',
				'message'              => '',
				'user'                 => '',
				'search'               => '',
				'date_from'            => '',
				'date_to'              => '',
				'months'               => '',
				'include_sticky'       => false,
				'only_sticky'          => false,
				'exclude_search'       => '',
				'exclude_log_level'    => '',
				'exclude_logger'       => '',
				'exclude_message'      => '',
				'exclude_user'         => '',
				'exclude_initiator'    => '',
				'surrounding_event_id' => '',
				'surrounding_count'    => 5,
			)
		);

		if ( ! is_numeric( $assoc_args['count'] ) ) {
			WP_CLI::error( __( 'Error: parameter "count" must be a number', 'simple-history' ) );
		}

		// Validate and parse filter parameters.
		$initiators = $this->parse_comma_separated_values(
			$assoc_args['initiator'],
			Log_Initiators::get_valid_initiators(),
			'initiator'
		);

		$log_levels = $this->parse_comma_separated_values(
			$assoc_args['log_level'],
			Log_Levels::get_valid_log_levels(),
			'log_level'
		);

		$loggers  = $this->parse_comma_separated_values( $assoc_args['logger'] );
		$messages = $this->parse_comma_separated_values( $assoc_args['message'] );
		$users    = $this->parse_comma_separated_values( $assoc_args['user'] );
		$months   = $this->parse_comma_separated_values( $assoc_args['months'] );

		// Validate and parse exclusion filter parameters.
		$exclude_log_levels = $this->parse_comma_separated_values(
			$assoc_args['exclude_log_level'],
			Log_Levels::get_valid_log_levels(),
			'exclude_log_level'
		);

		$exclude_loggers  = $this->parse_comma_separated_values( $assoc_args['exclude_logger'] );
		$exclude_messages = $this->parse_comma_separated_values( $assoc_args['exclude_message'] );
		$exclude_users    = $this->parse_comma_separated_values( $assoc_args['exclude_user'] );

		$exclude_initiators = $this->parse_comma_separated_values(
			$assoc_args['exclude_initiator'],
			Log_Initiators::get_valid_initiators(),
			'exclude_initiator'
		);

		// Override capability check: if you can run wp cli commands you can read all loggers.
		add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true', 10, 0 );

		$query = new Log_Query();

		// Build query args with filters.
		$query_args = array(
			'posts_per_page' => $assoc_args['count'],
		);

		// Add filters to query args if provided.
		if ( ! empty( $initiators ) ) {
			$query_args['initiator'] = $initiators;
		}

		if ( ! empty( $log_levels ) ) {
			$query_args['loglevels'] = $log_levels;
		}

		if ( ! empty( $loggers ) ) {
			$query_args['loggers'] = $loggers;
		}

		if ( ! empty( $messages ) ) {
			$query_args['messages'] = $messages;
		}

		if ( ! empty( $users ) ) {
			// Convert to integers for user IDs.
			$user_ids = array_map( 'intval', array_filter( $users, 'is_numeric' ) );
			if ( ! empty( $user_ids ) ) {
				$query_args['users'] = $user_ids;
			}
		}

		if ( ! empty( $assoc_args['search'] ) ) {
			$query_args['search'] = $assoc_args['search'];
		}

		if ( ! empty( $assoc_args['date_from'] ) ) {
			$query_args['date_from'] = $assoc_args['date_from'];
		}

		if ( ! empty( $assoc_args['date_to'] ) ) {
			$query_args['date_to'] = $assoc_args['date_to'];
		}

		if ( ! empty( $months ) ) {
			$query_args['months'] = $months;
		}

		if ( ! empty( $assoc_args['include_sticky'] ) ) {
			$query_args['include_sticky'] = true;
		}

		if ( ! empty( $assoc_args['only_sticky'] ) ) {
			$query_args['only_sticky'] = true;
		}

		// Add exclusion filters to query args if provided.
		if ( ! empty( $assoc_args['exclude_search'] ) ) {
			$query_args['exclude_search'] = $assoc_args['exclude_search'];
		}

		if ( ! empty( $exclude_log_levels ) ) {
			$query_args['exclude_loglevels'] = $exclude_log_levels;
		}

		if ( ! empty( $exclude_loggers ) ) {
			$query_args['exclude_loggers'] = $exclude_loggers;
		}

		if ( ! empty( $exclude_messages ) ) {
			$query_args['exclude_messages'] = $exclude_messages;
		}

		if ( ! empty( $exclude_users ) ) {
			// Convert to integers for user IDs.
			$exclude_user_ids = array_map( 'intval', array_filter( $exclude_users, 'is_numeric' ) );
			if ( ! empty( $exclude_user_ids ) ) {
				$query_args['exclude_users'] = $exclude_user_ids;
			}
		}

		if ( ! empty( $exclude_initiators ) ) {
			$query_args['exclude_initiator'] = $exclude_initiators;
		}

		// Handle surrounding events query.
		// This is a special mode that bypasses normal filtering.
		$is_surrounding_query = false;
		$center_event_id      = null;

		if ( ! empty( $assoc_args['surrounding_event_id'] ) ) {
			$is_surrounding_query = true;
			$center_event_id      = (int) $assoc_args['surrounding_event_id'];

			// Surrounding events query uses its own parameters and ignores most others.
			$query_args = array(
				'surrounding_event_id' => $center_event_id,
				'surrounding_count'    => (int) $assoc_args['surrounding_count'],
			);
		}

		$events = $query->query( $query_args );

		// Handle database errors.
		if ( is_wp_error( $events ) ) {
			WP_CLI::error( $events->get_error_message() );
		}

		// Show summary for surrounding events query.
		if ( $is_surrounding_query ) {
			WP_CLI::log(
				sprintf(
					/* translators: 1: center event ID, 2: events before count, 3: events after count, 4: total events */
					__( 'Showing events surrounding event #%1$d (%2$d before, %3$d after, %4$d total)', 'simple-history' ),
					$center_event_id,
					$events['events_before'],
					$events['events_after'],
					$events['total_row_count']
				)
			);
			WP_CLI::log( '' );
		}

		// A cleaned version of the events, formatted for wp cli table output.
		$eventsCleaned = array();

		foreach ( $events['log_rows'] as $row ) {
			$header_output = $this->simple_history->get_log_row_header_output( $row );
			$header_output = wp_strip_all_tags( html_entity_decode( $header_output, ENT_QUOTES, 'UTF-8' ) );
			$header_output = trim( preg_replace( '/\s\s+/', ' ', $header_output ) );

			$text_output = $this->simple_history->get_log_row_plain_text_output( $row );
			$text_output = wp_strip_all_tags( html_entity_decode( $text_output, ENT_QUOTES, 'UTF-8' ) );

			$row_logger = $this->simple_history->get_instantiated_logger_by_slug( $row->logger );

			// Add marker for center event in surrounding events query.
			$id_display = $row->id;
			if ( $is_surrounding_query && (int) $row->id === $center_event_id ) {
				$id_display = '>>> ' . $row->id;
			}

			$eventsCleaned[] = array(
				'ID'          => $id_display,
				'date'        => get_date_from_gmt( $row->date ),
				'initiator'   => Log_Initiators::get_initiator_text_from_row( $row ),
				'logger'      => $row->logger,
				'level'       => $row->level,
				'who_when'    => $header_output,
				'description' => $text_output,
				'via'         => $row_logger ? $row_logger->get_info_value_by_key( 'name_via' ) : '',
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				'count'       => $row->subsequentOccasions,
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
