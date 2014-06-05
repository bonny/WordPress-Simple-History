<?php

/**
 * Logs changes to posts and pages, including custom post types
 */
class SimplePostLogger extends SimpleLogger
{
	/**
	 * Unique slug for this logger
	 * Will be saved in DB and used to associate each log row with its logger
	 */
	public $slug = "SimpleLogger";

	public function __construct() {
		

		add_action("admin_init", array($this, "on_admin_init"));

	}

	function on_admin_init() {

		add_action("save_post", array($this, "on_save_post"));
		#add_action("transition_post_status", array($this, "on_transition_post_status", 10, 3));
		add_action("delete_post", array($this, "on_delete_post"));
		add_action("wp_trash_post", array($this, "on_wp_trash_post"));

	}

	function on_wp_trash_post($post_id) {

		$post = get_post($post_id);

		$this->info(
			'Moved {post_type} "{post_title}" to the trash',
			array(
				"post_type" => get_post_type($post),
				"post_title" => get_the_title($post)
			)
		);

	}

	/**
	 * Called when a post is saved
	 */
	function on_save_post($post_id) {

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$post = get_post($post_id);

		if ( "auto-draft" === $post->post_status ) {
			return;
		}

		// Don't log if status is trash, because then we probably moved it there
		// to the log entry on_wp_trash_post should be enough
		if ( "trash" === $post->post_status ) {
			return;
		}

		$this->info(
			'Edited {post_type} "{post_title}"',
			array(
				"post_type" => get_post_type($post),
				"post_title" => get_the_title($post)
			)
		);
			
	}

	// post has changed status
	function on_transition_post_status($new_status, $old_status, $post) {

		sf_d("on_transition_post_status: from $old_status to $new_status");exit;

		// From new to auto-draft <- ignore
		// From new to inherit <- ignore
		// From auto-draft to draft <- page/post created
		// From draft to draft
		// From draft to pending
		// From pending to publish
		# From pending to trash
		// if not from & to = same, then user has changed something
		//bonny_d($post); // regular post object
		if ($old_status == "auto-draft" && ($new_status != "auto-draft" && $new_status != "inherit")) {
			// page created
			$action = "created";
		} elseif ($new_status == "auto-draft" || ($old_status == "new" && $new_status == "inherit")) {
			// page...eh.. just leave it.
			return;
		} elseif ($new_status == "trash") {
			$action = "deleted";
		} else {
			// page updated. i guess.
			$action = "updated";
		}
		$object_type = "post";
		$object_subtype = $post->post_type;

		// Attempt to auto-translate post types*/
		// no, no longer, do it at presentation instead
		#$object_type = __( ucfirst ( $object_type ) );
		#$object_subtype = __( ucfirst ( $object_subtype ) );

		if ($object_subtype == "revision") {
			// don't log revisions
			return;
		}
		
		if (wp_is_post_revision($post->ID) === false) {
			// ok, no revision
			$object_id = $post->ID;
		} else {
			return; 
		}
		
		$post_title = get_the_title($post->ID);
		$post_title = urlencode($post_title);
		
		simple_history_add("action=$action&object_type=$object_type&object_subtype=$object_subtype&object_id=$object_id&object_name=$post_title");
	}	

	function on_delete_post($post_id) {
		
		$post = get_post($post_id);

		if ( wp_is_post_revision($post_id) ) {
			return;
		}

		if ($post->post_status === "auto-draft" || $post->post_status === "inherit") {
			return;
		}

		$this->info(
			'Deleted {post_type} "{post_title}"',
			array(
				"post_type" => get_post_type($post),
				"post_title" => get_the_title($post)
			)
		);

	}

}
