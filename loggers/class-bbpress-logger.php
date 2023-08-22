<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logs changes made on the Simple History settings page.
 */
class BBPress_Logger extends Logger {
	protected $slug = 'BBPressLogger';

	public function get_info() {
		return [
			'name' => _x( 'BBPress Logger', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			'description' => __( 'Logs topics and replies', 'simple-history' ),
			'messages' => [
				'created_topic' => _x( 'Created topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
				'deleted_topic' => _x( 'Deleted topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
				'created_reply' => _x( 'Created reply in topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
				'deleted_reply' => _x( 'Deleted reply in topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
			],
		];
	}

	public function loaded() {
		add_action( 'bbp_new_topic', array( $this, 'topic_create' ), 10, 4 );
		add_action( 'bbp_delete_topic', array( $this, 'topic_delete' ), 10, 1 );

		// do_action( 'bbp_new_reply', $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author, false, $reply_to );
		add_action( 'bbp_new_reply', array( $this, 'reply_create' ), 10, 5 );
		// do_action( 'bbp_delete_reply', $reply_id );
		add_action( 'bbp_delete_reply', array( $this, 'reply_delete' ), 10, 1 );

		// Hook into topic and reply status changes
		// add_action( 'edit_post',                         array( $this, 'topic_update'              ), 10, 2 );
		// add_action( 'edit_post',                         array( $this, 'reply_update'              ), 10, 2 );
	}

	/**
	 * Log topic creation
	 *
	 * @param int $topic_id
	 * @param int $forum_id
	 * @param array $anonymous_data
	 * @param int $topic_author
	 */
	public function topic_create( $topic_id, $forum_id, $anonymous_data, $topic_author ) {
		$topic_title     = get_post_field( 'post_title', $topic_id, 'raw' );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );

		$this->info_message(
			'created_topic',
			[
				'topic_title' => $topic_title,
				'forum_title' => $forum_title,
			]
		);
	}

	public function topic_delete( $topic_id ) {
		$topic_title = get_post_field( 'post_title', $topic_id, 'raw' );
		$forum_id = bbp_get_topic_forum_id( $post->ID );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );

		$this->info_message(
			'deleted_topic',
			[
				'topic_title' => $topic_title,
				'forum_title' => $forum_title,
			]
		);
	}

	public function reply_create( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
		$topic_title     = get_post_field( 'post_title', $topic_id, 'raw' );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );

		$this->info_message(
			'created_reply',
			[
				'topic_title' => $topic_title,
				'forum_title' => $forum_title,
			]
		);
	}

	public function reply_delete( $reply_id ) {
		$topic_id = bbp_get_reply_topic_id( $reply_id );
		$topic_title     = get_post_field( 'post_title', $topic_id, 'raw' );
		$forum_id = bbp_get_topic_forum_id( $topic_id );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );

		$this->info_message(
			'deleted_reply',
			[
				'topic_title' => $topic_title,
				'forum_title' => $forum_title,
			]
		);
	}


}
