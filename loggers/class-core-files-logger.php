<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logger to detect modifications to WordPress core files
 *
 * Checks core file integrity by comparing MD5 hashes against WordPress checksums
 * and logs any detected modifications for security monitoring.
 */
class Core_Files_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'CoreFilesLogger';

	/** @var string Option name to store previous check results */
	const OPTION_NAME_FILE_CHECK_RESULTS = 'simple_history_core_files_integrity_results';

	/** @var string Cron hook name */
	const CRON_HOOK = 'simple_history/core_files_integrity_check';

	/**
	 * Locales whose checksums the last check accepted, the installed package locale first.
	 *
	 * @var string[]
	 */
	private $checksum_locales = [];

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => __( 'Core Files Logger', 'simple-history' ),
			'description' => __( 'Detects modifications to WordPress core files by checking file integrity against official checksums', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => [
				'core_files_modified'     => __( 'Detected modifications to {file_count} WordPress core files', 'simple-history' ),
				'core_files_restored'     => __( 'Verified integrity restored for {file_count} WordPress core files', 'simple-history' ),
				'core_files_check_failed' => __( 'Could not check WordPress core files integrity: {error_message}', 'simple-history' ),
			],
			'labels'      => [
				'search' => [
					'label'   => _x( 'Core Files Modifications', 'Core Files Logger: search', 'simple-history' ),
					'options' => [
						_x( 'Core file modifications', 'Core Files Logger: search', 'simple-history' ) => [
							'core_files_modified',
							'core_files_restored',
							'core_files_check_failed',
						],
					],
				],
			],
		];
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		// Set up cron job for daily integrity checks.
		add_action( 'init', [ $this, 'setup_cron' ] );

		// Handle the actual cron job.
		add_action( self::CRON_HOOK, [ $this, 'perform_integrity_check' ] );
	}

	/**
	 * Setup WordPress cron job for daily core files integrity checks.
	 */
	public function setup_cron() {
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// Schedule daily check at 3 AM site time to minimize server impact.
		$timezone  = wp_timezone();
		$datetime  = new \DateTime( 'tomorrow 3:00 AM', $timezone );
		$timestamp = $datetime->getTimestamp();
		wp_schedule_event( $timestamp, 'daily', self::CRON_HOOK );
	}

	/**
	 * Perform core files integrity check.
	 *
	 * This is the main method that gets called by the cron job
	 */
	public function perform_integrity_check() {
		$modified_files = $this->check_core_files_integrity();

		// Bail if error.
		if ( is_wp_error( $modified_files ) ) {
			return;
		}

		$this->process_check_results( $modified_files );
	}

	/**
	 * Check WordPress core files integrity using official checksums.
	 *
	 * @return array|\WP_Error Array of modified files (see run_integrity_check()) or WP_Error if there is an error.
	 */
	private function check_core_files_integrity() {
		$result = self::run_integrity_check();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->checksum_locales = $result['checksum_locales'];

		return $result['modified_files'];
	}

	/**
	 * Get the locales whose official checksums a core file may match.
	 *
	 * The first one is the locale of the installed package
	 * (`$wp_local_package` in `wp-includes/version.php`), which is the one
	 * WordPress itself checks against. A site can still have files from
	 * another package, for example a German `wp-config-sample.php` next to
	 * an English `version.php` after a mixed install or update, so the
	 * site locale and en_US are accepted too. A file must still be
	 * byte-identical to an official package file to pass.
	 *
	 * @return string[] Unique locales, the installed package locale first.
	 */
	public static function get_checksum_locales() {
		global $wp_local_package;

		$locales = [
			is_string( $wp_local_package ) && $wp_local_package !== '' ? $wp_local_package : 'en_US',
			get_locale(),
			'en_US',
		];

		return array_values( array_unique( array_filter( $locales ) ) );
	}

	/**
	 * Compare core files on disk with the official checksums.
	 *
	 * Files are checked against the checksums of the installed package
	 * locale. Checksums for the other locales from get_checksum_locales()
	 * are only fetched when a file does not match, and the file passes if
	 * it matches one of them.
	 *
	 * Modified files are returned like this:
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
	 * )
	 *
	 * `checksum_locales` lists the package locale and the other locales whose
	 * checksums were fetched and compared.
	 *
	 * @return array{modified_files: array, files_checked: int, checksum_locales: string[]}|\WP_Error
	 */
	public static function run_integrity_check() {
		global $wp_version;

		// Make sure the `get_core_checksums()` function is available.
		if ( ! function_exists( 'get_core_checksums' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$locales        = self::get_checksum_locales();
		$primary_locale = array_shift( $locales );

		// Get official WordPress checksums for current version.
		$checksums = get_core_checksums( $wp_version, $primary_locale );

		if ( ! is_array( $checksums ) || empty( $checksums ) ) {
			return new \WP_Error( 'core_files_check_failed', 'Unable to retrieve WordPress core checksums for version ' . esc_html( $wp_version ) . ' (locale: ' . esc_html( $primary_locale ) . ')' );
		}

		// Checksums for the other locales, fetched on the first mismatch.
		$other_checksums = null;
		$modified_files  = [];
		$files_checked   = 0;
		$wp_root         = ABSPATH;

		// Check each file in the checksums array.
		foreach ( $checksums as $file => $expected_hash ) {
			// Skip files which get updated.
			if ( str_starts_with( $file, 'wp-content' ) ) {
				continue;
			}

			++$files_checked;
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
			if ( $actual_hash === $expected_hash ) {
				continue;
			}

			if ( $other_checksums === null ) {
				$other_checksums = self::get_checksums_for_locales( $wp_version, $locales );
			}

			if ( self::hash_matches_other_locale( $file, $actual_hash, $other_checksums ) ) {
				continue;
			}

			$modified_files[] = [
				'file'          => $file,
				'issue'         => 'modified',
				'expected_hash' => $expected_hash,
				'actual_hash'   => $actual_hash,
			];
		}

		return [
			'modified_files'   => $modified_files,
			'files_checked'    => $files_checked,
			// Only locales whose checksums were fetched: the others were either not needed or not available.
			'checksum_locales' => array_merge( [ $primary_locale ], array_keys( $other_checksums ?? [] ) ),
		];
	}

	/**
	 * Fetch the official checksums for several locales.
	 *
	 * A locale whose checksums can't be fetched is left out.
	 *
	 * @param string   $version WordPress version.
	 * @param string[] $locales Locales.
	 * @return array<string, array<string, string>> Checksums keyed by locale.
	 */
	private static function get_checksums_for_locales( $version, $locales ) {
		$checksums_by_locale = [];

		foreach ( $locales as $locale ) {
			$checksums = get_core_checksums( $version, $locale );

			if ( ! is_array( $checksums ) || empty( $checksums ) ) {
				continue;
			}

			$checksums_by_locale[ $locale ] = $checksums;
		}

		return $checksums_by_locale;
	}

	/**
	 * Check if a file's hash matches the official checksum in another locale.
	 *
	 * @param string                               $file                File path relative to ABSPATH.
	 * @param string                               $actual_hash         MD5 hash of the file on disk.
	 * @param array<string, array<string, string>> $checksums_by_locale Checksums keyed by locale.
	 * @return bool
	 */
	private static function hash_matches_other_locale( $file, $actual_hash, $checksums_by_locale ) {
		foreach ( $checksums_by_locale as $checksums ) {
			if ( isset( $checksums[ $file ] ) && $checksums[ $file ] === $actual_hash ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Process check results and log changes appropriately
	 *
	 * @param array $modified_files Array of modified files from integrity check.
	 */
	private function process_check_results( $modified_files ) {
		$previous_results = get_option( self::OPTION_NAME_FILE_CHECK_RESULTS, [] );
		$current_results  = [];

		// Convert modified files to simple array for comparison.
		foreach ( $modified_files as $file_data ) {
			$current_results[ $file_data['file'] ] = $file_data;
		}

		// Check if this is a new issue or resolved issue.
		$new_issues      = array_diff_key( $current_results, $previous_results );
		$resolved_issues = array_diff_key( $previous_results, $current_results );

		// Log new issues.
		if ( ! empty( $new_issues ) ) {
			$context = [
				'file_count'       => count( $new_issues ),
				'modified_files'   => array_keys( $new_issues ),
				'file_details'     => array_values( $new_issues ),
				'checksum_locales' => implode( ', ', $this->checksum_locales ),
			];

			$this->warning_message( 'core_files_modified', $context );
		}

		// Log resolved issues.
		if ( ! empty( $resolved_issues ) && ! empty( $previous_results ) ) {
			$context = [
				'file_count'           => count( $resolved_issues ),
				'restored_files'       => array_keys( $resolved_issues ),
				'file_details'         => array_values( $resolved_issues ),
				'still_modified_count' => count( $current_results ),
				'checksum_locales'     => implode( ', ', $this->checksum_locales ),
			];

			$this->info_message( 'core_files_restored', $context );
		}

		// Update stored results (no autoload — only used during cron checks).
		update_option( self::OPTION_NAME_FILE_CHECK_RESULTS, $current_results, false );
	}

	/**
	 * Get output for log row details
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|null
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'] ?? null;

		if ( ! $message_key ) {
			return null;
		}

		// Handle detected and restored events.
		if ( ! in_array( $message_key, [ 'core_files_modified', 'core_files_restored' ], true ) ) {
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
		if ( $message_key === 'core_files_restored' ) {
			$event_details_group->set_title( __( 'Restored Core Files', 'simple-history' ) );
		} else {
			$event_details_group->set_title( __( 'Modified Core Files', 'simple-history' ) );
		}

		// Limit to first 5 files to keep log events manageable.
		$limited_file_details = array_slice( $file_details, 0, 5 );
		$total_files          = count( $file_details );

		foreach ( $limited_file_details as $file_data ) {
			// Handle stdClass objects.
			$file  = $file_data->file ?? '';
			$issue = $file_data->issue ?? '';

			if ( empty( $file ) || empty( $issue ) ) {
				continue;
			}

			// Determine the status text.
			if ( $message_key === 'core_files_restored' ) {
				// For restored files, show what was fixed.
				if ( $issue === 'modified' ) {
					$status_text = __( 'Hash mismatch fixed', 'simple-history' );
				} elseif ( $issue === 'unreadable' ) {
					$status_text = __( 'File readability restored', 'simple-history' );
				} elseif ( $issue === 'missing' ) {
					$status_text = __( 'Missing file restored', 'simple-history' );
				} else {
					/* translators: %s: issue type */
					$status_text = sprintf( __( '%s fixed', 'simple-history' ), esc_html( $issue ) );
				}
			} elseif ( $message_key === 'core_files_modified' ) {
				// For detected issues, show the current problem.
				if ( $issue === 'modified' ) {
					$status_text = __( 'Hash mismatch', 'simple-history' );
				} elseif ( $issue === 'unreadable' ) {
					$status_text = __( 'File unreadable', 'simple-history' );
				} elseif ( $issue === 'missing' ) {
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

		// For restored events, show how many files are still modified.
		if ( $message_key === 'core_files_restored' ) {
			$still_modified_count = isset( $context['still_modified_count'] ) ? (int) $context['still_modified_count'] : 0;
			if ( $still_modified_count > 0 ) {
				$event_details_group->add_item(
					( new Event_Details_Item(
						null,
						__( 'Still modified', 'simple-history' )
					) )->set_new_value(
						sprintf(
							/* translators: %d: number of core files still with integrity issues */
							_n(
								'%d file still has issues',
								'%d files still have issues',
								$still_modified_count,
								'simple-history'
							),
							$still_modified_count
						)
					)
				);
			}
		}

		// Which official packages the files were compared with. Older events don't have it.
		if ( ! empty( $context['checksum_locales'] ) ) {
			$event_details_group->add_item(
				( new Event_Details_Item(
					null,
					__( 'Compared with', 'simple-history' )
				) )->set_new_value(
					sprintf(
						/* translators: %s: comma separated list of locales, for example "en_US, de_DE" */
						__( 'Official WordPress files for %s', 'simple-history' ),
						$context['checksum_locales']
					)
				)
			);
		}

		return $event_details_group;
	}
}
