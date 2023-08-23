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
				'trashed_topic' => _x( 'Trashed topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
				'created_reply' => _x( 'Created reply in topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
				'deleted_reply' => _x( 'Deleted reply in topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
				'trashed_reply' => _x( 'Trashed reply in topic "{topic_title}" in forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
				'updated_topic' => _x( 'Updated the topic "{topic_title}" in the forum "{forum_title}"', 'Logger: BBPressLogger', 'simple-history' ),
			],
		];
	}

	/**
	 * The actions here are based on the ones found in `activity.php` in the BBPress plugin.
	 */
	public function loaded() {
		add_action( 'bbp_new_topic', array( $this, 'topic_create' ), 10, 4 );
		add_action( 'bbp_delete_topic', array( $this, 'topic_delete' ), 10, 1 );

		add_action( 'bbp_new_reply', array( $this, 'reply_create' ), 10, 5 );
		add_action( 'bbp_delete_reply', array( $this, 'reply_delete' ), 10, 1 );

		// Hook into topic and reply status changes
		add_action( 'edit_post', array( $this, 'topic_update' ), 10, 2 );
		add_action( 'edit_post', array( $this, 'reply_update' ), 10, 2 );

		// Disable built in post logger for bbPress for some frontend actions.
		add_filter( 'simple_history/log/do_log', array( $this, 'disable_messages' ), 10, 5 );
	}

	/**
	 * Disable built in post logger for bbPress for some frontend actions.
	 *
	 * @param bool $doLog Whether to log or not.
	 * @param string $level The loglevel.
	 * @param string $message The log message.
	 * @param array $context The message context.
	 * @param Logger $instance Logger instance.
	 * @return bool
	 */
	public function disable_messages( $do_log, $level, $message, $context, $logger ) {
		// Don't do anything when in admin.
		if ( is_admin() ) {
			return $do_log;
		}

		$message_key = $context['_message_key'] ?? '';
		$post_type = $context['post_type'] ?? '';

		// Don't use built in logger for these, it's handled by the BBPress logger.
		if ( 'SimplePostLogger' === $logger->get_slug() && 'post_deleted' === $message_key && in_array( $post_type, [ 'topic', 'reply' ] ) ) {
			return false;
		}

		sh_error_log( '$message, $context, logger slug', $message, $context, $logger->get_slug() );

		return $do_log;
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
				'aaa' => 'aaa',
			]
		);
	}

	public function topic_update( $topic_id, $post ) {
		// Bail early if not a topic
		if ( get_post_type( $post ) !== bbp_get_topic_post_type() ) {
			return;
		}

		$topic_id = bbp_get_topic_id( $topic_id );

		$topic_title     = get_post_field( 'post_title', $topic_id, 'raw' );
		$forum_id = bbp_get_topic_forum_id( $topic_id );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );

		if ( bbp_is_topic_public( $post->ID ) ) {
			$this->info_message(
				'created_topic',
				[
					'topic_title' => $topic_title,
					'forum_title' => $forum_title,
				]
			);
		} else {
			$this->info_message(
				'trashed_topic',
				[
					'topic_title' => $topic_title,
					'forum_title' => $forum_title,
				]
			);
		}

	}

	/**
	 * Update the activity stream entry when a reply status changes
	 *
	 * @param int $reply_id
	 * @param obj $post
	 * @return Bail early if not a reply, or reply is by anonymous user
	 */
	public function reply_update( $reply_id, $post ) {
		// Bail early if not a reply
		if ( get_post_type( $post ) !== bbp_get_reply_post_type() ) {
			return;
		}

		$topic_id        = bbp_get_reply_topic_id( $reply_id );
		$forum_id        = bbp_get_reply_forum_id( $reply_id );

		$topic_title     = get_post_field( 'post_title', $topic_id, 'raw' );
		$forum_title     = get_post_field( 'post_title', $forum_id, 'raw' );

		// Action based on new status
		if ( bbp_get_public_status_id() === $post->post_status ) {
			$this->info_message(
				'updated_reply',
				[
					'topic_title' => $topic_title,
					'forum_title' => $forum_title,
				]
			);
		} else {
			$this->info_message(
				'trashed_reply',
				[
					'topic_title' => $topic_title,
					'forum_title' => $forum_title,
					'bbb' => 'bbb',
				]
			);
		}
	}

}
