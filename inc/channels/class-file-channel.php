<?php

namespace Simple_History\Channels;

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
	 * Delay before running cleanup after a log write (in seconds).
	 * Daily is sufficient since files only rotate daily/weekly/monthly.
	 */
	private const CLEANUP_DELAY_SECONDS = DAY_IN_SECONDS;

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
	 * Called when the channel is loaded and ready.
	 *
	 * Registers hooks for async cleanup processing.
	 */
	public function loaded() {
		add_action( 'simple_history_cleanup_log_files', [ $this, 'handle_async_cleanup' ] );
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
		return __( 'Local Files', 'simple-history' );
	}

	/**
	 * Get the description for this channel.
	 *
	 * @return string The channel description.
	 */
	public function get_description() {
		return __( 'Write events to log files on this server for backup or import into analysis tools.', 'simple-history' );
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
			return false;
		}

		// Ensure directory exists.
		$log_dir = dirname( $log_file );
		if ( ! $this->ensure_directory_exists( $log_dir ) ) {
			return false;
		}

		// Format the log entry using configured formatter.
		$formatter = $this->get_formatter();
		$log_entry = $formatter->format( $event_data, $formatted_message );

		// Write to file.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Direct file operations required for log file channel.
		$result = file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );

		if ( false === $result ) {
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

		// Output format field.
		add_settings_field(
			$option_name . '_formatter',
			Helpers::get_settings_field_title_output( __( 'Output format', 'simple-history' ) ),
			[ $this, 'settings_field_formatter' ],
			$settings_page_slug,
			$settings_section_id
		);

		// File management field (rotation + retention combined).
		add_settings_field(
			$option_name . '_file_management',
			Helpers::get_settings_field_title_output( __( 'File management', 'simple-history' ) ),
			[ $this, 'settings_field_file_management' ],
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
	 * Render the "Enabled" settings field with a descriptive label.
	 *
	 * Overrides parent to provide a more descriptive checkbox label.
	 */
	public function settings_field_enabled() {
		$enabled     = $this->is_enabled();
		$option_name = $this->get_settings_option_name();
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( $option_name ); ?>[enabled]"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Enable file logging', 'simple-history' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the combined file management settings field.
	 * Combines rotation frequency and file retention into a natural sentence.
	 */
	public function settings_field_file_management() {
		$option_name      = $this->get_settings_option_name();
		$rotation_value   = $this->get_setting( 'rotation_frequency', 'daily' );
		$keep_files_value = $this->get_setting( 'keep_files', 30 );

		$rotation_options = [
			'daily'   => __( 'daily', 'simple-history' ),
			'weekly'  => __( 'weekly', 'simple-history' ),
			'monthly' => __( 'monthly', 'simple-history' ),
		];
		?>
		<label class="sh-FileChannel-fileManagement">
			<?php esc_html_e( 'Create a new file', 'simple-history' ); ?>

			<select name="<?php echo esc_attr( $option_name ); ?>[rotation_frequency]">
				<?php foreach ( $rotation_options as $value => $label ) { ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $rotation_value, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php } ?>
			</select>

			<?php esc_html_e( 'and keep the last', 'simple-history' ); ?>

			<input
				type="number"
				name="<?php echo esc_attr( $option_name ); ?>[keep_files]"
				value="<?php echo esc_attr( $keep_files_value ); ?>"
				min="1"
				max="365"
				class="small-text"
			/>

			<?php esc_html_e( 'files', 'simple-history' ); ?>
		</label>
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
		<fieldset class="sh-RadioOptions">
			<?php
			foreach ( $formatters as $formatter_slug => $formatter ) {
				?>
				<label class="sh-RadioOption">
					<input
						type="radio"
						name="<?php echo esc_attr( $option_name ); ?>[formatter]"
						value="<?php echo esc_attr( $formatter_slug ); ?>"
						<?php checked( $selected_formatted_slug, $formatter_slug ); ?>
					/>

					<?php echo esc_html( $formatter->get_name() ); ?>

					<span class="sh-RadioOptionDescription description">
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
						__( 'JSON Lines, Logfmt, and Syslog formats', 'simple-history' ),
						__( 'Compatible with Graylog, Splunk, Grafana Loki, and more', 'simple-history' ),
						__( 'Machine-readable for easy parsing and analysis', 'simple-history' ),
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
				'description' => __( 'One JSON object per line. Best for Graylog, ELK, Splunk, and log aggregation tools.', 'simple-history' ),
			],
			'logfmt'     => [
				'name'        => __( 'Logfmt', 'simple-history' ),
				'description' => __( 'Key=value pairs. Best for Grafana Loki, Prometheus, and cloud-native log systems.', 'simple-history' ),
			],
			'rfc5424'    => [
				'name'        => __( 'RFC 5424 Syslog', 'simple-history' ),
				'description' => __( 'Standard syslog format with structured data. Best for syslog servers and SIEM tools.', 'simple-history' ),
			],
		];

		foreach ( $premium_formatters as $formatter ) {
			?>
			<label class="sh-RadioOption sh-RadioOption--disabled">
				<input
					type="radio"
					disabled
				/>

				<?php echo esc_html( $formatter['name'] ); ?>

				<span class="sh-RadioOptionDescription description">
					<?php echo esc_html( $formatter['description'] ); ?>
				</span>
			</label>
			<?php
		}
	}


	/**
	 * Test folder writability and attempt to create if needed.
	 *
	 * Returns an array with status information about the folder:
	 * - is_writable: bool - Whether the folder exists and is writable
	 * - creation_failed: bool - Whether creation was attempted but failed
	 *
	 * @param string $directory The directory path to test.
	 * @return array{is_writable: bool, creation_failed: bool}
	 */
	private function test_folder_writability( $directory ) {
		$existed_before = is_dir( $directory );
		$is_writable    = $this->ensure_directory_exists( $directory );

		return [
			'is_writable'     => $is_writable,
			'creation_failed' => ! $existed_before && ! $is_writable,
		];
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

		// Get stats for display.
		$stats = $is_writable ? $this->get_log_files_stats() : null;
		?>
		<div class="sh-FileChannel-folderInfo">
			<?php // Folder path. ?>
			<code class="sh-FileChannel-folderPath"><?php echo esc_html( $log_directory ); ?></code>

			<?php // Status line with icon. ?>
			<p class="sh-FileChannel-folderStatus">
				<?php if ( $creation_failed ) { ?>
					<span class="sh-FileChannel-folderStatus--error">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Folder could not be created. Check that the parent directory is writable.', 'simple-history' ); ?>
					</span>
				<?php } elseif ( ! $is_writable ) { ?>
					<span class="sh-FileChannel-folderStatus--error">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Folder exists but is not writable. Check folder permissions.', 'simple-history' ); ?>
					</span>
				<?php } else { ?>
					<span class="sh-FileChannel-folderStatus--success">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Writable', 'simple-history' ); ?>
					</span>
					<?php if ( $stats && $stats['count'] > 0 ) { ?>
						<span class="sh-FileChannel-folderStats">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: number of files, 2: total size */
									_n( '%1$s file', '%1$s files', $stats['count'], 'simple-history' ),
									number_format_i18n( $stats['count'] )
								)
							);
							?>
							&middot;
							<?php echo esc_html( size_format( $stats['total_size'] ) ); ?>
							<?php if ( $stats['oldest'] && $stats['newest'] ) { ?>
								&middot;
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: start date, 2: end date */
										__( '%1$s – %2$s', 'simple-history' ),
										wp_date( 'M j', $stats['oldest'] ),
										wp_date( 'M j, Y', $stats['newest'] )
									)
								);
								?>
							<?php } ?>
						</span>
					<?php } ?>
				<?php } ?>
			</p>

			<?php // Security note (inline, discrete). ?>
			<p class="sh-FileChannel-securityNote">
				<?php if ( $test_url ) { ?>
					<?php esc_html_e( 'Folder is public.', 'simple-history' ); ?>
					<a href="<?php echo esc_url( $test_url ); ?>" target="_blank" class="sh-ExternalLink">
						<?php esc_html_e( 'Verify access is blocked', 'simple-history' ); ?>
					</a>
				<?php } else { ?>
					<?php esc_html_e( 'Folder is outside the public web directory.', 'simple-history' ); ?>
				<?php } ?>
			</p>
		</div>
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
	 * Get the log file path based on current settings.
	 *
	 * @return string|false Log file path or false on error.
	 */
	private function get_log_file_path() {
		$log_dir  = $this->get_log_directory_path();
		$rotation = $this->get_setting( 'rotation_frequency', 'daily' );
		$filename = $this->get_log_filename( $rotation );

		if ( ! $filename ) {
			return false;
		}

		return $log_dir . $filename;
	}

	/**
	 * Get the log directory path.
	 *
	 * Uses a hard-to-guess directory within the uploads folder for security
	 * and VIP compatibility. Returns path with trailing slash.
	 *
	 * @return string Log directory path with trailing slash.
	 */
	public function get_log_directory_path() {
		// Use a random token for directory name (stored in settings for stability).
		$folder_token = $this->get_folder_token();

		// Use uploads directory for VIP compatibility.
		$upload_dir        = wp_get_upload_dir();
		$default_directory = trailingslashit( $upload_dir['basedir'] ) . 'simple-history-logs-' . $folder_token;

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
		$token = apply_filters( 'simple_history/file_channel/folder_token', $token );

		// Sanitize for safe folder naming in case wp_generate_password() is
		// overridden or filter returns unexpected characters.
		$token = sanitize_file_name( $token );

		return $token;
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
	 * Ensure a directory exists and is writable.
	 *
	 * Creates security files (.htaccess and index.php) when creating a new directory.
	 *
	 * @param string $directory Directory path.
	 * @return bool True if directory exists and is writable.
	 */
	private function ensure_directory_exists( $directory ) {
		if ( is_dir( $directory ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_is_writable
			return is_writable( $directory );
		}

		// Try to create the directory.
		if ( ! wp_mkdir_p( $directory ) ) {
			return false;
		}

		// Set appropriate permissions.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.chmod_chmod
		chmod( $directory, 0755 );

		// Create security files.
		$this->create_htaccess_file( $directory );
		$this->create_index_file( $directory );

		return true;
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

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Direct file operations required for log directory protection.
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
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Direct file operations required for log directory protection.
			file_put_contents( $index_path, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Schedule cleanup if not already scheduled.
	 */
	private function schedule_cleanup_if_needed() {
		$hook = 'simple_history_cleanup_log_files';
		$args = [ $this->get_slug() ];

		if ( wp_next_scheduled( $hook, $args ) ) {
			return;
		}

		wp_schedule_single_event( time() + self::CLEANUP_DELAY_SECONDS, $hook, $args );
	}

	/**
	 * Clean up old log files based on current rotation frequency and keep settings.
	 *
	 * Only removes files that match the current rotation pattern.
	 */
	private function cleanup_old_files() {
		$keep_files = $this->get_setting( 'keep_files', 30 );
		$rotation   = $this->get_setting( 'rotation_frequency', 'daily' );
		$log_dir    = $this->get_log_directory_path();

		if ( ! is_dir( $log_dir ) ) {
			return;
		}

		// Get files that match the current rotation pattern only.
		$pattern   = $this->get_cleanup_pattern( $rotation );
		$log_files = glob( $log_dir . $pattern );

		// glob() returns false on error, not an empty array.
		if ( ! is_array( $log_files ) || count( $log_files ) <= $keep_files ) {
			return;
		}

		// Sort by filename (which contains date) for consistent ordering.
		sort( $log_files );

		// Delete oldest files, keeping the most recent ones.
		$files_to_delete = array_slice( $log_files, 0, count( $log_files ) - $keep_files );

		foreach ( $files_to_delete as $file ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Direct file operations required for log file rotation cleanup.
			unlink( $file );
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
		$log_files = glob( $log_dir . 'events-*.log*' );

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
