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
					'Updated plugin "{plugin_name}" from {plugin_prev_version} to {plugin_version}', 
					'Plugin was updated',
					'simple-history'
				),

				'plugin_update_failed' => _x(
					'Updated plugin "{plugin_name}"', 
					'Plugin update failed',
					'simple-history'
				),

				'plugin_file_edited' => _x(
					'Edited plugin file "{plugin_edited_file}"', 
					'Plugin file edited',
					'simple-history'
				),

				'plugin_deleted' => _x(
					'Deleted plugin "{plugin_name}"', 
					'Plugin files was deleted',
					'simple-history'
				),

				// bulk versions
				'plugin_bulk_updated' => _x(
					'Updated plugin "{plugin_name}" from {plugin_prev_version} to {plugin_version}', 
					'Plugin was updated in bulk',
					'simple-history'
				),


			)
		);
		
		return $arr_info;

	}

	public function loaded() {

		#sf_d(get_plugins(), 'get_plugins()');

		//do_action( 'current_screen', $current_screen );
		// The first hook where current screen is available
		add_action( 'current_screen', array( $this, "save_versions_before_update" ) );
		add_action( 'delete_site_transient_update_plugins', array( $this, "remove_saved_versions" ) );

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

		// Dirty check for things that we can't catch using filters or actions
		add_action( 'admin_init', array( $this, "check_filterless_things" ) );

		// Detect files removed
		add_action( 'setted_transient', array( $this, 'on_setted_transient_for_remove_files' ), 10, 2 );

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
	 * Detect plugin being deleted
	 * When WP is done deleting a plugin it sets a transient called plugins_delete_result:
	 * set_transient('plugins_delete_result_' . $user_ID, $delete_result);
	 *
	 * We detect when that transient is set and then we have all info needed to log the plugin delete
	 *	 
	 */
	public function on_setted_transient_for_remove_files($transient, $value) {

		if ( ! $user_id = get_current_user_id() ) {
			return;
		}

		$transient_name = '_transient_plugins_delete_result_' . $user_id;
		if ( $transient_name !== $transient ) {
			return;
		}

		// We found the transient we were looking for
		if ( 
				isset( $_POST["action"] )
				&& "delete-selected" == $_POST["action"]
				&& isset( $_POST["checked"] )
				&& is_array( $_POST["checked"] )
				) {

			/*
		    [checked] => Array
		        (
		            [0] => the-events-calendar/the-events-calendar.php
		        )
		    */

			$plugins_deleted = $_POST["checked"];
			$plugins_before_update = json_decode( get_option( $this->slug . "_plugin_info_before_update", false ), true );

			foreach ($plugins_deleted as $plugin) {
				
				$context = array(
					"plugin" => $plugin
				);

				if ( is_array( $plugins_before_update ) && isset( $plugins_before_update[ $plugin ] ) ) {
					$context["plugin_name"] = $plugins_before_update[ $plugin ]["Name"];
					$context["plugin_title"] = $plugins_before_update[ $plugin ]["Title"];
					$context["plugin_description"] = $plugins_before_update[ $plugin ]["Description"];
					$context["plugin_author"] = $plugins_before_update[ $plugin ]["Author"];
					$context["plugin_version"] = $plugins_before_update[ $plugin ]["Version"];
					$context["plugin_url"] = $plugins_before_update[ $plugin ]["PluginURI"];
				}

				$this->infoMessage(
					"plugin_deleted",
					$context
				);

			}

		}
		
		$this->remove_saved_versions();

	}

	/**
	 * Save all plugin information before a plugin is updated or removed.
	 * This way we can know both the old (pre updated/removed) and the current version of the plugin
	 */
	public function save_versions_before_update() {
		
		$current_screen = get_current_screen();
		$request_uri = $_SERVER["SCRIPT_NAME"];

		// Only add option on pages where needed
		$do_store = false;

		if ( 
				( "/wp-admin/update.php" == $request_uri ) 
				&& isset( $current_screen->base ) 
				&& "update" == $current_screen->base 
			) {
			
			// Plugin update screen
			$do_store = true;

		} else if ( 
				( "/wp-admin/plugins.php" == $request_uri ) 
				&& isset( $current_screen->base ) 
				&& "plugins" == $current_screen->base
				&& ( isset( $_POST["action"] ) && "delete-selected" == $_POST["action"] )
			) {
			
			// Plugin delete screen, during delete
			$do_store = true;

		}

		if ( $do_store ) {
			update_option( $this->slug . "_plugin_info_before_update", SimpleHistory::json_encode( get_plugins() ) );
		}

	}

	/**
	  * when plugin updates are done wp_clean_plugins_cache() is called,
	  * which in it's turn run:
	  * delete_site_transient( 'update_plugins' );
	  * do_action( 'delete_site_transient_' . $transient, $transient );
	  * delete_site_transient_update_plugins
	  */
	public function remove_saved_versions() {
		
		delete_option( $this->slug . "_plugin_info_before_update" );

	}

	function check_filterless_things() {

		// Var is string with length 113: /wp-admin/plugin-editor.php?file=my-plugin%2Fviews%2Fplugin-file.php
		$referer = wp_get_referer();
		
		// contains key "path" with value like "/wp-admin/plugin-editor.php"
		$referer_info = parse_url($referer);

		if ( "/wp-admin/plugin-editor.php" === $referer_info["path"] ) {

			// We are in plugin editor
			// Check for plugin edit saved		
			if ( isset( $_POST["newcontent"] ) && isset( $_POST["action"] ) && "update" == $_POST["action"] && isset( $_POST["file"] ) && ! empty( $_POST["file"] ) ) {

				// A file was edited
				$file = $_POST["file"];

				// $plugins = get_plugins();
				// http://codex.wordpress.org/Function_Reference/wp_text_diff
				
				// Generate a diff of changes
				if ( ! class_exists( 'WP_Text_Diff_Renderer_Table' ) ) {
					require( ABSPATH . WPINC . '/wp-diff.php' );
				}

				$original_file_contents = file_get_contents( WP_PLUGIN_DIR . "/" . $file );
				$new_file_contents = wp_unslash( $_POST["newcontent"] );

				$left_lines  = explode("\n", $original_file_contents);
				$right_lines = explode("\n", $new_file_contents);
				$text_diff = new Text_Diff($left_lines, $right_lines);

				$num_added_lines = $text_diff->countAddedLines();
				$num_removed_lines = $text_diff->countDeletedLines();

				// Generate a diff in classic diff format
				$renderer  = new Text_Diff_Renderer();
				$diff = $renderer->render($text_diff);

				$this->infoMessage(
					'plugin_file_edited',
					array(
						"plugin_edited_file" => $file,
						"plugin_edit_diff" => $diff,
						"plugin_edit_num_added_lines" => $num_added_lines,
						"plugin_edit_num_removed_lines" => $num_removed_lines,
					)
				);

				$did_log = true;

			}

		}


	}

	/**
	 * Called when a single plugin is updated or installed
	 * (not bulk)
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
			if ( isset( $arr_data["action"] ) && "install" == $arr_data["action"] && ! $plugin_upgrader_instance->bulk ) {

				// Upgrader contains current info
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

			} // install single

			// Single plugin update
			if ( isset( $arr_data["action"] ) && "update" == $arr_data["action"] && ! $plugin_upgrader_instance->bulk ) {

				// No plugin info in instance, so get it ourself
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

				// To get old version we use our option
				$plugins_before_update = json_decode( get_option( $this->slug . "_plugin_info_before_update", false ), true );
				if ( is_array( $plugins_before_update ) && isset( $plugins_before_update[ $arr_data["plugin"] ] ) ) {

					$context["plugin_prev_version"] = $plugins_before_update[ $arr_data["plugin"] ]["Version"];

				}

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

					#echo "on_upgrader_process_complete";
					#sf_d( $plugin_upgrader_instance, '$plugin_upgrader_instance' );
					#sf_d( $arr_data, '$arr_data' );

					$did_log = true;

				}

			} // update single
		

			/**
			 * For bulk updates $arr_data looks like:
			 * Array
			 * (
			 *     [action] => update
			 *     [type] => plugin
			 *     [bulk] => 1
			 *     [plugins] => Array
			 *         (
			 *             [0] => plugin-folder-1/plugin-index.php
			 *             [1] => my-plugin-folder/my-plugin.php
			 *         )
			 * )
			 */
			if ( isset( $arr_data["bulk"] ) && $arr_data["bulk"] && isset( $arr_data["action"] ) && "update" == $arr_data["action"] ) {

				$plugins_updated = isset( $arr_data["plugins"] ) ? (array) $arr_data["plugins"] : array();

				foreach ($plugins_updated as $plugin_name) {

					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
			
					$context = array(
						"plugin_name" => $plugin_data["Name"],
						"plugin_title" => $plugin_data["Title"],
						"plugin_description" => $plugin_data["Description"],
						"plugin_author" => $plugin_data["Author"],
						"plugin_version" => $plugin_data["Version"],
						"plugin_url" => $plugin_data["PluginURI"],
					);

					// To get old version we use our option
					$plugins_before_update = json_decode( get_option( $this->slug . "_plugin_info_before_update", false ), true );
					if ( is_array( $plugins_before_update ) && isset( $plugins_before_update[ $plugin_name ] ) ) {

						$context["plugin_prev_version"] = $plugins_before_update[ $plugin_name ]["Version"];

					}

					$this->infoMessage(
						'plugin_bulk_updated',
						$context
					);

				}

			} // bulk update

		
		} // if plugin

		if ( ! $did_log ) {
			#echo "on_upgrader_process_complete";
			#sf_d( $plugin_upgrader_instance, '$plugin_upgrader_instance' );
			#sf_d( $arr_data, '$arr_data' );
			#exit;
		}

	}

	/*
	 * Called from filter 'upgrader_post_install'. 
	 *
	 * Used to log bulk plugin installs and updates
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
			#error_log("plugin install");
			

		} else if ( isset( $hook_extra["action"] ) && $hook_extra["action"] == "update" && isset( $hook_extra["type"] ) && $hook_extra["type"] == "plugin" ) {
			
			// It's a plugin upgrade
			#echo "plugin update!";
			error_log("plugin update");

		} else {

			error_log("other");

		}

		#sf_d($response, '$response');
		#sf_d($hook_extra, '$hook_extra');
		#sf_d($result, '$result');
		#exit;

		return $response;

	}

	/*

		 * Filter the list of action links available following bulk plugin updates.
		 *
		 * @since 3.0.0
		 *
		 * @param array $update_actions Array of plugin action links.
		 * @param array $plugin_info    Array of information for the last-updated plugin.

		$update_actions = apply_filters( 'update_bulk_plugins_complete_actions', $update_actions, $this->plugin_info );

	*/

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
