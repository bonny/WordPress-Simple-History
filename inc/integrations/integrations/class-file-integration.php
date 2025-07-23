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
		$this->name = __( 'File Backup', 'simple-history' );
		$this->description = __( 'Automatically save all events to log files. Never lose your history even if the database is compromised.', 'simple-history' );
		$this->supports_async = false; // File writing is fast, no need for async.

		parent::__construct();
	}

	/**
	 * Send an event to this integration.
	 *
	 * @param array $event_data The event data to send.
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
				'name' => 'log_format',
				'title' => __( 'Log Format', 'simple-history' ),
				'description' => __( 'Choose the format for log entries.', 'simple-history' ),
				'options' => [
					'simple' => __( 'Simple (Human readable)', 'simple-history' ),
					'json' => __( 'JSON (Machine readable)', 'simple-history' ),
					'csv' => __( 'CSV (Spreadsheet compatible)', 'simple-history' ),
				],
				'default' => 'simple',
			],
			[
				'type' => 'select',
				'name' => 'rotation_frequency',
				'title' => __( 'File Rotation', 'simple-history' ),
				'description' => __( 'How often to create new log files.', 'simple-history' ),
				'options' => [
					'daily' => __( 'Daily', 'simple-history' ),
					'weekly' => __( 'Weekly', 'simple-history' ),
					'monthly' => __( 'Monthly', 'simple-history' ),
					'never' => __( 'Never (single file)', 'simple-history' ),
				],
				'default' => 'daily',
			],
			[
				'type' => 'text',
				'name' => 'log_directory',
				'title' => __( 'Log Directory', 'simple-history' ),
				'description' => __( 'Directory to store log files. Leave empty for default location.', 'simple-history' ),
				'placeholder' => $this->get_default_log_directory(),
			],
			[
				'type' => 'number',
				'name' => 'max_file_size',
				'title' => __( 'Max File Size (MB)', 'simple-history' ),
				'description' => __( 'Maximum size for log files before rotation. Set to 0 for no limit.', 'simple-history' ),
				'default' => 10,
				'min' => 0,
				'max' => 1000,
			],
			[
				'type' => 'number',
				'name' => 'keep_files',
				'title' => __( 'Keep Files', 'simple-history' ),
				'description' => __( 'Number of old log files to keep. Set to 0 to keep all files.', 'simple-history' ),
				'default' => 30,
				'min' => 0,
				'max' => 365,
			],
		];

		return array_merge( $base_fields, $file_fields );
	}

	/**
	 * Test the integration connection/configuration.
	 *
	 * @return array Array with 'success' boolean and 'message' string.
	 */
	public function test_connection() {
		$settings = $this->get_settings();
		$log_file = $this->get_log_file_path( $settings );

		if ( ! $log_file ) {
			return [
				'success' => false,
				'message' => __( 'Could not determine log file path.', 'simple-history' ),
			];
		}

		$log_dir = dirname( $log_file );
		
		// Check if directory exists or can be created.
		if ( ! $this->ensure_directory_exists( $log_dir ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: Directory path */
					__( 'Cannot create or write to log directory: %s', 'simple-history' ),
					$log_dir
				),
			];
		}

		// Test write permissions.
		$test_content = sprintf(
			"[%s] TEST: Simple History file integration test\n",
			current_time( 'Y-m-d H:i:s' )
		);

		if ( ! $this->write_to_file( $log_file, $test_content ) ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %s: File path */
					__( 'Cannot write to log file: %s', 'simple-history' ),
					$log_file
				),
			];
		}

		return [
			'success' => true,
			'message' => sprintf(
				/* translators: %s: File path */
				__( 'Successfully tested writing to log file: %s', 'simple-history' ),
				$log_file
			),
		];
	}

	/**
	 * Get the log file path based on settings.
	 *
	 * @param array $settings Integration settings.
	 * @return string|false Log file path or false on error.
	 */
	private function get_log_file_path( $settings ) {
		$log_dir = ! empty( $settings['log_directory'] ) ? 
			$settings['log_directory'] : 
			$this->get_default_log_directory();

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
	 * @return string Default log directory path.
	 */
	private function get_default_log_directory() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'simple-history-logs';
	}

	/**
	 * Get the log filename based on rotation frequency.
	 *
	 * @param string $rotation Rotation frequency.
	 * @return string|false Log filename or false on error.
	 */
	private function get_log_filename( $rotation ) {
		$base_name = 'simple-history';
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
	 * Format a log entry based on settings.
	 *
	 * @param array $event_data The event data.
	 * @param string $formatted_message The formatted message.
	 * @param array $settings Integration settings.
	 * @return string Formatted log entry.
	 */
	private function format_log_entry( $event_data, $formatted_message, $settings ) {
		$format = $settings['log_format'] ?? 'simple';
		$timestamp = current_time( 'Y-m-d H:i:s' );

		switch ( $format ) {
			case 'json':
				$entry_data = [
					'timestamp' => $timestamp,
					'level' => $event_data['level'] ?? 'info',
					'logger' => $event_data['logger'] ?? '',
					'message' => $formatted_message,
					'initiator' => $event_data['initiator'] ?? '',
					'context' => $event_data['context'] ?? [],
				];
				return wp_json_encode( $entry_data ) . "\n";

			case 'csv':
				$csv_data = [
					$timestamp,
					$event_data['level'] ?? 'info',
					$event_data['logger'] ?? '',
					str_replace( '"', '""', $formatted_message ), // Escape quotes for CSV.
					$event_data['initiator'] ?? '',
				];
				return '"' . implode( '","', $csv_data ) . "\"\n";

			case 'simple':
			default:
				return sprintf(
					"[%s] %s %s: %s (via %s)\n",
					$timestamp,
					strtoupper( $event_data['level'] ?? 'info' ),
					$event_data['logger'] ?? 'Unknown',
					$formatted_message,
					$event_data['initiator'] ?? 'unknown'
				);
		}
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
			
			// Create .htaccess to prevent direct access.
			$htaccess_content = "Order deny,allow\nDeny from all\n";
			file_put_contents( trailingslashit( $directory ) . '.htaccess', $htaccess_content );
			
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
		// Check file size limits before writing.
		$settings = $this->get_settings();
		$max_size = ( $settings['max_file_size'] ?? 10 ) * 1024 * 1024; // Convert MB to bytes.
		
		if ( $max_size > 0 && file_exists( $file_path ) && filesize( $file_path ) >= $max_size ) {
			$this->rotate_log_file( $file_path );
		}

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
	 * Rotate a log file when it gets too large.
	 *
	 * @param string $file_path Path to the log file to rotate.
	 */
	private function rotate_log_file( $file_path ) {
		$rotated_path = $file_path . '.' . time();
		
		if ( rename( $file_path, $rotated_path ) ) {
			$this->log_debug( 'Rotated log file: ' . $file_path . ' to ' . $rotated_path );
		} else {
			$this->log_error( 'Failed to rotate log file: ' . $file_path );
		}
	}

	/**
	 * Clean up old log files based on settings.
	 */
	private function cleanup_old_files() {
		$settings = $this->get_settings();
		$keep_files = $settings['keep_files'] ?? 30;
		
		if ( $keep_files <= 0 ) {
			return; // Keep all files.
		}

		$log_dir = ! empty( $settings['log_directory'] ) ? 
			$settings['log_directory'] : 
			$this->get_default_log_directory();

		if ( ! is_dir( $log_dir ) ) {
			return;
		}

		// Get all log files.
		$log_files = glob( trailingslashit( $log_dir ) . 'simple-history*.log*' );
		
		if ( empty( $log_files ) || count( $log_files ) <= $keep_files ) {
			return; // Not enough files to clean up.
		}

		// Sort by modification time (oldest first).
		usort( $log_files, function( $a, $b ) {
			return filemtime( $a ) - filemtime( $b );
		} );

		// Delete oldest files.
		$files_to_delete = array_slice( $log_files, 0, count( $log_files ) - $keep_files );
		
		foreach ( $files_to_delete as $file ) {
			if ( unlink( $file ) ) {
				$this->log_debug( 'Deleted old log file: ' . $file );
			} else {
				$this->log_error( 'Failed to delete old log file: ' . $file );
			}
		}
	}
}