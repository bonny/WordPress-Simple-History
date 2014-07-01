<?php

/**
 * Logs changes to posts and pages, including custom post types
 */
class SimplePostLogger extends SimpleLogger
{

	public $slug = "SimplePostLogger";

	public function loaded() {
		
		add_action("admin_init", array($this, "on_admin_init"));

	}

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(
			
			// The logger slug. Defaulting to the class name is nice and logical I think
			"slug" => __CLASS__,

			// Shown on the info-tab in settings, use these fields to tell
			// an admin what your logger is used for
			"name" => "Post Logger",
			"description" => "Logs the created and modification of posts and pages",

			// Capability required to view log entries from this logger
			"capability" => "edit_pages",

			// Messages that this logger will log
			// By adding your messages here they will be stored both translated and non-translated
			// You then log something like this:
			// $this->info( $this->messages->POST_UPDATED );
			// $this->infoMessage( "POST_UPDATED" );
			// which results in the original, untranslated, string being added to the log and database
			// the translated string are then only used when showing the log in the GUI
			"messages" => array(
				'POST_UPDATED' => __('Updated {post_type} "{post_title}"', 'simple-history'),
				'POST_RESTORED' => __('Restored {post_type} "{post_title}" from trash', 'simple-history'),
				'POST_DELETED' => __('Deleted {post_type} "{post_title}"', 'simple-history'),
				'POST_CREATED' => __('Created {post_type} "{post_title}"', 'simple-history'),
				'POST_TRASHED' => __('Moved {post_type} "{post_title}" to the trash', 'simple-history')
			)
		);
		
		return $arr_info;

	}


	function on_admin_init() {

		add_action("transition_post_status", array($this, "on_transition_post_status"), 10, 3);
		add_action("delete_post", array($this, "on_delete_post"));
		add_action("untrash_post", array($this, "on_untrash_post"));
		
	}

	/**
	 * Called when a post is restored from the trash
	 */
	function on_untrash_post($post_id) {

		$post = get_post($post_id);

		__('Restored {post_type} "{post_title}" from trash', "simple-history");

		$this->info(
			'Restored {post_type} "{post_title}" from trash',
			array(
				"action_type" => "other",
				"post_id" => $post_id,
				"post_type" => get_post_type($post),
				"post_title" => get_the_title($post)
			)
		);

	}

	/**
	 * Called when a post is deleted from the trash
	 */
	function on_delete_post($post_id) {
		
		$post = get_post($post_id);

		if ( wp_is_post_revision($post_id) ) {
			return;
		}

		if ( $post->post_status === "auto-draft" || $post->post_status === "inherit" ) {
			return;
		}

		__('Deleted {post_type} "{post_title}"', "simple-history");

		$this->info(
			'Deleted {post_type} "{post_title}"',
			array(
				"action_type" => "delete",
				"post_id" => $post_id,
				"post_type" => get_post_type($post),
				"post_title" => get_the_title($post)
			)
		);

	}


	/**
	  * Fired when a post has changed status
	  */
	function on_transition_post_status($new_status, $old_status, $post) {

		// Don't log revisions
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		/*
		From new to auto-draft <- ignore
		From new to inherit <- ignore
		From auto-draft to draft <- page/post created
		From draft to draft
		From draft to pending
		From pending to publish
		From pending to trash
		if not from & to = same, then user has changed something
		*/

		$context = array(
			"action_type" => "other",
			"post_id" => $post->ID,
			"post_type" => get_post_type($post),
			"post_title" => get_the_title($post)
		);

		if ($old_status == "auto-draft" && ($new_status != "auto-draft" && $new_status != "inherit")) {

			// Post created
			__('Created {post_type} "{post_title}"', "simple-history");
			$context["action_type"] = "create";
			$this->info(
				'Created {post_type} "{post_title}"',
				$context
			);		

		} elseif ($new_status == "auto-draft" || ($old_status == "new" && $new_status == "inherit")) {

			// Hm... Not sure.
			return;

		} elseif ($new_status == "trash") {

			// Post trashed
			__('Moved {post_type} "{post_title}" to the trash', "simple-history");
			$context["action_type"] = "trash";
			$this->info(
				'Moved {post_type} "{post_title}" to the trash',
				$context
			);		

		} else {

			// Post updated
			__('Updated {post_type} "{post_title}"', "simple-history");
			$context["action_type"] = "update";
			$this->info(
				'Updated {post_type} "{post_title}"',
				$context
			);		

		}

	}	

	/**
	 * Modify plain output to inlcude link to post
	 */
	public function getLogRowPlainTextOutput($row) {
	
		$context = $row->context;
		$post_id = $context["post_id"];

		// Default to original log message
		$message = $row->message;

		// Check if post still is available
		// It wil return a WP_Post Object if post still is in system
		// If post is deleted from trash (not just moved there), then null is returned
		$post = get_post( $post_id );
		$post_is_available = is_a($post, "WP_Post");

		// If post is not available any longer then we can't link to it, so keep plain message then
		if ( $post_is_available && "update" == $context["action_type"] ) {

			$message = __('Updated {post_type} <a href="{edit_link}">"{post_title}"</a>', "simple-history");

		} else if ( $post_is_available && "delete" == $context["action_type"] ) {

			$message = __('Deleted {post_type} "{post_title}"');

		} else if ( $post_is_available && "create" == $context["action_type"] ) {

			$message = __('Created {post_type} <a href="{edit_link}">"{post_title}"</a>', "simple-history");

		} else if ( $post_is_available && "trash" == $context["action_type"] ) {

			// while in trash we can still get actions to delete or resore if we follow edit link
			$message = __('Moved {post_type} <a href="{edit_link}">"{post_title}"</a> to the trash', "simple-history");

		}

		$context["post_type"] = esc_html( $context["post_type"] );
		$context["post_title"] = esc_html( $context["post_title"] );
		$context["edit_link"] = get_edit_post_link( $post_id );

		return $this->interpolate($message, $context);

	}

}
