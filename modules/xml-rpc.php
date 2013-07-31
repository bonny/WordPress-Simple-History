<?php

/**
 * Simple History Modules XML-RPC Class
 *
 * @since 1.3.5
 * 
 * @package Simple History
 * @subpackage Modules
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simple_History_Module_XMLRPC' ) ) :

/**
 * Plugin class
 */
class Simple_History_Module_XMLRPC extends Simple_History_Module {

	function __construct(){
		parent::__construct( array(
			'id'          => 'xmlrpc',
			'title'       => __('XML-RPC', 'simple-history'),
			'description' => __('Log events that are triggered by using XML-RPC.', 'simple-history'),
			'tabs'        => array(
				'supports' => array(
					),
				'lacks'    => array(
					)
				)
			)
		);
	}

	function add_actions(){
		add_action( 'xmlrpc_call', array( $this, 'xmlrpc_called' ) );
	}

	/** XML-RPC ******************************************************/

	/**
	 * Log XML-RPC calls
	 *
	 * Hooked into xmlrpc_call action
	 * 
	 * @since 1.3.5
	 */
	public function xmlrpc_called( $event ){

		switch ( $event ) {

			/** Post *********************************************************/
			/*
			 * Fires xmlrpc_call_success_wp_deletePage action
			 * Fires xmlrpc_call_success_blogger_newPost action
			 * Fires xmlrpc_call_success_blogger_editPost action
			 * Fires xmlrpc_call_success_blogger_deletePost action
			 * Fires xmlrpc_call_success_mw_newPost action
			 * Fires xmlrpc_call_success_mw_editPost action
			 */
			
			// before wp_insert_post()
			case 'wp.newPost' :
			case 'blogger.newPost' :
			case 'metaWeblog.newPost' : // also wp.newPage
				break;

			// before wp_update_post()
			case 'wp.editPost' :
			case 'blogger.editPost' :
			case 'metaWeblog.editPost' : // also wp.editPage
				break;

			// before wp_restore_post_revision()
			case 'wp.restoreRevision' :
				break;

			// before wp_delete_post()
			case 'wp.deletePost' : // also wp.deletePage
			case 'blogger.deletePost' :
				break;

			/** Term *********************************************************/
			
			// before wp_insert_term()
			case 'wp.newTerm' :
				break;

			// before wp_update_term()
			case 'wp.editTerm' :
				break;

			// before wp_delete_term()
			case 'wp.deleteTerm' :
				break;

			/** User *********************************************************/
			
			// before wp_update_user()
			case 'wp.editProfile' :
				break;

			/** Category *****************************************************/
			/*
			 * Fires xmlrpc_call_success_wp_newCategory action 
			 * Fires xmlrpc_call_success_wp_deleteCategory action 
			 */

			// before wp_insert_category()
			case 'wp.newCategory' :
				break;

			// before wp_delete_term()
			case 'wp.deleteCategory' :
				break;

			// before wp_set_post_categories()
			case 'mt.setPostCategories' :
				break;

			/** Comment ******************************************************/
			/*
			 * Fires xmlrpc_call_success_wp_deleteComment action 
			 * Fires xmlrpc_call_success_wp_editComment action 
			 * Fires xmlrpc_call_success_wp_newComment action 
			 */
			
			// before wp_delete_comment()
			case 'wp.deleteComment' :
				break;

			// before wp_update_comment()
			case 'wp.editComment' :
				break;

			// before wp_new_comment()
			case 'wp.newComment' :
				break;

			/** Attachment ***************************************************/
			/*
			 * Fires xmlrpc_call_success_mw_newMediaObject action 
			 */
			
			// before wp_insert_attachment()
			case 'metaWeblog.newMediaObject' :
				break;

		}
	}
}

// new Simple_History_Module_XMLRPC();

endif; // class_exists
