<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs attachments updated with the great Enable Media Replace plugin
 * Plugin URL: https://wordpress.org/plugins/enable-media-replace/
 *
 * @since 2.2
 */
class PluginEnableMediaReplaceLogger extends SimpleLogger {

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(
			"name" => _x("Enable Media Replace Logger", "PluginEnableMediaReplaceLogger", "simple-history"),
			"description" => _x("Logs media updates made with the Enable Media Replace Plugin", "PluginEnableMediaReplaceLogger", "simple-history"),
			"name_via" => _x("Using plugin Enable Media Replace", "PluginUserSwitchingLogger", "simple-history"),
			"capability" => "upload_files",
			"messages" => array(
				'replaced_file' => _x('Replaced attachment "{prev_attachment_title}" with new attachment "{new_attachment_title}"', "PluginEnableMediaReplaceLogger", "simple-history"),
			),
		);

		return $arr_info;

	}

	function loaded() {

		// Action that is called when Enable Media Replace loads it's admin options page (both when viewing and when posting new file to it)
		add_action( 'load-media_page_enable-media-replace/enable-media-replace', array( $this, "on_load_plugin_admin_page" ), 10, 1 );
	}

	function on_load_plugin_admin_page() {

		if ( empty( $_POST ) ) {
			return;
		}

		if ( isset( $_GET["action"] ) && $_GET["action"] == "media_replace_upload" ) {

			$attachment_id = empty( $_POST["ID"] ) ? null : (int) $_POST["ID"];
			$replace_type = empty( $_POST["replace_type"] ) ? null : sanitize_text_field( $_POST["replace_type"] );
			$new_file = empty( $_FILES["userfile"] ) ? null : (array) $_FILES["userfile"];

			$prev_attachment_post = get_post( $attachment_id );

			if ( empty( $attachment_id ) || empty( $new_file ) || empty( $prev_attachment_post ) ) {
				return;
			}

			/*
			get	{
			    "page": "enable-media-replace\/enable-media-replace.php",
			    "noheader": "true",
			    "action": "media_replace_upload",
			    "attachment_id": "64085",
			    "_wpnonce": "1089573e0c"
			}

			post	{
			    "ID": "64085",
			    "replace_type": "replace"
			}

			files	{
			    "userfile": {
			        "name": "earth-transparent.png",
			        "type": "image\/png",
			        "tmp_name": "\/Applications\/MAMP\/tmp\/php\/phpKA2XOo",
			        "error": 0,
			        "size": 4325729
			    }
			}
			*/

			$this->infoMessage("replaced_file", array(
				"attachment_id" => $attachment_id,
				"prev_attachment_title" => get_the_title( $prev_attachment_post ),
				"new_attachment_title" => $new_file["name"],
				"new_attachment_type" => $new_file["type"],
				"new_attachment_size" => $new_file["size"],
				"replace_type" => $replace_type,
				/*
				"get" => $_GET,
				"post" => $_POST,
				"files" => $_FILES,
				"old_attachment_post" => $prev_attachment_post,
				"old_attachment_meta" => $prev_attachment_meta
				*/
			));

		}

	}

}
