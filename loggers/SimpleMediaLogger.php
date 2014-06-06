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
		
	}

	function on_add_attachment($attachment_id) {

		$attachment_post = get_post( $attachment_id );
		$filename = esc_html( wp_basename( $attachment_post->guid ) );
		$mime = get_post_mime_type( $attachment_post );
		
		// Meta is empty. Why?
		#$meta = wp_get_attachment_metadata( $attachment_id );
		#$size = size_format( $meta["filesize"] );

		$this->info(
			'Added {post_type} "{post_title}" ("{post_filename}") - {attachment_mime}',
			array(
				"post_type" => get_post_type($attachment_post),
				"post_title" => get_the_title($attachment_post),
				"post_filename" => $filename,
				"attachment_mime" => $mime
			)
		);

	}
	/*
	function on_edit_attachment($attachment_id) {
		// is this only being called if the title of the attachment is changed?!
		$post = get_post($attachment_id);
		$post_title = urlencode(get_the_title($post->ID));
		add("action=updated&object_type=attachment&object_id=$attachment_id&object_name=$post_title");
	}
	function on_delete_attachment($attachment_id) {
		$post = get_post($attachment_id);
		$post_title = urlencode(get_the_title($post->ID));
		add("action=deleted&object_type=attachment&object_id=$attachment_id&object_name=$post_title");
	}*/

}
