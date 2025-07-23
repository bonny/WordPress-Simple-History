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
	 * Cache for directory existence checks.
	 *
	 * @var array<string, bool>
	 */
	private static $directory_cache = [];

	/**
	 * Cache for settings to avoid repeated lookups.
	 *
	 * @var array<string, mixed>|null
	 */
	private $settings_cache = null;

	/**
	 * Last cleanup time to avoid frequent cleanup operations.
	 *
	 * @var int
	 */
	private static $last_cleanup_time = 0;

	/**
	 * Write buffer to batch multiple log entries.
	 *
	 * @var array<string, string>
	 */
	private static $write_buffer = [];

	/**
	 * Maximum buffer size before forcing a flush.
	 *
	 * @var int
	 */
	private static $buffer_max_size = 10;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->slug = 'file';
		$this->supports_async = false; // File writing is fast, no need for async.

		parent::__construct();

		// Register cleanup hook for async processing.
		add_action( 'simple_history_cleanup_log_files', [ $this, 'handle_async_cleanup' ] );
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
		return __( 'Save all events to a log file', 'simple-history' );
	}

	/**
	 * Send an event to this integration.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	public function send_event( $event_data, $formatted_message ) {
		// Don't write anything if integration is disabled.
		if ( ! $this->is_enabled() ) {
			return true;
		}

		// Get the log file path.
		$log_file = $this->get_log_file_path();

		if ( ! $log_file ) {
			$this->log_error( 'Could not determine log file path' );
			return false;
		}

		// Ensure directory exists (with caching).
		$log_dir = dirname( $log_file );
		if ( ! $this->ensure_directory_exists_cached( $log_dir ) ) {
			$this->log_error( 'Could not create log directory: ' . $log_dir );
			return false;
		}

		// Format the log entry.
		$log_entry = $this->format_log_entry( $event_data, $formatted_message );

		// Add to buffer for batch writing.
		return $this->add_to_write_buffer( $log_file, $log_entry );
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
	 * Get cached settings to avoid repeated database calls.
	 *
	 * @return array<string, mixed> Cached settings.
	 */
	private function get_cached_settings() {
		if ( null === $this->settings_cache ) {
			$this->settings_cache = $this->get_settings();
		}
		return $this->settings_cache;
	}

	/**
	 * Get a setting with caching.
	 *
	 * @param string $setting_name Setting name.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	private function get_cached_setting( $setting_name, $default = null ) {
		$settings = $this->get_cached_settings();
		return $settings[ $setting_name ] ?? $default;
	}

	/**
	 * Get the log file path based on current settings.
	 *
	 * @return string|false Log file path or false on error.
	 */
	private function get_log_file_path() {
		$log_dir = $this->get_default_log_directory();
		/** @var string $rotation */
		$rotation = $this->get_cached_setting( 'rotation_frequency', 'daily' );
		/** @var string|false $filename */
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
	 * @return string Formatted log entry.
	 */
	private function format_log_entry( $event_data, $formatted_message ) {
		$timestamp = current_time( 'Y-m-d H:i:s' );

		// Standard log format: timestamp level logger: message [key=value ...].
		$level = strtoupper( $event_data['level'] ?? 'info' );
		$logger = $event_data['logger'] ?? 'Unknown';
		$initiator = $event_data['initiator'] ?? 'unknown';

		// Use $context for easier access.
		$context = $event_data['context'] ?? [];

		// Add essential structured data for better parsing.
		$structured_data = [];

		// Only include most important and commonly available fields.
		$essential_fields = [ '_message_key', '_server_remote_addr', '_user_id', '_user_login', '_user_email' ];

		if ( ! empty( $context ) ) {
			foreach ( $essential_fields as $field ) {
				if ( isset( $context[ $field ] ) && is_scalar( $context[ $field ] ) ) {
					$clean_key = ltrim( $field, '_' ); // Remove leading underscore for cleaner output.
					$structured_data[] = $clean_key . '=' . $context[ $field ];
				}
			}
		}

		// Always include initiator.
		$structured_data[] = 'initiator=' . $initiator;

		$structured_suffix = count( $structured_data ) > 0 ? ' [' . implode( ' ', $structured_data ) . ']' : '';

		return sprintf(
			"[%s] %s %s: %s%s\n",
			$timestamp,
			$level,
			$logger,
			$formatted_message,
			$structured_suffix
		);
	}

	/**
	 * Ensure a directory exists with caching to avoid repeated filesystem checks.
	 *
	 * @param string $directory Directory path.
	 * @return bool True if directory exists or was created successfully.
	 */
	private function ensure_directory_exists_cached( $directory ) {
		// Check cache first.
		if ( isset( self::$directory_cache[ $directory ] ) ) {
			return self::$directory_cache[ $directory ];
		}

		$result = $this->ensure_directory_exists( $directory );
		self::$directory_cache[ $directory ] = $result;

		return $result;
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
			
			// Create .htaccess file for security.
			$this->create_htaccess_file( $directory );
			
			return true;
		}

		return false;
	}

	/**
	 * Create .htaccess file to protect log directory.
	 *
	 * @param string $directory Directory path.
	 */
	private function create_htaccess_file( $directory ) {
		$htaccess_path = trailingslashit( $directory ) . '.htaccess';
		
		// Only create if it doesn't exist.
		if ( ! file_exists( $htaccess_path ) ) {
			$htaccess_content = "# Simple History log directory protection\n";
			$htaccess_content .= "Order deny,allow\n";
			$htaccess_content .= "Deny from all\n";
			
			file_put_contents( $htaccess_path, $htaccess_content );
		}
	}

	/**
	 * Write content to a log file with optimizations for high-traffic scenarios.
	 *
	 * @param string $file_path Path to the log file.
	 * @param string $content Content to write.
	 * @return bool True on success, false on failure.
	 */
	private function write_to_file_optimized( $file_path, $content ) {
		// Attempt to write with retry mechanism.
		$max_attempts = 3;
		$attempt = 0;

		while ( $attempt < $max_attempts ) {
			$attempt++;

			// Write to file with locking. Suppress warnings for testing.
			$result = @file_put_contents( $file_path, $content, FILE_APPEND | LOCK_EX );

			if ( false !== $result ) {
				// Success - schedule cleanup if needed (throttled).
				$this->schedule_cleanup_if_needed();
				return true;
			}

			// Failed - wait briefly before retry.
			if ( $attempt < $max_attempts ) {
				usleep( 100000 ); // 100ms delay.
			}
		}

		// All attempts failed.
		$this->log_error( 'Failed to write to log file after ' . $max_attempts . ' attempts: ' . $file_path );
		return false;
	}

	/**
	 * Schedule cleanup if needed (throttled to avoid running on every write).
	 */
	private function schedule_cleanup_if_needed() {
		$cleanup_interval = 3600; // Run cleanup at most once per hour.
		$current_time = time();

		// Check if enough time has passed since last cleanup.
		if ( ( $current_time - self::$last_cleanup_time ) < $cleanup_interval ) {
			return;
		}

		// Update last cleanup time to prevent concurrent runs.
		self::$last_cleanup_time = $current_time;

		// Run cleanup asynchronously if possible.
		if ( function_exists( 'wp_schedule_single_event' ) ) {
			// Schedule cleanup to run in background.
			wp_schedule_single_event( time() + 60, 'simple_history_cleanup_log_files', [ $this->get_slug() ] );
		} else {
			// Fallback to immediate cleanup if WP Cron not available.
			$this->cleanup_old_files();
		}
	}

	/**
	 * Clean up old log files based on current rotation frequency and keep settings.
	 *
	 * Only removes files that match the current rotation pattern.
	 */
	private function cleanup_old_files() {
		/** @var int $keep_files */
		$keep_files = $this->get_cached_setting( 'keep_files', 30 );
		/** @var string $rotation */
		$rotation = $this->get_cached_setting( 'rotation_frequency', 'daily' );

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
		/** @var list<string>|false $log_files */
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

	/**
	 * Handle async cleanup triggered by WordPress cron.
	 *
	 * @param string $integration_slug The integration slug to clean up for.
	 */
	public function handle_async_cleanup( $integration_slug ) {
		// Only process if this is our integration.
		if ( $integration_slug !== $this->get_slug() ) {
			return;
		}

		$this->cleanup_old_files();
	}

	/**
	 * Add log entry to write buffer for batch processing.
	 *
	 * @param string $log_file Path to the log file.
	 * @param string $log_entry Formatted log entry.
	 * @return bool True on success, false on failure.
	 */
	private function add_to_write_buffer( $log_file, $log_entry ) {
		// Initialize buffer for this file if needed.
		if ( ! isset( self::$write_buffer[ $log_file ] ) ) {
			self::$write_buffer[ $log_file ] = '';
		}

		// Add entry to buffer.
		self::$write_buffer[ $log_file ] .= $log_entry;

		// Check if we should flush the buffer.
		if ( $this->should_flush_buffer() ) {
			return $this->flush_write_buffer();
		}

		// Register shutdown hook to ensure buffer is flushed.
		if ( ! has_action( 'shutdown', [ $this, 'flush_write_buffer_on_shutdown' ] ) ) {
			add_action( 'shutdown', [ $this, 'flush_write_buffer_on_shutdown' ] );
		}

		return true;
	}

	/**
	 * Check if write buffer should be flushed.
	 *
	 * @return bool True if buffer should be flushed.
	 */
	private function should_flush_buffer() {
		// Flush if buffer is getting large.
		if ( count( self::$write_buffer ) >= self::$buffer_max_size ) {
			return true;
		}

		// Flush if total buffer size is large.
		$total_size = 0;
		foreach ( self::$write_buffer as $content ) {
			$total_size += strlen( $content );
		}

		// Flush if buffer exceeds 64KB.
		return $total_size > 65536;
	}

	/**
	 * Flush the write buffer to disk.
	 *
	 * @return bool True if all writes succeeded, false otherwise.
	 */
	public function flush_write_buffer() {
		if ( empty( self::$write_buffer ) ) {
			return true;
		}

		$success = true;
		foreach ( self::$write_buffer as $log_file => $content ) {
			if ( ! $this->write_to_file_optimized( $log_file, $content ) ) {
				$success = false;
			}
		}

		// Clear the buffer after writing.
		self::$write_buffer = [];

		return $success;
	}

	/**
	 * Flush write buffer on shutdown to ensure no data is lost.
	 */
	public function flush_write_buffer_on_shutdown() {
		$this->flush_write_buffer();
	}
}
