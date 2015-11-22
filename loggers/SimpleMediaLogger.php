<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs media uploads
 */
class SimpleMediaLogger extends SimpleLogger
{

	public $slug = "SimpleMediaLogger";

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(
			"name" => "Media/Attachments Logger",
			"description" => "Logs media uploads and edits",
			"capability" => "edit_pages",
			"messages" => array(
				'attachment_created' => __('Created {post_type} "{attachment_title}"', 'simple-history'),
				'attachment_updated' => __('Edited {post_type} "{attachment_title}"', 'simple-history'),
				'attachment_deleted' => __('Deleted {post_type} "{attachment_title}" ("{attachment_filename}")', 'simple-history')
			),
			"labels" => array(
				"search" => array(
					"label" => _x("Media", "Media logger: search", "simple-history"),
					"label_all" => _x("All media activity", "Media logger: search", "simple-history"),
					"options" => array(
						_x("Added media", "Media logger: search", "simple-history") => array(
							"attachment_created"
						),
						_x("Updated media", "Media logger: search", "simple-history") => array(
							"attachment_updated"
						),
						_x("Deleted media", "Media logger: search", "simple-history") => array(
							"attachment_deleted"
						),
					)
				) // end search array
			) // end labels
		);

		return $arr_info;

	}

	public function loaded() {

		add_action("admin_init", array($this, "on_admin_init"));

		add_action("xmlrpc_call_success_mw_newMediaObject", array($this, "on_mw_newMediaObject"), 10, 2);

		add_filter("simple_history/rss_item_link", array($this, "filter_rss_item_link"), 10, 2);

	}

	function on_admin_init() {

		add_action("add_attachment", array($this, "on_add_attachment"));
		add_action("edit_attachment", array($this, "on_edit_attachment"));
		add_action("delete_attachment", array($this, "on_delete_attachment"));

	}

	/**
	 * Filter that fires after a new attachment has been added via the XML-RPC MovableType API.
	 *
	 * @since 2.0.21
	 *
	 * @param int   $id   ID of the new attachment.
	 * @param array $args An array of arguments to add the attachment.
	 */
	function on_mw_newMediaObject($attachment_id, $args) {

		$attachment_post = get_post( $attachment_id );
		$filename = esc_html( wp_basename( $attachment_post->guid ) );
		$mime = get_post_mime_type( $attachment_post );
		$file  = get_attached_file( $attachment_id );
		$file_size = false;

		if ( file_exists( $file ) ) {
			$file_size = filesize( $file );
		}

		$this->infoMessage(
			'attachment_created',
			array(
				"post_type" => get_post_type( $attachment_post ),
				"attachment_id" => $attachment_id,
				"attachment_title" => get_the_title( $attachment_post ),
				"attachment_filename" => $filename,
				"attachment_mime" => $mime,
				"attachment_filesize" => $file_size
			)
		);

	}

	/**
	 * Modify plain output to inlcude link to post
	 */
	public function getLogRowPlainTextOutput($row) {

		$message = $row->message;
		$context = $row->context;
		$message_key = $context["_message_key"];

		$attachment_id = $context["attachment_id"];
		$attachment_post = get_post( $attachment_id );
		$attachment_is_available = is_a($attachment_post, "WP_Post");

		// Only link to attachment if it is still available
		if ( $attachment_is_available ) {

			if ( "attachment_updated" == $message_key ) {

				$message = __('Edited {post_type} <a href="{edit_link}">"{attachment_title}"</a>', "simple-history");

			} else if ( "attachment_created" == $message_key ) {

				$message = __('Uploaded {post_type} <a href="{edit_link}">"{attachment_title}"</a>', "simple-history");

			}

			$context["post_type"] = esc_html( $context["post_type"] );
			$context["attachment_filename"] = esc_html( $context["attachment_filename"] );
			$context["edit_link"] = get_edit_post_link( $attachment_id );

			$message = $this->interpolate($message, $context, $row);

		} else {

			// Attachment post is not available, attachment has probably been deleted
			$message = parent::getLogRowPlainTextOutput( $row );

		}

		return $message;

	}

	/**
	 * Get output for detailed log section
	 */
	function getLogRowDetailsOutput($row) {

		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";

		$attachment_id = $context["attachment_id"];
		$attachment_post = get_post( $attachment_id );
		$attachment_is_available = is_a($attachment_post, "WP_Post");

		if ( "attachment_updated" == $message_key ) {

			// Attachment is changed = don't show thumbs and all

		} else if ( "attachment_deleted" == $message_key ) {

			// Attachment is deleted = don't show thumbs and all

		} else if ( "attachment_created" == $message_key ) {

			// Attachment is created/uploaded = show details with image thumbnail

			$attachment_id = $context["attachment_id"];
			$filetype = wp_check_filetype( $context["attachment_filename"] );
			$file_url = wp_get_attachment_url( $attachment_id );
			$edit_link = get_edit_post_link( $attachment_id );
			$attached_file = get_attached_file( $attachment_id );
			$message = "";
			$full_src = false;

			$is_image = wp_attachment_is_image( $attachment_id );
			$is_video = strpos($filetype["type"], "video/") !== false;
			$is_audio = strpos($filetype["type"], "audio/") !== false;

			$full_image_width = null;
			$full_image_height = null;
			if ( $is_image ) {

				$thumb_src = wp_get_attachment_image_src($attachment_id, array(350,500));
				$full_src = wp_get_attachment_image_src($attachment_id, "full");

				$full_image_width = $full_src[1];
				$full_image_height = $full_src[2];

				// is_image is also true for mime types that WP can't create thumbs for
				// so we need to check that wp got an resized version
				if ( $full_image_width && $full_image_height ) {

					$context["full_image_width"] = $full_image_width;
					$context["full_image_height"] = $full_image_height;

					// Only output thumb if file exists
					// For example images deleted on file system but not in WP cause broken images (rare case, but has happened to me.)
					if ( file_exists( $attached_file ) && $thumb_src ) {
						$context["attachment_thumb"] = sprintf('<div class="SimpleHistoryLogitemThumbnail"><img src="%1$s" alt=""></div>', $thumb_src[0] );
					}

				}

			} else if ( $is_audio ) {

				$content = sprintf('[audio src="%1$s"]', $file_url);
				$context["attachment_thumb"] = do_shortcode( $content );

			} else if ( $is_video ) {

				$content = sprintf('[video src="%1$s"]', $file_url);
				$context["attachment_thumb"] = do_shortcode( $content );

			} else {

				// use wordpress icon for other media types
				if ( $attachment_is_available ) {
					$context["attachment_thumb"] = wp_get_attachment_image( $attachment_id, null, true );
				}
				/*else {
				  // Add icon for deleted media?
					$context["attachment_thumb"] = "thumb";
				}*/

			}

			$context["attachment_size_format"] = size_format( $row->context["attachment_filesize"] );
			$context["attachment_filetype_extension"] = strtoupper( $filetype["ext"] );

			if ( ! empty( $context["attachment_thumb"] ) ) {

				if ( $is_image ) {
					$message .= "<a class='SimpleHistoryLogitemThumbnailLink' href='".$edit_link."'>";
				}

				$message .= __('{attachment_thumb}', 'simple-history');

				if ( $is_image ) {
					$message .= "</a>";
				}

			}

			$message .= "<p class='SimpleHistoryLogitem--logger-SimpleMediaLogger--attachment-meta'>";
			$message .= "<span class='SimpleHistoryLogitem__inlineDivided'>" . __('{attachment_size_format}', "simple-history") . "</span> ";
			$message .= "<span class='SimpleHistoryLogitem__inlineDivided'>" . __('{attachment_filetype_extension}', "simple-history") . "</span>";
			
			if ( $full_image_width && $full_image_height ) {

				$message .= " <span class='SimpleHistoryLogitem__inlineDivided'>" . __('{full_image_width} × {full_image_height}', "simple-history") . "</span>";

			}

			//$message .= " <span class='SimpleHistoryLogitem__inlineDivided'>" . sprintf( __('<a href="%1$s">Edit attachment</a>'), $edit_link ) . "</span>";
			$message .= "</p>";

			$output .= $this->interpolate( $message, $context, $row );

		}

		return $output;

	}

	/**
	 * Called when an attachment is added
	 */
	function on_add_attachment($attachment_id) {

		$attachment_post = get_post( $attachment_id );
		$filename = esc_html( wp_basename( $attachment_post->guid ) );
		$mime = get_post_mime_type( $attachment_post );
		$file  = get_attached_file( $attachment_id );
		$file_size = false;

		if ( file_exists( $file ) ) {
			$file_size = filesize( $file );
		}

		$this->infoMessage(
			'attachment_created',
			array(
				"post_type" => get_post_type( $attachment_post ),
				"attachment_id" => $attachment_id,
				"attachment_title" => get_the_title( $attachment_post ),
				"attachment_filename" => $filename,
				"attachment_mime" => $mime,
				"attachment_filesize" => $file_size
			)
		);

	}

	/**
	 * An attachmet is changed
	 * is this only being called if the title of the attachment is changed?!
	 *
	 * @param int $attachment_id
	 */
	function on_edit_attachment($attachment_id) {

		$attachment_post = get_post( $attachment_id );
		$filename = esc_html( wp_basename( $attachment_post->guid ) );
		$mime = get_post_mime_type( $attachment_post );
		$file  = get_attached_file( $attachment_id );

		$this->infoMessage(
			"attachment_updated",
			array(
				"post_type" => get_post_type( $attachment_post ),
				"attachment_id" => $attachment_id,
				"attachment_title" => get_the_title( $attachment_post ),
				"attachment_filename" => $filename,
				"attachment_mime" => $mime
			)
		);

	}

	/**
	 * Called when an attachment is deleted
	 */
	function on_delete_attachment($attachment_id) {

		$attachment_post = get_post( $attachment_id );
		$filename = esc_html( wp_basename( $attachment_post->guid ) );
		$mime = get_post_mime_type( $attachment_post );
		$file  = get_attached_file( $attachment_id );

		$this->infoMessage(
			"attachment_deleted",
			array(
				"post_type" => get_post_type( $attachment_post ),
				"attachment_id" => $attachment_id,
				"attachment_title" => get_the_title( $attachment_post ),
				"attachment_filename" => $filename,
				"attachment_mime" => $mime
			)
		);

	}

	/**
	 * Modify RSS links so they go directly to the correct media in wp admin
	 *
	 * @since 2.0.23
	 * @param string $link
	 * @param array $row
	 */
	public function filter_rss_item_link($link, $row) {

		if ( $row->logger != $this->slug ) {
			return $link;
		}

		if ( isset( $row->context["attachment_id"] ) ) {

			$permalink = add_query_arg(array("action" => "edit", "post" => $row->context["attachment_id"]), admin_url( "post.php" ) );

			if ( $permalink ) {
				$link = $permalink;
			}

		}

		return $link;

	}

}
