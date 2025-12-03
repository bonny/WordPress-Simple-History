<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Diff_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;

/**
 * Logs changes to posts and pages, including custom post types.
 */
class Post_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimplePostLogger';

	/**
	 * Array that will contain previous post data, before data is updated.
	 *
	 * Array format is
	 * [post_id] => [post_data, post_meta].
	 *               post_data = WP_Post object, post_meta = post meta array.
	 *
	 * @var array<int, array>
	 */
	protected $old_post_data = [];

	/**
	 * Get array with information about this logger.
	 *
	 * @return array
	 */
	public function get_info() {
		return [
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
				),
			),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Save old/prev post values before post is updated.
		add_action( 'admin_action_editpost', array( $this, 'on_admin_action_editpost_save_prev_post' ) );

		// Run quick edit changes old post save with prio 0 to run before WordPress core does its thing, which is at prio 1.
		add_action( 'wp_ajax_inline-save', array( $this, 'on_admin_action_editpost_save_prev_post' ), 0 );

		// Save prev post for bulk edit.
		// Bulk edit does not use ajax (like quick edit does). Instead it's a regular GET request to edit.php.
		// wp function bulk_edit_posts() takes care of making changes.
		add_action( 'admin_action_edit', array( $this, 'on_admin_action_edit_save_prev_post' ) );

		// Detect regular post edits.
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );

		// Detect posts changing status from future to publish.
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status_future' ), 10, 3 );

		add_action( 'delete_post', array( $this, 'on_delete_post' ) );
		add_action( 'untrash_post', array( $this, 'on_untrash_post' ) );

		$this->add_xml_rpc_hooks();

		// Add rest hooks late to increase chance of getting all registered post types.
		add_action( 'init', array( $this, 'add_rest_hooks' ), 99 );

		add_filter( 'simple_history/rss_item_link', array( $this, 'filter_rss_item_link' ), 10, 2 );

		// This is fired from wp_after_insert_post? So that's after simple history has done it's thing.
		add_action( '_wp_put_post_revision', array( $this, 'on_wp_put_post_revision' ), 1, 2 );
	}

	/**
	 * Fired when a post is saved using save button and does have changes.
	 * Does not track autosave.
	 * This is done after simple history has logged the post change.
	 * So we need to update the context with the revision id.
	 *
	 * @param int $revision_id The revision ID.
	 * @param int $post_id The post ID.
	 */
	public function on_wp_put_post_revision( $revision_id, $post_id ) {
		// Ensure that the last_insert_id is set.
		if ( ! $this->last_insert_id ) {
			return;
		}

		// Ensure that the revision is for the same post that we just logged.
		if ( $this->last_insert_context['post_id'] !== $post_id ) {
			return;
		}

		$this->append_context(
			$this->last_insert_id,
			[
				'post_revision_id' => $revision_id,
			]
		);
	}

	/**
	 * Add hooks to catch updates via REST API, i.e. from the Gutenberg editor.
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
			add_filter( "rest_pre_insert_{$post_type->name}", array( $this, 'on_rest_pre_insert' ), 10, 2 );

			// Rest insert happens after the post has been updated: "Fires after a single post is completely created or updated via the REST API.".
			add_action( "rest_after_insert_{$post_type->name}", array( $this, 'on_rest_after_insert' ), 10, 3 );

			// Rest delete is fired "immediately after a single post is deleted or trashed via the REST API".
			add_action( "rest_delete_{$post_type->name}", array( $this, 'on_rest_delete' ), 10, 3 );
		}
	}

	/**
	 * Fired after a single post is deleted or trashed via the REST API.
	 *
	 * @param \WP_Post          $post     The deleted or trashed post.
	 * @param \WP_REST_Response $response The response data.
	 * @param \WP_REST_Request  $request  The request sent to the API.

	 */
	public function on_rest_delete( $post, $response, $request ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! $this->ok_to_log_post_posttype( $post ) ) {
			return;
		}

		$this->info_message(
			'post_trashed',
			[
				'post_id'    => $post->ID,
				'post_type'  => $post->post_type,
				'post_title' => $post->post_title,
			]
		);
	}

	/**
	 * Filter "rest_pre_insert_{$this->post_type}" filters a post before it is inserted via the REST API.
	 * Fired from class-wp-rest-posts-controller.php.
	 *
	 * Here we can get the old post object.
	 *
	 * @param \stdClass        $prepared_post An object representing a single post prepared
	 *                                       for inserting or updating the database, i.e. the new updated post.
	 * @param \WP_REST_Request $request       Request object.
	 * @return \stdClass $prepared_post
	 */
	public function on_rest_pre_insert( $prepared_post, $request ) {
		// $prepared_post = stdClass Object with new and modified content.
		// changes are not saved to post in db yet, so get_post( $prepared_post->ID ) will get old contents.

		// Not all posts have ID, for example attachment uploaded in block editor does not.
		if ( empty( $prepared_post->ID ) ) {
			return $prepared_post;
		}

		$this->save_prev_post_data( $prepared_post->ID );

		return $prepared_post;
	}

	/**
	 * Fires after a single post is completely created or updated via the REST API.
	 *
	 * This is fired when a post is saved:
	 * - Using the Gutenberg block editor
	 * - ...possible more times...
	 *
	 * Here we get the updated post, after it is updated in the db.
	 *
	 * @param \WP_Post         $updated_post     Inserted or updated post object.
	 * @param \WP_REST_Request $request  Request object.
	 * @param bool             $creating True when creating a post, false when updating.
	 */
	public function on_rest_after_insert( $updated_post, $request, $creating ) {
		$updated_post = get_post( $updated_post->ID );
		$post_meta    = get_post_custom( $updated_post->ID );

		$old_post       = $this->old_post_data[ $updated_post->ID ]['post_data'] ?? null;
		$old_post_meta  = $this->old_post_data[ $updated_post->ID ]['post_meta'] ?? null;
		$old_post_terms = $this->old_post_data[ $updated_post->ID ]['post_terms'] ?? null;

		// If WordPress says this is a new post being created, and we don't have old post data,
		// assume it was transitioning from auto-draft status.
		// This ensures post creation is properly detected even when old data wasn't captured.
		$old_status = $old_post ? $old_post->post_status : null;
		if ( $creating && ! $old_status ) {
			$old_status = 'auto-draft';
		}

		$args = array(
			'new_post'       => $updated_post,
			'new_post_meta'  => $post_meta,
			'new_post_terms' => wp_get_object_terms( $updated_post->ID, get_object_taxonomies( $updated_post->post_type ) ),
			'old_post'       => $old_post,
			'old_post_meta'  => $old_post_meta,
			'old_post_terms' => $old_post_terms,
			'old_status'     => $old_status,
		);

		$this->maybe_log_post_change( $args );
	}

	/**
	 * Filters to XML RPC calls needs to be added early, admin_init is to late.
	 */
	public function add_xml_rpc_hooks() {
		add_action( 'xmlrpc_call_success_blogger_newPost', array( $this, 'on_xmlrpc_newPost' ), 10, 2 );
		add_action( 'xmlrpc_call_success_mw_newPost', array( $this, 'on_xmlrpc_newPost' ), 10, 2 );

		add_action( 'xmlrpc_call_success_blogger_editPost', array( $this, 'on_xmlrpc_editPost' ), 10, 2 );
		add_action( 'xmlrpc_call_success_mw_editPost', array( $this, 'on_xmlrpc_editPost' ), 10, 2 );

		add_action( 'xmlrpc_call_success_blogger_deletePost', array( $this, 'on_xmlrpc_deletePost' ), 10, 2 );
		add_action( 'xmlrpc_call_success_wp_deletePage', array( $this, 'on_xmlrpc_deletePost' ), 10, 2 );

		add_action( 'xmlrpc_call', array( $this, 'on_xmlrpc_call' ), 10, 1 );
	}

	/**
	 * Detect when a post is deleted using a XML-RPC call.
	 * Fired from action "xmlrpc_call".
	 *
	 * @param string $method Method called.
	 */
	public function on_xmlrpc_call( $method ) {
		$arr_methods_to_act_on = array( 'wp.deletePost' );

		$raw_post_data = null;
		$message       = null;
		$context       = array();

		if ( in_array( $method, $arr_methods_to_act_on, true ) ) {
			// Setup common stuff.
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
			$raw_post_data                    = file_get_contents( 'php://input' );
			$context['wp.deletePost.xmldata'] = Helpers::json_encode( $raw_post_data );
			$message                          = new \IXR_Message( $raw_post_data );

			if ( ! $message->parse() ) {
				return;
			}

			$context['wp.deletePost.xmlrpc_message'] = Helpers::json_encode( $message );

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$context['wp.deletePost.xmlrpc_message.messageType'] = Helpers::json_encode( $message->messageType );

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$context['wp.deletePost.xmlrpc_message.methodName'] = Helpers::json_encode( $message->methodName );

			$context['wp.deletePost.xmlrpc_message.messageParams'] = Helpers::json_encode( $message->params );

			// Actions for delete post.
			if ( 'wp.deletePost' === $method ) {
				// 4 params, where the last is the post id
				if ( ! isset( $message->params[3] ) ) {
					return;
				}

				$post_ID = $message->params[3];

				$post = get_post( $post_ID );

				$context = array(
					'post_id'    => $post->ID,
					'post_type'  => get_post_type( $post ),
					'post_title' => get_the_title( $post ),
				);

				$this->info_message( 'post_trashed', $context );
			}
		}
	}

	/**
	 * Fired when posts are saved in the admin area using bulk edit.
	 */
	public function on_admin_action_edit_save_prev_post() {
		global $pagenow;

		if ( $pagenow !== 'edit.php' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_ids = array_map( 'intval', (array) ( $_GET['post'] ?? [] ) );

		foreach ( $post_ids as $one_post_id ) {
			$this->save_prev_post_data( $one_post_id );
		}
	}

	/**
	 * Save old info about a post that is going to be edited.
	 * Needed to later compare old data with new data, to detect differences.
	 *
	 * @param mixed $post_ID Post ID.
	 */
	protected function save_prev_post_data( $post_ID ) {
		$prev_post_data = get_post( $post_ID );

		if ( ! $prev_post_data instanceof \WP_Post ) {
			return;
		}

		$this->old_post_data[ $post_ID ] = [
			'post_data'  => $prev_post_data,
			'post_meta'  => get_post_custom( $post_ID ),
			'post_terms' => wp_get_object_terms( $post_ID, get_object_taxonomies( $prev_post_data->post_type ) ),
		];
	}

	/**
	 * Get and store old info about a post that is going to be edited.
	 * Needed to later compare old data with new data, to detect differences.
	 * This function is called on edit screen but before post edits are saved.
	 *
	 * Can't use the regular filters like "pre_post_update" because custom fields are already written by then.
	 *
	 * This functions is not fird when using the block editor, then we use the REST API hooks instead.
	 *
	 * @since 2.0.29
	 */
	public function on_admin_action_editpost_save_prev_post() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_ID = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0;

		if ( $post_ID === 0 ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}

		$this->save_prev_post_data( $post_ID );
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
			'post_id'    => $post->ID,
			'post_type'  => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		$this->info_message( 'post_deleted', $context );
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
			'post_id'    => $post->ID,
			'post_type'  => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		$this->info_message( 'post_updated', $context );
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
			'post_id'    => $post->ID,
			'post_type'  => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		$this->info_message( 'post_created', $context );
	}

	/**
	 * Called when a post is restored from the trash
	 * @param int $post_id Post ID.
	 */
	public function on_untrash_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $this->ok_to_log_post_posttype( $post ) ) {
			return;
		}

		$this->info_message(
			'post_restored',
			array(
				'post_id'    => $post_id,
				'post_type'  => get_post_type( $post ),
				'post_title' => get_the_title( $post ),
			)
		);
	}

	/**
	 * Fired immediately before a post is deleted from the database.
	 *
	 * @param int $post_id Post ID.
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
		if ( isset( $wp_current_filter ) && is_array( $wp_current_filter ) && in_array( 'wp_scheduled_delete', $wp_current_filter, true ) ) {
			return;
		}

		$this->info_message(
			'post_deleted',
			array(
				'post_id'    => $post_id,
				'post_type'  => get_post_type( $post ),
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
			// SecuPress logs.
			'secupress_log_action',
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
	 * @param \WP_Post|int $post Post the check.
	 *
	 * @return bool
	 */
	public function ok_to_log_post_posttype( $post ) {
		$ok_to_log      = true;
		$skip_posttypes = $this->get_skip_posttypes();

		if ( in_array( get_post_type( $post ), $skip_posttypes, true ) ) {
			$ok_to_log = false;
		}

		return $ok_to_log;
	}

	/**
	 * Maybe log a post creation, modification or deletion.
	 *
	 * Called from:
	 * - on_transition_post_status
	 * - on_rest_after_insert
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
			// "transition_post_status", when a previous post may not exist.
			'old_status',
		);

		$args = wp_parse_args( $args, $default_args );

		// Bail if needed args not set.
		if ( ! isset( $args['new_post'] ) || ! isset( $args['new_post_meta'] ) ) {
			return;
		}

		$new_status    = $args['new_post']->post_status ?? null;
		$post          = $args['new_post'];
		$new_post_data = array(
			'post_data'  => $post,
			'post_meta'  => $args['new_post_meta'],
			'post_terms' => $args['new_post_terms'],
		);

		// Set old status to status from old post with fallback to old_status variable.
		$old_status = $args['old_post']->post_status ?? null;
		$old_status = ! isset( $old_status ) && isset( $args['old_status'] ) ? $args['old_status'] : $old_status;

		$old_post      = $args['old_post'] ?? null;
		$old_post_meta = $args['old_post_meta'] ?? null;
		$old_post_data = array(
			'post_data'  => $old_post,
			'post_meta'  => $old_post_meta,
			'post_terms' => $args['old_post_terms'] ?? null,
		);

		// Default to log.
		$ok_to_log = true;

		// Calls from the WordPress ios app/jetpack comes from non-admin-area
		// i.e. is_admin() is false
		// so don't log when outside admin area.
		if ( ! is_admin() ) {
			$ok_to_log = false;
		}

		$is_autosave      = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
		$isXmlRpcRequest  = defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST;
		$isRestApiRequest =
			( defined( 'REST_API_REQUEST' ) && REST_API_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		// Except when calls are from/for Jetpack/WordPress apps.
		// seems to be jetpack/app request when $_GET["for"] == "jetpack.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $isXmlRpcRequest && isset( $_GET['for'] ) && 'jetpack' === $_GET['for'] ) {
			$ok_to_log = true;
		}

		// Also accept calls from REST API.
		// "REST_API_REQUEST" is used by Jetpack I believe.
		if ( $isRestApiRequest ) {
			$ok_to_log = true;
		}

		// When a post is transitioned from future to publish, it's done by a cron job,
		// and is_admin() is false. It's called from filter "publish_future_post".
		// Logging is done from another function, we just make double sure to not log it here.
		if ( did_action( 'publish_future_post' ) ) {
			$ok_to_log = false;
		}

		// Don't log revisions.
		if ( wp_is_post_revision( $post ) ) {
			$ok_to_log = false;
		}

		// Don't log Gutenberg saving meta boxes.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['meta-box-loader'] ) && sanitize_text_field( wp_unslash( $_GET['meta-box-loader'] ) ) ) {
			$ok_to_log = false;
		}

		if ( ! $this->ok_to_log_post_posttype( $post ) ) {
			$ok_to_log = false;
		}

		/**
		 * Filter to control logging.
		 *
		 * @param bool $ok_to_log
		 * @param string|null $new_status
		 * @param string|null $old_status
		 * @param \WP_Post $post
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
			'post_id'    => $post->ID,
			'post_type'  => get_post_type( $post ),
			'post_title' => get_the_title( $post ),
		);

		// Check if this is a post being created.
		// This includes both manual creation (auto-draft -> draft/publish)
		// and auto-save creation (auto-draft -> draft).
		$is_post_created = 'auto-draft' === $old_status && ( 'auto-draft' !== $new_status && 'inherit' !== $new_status );

		if ( $is_post_created ) {
			// Post created.
			// Add context to indicate if this was auto-created by WordPress (auto-save)
			// vs manually created by user clicking Save/Publish.
			if ( 'draft' === $new_status && $is_autosave ) {
				$context['post_auto_created'] = true;
			}

			// Capture initial post content so there's no information gap in the audit trail.
			// This is especially important for autosaved posts where the initial content
			// would otherwise be lost (first update would only show diff from autosave state).
			$context['post_new_post_content'] = $post->post_content;
			$context['post_new_post_excerpt'] = $post->post_excerpt;
			$context['post_prev_status']      = $old_status;
			$context['post_new_status']       = $new_status;

			$this->info_message( 'post_created', $context );
		} elseif ( 'auto-draft' === $new_status || ( 'new' === $old_status && 'inherit' === $new_status ) ) {
			// Post was automagically saved by WordPress but not yet created (still auto-draft).
			return;
		} elseif ( 'trash' === $new_status ) {
			// Post trashed.
			$this->info_message( 'post_trashed', $context );
		} else {
			// Existing post was updated.

			// Also add diff between previous saved data and new data.
			// Now we have both old and new post data, including custom fields, in the same format
			// So let's compare!
			$context = $this->add_post_data_diff_to_context( $context, $old_post_data, $new_post_data );

			$context['_occasionsID'] = self::class . '/' . __FUNCTION__ . "/post_updated/{$post->ID}";

			/**
			 * Modify the context saved.
			 *
			 * @param array $context
			 * @param WP_Post $post
			 */
			$context = apply_filters( 'simple_history/post_logger/post_updated/context', $context, $post );

			$this->info_message( 'post_updated', $context );
		}
	}

	/**
	 * When a post is transitioned from future to publish, it's done by a cron job,
	 * and is_admin() is false. It's called from filter "publish_future_post" however, so we can check for that.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post Post object.
	 */
	public function on_transition_post_status_future( $new_status, $old_status, $post ) {
		if ( did_action( 'publish_future_post' ) ) {
			$this->info_message(
				'post_updated',
				[
					'post_id'          => $post->ID,
					'post_type'        => get_post_type( $post ),
					'post_title'       => get_the_title( $post ),
					'post_prev_status' => $old_status,
					'post_new_status'  => $new_status,
				]
			);
		}
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
	 * @param string   $new_status One of auto-draft, inherit, draft, pending, publish, future.
	 * @param string   $old_status Same as above.
	 * @param \WP_Post $post New updated post.
	 */
	public function on_transition_post_status( $new_status, $old_status, $post ) {
		$isRestApiRequest       = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$isAutosave             = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;
		$isAutosaveCreatingPost = $isAutosave && 'auto-draft' === $old_status && 'draft' === $new_status;

		// Bail if this is a REST API request, EXCEPT for autosaves that create posts.
		// Autosaves from Gutenberg use the REST API but we want to log when they
		// transition from auto-draft to draft (which represents post creation).
		if ( $isRestApiRequest && ! $isAutosaveCreatingPost ) {
			return;
		}

		// Bail if post is not a post.
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		$old_post       = $this->old_post_data[ $post->ID ]['post_data'] ?? null;
		$old_post_meta  = $this->old_post_data[ $post->ID ]['post_meta'] ?? null;
		$old_post_terms = $this->old_post_data[ $post->ID ]['post_terms'] ?? null;

		$args = array(
			'new_post'       => $post,
			'new_post_meta'  => get_post_custom( $post->ID ),
			'new_post_terms' => wp_get_object_terms( $post->ID, get_object_taxonomies( $post->post_type ) ),
			'old_post'       => $old_post,
			'old_post_meta'  => $old_post_meta,
			'old_post_terms' => $old_post_terms,
			'old_status'     => $old_status,
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
		// Save at least 2 values for each detected value change, i.e. the old value and the new value.
		foreach ( $post_data_diff as $diff_key => $diff_values ) {
				$context[ "post_prev_{$diff_key}" ] = $diff_values['old'];
				$context[ "post_new_{$diff_key}" ]  = $diff_values['new'];

				// If post_author then get more author info,
				// because just a user ID does not get us far.
			if ( 'post_author' === $diff_key ) {
				$old_author_user = get_userdata( (int) $diff_values['old'] );
				$new_author_user = get_userdata( (int) $diff_values['new'] );

				if ( is_a( $old_author_user, 'WP_User' ) && is_a( $new_author_user, 'WP_User' ) ) {
					$context[ "post_prev_{$diff_key}/user_login" ]   = $old_author_user->user_login;
					$context[ "post_prev_{$diff_key}/user_email" ]   = $old_author_user->user_email;
					$context[ "post_prev_{$diff_key}/display_name" ] = $old_author_user->display_name;

					$context[ "post_new_{$diff_key}/user_login" ]   = $new_author_user->user_login;
					$context[ "post_new_{$diff_key}/user_email" ]   = $new_author_user->user_email;
					$context[ "post_new_{$diff_key}/display_name" ] = $new_author_user->display_name;
				}
			}
		}

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

		/**
		 * Filters the array with custom field keys to ignore.
		 *
		 * @param  array $arr_meta_keys_to_ignore Array with custom field keys to ignore.
		 * @param  array $context                 Array with context.
		 * @return array                          Filtered array with custom field keys to ignore.
		 *
		 * @since 5.8.2
		 */
		$arr_meta_keys_to_ignore = apply_filters( 'simple_history/post_logger/meta_keys_to_ignore', $arr_meta_keys_to_ignore, $context );

		$meta_changes = array(
			'added'   => array(),
			'removed' => array(),
			'changed' => array(),
		);

		$old_meta = isset( $old_post_data['post_meta'] ) ? (array) $old_post_data['post_meta'] : array();
		$new_meta = isset( $new_post_data['post_meta'] ) ? (array) $new_post_data['post_meta'] : array();

		// Add post featured thumb data.
		$context = $this->add_post_thumb_diff( $context, $old_meta, $new_meta );

		// Detect page template changes.
		// Page template is stored in _wp_page_template.
		if ( isset( $old_meta['_wp_page_template'][0] ) && isset( $new_meta['_wp_page_template'][0] ) && $old_meta['_wp_page_template'][0] !== $new_meta['_wp_page_template'][0] ) {
			// Prev page template is different from new page template,
			// store template php file name.
			$context['post_prev_page_template'] = $old_meta['_wp_page_template'][0];
			$context['post_new_page_template']  = $new_meta['_wp_page_template'][0];
			$theme_templates                    = (array) $this->get_theme_templates();

			if ( isset( $theme_templates[ $context['post_prev_page_template'] ] ) ) {
					$context['post_prev_page_template_name'] = $theme_templates[ $context['post_prev_page_template'] ];
			}

			if ( isset( $theme_templates[ $context['post_new_page_template'] ] ) ) {
					$context['post_new_page_template_name'] = $theme_templates[ $context['post_new_page_template'] ];
			}
		}

		// Remove fields that we have checked already and other that should be ignored.
		foreach ( $arr_meta_keys_to_ignore as $key_to_ignore ) {
			unset( $old_meta[ $key_to_ignore ] );
			unset( $new_meta[ $key_to_ignore ] );
		}

		// Look for added custom fields/meta.
		foreach ( $new_meta as $meta_key => $meta_value ) {
			if ( ! isset( $old_meta[ $meta_key ] ) ) {
				$meta_changes['added'][ $meta_key ] = true;
			}
		}

		// Look for changed custom fields/meta.
		foreach ( $old_meta as $meta_key => $meta_value ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			if ( isset( $new_meta[ $meta_key ] ) && json_encode( $old_meta[ $meta_key ] ) !== json_encode( $new_meta[ $meta_key ] ) ) {
				$meta_changes['changed'][ $meta_key ] = true;
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
		// private = post private.
		$old_post_has_password = ! empty( $old_data->post_password );
		$old_post_password     = $old_post_has_password ? $old_data->post_password : null;
		$old_post_status       = $old_data->post_status ?? null;

		$new_post_has_password = ! empty( $new_data->post_password );
		$new_post_password     = $new_post_has_password ? $new_data->post_password : null;
		$new_post_status       = $new_data->post_status ?? null;

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
			// = post changed to be un-password protected.
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
		// $sticky_posts = get_option('sticky_posts');.

		// Check for changes in post terms.
		$old_post_terms = $old_post_data['post_terms'] ?? [];
		$new_post_terms = $new_post_data['post_terms'] ?? [];

		// Keys to keep for each term: term_id, name, slug, term_taxonomy_id, taxonomy.
		$term_keys_to_keep = [
			'term_id',
			'name',
			'slug',
			'term_taxonomy_id',
			'taxonomy',
		];

		$old_post_terms = array_map(
			function ( $term ) use ( $term_keys_to_keep ) {
				return array_intersect_key( (array) $term, array_flip( $term_keys_to_keep ) );
			},
			$old_post_terms
		);

		$new_post_terms = array_map(
			function ( $term ) use ( $term_keys_to_keep ) {
				return array_intersect_key( (array) $term, array_flip( $term_keys_to_keep ) );
			},
			$new_post_terms
		);

		// Detect added and removed terms.
		$term_changes = [
			// Added = exists in new but not in old.
			'added'   => [],
			// Removed = exists in old but not in new.
			'removed' => [],
		];

		$term_changes['added']   = array_values( array_udiff( $new_post_terms, $old_post_terms, [ $this, 'compare_terms' ] ) );
		$term_changes['removed'] = array_values( array_udiff( $old_post_terms, $new_post_terms, [ $this, 'compare_terms' ] ) );

		// Add old and new terms to context.
		$context['post_terms_added']   = $term_changes['added'];
		$context['post_terms_removed'] = $term_changes['removed'];

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
	 * Compare function for terms to check terms by id.
	 *
	 * @param array $a Term A.
	 * @param array $b Term B.
	 */
	private function compare_terms( $a, $b ) {
		return $a['term_id'] <=> $b['term_id'];
	}

	/**
	 * Return the current theme templates.
	 * Template will return untranslated.
	 * Uses the same approach as in class-wp-theme.php to get templates.
	 *
	 * @since 2.0.29
	 */
	public function get_theme_templates() {
		$theme          = wp_get_theme();
		$page_templates = array();

		$files = (array) $theme->get_files( 'php', 1 );

		foreach ( $files as $file => $full_path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
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
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Loose comparison intentional to avoid false diffs when types differ.
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
	public function get_log_row_plain_text_output( $row ) {
		$context = $row->context;
		$post_id = $context['post_id'] ?? 0;

		// Default to original log message.
		$message = $row->message;

		// Check if post still is available.
		// It will return a WP_Post Object if post still is in system.
		// If post is deleted from trash (not just moved there), then null is returned.
		$post              = get_post( $post_id );
		$post_is_available = is_a( $post, 'WP_Post' );

		$message_key = $context['_message_key'] ?? null;

		// Try to get singular name.
		$post_type     = $context['post_type'] ?? '';
		$post_type_obj = get_post_type_object( $post_type );
		if ( ! is_null( $post_type_obj ) && ! empty( $post_type_obj->labels->singular_name ) ) {
			$context['post_type'] = strtolower( $post_type_obj->labels->singular_name );
		}

		// Only try to get edit link if post is available. This _may_ fix some issues with edit links in
		// for example old versions of WPML.
		$context['edit_link'] = $post_is_available ? get_edit_post_link( $post_id ) : null;

		// If post is not available any longer then we can't link to it, so keep plain message then.
		// Also keep plain format if user is not allowed to edit post (edit link is empty).
		if ( $post_is_available && $context['edit_link'] ) {
			if ( 'post_updated' === $message_key ) {
				$message = __( 'Updated {post_type} <a href="{edit_link}">"{post_title}"</a>', 'simple-history' );
			} elseif ( 'post_deleted' === $message_key ) {
				$message = __( 'Deleted {post_type} "{post_title}"', 'simple-history' );
			} elseif ( 'post_created' === $message_key ) {
				$message = __( 'Created {post_type} <a href="{edit_link}">"{post_title}"</a>', 'simple-history' );
			} elseif ( 'post_trashed' === $message_key ) {
				// While in trash we can still get actions to delete or restore if we follow the edit link.
				$message = __(
					'Moved {post_type} <a href="{edit_link}">"{post_title}"</a> to the trash',
					'simple-history'
				);
			}
		}

		$context['post_type']  = isset( $context['post_type'] ) ? esc_html( $context['post_type'] ) : '';
		$context['post_title'] = isset( $context['post_title'] ) ? esc_html( $context['post_title'] ) : '';

		return helpers::interpolate( $message, $context, $row );
	}

	/**
	 * Get details output for row.
	 *
	 * @param object $row Row data.
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'];

		$out = '';

		if ( 'post_updated' === $message_key ) {
			// Check for keys like "post_prev_post_title" and "post_new_post_title".
			$diff_table_output = '';
			$has_diff_values   = false;

			foreach ( $context as $key => $val ) {

				// Skip some context keys.
				$keys_to_skip = [];

				// Skip post author because we manually output the change already.
				$keys_to_skip = [ 'post_author/user_login', 'post_author/user_email', 'post_author/display_name' ];

				if ( strpos( $key, 'post_prev_' ) !== false ) {
					// Old value exists, new value must also exist for diff to be calculates.
					$key_to_diff = substr( $key, strlen( 'post_prev_' ) );

					$key_for_new_val = "post_new_{$key_to_diff}";

					// Skip some keys.
					if ( in_array( $key_to_diff, $keys_to_skip, true ) ) {
						continue;
					}

					if ( isset( $context[ $key_for_new_val ] ) ) {
						$post_old_value = $context[ $key ];
						$post_new_value = $context[ $key_for_new_val ];
						// phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Loose comparison intentional to avoid false diffs when types differ.
						if ( $post_old_value != $post_new_value ) {
							// Different diffs for different keys.
							if ( 'post_title' === $key_to_diff ) {
								$has_diff_values = true;
								$label           = __( 'Title', 'simple-history' );

								$diff_table_output .= sprintf(
									'<tr><td>%1$s</td><td>%2$s</td></tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									helpers::text_diff( $post_old_value, $post_new_value )
								);
							} elseif ( 'post_content' === $key_to_diff ) {
								// Problem: to much text/content.
								// Risks to fill the visual output.
								// Maybe solution: use own diff function, that uses none or few context lines.
								$has_diff_values = true;
								$label           = __( 'Content', 'simple-history' );
								$key_text_diff   = helpers::text_diff( $post_old_value, $post_new_value );

								if ( $key_text_diff ) {
									$diff_table_output .= sprintf(
										'<tr><td>%1$s</td><td>%2$s</td></tr>',
										$this->label_for( $key_to_diff, $label, $context ),
										$key_text_diff
									);
								}
							} elseif ( 'post_status' === $key_to_diff ) {
								$has_diff_values    = true;
								$label              = __( 'Status', 'simple-history' );
								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>Changed from %2$s to %3$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									esc_html( $post_old_value ),
									esc_html( $post_new_value )
								);
							} elseif ( 'post_date' === $key_to_diff ) {
								$has_diff_values = true;
								$label           = __( 'Publish date', 'simple-history' );

								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>Changed from %2$s to %3$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									esc_html( $post_old_value ),
									esc_html( $post_new_value )
								);
							} elseif ( 'post_name' === $key_to_diff ) {
								$has_diff_values = true;
								$label           = __( 'Permalink', 'simple-history' );

								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>%2$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									helpers::text_diff( $post_old_value, $post_new_value )
								);
							} elseif ( 'comment_status' === $key_to_diff ) {
								$has_diff_values = true;
								$label           = __( 'Comment status', 'simple-history' );

								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>Changed from %2$s to %3$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									esc_html( $post_old_value ),
									esc_html( $post_new_value )
								);
							} elseif ( 'post_author' === $key_to_diff ) {
								$has_diff_values = true;

								// wp post edit screen uses display_name so we should use it too.
								if (
									isset( $context['post_prev_post_author/display_name'] ) &&
									isset( $context['post_new_post_author/display_name'] )
								) {
									$prev_user_display_name = $context['post_prev_post_author/display_name'];
									$new_user_display_name  = $context['post_new_post_author/display_name'];

									$prev_user_user_email = $context['post_prev_post_author/user_email'];
									$new_user_user_email  = $context['post_new_post_author/user_email'];

									$label              = __( 'Author', 'simple-history' );
									$diff_table_output .= sprintf(
										'<tr>
											<td>%1$s</td>
											<td>%2$s</td>
										</tr>',
										$this->label_for( $key_to_diff, $label, $context ),
										helpers::interpolate(
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
							} elseif ( 'page_template' === $key_to_diff ) {
								// page template filename.
								$prev_page_template = $context['post_prev_page_template'];
								$new_page_template  = $context['post_new_page_template'];

								// page template name, should exist, but I guess someone could have deleted a template
								// and after that change the template for a post.
								$prev_page_template_name = $context['post_prev_page_template_name'] ?? '';
								$new_page_template_name  = $context['post_new_page_template_name'] ?? '';

								// If prev och new template is "default" then use that as name.
								if ( 'default' === $prev_page_template && ! $prev_page_template_name ) {
									$prev_page_template_name = $prev_page_template;
								} elseif ( 'default' === $new_page_template && ! $new_page_template_name ) {
									$new_page_template_name = $new_page_template;
								}

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

								$label              = __( 'Template', 'simple-history' );
								$diff_table_output .= sprintf(
									'<tr>
										<td>%1$s</td>
										<td>%2$s</td>
									</tr>',
									$this->label_for( $key_to_diff, $label, $context ),
									helpers::interpolate(
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
							}
						}
					}
				}
			}

			if (
				isset( $context['post_meta_added'] ) ||
				isset( $context['post_meta_removed'] ) ||
				isset( $context['post_meta_changed'] )
			) {
				$meta_changed_out = '';
				$has_diff_values  = true;

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

			// Changed terms.
			$diff_table_output .= $this->get_log_row_details_output_for_post_terms( $context, 'added' );
			$diff_table_output .= $this->get_log_row_details_output_for_post_terms( $context, 'removed' );

			// Changed post thumb/featured image.
			// post_prev_thumb, int of prev thumb, empty if not prev thumb.
			// post_new_thumb, int of new thumb, empty if no new thumb.
			$diff_table_output .= $this->get_log_row_details_output_for_post_thumb( $context );

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
		} elseif ( 'post_created' === $message_key ) {
			// Show initial post content for created posts using Event_Details classes.
			// The Event Details system will automatically read values from context.
			// Using diff table formatter for consistency with post_updated display.
			$event_details_group = new Event_Details_Group();
			$event_details_group->set_formatter( new Event_Details_Group_Diff_Table_Formatter() );
			$event_details_group->add_items(
				[
					new Event_Details_Item(
						'post_new_post_content',
						__( 'Content', 'simple-history' )
					),
					new Event_Details_Item(
						'post_new_post_excerpt',
						__( 'Excerpt', 'simple-history' )
					),
					new Event_Details_Item(
						'post_new_status',
						__( 'Status', 'simple-history' )
					),
				]
			);

			return $event_details_group;
		}

		return $out;
	}

	/**
	 * Modify the label for a key.
	 *
	 * @param string $key Key.
	 * @param string $label Label.
	 * @param array  $context Context.
	 * @return string
	 */
	protected function label_for( $key, $label, $context ) {
		/**
		 * Filters the label for a key.
		 *
		 * @param string $label Label.
		 * @param string $key Key.
		 * @param array  $context Context.
		 * @return string
		 */
		return apply_filters( 'simple_history/post_logger/label_for_key', $label, $key, $context );
	}

	/**
	 * Get extra diff record.
	 *
	 * @param string $key Key.
	 * @param string $old_value Old value.
	 * @param string $new_value New value.
	 * @return string
	 */
	public function extra_diff_record( $key, $old_value, $new_value ) {
		return sprintf( '<tr><td>%1$s</td><td>%2$s</td></tr>', $key, helpers::text_diff( $old_value, $new_value ) );
	}

	/**
	 * Modify RSS links to they go directly to the correct post in WP admin.
	 *
	 * @since 2.0.23
	 * @param string $link Link.
	 * @param object $row Row.
	 */
	public function filter_rss_item_link( $link, $row ) {
		if ( $row->logger !== $this->get_slug() ) {
			return $link;
		}

		if ( isset( $row->context['post_id'] ) ) {
			$link = add_query_arg(
				array(
					'action' => 'edit',
					'post'   => $row->context['post_id'],
				),
				admin_url( 'post.php' )
			);
		}

		return $link;
	}

	/**
	 * Add diff for post thumb/post featured image.
	 *
	 * @param array $context Context.
	 * @param array $old_meta Old meta.
	 * @param array $new_meta New meta.
	 * @return array Maybe modified context.
	 */
	public function add_post_thumb_diff( $context, $old_meta, $new_meta ) {
		$prev_post_thumb_id = null;
		$new_post_thumb_id  = null;

		// If it was changed from one image to another.
		if ( isset( $old_meta['_thumbnail_id'][0] ) && isset( $new_meta['_thumbnail_id'][0] ) ) {
			if ( $old_meta['_thumbnail_id'][0] !== $new_meta['_thumbnail_id'][0] ) {
				$prev_post_thumb_id = $old_meta['_thumbnail_id'][0];
				$new_post_thumb_id  = $new_meta['_thumbnail_id'][0];
			}
		} elseif ( isset( $old_meta['_thumbnail_id'][0] ) ) {
			// Featured image id did not exist on both new and old data. But on any?
			$prev_post_thumb_id = $old_meta['_thumbnail_id'][0];
		} elseif ( isset( $new_meta['_thumbnail_id'][0] ) ) {
				$new_post_thumb_id = $new_meta['_thumbnail_id'][0];
		}

		if ( $prev_post_thumb_id ) {
			$context['post_prev_thumb_id']    = $prev_post_thumb_id;
			$context['post_prev_thumb_title'] = get_the_title( $prev_post_thumb_id );
		}

		if ( $new_post_thumb_id ) {
			$context['post_new_thumb_id']    = $new_post_thumb_id;
			$context['post_new_thumb_title'] = get_the_title( $new_post_thumb_id );
		}

		return $context;
	}

	/**
	 * Add keys to diff.
	 *
	 * @param array $arr_keys_to_diff Array with keys to diff.
	 * @return array
	 */
	protected function add_keys_to_diff( $arr_keys_to_diff ) {
		/**
		 * Filters the keys to diff.
		 *
		 * @param array $arr_keys_to_diff Array with keys to diff.
		 * @return array
		 */
		return apply_filters( 'simple_history/post_logger/keys_to_diff', $arr_keys_to_diff );
	}

	/**
	 * Get the HTML output for context that contains modified post meta.
	 *
	 * @param array  $context Context that may contains prev- and new thumb ids.
	 * @param string $type Type of meta change, "added" or "removed".
	 * @return string HTML to be used in keyvale table.
	 */
	private function get_log_row_details_output_for_post_terms( $context = [], $type = 'added' ) {
		// Bail if type is not added or removed.
		if ( ! in_array( $type, [ 'added', 'removed' ], true ) ) {
			return '';
		}

		$post_terms = json_decode( $context[ "post_terms_{$type}" ] ?? '' ) ?? null;

		// Bail if no terms.
		if ( $post_terms === null || sizeof( $post_terms ) === 0 ) {
			return '';
		}

		if ( $type === 'added' ) {
			$label = _n(
				'Added term',
				'Added terms',
				sizeof( $post_terms ),
				'simple-history'
			);
		} elseif ( $type === 'removed' ) {
			$label = _n(
				'Removed term',
				'Removed terms',
				sizeof( $post_terms ),
				'simple-history'
			);
		}

		$terms_values = [];
		foreach ( $post_terms as $term ) {
			$taxonomy_name  = get_taxonomy( $term->taxonomy )->labels->singular_name ?? '';
			$terms_values[] = sprintf(
				'%1$s (%2$s)',
				$term->name,
				$taxonomy_name,
			);
		}

		$term_added_values_as_comma_separated_list = wp_sprintf(
			'%l',
			$terms_values
		);

		$diff_table_output = sprintf(
			'<tr>
				<td>%1$s</td>
				<td>%2$s</td>
			</tr>',
			esc_html( $label ),
			esc_html( $term_added_values_as_comma_separated_list ),
		);

		return $diff_table_output;
	}

	/**
	 * Get the HTML output for context that contains a modified post thumb.
	 *
	 * @param array $context Context that may contains prev- and new thumb ids.
	 * @return string HTML to be used in keyvale table.
	 */
	private function get_log_row_details_output_for_post_thumb( $context = null ) {
		$out = '';

		if ( ! empty( $context['post_prev_thumb_id'] ) || ! empty( $context['post_new_thumb_id'] ) ) {
			// Check if images still exists and if so get their thumbnails.
			$prev_thumb_id         = empty( $context['post_prev_thumb_id'] ) ? null : $context['post_prev_thumb_id'];
			$new_thumb_id          = empty( $context['post_new_thumb_id'] ) ? null : $context['post_new_thumb_id'];
			$post_new_thumb_title  = empty( $context['post_new_thumb_title'] ) ? null : $context['post_new_thumb_title'];
			$post_prev_thumb_title = empty( $context['post_prev_thumb_title'] )
				? null
				: $context['post_prev_thumb_title'];

			$prev_attached_file = get_attached_file( $prev_thumb_id );
			$prev_thumb_src     = wp_get_attachment_image_src( $prev_thumb_id, 'small' );

			$new_attached_file = get_attached_file( $new_thumb_id );
			$new_thumb_src     = wp_get_attachment_image_src( $new_thumb_id, 'small' );

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
				$new_thumb_html = sprintf( '<div>%1$s</div>', esc_html( $post_new_thumb_title ) );
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
		}

		return $out;
	}
}
