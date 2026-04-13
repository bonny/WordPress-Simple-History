<?php
namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Diff_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logs edits to theme or plugin files done from Appearance -> Editor or Plugins -> Editor
 */
class File_Edits_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'FileEditsLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array<string, mixed>
	 */
	public function get_info() {
		return array(
			'name'        => _x( 'File edits Logger', 'Logger: FileEditsLogger', 'simple-history' ),
			'description' => __( 'Logs edits to theme and plugin files', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'theme_file_edited'  => __( 'Edited file "{file_name}" in theme "{theme_name}"', 'simple-history' ),
				'plugin_file_edited' => __( 'Edited file "{file_name}" in plugin "{plugin_name}"', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Edited theme and plugin files', 'Plugin logger: file edits', 'simple-history' ),
					'label_all' => _x( 'All file edits', 'Plugin logger: file edits', 'simple-history' ),
					'options'   => array(
						_x( 'Edited theme files', 'Plugin logger: file edits', 'simple-history' ) => array(
							'theme_file_edited',
						),
						_x( 'Edited plugin files', 'Plugin logger: file edits', 'simple-history' ) => array(
							'plugin_file_edited',
						),
					),
				),
			),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		// Capture old file contents before WordPress saves the edit.
		// Priority 0 runs before WP's handler at priority 1.
		add_action( 'wp_ajax_edit-theme-plugin-file', array( $this, 'on_file_edit_ajax' ), 0 );
	}

	/**
	 * Capture file contents before the AJAX edit handler saves.
	 *
	 * Runs at priority 0 on wp_ajax_edit-theme-plugin-file,
	 * before WordPress's own handler which writes the file.
	 */
	public function on_file_edit_ajax() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = isset( $_POST['file'] ) ? wp_unslash( $_POST['file'] ) : '';

		// Validate file path (same check WordPress core uses).
		if ( ! $file || 0 !== validate_file( $file ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_contents = isset( $_POST['newcontent'] ) ? wp_unslash( $_POST['newcontent'] ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$plugin = isset( $_POST['plugin'] ) ? wp_unslash( $_POST['plugin'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$theme = isset( $_POST['theme'] ) ? wp_unslash( $_POST['theme'] ) : '';

		if ( $plugin ) {
			$this->capture_plugin_file_edit( $file, $plugin, $new_contents );
		} elseif ( $theme ) {
			$this->capture_theme_file_edit( $file, $theme, $new_contents );
		}
	}

	/**
	 * Capture and log a plugin file edit.
	 *
	 * @param string $file Relative file name.
	 * @param string $plugin Plugin file path.
	 * @param string $new_contents New file contents.
	 */
	private function capture_plugin_file_edit( $file, $plugin, $new_contents ) {
		$file_full_path = WP_PLUGIN_DIR . '/' . $file;

		$plugin_info = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin );

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$old_contents = file_get_contents( $file_full_path );

		if ( $old_contents === false || $old_contents === $new_contents ) {
			return;
		}

		$this->info_message(
			'plugin_file_edited',
			array(
				'file_name'         => $file,
				'plugin_name'       => $plugin_info['Name'] ?? '',
				'plugin_version'    => $plugin_info['Version'] ?? '',
				'old_file_contents' => $old_contents,
				'new_file_contents' => $new_contents,
				'_occasionsID'      => self::class . "/file-edit/plugin/$plugin/$file",
			)
		);
	}

	/**
	 * Capture and log a theme file edit.
	 *
	 * @param string $file Relative file name.
	 * @param string $stylesheet Theme stylesheet slug.
	 * @param string $new_contents New file contents.
	 */
	private function capture_theme_file_edit( $file, $stylesheet, $new_contents ) {
		$theme = wp_get_theme( $stylesheet );

		if ( ! $theme->exists() ) {
			return;
		}

		$file_full_path = $theme->get_stylesheet_directory() . '/' . $file;

		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$old_contents = file_get_contents( $file_full_path );

		if ( $old_contents === false || $old_contents === $new_contents ) {
			return;
		}

		$this->info_message(
			'theme_file_edited',
			array(
				'theme_name'            => $theme->name,
				'theme_stylesheet_path' => $theme->get_stylesheet(),
				'theme_stylesheet_dir'  => $theme->get_stylesheet_directory(),
				'file_name'             => $file,
				'file_dir'              => $file_full_path,
				'old_file_contents'     => $old_contents,
				'new_file_contents'     => $new_contents,
				'_occasionsID'          => self::class . "/file-edit/theme/$stylesheet/$file",
			)
		);
	}

	/**
	 * Get output for row details
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|string
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'] ?? null;

		if ( ! $message_key ) {
			return '';
		}

		$group = ( new Event_Details_Group() )
			->set_formatter( new Event_Details_Group_Diff_Table_Formatter() )
			->add_items(
				[
					new Event_Details_Item(
						[ 'new_file_contents', 'old_file_contents' ],
						__( 'File contents', 'simple-history' ),
					),
				]
			);

		return $group;
	}
}
