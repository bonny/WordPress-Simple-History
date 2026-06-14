<?php

namespace Simple_History\Services;

use WP_CLI;
use WP_CLI_Command;

/**
 * WP CLI command that searches the history.
 *
 * Deprecated: kept as a thin alias for `wp simple-history event list --search=<term>`,
 * which supports the same search plus many more filters.
 */
class WP_CLI_Search_Command extends WP_CLI_Command {
	/**
	 * Search the log. (Deprecated — use `wp simple-history event list --search=<term>` instead.)
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : Words to search for.
	 *
	 * [--older_than=<date>]
	 * : Only show events older than this date. Alias for --date_to on the list command.
	 *
	 * [--newer_than=<date>]
	 * : Only show events newer than this date. Alias for --date_from on the list command.
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
		WP_CLI::warning(
			'The "event search" command is deprecated and will be removed in a future version. Use "wp simple-history event list --search=<term>" instead. Note: output columns have changed (ID and via are now included).'
		);

		// Translate this command's arguments to their list command equivalents
		// and delegate, so only one search implementation exists.
		$list_assoc_args = [
			'search' => $args[0],
		];

		if ( ! empty( $assoc_args['newer_than'] ) ) {
			$list_assoc_args['date_from'] = $assoc_args['newer_than'];
		}

		if ( ! empty( $assoc_args['older_than'] ) ) {
			$list_assoc_args['date_to'] = $assoc_args['older_than'];
		}

		if ( ! empty( $assoc_args['count'] ) ) {
			$list_assoc_args['count'] = $assoc_args['count'];
		}

		if ( ! empty( $assoc_args['format'] ) ) {
			$list_assoc_args['format'] = $assoc_args['format'];
		}

		( new WP_CLI_List_Command() )->list( [], $list_assoc_args );
	}
}
