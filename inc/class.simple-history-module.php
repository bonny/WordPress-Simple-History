<?php

/**
 * Simple History Module Class
 *
 * Use this class to build modules with.
 * 
 * @package Simple History Modules
 * @subpackage Main
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simple_History_Module' ) ) :

/**
 * Plugin class
 */
class Simple_History_Module {

	/**
	 * @var string The module identifier
	 */
	var $id;

	/**
	 * @var string The plugin or module screen title
	 */
	var $title;

	/**
	 * @var string The plugin basename or false if not a plugin
	 */
	var $plugin;

	/**
	 * @var string The module description
	 */
	var $description;

	/**
	 * @var array The supported events described on screen
	 */
	var $supports;

	/**
	 * @var array The not supported events described on screen
	 */
	var $lacks;

	/**
	 * @var array Events shortcuts for event description used in the plugin
	 */
	var $events;

	/**
	 * Build this module
	 * 
	 * @param array $args Class arguments. Requires id, title, plugin, description and 
	 *                     tabs, which can contain both 'supports' and 'lacks' arrays
	 * @return void
	 */
	function __construct( $args ){
		$this->setup_globals( $args );
		$this->setup_actions();
	}

	/**
	 * Define local class variables
	 *
	 * @since 1.1
	 * 
	 * @param array $args Class arguments
	 */
	function setup_globals( $args ){

		// Parse args to defaults
		$defaults = array(
			'id'          => '',
			'title'       => '',
			'plugin'      => false,
			'description' => false,
			'tabs'        => array()
			);
		$args = wp_parse_args( $args, $defaults );

		// Set module params
		$this->id          = $args['id'];
		$this->title       = $args['title'];
		$this->plugin      = isset( $args['plugin'] ) ? $args['plugin'] : false;
		$this->description = ! empty( $args['description'] )
			? $args['description'] 
			: ( ! empty( $this->plugin ) 
				? sprintf( __('Log events for the %s plugin.', 'simple-history'), $this->title )
				: sprintf( __('Log events for %s.',            'simple-history'), $this->title )
			);

		// Set module tab contents
		$this->supports    = isset( $args['tabs']['supports'] ) ? $args['tabs']['supports'] : $args['tabs'];
		$this->lacks       = isset( $args['tabs']['lacks'] )    ? $args['tabs']['lacks']    : array();

		// Set module events as object
		$this->events      = (object) $this->setup_events();
	}

	/**
	 * Return generic events for setup in $this->events
	 *
	 * @since 1.1
	 * 
	 * @return array $events
	 */
	public function setup_events(){
		$events = array( 
			// Translators: 1. Type, 2. Name
			'new'     => __('%1$s %2$s created',          'simple-history'),
			'add'     => __('%1$s %2$s added',            'simple-history'),
			'edit'    => __('%1$s %2$s edited',           'simple-history'),
			'delete'  => __('%1$s %2$s deleted',          'simple-history'),
			'spam'    => __('%1$s %2$s marked as spam',   'simple-history'),
			'unspam'  => __('%1$s %2$s unmarked as spam', 'simple-history'),
			'trash'   => __('%1$s %2$s trashed',          'simple-history'),
			'untrash' => __('%1$s %2$s untrashed',        'simple-history'),
			'submit'  => __('%1$s %2$s submitted',        'simple-history')
			);

		// Merge custom events with default events
		return wp_parse_args( $this->add_events(), $events );
	}

	/** Override this function in child class to add custom events to $this->events */
	function add_events(){
		return array();
	}

	/**
	 * Register action and filter hooks
	 * 
	 * @since 1.1
	 */
	public function setup_actions(){

		// Bail if module plugin is not active
		if ( $this->plugin && ! is_plugin_active( $this->plugin ) )
			return;

		// Register settings field and help tab
		add_action( 'admin_init',                           array( $this, 'settings_field' ) );
		add_filter( 'simple_history_modules_add_help_tabs', array( $this, 'add_help_tab'   ) );

		// Do we really need to load this every time we create a module?
		$modules = get_option( 'simple_history_modules' ); // Prev sh_extender_modules

		// Bail if module is not active
		if ( ! isset( $modules[$this->id] ) || ! $modules[$this->id]['active'] )
			return;

		// Register custom log actions
		$this->add_actions();
	}

	/** Override this function in child class to add log actions */
	function add_actions(){
	}

	/**
	 * Adds the module help tab to the contextual admin help
	 *
	 * @since 1.1
	 * 
	 * @param array $tabs The tabs already added
	 * @return array $tabs The tabs with module tab added
	 */
	function add_help_tab( $tabs ){

		// This module has no tab
		if ( empty( $this->supports ) )
			return $tabs;

		// Build content string starting with supporting events
		$content = '<p><strong>'. sprintf( __('The %s module logs the following events:', 'simple-history'), $this->title ) .'</strong></p><p>';

		// Create supports list
		$content .= '<ul>';

		// Create supports list items
		foreach ( $this->supports as $item )
			$content .= '<li>'. $item .'</li>';

		// Close supports list
		$content .= '</ul>';

		// Add non-supported events if there are any
		if ( ! empty( $this->lacks ) ){

			$content .= '</p><p><strong>'. sprintf( __('The %s module does not support the following events:', 'simple-history'), $this->title ) .'</strong></p><p>';

			// Create lacks list
			$content .='<ul>';

			// Create lacks list items
			foreach ( $this->lacks as $item )
				$content .= '<li>'. $item .'</li>';

			// Close lacks list
			$content .='</ul>';
		}

		// Close content string
		$content .= '</p>';

		// Add module tab to the tabs
		$tabs[] = array(
			'id'      => $this->id,
			'title'   => $this->title,
			'content' => $content
			);

		return $tabs;
	}

	/**
	 * Register settings field for this module
	 *
	 * @since 1.1
	 *
	 * @uses $simple_history_modules
	 */
	public function settings_field(){
		global $simple_history_modules;

		add_settings_field( 
			$simple_history_modules->name .'['. $this->id .']', 
			$this->title, 
			array( $this, 'module_field' ), 
			$simple_history_modules->page, 
			$simple_history_modules->modules_section 
			);
	}

	/**
	 * Output settings field for this module
	 * 
	 * @since 1.1
	 *
	 * @uses $simple_history_modules
	 */
	public function module_field(){
		global $simple_history_modules;

		printf( '<label><input type="checkbox" name="%s" value="1" %s />',
			$simple_history_modules->name .'['. $this->id .'][active]',
			checked( 
				isset( $simple_history_modules->modules[$this->id] ) 
					? $simple_history_modules->modules[$this->id]['active'] 
					: false, 
				true, 
				false 
				)
			);
		echo ' <span class="description">'. $this->description .'</span></label>';
	}

	/** Logging ******************************************************/

	/**
	 * Log a custom event
	 * 
	 * Call this function in all modules with $this->log()
	 * 
	 * @since 1.1
	 *
	 * @uses simple_history_add() To add an event to Simple History
	 * @param array $r The event arguments. Shortened for convenience:
	 *  - action         > action
	 *  - object_type    > type
	 *  - object_subtype > subtype
	 *  - object_name    > name
	 *  - object_id      > id
	 *  - user_id        > user_id
	 *  - description    > desc
	 */
	function log( $r ){ // Prev extend( $r )
		$args = array(
			'action'         => isset( $r['action']  ) ? $r['action']  : __('updated'),
			'object_type'    => isset( $r['type']    ) ? $r['type']    : null,
			'object_subtype' => isset( $r['subtype'] ) ? $r['subtype'] : null,
			'object_name'    => isset( $r['name']    ) ? $r['name']    : null,
			'object_id'      => isset( $r['id']      ) ? $r['id']      : null,
			'user_id'        => isset( $r['user_id'] ) ? $r['user_id'] : null,
			'description'    => isset( $r['desc']    ) ? $r['desc']    : null
			);

		// Do the magic
		simple_history_add( $args );
	}

	/**
	 * User logger. Requires user ID
	 *
	 * @since 1.1
	 * 
	 * @param int $user_id User ID
	 * @param string $action Log message
	 * @param string $desc Optional. Event description
	 */
	function log_user( $user_id, $action, $desc = '' ){
		$user = get_userdata( $user_id );

		$this->log( array( 
			'action' => $action,
			'type'   => 'user',
			'name'   => apply_filters( 'simple_history_log_user_name', $user->user_nicename ),
			'id'     => $user_id,
			'desc'   => $desc
		) );
	}

	/**
	 * Post logger. Requires post ID or object
	 *
	 * @since 1.1
	 *  
	 * @param int|WP_Post $post Post ID or data object
	 * @param string $action Log message
	 * @param string $desc Optional. Event description
	 */
	function log_post( $post, $action, $desc = '' ){
		$post_id   = is_object( $post ) ? $post->ID        : (int) $post;
		$post_type = is_object( $post ) ? $post->post_type : get_post_type( $post_id );

		$this->log( array( 
			'action' => $action,
			'type'   => $post_type,
			'name'   => get_the_title( $post_id ), // Previous or changed title?
			'id'     => $post_id,
			'desc'   => $desc
		) );
	}

	/**
	 * Test logger. 
	 * 
	 * Available for quick log setup for hook testing. Only runs 
	 * in developer mode with WP_DEBUG = true. 
	 *
	 * @since 1.3.5
	 * 
	 * @param string $message Test message
	 */
	function log_test( $message, $desc = '' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) 
			return;

		$this->log( array(
			'action' => '%1$s: '. $message,
			'type'   => 'test',
			'name'   => '', // Empty or internal counter?
			'id'     => 0, // Empty or internal counter?
			'desc'   => $desc
		) );
	}
}

endif; // class_exists
