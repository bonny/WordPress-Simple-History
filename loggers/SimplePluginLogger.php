<?php

/*


# logga installs/updates av plugins som är silent

// Innan update körs en av dessa
$current = get_site_option( 'active_sitewide_plugins', array() );
$current = get_option( 'active_plugins', array() );

// efter update körs en av dessa
update_site_option( 'active_sitewide_plugins', $current );
update_option('active_plugins', $current);

// så: jämför om arrayen har ändrats, och om den har det = ny plugin aktiverats




# extra stuff
vid aktivering/installation av plugin: spara resultat från
get_plugin_files($plugin)
så vid ev intrång/skadlig kod uppladdad så kan man analysera lite

*/


/**
 * Logs plugins installs and updates
 */
class SimplePluginLogger extends SimpleLogger
{

	// The logger slug. Defaulting to the class name is nice and logical I think
	public $slug = __CLASS__;

	public function loaded() {

		/**
		 * Manual plugin activation and de-activation
		 */

		// Fires after a plugin has been activated.
		// If a plugin is silently activated (such as during an update),
		// this hook does not fire.
		add_action( 'activated_plugin', array( $this, "on_activated_plugin" ), 10, 2 );
		
		// Fires after a plugin is deactivated.
		// If a plugin is silently deactivated (such as during an update),
		// this hook does not fire.
		add_action( 'deactivated_plugin', array( $this, "on_deactivated_plugin" ), 10, 2 );

	}

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => "Plugin Logger",
			"description" => "Logs plugin installs, uninstalls and updates",
			"capability" => "activate_plugins", // install_plugins, activate_plugins, 
			"messages" => array(
				'plugin_activated' => _x(
					'Activated plugin "{plugin_name}"', 
					'Plugin was non-silently activated by a user',
					'simple-history'
				),
				'plugin_deactivated' => _x(
					'Deactivated plugin "{plugin_name}"', 
					'Plugin was non-silently deactivated by a user',
					'simple-history'
				),
			)
		);
		
		return $arr_info;

	}

	/**
	 * Called when a post is restored from the trash
	 */
	function on_do_da_thingie($post_id) {

		$post = get_post($post_id);

		$this->info(
			$this->messages["post_restored"],
			array(
				"post_id" => $post_id,
				"post_type" => get_post_type($post),
				"post_title" => get_the_title($post)
			)
		);

	}

	/**
	 * Plugin is activated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 */
	function on_activated_plugin($plugin_name, $network_wide) {

		/*
		Plugin data returned array contains the following:
		'Name' - Name of the plugin, must be unique.
		'Title' - Title of the plugin and the link to the plugin's web site.
		'Description' - Description of what the plugin does and/or notes from the author.
		'Author' - The author's name
		'AuthorURI' - The authors web site address.
		'Version' - The plugin version number.
		'PluginURI' - Plugin web site address.
		'TextDomain' - Plugin's text domain for localization.
		'DomainPath' - Plugin's relative directory path to .mo files.
		'Network' - Boolean. Whether the plugin can only be activated network wide.
		*/
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
		
		$context = array(
			"plugin_name" => $plugin_data["Name"],
			"plugin_title" => $plugin_data["Title"],
			"plugin_description" => $plugin_data["Description"],
			"plugin_author" => $plugin_data["Author"],
			"plugin_version" => $plugin_data["Version"],
			"plugin_url" => $plugin_data["PluginURI"],
		);

		$this->infoMessage( 'plugin_activated', $context );
		
	}

	/**
	 * Plugin is deactivated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 */
	function on_deactivated_plugin($plugin_name) {

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
		
		$context = array(
			"plugin_name" => $plugin_data["Name"],
			"plugin_title" => $plugin_data["Title"],
			"plugin_description" => $plugin_data["Description"],
			"plugin_author" => $plugin_data["Author"],
			"plugin_version" => $plugin_data["Version"],
			"plugin_url" => $plugin_data["PluginURI"],
		);

		$this->infoMessage( 'plugin_deactivated', $context );

	}

}
