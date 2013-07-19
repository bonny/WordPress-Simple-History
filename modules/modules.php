<?php

/**
 * Simple History Modules Loader
 *
 * Modified code from Simple History Extender plugin by Laurens Offereins
 *
 * @since 1.1
 * 
 * @package Simple History
 * @subpackage Modules
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simple_History_Modules' ) ) :

/**
 * Plugin class
 */
class Simple_History_Modules {

	/** File vars **/
	public $file;
	public $basename;
	public $modules_dir;
	public $plugin_dir;

	/** Settings vars **/
	public $page;
	public $pagenow;
	public $opt_group;

	/** Modules vars **/
	public $name;
	public $modules_section;
	public $modules;

	/**
	 * Build this class
	 */
	public function __construct(){
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Define local class variables
	 */
	public function setup_globals(){

		/** Plugin *******************************************************/
		
		$this->file            = __FILE__;
		$this->basename        = plugin_basename( $this->file );
		$this->modules_dir     = plugin_dir_path( $this->file );
		$this->plugin_dir      = dirname( $this->modules_dir );
		
		/** Settings *****************************************************/

		$this->page            = 'simple_history_settings_menu_slug';
		$this->pagenow         = 'settings_page_'. $this->page;
		$this->opt_group       = 'simple_history_settings_group';

		/** Modules ******************************************************/
		
		$this->name            = 'simple_history_modules'; // Prev sh_extender_modules
		$this->modules         = get_option( $this->name );
		$this->modules_section = 'simple-history-modules'; // Prev sh-extender-modules
	}

	/**
	 * Register action and filter hooks
	 */
	public function setup_actions(){

		// Admin
		add_action( 'init',                  array( $this, 'load_modules'      ) );
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'load-'. $this->pagenow, array( $this, 'add_help_tabs'     ) );
	}

	/** Admin ********************************************************/

	/**
	 * Load the module files
	 *
	 * Require files later then 'plugins_loaded' action for 
	 * the module translation strings to be processed.
	 *
	 * @since 1.1
	 *
	 * @uses do_action() Calls 'simple_history_load_modules' for custom modules
	 */
	public function load_modules(){

		// Make is_plugin_active() available
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Load Module base class
		require( $this->plugin_dir . '/inc/class.simple-history-module.php' );

		// Load modules from directory
		foreach ( scandir( $this->modules_dir ) as $file ){
			if (   'php' == pathinfo( $file, PATHINFO_EXTENSION ) // Search php files
				&& file_exists( $this->modules_dir . $file  ) // Search exiting files
				&& false === strpos( $file, 'modules' ) // Exclude this __FILE__
				&& false === strpos( $file, 'index'   ) // Exclude index file
				)
				require_once( $this->modules_dir . $file );
		}

		// Hook for loading custom modules
		do_action( 'simple_history_load_modules' ); // Prev she_load_modules
	}

	/**
	 * Add module settings to the Simple History admin page
	 *
	 * Creates an extra settings section for our modules and registers
	 * all modules in one option in the DB.
	 *
	 * @since 1.1
	 *
	 * @uses register_setting() To save the activated modules to the DB
	 * @uses add_settings_section() To create the modules section
	 */
	public function register_settings(){
		register_setting( 
			$this->opt_group, 
			$this->name, 
			array( $this, 'modules_settings_sanitize' ) 
		);

		add_settings_section( 
			$this->modules_section, 
			__('Event Modules', 'simple-history'), 
			array( $this, 'modules_settings_intro' ), 
			$this->page 
		);
	}

	/**
	 * Output settings section information text
	 * 
	 * @since 1.1
	 */
	public function modules_settings_intro(){
		echo '<p>'. __( 'Activate or deactivate the events you want to log. Read the Help tab if you want to know which actions are supported and which aren\'t.', 'simple-history') .'</p>';
	}

	/**
	 * Sanitize input values before saving settings to DB
	 *
	 * Additionally logs which modules are (de)activated.
	 * 
	 * @since 1.1
	 * 
	 * @param array $input The input values
	 * @return array $retval The sanitized values
	 */
	public function modules_settings_sanitize( $input ){
		global $wp_settings_fields;

		$old    = get_option( $this->name, array() );
		$retval = array();

		// Sanitize input
		if ( ! is_array($input) ) $input = array();
		        foreach ( $input as $module => $args )
			$retval[$module]['active'] = isset( $args['active'] ) ? true : false;

		// Log module (de)activation
		foreach ( $wp_settings_fields[$this->page][$this->modules_section] as $id => $field ){

			// Strip module name from {settings_field_name[module_name]}
			$module = substr( $id, strpos( $id, '[' ) + 1, -1 );

			// Make sure not set modules are set to false
			if ( !isset( $retval[$module] ) )
				$retval[$module]['active'] = false;

			// Only log on change
			if ( ( !isset( $old[$module] ) && $retval[$module]['active'] ) 
				|| ( isset( $old[$module] ) && $old[$module]['active'] !== $retval[$module]['active'] )
				){

				// Setup action
				if ( $retval[$module]['active'] )
					$action =  __('%1$s %2$s activated',   'simple-history');
				else
					$action =  __('%1$s %2$s deactivated', 'simple-history');

				simple_history_add( array(
					'action'      => $action,
					'object_type' => __('Event Module', 'simple-history'),
					'object_name' => $field['title'],
					'object_id'   => $module
		        ) );
			}
		}

		return $retval;
	}

	/**
	 * Add module help tabs to the admin page contextual help
	 * 
	 * @since 1.1
	 *
	 * @uses apply_filters() To call sh_extender_add_help_tabs where
	 *                        modules can add their unique help tab
	 * @uses WP_Screen::add_help_tab()
	 */
	public function add_help_tabs(){
		$tabs = apply_filters( 'simple_history_modules_help_tabs', array() ); // Prev sh_extender_add_help_tabs

		// Loop over all tabs and add them to the screen
		foreach ( $tabs as $tab )
			get_current_screen()->add_help_tab( $tab );
	}
}

$GLOBALS['simple_history_modules'] = new Simple_History_Modules(); // Prev simple_history_extender

endif; // class_exists