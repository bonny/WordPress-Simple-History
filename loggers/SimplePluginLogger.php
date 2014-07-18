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
				'plugin_installed' => _x(
					'Installed plugin "{plugin_name}"', 
					'Plugin was installed',
					'simple-history'
				),
				'plugin_installed_failed' => _x(
					'Failed to install plugin "{plugin_name}"', 
					'Plugin failed to install',
					'simple-history'
				),
				'plugin_updated' => _x(
					'Updated plugin "{plugin_name}"', 
					'Plugin was updated',
					'simple-history'
				),
				'plugin_update_failed' => _x(
					'Updated plugin "{plugin_name}"', 
					'Plugin update failed',
					'simple-history'
				),

			)
		);
		
		return $arr_info;

	}

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


		// Fires after the upgrades has done it's thing
		// Check hook extra for upgrader initiator
		add_action( 'upgrader_post_install', array( $this, "on_upgrader_post_install" ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, "on_upgrader_process_complete" ), 10, 2 );



		/*
		do_action( 'automatic_updates_complete', $this->update_results );
		 * Fires after all automatic updates have run.
		 *
		 * @since 3.8.0
		 *
		 * @param array $update_results The results of all attempted updates.
		*/

	}

	/**
	 * Called when WordPress Core is updated
	 */
	function on_upgrader_process_complete( $plugin_upgrader_instance, $arr_data ) {

		/*
		
		# WordPress core update
		
		$arr_data:
		Array
		(
		    [action] => update
		    [type] => core
		)

		
		# Plugin install
		
		$arr_data:
		Array
		(
		    [type] => plugin
		    [action] => install
		)


		# Plugin update
		
		$arr_data:
		Array
		(
		    [type] => plugin
		    [action] => install
		)

		# Bulk actions

		array(
			'action' => 'update',
			'type' => 'plugin',
			'bulk' => true,
			'plugins' => $plugins,
		)

		*/

		// To keep track of if something was logged, so wen can output debug info only
		// only if we did not log anything
		$did_log = false;

		if ( isset( $arr_data["type"] ) && "plugin" == $arr_data["type"] ) {

			// Single plugin install
			if ( isset( $arr_data["action"] ) && "install" == $arr_data["action"] ) {

				$context = array(
					"plugin_name" => $plugin_upgrader_instance->skin->api->name,
					"plugin_slug" => $plugin_upgrader_instance->skin->api->slug,
					"plugin_version" => $plugin_upgrader_instance->skin->api->version,
					"plugin_author" => $plugin_upgrader_instance->skin->api->author,
					"plugin_last_updated" => $plugin_upgrader_instance->skin->api->last_updated,
					"plugin_source_files" => json_encode( $plugin_upgrader_instance->result["source_files"] )
				);

				if ( is_a( $plugin_upgrader_instance->skin->result, "WP_Error" ) ) {

					// Add errors
					// Errors is in original wp admin language
					$context["error_messages"] = json_encode( $plugin_upgrader_instance->skin->result->errors );
					$context["error_data"] = json_encode( $plugin_upgrader_instance->skin->result->error_data );

					$this->infoMessage(
						'plugin_installed_failed',
						$context
					);

					$did_log = true;
					
				} else {

					$this->infoMessage(
						'plugin_installed',
						$context
					);

					$did_log = true;

				}

			}

			// Single plugin update
			if ( isset( $arr_data["action"] ) && "update" == $arr_data["action"] ) {

				// No plugin info in instance, so get it ourself
				#sf_d( $plugin_upgrader_instance, '$plugin_upgrader_instance' );
				#sf_d( $arr_data, '$arr_data' );
				$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $arr_data["plugin"] );
				
				$context = array(
					"plugin_name" => $plugin_data["Name"],
					"plugin_title" => $plugin_data["Title"],
					"plugin_description" => $plugin_data["Description"],
					"plugin_author" => $plugin_data["Author"],
					"plugin_version" => $plugin_data["Version"],
					"plugin_url" => $plugin_data["PluginURI"],
					"plugin_source_files" => json_encode( $plugin_upgrader_instance->result["source_files"] )
				);

				if ( is_a( $plugin_upgrader_instance->skin->result, "WP_Error" ) ) {

					// Add errors
					// Errors is in original wp admin language
					$context["error_messages"] = json_encode( $plugin_upgrader_instance->skin->result->errors );
					$context["error_data"] = json_encode( $plugin_upgrader_instance->skin->result->error_data );

					$this->infoMessage(
						'plugin_update_failed',
						$context
					);

					$did_log = true;
					
				} else {

					$this->infoMessage(
						'plugin_updated',
						$context
					);

					$did_log = true;

				}

			}
		
		
		}

		if ( ! $did_log ) {
			echo "on_upgrader_process_complete";
			sf_d( $plugin_upgrader_instance, '$plugin_upgrader_instance' );
			sf_d( $arr_data, '$arr_data' );
			exit;
		}

	}

	/*
	 * Called from filter 'upgrader_post_install'. 
	 * Used to log plugin installs and updates
	 *
	 * Filter docs:
	 *
	 * Filter the install response after the installation has finished.
	 *
	 * @param bool  $response   Install response.
	 * @param array $hook_extra Extra arguments passed to hooked filters.
	 * @param array $result     Installation result data.
	 */
	public function on_upgrader_post_install( $response, $hook_extra, $result ) {
		
		#echo "on_upgrader_post_install";
		/*
		
		# Plugin update:
		$hook_extra
		Array
		(
		    [plugin] => plugin-folder/plugin-name.php
		    [type] => plugin
		    [action] => update
		)

		# Plugin install, i.e. download/install, but not activation:
		$hook_extra:
		Array
		(
		    [type] => plugin
		    [action] => install
		)

		*/

		if ( isset( $hook_extra["action"] ) && $hook_extra["action"] == "install" && isset( $hook_extra["type"] ) && $hook_extra["type"] == "plugin" ) {

			// It's a plugin install
			

		} else if ( isset( $hook_extra["action"] ) && $hook_extra["action"] == "update" && isset( $hook_extra["type"] ) && $hook_extra["type"] == "plugin" ) {
			
			// It's a plugin upgrade
			echo "plugin update!";

		}

		#sf_d($response, '$response');
		#sf_d($hook_extra, '$hook_extra');
		#sf_d($result, '$result');

		return $response;

	}

	/*


		*
		 * Fires when the bulk upgrader process is complete.
		 *
		 * @since 3.6.0
		 *
		 * @param Plugin_Upgrader $this Plugin_Upgrader instance. In other contexts, $this, might
		 *                              be a Theme_Upgrader or Core_Upgrade instance.
		 * @param array           $data {
		 *     Array of bulk item update data.
		 *
		 *     @type string $action   Type of action. Default 'update'.
		 *     @type string $type     Type of update process. Accepts 'plugin', 'theme', or 'core'.
		 *     @type bool   $bulk     Whether the update process is a bulk update. Default true.
		 *     @type array  $packages Array of plugin, theme, or core packages to update.
		 * }
		 *
		do_action( 'upgrader_process_complete', $this, array(
			'action' => 'update',
			'type' => 'plugin',
			'bulk' => true,
			'plugins' => $plugins,
		) );


	do_action( 'upgrader_process_complete', $this, array( 'action' => 'update', 'type' => 'core' ) );
	*/

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
