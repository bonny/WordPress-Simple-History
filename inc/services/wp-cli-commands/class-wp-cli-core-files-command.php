<?php

namespace Simple_History\Services\WP_CLI_Commands;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;
use Simple_History\Loggers\Core_Files_Logger;

/**
 * Debug and manage WordPress core files checking.
 */
class WP_CLI_Core_Files_Command extends WP_CLI_Command {

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
	 *     # Check core files
	 *     $ wp simple-history core-files check
	 *
	 *     # Get results as JSON
	 *     $ wp simple-history core-files check --format=json
	 *
	 * @subcommand check
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function check( $args, $assoc_args ) {
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

		$modified_files      = [];
		$wp_root             = ABSPATH;
		$total_files_checked = 0;

		// Check each file in the checksums array.
		foreach ( $checksums as $file => $expected_hash ) {
			// Skip files which get updated.
			if ( str_starts_with( $file, 'wp-content' ) ) {
				continue;
			}

			++$total_files_checked;
			$file_path = $wp_root . $file;

			// Check if file doesn't exist (missing core files should be logged).
			if ( ! file_exists( $file_path ) ) {
				$modified_files[] = [
					'file'          => $file,
					'issue'         => 'missing',
					'expected_hash' => $expected_hash,
					'actual_hash'   => null,
				];
				continue;
			}

			// Calculate actual file hash.
			$actual_hash = md5_file( $file_path );

			if ( $actual_hash === false ) {
				// File exists but can't be read.
				$modified_files[] = [
					'file'          => $file,
					'issue'         => 'unreadable',
					'expected_hash' => $expected_hash,
					'actual_hash'   => null,
				];
				continue;
			}

			// Compare hashes.
			if ( $actual_hash !== $expected_hash ) {
				$modified_files[] = [
					'file'          => $file,
					'issue'         => 'modified',
					'expected_hash' => $expected_hash,
					'actual_hash'   => $actual_hash,
				];
			}
		}

		// End timing.
		$end_time       = microtime( true );
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
		$formatted_files = array_map(
			function ( $file_data ) {
				return [
					'file'     => $file_data['file'],
					'issue'    => $file_data['issue'],
					'expected' => $file_data['expected_hash'],
					'actual'   => $file_data['actual_hash'] ?? 'N/A',
				];
			},
			$modified_files
		);

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
	 *     $ wp simple-history core-files list-stored
	 *
	 *     # Get count of stored modified files
	 *     $ wp simple-history core-files list-stored --format=count
	 *
	 * @subcommand list-stored
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_stored( $args, $assoc_args ) {
		$stored_results = get_option( Core_Files_Logger::OPTION_NAME_FILE_CHECK_RESULTS, [] );

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
				'file'     => $file,
				'issue'    => $file_data['issue'] ?? 'unknown',
				'expected' => $file_data['expected_hash'] ?? 'N/A',
				'actual'   => $file_data['actual_hash'] ?? 'N/A',
			];
		}

		Utils\format_items( $format, $formatted_files, [ 'file', 'issue', 'expected', 'actual' ] );
	}

	/**
	 * Perform integrity check and log results to Simple History.
	 *
	 * This command performs the same check as the scheduled cron job,
	 * actually logging any new issues or resolved issues to Simple History.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run check and log results
	 *     $ wp simple-history core-files check-and-log
	 *
	 * @subcommand check-and-log
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function check_and_log( $args, $assoc_args ) {
		// Get the Core Files Integrity Logger instance by its slug.
		$core_logger = $this->simple_history->get_instantiated_logger_by_slug( 'CoreFilesLogger' );

		if ( ! $core_logger instanceof Core_Files_Logger ) {
			WP_CLI::error( 'Core Files Integrity Logger is not loaded.' );
			return;
		}

		WP_CLI::log( 'Performing core files integrity check and logging results...' );

		// Start timing.
		$start_time = microtime( true );

		// Perform the actual integrity check using the logger's method.
		$core_logger->perform_integrity_check();

		// End timing.
		$end_time       = microtime( true );
		$execution_time = round( $end_time - $start_time, 2 );

		WP_CLI::log( sprintf( 'Integrity check completed in %s seconds.', $execution_time ) );

		// Get the current stored results to show what was found/logged.
		$stored_results = get_option( Core_Files_Logger::OPTION_NAME_FILE_CHECK_RESULTS, [] );

		if ( empty( $stored_results ) ) {
			WP_CLI::success( 'No modified core files detected. Simple History log updated if there were previous issues that are now resolved.' );
		} else {
			WP_CLI::warning( sprintf( 'Found %d modified core files. Check Simple History log for details.', count( $stored_results ) ) );

			// Show a summary of what was found.
			$issues_by_type = [];
			foreach ( $stored_results as $file => $file_data ) {
				$issue = $file_data['issue'] ?? 'unknown';
				if ( ! isset( $issues_by_type[ $issue ] ) ) {
					$issues_by_type[ $issue ] = 0;
				}
				++$issues_by_type[ $issue ];
			}

			WP_CLI::log( 'Summary by issue type:' );
			foreach ( $issues_by_type as $issue => $count ) {
				WP_CLI::log( sprintf( '  - %s: %d files', $issue, $count ) );
			}
		}

		WP_CLI::log( 'Check your Simple History log for the full details of any new or resolved issues.' );
	}

	/**
	 * Debug cron scheduling for core files integrity checks.
	 *
	 * Shows information about the cron job status, scheduling, and configuration.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check cron debug information
	 *     $ wp simple-history core-files debug-cron
	 *
	 * @subcommand debug-cron
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function debug_cron( $args, $assoc_args ) {
		WP_CLI::log( '=== Core Files Integrity Cron Debug Information ===' );
		WP_CLI::log( '' );

		// Check if WordPress cron is disabled.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			WP_CLI::warning( 'WordPress cron is DISABLED (DISABLE_WP_CRON is set to true)' );
			WP_CLI::log( 'This means scheduled integrity checks will not run automatically.' );
			WP_CLI::log( 'You need to set up a system cron job to trigger wp-cron.php' );
		} else {
			WP_CLI::success( 'WordPress cron is enabled' );
		}
		WP_CLI::log( '' );

		// Get the cron hook name.
		$cron_hook = Core_Files_Logger::CRON_HOOK;

		// Check if the logger is loaded.
		$core_logger = $this->simple_history->get_instantiated_logger_by_slug( 'CoreFilesLogger' );
		if ( $core_logger instanceof Core_Files_Logger ) {
			WP_CLI::success( 'Core Files Integrity Logger is loaded' );
		} else {
			WP_CLI::error( 'Core Files Integrity Logger is NOT loaded!' );
			return;
		}
		WP_CLI::log( '' );

		// Check if the cron event is scheduled.
		$next_scheduled = wp_next_scheduled( $cron_hook );

		if ( $next_scheduled ) {
			WP_CLI::success( 'Integrity check cron job is scheduled' );
			WP_CLI::log( 'Hook name: ' . $cron_hook );
			WP_CLI::log( 'Next run: ' . gmdate( 'Y-m-d H:i:s', $next_scheduled ) . ' (' . human_time_diff( time(), $next_scheduled ) . ' from now)' );
			WP_CLI::log( 'Schedule: daily' );
		} else {
			WP_CLI::warning( 'Integrity check cron job is NOT scheduled!' );
			WP_CLI::log( 'Expected hook name: ' . $cron_hook );
			WP_CLI::log( 'This means automatic integrity checks will not run.' );
		}
		WP_CLI::log( '' );

		// Show all Simple History related cron events.
		WP_CLI::log( '=== All Simple History Cron Events ===' );
		$crons          = _get_cron_array();
		$found_sh_crons = false;

		foreach ( $crons as $timestamp => $cron ) {
			foreach ( $cron as $hook => $dings ) {
				if ( strpos( $hook, 'simple_history' ) !== false ) {
					$found_sh_crons = true;
					foreach ( $dings as $sig => $data ) {
						WP_CLI::log(
							sprintf(
								'- %s: %s (%s from now)',
								$hook,
								gmdate( 'Y-m-d H:i:s', $timestamp ),
								human_time_diff( time(), $timestamp )
							)
						);
						if ( ! empty( $data['schedule'] ) ) {
							WP_CLI::log( '  Schedule: ' . $data['schedule'] );
						}
					}
				}
			}
		}

		if ( ! $found_sh_crons ) {
			WP_CLI::log( 'No Simple History cron events found.' );
		}
		WP_CLI::log( '' );

		// Check last run information (if we can deduce it).
		$stored_results = get_option( Core_Files_Logger::OPTION_NAME_FILE_CHECK_RESULTS, [] );
		if ( ! empty( $stored_results ) ) {
			WP_CLI::log( '=== Stored Results ===' );
			WP_CLI::log( sprintf( 'Currently storing %d modified files in the database option.', count( $stored_results ) ) );
			WP_CLI::log( 'This suggests the integrity check has run at least once.' );
		} else {
			WP_CLI::log( '=== Stored Results ===' );
			WP_CLI::log( 'No modified files stored in the database option.' );
			WP_CLI::log( 'This could mean:' );
			WP_CLI::log( '- No issues have been detected' );
			WP_CLI::log( '- The integrity check has never run' );
		}
		WP_CLI::log( '' );

		// WordPress and locale information.
		global $wp_version;
		WP_CLI::log( '=== WordPress Information ===' );
		WP_CLI::log( 'WordPress version: ' . $wp_version );
		WP_CLI::log( 'Site locale: ' . get_locale() );
		WP_CLI::log( 'Checksum locale used: en_US (hardcoded)' );

		// Test if checksums can be retrieved.
		WP_CLI::log( '' );
		WP_CLI::log( 'Testing checksum retrieval...' );
		$checksums = get_core_checksums( $wp_version, 'en_US' );
		if ( is_array( $checksums ) && ! empty( $checksums ) ) {
			WP_CLI::success( sprintf( 'Successfully retrieved checksums for %d core files', count( $checksums ) ) );
		} else {
			WP_CLI::error( 'Failed to retrieve core checksums!' );
		}
	}
}
