<?php
namespace Simple_History\Loggers;

use Simple_History\Helpers;

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
		$arr_info = array(
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

		return $arr_info;
	}

	/**
	 * Called when logger is loaded
	 */
	public function loaded() {
		add_action( 'admin_init', array( $this, 'on_load_theme_editor' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'on_load_plugin_editor' ), 10, 1 );
	}

	/**
	 * Called when /wp/wp-admin/plugin-editor.php is loaded
	 * Both using regular GET and during POST with updated file data
	 *
	 * todo:
	 * - log edits
	 * - log failed edits that result in error and plugin deactivation
	 */
	public function on_load_plugin_editor() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['plugin'] ) && isset( $_POST['action'] ) && $_POST['action'] === 'edit-theme-plugin-file' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$file = wp_unslash( $_POST['file'] ?? null );
			$file = sanitize_file_name( $file );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$plugin_file = wp_unslash( $_POST['plugin'] ?? null );
			$plugin_file = sanitize_file_name( $plugin_file );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$fileNewContents = isset( $_POST['newcontent'] ) ? wp_unslash( $_POST['newcontent'] ) : null;

			// if 'phperror' is set then there was an error and an edit is done and wp tries to activate the plugin again
			// $phperror = isset($_POST["phperror"]) ? $_POST["phperror"] : null;
			// Get info about the edited plugin.
			$pluginInfo    = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file );
			$pluginName    = $pluginInfo['Name'] ?? null;
			$pluginVersion = $pluginInfo['Version'] ?? null;

			$file_full_path = WP_PLUGIN_DIR . '/' . $file;

			// Check if file exists and bail if not.
			if ( ! file_exists( $file_full_path ) ) {
				return;
			}

			// Get contents before save.
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- This is a known local file.
			$fileContentsBeforeEdit = file_get_contents( $file_full_path );

			$context = array(
				'file_name'         => $plugin_file,
				'plugin_name'       => $pluginName,
				'plugin_version'    => $pluginVersion,
				'old_file_contents' => $fileContentsBeforeEdit,
				'new_file_contents' => $fileNewContents,
				'_occasionsID'      => self::class . '/' . __FUNCTION__ . "/file-edit/$plugin_file/$file",
			);

			$this->info_message( 'plugin_file_edited', $context );
		}
	}

	/**
	 * Called when /wp/wp-admin/theme-editor.php is loaded
	 * Both using regular GET and during POST with updated file data
	 *
	 * When this action is fired we don't know if a file will be successfully saved or not.
	 * There are no filters/actions fired when the edit is saved. On the end wp_redirect() is
	 * called however and we know the location for the redirect and wp_redirect() has filters
	 * so we hook onto that to save the edit.
	 */
	public function on_load_theme_editor() {

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only continue if method is post and action is update.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['theme'] ) && isset( $_POST['action'] ) && $_POST['action'] === 'edit-theme-plugin-file' ) {
			/**
			 * POST data is like
			 *  array(8)
			 *      '_wpnonce' => string(10) "9b5e46634f"
			 *      '_wp_http_referer' => string(88) "/wp/wp-admin/theme-editor.php?file=style.css&theme=twentyfifteen&scrollto=0&upda…"
			 *      'newcontent' => string(104366) "/* Theme Name: Twenty Fifteen Theme URI: https://wordpress.org/themes/twentyfift…"
			 *      'action' => string(6) "edit-theme-plugin-file"
			 *      'file' => string(9) "style.css"
			 *      'theme' => string(13) "twentyfifteen"
			 *      'scrollto' => string(3) "638"
			 *      'submit' => string(11) "Update File"
			 */

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$file = wp_unslash( $_POST['file'] ?? null );
			$file = sanitize_file_name( $file );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$theme = wp_unslash( $_POST['theme'] ?? null );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$fileNewContents = isset( $_POST['newcontent'] ) ? wp_unslash( $_POST['newcontent'] ) : null;

			// Same code as in theme-editor.php.
			if ( $theme ) {
				$stylesheet = $theme;
			} else {
				$stylesheet = get_stylesheet();
			}

			$theme = wp_get_theme( $stylesheet );

			if ( ! is_a( $theme, 'WP_Theme' ) ) {
				return;
			}

			// Same code as in theme-editor.php.
			$relative_file = $file;
			$file          = $theme->get_stylesheet_directory() . '/' . $relative_file;

			// Get file contents, so we have something to compare with later.
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- This is a known local file.
			$fileContentsBeforeEdit = file_get_contents( $file );

			$context = array(
				'theme_name'            => $theme->name,
				'theme_stylesheet_path' => $theme->get_stylesheet(),
				'theme_stylesheet_dir'  => $theme->get_stylesheet_directory(),
				'file_name'             => $relative_file,
				'file_dir'              => $file,
				'old_file_contents'     => $fileContentsBeforeEdit,
				'new_file_contents'     => $fileNewContents,
				'_occasionsID'          => self::class . '/' . __FUNCTION__ . "/file-edit/$file",
			);

			$this->info_message( 'theme_file_edited', $context );
		}
	}

	/**
	 * Get output for row details
	 *
	 * @param object $row Log row.
	 * @return string HTML
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'] ?? null;

		if ( ! $message_key ) {
			return;
		}

		$out = '';

		$diff_table_output = '';

		if ( ! empty( $context['new_file_contents'] ) && ! empty( $context['old_file_contents'] ) && $context['new_file_contents'] !== $context['old_file_contents'] ) {
			$diff_table_output .= sprintf(
				'<tr><td>%1$s</td><td>%2$s</td></tr>',
				__( 'File contents', 'simple-history' ),
				helpers::text_diff( $context['old_file_contents'], $context['new_file_contents'] )
			);
		}

		if ( $diff_table_output !== '' ) {
			$diff_table_output = '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
		}

		$out .= $diff_table_output;

		return $out;
	}
}
