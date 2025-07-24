<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logger to detect modifications to WordPress core files
 *
 * Checks core file integrity by comparing MD5 hashes against WordPress checksums
 * and logs any detected modifications for security monitoring.
 */
class Core_Files_Integrity_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'CoreFilesIntegrityLogger';

	/** @var string Option name to store previous check results */
	private $option_name = 'simple_history_core_files_integrity_results';

	/** @var string Cron hook name */
	private $cron_hook = 'simple_history/core_files_integrity_check';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => __( 'Core Files Integrity Logger', 'simple-history' ),
			'description' => __( 'Detects modifications to WordPress core files by checking file integrity against official checksums', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => [
				'core_files_modified_detected' => __( 'WordPress core file modifications detected: {file_count} files modified', 'simple-history' ),
				'core_files_integrity_restored' => __( 'WordPress core file integrity restored: {file_count} files fixed', 'simple-history' ),
				'core_files_check_failed' => __( 'WordPress core files integrity check failed: {error_message}', 'simple-history' ),
			],
			'labels'      => [
				'search' => [
					'label' => _x( 'Core Files Security', 'Core Files Logger: search', 'simple-history' ),
					'options' => [
						_x( 'Core file modifications', 'Core Files Logger: search', 'simple-history' ) => [
							'core_files_modified_detected',
							'core_files_integrity_restored',
							'core_files_check_failed',
						],
					],
				],
			],
		];
	}

	/**
	 * Called when logger is loaded
	 */
	public function loaded() {
		// Only enable this logger if experimental features are enabled.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		// Set up cron job for daily integrity checks.
		add_action( 'init', [ $this, 'setup_cron' ] );

		// Handle the actual cron job.
		add_action( $this->cron_hook, [ $this, 'perform_integrity_check' ] );
	}

	/**
	 * Setup WordPress cron job for daily core files integrity checks
	 */
	public function setup_cron() {
		if ( ! wp_next_scheduled( $this->cron_hook ) ) {
			// Schedule daily check at 3 AM local time to minimize server impact.
			$timestamp = strtotime( 'tomorrow 3:00 AM' );
			wp_schedule_event( $timestamp, 'daily', $this->cron_hook );
		}
	}

	/**
	 * Perform core files integrity check
	 *
	 * This is the main method that gets called by the cron job
	 */
	public function perform_integrity_check() {
		try {
			$modified_files = $this->check_core_files_integrity();
			$this->process_check_results( $modified_files );
		} catch ( \Exception $e ) {
			$this->warning_message(
				'core_files_check_failed',
				[
					'error_message' => $e->getMessage(),
				]
			);
		}
	}

	   /**
		* Check WordPress core files integrity using official checksums.
		*
		* If modified files are found, they are returned like this:
		*
		* Array
		* (
		*     [0] => Array
		*         (
		*             [file] => xmlrpc.php
		*             [issue] => modified
		*             [expected_hash] => fb407463c202f1a8ab8783fa5b24ec13
		*             [actual_hash] => 57cb4f86b855614dd3e7d565b2f6f888
		*         )
		*
		*     [1] => Array
		*         (
		*             [file] => wp-settings.php
		*             [issue] => modified
		*             [expected_hash] => 0f52e2e688de1d2d776a12e55e5ca9c3
		*             [actual_hash] => 2e60a9b2c1daef9089a8fea7e0a691dd
		*         )
		* )
		*
		* @return array Array of modified files with their details.
		* @throws \Exception If checksums cannot be retrieved or check fails.
		*/
	private function check_core_files_integrity() {
		global $wp_version;

		// Get official WordPress checksums for current version.
		$checksums = get_core_checksums( $wp_version, 'en_US' );

		if ( ! is_array( $checksums ) || empty( $checksums ) ) {
			throw new \Exception( 'Unable to retrieve WordPress core checksums for version ' . esc_html( $wp_version ) );
		}

		$modified_files = [];
		$wp_root = ABSPATH;

		// Check each file in the checksums array.
		foreach ( $checksums as $file => $expected_hash ) {
			// Skip files which get updated.
			if ( str_starts_with( $file, 'wp-content' ) ) {
				continue;
			}

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

		return $modified_files;
	}

	/**
	 * Process check results and log changes appropriately
	 *
	 * @param array $modified_files Array of modified files from integrity check.
	 */
	private function process_check_results( $modified_files ) {
		$previous_results = get_option( $this->option_name, [] );
		$current_results = [];

		// Convert modified files to simple array for comparison.
		foreach ( $modified_files as $file_data ) {
			$current_results[ $file_data['file'] ] = $file_data;
		}

		// Check if this is a new issue or resolved issue.
		$new_issues = array_diff_key( $current_results, $previous_results );
		$resolved_issues = array_diff_key( $previous_results, $current_results );

		// Log new issues.
		if ( ! empty( $new_issues ) ) {
			$context = [
				'file_count' => count( $new_issues ),
				'modified_files' => array_keys( $new_issues ),
				'file_details' => array_values( $new_issues ),
			];

			$this->warning_message( 'core_files_modified_detected', $context );
		}

		// Log resolved issues.
		if ( ! empty( $resolved_issues ) && ! empty( $previous_results ) ) {
			$context = [
				'file_count' => count( $resolved_issues ),
				'restored_files' => array_keys( $resolved_issues ),
				'file_details' => array_values( $resolved_issues ),
			];

			$this->info_message( 'core_files_integrity_restored', $context );
		}

		// Update stored results.
		update_option( $this->option_name, $current_results );
	}

	/**
	 * Get output for log row details
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|null
	 */
	public function get_log_row_details_output( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'] ?? null;

		if ( ! $message_key ) {
			return null;
		}

		// Handle both detected and restored events.
		if ( ! in_array( $message_key, [ 'core_files_modified_detected', 'core_files_integrity_restored' ], true ) ) {
			return null;
		}

		if ( empty( $context['file_details'] ) ) {
			return null;
		}

		// Decode the JSON stored file_details.
		$file_details = json_decode( $context['file_details'] );
		if ( ! is_array( $file_details ) ) {
			return null;
		}

		$event_details_group = new Event_Details_Group();

		// Set appropriate title based on the event type.
		if ( 'core_files_integrity_restored' === $message_key ) {
			$event_details_group->set_title( __( 'Restored Core Files', 'simple-history' ) );
		} else {
			$event_details_group->set_title( __( 'Modified Core Files', 'simple-history' ) );
		}

		// Limit to first 5 files to keep log events manageable.
		$limited_file_details = array_slice( $file_details, 0, 5 );
		$total_files = count( $file_details );

		foreach ( $limited_file_details as $file_data ) {
			// Handle stdClass objects.
			$file = $file_data->file ?? '';
			$issue = $file_data->issue ?? '';

			if ( empty( $file ) || empty( $issue ) ) {
				continue;
			}

			// Determine the status text.
			if ( 'core_files_integrity_restored' === $message_key ) {
				// For restored files, show what was fixed.
				if ( 'modified' === $issue ) {
					$status_text = __( 'Hash mismatch fixed', 'simple-history' );
				} elseif ( 'unreadable' === $issue ) {
					$status_text = __( 'File readability restored', 'simple-history' );
				} elseif ( 'missing' === $issue ) {
					$status_text = __( 'Missing file restored', 'simple-history' );
				} else {
					/* translators: %s: issue type */
					$status_text = sprintf( __( '%s fixed', 'simple-history' ), esc_html( $issue ) );
				}
			} else if ( 'core_files_modified_detected' === $message_key ) {
				// For detected issues, show the current problem.
				if ( 'modified' === $issue ) {
					$status_text = __( 'Hash mismatch', 'simple-history' );
				} elseif ( 'unreadable' === $issue ) {
					$status_text = __( 'File unreadable', 'simple-history' );
				} elseif ( 'missing' === $issue ) {
					$status_text = __( 'File missing', 'simple-history' );
				} else {
					$status_text = esc_html( $issue );
				}
			}

			// Create an Event_Details_Item for each file without context key.
			$event_details_group->add_item(
				( new Event_Details_Item(
					null, // No context key needed.
					$file // Label (file name).
				) )->set_new_value( $status_text ) // Manually set the value.
			);
		}

		// Add summary if there are more files than displayed.
		if ( $total_files > 5 ) {
			$remaining_count = $total_files - 5;
			$event_details_group->add_item(
				( new Event_Details_Item(
					null,
					__( 'Additional files', 'simple-history' )
				) )->set_new_value(
					sprintf(
						/* translators: %d: number of additional files not shown */
						_n(
							'%d more file affected',
							'%d more files affected',
							$remaining_count,
							'simple-history'
						),
						$remaining_count
					)
				)
			);
		}

		return $event_details_group;
	}
}
