<?php

namespace Simple_History\Channels\Channels;

use Simple_History\Channels\Channel;
use Simple_History\Channels\Formatters\Formatter_Interface;
use Simple_History\Channels\Formatters\Human_Readable_Formatter;
use Simple_History\Helpers;

/**
 * File Channel for Simple History.
 *
 * This channel automatically writes all log events to files,
 * providing a backup mechanism and demonstrating the channel system.
 * This is included in the free version of Simple History.
 *
 * @since 4.4.0
 */
class File_Channel extends Channel {
	/**
	 * The unique slug for this channel.
	 *
	 * @var ?string
	 */
	protected ?string $slug = 'file';

	/**
	 * Whether this channel supports async processing.
	 * File writing is fast, no need for async.
	 *
	 * @var bool
	 */
	protected bool $supports_async = false;

	/**
	 * Cache for directory existence checks.
	 *
	 * @var array<string, bool>
	 */
	private static $directory_cache = [];

	/**
	 * Last cleanup time to avoid frequent cleanup operations.
	 *
	 * @var int
	 */
	private static $last_cleanup_time = 0;

	/**
	 * Called when the channel is loaded and ready.
	 *
	 * Registers hooks for async cleanup processing.
	 */
	public function loaded() {
		add_action( 'simple_history_cleanup_log_files', [ $this, 'handle_async_cleanup' ] );
	}

	/**
	 * Flush the write buffer.
	 *
	 * Currently a no-op as writes are synchronous.
	 * This method exists for API compatibility and future buffering implementation.
	 */
	public function flush_write_buffer() {
		// Currently writes are synchronous, so nothing to flush.
		// This method is here for API compatibility with tests
		// and for potential future buffering implementation.
	}

	/**
	 * Get the selected formatter slug with fallback.
	 *
	 * Returns the saved formatter slug if it exists in available formatters,
	 * otherwise falls back to 'human_readable'. This handles the case where
	 * a user had the premium add-on with additional formatters, selected one,
	 * and then disabled the premium add-on.
	 *
	 * @return string The formatter slug.
	 */
	private function get_selected_formatter_slug(): string {
		$formatter_slug = $this->get_setting( 'formatter', 'human_readable' );
		$formatters     = $this->get_available_formatters();

		// Check if saved formatter exists in available formatters.
		if ( isset( $formatters[ $formatter_slug ] ) ) {
			return $formatter_slug;
		}

		// Fall back to human readable.
		return 'human_readable';
	}

	/**
	 * Get the formatter instance for this channel.
	 *
	 * @return Formatter_Interface The formatter instance.
	 */
	private function get_formatter(): Formatter_Interface {
		$formatter_slug = $this->get_selected_formatter_slug();
		$formatters     = $this->get_available_formatters();

		// Return the formatter instance.
		if ( isset( $formatters[ $formatter_slug ] ) && $formatters[ $formatter_slug ] instanceof Formatter_Interface ) {
			return $formatters[ $formatter_slug ];
		}

		// Fall back to human readable (should not happen but safety first).
		return new Human_Readable_Formatter();
	}

	/**
	 * Get available formatters.
	 *
	 * Returns an array of formatter instances keyed by slug.
	 * Each formatter provides its own name and description via get_name() and get_description().
	 *
	 * @return array<string, Formatter_Interface>
	 */
	private function get_available_formatters(): array {
		$formatters = [
			'human_readable' => new Human_Readable_Formatter(),
		];

		/**
		 * Filter available formatters for the file channel.
		 *
		 * Allows adding custom formatters. Each formatter must implement Formatter_Interface
		 * and provide get_name() and get_description() methods.
		 *
		 * Example:
		 *
		 *     add_filter( 'simple_history/file_channel/formatters', function( $formatters ) {
		 *         $formatters['my_format'] = new My_Custom_Formatter();
		 *         return $formatters;
		 *     } );
		 *
		 * @since 5.7.0
		 *
		 * @param array<string, Formatter_Interface> $formatters Array of formatter instances keyed by slug.
		 */
		return apply_filters( 'simple_history/file_channel/formatters', $formatters );
	}

	/**
	 * Get the display name for this channel.
	 *
	 * @return string The channel display name.
	 */
	public function get_name() {
		return __( 'Log to File', 'simple-history' );
	}

	/**
	 * Get the description for this channel.
	 *
	 * @return string The channel description.
	 */
	public function get_description() {
		return __( 'Automatically forward all events to log files for backup, compliance, or integration with external log analysis tools.', 'simple-history' );
	}

	/**
	 * Output HTML after the description in the intro section.
	 */
	public function settings_output_intro() {
		?>
		<p>
			<?php esc_html_e( 'Log files are stored independently from the database, unaffected by "Clear log" or database retention settings.', 'simple-history' ); ?>
		</p>
		<?php
	}

	/**
	 * Send an event to this channel.
	 *
	 * @param array  $event_data The event data to send.
	 * @param string $formatted_message The formatted message.
	 * @return bool True on success, false on failure.
	 */
	public function send_event( $event_data, $formatted_message ) {
		// Don't write anything if channel is disabled.
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

		// Format the log entry using configured formatter.
		$formatter = $this->get_formatter();
		$log_entry = $formatter->format( $event_data, $formatted_message );

		// Write to file.
		$result = file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );

		if ( false === $result ) {
			$this->log_error( 'Failed to write to log file: ' . $log_file );
			return false;
		}

		// Schedule cleanup if needed.
		$this->schedule_cleanup_if_needed();

		return true;
	}

	/**
	 * Add settings fields for this channel using WordPress Settings API.
	 *
	 * @param string $settings_page_slug The settings page slug.
	 * @param string $settings_section_id The settings section ID.
	 */
	public function add_settings_fields( $settings_page_slug, $settings_section_id ) {
		// Add parent's enable checkbox first.
		parent::add_settings_fields( $settings_page_slug, $settings_section_id );

		$option_name = $this->get_settings_option_name();

		// Rotation frequency field.
		add_settings_field(
			$option_name . '_rotation_frequency',
			Helpers::get_settings_field_title_output( __( 'Create new files', 'simple-history' ) ),
			[ $this, 'settings_field_rotation_frequency' ],
			$settings_page_slug,
			$settings_section_id
		);

		// Output format field.
		add_settings_field(
			$option_name . '_formatter',
			Helpers::get_settings_field_title_output( __( 'Output format', 'simple-history' ) ),
			[ $this, 'settings_field_formatter' ],
			$settings_page_slug,
			$settings_section_id
		);

		// Number of files to keep field.
		add_settings_field(
			$option_name . '_keep_files',
			Helpers::get_settings_field_title_output( __( 'Number of files to keep', 'simple-history' ) ),
			[ $this, 'settings_field_keep_files' ],
			$settings_page_slug,
			$settings_section_id
		);

		// File path info field.
		add_settings_field(
			$option_name . '_file_path',
			Helpers::get_settings_field_title_output( __( 'Log folder', 'simple-history' ) ),
			[ $this, 'settings_field_file_path' ],
			$settings_page_slug,
			$settings_section_id
		);
	}

	/**
	 * Render the rotation frequency settings field.
	 */
	public function settings_field_rotation_frequency() {
		$option_name = $this->get_settings_option_name();
		$value       = $this->get_setting( 'rotation_frequency', 'daily' );

		$options = [
			'daily'   => __( 'Daily', 'simple-history' ),
			'weekly'  => __( 'Weekly', 'simple-history' ),
			'monthly' => __( 'Monthly', 'simple-history' ),
		];
		?>
		<select name="<?php echo esc_attr( $option_name ); ?>[rotation_frequency]">
			<?php
			foreach ( $options as $option_value => $option_label ) {
				?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
				<?php
			}
			?>
		</select>
		<?php
	}

	/**
	 * Render the output format settings field.
	 */
	public function settings_field_formatter() {
		$option_name             = $this->get_settings_option_name();
		$selected_formatted_slug = $this->get_selected_formatter_slug();
		$formatters              = $this->get_available_formatters();
		$is_premium_active       = Helpers::is_premium_add_on_active();
		?>
		<fieldset class="sh-FileChannel-formatters">
			<?php
			foreach ( $formatters as $formatter_slug => $formatter ) {
				?>
				<label class="sh-FileChannel-formatterOption">
					<input
						type="radio"
						name="<?php echo esc_attr( $option_name ); ?>[formatter]"
						value="<?php echo esc_attr( $formatter_slug ); ?>"
						<?php checked( $selected_formatted_slug, $formatter_slug ); ?>
					/>

					<?php echo esc_html( $formatter->get_name() ); ?>

					<span class="sh-FileChannel-formatterDescription description">
						<?php echo esc_html( $formatter->get_description() ); ?>
					</span>
				</label>
				<?php
			}

			// Show disabled premium formatters when premium is not active.
			if ( ! $is_premium_active ) {
				$this->render_premium_formatter_teasers();
			}
			?>
		</fieldset>

		<?php
		// Show premium promo box if premium add-on is not active.
		if ( ! $is_premium_active ) {
			echo wp_kses_post(
				Helpers::get_premium_feature_teaser(
					__( 'Unlock All Log Formats', 'simple-history' ),
					[
						__( 'Unlock the three formats above', 'simple-history' ),
						__( 'Send logs to your existing monitoring stack', 'simple-history' ),
						__( 'Industry-standard formats for automated processing', 'simple-history' ),
					],
					'file_channel_formatters',
					__( 'Unlock All Formats', 'simple-history' )
				)
			);
		}
	}

	/**
	 * Render disabled radio buttons for premium formatters.
	 *
	 * Shows what premium formatters look like without being selectable,
	 * creating FOMO and demonstrating value of the premium add-on.
	 */
	private function render_premium_formatter_teasers() {
		// Define premium formatters with their names and descriptions.
		// These match the actual premium formatters for consistency.
		$premium_formatters = [
			'json_lines' => [
				'name'        => __( 'JSON Lines (GELF)', 'simple-history' ),
				'description' => __( 'One JSON object per line. Compatible with Graylog, ELK, Splunk, and other log aggregation tools.', 'simple-history' ),
			],
			'logfmt'     => [
				'name'        => __( 'Logfmt', 'simple-history' ),
				'description' => __( 'Key=value pairs. Compatible with Grafana Loki, Prometheus, and cloud-native log systems.', 'simple-history' ),
			],
			'rfc5424'    => [
				'name'        => __( 'RFC 5424 Syslog', 'simple-history' ),
				'description' => __( 'Standard syslog format with structured data. Best for syslog servers and SIEM tools.', 'simple-history' ),
			],
		];

		foreach ( $premium_formatters as $formatter_slug => $formatter ) {
			?>
			<label class="sh-FileChannel-formatterOption sh-FileChannel-formatterOption--disabled">
				<input
					type="radio"
					disabled
				/>

				<?php echo esc_html( $formatter['name'] ); ?>

				<span class="sh-FileChannel-formatterDescription description">
					<?php echo esc_html( $formatter['description'] ); ?>
				</span>
			</label>
			<?php
		}
	}

	/**
	 * Render the keep files settings field.
	 */
	public function settings_field_keep_files() {
		$option_name = $this->get_settings_option_name();
		$value       = $this->get_setting( 'keep_files', 30 );
		?>
		<input
			type="number"
			name="<?php echo esc_attr( $option_name ); ?>[keep_files]"
			value="<?php echo esc_attr( $value ); ?>"
			min="1"
			max="365"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Oldest files will be deleted when this limit is reached.', 'simple-history' ); ?>
		</p>
		<?php
	}

	/**
	 * Test folder writability and attempt to create if needed.
	 *
	 * Returns an array with status information about the folder:
	 * - exists: bool - Whether the folder exists (after any creation attempt)
	 * - created: bool - Whether the folder was created during this call
	 * - creation_failed: bool - Whether creation was attempted but failed
	 * - is_writable: bool - Whether the folder is writable
	 *
	 * @param string $directory The directory path to test.
	 * @return array{exists: bool, created: bool, creation_failed: bool, is_writable: bool}
	 */
	private function test_folder_writability( $directory ) {
		$result = [
			'exists'          => false,
			'created'         => false,
			'creation_failed' => false,
			'is_writable'     => false,
		];

		// Check if directory already exists.
		$result['exists'] = file_exists( $directory );

		if ( ! $result['exists'] ) {
			// Attempt to create the directory.
			$created = wp_mkdir_p( $directory );

			if ( $created ) {
				$result['exists']  = true;
				$result['created'] = true;

				// Create protection files.
				$this->create_htaccess_file( $directory );
				$this->create_index_file( $directory );
			} else {
				$result['creation_failed'] = true;
			}
		}

		// Check writability if directory exists.
		$result['is_writable'] = $result['exists'] && is_writable( $directory );

		return $result;
	}

	/**
	 * Render the file path info field.
	 */
	public function settings_field_file_path() {
		$log_directory = $this->get_log_directory_path();
		$test_url      = $this->get_log_directory_url();

		// Test folder writability and attempt creation if needed.
		$folder_status   = $this->test_folder_writability( $log_directory );
		$creation_failed = $folder_status['creation_failed'];
		$is_writable     = $folder_status['is_writable'];
		?>
		<code><?php echo esc_html( $log_directory ); ?></code>

		<?php // Directory status message. ?>
		<p class="description">
			<?php
			if ( $creation_failed ) {
				printf(
					'<span class="sh-FileChannel-folderStatus--error">%s</span>',
					esc_html__( 'Folder could not be created. Please check that the parent directory is writable.', 'simple-history' )
				);
			} elseif ( ! $is_writable ) {
				printf(
					'<span class="sh-FileChannel-folderStatus--error">%s</span>',
					esc_html__( 'Folder exists but is not writable. Please check folder permissions.', 'simple-history' )
				);
			} else {
				printf(
					'<span class="sh-FileChannel-folderStatus--success">%s</span>',
					esc_html__( 'Folder exists and is writable.', 'simple-history' )
				);

				// Show file statistics when folder is accessible.
				$stats = $this->get_log_files_stats();
				if ( $stats['count'] > 0 ) {
					echo '<br>';
					printf(
						/* translators: 1: number of log files, 2: total size */
						esc_html__( 'Contains %1$s log files (%2$s total).', 'simple-history' ),
						esc_html( number_format_i18n( $stats['count'] ) ),
						esc_html( size_format( $stats['total_size'] ) )
					);

					if ( $stats['oldest'] && $stats['newest'] ) {
						echo '<br>';
						printf(
							/* translators: 1: oldest file date, 2: newest file date */
							esc_html__( 'Date range: %1$s to %2$s.', 'simple-history' ),
							esc_html( wp_date( get_option( 'date_format' ), $stats['oldest'] ) ),
							esc_html( wp_date( get_option( 'date_format' ), $stats['newest'] ) )
						);
					}
				}
			}
			?>
		</p>

		<?php // Public access message. ?>
		<p class="description">
			<?php
			if ( $test_url ) {
				esc_html_e( 'The folder appears to be in a public web directory. Ensure the folder and its files are not accessible from the public.', 'simple-history' );
				echo '<br>';
				printf(
					'<a href="%1$s" target="_blank" class="sh-ExternalLink">%2$s</a>%3$s',
					esc_url( $test_url ),
					esc_html__( 'Test folder access', 'simple-history' ),
					esc_html__( ' – should show a 403 Forbidden error.', 'simple-history' )
				);
			} else {
				esc_html_e( 'The folder appears to be outside the public web directory.', 'simple-history' );
			}
			?>
		</p>
		<?php
	}

	/**
	 * Get the URL to the log directory for access testing.
	 *
	 * @return string|false The URL to the log directory, or false if not determinable.
	 */
	private function get_log_directory_url() {
		$log_directory = $this->get_log_directory_path();

		// Check if the log directory is inside ABSPATH (WordPress root).
		// If so, it's potentially publicly accessible.
		if ( strpos( $log_directory, ABSPATH ) !== 0 ) {
			return false;
		}

		// Get the relative path from ABSPATH.
		$relative_path = substr( $log_directory, strlen( ABSPATH ) );

		// Build the URL.
		return site_url( $relative_path );
	}

	/**
	 * Sanitize settings for this channel.
	 *
	 * @param array $input Raw input data from form submission.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		// Get parent sanitization first.
		$sanitized = parent::sanitize_settings( $input );

		// Sanitize rotation frequency.
		$valid_frequencies               = [ 'daily', 'weekly', 'monthly' ];
		$sanitized['rotation_frequency'] = in_array( $input['rotation_frequency'] ?? '', $valid_frequencies, true )
			? $input['rotation_frequency']
			: 'daily';

		// Sanitize formatter.
		$valid_formatters       = array_keys( $this->get_available_formatters() );
		$sanitized['formatter'] = in_array( $input['formatter'] ?? '', $valid_formatters, true )
			? $input['formatter']
			: 'human_readable';

		// Sanitize keep files (integer between 1 and 365).
		$keep_files              = isset( $input['keep_files'] ) ? absint( $input['keep_files'] ) : 30;
		$sanitized['keep_files'] = min( 365, max( 1, $keep_files ) );

		return $sanitized;
	}

	/**
	 * Get the default settings for this channel.
	 *
	 * @return array Array of default settings.
	 */
	protected function get_default_settings() {
		return array_merge(
			parent::get_default_settings(),
			[
				'rotation_frequency' => 'daily',
				'formatter'          => 'human_readable',
				'keep_files'         => 30,
			]
		);
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
	 * Get the log file path based on current settings.
	 *
	 * @return string|false Log file path or false on error.
	 */
	private function get_log_file_path() {
		$log_dir = $this->get_default_log_directory();
		/** @var string $rotation */
		$rotation = $this->get_setting( 'rotation_frequency', 'daily' );
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
		// Use a random token for directory name (stored in settings for stability).
		$folder_token = $this->get_folder_token();

		$default_directory = trailingslashit( WP_CONTENT_DIR ) . 'simple-history-logs-' . $folder_token;

		/**
		 * Filter the log directory path.
		 *
		 * Allows customization of where log files are stored.
		 * For security, consider placing logs outside the public web directory.
		 *
		 * Example: Move logs outside the public web directory:
		 *
		 *     add_filter( 'simple_history/file_channel/log_directory', function( $directory ) {
		 *         return '/var/log/wordpress/simple-history';
		 *     } );
		 *
		 * @since 5.6.0
		 *
		 * @param string $directory The log directory path.
		 */
		$log_directory = apply_filters( 'simple_history/file_channel/log_directory', $default_directory );

		// Ensure the directory ends with a slash.
		$log_directory = trailingslashit( $log_directory );

		return $log_directory;
	}

	/**
	 * Get or create the folder token for log directory naming.
	 *
	 * Uses a stored random token instead of deriving from site config,
	 * ensuring the folder path remains stable even if auth keys change.
	 *
	 * Note: Stored in a separate option (not channel settings) to avoid
	 * conflicts with the WordPress Settings API sanitization flow.
	 *
	 * @return string 16-character alphanumeric token.
	 */
	private function get_folder_token() {
		$option_name = 'simple_history_file_channel_folder_token';
		$token       = get_option( $option_name );

		if ( ! $token ) {
			$token = wp_generate_password( 16, false, false );
			update_option( $option_name, $token, false );
		}

		/**
		 * Filter the folder token used for log directory naming.
		 *
		 * Allows customization of the folder token for multisite or custom deployments.
		 *
		 * @since 5.6.0
		 *
		 * @param string $token The 16-character folder token.
		 */
		return apply_filters( 'simple_history/file_channel/folder_token', $token );
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

			default:
				return false;
		}
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

		$result                              = $this->ensure_directory_exists( $directory );
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
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.chmod_chmod
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
			$htaccess_content  = "# Simple History log directory protection\n\n";
			$htaccess_content .= "# Apache 2.4+\n";
			$htaccess_content .= "<IfModule mod_authz_core.c>\n";
			$htaccess_content .= "    Require all denied\n";
			$htaccess_content .= "</IfModule>\n\n";
			$htaccess_content .= "# Apache 2.2\n";
			$htaccess_content .= "<IfModule !mod_authz_core.c>\n";
			$htaccess_content .= "    Order deny,allow\n";
			$htaccess_content .= "    Deny from all\n";
			$htaccess_content .= "</IfModule>\n";

			file_put_contents( $htaccess_path, $htaccess_content );
		}
	}

	/**
	 * Create index.php file to prevent directory listing.
	 *
	 * @param string $directory Directory path.
	 */
	private function create_index_file( $directory ) {
		$index_path = trailingslashit( $directory ) . 'index.php';

		// Only create if it doesn't exist.
		if ( ! file_exists( $index_path ) ) {
			file_put_contents( $index_path, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Schedule cleanup if needed (throttled to avoid running on every write).
	 */
	private function schedule_cleanup_if_needed() {
		$cleanup_interval = 3600; // Run cleanup at most once per hour.
		$current_time     = time();

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
		$keep_files = $this->get_setting( 'keep_files', 30 );
		/** @var string $rotation */
		$rotation = $this->get_setting( 'rotation_frequency', 'daily' );

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
	 * @param string $channel_slug The channel slug to clean up for.
	 */
	public function handle_async_cleanup( $channel_slug ) {
		// Only process if this is our channel.
		if ( $channel_slug !== $this->get_slug() ) {
			return;
		}

		$this->cleanup_old_files();
	}

	/**
	 * Get statistics about log files in the log directory.
	 *
	 * @return array{count: int, oldest: int|null, newest: int|null, total_size: int} File statistics.
	 */
	private function get_log_files_stats(): array {
		$stats = [
			'count'      => 0,
			'oldest'     => null,
			'newest'     => null,
			'total_size' => 0,
		];

		$log_dir = $this->get_log_directory_path();

		if ( ! is_dir( $log_dir ) ) {
			return $stats;
		}

		// Get all log files (any rotation pattern).
		$log_files = glob( trailingslashit( $log_dir ) . 'events-*.log*' );

		if ( empty( $log_files ) ) {
			return $stats;
		}

		$stats['count'] = count( $log_files );

		// Get modification times for all files.
		$file_times = [];
		foreach ( $log_files as $file ) {
			$mtime                = filemtime( $file );
			$file_times[ $file ]  = $mtime;
			$stats['total_size'] += filesize( $file );
		}

		// Find oldest and newest by modification time.
		$stats['oldest'] = min( $file_times );
		$stats['newest'] = max( $file_times );

		return $stats;
	}
}
