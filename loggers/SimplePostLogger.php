<?php

defined( 'ABSPATH' ) || die();

/**
 * Todo/@HERE
 * - [ ] install and test with ACF again
 *   - Install 5.7.13 and then each save or preview results in 2 or 3 adds to the log.
 *   - The second save saves all the post meta. So it's technically two saves but not for the user.
 *  Both requests have the same HTTP_X_WP_NONCE
 * - [ ] test REST API update from curl or similar
 * - [ ] test REST API from Android/Ios-apps
 * - [ ] Save auto-saves? Not done by user but still done...
 */

/**
 * Logs changes to posts and pages, including custom post types
 */
class SimplePostLogger extends SimpleLogger {

	// The logger slug. Defaulting to the class name is nice and logical I think.
	public $slug = __CLASS__;

	// Array that will contain previous post data, before data is updated.
	// Array format is
	// [post_id] => [post_data, post_meta].
	// post_data = WP_Post object, post_meta = post meta array.
	protected $old_post_data = array();

	public function loaded() {
		add_action( 'admin_action_editpost', array( $this, 'on_admin_action_editpost' ) );
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'on_delete_post' ) );
		add_action( 'untrash_post', array( $this, 'on_untrash_post' ) );

		$this->add_xml_rpc_hooks();

		// Add rest hooks late to increase chance of getting all registered post types.
		add_action( 'init', array( $this, 'add_rest_hooks' ), 99 );

		add_filter( 'simple_history/rss_item_link', array( $this, 'filter_rss_item_link' ), 10, 2 );
	}

	/**
	 * Add hooks to catch updates via REST API, i.e. the new Gutenberg editor.
	 */
	public function add_rest_hooks() {
		/**
		 * Filter the post types we are logging information from.
		 *
		 * @param array $post_types Core, public and private post types.
		 * @return array $post_types Filtered post types.
		 *
		 * @since 2.37
		 */
		$post_types = apply_filters( 'simple_history/post_logger/post_types', get_post_types( array(), 'object' ) );

		// Add actions for each post type.
		foreach ( $post_types as $post_type ) {
			// class-wp-rest-posts-controller.php fires two actions in
			// the update_item() method: pre_insert and after_insert.

			// Rest pre insert is fired before an updated post is inserted into db.
			add_action( "rest_pre_insert_{$post_type->name}", array( $this, 'on_rest_pre_insert' ), 10, 2 );

			// Rest insert happens after the post has been updated: "Fires after a single post is completely created or updated via the REST API."
			add_action( "rest_after_insert_{$post_type->name}", array( $this, 'on_rest_after_insert' ), 10, 3 );
		}
	}

	/**
	 * Filter "rest_pre_insert_{$this->post_type}" filters a post before it is inserted via the REST API.
	 * Fired from class-wp-rest-posts-controller.php.
	 *
	 * Here we can get the old post object.
	 *
	 * @param stdClass        $prepared_post An object representing a single post prepared
	 *                                       for inserting or updating the database, i.e. the new updated post.
	 * @param WP_REST_Request $request       Request object.
	 * @return stdClass $prepared_post
	 */
	public function on_rest_pre_insert( $prepared_post, $request ) {
		// $prepared_post = stdClass Object with new and modified content.
		// changes are not saved to post in db yet, so get_post( $prepared_post->ID ) will get old contents.

		// Not all posts have ID, for example attachment uploaded in block editor does not.
		if ( empty( $prepared_post->ID ) ) {
			return $prepared_post;
		}

		// $old_post = post with old content and old meta
		$old_post = get_post( $prepared_post->ID );

		$this->old_post_data[ $old_post->ID ] = array(
			'post_data' => $old_post,
			'post_meta' => get_post_custom( $old_post->ID ),
		);

		return $prepared_post;
	}

	/**
	 * Fires after a single post is completely created or updated via the REST API.
	 *
	 * Here we get the updated post, after it is updated in the db.
	 *
	 * @param WP_Post         $updated_post     Inserted or updated post object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating True when creating a post, false when updating.
	 */
	public function on_rest_after_insert( $updatedPost, $request, $creating ) {
		$updatedPost = get_post( $updatedPost->ID );
		$post_meta = get_post_custom( $updatedPost->ID );

		$old_post = isset( $this->old_post_data[ $updatedPost->ID ] ) ? $this->old_post_data[ $updatedPost->ID ]['post_data'] : null;
		$old_post_meta = isset( $this->old_post_data[ $updatedPost->ID ] ) ? $this->old_post_data[ $updatedPost->ID ]['post_meta'] : null;

		$args = array(
			'new_post' => $updatedPost,
			'new_post_meta' => $post_meta,
			'old_post' => $old_post,
			'old_post_meta' => $old_post_meta,
			'old_status' => $old_post ? $old_post->post_status : null,
			'_debug_caller_method' => __METHOD__,
		);

		$this->maybe_log_post_change( $args );
	}

	/**
	 * Filters to XML RPC calls needs to be added early, admin_init is to late.
	 */
	public function add_xml_rpc_hooks() {
		// Debug: log all XML-RPC requests
		/*
		add_action("xmlrpc_call", function($method) {
			SimpleLogger()->debug("XML-RPC call for method '{method}'", array("method" => $method));
		}, 10, 1);
		*/

		add_action( 'xmlrpc_call_success_blogger_newPost', array( $this, 'on_xmlrpc_newPost' ), 10, 2 );
		add_action( 'xmlrpc_call_success_mw_newPost', array( $this, 'on_xmlrpc_newPost' ), 10, 2 );

		add_action( 'xmlrpc_call_success_blogger_editPost', array( $this, 'on_xmlrpc_editPost' ), 10, 2 );
		add_action( 'xmlrpc_call_success_mw_editPost', array( $this, 'on_xmlrpc_editPost' ), 10, 2 );

		add_action( 'xmlrpc_call_success_blogger_deletePost', array( $this, 'on_xmlrpc_deletePost' ), 10, 2 );
		add_action( 'xmlrpc_call_success_wp_deletePage', array( $this, 'on_xmlrpc_deletePost' ), 10, 2 );

		add_action( 'xmlrpc_call', array( $this, 'on_xmlrpc_call' ), 10, 1 );
	}

	public function on_xmlrpc_call( $method ) {
		$arr_methods_to_act_on = array( 'wp.deletePost' );

		$raw_post_data = null;
		$message = null;
		$context = array();

		if ( in_array( $method, $arr_methods_to_act_on ) ) {
			// Setup common stuff
			$raw_post_data = file_get_contents( 'php://input' );
			$context['wp.deletePost.xmldata'] = $this->simpleHistory->json_encode( $raw_post_data );
			$message = new IXR_Message( $raw_post_data );

			if ( ! $message->parse() ) {
				return;
			}

			$context['wp.deletePost.xmlrpc_message'] = $this->simpleHistory->json_encode( $message );
			$context['wp.deletePost.xmlrpc_message.messageType'] = $this->simpleHistory->json_encode(
				$message->messageType
			);
			$context['wp.deletePost.xmlrpc_message.methodName'] = $this->simpleHistory->json_encode(
				$message->methodName
			);
			$context['wp.deletePost.xmlrpc_message.messageParams'] = $this->simpleHistory->json_encode(
				$message->params
			);

			// Actions for delete post
			if ( 'wp.deletePost' == $method ) {
				// 4 params, where the last is the post id
				if ( ! isset( $message->params[3] ) ) {
					return;
				}

				$post_ID = $message->params[3];

				$post = get_post( $post_ID );

				$context = array(
					'post_id' => $post->ID,
					'post_type' => get_post_type( $post ),
					'post_title' => get_the_title( $post ),
				);

				$this->infoMessage( 'post_trashed', $context );
			}
		} // End if().
	}

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function getInfo() {
		$arr_info = array(
			'name'        => __( 'Post Logger', 'simple-history' ),
			'description' => __( 'Logs the creation and modification of posts and pages', 'simple-history' ),
			'capability'  => 'edit_pages',
			'messages'    => array(
				'post_created'  => __( 'Created {post_type} "{post_title}"', 'simple-history' ),
				'post_updated'  => __( 'Updated {post_type} "{post_title}"', 'simple-history' ),
				'post_restored' => __( 'Restored {post_type} "{post_title}" from trash', 'simple-history' ),
				'post_deleted'  => __( 'Deleted {post_type} "{post_title}"', 'simple-history' ),
				'post_trashed'  => __( 'Moved {post_type} "{post_title}" to the trash', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Posts & Pages', 'Post logger: search', 'simple-history' ),
					'label_all' => _x( 'All posts & pages activity', 'Post logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Posts created', 'Post logger: search', 'simple-history' ) => array( 'post_created' ),
						_x( 'Posts updated', 'Post logger: search', 'simple-history' ) => array( 'post_updated' ),
						_x( 'Posts trashed', 'Post logger: search', 'simple-history' ) => array( 'post_trashed' ),
						_x( 'Posts deleted', 'Post logger: search', 'simple-history' ) => array( 'post_deleted' ),
						_x( 'Posts restored', 'Post logger: search', 'simple-history' ) => array( 'post_restored' ),
					),
				), // end search array
			), // end labels
		);

		return $arr_info;
	}

	/**
	 * Get and store old info about a post that is going to be edited.
	 * Needed to later compare old data with new data, to detect differences.
	 * This function is called on edit screen but before post edits are saved.
	 *
	 * Can't use the regular filters like "pre_post_update" because custom fields are already written by then.
	 *
	 * @since 2.0.29
	 */
	public function on_admin_action_editpost() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_ID = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;

		if ( ! $post_ID ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}

		$prev_post_data = get_post( $post_ID );

		if ( is_wp_error( $prev_post_data ) ) {
			return;
		}

		$this->old_post_data[ $post_ID ] = array(
			'post_data' => $prev_post_data,
			'post_meta' => get_post_custom( $post_ID ),
		);
	}

	/**
	 * Fires after a post has been successfully deleted via the XML-RPC Blogger API.
	 *
	 * @since 2.0.21
	 *
	 * @param int   $post_ID ID of the deleted post.
	 * @param array $args    An array of arguments to delete the post.
	 */
	public function on_xmlrpc_deletePost( $post_ID, $args ) {
		$post = get_post( $post_ID );

		$context = array(
			'post_id' => $post->ID,
			'post_type' => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		$this->infoMessage( 'post_deleted', $context );
	}

	/**
	 * Fires after a post has been successfully updated via the XML-RPC API.
	 *
	 * @since 2.0.21
	 *
	 * @param int   $post_ID ID of the updated post.
	 * @param array $args    An array of arguments for the post to edit.
	 */
	public function on_xmlrpc_editPost( $post_ID, $args ) {
		$post = get_post( $post_ID );

		$context = array(
			'post_id' => $post->ID,
			'post_type' => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		$this->infoMessage( 'post_updated', $context );
	}

	/**
	 * Fires after a new post has been successfully created via the XML-RPC API.
	 *
	 * @since 2.0.21
	 *
	 * @param int   $post_ID ID of the new post.
	 * @param array $args    An array of new post arguments.
	 */
	public function on_xmlrpc_newPost( $post_ID, $args ) {
		$post = get_post( $post_ID );

		$context = array(
			'post_id' => $post->ID,
			'post_type' => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		$this->infoMessage( 'post_created', $context );
	}

	/**
	 * Called when a post is restored from the trash
	 * @param int $post_id
	 */
	public function on_untrash_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $this->ok_to_log_post_posttype( $post ) ) {
			return;
		}

		$this->infoMessage(
			'post_restored',
			array(
				'post_id' => $post_id,
				'post_type' => get_post_type( $post ),
				'post_title' => get_the_title( $post ),
			)
		);
	}

	/**
	 * Fired immediately before a post is deleted from the database.
	 *
	 * @param int $postid Post ID.
	 */
	public function on_delete_post( $post_id ) {
		$post = get_post( $post_id );

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( $post->post_status === 'auto-draft' || $post->post_status === 'inherit' ) {
			return;
		}

		$ok_to_log = true;

		if ( ! $this->ok_to_log_post_posttype( $post ) ) {
			$ok_to_log = false;
		}

		/**
		 * Filter to control logging.
		 *
		 * @param bool $ok_to_log If this post deletion should be logged.
		 * @param int $post_id
		 *
		 * @return bool True to log, false to not log.
		 *
		 * @since 2.21
		 */
		$ok_to_log = apply_filters( 'simple_history/post_logger/post_deleted/ok_to_log', $ok_to_log, $post_id );

		if ( ! $ok_to_log ) {
			return;
		}

		/*
		Posts that have been in the trash for 30 days (default)
		are deleted using a cron job that is called with action hook "wp_scheduled_delete".
		We skip logging these because users are confused and think that the real post has been
		deleted.
		We detect this by checking $wp_current_filter for 'wp_scheduled_delete'
		[
			"wp_scheduled_delete",
			"delete_post",
			"simple_history\/log_argument\/context"
		]
		*/
		global $wp_current_filter;
		if ( isset( $wp_current_filter ) && is_array( $wp_current_filter ) ) {
			if ( in_array( 'wp_scheduled_delete', $wp_current_filter, true ) ) {
				return;
			}
		}

		$this->infoMessage(
			'post_deleted',
			array(
				'post_id' => $post_id,
				'post_type' => get_post_type( $post ),
				'post_title' => get_the_title( $post ),
			)
		);
	}

	/**
	 * Get an array of post types that should not be logged by this logger.
	 *
	 * @return Array with post type slugs to skip.
	 */
	public function get_skip_posttypes() {
		$skip_posttypes = array(
			// Don't log nav_menu_updates.
			'nav_menu_item',
			// Don't log jetpack migration-things.
			// https://wordpress.org/support/topic/updated-jetpack_migration-sidebars_widgets/.
			'jetpack_migration',
			'jp_sitemap',
			'jp_img_sitemap',
			'jp_sitemap_master',
			'attachment',
		);

		/**
		 * Filter to log what post types not to log
		 *
		 * @since 2.18
		 */
		$skip_posttypes = apply_filters( 'simple_history/post_logger/skip_posttypes', $skip_posttypes );

		return $skip_posttypes;
	}

	/**
	 * Check if post type is ok to log by logger
	 *
	 * @param Int or WP_Post $post Post the check.
	 *
	 * @return bool
	 */
	public function ok_to_log_post_posttype( $post ) {
		$ok_to_log = true;
		$skip_posttypes = $this->get_skip_posttypes();

		if ( in_array( get_post_type( $post ), $skip_posttypes, true ) ) {
			$ok_to_log = false;
		}

		return $ok_to_log;
	}

	/**
	 * Maybe log a post creation, modification or deletion.
	 *
	 * Todo:
	 * - support password protect.
	 * - post_password is set
	 *
	 * @param array $args Array with old and new post data.
	 */
	public function maybe_log_post_change( $args ) {
		$default_args = array(
			'new_post',
			'new_post_meta',
			'old_post',
			'old_post_meta',
			// Old status is included because that's the value we get in filter
			// "transation_post_status", when a previous post may not exist.
			'old_status',
		);

		$args = wp_parse_args( $args, $default_args );

		// Bail if needed args not set.
		if ( ! isset( $args['new_post'] ) || ! isset( $args['new_post_meta'] ) ) {
			return;
		}

		$new_status = isset( $args['new_post']->post_status ) ? $args['new_post']->post_status : null;
		$post = $args['new_post'];
		$new_post_data = array(
			'post_data' => $post,
			'post_meta' => $args['new_post_meta'],
		);

		// Set old status to status from old post with fallback to old_status variable.
		$old_status = isset( $args['old_post']->post_status ) ? $args['old_post']->post_status : null;
		$old_status = ! isset( $old_status ) && isset( $args['old_status'] ) ? $args['old_status'] : $old_status;

		$old_post = isset( $args['old_post'] ) ? $args['old_post'] : null;
		$old_post_meta = isset( $args['old_post_meta'] ) ? $args['old_post_meta'] : null;
		$old_post_data = array(
			'post_data' => $old_post,
			'post_meta' => $old_post_meta,
		);

		// Default to log.
		$ok_to_log = true;

		// Calls from the WordPress ios app/jetpack comes from non-admin-area
		// i.e. is_admin() is false
		// so don't log when outside admin area.
		if ( ! is_admin() ) {
			$ok_to_log = false;
		}

		// Except when calls are from/for Jetpack/WordPress apps.
		// seems to be jetpack/app request when $_GET["for"] == "jetpack.
		$isXmlRpcRequest = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
		if ( $isXmlRpcRequest && isset( $_GET['for'] ) && 'jetpack' === $_GET['for'] ) {
			$ok_to_log = true;
		}

		// Also accept calls from REST API
		$isRestApiRequest =
			( defined( 'REST_API_REQUEST' ) && REST_API_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		if ( $isRestApiRequest ) {
			$ok_to_log = true;
		}

		// Don't log revisions.
		if ( wp_is_post_revision( $post ) ) {
			$ok_to_log = false;
		}

		// Don't log Gutenberg saving meta boxes.
		if ( isset( $_GET['meta-box-loader'] ) && $_GET['meta-box-loader'] ) {
			$ok_to_log = false;
		}

		if ( ! $this->ok_to_log_post_posttype( $post ) ) {
			$ok_to_log = false;
		}

		/**
		 * Filter to control logging.
		 *
		 * @param bool $ok_to_log
		 * @param $new_status
		 * @param $old_status
		 * @param $post
		 *
		 * @return bool True to log, false to not log.
		 *
		 * @since 2.21
		 */
		$ok_to_log = apply_filters(
			'simple_history/post_logger/post_updated/ok_to_log',
			$ok_to_log,
			$new_status,
			$old_status,
			$post
		);

		if ( ! $ok_to_log ) {
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
		From something to publish = post published
		if not from & to = same, then user has changed something
		From draft to publish in future: status = "future"
		*/
		$context = array(
			'post_id' => $post->ID,
			'post_type' => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		if ( 'auto-draft' === $old_status && ( 'auto-draft' !== $new_status && 'inherit' !== $new_status ) ) {
			// Post created
			$this->infoMessage( 'post_created', $context );
		} elseif ( 'auto-draft' === $new_status || ( 'new' === $old_status && 'inherit' === $new_status ) ) {
			// Post was automagically saved by WordPress
			return;
		} elseif ( 'trash' === $new_status ) {
			// Post trashed
			$this->infoMessage( 'post_trashed', $context );
		} else {
			// Existing post was updated.

			// Also add diff between previous saved data and new data.
			if ( isset( $old_post_data ) && isset( $new_post_data ) ) {
				// Now we have both old and new post data, including custom fields, in the same format
				// So let's compare!
				$context = $this->add_post_data_diff_to_context( $context, $old_post_data, $new_post_data );
			}

			$context['_occasionsID'] = __CLASS__ . '/' . __FUNCTION__ . "/post_updated/{$post->ID}";

			/**
			 * Modify the context saved.
			 *
			 * @param array $context
			 * @param WP_Post $post
			 */
			$context = apply_filters( 'simple_history/post_logger/post_updated/context', $context, $post );

			$this->infoMessage( 'post_updated', $context );
		} // End if().
	}

	/**
	 * Fired when a post has changed status in the classical editor.
	 *
	 * It is also fired when saving from the Gutenberg editor,
	 * but it seems something is different because
	 * we can't get previously custom fields here (we only get latest values instead).
	 *
	 * Only run in certain cases,
	 * because when always enabled it catches a lots of edits made by plugins during cron jobs etc,
	 * which by definition is not wrong, but perhaps not wanted/annoying.
	 *
	 * @param string $new_status One of auto-draft, inherit, draft, pending, publish, future.
	 * @param string $old_status Same as above.
	 * @param WP_Post $post New updated post.
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ) {
		$isRestApiRequest = defined( 'REST_REQUEST' ) && REST_REQUEST;

		// Bail if this is a rest request.
		if ( $isRestApiRequest ) {
			return;
		}

		// Bail if post is not a post.
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// $old_post_data_exists = ! empty( $this->old_post_data[ $post->ID ] );

		$old_post = null;
		$old_post_meta = null;

		if ( ! empty( $this->old_post_data[ $post->ID ] ) ) {
			$old_post = $this->old_post_data[ $post->ID ]['post_data'];
			$old_post_meta = $this->old_post_data[ $post->ID ]['post_meta'];
		}

		$args = array(
			'new_post' => $post,
			'new_post_meta' => get_post_custom( $post->ID ),
			'old_post' => $old_post,
			'old_post_meta' => $old_post_meta,
			'old_status' => $old_status,
			'_debug_caller_method' => __METHOD__,
		);

		$this->maybe_log_post_change( $args );
	}

	/**
	 * Adds diff data to the context array. Is called just before the event is logged.
	 *
	 * Since 2.0.29
	 *
	 * To detect
	 *  - categories
	 *  - tags
	 *
	 * @param array $context Array with context.
	 * @param array $old_post_data Old/prev post data.
	 * @param array $new_post_data New post data.
	 * @return array $context with diff data added.
	 */
	public function add_post_data_diff_to_context( $context, $old_post_data, $new_post_data ) {
		$old_data = $old_post_data['post_data'];
		$new_data = $new_post_data['post_data'];

		// Will contain the differences.
		$post_data_diff = array();

		$arr_keys_to_diff = array(
			'post_title',
			'post_name',
			'post_content',
			'post_status',
			'menu_order',
			'post_date',
			'post_date_gmt',
			'post_excerpt',
			'comment_status',
			'ping_status',
			'post_parent', // only id, need to get context for that, like name of parent at least?
			'post_author', // only id, need to get more info for user.
		);

		$arr_keys_to_diff = $this->add_keys_to_diff( $arr_keys_to_diff );

		foreach ( $arr_keys_to_diff as $key ) {
			if ( isset( $old_data->$key ) && isset( $new_data->$key ) ) {
				$post_data_diff = $this->add_diff( $post_data_diff, $key, $old_data->$key, $new_data->$key );
			}
		}

		// If changes where detected.
		if ( $post_data_diff ) {
			// Save at least 2 values for each detected value change, i.e. the old value and the new value.
			foreach ( $post_data_diff as $diff_key => $diff_values ) {
				$context[ "post_prev_{$diff_key}" ] = $diff_values['old'];
				$context[ "post_new_{$diff_key}" ] = $diff_values['new'];

				// If post_author then get more author info,
				// because just a user ID does not get us far.
				if ( 'post_author' == $diff_key ) {
					$old_author_user = get_userdata( (int) $diff_values['old'] );
					$new_author_user = get_userdata( (int) $diff_values['new'] );

					if ( is_a( $old_author_user, 'WP_User' ) && is_a( $new_author_user, 'WP_User' ) ) {
						$context[ "post_prev_{$diff_key}/user_login" ] = $old_author_user->user_login;
						$context[ "post_prev_{$diff_key}/user_email" ] = $old_author_user->user_email;
						$context[ "post_prev_{$diff_key}/display_name" ] = $old_author_user->display_name;

						$context[ "post_new_{$diff_key}/user_login" ] = $new_author_user->user_login;
						$context[ "post_new_{$diff_key}/user_email" ] = $new_author_user->user_email;
						$context[ "post_new_{$diff_key}/display_name" ] = $new_author_user->display_name;
					}
				}
			}
		} // End if().

		// Compare custom fields.
		// Array with custom field keys to ignore because changed every time or very internal.
		$arr_meta_keys_to_ignore = array(
			'_edit_lock',
			'_edit_last',
			'_post_restored_from',
			'_wp_page_template',
			'_thumbnail_id',

			// _encloseme is added to a post when it's published. The wp-cron process should get scheduled shortly thereafter to process the post to look for enclosures.
			// https://wordpress.stackexchange.com/questions/20904/the-encloseme-meta-key-conundrum
			'_encloseme',
		);

		$meta_changes = array(
			'added' => array(),
			'removed' => array(),
			'changed' => array(),
		);

		$old_meta = isset( $old_post_data['post_meta'] ) ? (array) $old_post_data['post_meta'] : array();
		$new_meta = isset( $new_post_data['post_meta'] ) ? (array) $new_post_data['post_meta'] : array();

		// Add post featured thumb data.
		$context = $this->add_post_thumb_diff( $context, $old_meta, $new_meta );

		// Page template is stored in _wp_page_template.
		if ( isset( $old_meta['_wp_page_template'][0] ) && isset( $new_meta['_wp_page_template'][0] ) ) {
			/*
			Var is string with length 7: default
			Var is string with length 20: template-builder.php
			*/

			if ( $old_meta['_wp_page_template'][0] !== $new_meta['_wp_page_template'][0] ) {
				// Prev page template is different from new page template,
				// store template php file name.
				$context['post_prev_page_template'] = $old_meta['_wp_page_template'][0];
				$context['post_new_page_template'] = $new_meta['_wp_page_template'][0];

				$theme_templates = (array) $this->get_theme_templates();

				if ( isset( $theme_templates[ $context['post_prev_page_template'] ] ) ) {
					$context['post_prev_page_template_name'] = $theme_templates[ $context['post_prev_page_template'] ];
				}

				if ( isset( $theme_templates[ $context['post_new_page_template'] ] ) ) {
					$context['post_new_page_template_name'] = $theme_templates[ $context['post_new_page_template'] ];
				}
			}
		}

		// Remove fields that we have checked already and other that should be ignored.
		foreach ( $arr_meta_keys_to_ignore as $key_to_ignore ) {
			unset( $old_meta[ $key_to_ignore ] );
			unset( $new_meta[ $key_to_ignore ] );
		}

		// Look for added custom fields.
		foreach ( $new_meta as $meta_key => $meta_value ) {
			if ( ! isset( $old_meta[ $meta_key ] ) ) {
				$meta_changes['added'][ $meta_key ] = true;
			}
		}

		// Look for changed meta.
		foreach ( $old_meta as $meta_key => $meta_value ) {
			if ( isset( $new_meta[ $meta_key ] ) ) {
				if ( json_encode( $old_meta[ $meta_key ] ) != json_encode( $new_meta[ $meta_key ] ) ) {
					$meta_changes['changed'][ $meta_key ] = true;
				}
			}
		}

		if ( $meta_changes['added'] ) {
			$context['post_meta_added'] = count( $meta_changes['added'] );
		}

		if ( $meta_changes['removed'] ) {
			$context['post_meta_removed'] = count( $meta_changes['removed'] );
		}

		if ( $meta_changes['changed'] ) {
			$context['post_meta_changed'] = count( $meta_changes['changed'] );
		}

		// Check for changes in post visibility and post password usage and store in context.
		// publish = public
		// publish + post_password = password protected
		// private = post private
		$old_post_has_password = ! empty( $old_data->post_password );
		$old_post_password = $old_post_has_password ? $old_data->post_password : null;
		$old_post_status = isset( $old_data->post_status ) ? $old_data->post_status : null;

		$new_post_has_password = ! empty( $new_data->post_password );
		$new_post_password = $new_post_has_password ? $new_data->post_password : null;
		$new_post_status = isset( $new_data->post_status ) ? $new_data->post_status : null;

		if ( false === $old_post_has_password && 'publish' === $new_post_status && $new_post_has_password ) {
			// If updated post is published and password is set and old post did not have password set
			// = post changed to be password protected.
			$context['post_password_protected'] = true;
		} elseif (
			$old_post_has_password &&
			'publish' === $old_post_status &&
			false === $new_post_has_password &&
			'publish' === $new_post_status
		) {
			// Old post is publish and had password protection and new post is publish but no password
			// = post changed to be un-password protected
			$context['post_password_unprotected'] = true;
		} elseif ( $old_post_has_password && $new_post_has_password && $old_post_password !== $new_post_password ) {
			// If old post had password and new post has password, but passwords are note same
			// = post has changed password.
			$context['post_password_changed'] = true;
		} elseif ( 'private' === $new_post_status && 'private' !== $old_post_status ) {
			// If new status is private and old is not
			// = post is changed to be private.
			$context['post_private'] = true;
			// Also check if password was set before.
			if ( $old_post_has_password ) {
				$context['post_password_unprotected'] = true;
			}
		}

		// Todo: detect sticky.
		// Sticky is stored in option:
		// $sticky_posts = get_option('sticky_posts');

		/**
		 * Filter to control context sent to the diff output.
		 *
		 * @param array $context Array with context.
		 * @param array $old_data Old/prev post data.
		 * @param array $new_data New post data.
		 * @param array $old_meta Old/prev post meta data.
		 * @param array $new_meta New post meta data.
		 *
		 * @return array $context Array with diff data added.
		 *
		 * @since 2.36.0
		 */
		return apply_filters( 'simple_history/post_logger/context', $context, $old_data, $new_data, $old_meta, $new_meta );
	}

	/**
	 * Return the current theme templates.
	 * Template will return untranslated.
	 * Uses the same approach as in class-wp-theme.php to get templates.
	 *
	 * @since 2.0.29
	 */
	public function get_theme_templates() {
		$theme = wp_get_theme();
		$page_templates = array();

		$files = (array) $theme->get_files( 'php', 1 );

		foreach ( $files as $file => $full_path ) {
			if ( ! preg_match( '|Template Name:(.*)$|mi', file_get_contents( $full_path ), $header ) ) {
				continue;
			}
			$page_templates[ $file ] = _cleanup_header_comment( $header[1] );
		}

		return $page_templates;
	}

	/**
	 * Add diff to array if old and new values are different
	 *
	 * Since 2.0.29
	 *
	 * @param array  $post_data_diff Post data diff.
	 * @param string $key Key.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return array
	 */
	public function add_diff( $post_data_diff, $key, $old_value, $new_value ) {
		if ( $old_value != $new_value ) {
			$post_data_diff[ $key ] = array(
				'old' => $old_value,
				'new' => $new_value,
			);
		}

		return $post_data_diff;
	}

	/**
	 * Modify plain output to include link to post.
	 *
	 * @param object $row Row data.
	 */
	public function getLogRowPlainTextOutput( $row ) {
		$context = $row->context;
		$post_id = isset( $context['post_id'] ) ? $context['post_id'] : 0;

		// Default to original log message.
		$message = $row->message;

		// Check if post still is available.
		// It will return a WP_Post Object if post still is in system.
		// If post is deleted from trash (not just moved there), then null is returned.
		$post = get_post( $post_id );
		$post_is_available = is_a( $post, 'WP_Post' );

		$message_key = isset( $context['_message_key'] ) ? $context['_message_key'] : null;

		// Try to get singular name.
		$post_type = isset( $context['post_type'] ) ? $context['post_type'] : '';
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! is_null( $post_type_obj ) ) {
			if ( ! empty( $post_type_obj->labels->singular_name ) ) {
				$context['post_type'] = strtolower( $post_type_obj->labels->singular_name );
			}
		}

		$context['edit_link'] = get_edit_post_link( $post_id );

		// If post is not available any longer then we can't link to it, so keep plain message then.
		// Also keep plain format if user is not allowed to edit post (edit link is empty).
		if ( $post_is_available && $context['edit_link'] ) {
			if ( 'post_updated' == $message_key ) {
				$message = __( 'Updated {post_type} <a href="{edit_link}">"{post_title}"</a>', 'simple-history' );
			} elseif ( 'post_deleted' == $message_key ) {
				$message = __( 'Deleted {post_type} "{post_title}"', 'simple-history' );
			} elseif ( 'post_created' == $message_key ) {
				$message = __( 'Created {post_type} <a href="{edit_link}">"{post_title}"</a>', 'simple-history' );
			} elseif ( 'post_trashed' == $message_key ) {
				// While in trash we can still get actions to delete or restore if we follow the edit link.
				$message = __(
					'Moved {post_type} <a href="{edit_link}">"{post_title}"</a> to the trash',
					'simple-history'
				);
			}
		} // End if().

		$context['post_type'] = isset( $context['post_type'] ) ? esc_html( $context['post_type'] ) : '';
		$context['post_title'] = isset( $context['post_title'] ) ? esc_html( $context['post_title'] ) : '';

		return $this->interpolate( $message, $context, $row );
	}

	/**
	 * Get details output for row.
	 *
	 * @param object $row Row data.
	 */
	public function getLogRowDetailsOutput( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'];

		$out = '';

		if ( 'post_updated' == $message_key ) {
			// Check for keys like "post_prev_post_title" and "post_new_post_title".
			$diff_table_output = '';
			$has_diff_values = false;

			foreach ( $context as $key => $val ) {
				if ( strpos( $key, 'post_prev_' ) !== false ) {
					// Old value exists, new value must also exist for diff to be calculates.
					$key_to_diff = substr( $key, strlen( 'post_prev_' ) );

					$key_for_new_val = "post_new_{$key_to_diff}";

					if ( isset( $context[ $key_for_new_val ] ) ) {
						$post_old_value = $context[ $key ];
						$post_new_value = $context[ $key_for_new_val ];
						if ( $post_old_value != $post_new_value ) {
							// Different diffs for different keys.
							if ( 'post_title' == $key_to_diff ) {
								$has_diff_values = true;
								$label = __( 'Title', 'simple-history' );

								$diff_table_output .= sprintf(
									'<tr><td>%1$s</td><td>%2$s</td></tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									simple_history_text_diff( $post_old_value, $post_new_value )
								);
							} elseif ( 'post_content' == $key_to_diff ) {
								// Problem: to much text/content.
								// Risks to fill the visual output.
								// Maybe solution: use own diff function, that uses none or few context lines.
								$has_diff_values = true;
								$label = __( 'Content', 'simple-history' );
								$key_text_diff = simple_history_text_diff( $post_old_value, $post_new_value );

								if ( $key_text_diff ) {
									$diff_table_output .= sprintf(
										'<tr><td>%1$s</td><td>%2$s</td></tr>',
										$this->label_for( $key_to_diff, $label, $context ),
										$key_text_diff
									);
								}
							} elseif ( 'post_status' == $key_to_diff ) {
								$has_diff_values = true;
								$label = __( 'Status', 'simple-history' );
								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>Changed from %2$s to %3$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									esc_html( $post_old_value ),
									esc_html( $post_new_value )
								);
							} elseif ( 'post_date' == $key_to_diff ) {
								$has_diff_values = true;
								$label = __( 'Publish date', 'simple-history' );

								// $diff = new FineDiff($post_old_value, $post_new_value, FineDiff::$wordGranularity);
								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>Changed from %2$s to %3$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									esc_html( $post_old_value ),
									esc_html( $post_new_value )
								);
							} elseif ( 'post_name' == $key_to_diff ) {
								$has_diff_values = true;
								$label = __( 'Permalink', 'simple-history' );

								// $diff = new FineDiff($post_old_value, $post_new_value, FineDiff::$wordGranularity);
								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>%2$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									simple_history_text_diff( $post_old_value, $post_new_value )
								);
							} elseif ( 'comment_status' == $key_to_diff ) {
								$has_diff_values = true;
								$label = __( 'Comment status', 'simple-history' );

								// $diff = new FineDiff($post_old_value, $post_new_value, FineDiff::$wordGranularity);
								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>Changed from %2$s to %3$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									esc_html( $post_old_value ),
									esc_html( $post_new_value )
								);
							} elseif ( 'post_author' == $key_to_diff ) {
								$has_diff_values = true;

								// wp post edit screen uses display_name so we should use it too.
								if (
									isset( $context['post_prev_post_author/display_name'] ) &&
									isset( $context['post_new_post_author/display_name'] )
								) {
									$prev_user_display_name = $context['post_prev_post_author/display_name'];
									$new_user_display_name = $context['post_new_post_author/display_name'];

									$prev_user_user_email = $context['post_prev_post_author/user_email'];
									$new_user_user_email = $context['post_new_post_author/user_email'];

									$label = __( 'Author', 'simple-history' );
									$diff_table_output .= sprintf(
										'<tr>
											<td>%1$s</td>
											<td>%2$s</td>
										</tr>',
										$this->label_for( $key_to_diff, $label, $context ),
										$this->interpolate(
											__(
												'Changed from {prev_user_display_name} ({prev_user_email}) to {new_user_display_name} ({new_user_email})',
												'simple-history'
											),
											array(
												'prev_user_display_name' => esc_html( $prev_user_display_name ),
												'prev_user_email' => esc_html( $prev_user_user_email ),
												'new_user_display_name' => esc_html( $new_user_display_name ),
												'new_user_email' => esc_html( $new_user_user_email ),
											)
										)
									);
								}
							} elseif ( 'page_template' == $key_to_diff ) {
								// page template filename.
								$prev_page_template = $context['post_prev_page_template'];
								$new_page_template = $context['post_new_page_template'];

								// page template name, should exist, but I guess someone could have deleted a template
								// and after that change the template for a post.
								$prev_page_template_name = isset( $context['post_prev_page_template_name'] )
									? $context['post_prev_page_template_name']
									: '';
								$new_page_template_name = isset( $context['post_new_page_template_name'] )
									? $context['post_new_page_template_name']
									: '';

								// If prev och new template is "default" then use that as name.
								if ( 'default' == $prev_page_template && ! $prev_page_template_name ) {
									$prev_page_template_name = $prev_page_template;
								} elseif ( 'default' == $new_page_template && ! $new_page_template_name ) {
									$new_page_template_name = $new_page_template;
								}

								// @TODO: translate template names
								// $value = translate( $value, $this->get('TextDomain') );
								$message = __(
									'Changed from {prev_page_template} to {new_page_template}',
									'simple-history'
								);
								if ( $prev_page_template_name && $new_page_template_name ) {
									$message = __(
										'Changed from "{prev_page_template_name}" to "{new_page_template_name}"',
										'simple-history'
									);
								}

								$label = __( 'Template', 'simple-history' );
								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>%2$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									$this->interpolate(
										$message,
										array(
											'prev_page_template' => '<code>' . esc_html( $prev_page_template ) . '</code>',
											'new_page_template' => '<code>' . esc_html( $new_page_template ) . '</code>',
											'prev_page_template_name' => esc_html( $prev_page_template_name ),
											'new_page_template_name' => esc_html( $new_page_template_name ),
										)
									)
								);
							} else {
								$has_diff_values = true;

								$diff_table_output .= $this->extra_diff_record(
									$this->label_for( $key_to_diff, $key_to_diff, $context ),
									$post_old_value,
									$post_new_value
								);
							} // End if().
						} // End if().
					} // End if().
				} // End if().
			} // End foreach().

			if (
				isset( $context['post_meta_added'] ) ||
				isset( $context['post_meta_removed'] ) ||
				isset( $context['post_meta_changed'] )
			) {
				$meta_changed_out = '';
				$has_diff_values = true;

				if ( isset( $context['post_meta_added'] ) ) {
					$meta_changed_out .=
						"<span class='SimpleHistoryLogitem__inlineDivided'>" .
						(int) $context['post_meta_added'] .
						' added</span> ';
				}

				if ( isset( $context['post_meta_removed'] ) ) {
					$meta_changed_out .=
						"<span class='SimpleHistoryLogitem__inlineDivided'>" .
						(int) $context['post_meta_removed'] .
						' removed</span> ';
				}

				if ( isset( $context['post_meta_changed'] ) ) {
					$meta_changed_out .=
						"<span class='SimpleHistoryLogitem__inlineDivided'>" .
						(int) $context['post_meta_changed'] .
						' changed</span> ';
				}

				$diff_table_output .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>',
					esc_html( __( 'Custom fields', 'simple-history' ) ),
					$meta_changed_out
				);
			}

			/*
			$diff_table_output .= "
				<p>
					<span class='SimpleHistoryLogitem__inlineDivided'><em>Title</em> Hey there » Yo there</span>
					<span class='SimpleHistoryLogitem__inlineDivided'><em>Permalink</em> /my-permalink/ » /permalinks-rule/</span>
				</p>
				<p>
					<span class='SimpleHistoryLogitem__inlineDivided'><em>Status</em> draft » publish</span>
					<span class='SimpleHistoryLogitem__inlineDivided'><em>Publish date</em> 23:31:24 to 2015-04-11 23:31:40</span>
				</p>
			";
			*/

			// Changed post thumb/featured image.
			// post_prev_thumb, int of prev thumb, empty if not prev thumb.
			// post_new_thumb, int of new thumb, empty if no new thumb.
			$diff_table_output .= $this->getLogRowDetailsOutputForPostThumb( $context );

			/**
			 * Modify the formatted diff output of a saved/modified post
			 *
			 * @param string $diff_table_output
			 * @param array $context
			 * @return string
			 */
			$diff_table_output = apply_filters(
				'simple_history/post_logger/post_updated/diff_table_output',
				$diff_table_output,
				$context
			);

			if ( $has_diff_values || $diff_table_output ) {
				$diff_table_output =
					'<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
			}

			$out .= $diff_table_output;
		} // End if().

		return $out;
	}

	protected function label_for( $key, $label, $context ) {
		return apply_filters( 'simple_history/post_logger/label_for_key', $label, $key, $context );
	}

	public function extra_diff_record( $key, $old_value, $new_value ) {
		return sprintf( '<tr><td>%1$s</td><td>%2$s</td></tr>', $key, simple_history_text_diff( $old_value, $new_value ) );
	}

	/**
	 * Modify RSS links to they go directly to the correct post in wp admin
	 *
	 * @since 2.0.23
	 * @param string $link Link.
	 * @param object  $row Row.
	 */
	public function filter_rss_item_link( $link, $row ) {
		if ( $row->logger != $this->slug ) {
			return $link;
		}

		if ( isset( $row->context['post_id'] ) ) {
			$permalink = add_query_arg(
				array(
					'action' => 'edit',
					'post' => $row->context['post_id'],
				),
				admin_url( 'post.php' )
			);

			if ( $permalink ) {
				$link = $permalink;
			}
		}

		return $link;
	}

	/**
	 * Add diff for post thumb/post featured image
	 *
	 * @param array $context Context.
	 * @param array $old_meta Old meta.
	 * @param array $new_meta New meta.
	 * @return array Maybe modified context.
	 */
	public function add_post_thumb_diff( $context, $old_meta, $new_meta ) {
		$post_thumb_modified = false;
		$prev_post_thumb_id = null;
		$new_post_thumb_id = null;

		// If it was changed from one image to another.
		if ( isset( $old_meta['_thumbnail_id'][0] ) && isset( $new_meta['_thumbnail_id'][0] ) ) {
			if ( $old_meta['_thumbnail_id'][0] !== $new_meta['_thumbnail_id'][0] ) {
				$post_thumb_modified = true;
				$prev_post_thumb_id = $old_meta['_thumbnail_id'][0];
				$new_post_thumb_id = $new_meta['_thumbnail_id'][0];
			}
		} else {
			// Featured image id did not exist on both new and old data. But on any?
			if ( isset( $old_meta['_thumbnail_id'][0] ) ) {
				$prev_post_thumb_id = $old_meta['_thumbnail_id'][0];
			} elseif ( isset( $new_meta['_thumbnail_id'][0] ) ) {
				$new_post_thumb_id = $new_meta['_thumbnail_id'][0];
			}
		}

		if ( $prev_post_thumb_id ) {
			$context['post_prev_thumb_id'] = $prev_post_thumb_id;
			$context['post_prev_thumb_title'] = get_the_title( $prev_post_thumb_id );
		}

		if ( $new_post_thumb_id ) {
			$context['post_new_thumb_id'] = $new_post_thumb_id;
			$context['post_new_thumb_title'] = get_the_title( $new_post_thumb_id );
		}

		return $context;
	}

	protected function add_keys_to_diff( $arr_keys_to_diff ) {
		return apply_filters( 'simple_history/post_logger/keys_to_diff', $arr_keys_to_diff );
	}

	/**
	 * Get the HTML output for context that contains a modified post thumb.
	 *
	 * @param array $context Context that may contains prev- and new thumb ids.
	 * @return string HTML to be used in keyvale table.
	 */
	private function getLogRowDetailsOutputForPostThumb( $context = null ) {
		$out = '';

		if ( ! empty( $context['post_prev_thumb_id'] ) || ! empty( $context['post_new_thumb_id'] ) ) {
			// Check if images still exists and if so get their thumbnails.
			$prev_thumb_id = empty( $context['post_prev_thumb_id'] ) ? null : $context['post_prev_thumb_id'];
			$new_thumb_id = empty( $context['post_new_thumb_id'] ) ? null : $context['post_new_thumb_id'];
			$post_new_thumb_title = empty( $context['post_new_thumb_title'] ) ? null : $context['post_new_thumb_title'];
			$post_prev_thumb_title = empty( $context['post_prev_thumb_title'] )
				? null
				: $context['post_prev_thumb_title'];

			$prev_attached_file = get_attached_file( $prev_thumb_id );
			$prev_thumb_src = wp_get_attachment_image_src( $prev_thumb_id, 'small' );

			$new_attached_file = get_attached_file( $new_thumb_id );
			$new_thumb_src = wp_get_attachment_image_src( $new_thumb_id, 'small' );

			$prev_thumb_html = '';
			if ( file_exists( $prev_attached_file ) && $prev_thumb_src ) {
				$prev_thumb_html = sprintf(
					'
						<div>%2$s</div>
						<div class="SimpleHistoryLogitemThumbnail">
							<img src="%1$s" alt="">
						</div>
					',
					$prev_thumb_src[0],
					esc_html( $post_prev_thumb_title )
				);
			} else {
				// Fallback if image does not exist.
				$prev_thumb_html = sprintf( '<div>%1$s</div>', esc_html( $post_prev_thumb_title ) );
			}

			$new_thumb_html = '';
			if ( file_exists( $new_attached_file ) && $new_thumb_src ) {
				$new_thumb_html = sprintf(
					'
						<div>%2$s</div>
						<div class="SimpleHistoryLogitemThumbnail">
							<img src="%1$s" alt="">
						</div>
					',
					$new_thumb_src[0],
					esc_html( $post_new_thumb_title )
				);
			} else {
				// Fallback if image does not exist.
				$prev_thumb_html = sprintf( '<div>%1$s</div>', esc_html( $post_new_thumb_title ) );
			}

			$out .= sprintf(
				'<tr>
					<td>%1$s</td>
					<td>

						<div class="SimpleHistory__diff__contents SimpleHistory__diff__contents--noContentsCrop" tabindex="0">
						    <div class="SimpleHistory__diff__contentsInner">
						        <table class="diff SimpleHistory__diff">
						            <tr>
						                <td class="diff-deletedline">
						                    %2$s
						                </td>
						                <td>&nbsp;</td>
						                <td class="diff-addedline">
						                    %3$s
						                </td>
						            </tr>
						        </table>
						    </div>
						</div>

					</td>
				</tr>',
				esc_html( __( 'Featured image', 'simple-history' ) ), // 1
				$prev_thumb_html, // 2
				$new_thumb_html // 3
			);
		} // End if().

		return $out;
	}
}
