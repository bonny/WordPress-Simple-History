<?php

/**
 * Logs things related to comments
 */
class SimpleCommentsLogger extends SimpleLogger
{

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => "Comments Logger",
			"description" => "Logs comments, and modifications to them",
			"search_label" => "Comments",
			"capability" => "moderate_comments",
			"messages" => array(

				'anon_comment_added' => _x(
					//'{comment_author} ({comment_author_email}) added a comment to "{comment_post_title}"', 
					'Added a comment to {comment_post_type} "{comment_post_title}"',
					'A comment was added to the database by a non-logged in internet user',
					'simple-history'
				),

				'user_comment_added' => _x(
					'Added a comment to {comment_post_type} "{comment_post_title}"', 
					'A comment was added to the database by a logged in user',
					'simple-history'
				),

				// approve, spam, trash, hold
				'comment_status_approve' => _x(
					#'Approved a comment to "{comment_post_title}"', 
					'Approved a comment to "{comment_post_title}" made by {comment_author} ({comment_author_email})', 
					'A comment was approved',
					'simple-history'
				),

				'comment_status_hold' => _x(
					'Unapproved a comment to "{comment_post_title}" made by {comment_author} ({comment_author_email})', 
					#'Unapproved the comment for "{comment_post_title}" from {comment_author} ({comment_author_email})',
					#'Unapproved a comment from {comment_author} ({comment_author_email}) to "{comment_post_title}"', 	
					'A comment was was unapproved',
					'simple-history'
				),

				'comment_status_spam' => _x(
					'Marked a comment to post "{comment_post_title}" as spam', 
					'A comment was marked as spam',
					'simple-history'
				),

				'comment_status_trash' => _x(
					#'Moved a comment to post "{comment_post_title}" to the trash', 
					'Trashed a comment to "{comment_post_title}" made by {comment_author} ({comment_author_email})', 
					'A comment was marked moved to the trash',
					'simple-history'
				),

				'comment_untrashed' => _x(
					'Restored a comment to "{comment_post_title}" made by {comment_author} ({comment_author_email}) from the trash', 
					'A comment was restored from the trash',
					'simple-history'
				),

				'comment_deleted' => _x(
					#'Deleted a comment to post "{comment_post_title}"', 
					'Deleted a comment to "{comment_post_title}" made by {comment_author} ({comment_author_email})', 
					'A comment was deleted',
					'simple-history'
				),

				'comment_edited' => _x(
					#'Edited a comment to post "{comment_post_title}"', 
					'Edited a comment to "{comment_post_title}" made by {comment_author} ({comment_author_email})', 
					'A comment was edited',
					'simple-history'
				),

			)
		);
		
		return $arr_info;

	}

	public function loaded() {

		/**
		 * Fires immediately after a comment is inserted into the database.
		 */
		add_action( 'comment_post', array( $this, 'on_comment_post'), 10, 2 );

		/**
		 * Fires after a comment status has been updated in the database.
		 * The hook also fires immediately before comment status transition hooks are fired.
		 */
		add_action( "wp_set_comment_status", array( $this, 'on_wp_set_comment_status'), 10, 2 );

		/**
		 *Fires immediately after a comment is restored from the Trash.
		 */
		add_action( "untrashed_comment", array( $this, 'on_untrashed_comment'), 10, 1 );

 		/**
 		 * Fires immediately before a comment is deleted from the database.
 		 */
		add_action( "delete_comment", array( $this, 'on_delete_comment'), 10, 1 );

		/**
		 * Fires immediately after a comment is updated in the database.
	 	 * The hook also fires immediately before comment status transition hooks are fired.
	 	 */
		add_action( "edit_comment", array( $this, 'on_edit_comment'), 10, 1 );

		
	}

	/**
	 * Get comments context
	 * 
	 * @param int $comment_ID
	 * @return mixed array with context if comment found, false if comment not found
	 */
	public function get_context_for_comment($comment_ID) {

		$comment_data = get_comment( $comment_ID );

		if ( is_null( $comment_data ) ) {
			return false;
		}

		$comment_parent_post = get_post( $comment_data->comment_post_ID );

		$context = array(
			"comment_ID" => $comment_ID,
			"comment_author" => $comment_data->comment_author,
			"comment_author_email" => $comment_data->comment_author_email,
			"comment_author_url" => $comment_data->comment_author_url,
			"comment_author_IP" => $comment_data->comment_author_IP,
			"comment_content" => $comment_data->comment_content,
			"comment_approved" => $comment_data->comment_approved,
			"comment_agent" => $comment_data->comment_agent,
			"comment_type" => $comment_data->comment_type,
			"comment_parent" => $comment_data->comment_parent,
			"comment_post_ID" => $comment_data->comment_post_ID,
			"comment_post_title" => $comment_parent_post->post_title,
			"comment_post_type" => $comment_parent_post->post_type,
		);

		return $context;

	}

	public function on_edit_comment($comment_ID) {

		$context = $this->get_context_for_comment($comment_ID);
		if ( ! $context ) {
			return;
		}

		$this->infoMessage(
			"comment_edited",
			$context
		);		

	}

	public function on_delete_comment($comment_ID) {

		$context = $this->get_context_for_comment($comment_ID);
		if ( ! $context ) {
			return;
		}

		$this->infoMessage(
			"comment_deleted",
			$context
		);		

	}

	public function on_untrashed_comment($comment_ID) {

		$context = $this->get_context_for_comment($comment_ID);
		if ( ! $context ) {
			return;
		}

		$this->infoMessage(
			"comment_untrashed",
			$context
		);		

	}

	/**
	 * Fires after a comment status has been updated in the database.
	 * The hook also fires immediately before comment status transition hooks are fired.
	 * @param int         $comment_id     The comment ID.
	 * @param string|bool $comment_status The comment status. Possible values include 'hold',
	 *                                    'approve', 'spam', 'trash', or false.
	 * do_action( 'wp_set_comment_status', $comment_id, $comment_status );
	 */	
	public function on_wp_set_comment_status($comment_ID, $comment_status) {

		$context = $this->get_context_for_comment($comment_ID);
		if ( ! $context ) {
			return;
		}

		/*
		$comment_status:
			approve
				comment was approved
			spam
				comment was marked as spam
			trash
				comment was trashed
			hold
				comment was un-approved
		*/
		// sf_d($comment_status);exit;
		$message = "comment_status_{$comment_status}";

		$this->infoMessage(
			$message,
			$context
		);

	}

	public function on_comment_post($comment_ID, $comment_approved) {

		$context = $this->get_context_for_comment($comment_ID);
		
		if ( ! $context ) {
			return;
		}

		$comment_data = get_comment( $comment_ID );

		$message = "";
		
		if ( $comment_data->user_id ) {
		
			// comment was from a logged in user
			$message = "user_comment_added";

		} else {
		
			// comment was from a non-logged in user
			$message = "anon_comment_added";
			$context["_initiator"] = SimpleLoggerLogInitiators::WEB_USER;

			// @TODO: add occasions if comment is considered spam
			// if not added, spam comments can easily flood the log
			if ( isset( $comment_data["comment_approved"] ) && "spam" === $comment_data["comment_approved"] ) {
				$context["_occasionsID"] = __CLASSNAME__  . '/' . __FUNCTION__ . "/anon_comment_added/type:spam";
			}

		}

		$this->infoMessage(
			$message,
			$context
		);

	}


	/**
	 * Modify plain output to inlcude link to post
	 * and link to comment
	 */
	public function getLogRowPlainTextOutput($row) {
		
		$message = $row->message;
		$context = $row->context;
		$message_key = $context["_message_key"];

		// Wrap links around {comment_post_title}
		$comment_post_ID = isset( $context["comment_post_ID"] ) ? (int) $context["comment_post_ID"] : null;
		if ( $comment_post_ID && $comment_post = get_post( $comment_post_ID ) ) {

			$edit_post_link = get_edit_post_link( $comment_post_ID );

			if ( $edit_post_link ) {
			
				$message = str_replace(
					'"{comment_post_title}"',
					"<a href='{$edit_post_link}'>\"{comment_post_title}\"</a>",
					$message
				);

			}
	
		}

		return $this->interpolate($message, $context);

	}


	/**
	 * Get output for detailed log section
	 */
	function getLogRowDetailsOutput($row) {

		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";
	
		/*	
		if ( 'spam' !== $commentdata['comment_approved'] ) { // If it's spam save it silently for later crunching
				if ( '0' == $commentdata['comment_approved'] ) { // comment not spam, but not auto-approved
					wp_notify_moderator( $comment_ID );
		*/
		/*if ( isset( $context["comment_approved"] ) && $context["comment_approved"] == '0' ) {
			$output .= "<br>comment was automatically approved";
		} else {
			$output .= "<br>comment was not automatically approved";
		}*/

		$comment_text = "";
		if ( isset( $context["comment_content"] ) && $context["comment_content"] ) {
			$comment_text = $context["comment_content"];
			$comment_text = wp_trim_words( $comment_text, 20 );
			$comment_text = wpautop( $comment_text );
		}
	
		// Keys to show
		$arr_plugin_keys = array(
			"comment_status" => _x("Comment status", "comments logger - detailed output url", "simple-history"),
			"comment_author" => _x("Author name", "comments logger - detailed output version", "simple-history"),
			"comment_author_email" => _x("Author email", "comments logger - detailed output url", "simple-history"),
			"comment_content" => _x("Comment", "comments logger - detailed output author", "simple-history"),
			//"comment_author_url" => _x("Author URL", "comments logger - detailed output author", "simple-history"),
			//"comment_author_IP" => _x("IP number", "comments logger - detailed output IP", "simple-history"),
		);

		$arr_plugin_keys = apply_filters("simple_history/comments_logger_row_details_plugin_info_keys", $arr_plugin_keys);

		// Start output of plugin meta data table
		$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

		foreach ( $arr_plugin_keys as $key => $desc ) {
			
			switch ( $key ) {

				case "comment_content":
					$desc_output = $comment_text;
					break;

				case "comment_author":
					
					$desc_output = "";

					$desc_output .= esc_html( $context[ $key ] );

					/*
					if ( isset( $context["comment_author_email"] ) ) {
						
						$gravatar_email = $context["comment_author_email"];
						$avatar = $this->simpleHistory->get_avatar( $gravatar_email, 14, "blank" );
						$desc_output .= "<span class='SimpleCommentsLogger__gravatar'>{$avatar}</span>";
						
					}
					*/

					break;

				case "comment_status":

					if ( isset( $context["comment_approved"] ) ) {
						// $desc_output = esc_html( "apa" );
						if ( $context["comment_approved"] === "spam" ) {
							$desc_output = "Spam";
						} else if ( $context["comment_approved"] == 1 ) {
							$desc_output = "Approved";
						} else if ( $context["comment_approved"] == 0 ) {
							$desc_output = "Pending";
						}
					}
					break;

				default;
					$desc_output = esc_html( $context[ $key ] );
					break;
			}

			// Skip empty rows
			if (empty( $desc_output )) {
				continue;
			}

			$output .= sprintf(
				'
				<tr>
					<td>%1$s</td>
					<td>%2$s</td>
				</tr>
				',
				esc_html($desc),
				$desc_output
			);

		}

		// Add link to edit comment
		$comment_ID = isset( $context["comment_ID"] ) && is_numeric( $context["comment_ID"] ) ? (int) $context["comment_ID"] : false;
		
		if ($comment_ID) {
	
			$edit_comment_link = get_edit_comment_link( $comment_ID );
			
			if ($edit_comment_link) {
			
				$output .= sprintf(
					'
					<tr>
						<td></td>
						<td><a href="%2$s">%1$s</a></td>
					</tr>
					',
					_x("Edit comment", "comments logger - edit comment", "simple-history"),
					$edit_comment_link
				);

			}

		}


		// End table
		$output .= "</table>";

		return $output;

	}

	function adminCSS() {
		?>
		<style>
			.SimpleCommentsLogger__gravatar {
				line-height: 1;
				border-radius: 50%;
				overflow: hidden;
				margin-right: .5em;
				margin-left: .5em;
				display: inline-block;
			}
			.SimpleCommentsLogger__gravatar img {
				display: block;
			}
		</style>
		<?php
	}

}
