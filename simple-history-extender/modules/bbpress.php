<?php

/**
 * Simple History Extender BBPress Class
 *
 * Extend Simple History for BBPress events
 * Version 2.2
 *
 * @since 0.0.2
 * 
 * @package Simple History Extender
 * @subpackage Modules
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Simple_History_Extend_BBPress' ) ) :

/**
 * Plugin class
 */
class Simple_History_Extend_BBPress extends Simple_History_Extend {

	function __construct(){
		parent::__construct( array(
			'id'     => 'bbpress',
			'title'  => __('BBPress', 'sh-extender'),
			'plugin' => 'bbpress/bbpress.php',
			'tabs'   => array(
				'supports' => array(
					__('Creating, editing and deleting a forum, topic, reply.', 'sh-extender'),
					__('Setting the type of a forum to category or forum.', 'sh-extender'),
					__('Setting the status of a forum, topic to open or closed.', 'sh-extender'),
					__('Setting the forum visibility to public, private or hidden.', 'sh-extender'),
					__('Trashing and untrashing a forum, topic, reply.', 'sh-extender'),
					__('Marking and unmarking a topic, reply as spam.', 'sh-extender'),
					__('Marking and unmarking a topic as sticky.', 'sh-extender'),
					__('Merging and splitting a topic.', 'sh-extender'),
					__('Updating, merging and deleting a topic tag.', 'sh-extender'),
					__('A user (un)favoriting and (un)subscribing to a topic.', 'sh-extender'),
					__('A user saving his/her profile.', 'sh-extender')
					)
				)
			)
		);
	}

	function add_events(){

		// Add custom bbPress events text
		$events = array(
			'close'       => __('closed', 'sh-extender'),
			'open'        => __('opened', 'sh-extender'),
			'stick'       => __('marked as sticky', 'sh-extender'),
			'super-stick' => __('marked as super sticky', 'sh-extender'),
			'unstick'     => __('unmarked as sticky', 'sh-extender'),
			'categorize'  => __('set to category type', 'sh-extender'),
			'normalize'   => __('set to forum type', 'sh-extender'),
			'publicize'   => __('set to public', 'sh-extender'),
			'privatize'   => __('set to private', 'sh-extender'),
			'hide'        => __('set to hidden', 'sh-extender'),
			'merge'       => __('in forum %s merged into %s', 'sh-extender'),
			'split'       => __('in forum %s split from reply %s by %s into %s in forum %s', 'sh-extender')
			);

		return $events;
	}

	function add_actions(){

		// Forum
		add_action( 'bbp_new_forum',         array( $this, 'new_forum'         ) ); // Covered by Simple History
		add_action( 'bbp_edit_forum',        array( $this, 'edit_forum'        ) ); // ..
		add_action( 'bbp_closed_forum',      array( $this, 'closed_forum'      ) );
		add_action( 'bbp_opened_forum',      array( $this, 'opened_forum'      ) );
		add_action( 'bbp_categorized_forum', array( $this, 'categorized_forum' ) );
		add_action( 'bbp_normalized_forum',  array( $this, 'normalized_forum'  ) );
		add_action( 'bbp_publicized_forum',  array( $this, 'publicized_forum'  ) );
		add_action( 'bbp_privatized_forum',  array( $this, 'privatized_forum'  ) );
		add_action( 'bbp_hid_forum',         array( $this, 'hid_forum'         ) );
		add_action( 'bbp_deleted_forum',     array( $this, 'deleted_forum'     ) ); // ..
		add_action( 'bbp_trashed_forum',     array( $this, 'trashed_forum'     ) ); // ..
		add_action( 'bbp_untrashed_forum',   array( $this, 'untrashed_forum'   ) ); // ..

		// Topic
		add_action( 'bbp_new_topic',         array( $this, 'new_topic'         ), 10, 4 ); // Covered by Simple History
		add_action( 'bbp_edit_topic',        array( $this, 'edit_topic'        ), 10, 5 ); // ..
		add_action( 'bbp_merged_topic',      array( $this, 'merged_topic'      ), 10, 3 );
		add_action( 'bbp_post_split_topic',  array( $this, 'post_split_topic'  ), 10, 3 );
		add_action( 'bbp_closed_topic',      array( $this, 'closed_topic'      ) );
		add_action( 'bbp_opened_topic',      array( $this, 'opened_topic'      ) );
		add_action( 'bbp_spammed_topic',     array( $this, 'spammed_topic'     ) );
		add_action( 'bbp_unspammed_topic',   array( $this, 'unspammed_topic'   ) );
		add_action( 'bbp_sticked_topic',     array( $this, 'sticked_topic'     ), 10, 3 );
		add_action( 'bbp_unsticked_topic',   array( $this, 'unsticked_topic'   ), 10, 2 );
		add_action( 'bbp_deleted_topic',     array( $this, 'deleted_topic'     ) ); // ..
		add_action( 'bbp_trashed_topic',     array( $this, 'trashed_topic'     ) ); // ..
		add_action( 'bbp_untrashed_topic',   array( $this, 'untrashed_topic'   ) ); // ..

		// Topic Tag
		add_action( 'bbp_update_topic_tag',  array( $this, 'update_topic_tag'  ), 10, 4 );
		add_action( 'bbp_merge_topic_tag',   array( $this, 'merge_topic_tag'   ), 10, 3 );
		add_action( 'bbp_delete_topic_tag',  array( $this, 'delete_topic_tag'  ), 10, 2 );

		// Reply
		add_action( 'bbp_new_reply',         array( $this, 'new_reply'         ), 10, 5 ); // Covered by Simple History
		add_action( 'bbp_edit_reply',        array( $this, 'edit_reply'        ), 10, 6 ); // ..
		add_action( 'bbp_spammed_reply',     array( $this, 'spammed_reply'     ) );
		add_action( 'bbp_unspammed_reply',   array( $this, 'unspammed_reply'   ) );
		add_action( 'bbp_deleted_reply',     array( $this, 'deleted_reply'     ) ); // ..
		add_action( 'bbp_trashed_reply',     array( $this, 'trashed_reply'     ) ); // ..
		add_action( 'bbp_untrashed_reply',   array( $this, 'untrashed_reply'   ) ); // ..

		// User
		add_action( 'bbp_add_user_favorite',        array( $this, 'add_user_favorite'        ), 10, 2 );
		add_action( 'bbp_remove_user_favorite',     array( $this, 'remove_user_favorite'     ), 10, 2 );
		add_action( 'bbp_add_user_subscription',    array( $this, 'add_user_subscription'    ), 10, 2 );
		add_action( 'bbp_remove_user_subscription', array( $this, 'remove_user_subscription' ), 10, 2 );
		// add_action( 'bbp_profile_update',           array( $this, 'profile_update'           ), 10, 2 ); // Covered by Simple History
		// add_action( 'bbp_user_register',            array( $this, 'user_register'            ) ); // ..
		add_filter( 'bbp_set_user_role',            array( $this, 'set_user_role'            ), 10, 3 );

	}

	/** Helpers ******************************************************/

	function extend_forum( $forum_id, $action ){
		$this->extend( array( 
			'action' => $action,
			'type'   => __('Forum', 'bbpress'),
			'name'   => bbp_get_forum_title( $forum_id ),
			'id'     => $forum_id
			) );
	}

	// @todo Author can be anonymous
	function extend_topic( $topic_id, $action, $user_id = null ){
		$this->extend( array( 
			'action'  => $action,
			'type'    => __('Topic', 'bbpress'),
			'name'    => bbp_get_topic_title( $topic_id ),
			'id'      => $topic_id
			) );
	}

	function extend_topic_tag( $tag_id, $action, $tag ){
		$this->extend( array( 
			'action' => $action,
			'type'   => __('Topic Tag', 'bbpress'),
			'name'   => bbp_get_topic_tag_name( $tag ),
			'id'     => $tag_id
			) );
	}

	// @todo Author can be anonymous
	function extend_reply( $reply_id, $action, $user_id ){
		$user = get_userdata( $user_id );

		$this->extend( array( 
			'action' => sprintf( __('by %s', 'sh-extender'), $user->user_login ) .' '. $action,
			'type'   => __('Reply', 'bbpress'),
			'name'   => bbp_get_reply_title( $reply_id ),
			'id'     => $reply_id
			) );
	}

	/** Forum ********************************************************/

	public function new_forum( $forum_args ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$child = 0 != $forum_args['post_parent'] ? ' '. sprintf( __('as child of %s'), bbp_get_forum_title( $forum_args['post_parent'] ) ) : '';
		$this->extend_forum( $forum_args['forum_id'], $this->events['new'] . $child );
	}

	public function edit_forum( $forum_args ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->extend_forum( $forum_args['forum_id'], $this->events['edit'] );
	}

	public function closed_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['close'] );
	}

	public function opened_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['open'] );
	}

	public function categorized_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['categorize'] );
	}

	public function normalized_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['normalize'] );
	}

	public function publicized_forum( $forum_id ){
		if ( bbp_get_forum_visibility( $forum_id ) == 'public' )
			$this->extend_forum( $forum_id, $this->events['publicize'] );
	}

	public function privatized_forum( $forum_id ){
		if ( bbp_get_forum_visibility( $forum_id ) == 'private' )
			$this->extend_forum( $forum_id, $this->events['privatize'] );
	}

	public function hid_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['hide'] );
	}

	public function deleted_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['delete'] );
	}

	public function trashed_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['trash'] );
	}

	public function untrashed_forum( $forum_id ){
		$this->extend_forum( $forum_id, $this->events['untrash'] );
	}

	/** Topic ********************************************************/

	public function new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->extend_topic( 
			$topic_id, 
			$this->events['new'] .' '. sprintf( __('in forum %s', 'sh-extender'), bbp_get_forum_title( $forum_id ) ) 
			);
	}

	public function edit_topic( $topic_id, $forum_id, $anonymous_data, $topic_author, $is_edit ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->extend_topic( 
			$topic_id, 
			$this->events['edit'] .' '. sprintf( __('in forum %s', 'sh-extender'), bbp_get_forum_title( $forum_id ) ) 
			);
	}

	public function merged_topic( $destination_topic_id, $source_topic_id, $source_parent_id ){
		$this->extend_topic( 
			$source_topic_id, 
			sprintf( $this->events['merge'], bbp_get_forum_title( $source_parent_id ), bbp_get_topic_title( $destination_topic_id ) ) 
			);
	}

	public function post_split_topic( $from_reply_id, $source_topic_id, $destination_topic_id ){
		$this->extend_topic( 
			$source_topic_id, 
			sprintf( $this->events['split'], bbp_get_reply_title( $from_reply_id ), bbp_get_topic_title( $destination_topic_id ) )
			);
	}

	public function closed_topic( $topic_id ){
		$this->extend_topic( $topic_id, $this->events['close'] );
	}

	public function opened_topic( $topic_id ){
		$this->extend_topic( $topic_id, $this->events['open'] );
	}

	public function spammed_topic( $topic_id ){
		$this->extend_topic( $topic_id, $this->events['spam'] );
	}

	public function unspammed_topic( $topic_id ){
		$this->extend_topic( $topic_id, $this->events['unspam'] );
	}

	public function sticked_topic( $topic_id, $super, $success ){
		if ( $success ){	
			if ( $super )
				$this->extend_topic( $topic_id, $this->events['super-stick'] );
			else
				$this->extend_topic( $topic_id, $this->events['stick'] );
		}
	}

	public function unsticked_topic( $topic_id, $success ){
		if ( $success )
			$this->extend_topic( $topic_id, $this->events['unstick'] );
	}

	public function deleted_topic( $topic_id ){
		$this->extend_topic( $topic_id, $this->events['delete'] );
	}

	public function trashed_topic( $topic_id ){
		$this->extend_topic( $topic_id, $this->events['trash'] );
	}

	public function untrashed_topic( $topic_id ){
		$this->extend_topic( $topic_id, $this->events['untrash'] );
	}

	/** Topic Tags ***************************************************/

	public function update_topic_tag( $tag_id, $tag, $name, $slug ){
		$this->extend_topic_tag( $tag_id, $this->events['edit'], $tag );
	}

	public function merge_topic_tag( $tag_id, $to_tag, $tag ){
		$this->extend_topic_tag( $tag_id, sprintf( $this->events['merge'], bbp_get_topic_tag_name( $to_tag ) ), $tag );
	}

	public function delete_topic_tag( $tag_id, $tag ){
		$this->extend_topic_tag( $tag_id, $this->events['delete'], $tag );
	}

	/** Reply ********************************************************/

	public function new_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->extend_reply( $reply_id, $this->events['new'], $reply_author );
	}

	public function edit_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author, $is_edit ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->extend_reply( $reply_id, $this->events['edit'], $reply_author );
	}

	public function spammed_reply( $reply_id ){
		$this->extend_reply( $reply_id, $this->events['spam'], $reply_author );
	}

	public function unspammed_reply( $reply_id ){
		$this->extend_reply( $reply_id, $this->events['unspam'], $reply_author );
	}

	public function deleted_reply( $reply_id ){
		$this->extend_reply( $reply_id, $this->events['delete'], $reply_author );
	}

	public function trashed_reply( $reply_id ){
		$this->extend_reply( $reply_id, $this->events['trash'], $reply_author );
	}

	public function untrashed_reply( $reply_id ){
		$this->extend_reply( $reply_id, $this->events['untrash'], $reply_author );
	}

	/** User *********************************************************/

	public function add_user_favorite( $user_id, $topic_id ){
		$this->extend_topic( $topic_id, __('favorited', 'sh-extender') );
	}

	public function remove_user_favorite( $user_id, $topic_id ){
		$this->extend_topic( $topic_id, __('unfavorited', 'sh-extender') );
	}

	public function add_user_subscription( $user_id, $topic_id ){
		$this->extend_topic( $topic_id, __('subscribed', 'sh-extender') );
	}

	public function remove_user_subscription( $user_id, $topic_id ){
		$this->extend_topic( $topic_id, __('unsubscribed', 'sh-extender') );
	}

	public function profile_update( $user_id, $old_user_data ){
		$this->extend_user( $user_id, __('profile updated', 'sh-extender') );
	}

	public function user_register( $user_id ){
		$this->extend_user( $user_id, __('registered', 'sh-extender') );
	}

	/**
	 * @todo Removing ones role does somehow not trigger bbp_set_user_role action
	 *        while it actually has to.
	 */
	public function set_user_role( $new_role, $user_id, $user ){

		// Only log if a new role was actually assigned
		if ( false !== $new_role ){
			$bbp_roles = bbp_get_dynamic_roles();
			$this->extend_user( $user_id, sprintf( __('changed forum role to %s', 'sh-extender'), !empty( $new_role ) ? translate_user_role( $bbp_roles[$new_role]['name'] ) : __('none') ) );
		}

		return $new_role;
	}

}

new Simple_History_Extend_BBPress();

endif; // class_exists
