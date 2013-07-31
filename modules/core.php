<?php

/**
 * Simple History Modules Core Class
 *
 * Extend Simple History for WP Core events
 *
 * @since 1.3.5
 * 
 * @package Simple History
 * @subpackage Modules
 *
 * @todo Log theme customizer editing, custom header, custom background
 * @todo Log file editor changes (plugin/theme)
 * @todo Log import/export data
 * @todo Fix multisite compatibility
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simple_History_Module_Core' ) ) :

/**
 * Plugin class
 *
 */
class Simple_History_Module_Core extends Simple_History_Module {

	function __construct(){
		parent::__construct( array(
			'_builtin'    => true,
			'id'          => 'core',
			'title'       => 'WordPress',
			'description' => __('Log occurences of the main WP core events.', 'simple-history'),
			'tabs'        => array(
				'supports' => array(
					__('Creating, editing, (un)sticking, restoring and deleting a post.',       'simple-history'),
					__('Creating, editing and deleting an attachment.',                         'simple-history'),
					__('Creating, editing and deleting a navigation menu and nav menu items.',  'simple-history'),
					__('Creating, editing and deleting a comment.',                             'simple-history'),
					__('User login, failed login, logout, registering, updating and deleting.', 'simple-history'),
					__('Saving a WordPress settings page.',                                     'simple-history'),
					__('Installing, activating, updating, deactivating and deleting plugins.',  'simple-history'),
					__('Installing, switching, updating and deleting themes.',                  'simple-history'),
					__('Updating your WordPress installation.',                                 'simple-history'),
					),
				),
			)
		);
	}

	function add_events(){
		$events = array(
			// Translators: 1. Type, 2. Name, 3. Version
			'update_to_version'      => __('%1$s %2$s updated to version %3$s',                    'simple-history'),
			'update_from_version' => __('%1$s %2$s updated from version %3$s to version %4$s',  'simple-history'),
		);

		return $events;
	}

	function add_actions() {

		// Post
		add_action( 'wp_insert_post',             array( $this, 'post_created'              )        );
		add_action( 'post_updated',               array( $this, 'post_edited'               ), 10, 3 );
		add_action( 'transition_post_status',     array( $this, 'post_status_transition'    ), 10, 3 );
		add_action( 'updated_option',             array( $this, 'post_stickies'             ), 10, 3 );
		add_action( 'wp_restore_post_revision',   array( $this, 'post_restore'              ), 10, 2 );
		add_action( 'delete_post',                array( $this, 'post_delete'               )        );
		add_action( 'after_delete_post',          array( $this, 'post_deleted'              )        );

		// Attachment
		add_action( 'add_attachment',             array( $this, 'attachment_added'          )        );
		add_action( 'edit_attachment',            array( $this, 'attachment_edited'         )        );
		add_action( 'delete_attachment',          array( $this, 'attachment_delete'         )        );
		add_action( 'deleted_post',               array( $this, 'attachment_deleted'        )        );

		// Term
		add_action( 'created_term',               array( $this, 'term_created'              ), 10, 3 );
		add_action( 'edit_term',                  array( $this, 'term_edit'                 ), 10, 3 );
		add_action( 'edited_term',                array( $this, 'term_edited'               ), 10, 3 );
		add_action( 'delete_term',                array( $this, 'term_deleted'              ), 10, 4 );

		// Menu
		add_action( 'wp_create_nav_menu',         array( $this, 'menu_created'              ), 10, 2 );
		add_action( 'check_admin_referer',        array( $this, 'menu_update'               ), 10, 2 );
		add_action( 'wp_update_nav_menu',         array( $this, 'menu_edited'               ), 10, 2 );
		add_action( 'updated_option',             array( $this, 'menu_locations_edited'     ), 10, 3 );
		add_action( 'check_admin_referer',        array( $this, 'menu_delete'               ), 10, 2 );
		add_action( 'wp_delete_nav_menu',         array( $this, 'menu_deleted'              )        );
		add_action( 'post_updated',               array( $this, 'menu_item_edited'          ), 10, 3 );
		add_action( 'wp_update_nav_menu_item',    array( $this, 'menu_item_updated'         ), 10, 3 );
		add_action( 'before_delete_post',         array( $this, 'menu_item_delete'          )        );
		add_action( 'after_delete_post',          array( $this, 'menu_item_deleted'         )        );
		add_action( 'simple_history_dont_log',    array( $this, 'menu_dont_log'             ), 10, 3 );

		// Comment
		add_action( 'wp_insert_comment',          array( $this, 'comment_created'           ), 10, 2 );
		add_action( 'edit_comment',               array( $this, 'comment_edited'            )        );
		add_action( 'transition_comment_status',  array( $this, 'comment_status_transition' ), 10, 3 );
		add_action( 'delete_comment',             array( $this, 'comment_delete'            )        );
		add_action( 'deleted_comment',            array( $this, 'comment_deleted'           )        );

		// User
		add_action( 'wp_login',                   array( $this, 'user_loggedin'             )        );
		add_filter( 'wp_authenticate_user',       array( $this, 'user_authenticate'         ), 10, 2 );
		add_action( 'wp_logout',                  array( $this, 'user_loggedout'            )        );
		add_action( 'user_register',              array( $this, 'user_registered'           )        );
		add_action( 'profile_update',             array( $this, 'user_updated'              )        );
		add_action( 'delete_user',                array( $this, 'user_deleted'              )        );

		// Settings
		foreach ( array( 'general', 'writing', 'reading', 'discussion', 'media', 'privacy' ) as $page )
			add_filter( 'option_page_capability_'. $page, array( $this, 'settings_updated' ) );
		add_action( 'check_admin_referer',        array( $this, 'permalinks_updated'        ), 10, 2 );

		// Plugin
		add_filter( 'upgrader_post_install',      array( $this, 'plugin_installed'          ), 10, 3 );
		add_action( 'activated_plugin',           array( $this, 'plugin_activated'          )        );
		add_filter( 'upgrader_pre_install',       array( $this, 'plugin_update'             ), 10, 2 );
		add_filter( 'upgrader_post_install',      array( $this, 'plugin_updated'            ), 10, 3 );
		add_action( 'deactivated_plugin',         array( $this, 'plugin_deactivated'        )        );
		add_action( 'check_admin_referer',        array( $this, 'plugin_delete'             ), 10, 2 );
		add_action( 'delete_transient_plugins_delete_result', array( $this, 'plugin_deleted' )      );

		// Theme
		add_filter( 'upgrader_post_install',      array( $this, 'theme_installed'           ), 10, 3 );
		add_action( 'after_switch_theme',         array( $this, 'theme_switched'            )        );
		add_filter( 'upgrader_pre_install',       array( $this, 'theme_update'              ), 10, 3 );
		add_filter( 'upgrader_post_install',      array( $this, 'theme_updated'             ), 10, 3 );
		add_action( 'check_admin_referer',        array( $this, 'theme_delete'              ), 10, 2 );
		add_action( 'deleted_site_transient',     array( $this, 'theme_deleted'             )        );

		// Core
		add_action( '_core_updated_successfully', array( $this, 'core_updated'              )        );
	}

	/** Post *********************************************************/

	/**
	 * Log creating posts
	 *
	 * Hooked into wp_insert_post action in favor of save_post action.
	 * 
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID
	 */
	public function post_created( $post_id ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		// Ignore updated posts
		if ( isset( $GLOBALS['simple_history-update_post'] ) ) {
			unset( $GLOBALS['simple_history-update_post'] );
			return;
		}

		if ( simple_history_dont_log( 'post.post_created', array( 'post_id' => $post_id ) ) )
			return;

		$this->log_post( $post_id, $this->events->new );
	}

	/**
	 * Log updating posts
	 *
	 * Hooked into post_updated action.
	 * 
	 * @since 1.3.5
	 * 
	 * @param int $post_id Post ID
	 * @param object $post_after New post data
	 * @param object $post_before Previous post data
	 */
	public function post_edited( $post_id, $post_after, $post_before ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		if ( simple_history_dont_log( 'post.post_edited', compact( 'post_id', 'post_after', 'post_before' ) ) )
			return;

		// Titles changed
		if ( $post_after->post_title != $post_before->post_title )
			$action = sprintf( __('%1$s %2$s edited and changed to %3$s', 'simple-history'), '%1$s', '"'. $post_before->post_title .'"', '%2$s' );
		else
			$action = $this->events->edit;

		$this->log_post( $post_id, $action );

		// Set global post reference
		$GLOBALS['simple_history-update_post'] = $post_id;
	}

	/**
	 * Log changing post statuses
	 * 
	 * Hooked into transition_post_status action.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $new New post status
	 * @param string $old Previous post status
	 * @param object $post Post data
	 */
	public function post_status_transition( $new, $old, $post ) {
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		// Bail when nothing changed
		if ( $new === $old )
			return;

		if ( simple_history_dont_log( 'post.post_status_transition', compact( 'old', 'new', 'post' ) ) )
			return;

		// Post trashed - ignore post_trashed action
		if ( 'trash' == $new )
			$action = $this->events->trash;

		// Post untrashed - ignore post_untrashed action
		elseif ( 'trash' == $old )
			$action = $this->events->untrash;

		// Post reset to draft
		elseif ( 'draft' == $new && 'auto-draft' != $old )
			$action = __('%1$s %2$s saved as draft', 'simple-history');
		
		// Post newly drafted
		elseif ( 'draft' == $new )
			$action = __('%1$s %2$s drafted', 'simple-history');
		
		// Post pending review
		elseif ( 'pending' == $new )
			$action = __('%1$s %2$s pending review', 'simple-history');
		
		// Post scheduled
		elseif ( 'future' == $new ) {
			$action = sprintf( __('%1$s %2$s scheduled for %3$s', 'simple-history'),
				'%1$s', '%2$s',
				// Translators: Publish box date format, see http://php.net/date
				date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) )
			);
		}
		
		// Post published
		elseif ( 'publish' == $new )
			$action = __('%1$s %2$s published', 'simple-history');
		
		// Other/custom status change
		else {
			$action = sprintf( 
				_x( '%1$s %2$s changed status from %3$s to %4$s', 'Post status changed', 'simple-history'),
				'%1$s', '%2$s', '"'. $old .'"', '"'. $new .'"'
			);
		}

		$this->log_post( $post, $action );
	}

	/**
	 * Log sticking/unsticking posts
	 *
	 * Hooked into updated_option action called in stick_post() and unstick_post().
	 *
	 * @since 1.3.5
	 * 
	 * @param string $action Option name
	 * @param array $old Previous stickies
	 * @param array $new New stickies
	 */
	public function post_stickies( $action, $old, $new ) {
		if ( 'sticky_posts' != $action ) 
			return;

		// Stick post
		if ( $post_id = current( array_diff( $new, $old ) ) )
			$this->log_post( $post_id, __('%1$s %2$s marked as sticky', 'simple-history') );

		// Unstick post
		else
			$this->log_post( current( array_diff( $old, $new ) ), __('%1$s %2$s unmarked as sticky', 'simple-history') );
	}

	/**
	 * Log restoring revisions
	 * 
	 * Hooked into wp_restore_post_revision action.
	 *
	 * NOTE: The wp_insert_post function is called before this point.
	 * 
	 * @since 1.3.5
	 * 
	 * @param int $post_id Post ID
	 * @param int $revision_id Revision ID
	 */
	public function post_restore( $post_id, $revision_id ) {
		if ( simple_history_dont_log( 'post.post_restore', compact( 'post_id', 'revision_id' ) ) )
			return;

		$this->log_post( 
			$post_id, 
			sprintf( 
				// Translators: 1. Type, 2. Name, 3. Post date
				__('%1$s %2$s restored to revision from %3$s', 'simple-history'),
				'%1$s', '%2$s', wp_post_revision_title( $revision_id, false ) 
			) 
		);
	}

	/**
	 * Setup global post reference for {@link self::post_deleted()}. Doesn't
	 * log anything by itself.
	 *
	 * Hooked into delete_post action.
	 *
	 * NOTE: Called in wp_delete_post and wp_delete_attachment
	 * 
	 * @since 1.3.5
	 *
	 * @global simple_history-delete_post
	 * @param int $post_id Post ID
	 */
	public function post_delete( $post_id ) {
		$GLOBALS['simple_history-delete_post'] = get_post( $post_id );
	}

	/**
	 * Log deleting posts
	 *
	 * Hooked into after_delete_post action.
	 * 
	 * NOTE: Called only in wp_delete_post
	 * 
	 * @since 1.0.0
	 *
	 * @global simple_history-delete_post
	 * @param int $post_id Post ID
	 */
	public function post_deleted( $post_id ) {
		$global = 'simple_history-delete_post';

		// Check if global post reference exists
		if ( ! isset( $GLOBALS[$global] ) ) 
			return;

		if ( simple_history_dont_log( 'post.post_deleted', array( 'post_id' => $post_id, 'post' => $GLOBALS[$global] ) ) )
			return;

		$this->log_post( $GLOBALS[$global], $this->events->delete );
		unset( $GLOBALS[$global] );
	}

	/** Attachment ***************************************************/

	/**
	 * Log adding attachments
	 *
	 * Hooked into add_attachment action.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $post_id Post ID
	 */
	public function attachment_added( $post_id ) {
		// Ignore logging when uploading files with File_Upload_Upgrader
		if ( 'upgrader' == get_post_meta( $post_id, '_wp_attachment_context', true ) ) 
			return;

		// Find possible post parent
		if ( 0 != get_post_parent( $post_id ) ) {
			$action = sprintf( 
				_x('%1$s %2$s was added to %3$s', 'Attachment added to post', 'simple-history'), 
				'%1$s', '%2$s', get_the_title( $post->post_parent ) 
			);
		} else {
			$action = __('%1$s %2$s added', 'simple-history');
		}

		$this->log_post( $post, $action );
	}

	/**
	 * Log editing attachments
	 *
	 * Hooked into edit_attachment action.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $post_id Post ID
	 */
	public function attachment_edited( $post_id ) {
		$this->log_post( $post_id, $this->events->edit );
	}

	/**
	 * Setup global attachment reference for {@link self::attachment_deleted()}.
	 * Doesn't log anything by itself.
	 *
	 * Hooked into delete_attachment action.
	 *
	 * @since 1.0.0
	 *
	 * @global simple_history-delete_attachment
	 * @param int $post_id Post ID
	 */
	public function attachment_delete( $post_id ) {
		// Ignore logging when uploading files with File_Upload_Upgrader
		if ( 'upgrader' == get_post_meta( $post_id, '_wp_attachment_context', true ) ) 
			return;

		// Setup global attachment id reference
		$GLOBALS['simple_history-delete_attachment'] = $post_id;
	}

	/**
	 * Log deleting attachments
	 *
	 * Hooked into deleted_post action called in wp_delete_attachment().
	 * 
	 * Note that wp_delete_attachment function fires the delete_attachment action,
	 * delete_post action, deleted_post action and the wp_delete_file filter before 
	 * actually removing the original file of the attachment. What to do with that?
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-delete_attachment
	 * @global simple_history-delete_post
	 * @param int $post_id Post ID
	 */
	public function attachment_deleted( $post_id ) {
		// Since this hook is also called in wp_delete_post, check for attachment global
		if ( ! isset( $GLOBALS['simple_history-delete_attachment'] ) ) 
			return;

		$this->log_post( $GLOBALS['simple_history-delete_post'], $this->events->delete );
		unset( $GLOBALS['simple_history-delete_attachment'], $GLOBALS['simple_history-delete_post'] );
	}

	/** Term *********************************************************/

	/**
	 * Term logger. Requires term ID or term object and taxonomy name
	 *
	 * @since 1.3.5
	 * 
	 * @param int|object $term Term ID or term object
	 * @param string $taxonomy Taxonomy name
	 * @param string $action Log message
	 * @param string $desc Optional. Additional event description
	 */
	public function log_term( $term, $taxonomy, $action, $desc = '' ) {
		if ( is_numeric( $term ) )
			$term = get_term( $term, $taxonomy );

		$this->log( array(
			'action' => $action,
			'type'   => $taxonomy,
			'name'   => $term->name,
			'id'     => $term->term_taxonomy_id,
			'desc'   => $desc
		) );
	}

	/**
	 * Log creating terms
	 *
	 * Hooked into created_term action.
	 *
	 * @since 1.3.5
	 *
	 * @param int $term_id Term ID
	 * @param int $tt_id Term taxonomy ID
	 * @param string $taxonomy Taxonomy name
	 */
	public function term_created( $term_id, $tt_id, $taxonomy ) {
		if ( simple_history_dont_log( 'term.term_created', compact( 'term_id', 'tt_id', 'taxonomy' ) ) )
			return;

		$this->log_term( $term_id, $taxonomy, $this->events->new );
	}

	/**
	 * Setup global term reference for {@link self::term_edited()}
	 *
	 * Hooked into edit_term action.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-update_term_{$term_id}
	 * @param int $term_id Term ID
	 * @param int $tt_id Term taxonomy ID
	 * @param string $taxonomy Taxonomy name
	 */
	public function term_edit( $term_id, $tt_id, $taxonomy ) {
		$GLOBALS['simple_history-update_term_'. $term_id] = get_term( $term_id, $taxonomy );
	}

	/**
	 * Log editing terms
	 *
	 * Hooked into edited_term action.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-update_term_{$term_id}
	 * @param int $term_id Term ID
	 * @param int $tt_id Term taxonomy ID
	 * @param string $taxonomy Taxonomy name
	 */
	public function term_edited( $term_id, $tt_id, $taxonomy ) {
		if ( simple_history_dont_log( 'term.term_edited', compact( 'term_id', 'tt_id', 'taxonomy' ) ) )
			return;

		$term   = get_term( $term_id, $taxonomy );
		$global = 'simple_history-update_term_'. $term_id;

		// Compare term names
		if ( isset( $GLOBALS[$global] ) && $GLOBALS[$global]->name != $term->name ) {
			$this->log_term( $term, $taxonomy, sprintf( 
				__('%1$s %2$s edited and changed to %3$s', 'simple-history'),
				'%1$s', $GLOBALS[$global]->name, '%2$s' 
			) );
			unset( $GLOBALS[$global] );
		} else
			$this->log_term( $term, $taxonomy, $this->events->edit );
	}

	/**
	 * Log deleting terms
	 *
	 * Hooked into delete_term action.
	 *
	 * @since 1.3.5
	 *
	 * @param int $term_id Term ID
	 * @param int $tt_id Term taxonomy ID
	 * @param string $taxonomy Taxonomy name
	 * @param array $the_term Deleted term
	 */
	public function term_deleted( $term_id, $tt_id, $taxonomy, $the_term ) {
		if ( simple_history_dont_log( 'term.term_deleted', compact( 'term_id', 'tt_id', 'taxonomy', 'the_term' ) ) )
			return;

		$this->log_term( $the_term, $taxonomy, $this->events->delete );
	}

	/** Menu *********************************************************/

	/**
	 * Menu logger. Requires a menu ID or menu object
	 *
	 * @since 1.3.5
	 * 
	 * @param int|object $menu Menu ID or menu object
	 * @param string $action Log message
	 * @param string $desc Optional. Additional event description
	 */
	function log_menu( $menu, $action, $desc = '' ) {
		if ( is_numeric( $menu ) )
			$menu = wp_get_nav_menu_object( $menu );

		$this->log( array(
			'action' => $action,
			'type'   => 'menu',
			'name'   => $menu->name,
			'id'     => $menu->term_id,
			'desc'   => $desc
		) );
	}

	/**
	 * Log creating menus
	 *
	 * Hooked into wp_create_nav_menu action.
	 *
	 * @since 1.3.5
	 * 
	 * @param int $menu_id Menu ID
	 * @param array $menu_data Menu data
	 */
	public function menu_created( $menu_id, $menu_data ) {
		$this->log_menu( $menu_id, $this->events->new );
	}

	/**
	 * Setup global menu reference for menu update functions
	 *
	 * Hooked into check_admin_referer action called in wp-admin/nav-menus.php.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-update_menu
	 * @param string $action Referer action
	 * @param boolean $result User passes referer
	 */
	public function menu_update( $action, $result ) {
		if ( ! $result || 'update-nav_menu' != $action ) 
			return;

		$GLOBALS['simple_history-update_menu'] = isset( $_REQUEST['menu'] ) ? (int) $_REQUEST['menu'] : 0;
	}

	/**
	 * Log editing menus
	 *
	 * Hooked into wp_update_nav_menu action.
	 *
	 * @since 1.3.5
	 * 
	 * @param int $menu_id Menu ID
	 * @param array $menu_data Menu data
	 */
	public function menu_edited( $menu_id, $menu_data = array() ) {
		// Previous to this point passed through wp_update_term( $menu_id, 'nav_menu', $args )
		$this->log_menu( $menu_id, $this->events->edit );
	}

	/**
	 * Log editing menu locations
	 *
	 * Hooked into updated_option action called in set_theme_mod().
	 *
	 * @since 1.3.5
	 *
	 * @param string $action Option name
	 * @param array $old Previous stickies
	 * @param array $new New stickies
	 */
	public function menu_locations_edited( $action, $old, $new ) {
		$theme = get_option('stylesheet'); // Not to heavy on every action call?
		if ( "theme_mods_$theme" != $action )
			return;

		$old_locs = $old['nav_menu_locations'];
		$new_locs = $new['nav_menu_locations'];

		// Only log changes in locations
		if ( $old_locs === $new_locs )
			return;

		$locations = get_registered_nav_menus();
		foreach ( array_diff( $new_locs, $old_locs ) as $location => $menu_id ) {
			// Translators: 1. Type, 2. Menu name, 3. Location name 
			if ( ! empty( $menu_id ) ) {
				$action = __('%1$s %2$s assigned to location %3$s', 'simple-history');

			// None assigned
			} else {
				$action = __('%1$s %2$s removed from location %3$s', 'simple-history');
				$menu_id = $old_locs[$location];
			}

			$this->log_menu( $menu_id, sprintf( $action, '%1$s', '%2$s', $locations[$location] ) );
		}
	}

	/**
	 * Setup global menu reference for {@link self::menu_deleted()}
	 *
	 * Hooked into check_admin_referer action called in wp-admin/nav-menus.php.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-update_menu
	 * @param string $action Referer action
	 * @param boolean $result User passes referer
	 */
	public function menu_delete( $action, $result ) {
		if ( ! $result || false === strpos( $action, 'delete-nav_menu-' ) )
			return;

		$GLOBALS['simple_history-delete_menu'] = wp_get_nav_menu_object( substr( $action, 16 ) );
	}

	/**
	 * Log deleting menus
	 *
	 * Hooked into wp_delete_nav_menu action.
	 *
	 * @since 1.3.5
	 * 
	 * @param int $menu_id Menu ID
	 */
	public function menu_deleted( $menu_id ) {
		if ( ! isset( $GLOBALS['simple_history-delete_menu'] ) )
			return;

		$this->log_menu( $GLOBALS['simple_history-delete_menu'], $this->events->delete );
		unset( $GLOBALS['simple_history-delete_menu'] );
	}

	/**
	 * Menu item logger. Requires a menu item ID or item setup object
	 *
	 * @since 1.3.5
	 * 
	 * @param int $menu_id Menu ID
	 * @param string $action Log message
	 * @param string $desc Optional. Additional event description
	 */
	function log_menu_item( $item, $menu_id, $action, $desc = '' ) {
		if ( is_numeric( $item ) )
			$item = wp_setup_nav_menu_item( get_post( $item ) );

		// Find menu item menu ID
		if ( empty( $menu_id ) ) {
			$terms   = wp_get_object_terms( $item->db_id, 'nav_menu', array( 'fields' => 'ids' ) );
			$menu_id = current( $terms );
		}

		// Setup log message like 'Menu "x" edited: nav menu item added'
		$action = $this->events->edit .': '. sprintf( $action, _x('item', 'Navigation Menu Item', 'simple-history'), '"'. $item->title .'"' );

		$this->log_menu( $menu_id, $action, $desc );
	}

	/**
	 * Setup global item reference for {@link self::menu_item_updated()}
	 *
	 * Hooked into post_updated action.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-edit_menu_item
	 * @param int $item_id Menu item ID
	 * @param object $item_after Menu item after update
	 * @param object $item_before Menu item before update
	 */
	public function menu_item_edited( $item_id, $item_after, $item_before ) {
		if ( is_nav_menu_item( $item_id ) )
			$GLOBALS['simple_history-edit_menu_item'] = $item_before;
	}

	/**
	 * Log creating or editing menu items
	 *
	 * Hooked into wp_update_nav_menu_item action.
	 *
	 * NOTE: Auto-added pages run through this filter.
	 *
	 * @since 1.3.5
	 *
	 * @todo Fires somehow twice during update: before and after edit of menu items.
	 * @todo Log item changes more exact. Now on every update items log as 'edited' 
	 *        while mostly they are not. Real item changes are in update_post_meta 
	 *        calls before this hook fires.
	 *
	 * @global simple_history-edit_menu_item
	 * @param int $menu_id Menu ID
	 * @param int $menu_item_db_id Menu item database ID
	 * @param array $args Update arguments
	 */
	public function menu_item_updated( $menu_id, $menu_item_db_id, $args ) {
		// Don't log drafted items
		if ( 'draft' == get_post_status( $menu_item_db_id ) || 0 == $menu_id )
			return;

		// Build nav item setup from args
		$item = array();
		foreach ( $args as $key => $value ) {
			$item_key = str_replace( '-', '_', substr( $key, 10 ) ); // Strip 'menu-item-' and change - into _

			// Provide original title if empty
			if ( 'title' == $item_key && empty( $value ) ) {
				if ( 'taxonomy' == $args['menu-item-type'] )
					$value = get_term_field( 'name', $args['menu-item-object-id'], $args['menu-item-object'], 'raw' );
				elseif ( 'post_type' == $args['menu-item-type'] )
					$value = get_the_title( $args['menu-item-object-id'] );
			}

			$item[$item_key] = $value;
		}

		// Item created
		if ( isset( $GLOBALS['simple_history-edit_menu_item'] ) && 'draft' == $GLOBALS['simple_history-edit_menu_item']->post_status ) {
			$action = $this->events->add;
			unset( $GLOBALS['simple_history-edit_menu_item'] );
		
		// Item updated 
		} else
			$action = $this->events->edit;

		$this->log_menu_item( (object) $item, $menu_id, $action );
	}

	/**
	 * Setup global menu item reference for {@link self::menu_item_deleted()}
	 *
	 * Hooked into before_delete_post action.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-delete_menu_item
	 * @param int $item_id Menu item ID
	 */
	public function menu_item_delete( $item_id ) {
		$GLOBALS['simple_history-delete_menu_item'] = wp_setup_nav_menu_item( get_post( $item_id ) );
	}

	/**
	 * Log deleting menu items
	 *
	 * Hooked into after_delete_post action.
	 *
	 * NOTE: Menu items get also deleted when their reference post
	 * or taxonomy term is deleted from those respective places.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-update_menu
	 * @global simple_history-delete_menu_item
	 * @param int $item_id Menu item ID
	 */
	public function menu_item_deleted( $item_id ) {
		// Only log on menu update, not on menu delete or reference object (post or term) delete
		if ( ! isset( $GLOBALS['simple_history-update_menu'] ) || isset( $GLOBALS['simple_history-delete_menu'] ) )
			return;

		$this->log_menu_item( $GLOBALS['simple_history-delete_menu_item'], $GLOBALS['simple_history-update_menu'], _x('%1$s %2$s removed', 'Nav menu item deleted', 'simple-history') ); 
		unset( $GLOBALS['simple_history-delete_menu_item'] );
	}

	/**
	 * Prevent other loggers from firing for menus and menu items
	 *
	 * @since 1.3.5
	 * 
	 * @param boolean $dont To log or not to log 
	 * @param string $event Event name
	 * @param array $args Event arguments
	 * @return boolean $dont
	 */
	public function menu_dont_log( $dont, $event, $args ) {
		switch ( $event ) {

			// Menu created
			case 'term.term_created' :
			// Menu edited
			case 'term.term_edited' :
			// Menu deleted
			case 'term.term_deleted' :
				$dont = 'nav_menu' == $args['taxonomy'];
				break;

			// Menu item added
			case 'post.post_created' :
			case 'post.post_status_transition' :
			// Menu item edited
			case 'post.post_edited' :
				$dont = is_nav_menu_item( isset( $args['post'] ) ? $args['post']->ID : $args['post_id'] );
				break;

			// Menu item deleted
			case 'post.post_deleted' :
				$dont = 'nav_menu_item' == $args['post']->post_type;
				break;
		}

		return $dont;
	}

	/** Comment ******************************************************/

	/**
	 * Comment logger. Requires a comment ID or comment object
	 * 
	 * @since 1.3.5
	 *
	 * @param int|object $comment Comment ID or object from get_comment()
	 * @param string $action Log message
	 * @param string $desc Optional. Additional event description
	 */
	function log_comment( $comment, $action, $desc = '' ) {
		if ( is_numeric( $comment ) )
			$comment = get_comment( $comment );

		// Provide action with comment author
		if ( false !== strpos( $action, '%3$s' ) )
			$action = sprintf( $action, '%1$s', '%2$s', $comment->comment_author );

		// Set empty description to comment content
		if ( empty( $desc ) )
			$desc = $comment->comment_content; // Use raw or filter?

		$this->log( array(
			'action' => $action,
			'type'   => 'comment',
			'name'   => get_the_title( $comment->comment_post_ID ),
			'id'     => $comment->comment_ID,
			'desc'   => $desc
		) );
	}

	/**
	 * Log creating comments
	 * 
	 * Hooked into wp_insert_comment action in favor of comment_post action.
	 * 
	 * @since 1.3.5
	 * 
	 * @param int $comment_id Comment ID
	 * @param object $comment Comment data object
	 */
	public function comment_created( $comment_id, $comment ) {
		// Translators: 1. Type, 2. Post name
		$this->log_comment( $comment, _x('%1$s to %2$s created', 'Comment created', 'simple-history') );
	}

	/**
	 * Log editing comments
	 * 
	 * Hooked into edit_comment action.
	 *
	 * @since 0.3.3
	 * 
	 * @param int $comment_id Comment ID
	 */
	public function comment_edited( $comment_id ) {
		// Translators: 1. Type, 2. Post name, 3. Comment author
		$this->log_comment( $comment_id, _x('%$1s by %3$s to %2$s edited', 'Comment edited', 'simple-history') );
	}

	/**
	 * Log changing comments status
	 * 
	 * Hooked into transition_comment_status action.
	 *
	 * @since 0.3.3
	 * 
	 * @param string $new New comment status
	 * @param string $old Previous comment status
	 * @param object $comment Comment data
	 */
	public function comment_status_transition( $new, $old, $comment ) {
		// Translators: 1. Type, 2. Post name, 3. Comment author

		// Comment approved
		if ( in_array( $new, array( '1', 'approved' ) ) )
			$action = _x('%1$s by %3$s to %2$s approved',         'Comment approved',   'simple-history');

		// Comment unapproved
		elseif ( 'unapproved' == $new )
			$action = _x('%1$s by %3$s to %2$s unapproved',       'Comment unapproved', 'simple-history');

		// Comment marked as spam - ignore spammed_comment action
		elseif ( 'spam' == $new )
			$action = _x('%1$s by %3$s to %2$s marked as spam',   'Spam comment',       'simple-history');

		// Comment trashed - ignore trashed_comment action
		elseif ( 'trash' == $new )
			$action = _x('%1$s by %3$s to %2$s trashed',          'Comment trashed',    'simple-history');
		
		// Comment unmarked as spam - ignore unspammed_comment action
		elseif ( 'spam' == $old )
			$action = _x('%1$s by %3$s to %2$s unmarked as spam', 'Unspam comment',     'simple-history');

		// Comment untrashed - ignore untrashed_comment action
		elseif ( 'trash' == $old ) {
			// Don't fire when post is deleted
			if ( ! get_comment( $comment->comment_id ) )
				return;
			$action = _x('%1$s by %3$s to %2$s untrashed',        'Comment untrashed',  'simple-history');
		}

		// Other/custom status change
		else {
			$action = sprintf( 
				_x('%1$s by %3$s to %2$s changed status from %4$s to %5$s', 'Comment status changed', 'simple-history'),
				'%1$s', '%2$s', '%3$s', $old, $new
			);
		}

		// Do the logging
		$this->log_comment( $comment, $action );
	}

	/**
	 * Setup global comment reference for {@link self::comment_deleted()}. Doesn't
	 * log anything by itself.
	 * 
	 * Hooked into delete_comment action.
	 * 
	 * @since 1.3.5
	 * 
	 * @global simple_history-delete_comment-{$comment_id}
	 * @param int $comment_id Comment ID
	 */
	public function comment_delete( $comment_id ) {
		$GLOBALS['simple_history-delete_comment-'. $comment_id] = get_comment( $comment_id );
	}

	/**
	 * Log deleting comments
	 * 
	 * Hooked into deleted_comment action.
	 * 
	 * @since 0.3.3
	 *
	 * @global simple_history-delete_comment-{$comment_id}
	 * @param int $comment_id Comment ID
	 */
	public function comment_deleted( $comment_id ) {
		$global = 'simple_history-delete_comment-'. $comment_id;
		
		// Translators: 1. Type, 2. Post name, 3. Comment author
		$this->log_comment( $GLOBALS[$global], _x('%1$s by %3$s to %2$s deleted', 'Comment deleted', 'simple-history') );
		unset( $GLOBALS[$global] ); 
	}

	/** User *********************************************************/
	
	/**
	 * Log logging in of users
	 * 
	 * Hooked into wp_login action.
	 *
	 * @since 1.0.0
	 * 
	 * @param string $user_login User login name
	 */
	public function user_loggedin( $user_login ) {
		if ( 0 == get_current_user_id() ) {
			$user    = get_user_by( 'login', $user_login );
			$user_id = $user->ID;
		} else {
			$user_id = get_current_user_id();
		}

		// Translators: 1. Type, 2. Name
		$this->log_user( $user_id, __('%1$s %2$s logged in', 'simple-history') );
	}

	/**
	 * Log failed logins of users
	 * 
	 * Hooked into wp_authenticate_user filter.
	 *
	 * @since 1.3
	 * 
	 * @param WP_User $user User data object
	 * @param string $password User password
	 */
	public function user_authenticate( $user, $password ) {
		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			// Translators: 1. Type, 2. Name
			$this->log_user( 
				$user->ID, 
				__('%1$s %2$s entered the wrong password to login', 'simple-history'), 
				sprintf( "HTTP_USER_AGENT: %s \nHTTP_REFERER: %s \nREMOTE_ADDR: %s",
					$_SERVER['HTTP_USER_AGENT'],
					$_SERVER['HTTP_REFERER'],
					$_SERVER['REMOTE_ADDR']
				)
			);
		}

		return $user;
	}

	/**
	 * Log logging out of users
	 * 
	 * Hooked into wp_logout action.
	 * 
	 * @since 1.0.0
	 */
	public function user_loggedout() {
		// Translators: 1. Type, 2. Name
		$this->log_user( get_current_user_id(), __('%1$s %2$s logged out', 'simple-history' ) );
	}

	/**
	 * Log registering users
	 * 
	 * Hooked into user_register action.
	 * 
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 */
	public function user_registered( $user_id ) {
		// Translators: 1. Type, 2. Name
		$this->log_user( $user_id, __('%1$s %2$s registered', 'simple-history' ) );
	}

	/**
	 * Log updating user profiles
	 * 
	 * Hooked into profile_update action.
	 * 
	 * @since 1.0.0
	 *
	 * @todo Log changing user role and other core user data
	 *
	 * @param int $user_id User ID
	 */
	public function user_updated( $user_id ) {
		// Translators: 1. Type, 2. Name
		$this->log_user( $user_id, __('%1$s %2$s profile updated', 'simple-history' ) );
	}

	/**
	 * Log deleting users
	 * 
	 * Hooked into delete_user action.
	 * 
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID
	 */
	public function user_deleted( $user_id ) {
		$this->log_user( $user_id, $this->events->delete );
	}	

	/** Settings *****************************************************/

	/**
	 * Settings page logger.
	 * 
	 * @since 1.3.5
	 *
	 * @param array $page Settings page identifier => Settings page title
	 * @param string $desc Optional. Additional event description
	 */
	function log_settings( $page, $desc = '' ) {
		$this->log( array(
			// Translators: 1. Type, 2. Name
			'action' => __('Settings page %2$s updated', 'simple-history'),
			'type'   => 'settings',
			'name'   => current( $page ),
			'id'     => key( $page ),
			'desc'   => $desc
		) );
	}

	/**
	 * Log saving settings pages
	 * 
	 * Hooked in option_page_capability_$page filter called on wp-admin/options.php.
	 * 
	 * @since 0.8
	 * 
	 * @todo Find a better hook since past this point there are checks before 
	 *        options.php actually updates something. This hook only checks
	 *        if options.php is visited. Not if options are updated.
	 */
	public function settings_updated( $cap ) {
		if ( false !== strpos( current_filter(), 'option_page_capability_' ) ) {
			$page  = substr( current_filter(), 23 ); // 23 = after 'option_page_capability_'
			$pages = array(
				'general'    => __('General Settings'),
				'writing'    => __('Writing Settings'),
				'reading'    => __('Reading Settings'),
				'discussion' => __('Discussion Settings'),
				'media'      => __('Media Settings'),
				'privacy'    => __('Privacy Settings')
			);

			if ( isset( $pages[$page] ) )
				$this->log_settings( array( $page => $pages[$page] ) );
		}

		return $cap;
	}

	/**
	 * Log saving permalinks settings page
	 * 
	 * Hooked in check_admin_referer action called in ?.
	 * 
	 * @since 0.8
	 * 
	 * @todo Find a better hook.
	 *
	 * @param string $action Referer action
	 * @param boolean $result User passes referer
	 */
	public function permalinks_updated( $action, $result ) {
		if ( ! $result || 'update-permalink' != $action ) 
			return;

		$this->log_settings( array( 'permalinks' => __('Permalinks', 'simple-history') ) );
	}

	/** Plugin *******************************************************/
	
	/**
	 * Plugin logger. Requires a plugin data array or plugin file id
	 * 
	 * @since 1.3.5
	 *
	 * @todo Use file id as object ID
	 *
	 * @param string|array|object $plugin Plugin file id or data array or data object
	 * @param string $action Log message
	 * @param string $desc Optional. Additional event description
	 */
	function log_plugin( $plugin, $action, $desc = '' ) {
		if ( is_string( $plugin ) )
			$plugin = get_plugin_data( WP_PLUGIN_DIR .'/'. $plugin );

		if ( ! is_object( $plugin ) )
			$plugin = (object) $plugin;

		$this->log( array(
			'action' => $action,
			'type'   => 'plugin',
			'name'   => $plugin->Name,
			'id'     => sanitize_title( $plugin->Name ), // Prefer file id
			'desc'   => $desc
		) );
	}

	/**
	 * Log installing plugins by download or upload 
	 * 
	 * Hooked into upgrader_post_install filter called in {@link WP_Upgrader::install_package()}.
	 * 
	 * Does not log uploads through ftp or other server side channels. 
	 * 
	 * @since 1.3.5
	 *
	 * @param boolean|WP_Error $return Filter return val
	 * @param array $plugin Empty on install
	 * @param array $result Install arguments
	 */
	public function plugin_installed( $return, $plugin, $result ) {
		if ( is_wp_error( $return ) ) 
			return $return;

		// Check installing plugin
		if ( empty( $plugin ) && false !== strpos( $result['destination'], WP_PLUGIN_DIR ) ) {
			$plugin = current( get_plugins('/'. $result['destination_name']) );
			$this->log_plugin( 
				$plugin,
				// Translators: 1. Type, 2. Name, 3. Version
				sprintf( _x('%1$s %2$s version %3$s installed', 'Plugin installed', 'simple-history'), '%1$s', '%2$s', $plugin['Version'] )
				// Event link would be to wp-admin/plugins.php?paged=x#{$destination_name} - despite admin bar
			);
		}

		return $return;
	}

	/**
	 * Log activating plugins
	 * 
	 * Hooked into activated_plugin action.
	 * 
	 * @since 0.3
	 *
	 * @param string $plugin Plugin file
	 * @param boolean $network_wide Whether plugin is multisite activated
	 */
	public function plugin_activated( $plugin, $network_wide ) {
		// Translators: 1. Type, 2. Name
		if ( $network_wide )
			$action = _x('%1$s %2$s activated for the network', 'Plugin activated multisite', 'simple-history');
		else
			$action = _x('%1$s %2$s activated', 'Plugin activated', 'simple-history');

		$this->log_plugin( $plugin, $action );
	}

	/**
	 * Setup global version reference for {@link self::plugin_updated()}
	 *
	 * Hooked into upgrader_pre_install filter called in {@link WP_Upgrader::install_package()}.
	 *
	 * @since 1.3.5
	 *
	 * @global simple_history-plugin_update_version
	 * @param boolean|WP_Error $return Filter return val
	 * @param array $plugin Plugin name
	 * @return mixed $return
	 */
	public function plugin_update( $return, $plugin ) {
		if ( is_wp_error( $return ) ) 
			return $return;

		if ( isset( $plugin['plugin'] ) ) {
			$plugin = get_plugin_data( WP_PLUGIN_DIR .'/'. $plugin['plugin'] );
			$GLOBALS['simple_history-plugin_update_version'] = $plugin['Version'];
		}

		return $return;	
	}

	/**
	 * Logs updating plugins
	 * 
	 * Hooked into upgrader_post_install filter called in {@link WP_Upgrader::install_package()}.
	 * 
	 * @since 1.3.5
	 *
	 * @todo Implement changelog link
	 * 
	 * @global simple_history-plugin_update_version
	 * @param boolean|WP_Error $return Filter return val
	 * @param array $plugin Plugin name
	 * @param array $result Update arguments
	 * @return mixed $return
	 */
	public function plugin_updated( $return, $plugin, $result ) {
		if ( is_wp_error( $return ) ) 
			return $return;

		if ( isset( $plugin['plugin'] ) ) {
			$plugin = get_plugin_data( WP_PLUGIN_DIR .'/'. $plugin['plugin'] );
			if ( isset( $GLOBALS['simple_history-plugin_update_version'] ) && $previous = $GLOBALS['simple_history-plugin_update_version'] ) {
				$this->log_plugin( $plugin, sprintf( $this->events->update_from_version, '%1$s', '%2$s', $previous, $plugin['Version'] ) );
				unset( $GLOBALS['simple_history-plugin_update_version'] );
			} else
				$this->log_plugin( $plugin, sprintf( $this->events->update_to_version,   '%1$s', '%2$s', $plugin['Version'] ) );
		}

		return $return;
	}

	/**
	 * Log deactivating plugins
	 * 
	 * Hooked into deactivated_plugin action.
	 * 
	 * @since 0.3
	 *
	 * @param string $plugin Plugin file
	 * @param boolean $network_wide Whether plugin is multisite deactivated
	 */
	public function plugin_deactivated( $plugin, $network_wide ) {
		// Translators: 1. Type, 2. Name
		if ( $network_wide )
			$action = _x('%1$s %2$s deactivated for the network', 'Plugin deactivated multisite', 'simple-history');
		else
			$action = _x('%1$s %2$s deactivated', 'Plugin deactivated', 'simple-history');

		$this->log_plugin( $plugin, $action );
	}

	/**
	 * Store user meta as reference for {@link self::plugin_deleted()}
	 *
	 * Hooked into check_admin_referer action called on wp-admin/plugins.php.
	 * 
	 * @since 1.3.5
	 *
	 * @todo Replace user meta with cache to store delete reference
	 * 
	 * @param string $action Performed admin referer action
	 * @param boolean $result User passes referer
	 */
	public function plugin_delete( $action, $result ) {
		if (   ! $result                                // Passing referer
			|| 'bulk-plugins'    != $action             // Referer action
			|| 'delete-selected' != $_REQUEST['action'] // Bulk action
			|| ! isset( $_REQUEST['verify-delete'] )    // Delete verified
			) 
			return;

		// Get plugins to delete
		$plugins = isset( $_REQUEST['checked'] ) ? (array) $_REQUEST['checked'] : array();
		$plugins = array_filter( $plugins, 'is_plugin_inactive' );
		if ( empty( $plugins ) ) 
			return;

		// Get plugin data
		foreach ( $plugins as $k => $plugin )
			$plugins[$k] = get_plugin_data( WP_PLUGIN_DIR .'/'. $plugin );

		// Store reference of deleted plugins
		update_user_meta( get_current_user_id(), 'simple_history-plugins_delete', $plugins );
	}

	/**
	 * Log deleting plugins
	 *
	 * Hooked into delete_transient_plugins_delete_result action called on 
	 * wp-admin/plugins.php.
	 *
	 * Note that the deleted_transient action does not fire since delete_option() 
	 * returns false as the transient was never stored. WP, then why are you 
	 * deleting it?!
	 * 
	 * Note that we're not using hooks in uninstall_plugin(), because those are 
	 * for uninstalling plugins and fire before actually deleting files. Also 
	 * not every plugin uninstalls before deleting.
	 * 
	 * @since 1.3.5
	 * 
	 * @param string $transient The transient deleted
	 */
	public function plugin_deleted( $transient ) {
		if ( $plugins = get_user_meta( get_current_user_id(), 'simple_history-plugins_delete', true ) && ! empty( $plugins ) ) {
			// Translators: 1. Type, 2. Name
			foreach ( $plugins as $plugin )
				$this->log_plugin( $plugin, _x('%1$s %2$s deleted', 'Plugin deleted', 'simple-history') );

			delete_user_meta( get_current_user_id(), 'simple_history-plugins_delete' );
		}
	}

	/** Theme ********************************************************/

	/**
	 * Theme logger. Requires a WP_Theme object or theme template id
	 * 
	 * @since 1.3.5
	 *
	 * @param string|WP_Theme $theme Theme file id or object from wp_get_theme()
	 * @param string $action Log message
	 * @param string $desc Optional. Additional event description
	 */
	function log_theme( $theme, $action, $desc = '' ) {
		if ( is_string( $theme ) )
			$theme = wp_get_theme( $theme );

		$this->log( array(
			'action' => $action,
			'type'   => 'theme',
			'name'   => $theme->Name,
			'id'     => $theme->Template,
			'desc'   => $desc
		) );
	}

	/**
	 * Log installing themes by download or upload
	 * 
	 * Hooked into upgrader_post_install filter called in {@link WP_Upgrader::install_package()}.
	 * 
	 * @since 1.3.5
	 *
	 * @todo Enable for custom theme directories as destination value
	 *
	 * @param boolean|WP_error $return Filter return val
	 * @param array $theme Empty on install
	 * @param array $result Install arguments
	 * @return mixed $return
	 */
	public function theme_installed( $return, $theme, $result ) {
		if ( is_wp_error( $return ) ) 
			return $return;

		if ( empty( $theme ) && false !== strpos( $result['destination'], '/themes' ) ) {
			$theme = wp_get_theme( $result['destination_name'] );
			$this->log_theme( 
				$theme,
				// Translators: 1. Type, 2. Name, 3. Version
				sprintf( _x('%1$s %2$s version %3$s installed', 'Theme installed', 'simple-history'), '%1$s', '%2$s', $theme->Version ) 
			);
		}

		return $return;
	}

	/**
	 * Log switching themes
	 * 
	 * Hooked into after_switch_theme action.
	 * 
	 * @since 1.3.5
	 *
	 * @param string $old Previous theme name
	 */
	public function theme_switched( $old ) {
		// Translators: 1. Type, 2. Previous theme, 3. New theme, 4. Blog site url
		if ( is_multisite() )
			$action = sprintf( _x('%1$s on %4$s changed from %2$s to %3$s', 'Theme switched multisite', 'simple-history'), '%1$s', $old, '%2$s', site_url() );
		else
			$action = sprintf( _x('%1$s changed from %2$s to %3$s', 'Theme switched', 'simple-history'), '%1$s', $old, '%2$s' );

		$this->log_theme( wp_get_theme(), $action );
	}

	/**
	 * Log updating themes
	 * 
	 * Hooked into upgrader_pre_install filter called in {@link WP_Upgrader::install_package()}.
	 * 
	 * @since 1.3.5
	 *
	 * @global simple_history-theme_update_version
	 * @param boolean|WP_Error $return Filter return val
	 * @param array $theme Theme name
	 * @return mixed $return
	 */
	public function theme_update( $return, $theme ) {
		if ( is_wp_error( $return ) ) 
			return $return;

		if ( isset( $theme['theme'] ) ) {
			$theme = wp_get_theme( $theme['theme'] );
			$GLOBALS['simple_history-theme_update_version'] = $theme->Version;
		}

		return $return;
	}

	/**
	 * Log updating themes
	 * 
	 * Hooked into upgrader_post_install filter called in {@link WP_Upgrader::install_package()}.
	 * 
	 * @since 1.3.5
	 *
	 * @global simple_history-theme_update_version
	 * @param boolean|WP_Error $return Filter return val
	 * @param array $theme Theme name
	 * @param array $result Update arguments
	 * @return mixed $return
	 */
	public function theme_updated( $return, $theme, $result ) {
		if ( is_wp_error( $return ) ) 
			return $return;

		if ( isset( $theme['theme'] ) ) {
			$theme = wp_get_theme( $theme['theme'] );
			if ( isset( $GLOBALS['simple_history-theme_update_version'] ) && $previous = $GLOBALS['simple_history-theme_update_version'] ) {
				$this->log_theme( $theme, sprintf( $this->events->update_from_version, '%1$s', '%2$s', $previous, $theme->Version ) );
				unset( $GLOBALS['simple_history-theme_update_version'] );	
			} else
				$this->log_theme( $theme, sprintf( $this->events->update_to_version,   '%1$s', '%2$s', $theme->Version ) );
		}

		return $return;
	}

	/**
	 * Store user meta as reference for {@link self::theme_deleted()}
	 *
	 * Hooked into check_admin_referer action called on wp-admin/themes.php.
	 * 
	 * @since 1.3.5
	 * 
	 * @todo Replace user meta with cache to store delete reference
	 * 
	 * @param string $action Referer action
	 * @param boolean $result User passes referer
	 */
	public function theme_delete( $action, $result ) {
		if ( ! $result || false === strpos( $action, 'delete-theme' ) ) 
			return;

		// Store reference of deleted theme contained in $action
		update_user_meta( get_current_user_id(), 'simple_history-theme_delete', substr( $action, 13 ) );
	}

	/**
	 * Log deleting themes
	 *
	 * Hooked into deleted_site_transient action called in delete_theme().
	 * 
	 * @since 1.3.5
	 *
	 * @param string $transient The transient deleted
	 */
	public function theme_deleted( $transient ) {
		if ( 'update_themes' != $transient ) 
			return;

		if ( $theme = get_user_meta( get_current_user_id(), 'simple_history-theme_delete', true ) && ! empty( $theme ) ) {
			// Translators: 1. Type, 2. Name
			$this->log_theme( wp_get_theme( $theme ), $this->events->delete );
			delete_user_meta( get_current_user_id(), 'simple_history-theme_delete' );
		}
	}

	/** Core *********************************************************/

	/**
	 * Log updating WP core
	 * 
	 * Hooked into _core_updated_successfully action.
	 * 
	 * @since 1.0.0
	 *
	 * @param string $wp_version The new WP version
	 */
	public function core_updated( $wp_version ) {
		if ( $GLOBALS['wp_version'] != $wp_version )
			$action = sprintf( $this->events->update_from_version, '%1$s', '%2$s', $GLOBALS['wp_version'], $wp_version );
		else
			$action = sprintf( $this->events->update_to_version,   '%1$s', '%2$s', $wp_version );

		$this->log( array(
			'action' => $action,
			'type'   => 'wordpress',
			'name'   => _x('Core', 'WordPress Core', 'simple-history'),
			'id'     => 'wordpress-'. $wp_version,
		) );
	}
}

new Simple_History_Module_Core();

endif; // class_exists
