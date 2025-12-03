<?php

namespace Simple_History\Loggers;

/**
 * Logs attachments updated with the great Enable Media Replace plugin
 * Plugin URL: https://wordpress.org/plugins/enable-media-replace/
 *
 * @since 2.2
 */
class Plugin_Enable_Media_Replace_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'PluginEnableMediaReplaceLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {

		$arr_info = array(
			'name'        => _x( 'Plugin: Enable Media Replace Logger', 'PluginEnableMediaReplaceLogger', 'simple-history' ),
			'description' => _x( 'Logs media updates made with the Enable Media Replace Plugin', 'PluginEnableMediaReplaceLogger', 'simple-history' ),
			'name_via'    => _x( 'Using plugin Enable Media Replace', 'PluginUserSwitchingLogger', 'simple-history' ),
			'capability'  => 'upload_files',
			'messages'    => array(
				'replaced_file' => _x( 'Replaced attachment "{prev_attachment_title}" with new attachment "{new_attachment_title}"', 'PluginEnableMediaReplaceLogger', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * @inheritdoc
	 */
	public function loaded() {

		// Action that is called when Enable Media Replace loads it's admin options page (both when viewing and when posting new file to it).
		add_action( 'load-media_page_enable-media-replace/enable-media-replace', array( $this, 'on_load_plugin_admin_page' ), 10, 1 );
	}

	/**
	 * Called when Enable Media Replace loads it's admin options page
	 */
	public function on_load_plugin_admin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'media_replace_upload' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$attachment_id = empty( $_POST['ID'] ) ? null : (int) $_POST['ID'];

			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$replace_type = empty( $_POST['replace_type'] ) ? null : sanitize_text_field( wp_unslash( $_POST['replace_type'] ) );

			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_file = empty( $_FILES['userfile'] ) ? null : (array) $_FILES['userfile'];

			$prev_attachment_post = get_post( $attachment_id );

			if ( empty( $attachment_id ) || empty( $new_file ) || empty( $prev_attachment_post ) ) {
				return;
			}

			$this->info_message(
				'replaced_file',
				array(
					'attachment_id'         => $attachment_id,
					'prev_attachment_title' => get_the_title( $prev_attachment_post ),
					'new_attachment_title'  => $new_file['name'],
					'new_attachment_type'   => $new_file['type'],
					'new_attachment_size'   => $new_file['size'],
					'replace_type'          => $replace_type,
				)
			);
		}
	}
}
