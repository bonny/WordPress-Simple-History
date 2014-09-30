<?php

/**
 * Plugin Name: Simple History Extender
 * Description: Extend the Simple History plugin with more events to log.
 * Version: 0.0.3
 * Author: Laurens Offereins
 * Author URI: http://www.offereinspictures.nl
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'SimpleHistoryExtender' ) ) :

/**
 * Plugin class
 */
class SimpleHistoryExtender {

	public $file;
	public $basename;
	public $sh_plugin;
	public $domain;
	public $page;
	public $sh_pagenow;
	public $opt_group;
	public $plugin_dir;

	public $modules_dir;
	public $modules_name;
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
	 * 
	 * @return void
	 */
	public function setup_globals(){
		$this->file            = __FILE__;
		$this->basename        = plugin_basename( $this->file );
		$this->sh_plugin       = 'simple-history/index.php';
		$this->domain          = 'sh-extender';
		$this->page            = 'simple_history_settings_menu_slug';
		$this->sh_pagenow      = 'settings_page_'. $this->page;
		$this->opt_group       = 'simple_history_settings_group';
		$this->plugin_dir      = plugin_dir_path( $this->file );

		$this->modules_dir     = $this->plugin_dir .'modules/';
		$this->modules_name    = 'sh_extender_modules';
		$this->modules_section = 'sh-extender-modules';
		$this->modules         = get_option( $this->modules_name );
	}

	/**
	 * Register action and filter hooks
	 * 
	 * @return void
	 */
	public function setup_actions(){

		// Plugin
		add_action( 'activate_'.   $this->basename,  array( $this, 'on_activate'       ) );
		add_action( 'deactivate_'. $this->basename,  array( $this, 'on_deactivate'     ) );
		add_action( 'uninstall_'.  $this->basename,  array( $this, 'on_uninstall'      ) );
		add_action( 'plugins_loaded',                array( $this, 'textdomain'        ) );
		add_filter( 'plugin_action_links',           array( $this, 'action_links'      ), 10, 2 );
		add_action( 'deactivate_'. $this->sh_plugin, array( $this, 'on_deactivate_sh'  ) );

		// Admin
		add_action( 'init',                          array( $this, 'load_modules'      ) );
		add_action( 'admin_init',                    array( $this, 'register_settings' ) );
		add_action( 'load-'. $this->sh_pagenow,      array( $this, 'add_help_tabs'     ) );
	}

	/** Plugin *******************************************************/

	/**
	 * Act on plugin activation
	 * 
	 * @return void
	 */
	public function on_activate(){
		
		// Cancel activation if Simple History is not active
		if ( !is_plugin_active( $this->sh_plugin ) ){
			$this->deactivate_plugin( true );
		}
	}

	/**
	 * Act on plugin deactivation
	 * 
	 * @return void
	 */
	public function on_deactivate(){
		// Do stuff
	}

	/**
	 * Act on plugin uninstallation
	 * 
	 * @return void
	 */
	public function on_uninstall(){

		// Remove option from DB
		delete_option( $this->modules_name );
	}

	/**
	 * Load the translation files
	 * 
	 * @return void
	 */
	public function textdomain(){
		load_plugin_textdomain( $this->domain, false, dirname( $this->basename ) . '/languages/' );
	}

	/**
	 * Add plugin action links to the plugin row actions
	 *
	 * @param array $links The current plugin row actions
	 * @param string $file The plugin file basename
	 */
	public function action_links( $links, $file ) {

		// Create settings link for this plugin only
		if ( $this->basename == $file )
			$links[] = '<a href="' . add_query_arg( 'page', $this->page, 'options-general.php' ) . '">'. __('Settings') .'</a>';

		return $links;
	}

	/**
	 * Deactivate this plugin and maybe display error message
	 *
	 * @param boolean $die Whether this function should execute wp_die()
	 * @param integer $message The message ID of the message to display
	 * @uses deactivate_plugins()
	 * @return void
	 */
	public function deactivate_plugin( $die = false, $message = 0 ){

		// Deactivate this plugin
		deactivate_plugins( $this->basename );

		// Redirect user and present die message
		if ( $die ){

			// Default messages
			$messages = array(
				0 => __('The Simple History Extender plugin was deactivated because the Simple History plugin was not found installed or active.', 'sh-extender'),
				1 => __('The Simple History Extender plugin was deactivated.', 'sh-extender')
			);

			wp_die( sprintf( 
				'<p>'. $messages[$message] .'</p><p><a href="%s">'. __('Return') .'</a></p>', 
				// Remove previous messages
				remove_query_arg( array( 'activate', 'deactivate', 'error' ), wp_get_referer() )
				) );
			exit;
		} 
	}

	/**
	 * Do plugin deactivation on Simple History deactivation
	 *
	 * Deactivates this plugin silently without feedback
	 *
	 * @link http://wordpress.stackexchange.com/questions/27850/deactivate-plugin-upon-deactivation-of-another-plugin/56924#56924
	 */
	public function on_deactivate_sh(){
		if ( is_plugin_active( $this->basename ) )
			add_action( 'update_option_active_plugins', array( $this, 'deactivate_plugin' ) );
	}

	/** Admin ********************************************************/

	/**
	 * Load the module files
	 *
	 * Require files later then 'plugins_loaded' action for 
	 * the module translation strings to be processed.
	 *
	 * @uses do_action() To call 'she_load_modules' for custom modules
	 * 
	 * @return void
	 */
	public function load_modules(){

		// Make is_plugin_active() available
		include_once( ABSPATH . 'wp-admin/includes/plugin.php'  );

		// Load Extend class which the modules depend upon
		require( $this->plugin_dir  . 'class.simple-history-extend.php' );

		// Load modules from directory
		foreach ( scandir( $this->modules_dir ) as $file ){
			if ( 'php' == pathinfo( $file, PATHINFO_EXTENSION ) && file_exists( $this->modules_dir . $file  ) )
				require( $this->modules_dir . $file );
		}

		// Hook for loading custom modules
		do_action( 'she_load_modules' );
	}

	/**
	 * Add module settings to the Simple History admin page
	 *
	 * Creates an extra settings section for our modules and registers
	 * all modules in one option in the DB.
	 * 
	 * @return void
	 */
	public function register_settings(){
		add_settings_section( $this->modules_section, __('Simple History Extender Modules', 'sh-extender'), array( $this, 'modules_settings_intro' ), $this->page );
		register_setting( $this->opt_group, $this->modules_name, array( $this, 'modules_settings_sanitize' ) );
	}

	/**
	 * Output settings section information text
	 * 
	 * @return void
	 */
	public function modules_settings_intro(){
		echo '<p>'. __( 'Activate or deactivate the events you want to log. Read the Help tab if you want to know which actions are supported and which aren\'t.', 'sh-extender') .'</p>';
	}

	/**
	 * Sanitize input values before saving settings to DB
	 *
	 * Additionally logs which modules are (de)activated.
	 * 
	 * @param array $input The input values
	 * @return array $retval The sanitized values
	 */
	public function modules_settings_sanitize( $input ){
		global $wp_settings_fields;

		$old    = get_option( $this->modules_name, array() );
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

				Simple_History_Extend::extendStatic( array(
					'action' => $retval[$module]['active'] ? __('activated', 'sh-extender') : __('deactivated', 'sh-extender'),
					'type'   => __('Simple History Extender Module', 'sh-extender'),
					'name'   => $field['title'],
					'id'     => $module
					) );
			}
		}

		return $retval;
	}

	/**
	 * Add module help tabs to the admin page contextual help
	 *
	 * @uses apply_filters() To call sh_extender_add_help_tabs where
	 *                        modules can add their unique help tab
	 * @uses WP_Screen::add_help_tab()
	 */
	public function add_help_tabs(){
		$tabs = apply_filters( 'sh_extender_add_help_tabs', array() );

		// Loop over all tabs and add them to the screen
		foreach ( $tabs as $tab )
			get_current_screen()->add_help_tab( $tab );
	}
}

$GLOBALS['simple_history_extender'] = new SimpleHistoryExtender();

endif; // class_exists