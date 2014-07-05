<?php

/**
 * Logs media uploads
 */
class SimpleMediaLogger extends SimpleLogger
{

	public $slug = "SimpleMediaLogger";

	public function __construct() {
		
		add_action("admin_init", array($this, "on_admin_init"));

	}

	function on_admin_init() {

		add_action("add_attachment", array($this, "on_add_attachment"));
		add_action("edit_attachment", array($this, "on_edit_attachment"));
		add_action("delete_attachment", array($this, "on_delete_attachment"));

		add_action("admin_head", array($this, "output_styles"));
		
	}

	/**
	 * Outputs styles for this logger
	 */
	function output_styles() {
		
		?>
		<style>
			.simple-history-logitem--logger-SimpleMediaLogger--attachment-icon,
			.simple-history-logitem--logger-SimpleMediaLogger--attachment-thumb {
				display: inline-block;
				margin: .5em 0 0 0;
			}

			.simple-history-logitem--logger-SimpleMediaLogger--attachment-icon {
				max-width: 40px;
				max-height: 32px;
			}

			.simple-history-logitem--logger-SimpleMediaLogger--attachment-thumb {
				padding: 5px;
				border: 1px solid #ddd;
				-webkit-border-radius: 2px;
				-moz-border-radius: 2px;
				border-radius: 2px;
			}

			.simple-history-logitem--logger-SimpleMediaLogger--attachment-thumb img {
				/*
				photoshop-like background that represents tranpsarency
				so user can see that an image have transparency
				*/
				display: block;
				background-image: url('data:image/gif;base64,R0lGODlhEAAQAIAAAOXl5f///yH5BAAAAAAALAAAAAAQABAAAAIfhG+hq4jM3IFLJhoswNly/XkcBpIiVaInlLJr9FZWAQA7');
				max-width: 100%;
				max-height: 300px;
				max-height: 200px;
				height: auto;
			}

			.simple-history-logitem--logger-SimpleMediaLogger--attachment-meta-size,
			.simple-history-logitem--logger-SimpleMediaLogger--attachment-open {
				margin: .5em 0 0 0;
			}

			.simple-history-logitem--logger-SimpleMediaLogger .simple-history-logitem__details {
				max-width: 70%;
			}
		</style>
		<?php

	}

	/**
	 * Modify plain output to inlcude link to post
	 */
	public function getLogRowPlainTextOutput($row) {
		
		$message = $row->message;
		$context = $row->context;

		$attachment_id = $context["attachment_id"];
		$attachment_post = get_post( $attachment_id );
		$attachment_is_available = is_a($attachment_post, "WP_Post");
		
		// Only link to attachment if it is still available
		if ( $attachment_is_available && "update" == $context["action_type"] ) {

			$message = __('Edited {post_type} <a href="{edit_link}">"{attachment_title}"</a>', "simple-history");

		} else if ( $attachment_is_available && "delete" == $context["action_type"] ) {

			$message = __('Deleted {post_type} "{attachment_title}"', "simple-history");

		} else if ( $attachment_is_available && "create" == $context["action_type"] ) {

			$message = __('Uploaded {post_type} <a href="{edit_link}">"{attachment_title}"</a>', "simple-history");
		
		}

		$context["post_type"] = esc_html( $context["post_type"] );
		$context["attachment_filename"] = esc_html( $context["attachment_filename"] );
		$context["edit_link"] = get_edit_post_link( $attachment_id );

		return $this->interpolate($message, $context);

	}

	/**
	 * Get output with details
	 */
	function getLogRowDetailsOutput($row) {

		$context = $row->context;
		$output = "";

		if ( "update" == $context["action_type"] ) {
			
			// Attachment is changed = don't show thumbs and all

		} else if ( "delete" == $context["action_type"] ) {
			
			// Attachment is deleted = don't show thumbs and all

		} else if ( "create" == $context["action_type"] ) {

			// Attachment is created/uploaded = show details with image thumbnail

			$attachment_id = $context["attachment_id"];
			$filetype = wp_check_filetype( $context["attachment_filename"] );
			$file_url = wp_get_attachment_url( $attachment_id );
			$edit_link = get_edit_post_link( $attachment_id );
			$message = "";
			$full_src = false;

			$is_image = wp_attachment_is_image( $attachment_id );
			$is_video = strpos($filetype["type"], "video/") !== false;
			$is_audio = strpos($filetype["type"], "audio/") !== false;

			if ( $is_image ) {

				$thumb_src = wp_get_attachment_image_src($attachment_id, array(350,500));
				$full_src = wp_get_attachment_image_src($attachment_id, "full");
				#sf_d($thumb_src, '$thumb_src');
				#sf_d($full_src, '$full_src');

				$full_image_width = $full_src[1];
				$full_image_height = $full_src[2];

				// is_image is also true for mime types that WP can't create thumbs for
				// so we need to check that wp got an resized version
				if ( $full_image_width && $full_image_height ) {

					$context["full_image_width"] = $full_image_width;
					$context["full_image_height"] = $full_image_width;
					$context["attachment_thumb"] = sprintf('<div class="simple-history-logitem--logger-SimpleMediaLogger--attachment-thumb"><img src="%1$s"></div>', $thumb_src[0] );
				
				}

			} else if ($is_audio) {

				$content = sprintf('[audio src="%1$s"]', $file_url);
				$context["attachment_thumb"] .= do_shortcode( $content );

			} else if ($is_video) {

				$content = sprintf('[video src="%1$s"]', $file_url);
				$context["attachment_thumb"] .= do_shortcode( $content );

			}
			
			$context["attachment_size_format"] = size_format( $row->context["attachment_filesize"] );
			$context["attachment_filetype_extension"] = strtoupper( $filetype["ext"] );

			if ( ! empty( $context["attachment_thumb"] ) ) {
				
				if ($is_image) {
					$message .= "<a href='".$edit_link."'>";
				}
				
				$message .= __('{attachment_thumb}', 'simple-history');
				
				if ($is_image) {
					$message .= "</a>";
				}

			}

			$message .= "<p class='simple-history-logitem--logger-SimpleMediaLogger--attachment-meta'>";
			$message .= "<span class='simple-history-logitem__inlineDivided'>" . __('{attachment_size_format}', "simple-history") . "</span>";
			$message .= "<span class='simple-history-logitem__inlineDivided'>" . __('{attachment_filetype_extension}', "simple-history") . "</span>";
			if ($full_image_width && $full_image_height) {
				$message .= " <span class='simple-history-logitem__inlineDivided'>" . __('{full_image_width} × {full_image_height}') . "</span>";
			}
			$message .= " <span class='simple-history-logitem__inlineDivided'>" . sprintf( __('<a href="%1$s">Edit attachment</a>'), $edit_link ) . "</span>";
			$message .= "</p>";

			$output .= $this->interpolate($message, $context);

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
		
		__('Uploaded {post_type} "{attachment_filename}"', "simple-history");

		$this->info(
			'Uploaded {post_type} "{attachment_filename}"',
			array(
				"action_type" => "create",
				"post_type" => get_post_type($attachment_post),
				"attachment_id" => $attachment_id,
				"attachment_title" => get_the_title($attachment_post),
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

		__('Modified {post_type} "{attachment_filename}"', "simple-history");

		$this->info(
			'Modified {post_type} "{attachment_filename}"',
			array(
				"action_type" => "update",
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

		__('Deleted {post_type} "{attachment_filename}"', "simple-history");

		$this->info(
			'Deleted {post_type} "{attachment_filename}"',
			array(
				"action_type" => "delete",
				"post_type" => get_post_type( $attachment_post ),
				"attachment_id" => $attachment_id,
				"attachment_title" => get_the_title( $attachment_post ),
				"attachment_filename" => $filename,
				"attachment_mime" => $mime
			)
		);

	}

}
