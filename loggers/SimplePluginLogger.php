<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs plugin installs, updates, and deletions
 */
class SimplePluginLogger extends SimpleLogger {

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
					'Updated plugin "{plugin_name}" to version {plugin_version} from {plugin_prev_version}',
					'Plugin was updated',
					'simple-history'
				),

				'plugin_update_failed' => _x(
					'Failed to update plugin "{plugin_name}"',
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
					'Updated plugin "{plugin_name}" to {plugin_version} from {plugin_prev_version}',
					'Plugin was updated in bulk',
					'simple-history'
				),

				// plugin disabled due to some error
				'plugin_disabled_because_error' => _x(
					'Deactivated a plugin because of an error: {error_message}',
					'Plugin was disabled because of an error',
					'simple-history'
				),

			), // messages
			"labels" => array(
				"search" => array(
					"label" => _x("Plugins", "Plugin logger: search", "simple-history"),
					"label_all" => _x("All plugin activity", "Plugin logger: search", "simple-history"),
					"options" => array(
						_x("Activated plugins", "Plugin logger: search", "simple-history") => array(
							'plugin_activated'
						),
						_x("Deactivated plugins", "Plugin logger: search", "simple-history") => array(
							'plugin_deactivated',
							'plugin_disabled_because_error'
						),
						_x("Installed plugins", "Plugin logger: search", "simple-history") => array(
							'plugin_installed'
						),
						_x("Failed plugin installs", "Plugin logger: search", "simple-history") => array(
							'plugin_installed_failed'
						),
						_x("Updated plugins", "Plugin logger: search", "simple-history") => array(
							'plugin_updated',
							'plugin_bulk_updated'
						),
						_x("Failed plugin updates", "Plugin logger: search", "simple-history") => array(
							'plugin_update_failed'
						),
						_x("Edited plugin files", "Plugin logger: search", "simple-history") => array(
							'plugin_file_edited'
						),
						_x("Deleted plugins", "Plugin logger: search", "simple-history") => array(
							'plugin_deleted'
						),
					)
				) // search array
			) // labels
		);

		return $arr_info;

	}

	public function loaded() {

		/**
		 * At least the plugin bulk upgrades fires this action before upgrade
		 * We use it to fetch the current version of all plugins, before they are upgraded
		 */
		add_filter( 'upgrader_pre_install', array( $this, "save_versions_before_update"), 10, 2);

		// Clear our transient after an update is done
		// Removed because something probably changed in core and this was fired earlier than it used to be
		// add_action( 'delete_site_transient_update_plugins', array( $this, "remove_saved_versions" ) );

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
		add_action( 'upgrader_process_complete', array( $this, "on_upgrader_process_complete" ), 10, 2 );

		// Dirty check for things that we can't catch using filters or actions
		add_action( 'admin_init', array( $this, "check_filterless_things" ) );

		// Detect files removed
		add_action( 'setted_transient', array( $this, 'on_setted_transient_for_remove_files' ), 10, 2 );

		add_action("admin_action_delete-selected", array($this, "on_action_delete_selected"), 10, 1);

		// Ajax function to get info from GitHub repo. Used by "View plugin info"-link for plugin installs
		add_action("wp_ajax_SimplePluginLogger_GetGitHubPluginInfo", array($this, "ajax_GetGitHubPluginInfo"));

		// If the Github Update plugin is not installed we need to get extra fields used by it.
		// So need to hook filter "extra_plugin_headers" ourself.
		add_filter( "extra_plugin_headers", function($arr_headers) {
			$arr_headers[] = "GitHub Plugin URI";
			return $arr_headers;
		} );

		// There is no way to ue a filter and detect a plugin that is disabled because it can't be found or similar error.
		// So we hook into gettext and look for the usage of the error that is returned when this happens.
		add_filter( 'gettext', array( $this, "on_gettext" ), 10, 3 );

	}

	/**
	 * There is no way to ue a filter and detect a plugin that is disabled because it can't be found or similar error.
	 * we hook into gettext and look for the usage of the error that is returned when this happens.
	 */
	function on_gettext( $translation, $text, $domain ) {

		// The errors we can get is:
		// return new WP_Error('plugin_invalid', __('Invalid plugin path.'));
		// return new WP_Error('plugin_not_found', __('Plugin file does not exist.'));
		// return new WP_Error('no_plugin_header', __('The plugin does not have a valid header.'));

		global $pagenow;

		// We only act on page plugins.php
		if ( ! isset( $pagenow ) || $pagenow !== "plugins.php" ) {
			return $translation;
		}

		// We only act if the untranslated text is among the following ones
		// (Literally these, no translation)
		$untranslated_texts = array(
			"Plugin file does not exist.",
			"Invalid plugin path.",
			"The plugin does not have a valid header."
		);

		if ( ! in_array( $text, $untranslated_texts )) {
			return $translation;
		}

		// We don't know what plugin that was that got this error and currently there does not seem to be a way to determine that
		// So that's why we use such generic log messages
		$this->warningMessage(
			"plugin_disabled_because_error",
			array(
				"_initiator" => SimpleLoggerLogInitiators::WORDPRESS,
				"error_message" => $text
			)
		);

		return $translation;

	} // on_gettext


	/**
	 * Show readme from github in a modal win
	 */
	function ajax_GetGitHubPluginInfo() {

		if ( ! current_user_can("install_plugins") ) {
			wp_die( __("You don't have access to this page.", "simple-history" ));
		}

		$repo = isset( $_GET["repo"] ) ? (string) $_GET["repo"] : "";

		if ( ! $repo ) {
			wp_die( __("Could not find GitHub repository.", "simple-history" ));
		}

		$repo_parts = explode("/", rtrim($repo, "/"));
		if ( count($repo_parts) !== 5 ) {
			wp_die( __("Could not find GitHub repository.", "simple-history" ));
		}

		$repo_username = $repo_parts[3];
		$repo_repo = $repo_parts[4];

		// https://developer.github.com/v3/repos/contents/
		// https://api.github.com/repos/<username>/<repo>/readme
		$api_url = sprintf('https://api.github.com/repos/%1$s/%2$s/readme', urlencode( $repo_username ), urlencode( $repo_repo ));

		// Get file. Use accept-header to get file as HTML instead of JSON
		$response = wp_remote_get( $api_url, array(
			"headers" => array(
				"accept" => "application/vnd.github.VERSION.html"
			)
		) );

		$response_body = wp_remote_retrieve_body( $response );

		$repo_info = sprintf(
						__('<p>Viewing <code>readme</code> from repository <code><a target="_blank" href="%1$s">%2$s</a></code>.</p>', "simple-history"),
						esc_url( $repo ),
						esc_html( $repo )
					);

		$github_markdown_css_path = SIMPLE_HISTORY_PATH . "/css/github-markdown.css";

		printf(
			'
				<!doctype html>
				<style>
					body {
						font-family: sans-serif;
						font-size: 16px;
					}
					.repo-info {
						padding: 1.25em 1em;
						background: #fafafa;
						line-height: 1;
					}
					.repo-info p {
						margin: 0;
					}
					    .markdown-body {
				        min-width: 200px;
				        max-width: 790px;
				        margin: 0 auto;
				        padding: 30px;
				    }

					@import url("%3$s");

				</style>

				<base href="%4$s/raw/master/">

				<header class="repo-info">
					%1$s
				</header>

				<div class="markdown-body readme-contents">
					%2$s
				</div>
			',
			$repo_info,
			$response_body,
			$github_markdown_css_path,
			esc_url( $repo ) // 4
		);

		#echo($response_body);

		exit;

	}

	/*
	 * When a plugin has been deleted there is no way for us to get
	 * the real name of the plugin, only the dir and main index file.
	 * So before a plugin is deleted we save all needed info in a transient
	 */
	function on_action_delete_selected() {

		// Same as in plugins.php
		if ( ! current_user_can('delete_plugins') ) {
			wp_die(__('You do not have sufficient permissions to delete plugins for this site.'));
		}

		// Verify delete must be set
		if ( ! isset( $_POST["verify-delete"] ) || ! $_POST["verify-delete"] ) {
			return;
		}

		// An arr of plugins must be set
		if ( ! isset( $_POST["checked"] ) || ! is_array( $_POST["checked"] ) ) {
			return;
		}

		// If we get this far it looks like a plugin is begin deleted
		// Get and save info about it

		$this->save_versions_before_update();


	}

	/**
	 * Saves info about all installed plugins to an option.
	 * When we are done logging then we remove the option.
	 */
    function save_versions_before_update($bool = null, $hook_extra = null) {

		$plugins = get_plugins();

		// does not work
		$option_name = $this->slug . "_plugin_info_before_update";

		$r = update_option( $option_name, SimpleHistory::json_encode( $plugins ) );

		return $bool;

	}

	/**
	 * Detect plugin being deleted
	 * When WP is done deleting a plugin it sets a transient called plugins_delete_result:
	 * set_transient('plugins_delete_result_' . $user_ID, $delete_result);
	 *
	 * We detect when that transient is set and then we have all info needed to log the plugin delete
	 *
	 */
	public function on_setted_transient_for_remove_files( $transient = "", $value = "" ) {

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

			foreach ( $plugins_deleted as $plugin ) {

				$context = array(
					"plugin" => $plugin // plugin-name-folder/plugin-main-file.php
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
	/*public function save_versions_before_update() {

		$current_screen = get_current_screen();
		$request_uri = $_SERVER["SCRIPT_NAME"];

		// Only add option on pages where needed
		$do_store = false;

		if (
				SimpleHistory::ends_with( $request_uri, "/wp-admin/update.php" )
				&& isset( $current_screen->base )
				&& "update" == $current_screen->base
			) {

			// Plugin update screen
			$do_store = true;

		} else if (
				SimpleHistory::ends_with( $request_uri, "/wp-admin/plugins.php" )
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
	*/

	/**
	  * when plugin updates are done wp_clean_plugins_cache() is called,
	  * which in its turn run:
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
					require_once( ABSPATH . WPINC . '/wp-diff.php' );
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
	 * Called when plugins is updated or installed
	 * Called from class-wp-upgrader.php
	 *
	 * @param Plugin_Upgrader $this Plugin_Upgrader instance. In other contexts, $this, might
	 *                              be a Theme_Upgrader or Core_Upgrade instance.
	 * @param array           $data {
	 *     Array of bulk item update data.
	 *
	 */
	function on_upgrader_process_complete( $plugin_upgrader_instance, $arr_data ) {

		// Can't use get_plugins() here to get version of plugins updated from
		// Tested that, and it will get the new version (and that's the correct answer I guess. but too bad for us..)

		/*
		If an update fails then $plugin_upgrader_instance->skin->result->errors contains something like:
		Array
		(
		    [remove_old_failed] => Array
		        (
		            [0] => Could not remove the old plugin.
		        )

		)
		*/

		/*

		# Contents of $arr_data in different scenarios

		## WordPress core update

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


		## Plugin update

		$arr_data:
		Array
		(
		    [type] => plugin
		    [action] => install
		)

		## Bulk actions

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

				$upgrader_skin_options = isset( $plugin_upgrader_instance->skin->options ) && is_array( $plugin_upgrader_instance->skin->options ) ? $plugin_upgrader_instance->skin->options : array();
				$upgrader_skin_result = isset( $plugin_upgrader_instance->skin->result ) && is_array( $plugin_upgrader_instance->skin->result ) ? $plugin_upgrader_instance->skin->result : array();
				$upgrader_skin_api = isset( $plugin_upgrader_instance->skin->api ) ? $plugin_upgrader_instance->skin->api : (object) array();

				$plugin_slug = isset( $upgrader_skin_result["destination_name"] ) ? $upgrader_skin_result["destination_name"] : "";

				// Upgrader contains current info
				$context = array(
					"plugin_slug" => $plugin_slug,
					"plugin_name" => isset( $upgrader_skin_api->name ) ? $upgrader_skin_api->name : "",
					"plugin_version" => isset( $upgrader_skin_api->version ) ? $upgrader_skin_api->version : "",
					"plugin_author" => isset( $upgrader_skin_api->author ) ? $upgrader_skin_api->author : "",
					"plugin_last_updated" => isset( $upgrader_skin_api->last_updated ) ? $upgrader_skin_api->last_updated : "",
					"plugin_requires" => isset( $upgrader_skin_api->requires ) ? $upgrader_skin_api->requires : "",
					"plugin_tested" => isset( $upgrader_skin_api->tested ) ? $upgrader_skin_api->tested : "",
					"plugin_rating" => isset( $upgrader_skin_api->rating ) ? $upgrader_skin_api->rating : "",
					"plugin_num_ratings" => isset( $upgrader_skin_api->num_ratings ) ? $upgrader_skin_api->num_ratings : "",
					"plugin_downloaded" => isset( $upgrader_skin_api->downloaded ) ? $upgrader_skin_api->downloaded : "",
					"plugin_added" => isset( $upgrader_skin_api->added ) ? $upgrader_skin_api->added : "",
					"plugin_source_files" => $this->simpleHistory->json_encode( $plugin_upgrader_instance->result["source_files"] ),

					// To debug comment out these:
					// "debug_skin_options" => $this->simpleHistory->json_encode( $upgrader_skin_options ),
					// "debug_skin_result" => $this->simpleHistory->json_encode( $upgrader_skin_result ),

				);

				/*
				Detect install plugin from wordpress.org
					- options[type] = "web"
					- options[api] contains all we need

				Detect install from upload ZIP
					- options[type] = "upload"

				Also: plugins hosted at GitHub have a de-facto standard field of "GitHub Plugin URI"
				*/
				$install_source = "unknown";
				if ( isset( $upgrader_skin_options["type"] ) ) {
					$install_source = (string) $upgrader_skin_options["type"];
				}

				$context["plugin_install_source"] = $install_source;

				// If uploaded plugin store name of ZIP
				if ( "upload" == $install_source ) {

					/*_debug_files
					{
					    "pluginzip": {
					        "name": "WPThumb-master.zip",
					        "type": "application\/zip",
					        "tmp_name": "\/Applications\/MAMP\/tmp\/php\/phpnThImc",
					        "error": 0,
					        "size": 2394625
					    }
					}
					*/

					if ( isset( $_FILES["pluginzip"]["name"] ) ) {
						$plugin_upload_name = $_FILES["pluginzip"]["name"];
						$context["plugin_upload_name"] = $plugin_upload_name;
					}

				}


				if ( is_a( $plugin_upgrader_instance->skin->result, "WP_Error" ) ) {

					// Add errors
					// Errors is in original wp admin language
					$context["error_messages"] = $this->simpleHistory->json_encode( $plugin_upgrader_instance->skin->result->errors );
					$context["error_data"] = $this->simpleHistory->json_encode( $plugin_upgrader_instance->skin->result->error_data );

					$this->infoMessage(
						'plugin_installed_failed',
						$context
					);

					$did_log = true;

				} else {

					// Plugin was successfully installed
					// Try to grab more info from the readme
					// Would be nice to grab a screenshot, but that is difficult since they often are stored remotely
					$plugin_destination = isset( $plugin_upgrader_instance->result["destination"] ) ? $plugin_upgrader_instance->result["destination"] : null;

					if ( $plugin_destination ) {

						$plugin_info = $plugin_upgrader_instance->plugin_info();

						$plugin_data = array();
						if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_info ) ) {
							$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_info, true, false );
						}

						$context["plugin_name"] = isset( $plugin_data["Name"] ) ? $plugin_data["Name"] : "";
						$context["plugin_description"] = isset( $plugin_data["Description"] ) ? $plugin_data["Description"] : "";
						$context["plugin_url"] = isset( $plugin_data["PluginURI"] ) ? $plugin_data["PluginURI"] : "";
						$context["plugin_version"] = isset( $plugin_data["Version"] ) ? $plugin_data["Version"] : "";
						$context["plugin_author"] = isset( $plugin_data["AuthorName"] ) ? $plugin_data["AuthorName"] : "";

						// Comment out these to debug plugin installs
						#$context["debug_plugin_data"] = $this->simpleHistory->json_encode( $plugin_data );
						#$context["debug_plugin_info"] = $this->simpleHistory->json_encode( $plugin_info );

						if ( ! empty( $plugin_data["GitHub Plugin URI"] ) ) {
							$context["plugin_github_url"] = $plugin_data["GitHub Plugin URI"];
						}

					}

					$this->infoMessage(
						'plugin_installed',
						$context
					);

					$did_log = true;

				} // if error or not

			} // install single

			// Single plugin update
			if ( isset( $arr_data["action"] ) && "update" == $arr_data["action"] && ! $plugin_upgrader_instance->bulk ) {

				// No plugin info in instance, so get it ourself
				$plugin_data = array();
				if ( file_exists( WP_PLUGIN_DIR . '/' . $arr_data["plugin"] ) ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $arr_data["plugin"], true, false );
				}

				// autoptimize/autoptimize.php
				$plugin_slug = dirname( $arr_data["plugin"] );

				$context = array(
					"plugin_slug" => $plugin_slug,
					"request" => $this->simpleHistory->json_encode( $_REQUEST ),
					"plugin_name" => $plugin_data["Name"],
					"plugin_title" => $plugin_data["Title"],
					"plugin_description" => $plugin_data["Description"],
					"plugin_author" => $plugin_data["Author"],
					"plugin_version" => $plugin_data["Version"],
					"plugin_url" => $plugin_data["PluginURI"],
					"plugin_source_files" => $this->simpleHistory->json_encode( $plugin_upgrader_instance->result["source_files"] )
				);

				// update status for plugins are in response
				// plugin folder + index file = key
				// use transient to get url and package
				$update_plugins = get_site_transient( 'update_plugins' );
				if ( $update_plugins && isset( $update_plugins->response[ $arr_data["plugin"] ] ) ) {

					/*
					$update_plugins[plugin_path/slug]:
					{
						"id": "8986",
						"slug": "autoptimize",
						"plugin": "autoptimize/autoptimize.php",
						"new_version": "1.9.1",
						"url": "https://wordpress.org/plugins/autoptimize/",
						"package": "https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip"
					}
					*/
					// for debug purposes the update_plugins key can be added
					// $context["update_plugins"] = $this->simpleHistory->json_encode( $update_plugins );

					$plugin_update_info = $update_plugins->response[ $arr_data["plugin"] ];

					// autoptimize/autoptimize.php
					if ( isset( $plugin_update_info->plugin ) ) {
						$context["plugin_update_info_plugin"] = $plugin_update_info->plugin;
					}

					// https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip
					if ( isset( $plugin_update_info->package ) ) {
						$context["plugin_update_info_package"] = $plugin_update_info->package;
					}

				}

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

					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name, true, false );

					$plugin_slug = dirname( $plugin_name );

					$context = array(
						"plugin_slug" => $plugin_slug,
						"plugin_name" => $plugin_data["Name"],
						"plugin_title" => $plugin_data["Title"],
						"plugin_description" => $plugin_data["Description"],
						"plugin_author" => $plugin_data["Author"],
						"plugin_version" => $plugin_data["Version"],
						"plugin_url" => $plugin_data["PluginURI"]
					);

					// get url and package
					$update_plugins = get_site_transient( 'update_plugins' );
					if ( $update_plugins && isset( $update_plugins->response[ $plugin_name ] ) ) {

						/*
						$update_plugins[plugin_path/slug]:
						{
							"id": "8986",
							"slug": "autoptimize",
							"plugin": "autoptimize/autoptimize.php",
							"new_version": "1.9.1",
							"url": "https://wordpress.org/plugins/autoptimize/",
							"package": "https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip"
						}
						*/

						$plugin_update_info = $update_plugins->response[ $plugin_name ];

						// autoptimize/autoptimize.php
						if ( isset( $plugin_update_info->plugin ) ) {
							$context["plugin_update_info_plugin"] = $plugin_update_info->plugin;
						}

						// https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip
						if ( isset( $plugin_update_info->package ) ) {
							$context["plugin_update_info_package"] = $plugin_update_info->package;
						}

					}

					// To get old version we use our option
					// @TODO: this does not always work, why?
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

		$this->remove_saved_versions();

	} // on upgrader_process_complete


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
		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name, true, false );

		$plugin_slug = dirname( $plugin_name );

		$context = array(
			"plugin_name" => $plugin_data["Name"],
			"plugin_slug" => $plugin_slug,
			"plugin_title" => $plugin_data["Title"],
			"plugin_description" => $plugin_data["Description"],
			"plugin_author" => $plugin_data["Author"],
			"plugin_version" => $plugin_data["Version"],
			"plugin_url" => $plugin_data["PluginURI"],
		);

		if ( ! empty( $plugin_data["GitHub Plugin URI"] ) ) {
			$context["plugin_github_url"] = $plugin_data["GitHub Plugin URI"];
		}

		$this->infoMessage( 'plugin_activated', $context );

	} // on_activated_plugin

	/**
	 * Plugin is deactivated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 */
	function on_deactivated_plugin($plugin_name) {

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name, true, false );
		$plugin_slug = dirname( $plugin_name );

		$context = array(
			"plugin_name" => $plugin_data["Name"],
			"plugin_slug" => $plugin_slug,
			"plugin_title" => $plugin_data["Title"],
			"plugin_description" => $plugin_data["Description"],
			"plugin_author" => $plugin_data["Author"],
			"plugin_version" => $plugin_data["Version"],
			"plugin_url" => $plugin_data["PluginURI"],
		);

		if ( ! empty( $plugin_data["GitHub Plugin URI"] ) ) {
			$context["plugin_github_url"] = $plugin_data["GitHub Plugin URI"];
		}

		$this->infoMessage( 'plugin_deactivated', $context );

	} // on_deactivated_plugin


	/**
	 * Get output for detailed log section
	 */
	function getLogRowDetailsOutput($row) {

		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";

		// When a plugin is installed we show a bit more information
		// We do it only on install because we don't want to clutter to log,
		// and when something is installed the description is most useul for other
		// admins on the site
		if ( "plugin_installed" === $message_key ) {

			if ( isset( $context["plugin_description"] ) ) {

				// Description includes a link to author, remove that, i.e. all text after and including <cite>
				$plugin_description = $context["plugin_description"];
				$cite_pos = strpos( $plugin_description, "<cite>" );
				if ($cite_pos) {
					$plugin_description = substr( $plugin_description, 0, $cite_pos );
				}

				// Keys to show
				$arr_plugin_keys = array(
					"plugin_description" => _x("Description", "plugin logger - detailed output", "simple-history"),
					"plugin_install_source" => _x("Source", "plugin logger - detailed output install source", "simple-history"),
					"plugin_install_source_file" => _x("Source file name", "plugin logger - detailed output install source", "simple-history"),
					"plugin_version" => _x("Version", "plugin logger - detailed output version", "simple-history"),
					"plugin_author" => _x("Author", "plugin logger - detailed output author", "simple-history"),
					"plugin_url" => _x("URL", "plugin logger - detailed output url", "simple-history"),
					#"plugin_downloaded" => _x("Downloads", "plugin logger - detailed output downloaded", "simple-history"),
					#"plugin_requires" => _x("Requires", "plugin logger - detailed output author", "simple-history"),
					#"plugin_tested" => _x("Compatible up to", "plugin logger - detailed output compatible", "simple-history"),
					// also available: plugin_rating, plugin_num_ratings
				);

				$arr_plugin_keys = apply_filters("simple_history/plugin_logger/row_details_plugin_info_keys", $arr_plugin_keys);

				// Start output of plugin meta data table
				$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

				foreach ( $arr_plugin_keys as $key => $desc ) {

					$desc_output = "";

					switch ( $key ) {

						case "plugin_downloaded":
							$desc_output = esc_html( number_format_i18n( (int) $context[ $key ] ) );
							break;

						// author is already formatted
						case "plugin_author":
							$desc_output = $context[ $key ];
							break;

						// URL needs a link
						case "plugin_url":
							$desc_output = sprintf('<a href="%1$s">%2$s</a>', esc_attr( $context["plugin_url"] ), esc_html( $context["plugin_url"] ));
							break;

						case "plugin_description":
							$desc_output = $plugin_description;
							break;

						case "plugin_install_source":

							if ( ! isset( $context[ $key ] ) ) {
								continue;
							}

							if ( "web" == $context[ $key ] ) {
								$desc_output = esc_html( __("WordPress Plugin Repository", "simple-history") );
							} else if ( "upload" == $context[ $key ] ) {
								#$plugin_upload_name = isset( $context["plugin_upload_name"] ) ? $context["plugin_upload_name"] : __("Unknown archive name", "simple-history");
								$desc_output = esc_html( __('Uploaded ZIP archive', "simple-history") );
								#$desc_output = esc_html( sprintf( __('Uploaded ZIP archive (%1$s)', "simple-history"), $plugin_upload_name ) );
								#$desc_output = esc_html( sprintf( __('%1$s (uploaded ZIP archive)', "simple-history"), $plugin_upload_name ) );
							} else {
								$desc_output = esc_html( $context[ $key ] );
							}

							break;

						case "plugin_install_source_file":

							if ( ! isset( $context["plugin_upload_name"] ) || ! isset( $context["plugin_install_source"] ) ) {
								continue;
							}

							if ( "upload" == $context["plugin_install_source"] ) {
								$plugin_upload_name = $context["plugin_upload_name"];
								$desc_output = esc_html( $plugin_upload_name );
							}

							break;

						default;
							$desc_output = esc_html( $context[ $key ] );
							break;
					}

					if ( ! trim( $desc_output ) ) {
						continue;
					}

					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%2$s</td>
						</tr>
						',
						esc_html($desc),
						$desc_output
					);

				}

				// Add link with more info about the plugin
				// If plugin_install_source	= web then it should be a wordpress.org-plugin
				// If plugin_github_url is set then it's a zip from a github thingie
				// so use link to that.

				$plugin_slug = ! empty( $context["plugin_slug"] ) ? $context["plugin_slug"] : "";

				// Slug + web as install source = show link to wordpress.org
				if ( $plugin_slug && isset( $context["plugin_install_source"] ) && $context["plugin_install_source"] == "web" ) {

					$output .= sprintf(
						'
						<tr>
							<td></td>
							<td><a title="%2$s" class="thickbox" href="%1$s">%2$s</a></td>
						</tr>
						',
						admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=&amp;TB_iframe=true&amp;width=640&amp;height=550" ),
						esc_html_x("View plugin info", "plugin logger: plugin info thickbox title view all info", "simple-history")
					);

				}
				// GitHub plugin url set = show link to github repo
				else if ( isset( $context["plugin_install_source"] ) && $context["plugin_install_source"] == "upload" && ! empty( $context["plugin_github_url"] ) ) {

					// Can't embed iframe
					// Must use API instead
					// https://api.github.com/repos/<username>/<repo>/readme?callback=<callbackname>

					$output .= sprintf(
						'
						<tr>
							<td></td>
							<td><a title="%2$s" class="thickbox" href="%1$s">%2$s</a></td>
						</tr>
						',
						admin_url(sprintf('admin-ajax.php?action=SimplePluginLogger_GetGitHubPluginInfo&getrepo&amp;repo=%1$s&amp;TB_iframe=true&amp;width=640&amp;height=550', esc_url_raw( $context["plugin_github_url"] ) ) ),
						esc_html_x("View plugin info", "plugin logger: plugin info thickbox title view all info", "simple-history")
					);

				}

				$output .= "</table>";

			}

		} elseif ( "plugin_bulk_updated" === $message_key || "plugin_updated" === $message_key || "plugin_activated" === $message_key || "plugin_deactivated" === $message_key ) {

			$plugin_slug = ! empty( $context["plugin_slug"] ) ? $context["plugin_slug"] : "";

			if ( $plugin_slug && empty( $context["plugin_github_url"] ) ) {

				$link_title = esc_html_x("View plugin info", "plugin logger: plugin info thickbox title", "simple-history");
				$url = admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=&amp;TB_iframe=true&amp;width=640&amp;height=550" );

				if ( "plugin_updated" == $message_key || "plugin_bulk_updated" == $message_key ) {

					$link_title = esc_html_x("View changelog", "plugin logger: plugin info thickbox title", "simple-history");
					
					if ( is_multisite() ) {
						$url = network_admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=changelog&amp;TB_iframe=true&amp;width=772&amp;height=550" );
					} else {
						$url = admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=changelog&amp;TB_iframe=true&amp;width=772&amp;height=550" );
					}

				}

				$output .= sprintf(
					'<p><a title="%2$s" class="thickbox" href="%1$s">%2$s</a></p>',
					$url,
					$link_title
				);

			} else if ( ! empty( $context["plugin_github_url"] ) ) {

				$output .= sprintf(
					'
					<tr>
						<td></td>
						<td><a title="%2$s" class="thickbox" href="%1$s">%2$s</a></td>
					</tr>
					',
					admin_url(sprintf('admin-ajax.php?action=SimplePluginLogger_GetGitHubPluginInfo&getrepo&amp;repo=%1$s&amp;TB_iframe=true&amp;width=640&amp;height=550', esc_url_raw( $context["plugin_github_url"] ) ) ),
					esc_html_x("View plugin info", "plugin logger: plugin info thickbox title view all info", "simple-history")
				);

			}


		} // if plugin_updated

		return $output;

	} // getLogRowDetailsOutput

} // class SimplePluginLogger
