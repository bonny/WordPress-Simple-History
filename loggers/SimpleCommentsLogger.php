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
			"capability" => "moderate_comments",
			"messages" => array(
				'comment_added' => _x(
					'A comment was added', 
					'A comment was added to the database',
					'simple-history'
				)
			)
		);
		
		return $arr_info;

	}

	public function loaded() {

		// Fires immediately after a comment is inserted into the database.
		// @param int $comment_ID The comment ID.
		// @param int $comment_approved 1 (true) if the comment is approved, 0 (false) if not.
		add_action( 'comment_post', array( $this, 'on_comment_post'), 10, 2 );

		add_action( "edit_comment", array( $this, 'on_edit_comment') );
		add_action( "delete_comment", array( $this, 'on_delete_comment') );
		add_action( "wp_set_comment_status", array( $this, 'on_set_comment_status') );
		
	}

	public function on_comment_post($comment_ID, $comment_approved) {

		// Only log approved comments
		if ( ! $comment_approved ) {
			// return;
		}

		/*
		{
		    "comment_ID": "5",
		    "comment_post_ID": "25080",
		    "comment_author": "P\u00e4r",
		    "comment_author_email": "par@earthpeople.se",
		    "comment_author_url": "",
		    "comment_author_IP": "127.0.0.1",
		    "comment_date": "2014-07-30 16:42:46",
		    "comment_date_gmt": "2014-07-30 16:42:46",
		    "comment_content": "hej svejs igen",
		    "comment_karma": "0",
		    "comment_approved": "1",
		    "comment_agent": "Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_9_4) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/36.0.1985.125 Safari\/537.36",
		    "comment_type": "",
		    "comment_parent": "0",
		    "user_id": "1"
		}
		*/
		$comment_data = get_comment( $comment_ID );

		// WP_Post object
		$comment_parent_post = get_post( $comment_data->comment_post_ID );

		$this->infoMessage(
			"comment_added",
			array(
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
				// the post that this is a comment to
				"comment_post_title" => $comment_parent_post->post_title,
			)
		);

	}

}
