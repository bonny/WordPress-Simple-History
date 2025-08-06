<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Loggers\Core_Files_Integrity_Logger;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;

/**
 * Debug and manage WordPress core files integrity checking.
 */
class WP_CLI_Core_Integrity_Command extends WP_CLI_Command {

	/**
	 * Simple_History instance.
	 *
	 * @var Simple_History
	 */
	private $simple_history;

	/**
	 * Option name where integrity results are stored.
	 *
	 * @var string
	 */
	private $option_name = 'simple_history_core_files_integrity_results';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Check WordPress core files integrity without logging results.
	 *
	 * This command performs the same integrity check as the logger but outputs
	 * results to the CLI instead of logging them. Useful for debugging.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Check core files integrity
	 *     $ wp simple-history core-integrity check
	 *
	 *     # Get results as JSON
	 *     $ wp simple-history core-integrity check --format=json
	 *
	 * @subcommand check
	 */
	public function check( $args, $assoc_args ) {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			WP_CLI::error( 'Core Files Integrity Logger requires experimental features to be enabled.' );
			return;
		}

		WP_CLI::log( 'Checking WordPress core files integrity...' );

		// Start timing.
		$start_time = microtime( true );

		global $wp_version;

		// Get official WordPress checksums for current version.
		$checksums = get_core_checksums( $wp_version, 'en_US' );

		if ( ! is_array( $checksums ) || empty( $checksums ) ) {
			WP_CLI::error( 'Unable to retrieve WordPress core checksums for version ' . $wp_version );
			return;
		}

		$modified_files = [];
		$wp_root = ABSPATH;
		$total_files_checked = 0;

		// Check each file in the checksums array.
		foreach ( $checksums as $file => $expected_hash ) {
			// Skip files which get updated.
			if ( str_starts_with( $file, 'wp-content' ) ) {
				continue;
			}

			$total_files_checked++;
			$file_path = $wp_root . $file;

			// Check if file doesn't exist (missing core files should be logged).
			if ( ! file_exists( $file_path ) ) {
				$modified_files[] = [
					'file' => $file,
					'issue' => 'missing',
					'expected_hash' => $expected_hash,
					'actual_hash' => null,
				];
				continue;
			}

			// Calculate actual file hash.
			$actual_hash = md5_file( $file_path );

			if ( $actual_hash === false ) {
				// File exists but can't be read.
				$modified_files[] = [
					'file' => $file,
					'issue' => 'unreadable',
					'expected_hash' => $expected_hash,
					'actual_hash' => null,
				];
				continue;
			}

			// Compare hashes.
			if ( $actual_hash !== $expected_hash ) {
				$modified_files[] = [
					'file' => $file,
					'issue' => 'modified',
					'expected_hash' => $expected_hash,
					'actual_hash' => $actual_hash,
				];
			}
		}

		// End timing.
		$end_time = microtime( true );
		$execution_time = round( $end_time - $start_time, 2 );

		WP_CLI::log( sprintf( 'Checked %d core files in %s seconds.', $total_files_checked, $execution_time ) );

		if ( empty( $modified_files ) ) {
			WP_CLI::success( 'All WordPress core files are intact.' );
			return;
		}

		WP_CLI::warning( sprintf( 'Found %d modified core files.', count( $modified_files ) ) );

		// Format output.
		$format = $assoc_args['format'] ?? 'table';

		if ( 'count' === $format ) {
			WP_CLI::log( count( $modified_files ) );
			return;
		}

		// Prepare data for table format.
		$formatted_files = array_map( function( $file_data ) {
			return [
				'file' => $file_data['file'],
				'issue' => $file_data['issue'],
				'expected' => $file_data['expected_hash'],
				'actual' => $file_data['actual_hash'] ?? 'N/A',
			];
		}, $modified_files );

		Utils\format_items( $format, $formatted_files, [ 'file', 'issue', 'expected', 'actual' ] );
	}

	/**
	 * List stored modified core files from the database option.
	 *
	 * This command shows what modified files are currently stored in the
	 * database from previous integrity checks.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List stored modified files
	 *     $ wp simple-history core-integrity list-stored
	 *
	 *     # Get count of stored modified files
	 *     $ wp simple-history core-integrity list-stored --format=count
	 *
	 * @subcommand list-stored
	 */
	public function list_stored( $args, $assoc_args ) {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			WP_CLI::error( 'Core Files Integrity Logger requires experimental features to be enabled.' );
			return;
		}

		$stored_results = get_option( $this->option_name, [] );

		if ( empty( $stored_results ) ) {
			WP_CLI::success( 'No modified core files are currently stored in the database.' );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';

		if ( 'count' === $format ) {
			WP_CLI::log( count( $stored_results ) );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d stored modified core files:', count( $stored_results ) ) );

		// Convert stored results to array format.
		$formatted_files = [];
		foreach ( $stored_results as $file => $file_data ) {
			$formatted_files[] = [
				'file' => $file,
				'issue' => $file_data['issue'] ?? 'unknown',
				'expected' => $file_data['expected_hash'] ?? 'N/A',
				'actual' => $file_data['actual_hash'] ?? 'N/A',
			];
		}

		Utils\format_items( $format, $formatted_files, [ 'file', 'issue', 'expected', 'actual' ] );
	}
}