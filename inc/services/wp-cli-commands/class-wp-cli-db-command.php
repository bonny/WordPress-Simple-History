<?php

namespace Simple_History\Services;

use WP_CLI;

use Simple_History\Helpers;
use WP_CLI_Command;

/**
 * Clear all logged items using method clear_log().
 * This is a destructive action and cannot be undone.
 * WP-CLI will ask for confirmation before proceeding using function WP_CLI::confirm().
 *
 * @since 4.0.2
 */
class WP_CLI_Db_Command extends WP_CLI_Command {
	/**
	 * Clear all logged items from the database.
	 *
	 * This is a destructive action and cannot be undone.
	 *
	 * WP-CLI will ask for confirmation before proceeding.
	 *
	 * ## Examples
	 *
	 *     wp simple-history db clear
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function clear( $args, $assoc_args ) {
		// Script exits if user does not confirm.
		WP_CLI::confirm(
			__( 'Are you sure you want to clear all logged items?', 'simple-history' ),
			$assoc_args
		);

		$num_rows_deleted = Helpers::clear_log();

		WP_CLI::success(
			sprintf(
				/* translators: %d: number of rows deleted */
				__( 'Removed %1$d rows.', 'simple-history' ),
				$num_rows_deleted
			)
		);
	}

	/**
	 * Show information about number of logged items and database size.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Format of output. Defaults to table. Options: table, json, csv, yaml.
	 *
	 * ## Examples
	 *
	 *     wp simple-history db stats
	 *     wp simple-history db stats --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function stats( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'format' => 'table',
			)
		);

		$table_size_result = Helpers::get_db_table_stats();

		WP_CLI\Utils\format_items(
			$assoc_args['format'],
			$table_size_result,
			array(
				'table_name',
				'size_in_mb',
				'num_rows',
			)
		);
	}
}
