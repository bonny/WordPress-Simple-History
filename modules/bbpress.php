<?php

/**
 * Simple History Module BBPress Class
 *
 * Extend Simple History for BBPress events
 * Version 2.2
 *
 * @since 1.1
 * 
 * @package Simple History
 * @subpackage Modules
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simple_History_Module_BBPress' ) ) :

/**
 * Plugin class
 */
class Simple_History_Module_BBPress extends Simple_History_Module {

	function __construct(){
		parent::__construct( array(
			'id'     => 'bbpress',
			'title'  => __('BBPress', 'simple-history'),
			'plugin' => 'bbpress/bbpress.php',
			'tabs'   => array(
				'supports' => array(
					__('Creating, editing and deleting a forum, topic, reply.',      'simple-history'),
					__('Setting the type of a forum to category or forum.',          'simple-history'),
					__('Setting the status of a forum, topic to open or closed.',    'simple-history'),
					__('Setting the forum visibility to public, private or hidden.', 'simple-history'),
					__('Trashing and untrashing a forum, topic, reply.',             'simple-history'),
					__('Marking and unmarking a topic, reply as spam.',              'simple-history'),
					__('Marking and unmarking a topic as sticky.',                   'simple-history'),
					__('Merging and splitting a topic.',                             'simple-history'),
					__('Updating, merging and deleting a topic tag.',                'simple-history'),
					__('A user (un)favoriting and (un)subscribing to a topic.',      'simple-history'),
					__('A user saving his/her profile.',                             'simple-history'),
				)
			)
		) );
	}

	function add_events(){

		// No common bbpress events
		$events = array();

		return $events;
	}

	function add_actions(){

		// Forum
		add_action( 'bbp_new_forum',                array( $this, 'forum_created'     )        ); // Covered in core Simple History
		add_action( 'bbp_edit_forum',               array( $this, 'forum_edited'      )        ); // ..
		add_action( 'bbp_closed_forum',             array( $this, 'forum_closed'      )        );
		add_action( 'bbp_opened_forum',             array( $this, 'forum_opened'      )        );
		add_action( 'bbp_categorized_forum',        array( $this, 'forum_categorized' )        );
		add_action( 'bbp_normalized_forum',         array( $this, 'forum_normalized'  )        );
		add_action( 'bbp_publicized_forum',         array( $this, 'forum_publicized'  )        );
		add_action( 'bbp_privatized_forum',         array( $this, 'forum_privatized'  )        );
		add_action( 'bbp_hid_forum',                array( $this, 'forum_hidden'      )        );
		add_action( 'bbp_deleted_forum',            array( $this, 'forum_deleted'     )        ); // ..
		add_action( 'bbp_trashed_forum',            array( $this, 'forum_trashed'     )        ); // ..
		add_action( 'bbp_untrashed_forum',          array( $this, 'forum_untrashed'   )        ); // ..

		// Topic
		add_action( 'bbp_new_topic',                array( $this, 'topic_created'     ), 10, 4 ); // Covered in core Simple History
		add_action( 'bbp_edit_topic',               array( $this, 'topic_edited'      ), 10, 5 ); // ..
		add_action( 'bbp_merged_topic',             array( $this, 'topic_merged'      ), 10, 3 );
		add_action( 'bbp_post_split_topic',         array( $this, 'topic_split'       ), 10, 3 );
		add_action( 'bbp_closed_topic',             array( $this, 'topic_closed'      )        );
		add_action( 'bbp_opened_topic',             array( $this, 'topic_opened'      )        );
		add_action( 'bbp_spammed_topic',            array( $this, 'topic_spammed'     )        );
		add_action( 'bbp_unspammed_topic',          array( $this, 'topic_unspammed'   )        );
		add_action( 'bbp_sticked_topic',            array( $this, 'topic_sticked'     ), 10, 3 );
		add_action( 'bbp_unsticked_topic',          array( $this, 'topic_unsticked'   ), 10, 2 );
		add_action( 'bbp_deleted_topic',            array( $this, 'topic_deleted'     )        ); // ..
		add_action( 'bbp_trashed_topic',            array( $this, 'topic_trashed'     )        ); // ..
		add_action( 'bbp_untrashed_topic',          array( $this, 'topic_untrashed'   )        ); // ..

		// Topic Tag
		add_action( 'bbp_update_topic_tag',         array( $this, 'topic_tag_updated' ), 10, 4 );
		add_action( 'bbp_merge_topic_tag',          array( $this, 'topic_tac_merged'  ), 10, 3 );
		add_action( 'bbp_delete_topic_tag',         array( $this, 'topic_tag_deleted' ), 10, 2 );

		// Reply
		add_action( 'bbp_new_reply',                array( $this, 'reply_created'     ), 10, 5 ); // Covered in core Simple History
		add_action( 'bbp_edit_reply',               array( $this, 'reply_edited'      ), 10, 6 ); // ..
		add_action( 'bbp_spammed_reply',            array( $this, 'reply_spammed'     )        );
		add_action( 'bbp_unspammed_reply',          array( $this, 'reply_unspammed'   )        );
		add_action( 'bbp_deleted_reply',            array( $this, 'reply_deleted'     )        ); // ..
		add_action( 'bbp_trashed_reply',            array( $this, 'reply_trashed'     )        ); // ..
		add_action( 'bbp_untrashed_reply',          array( $this, 'reply_untrashed'   )        ); // ..

		// User
		add_action( 'bbp_add_user_favorite',        array( $this, 'user_favorited'    ), 10, 2 );
		add_action( 'bbp_remove_user_favorite',     array( $this, 'user_unfavorited'  ), 10, 2 );
		add_action( 'bbp_add_user_subscription',    array( $this, 'user_subscribed'   ), 10, 2 );
		add_action( 'bbp_remove_user_subscription', array( $this, 'user_unsubscribed' ), 10, 2 );
		// add_action( 'bbp_profile_update',           array( $this, 'user_updated'      ), 10, 2 ); // Covered in core Simple History
		// add_action( 'bbp_user_register',            array( $this, 'user_registered'   ) ); // ..
		add_filter( 'bbp_set_user_role',            array( $this, 'user_set_role'     ), 10, 3 );

	}

	/** Helpers ******************************************************/

	/**
	 * Forum logger. Requires forum ID
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 * @param string $action Log message
	 */
	function log_forum( $forum_id, $action, $desc = '' ){
		$this->log( array( 
			'action' => $action,
			'type'   => bbp_get_forum_post_type(),
			'name'   => bbp_get_forum_title( $forum_id ),
			'id'     => $forum_id,
			'desc'   => $desc
		) );
	}

	/**
	 * Topic logger. Requires topic ID
	 *
	 * @since 1.1
	 * 
	 * @todo Author can be anonymous
	 * 
	 * @param int $topic_id Topic ID
	 * @param string $action Log message
	 */
	function log_topic( $topic_id, $action, $desc = '' ){
		$this->log( array( 
			'action' => sprintf( $action,
				'%1$s', // Type
				'%2$s', // Topic
				bbp_get_topic_forum_title( $topic_id ) // Forum
			),
			'type'   => bbp_get_topic_post_type(),
			'name'   => bbp_get_topic_title( $topic_id ),
			'id'     => $topic_id,
			'desc'   => $desc
		) );
	}

	/**
	 * Topic tag logger. Requires tag array
	 *
	 * @since 1.1
	 * 
	 * @param array $tag Tag term_id and term_taxonomy_id
	 * @param string $action Log message
	 */
	function log_topic_tag( $tag, $action, $desc = '' ){
		$this->log( array( 
			'action' => $action,
			'type'   => bbp_get_topic_tag_tax_id(),
			'name'   => bbp_get_topic_tag_name( $tag ),
			'id'     => $tag['term_id'],
			'desc'   => $desc
		) );
	}

	/**
	 * Reply logger. Requires reply ID
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 * @param string $action Log message
	 */
	function log_reply( $reply_id, $action, $desc = '' ){
		$this->log( array( 
			'action' => sprintf( $action,
				'%1$s', // Reply
				'%2$s', // Topic
				bbp_get_reply_author_display_name( $reply_id ), // User
				bbp_get_forum_title( bbp_get_reply_forum_id( $reply_id ) ) // Forum
			),
			'type'   => bbp_get_reply_post_type(),
			'name'   => bbp_get_reply_topic_title( $reply_id ),
			'id'     => $reply_id,
			'desc'   => $desc
		) );
	}

	/** Forum ********************************************************/

	/**
	 * Log creating forums
	 *
	 * Hooked into bbp_new_forum action
	 *
	 * @since 1.1
	 * 
	 * @param array $forum_args Forum arguments
	 */
	public function forum_created( $forum_args ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		// Identify forum parent
		if ( 0 != $forum_args['post_parent'] ) {
			$action = sprintf( 
				// Translators: 1. Type, 2. Name, 3. Parent forum
				__('%1$s %2$s created in %3$s', 'simple-history'), 
				'%1$s', '%2$s', bbp_get_forum_title( $forum_args['post_parent'] ) 
			);
		} else {
			$action = $this->events->new;
		}

		$this->log_forum( $forum_args['forum_id'], $action );
	}

	/**
	 * Log editing forums
	 *
	 * Hooked into bbp_edit_forum action
	 *
	 * @since 1.1
	 * 
	 * @param array $forum_args Forum arguments
	 */
	public function forum_edited( $forum_args ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->log_forum( $forum_args['forum_id'], $this->events->edit );
	}

	/**
	 * Log closing forums
	 *
	 * Hooked into bbp_closed_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_closed( $forum_id ){
		$this->log_forum( $forum_id, __('%1$s %2$s closed', 'simple-history') );
	}

	/**
	 * Log opening forums
	 *
	 * Hooked into bbp_opened_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_opened( $forum_id ){
		$this->log_forum( $forum_id, __('%1$s %2$s opened', 'simple-history') );
	}

	/**
	 * Log setting forums to category type
	 *
	 * Hooked into bbp_categorized_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_categorized( $forum_id ){
		$this->log_forum( $forum_id, __('%1$s %2$s set to category type', 'simple-history') );
	}

	/**
	 * Log setting forums to normal type
	 *
	 * Hooked into bbp_normalized_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_normalized( $forum_id ){
		$this->log_forum( $forum_id, __('%1$s %2$s set to normal type', 'simple-history') );
	}

	/**
	 * Log setting forums to public
	 *
	 * Hooked into bbp_publicized_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_publicized( $forum_id ){
		if ( 'public' == bbp_get_forum_visibility( $forum_id ) )
			$this->log_forum( $forum_id, __('%1$s %2$s set to public', 'simple-history') );
	}

	/**
	 * Log setting forums to private
	 *
	 * Hooked into bbp_privatized_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_privatized( $forum_id ){
		if ( 'private' == bbp_get_forum_visibility( $forum_id ) )
			$this->log_forum( $forum_id, __('%1$s %2$s set to private', 'simple-history') );
	}

	/**
	 * Log hiding forums
	 *
	 * Hooked into bbp_hid_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_hidden( $forum_id ){
		$this->log_forum( $forum_id, __('%1$s %2$s set to hidden', 'simple-history') );
	}

	/**
	 * Log deleting forums
	 *
	 * Hooked into bbp_deleted_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_deleted( $forum_id ){
		$this->log_forum( $forum_id, $this->events->delete );
	}

	/**
	 * Log trashing forums
	 *
	 * Hooked into bbp_trashed_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_trashed( $forum_id ){
		$this->log_forum( $forum_id, $this->events->trash );
	}

	/**
	 * Log untrashing forums
	 *
	 * Hooked into bbp_untrashed_forum action
	 *
	 * @since 1.1
	 * 
	 * @param int $forum_id Forum ID
	 */
	public function forum_untrashed( $forum_id ){
		$this->log_forum( $forum_id, $this->events->untrash );
	}

	/** Topic ********************************************************/

	/**
	 * Log creating topics
	 *
	 * Hooked into bbp_new_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 * @param int $forum_id Forum ID
	 * @param array $anonymous_data Anonymous user data
	 * @param int $topic_author User ID
	 */
	public function topic_created( $topic_id, $forum_id, $anonymous_data, $topic_author ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->log_topic( $topic_id, __('%1$s %2$s created in %3$s', 'simple-history') );
	}

	/**
	 * Log editing topics
	 *
	 * Hooked into bbp_edit_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 * @param int $forum_id Forum ID
	 * @param array $anonymous_data Anonymous user data
	 * @param int $topic_author User ID
	 * @param boolean $is_edit Is topic edited
	 */
	public function topic_edited( $topic_id, $forum_id, $anonymous_data, $topic_author, $is_edit ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s edited', 'simple-history') );
	}

	/**
	 * Log merging topics
	 *
	 * Hooked into bbp_merged_topic action
	 *
	 * @since 1.1
	 *
	 * @todo Check if source topic is available
	 * 
	 * @param int $destination_topic_id Destination topic ID
	 * @param int $source_topic_id Source topic ID
	 * @param int $source_parent_id Source forum ID
	 */
	public function topic_merged( $destination_topic_id, $source_topic_id, $source_parent_id ){
		$this->log_topic( 
			$source_topic_id, 
			sprintf( 
				// Translators: 1. Type, 2. Source topic, 3. Source forum, 4. Destination topic, 
				// 5. Destination forum
				__('%1$s %2$s in %3$s merged into %4$s in %5$s', 'simple-history' ), 
				'%1$s', '%2$s', '%3$s', // Something with $source_parent_id?
				bbp_get_topic_title( $destination_topic_id ),
				bbp_get_topic_forum_title( $destination_topic_id ) 
			) 
		);
	}

	/**
	 * Log splitting topics
	 *
	 * Hooked into bbp_post_split_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $from_reply_id Reply ID
	 * @param int $source_topic_id Source topic ID
	 * @param int $destination_topic_id Destination topic ID
	 */
	public function topic_split( $from_reply_id, $source_topic_id, $destination_topic_id ){
		$this->log_topic( 
			$source_topic_id, 
			sprintf( 
				// Translators: 1. Type, 2. Source topic, 3. Source forum, 4. Destination topic, 
				// 5. Destination forum
				__('%1$s %2$s in %3$s split into %4$s in %5$s', 'simple-history'),
				'%1$s', '%2$s', '%3$s',
				bbp_get_topic_title( $destination_topic_id ),
				bbp_get_topic_forum_title( $destination_topic_id )
			)
		);
	}

	/**
	 * Log closing topics
	 *
	 * Hooked into bbp_closed_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 */
	public function topic_closed( $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s closed', 'simple-history') );
	}

	/**
	 * Log opening topics
	 *
	 * Hooked into bbp_opened_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 */
	public function topic_opened( $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s opened', 'simple-history') );
	}

	/**
	 * Log marking topics as spam
	 *
	 * Hooked into bbp_spammed_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 */
	public function topic_spammed( $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s marked as spam', 'simple-history') );
	}

	/**
	 * Log unmarking topics as spam
	 *
	 * Hooked into bbp_unspammed_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 */
	public function topic_unspammed( $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s unmarked as spam', 'simple-history') );
	}

	/**
	 * Log marking topics as (super) sticky 
	 *
	 * Hooked into bbp_sticked_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 * @param boolean $super Whether topic was super sticked
	 * @param boolean $success Sticking success
	 */
	public function topic_sticked( $topic_id, $super, $success ){
		if ( $success ){	
			if ( $super )
				$this->log_topic( $topic_id, __('%1$s %2$s in %3$s marked as super sticky', 'simple-history') );
			else
				$this->log_topic( $topic_id, __('%1$s %2$s in %3$s marked as sticky', 'simple-history') );
		}
	}

	/**
	 * Log unmarking topics as sticky
	 *
	 * Hooked into bbp_unsticked_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 * @param boolean $success Unsticking success
	 */
	public function topic_unsticked( $topic_id, $success ){
		if ( $success )
			$this->log_topic( $topic_id, __('%1$s %2$s in %3$s unmarked as sticky', 'simple-history') );
	}

	/**
	 * Log deleting topics
	 *
	 * Hooked into bbp_deleted_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 */
	public function topic_deleted( $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s deleted', 'simple-history') );
	}

	/**
	 * Log trashing topics
	 *
	 * Hooked into bbp_trashing_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 */
	public function topic_trashed( $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s trashed', 'simple-history') );
	}

	/**
	 * Log untrashing topics
	 *
	 * Hooked into bbp_untrashed_topic action
	 *
	 * @since 1.1
	 * 
	 * @param int $topic_id Topic ID
	 */
	public function topic_untrashed( $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s in %3$s untrashed', 'simple-history') );
	}

	/** Topic Tags ***************************************************/

	/**
	 * Log updating topic tags
	 *
	 * Hooked into bbp_update_topic_tag action
	 *
	 * @since 1.1
	 * 
	 * @param int $tag_id Tag ID
	 * @param array $tag Tag data
	 * @param string $name Tag name
	 * @param string $slug Tag slug
	 */
	public function topic_tag_updated( $tag_id, $tag, $name, $slug ){
		$this->log_topic_tag( $tag, $this->events->edit );
	}

	/**
	 * Log merging topic tags
	 *
	 * Hooked into bbp_merge_topic_tag
	 *
	 * @since 1.1
	 * 
	 * @param int $tag_id Topic ID
	 * @param array $to_tag Target tag data
	 * @param array $tag Source tag data
	 */
	public function topic_tac_merged( $tag_id, $to_tag, $tag ){
		$this->log_topic_tag( 
			$tag, 
			sprintf( 
				__('%1$s %2$s merged into %3$s', 'simple-history'), 
				'%1$s', '%2$s', bbp_get_topic_tag_name( $to_tag ) 
			) 
		);
	}

	/**
	 * Log deleting topic tags
	 *
	 * Hooked into bbp_delete_topic_tag
	 *
	 * @since 1.1
	 * 
	 * @param int $tag_id Topic ID
	 * @param array $tag Tag data
	 */
	public function topic_tag_deleted( $tag_id, $tag ){
		$this->log_topic_tag( $tag, $this->events->delete );
	}

	/** Reply ********************************************************/

	/**
	 * Log creating replies
	 *
	 * Hooked into bbp_new_reply action
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 * @param int $topic_id Topic ID
	 * @param int $forum_id Forum ID
	 * @param array $anonymous_data Anonymous user data
	 * @param int $reply_author User ID
	 */
	public function reply_created( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		// Translators: 1. Reply, 2. Topic title, 3. Author name, 4. Forum title
		$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s created', 'simple-history') );
	}

	/**
	 * Log editing replies
	 *
	 * Hooked into bbp_edit_reply action
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 * @param int $topic_id Topic ID
	 * @param int $forum_id Forum ID
	 * @param array $anonymous_data Anonymous user data
	 * @param int $reply_author User ID
	 * @param boolean $is_edit Is reply edited
	 */
	public function reply_edited( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author, $is_edit ){
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		// Translators: 1. Reply, 2. Topic title, 3. Author name, 4. Forum title
		$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s edited', 'simple-history') );
	}

	/**
	 * Log marking replies as spam
	 *
	 * Hooked into bbp_spammed_reply action
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 */
	public function reply_spammed( $reply_id ){
		// Translators: 1. Reply, 2. Topic title, 3. Author name, 4. Forum title
		$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s marked as spam', 'simple-history') );
	}

	/**
	 * Log unmarking replies as spam
	 *
	 * Hooked into bbp_unspammed_reply action
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 */
	public function reply_unspammed( $reply_id ){
		// Translators: 1. Reply, 2. Topic title, 3. Author name, 4. Forum title
		$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s unmarked as spam', 'simple-history') );
	}

	/**
	 * Log deleting replies
	 *
	 * Hooked into bbp_deleted_reply action
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 */
	public function reply_deleted( $reply_id ){
		// Translators: 1. Reply, 2. Topic title, 3. Author name, 4. Forum title
		if ( bbp_is_reply_trash( $reply_id ) )
			$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s removed from trash', 'simple-history') );
		else
			$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s deleted', 'simple-history') );
	}

	/**
	 * Log trashing replies
	 *
	 * Hooked into bbp_trashd_reply action
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 */
	public function reply_trashed( $reply_id ){
		// Translators: 1. Reply, 2. Topic title, 3. Author name, 4. Forum title
		$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s trashed', 'simple-history') );
	}

	/**
	 * Log untrashing replies
	 *
	 * Hooked into bbp_untrashed_reply action
	 *
	 * @since 1.1
	 * 
	 * @param int $reply_id Reply ID
	 */
	public function reply_untrashed( $reply_id ){
		// Translators: 1. Reply, 2. Topic title, 3. Author name, 4. Forum title
		$this->log_reply( $reply_id, __('%1$s by %3$s to %2$s in %4$s untrashed', 'simple-history') );
	}

	/** User *********************************************************/

	/**
	 * Log favoriting topics
	 *
	 * Hooked into bbp_add_user_favorite action
	 *
	 * @since 1.1
	 * 
	 * @param int $user_id User ID
	 * @param int $topic_id Topic ID
	 */
	public function user_favorited( $user_id, $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s favorited', 'simple-history') );
	}

	/**
	 * Log unfavoriting topics
	 *
	 * Hooked into bbp_remove_user_favorite action
	 *
	 * @since 1.1
	 * 
	 * @param int $user_id User ID
	 * @param int $topic_id Topic ID
	 */
	public function user_unfavorited( $user_id, $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s unfavorited', 'simple-history') );
	}

	/**
	 * Log subscribing topics
	 *
	 * Hooked into bbp_add_user_subscription action
	 *
	 * @since 1.1
	 * 
	 * @param int $user_id User ID
	 * @param int $topic_id Topic ID
	 */
	public function user_subscribed( $user_id, $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s subscribed', 'simple-history') );
	}

	/**
	 * Log unsubscribing topics
	 *
	 * Hooked into bbp_remove_user_subscription action
	 *
	 * @since 1.1
	 * 
	 * @param int $user_id User ID
	 * @param int $topic_id Topic ID
	 */
	public function user_unsubscribed( $user_id, $topic_id ){
		$this->log_topic( $topic_id, __('%1$s %2$s unsubscribed', 'simple-history') );
	}

	/**
	 * Log updating users
	 *
	 * Hooked into bbp_user_update action
	 *
	 * @since 1.1
	 * 
	 * @param int $user_id User ID
	 * @param array $old_user_data Previous user data
	 */
	public function user_updated( $user_id, $old_user_data ){
		$this->log_user( $user_id, __('%1$s %2$s profile updated', 'simple-history') );
	}

	/**
	 * Log registering users
	 *
	 * Hooked into bbp_user_register action
	 *
	 * @since 1.1
	 * 
	 * @param int $user_id User ID
	 */
	public function user_registered( $user_id ){
		$this->log_user( $user_id, __('%1$s %2$s registered', 'simple-history') );
	}

	/**
	 * Log changing forum roles
	 *
	 * Hooked into bbp_set_user_role filter
	 *
	 * @since 1.1
	 * 
	 * @todo Needs fix because removing ones role does somehow not trigger the bbp_set_user_role filter.
	 *
	 * @param string $new_role New forum role
	 * @param int $user_id User ID
	 * @param object $user User data
	 */
	public function user_set_role( $new_role, $user_id, $user ){

		// Only log if a new role was actually assigned
		if ( false !== $new_role ){
			$bbp_roles = bbp_get_dynamic_roles();

			if ( ! empty( $new_role ) ) {
				// Translators: 1. Type, 2. Name, 3. Role
				$action = sprintf( __('%1$s %2$s has now the %3$s forum role', 'simple-history'), translate_user_role( $bbp_roles[$new_role]['name'] ) );
			} else {
				$action = __('%1$s %2$s has no forum role anymore', 'simple-history');
			}

			$this->log_user( $user_id, $action );
		}

		return $new_role;
	}
}

new Simple_History_Module_BBPress();

endif; // class_exists
