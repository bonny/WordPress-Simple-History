<?php

/**
 * Simple History Extend Class
 *
 * Use this class to build modules upon.
 * 
 * @package Simple History Extender
 * @subpackage Main
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Simple_History_Extend' ) ) :

/**
 * Plugin class
 */
class Simple_History_Extend {

	var $id;
	var $title;
	var $plugin;
	var $description;

	var $supports; // Events supported
	var $lacks; // Events not supported

	var $events;

	var $modules;

	/**
	 * Build this module
	 * 
	 * @param array $args Class arguments. Requires
	 * array(
	 *     'id'          => The module id
	 *     'title'       => The module title
	 *     'plugin'      => The plugin basename or False if not a plugin
	 *     'description' => Description of the module
	 *     'tabs'        => The contents of the contextual help tab. Can
	 *                       contain 'supports' array and 'lacks' array
	 * );
	 * @return void
	 */
	function __construct( $args ){
		$this->setup_globals( $args );
		$this->setup_actions();
	}

	/**
	 * Define local class variables
	 * 
	 * @param array $args Class arguments
	 * @return void
	 */
	function setup_globals( $args ){

		// Set module params
		$this->id          = $args['id'];
		$this->title       = $args['title'];
		$this->plugin      = isset( $args['plugin'] ) ? $args['plugin'] : false;
		$this->description = isset( $args['description'] ) 
			? $args['description'] 
			: ( $this->plugin 
				? sprintf( __('Log events for the %s plugin.', 'sh-extender'), $this->title )
				: sprintf( __('Log events for %s.', 'sh-extender'), $this->title )
				);

		// Set module tab contents
		$this->supports    = isset( $args['tabs']['supports'] ) ? $args['tabs']['supports'] : $args['tabs'];
		$this->lacks       = isset( $args['tabs']['lacks'] ) ? $args['tabs']['lacks'] : array();

		// Set module events
		$this->events      = $this->setup_events();
	}

	/**
	 * Return generic events for setup in $this->events
	 * 
	 * @return array $events
	 */
	public function setup_events(){

		// Setup default events
		$events = array( 
			'new'     => __('created', 'sh-extender'),
			'edit'    => __('edited', 'sh-extender'),
			'delete'  => __('deleted', 'sh-extender'),
			'spam'    => __('marked as spam', 'sh-extender'),
			'unspam'  => __('unmarked as spam', 'sh-extender'),
			'trash'   => __('trashed', 'sh-extender'),
			'untrash' => __('untrashed', 'sh-extender'),
			'submit'  => __('submitted', 'sh-extender')
			);

		return wp_parse_args( $this->add_events(), $events );
	}

	/**
	 * Register action and filter hooks
	 * 
	 * @return void
	 */
	public function setup_actions(){

		// Bail out if module plugin is not active
		if ( $this->plugin && !is_plugin_active( $this->plugin ) )
			return;

		// Register settings field and help tab
		add_action( 'admin_init',                array( $this, 'settings_field' ) );
		add_filter( 'sh_extender_add_help_tabs', array( $this, 'add_help_tab'   ) );

		// Do we really need to load this every time we create a module?
		$modules = get_option( 'sh_extender_modules' );

		// Bail out if module is not active
		if ( !isset( $modules[$this->id] ) || !$modules[$this->id]['active'] )
			return;

		// Register custom log actions
		$this->add_actions();
	}

	/** Override this function in child class to add custom events to $this->events */
	function add_events(){
		return array();
	}

	/** Override this function in child class to add log actions */
	function add_actions(){
	}

	/**
	 * Adds the module help tab to the contextual admin help
	 * 
	 * @param array $tabs The tabs already added
	 * @return array $tabs The tabs with module tab added
	 */
	function add_help_tab( $tabs ){

		// This module has no tab
		if ( empty( $this->supports ) )
			return $tabs;

		// Build content string starting with supporting events
		$content = '<p><strong>'. sprintf( __('The %s module logs the following events:', 'sh-extender'), $this->title ) .'</strong></p><p>';

		// Create supports list
		$content .= '<ul>';

		// Create supports list items
		foreach ( $this->supports as $item )
			$content .= '<li>'. $item .'</li>';

		// Close supports list
		$content .= '</ul>';

		// Add non-supported events if there are any
		if ( !empty( $this->lacks ) ){

			$content .= '</p><p><strong>'. sprintf( __('The %s module does not support the following events:', 'sh-extender'), $this->title ) .'</strong></p><p>';

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
	 * @return void
	 */
	public function settings_field(){
		global $simple_history_extender;

		add_settings_field( 
			$simple_history_extender->modules_name .'['. $this->id .']', 
			$this->title, 
			array( $this, 'module_field' ), 
			$simple_history_extender->page, 
			$simple_history_extender->modules_section 
			);
	}

	/**
	 * Output settings field for this module
	 * 
	 * @return void
	 */
	public function module_field(){
		global $simple_history_extender;

		echo '<label><input type="checkbox" name="'. $simple_history_extender->modules_name .'['. $this->id .'][active]" value="1" '. checked( isset( $simple_history_extender->modules[$this->id] ) ? $simple_history_extender->modules[$this->id]['active'] : false, true, false ) .' /> ';
		echo '<span class="description">'. $this->description .'</span></label>';
	}

	/** Helpers ******************************************************/

	/**
	 * Add a custom event to the Simple History plugin
	 * 
	 * Call this function in all modules with $this->extend()
	 *
	 * @param array $r The event arguments. Shortened for convenience:
	 *  - action         > action
	 *  - object_type    > type
	 *  - object_subtype > subtype
	 *  - object_name    > name
	 *  - object_id      > id
	 *  - user_id        > user_id
	 * @uses simple_history_extend() To add an event to Simple History
	 * @return void
	 */
	function extend( $r ){
		$args = array(
			'action'         => isset( $r['action']  ) ? $r['action']  : __('updated'),
			'object_type'    => isset( $r['type']    ) ? $r['type']    : null,
			'object_subtype' => isset( $r['subtype'] ) ? $r['subtype'] : null,
			'object_name'    => isset( $r['name']    ) ? $r['name']    : null,
			'object_id'      => isset( $r['id']      ) ? $r['id']      : null,
			'user_id'        => isset( $r['user_id'] ) ? $r['user_id'] : null
			);

		// Do the magic
		simple_history_add( $args );
	}

	static function extendStatic( $r ){
		$args = array(
			'action'         => isset( $r['action']  ) ? $r['action']  : __('updated'),
			'object_type'    => isset( $r['type']    ) ? $r['type']    : null,
			'object_subtype' => isset( $r['subtype'] ) ? $r['subtype'] : null,
			'object_name'    => isset( $r['name']    ) ? $r['name']    : null,
			'object_id'      => isset( $r['id']      ) ? $r['id']      : null,
			'user_id'        => isset( $r['user_id'] ) ? $r['user_id'] : null
			);

		// Do the magic
		simple_history_add( $args );
	}

	/**
	 * Extend Simple History shortcut for User type
	 * 
	 * @param int $user_id User ID
	 * @param string $action The logged action
	 * @return void
	 */
	function extend_user( $user_id, $action ){
		$user = get_userdata( $user_id );

		$this->extend( array( 
			'action' => $action,
			'type'   => __('User'),
			'name'   => apply_filters( 'she_extend_user_name', $user->user_login ),
			'id'     => $user_id
			) );
	}

	/**
	 * Extend Simple History shortcut for Post type
	 * 
	 * @param int $post_id Post ID
	 * @param string $action The logged action
	 * @return void
	 */
	function extend_post( $post_id, $action ){
		$post_type = get_post_type( $post_id );
		$cpt       = get_post_type_object( $post_type );

		$this->extend( array( 
			'action' => $action,
			'type'   => $cpt->labels->singular_name,
			'name'   => get_the_title( $post_id ), // Previous or changed title?
			'id'     => $post_id
			) );
	}
}

endif; // class_exists
