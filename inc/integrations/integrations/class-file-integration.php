<?php

namespace Simple_History\Integrations\Integrations;

use Simple_History\Integrations\Integration;

/**
 * File Integration for Simple History.
 *
 * This integration automatically writes all log events to files,
 * providing a backup mechanism and demonstrating the integration system.
 * This is included in the free version of Simple History.
 *
 * @since 4.4.0
 */
class File_Integration extends Integration {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slug = 'file';
		$this->supports_async = false; // File writing is fast, no need for async.

		parent::__construct();
	}

	/**
	 * Get the display name for this integration.
	 *
	 * @return string The integration display name.
	 */
	public function get_name() {
		return __( 'Log to file', 'simple-history' );
	}

	/**
	 * Get the description for this integration.
	 *
	 * @return string The integration description.
	 */
	public function get_description() {
		return __( 'Automatically save all events to a log file.', 'simple-history' );
	}

	/**
	 * Send an event to this integration.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	public function send_event( $event_data, $formatted_message ) {
		$settings = $this->get_settings();

		// Get the log file path.
		$log_file = $this->get_log_file_path( $settings );

		if ( ! $log_file ) {
			$this->log_error( 'Could not determine log file path' );
			return false;
		}

		// Ensure directory exists.
		$log_dir = dirname( $log_file );
		if ( ! $this->ensure_directory_exists( $log_dir ) ) {
			$this->log_error( 'Could not create log directory: ' . $log_dir );
			return false;
		}

		// Format the log entry.
		$log_entry = $this->format_log_entry( $event_data, $formatted_message, $settings );

		// Write to file.
		return $this->write_to_file( $log_file, $log_entry );
	}

	/**
	 * Get the settings fields for this integration.
	 *
	 * @return array Array of settings fields.
	 */
	public function get_settings_fields() {
		$base_fields = parent::get_settings_fields();

		$file_fields = [
			[
				'type' => 'select',
				'name' => 'rotation_frequency',
				'title' => __( 'Create new files', 'simple-history' ),
				'options' => [
					'daily' => __( 'Daily', 'simple-history' ),
					'weekly' => __( 'Weekly', 'simple-history' ),
					'monthly' => __( 'Monthly', 'simple-history' ),
					'never' => __( 'Never (single file)', 'simple-history' ),
				],
				'default' => 'daily',
			],
			[
				'type' => 'number',
				'name' => 'keep_files',
				'title' => __( 'Number of files to keep', 'simple-history' ),
				'description' => __( 'Oldest file will be deleted. Set to 0 to keep forever.', 'simple-history' ),
				'default' => 30,
				'min' => 0,
				'max' => 365,
			],
		];

		return array_merge( $base_fields, $file_fields );
	}

	/**
	 * Get additional info HTML to display after the settings fields.
	 *
	 * @return string HTML content to display.
	 */
	public function get_settings_info_after_fields_html() {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		$log_directory = $this->get_log_directory_path();

		ob_start();
		?>
		<div class="sh-Integration-info">
			<p class="description">
				<?php esc_html_e( 'Files are saved to directory:', 'simple-history' ); ?><br>
				<code><?php echo esc_html( $log_directory ); ?></code>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the current log directory path for display to users.
	 *
	 * @return string The log directory path.
	 */
	public function get_log_directory_path() {
		return $this->get_default_log_directory();
	}

	/**
	 * Get the log file path based on settings.
	 *
	 * @param array $settings Integration settings.
	 * @return string|false Log file path or false on error.
	 */
	private function get_log_file_path( $settings ) {
		$log_dir = $this->get_default_log_directory();
		$rotation = $settings['rotation_frequency'] ?? 'daily';
		$filename = $this->get_log_filename( $rotation );

		if ( ! $filename ) {
			return false;
		}

		return trailingslashit( $log_dir ) . $filename;
	}

	/**
	 * Get the default log directory.
	 *
	 * Uses a hard-to-guess directory within wp-content for security.
	 *
	 * @return string Default log directory path.
	 */
	private function get_default_log_directory() {
		// Generate a hard-to-guess directory name based on site specifics.
		$site_hash = $this->get_site_hash();

		return trailingslashit( WP_CONTENT_DIR ) . 'simple-history-logs-' . $site_hash;
	}

	/**
	 * Generate a site-specific hash for directory naming.
	 *
	 * Uses site URL and auth keys to create a unique, hard-to-guess identifier.
	 *
	 * @return string 8-character hash based on site specifics.
	 */
	private function get_site_hash() {
		// Combine site-specific data for uniqueness.
		$site_data = get_site_url() . ABSPATH;

		// Add auth keys/salts if available for additional uniqueness.
		if ( defined( 'AUTH_KEY' ) ) {
			$site_data .= AUTH_KEY;
		}
		if ( defined( 'SECURE_AUTH_KEY' ) ) {
			$site_data .= SECURE_AUTH_KEY;
		}

		// Generate hash and return first 8 characters.
		return substr( md5( $site_data ), 0, 8 );
	}

	/**
	 * Get the log filename based on rotation frequency.
	 *
	 * Example formats:
	 * - Daily: events-2023-10-01.log
	 * - Weekly: events-2023-W40.log
	 * - Monthly: events-2023-10.log
	 *
	 * @param string $rotation Rotation frequency.
	 * @return string|false Log filename or false on error.
	 */
	private function get_log_filename( $rotation ) {
		$base_name = 'events';
		$extension = '.log';

		switch ( $rotation ) {
			case 'daily':
				return $base_name . '-' . current_time( 'Y-m-d' ) . $extension;

			case 'weekly':
				return $base_name . '-' . current_time( 'Y' ) . '-W' . current_time( 'W' ) . $extension;

			case 'monthly':
				return $base_name . '-' . current_time( 'Y-m' ) . $extension;

			case 'never':
				return $base_name . $extension;

			default:
				return false;
		}
	}

	/**
	 * Format a log entry in simple human-readable format.
	 *
	 * @param array  $event_data The event data.
	 * @param string $formatted_message The formatted message.
	 * @param array  $settings Integration settings (unused).
	 * @return string Formatted log entry.
	 */
	private function format_log_entry( $event_data, $formatted_message, $settings ) {
		$timestamp = current_time( 'Y-m-d H:i:s' );

		return sprintf(
			"[%s] %s %s: %s (via %s)\n",
			$timestamp,
			strtoupper( $event_data['level'] ?? 'info' ),
			$event_data['logger'] ?? 'Unknown',
			$formatted_message,
			$event_data['initiator'] ?? 'unknown'
		);
	}

	/**
	 * Ensure a directory exists and is writable.
	 *
	 * @param string $directory Directory path.
	 * @return bool True if directory exists or was created successfully.
	 */
	private function ensure_directory_exists( $directory ) {
		if ( is_dir( $directory ) ) {
			return is_writable( $directory );
		}

		// Try to create the directory.
		if ( wp_mkdir_p( $directory ) ) {
			// Set appropriate permissions.
			chmod( $directory, 0755 );
			return true;
		}

		return false;
	}

	/**
	 * Write content to a log file.
	 *
	 * @param string $file_path Path to the log file.
	 * @param string $content Content to write.
	 * @return bool True on success, false on failure.
	 */
	private function write_to_file( $file_path, $content ) {
		// Write to file with locking.
		$result = file_put_contents( $file_path, $content, FILE_APPEND | LOCK_EX );

		if ( false === $result ) {
			$this->log_error( 'Failed to write to log file: ' . $file_path );
			return false;
		}

		// Clean up old files if needed.
		$this->cleanup_old_files();

		return true;
	}

	/**
	 * Clean up old log files based on current rotation frequency and keep settings.
	 *
	 * Only removes files that match the current rotation pattern.
	 */
	private function cleanup_old_files() {
		$settings = $this->get_settings();
		$keep_files = $settings['keep_files'] ?? 30;
		$rotation = $settings['rotation_frequency'] ?? 'daily';

		if ( $keep_files <= 0 ) {
			return; // Keep all files.
		}

		// No cleanup needed for "never" rotation - only one file exists.
		if ( $rotation === 'never' ) {
			return;
		}

		$log_dir = $this->get_default_log_directory();

		if ( ! is_dir( $log_dir ) ) {
			return;
		}

		// Get files that match the current rotation pattern only.
		$pattern = $this->get_cleanup_pattern( $rotation );
		/** @var array<string>|false $log_files */
		$log_files = glob( trailingslashit( $log_dir ) . $pattern );

		if ( empty( $log_files ) || count( $log_files ) <= $keep_files ) {
			return; // Not enough files to clean up.
		}

		// Sort by filename (which contains date) for consistent ordering.
		sort( $log_files );

		// Delete oldest files, keeping the most recent ones.
		$files_to_delete = array_slice( $log_files, 0, count( $log_files ) - $keep_files );

		foreach ( $files_to_delete as $file ) {
			/** @var string $file */
			if ( unlink( $file ) ) {
				$this->log_debug( 'Deleted old log file: ' . basename( $file ) );
			} else {
				$this->log_error( 'Failed to delete old log file: ' . basename( $file ) );
			}
		}
	}

	/**
	 * Get the glob pattern for cleanup based on rotation frequency.
	 *
	 * Note: This method is only called for rotated files (daily, weekly, monthly).
	 * The "never" case is handled by early exit in cleanup_old_files().
	 *
	 * @param string $rotation The rotation frequency.
	 * @return string The glob pattern to match log files.
	 */
	private function get_cleanup_pattern( $rotation ) {
		switch ( $rotation ) {
			case 'daily':
				// Matches files like events-2025-01-23.log.
				return 'events-[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9].log*';

			case 'weekly':
				// Matches files like events-2025-W04.log.
				return 'events-[0-9][0-9][0-9][0-9]-W[0-9][0-9].log*';

			case 'monthly':
				// Matches files like events-2025-01.log.
				return 'events-[0-9][0-9][0-9][0-9]-[0-9][0-9].log*';

			default:
				// Fallback for any unexpected rotation values.
				return 'events*.log*';
		}
	}
}
