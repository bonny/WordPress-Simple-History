<?php

/*

# Logga installs av plugins manuellt

do_action( 'activate_plugin', $plugin, $network_wide );
do_action( 'activated_plugin', $plugin, $network_wide );
Action: activated_plugin
If a plugin is silently activated (such as during an update),
this hook does not fire.

Action: deactivated_plugin
Fires after a plugin is deactivated.
If a plugin is silently deactivated (such as during an update),
this hook does not fire.


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
		
		// add_action("admin_init", array($this, "on_admin_init"));
		
		add_action("admin_init", function() {

			#sf_d( $this );

			$this->info("Simple log message");
			$this->infoMessage("plugin_activated");

		});


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
					'Activated plugin {plugin_name} (version {plugin_version})', 
					'Plugin was non-silently activated by a user',
					'simple-history'
				),
				'plugin_deactivated' => _x(
					'Deactivated plugin {plugin_name} from {plugin_old_version} to {plugin_new_version}', 
					'Plugin was non-silently deactivated by a user',
					'simple-history'
				),
				'plugin_message_with_no_context' => __('This is the message', 'simple-history'),
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
	function simple_history_activated_plugin($plugin_name) {

		// Fetch info about the plugin
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
		
		if ( is_array( $plugin_data ) && ! empty( $plugin_data["Name"] ) ) {
			$plugin_name = urlencode( $plugin_data["Name"] );
		} else {
			$plugin_name = urlencode($plugin_name);
		}

		simple_history_add("action=activated&object_type=plugin&object_name=$plugin_name");
	}

	/**
	 * Plugin is deactivated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 */
	function simple_history_deactivated_plugin($plugin_name) {

		// Fetch info about the plugin
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
		
		if ( is_array( $plugin_data ) && ! empty( $plugin_data["Name"] ) ) {
			$plugin_name = urlencode( $plugin_data["Name"] );
		} else {
			$plugin_name = urlencode($plugin_name);
		}
		
		simple_history_add("action=deactivated&object_type=plugin&object_name=$plugin_name");

	}

}
