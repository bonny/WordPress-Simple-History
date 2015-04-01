<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs plugin installs, updates, and deletions
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
					'Updated plugin "{plugin_name}" to version {plugin_version} from {plugin_prev_version}', 
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
					'Updated plugin "{plugin_name}" to {plugin_version} from {plugin_prev_version}', 
					'Plugin was updated in bulk',
					'simple-history'
				),
			), // messages
			"labels" => array(
				"search" => array(
					"label" => _x("Plugins", "Plugin logger: search", "simple-history"),
					"options" => array(
						_x("Activated plugins", "Plugin logger: search", "simple-history") => array(
							'plugin_activated'
						),
						_x("Deactivated plugins", "Plugin logger: search", "simple-history") => array(
							'plugin_deactivated'
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

		#sf_d(get_plugins(), 'get_plugins()');

		//do_action( 'current_screen', $current_screen );
		// The first hook where current screen is available
		//add_action( 'current_screen', array( $this, "save_versions_before_update" ) );

		/**
		 * At least the plugin bulk upgrades fires this action before upgrade
		 * We use it to fetch the current version of all plugins, before they are upgraded
		 */
		add_filter( 'upgrader_pre_install', array( $this, "save_versions_before_update"), 10, 2);

		// Clear our transient after an update is done
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
		add_action( 'upgrader_process_complete', array( $this, "on_upgrader_process_complete" ), 10, 2 );

		// Dirty check for things that we can't catch using filters or actions
		add_action( 'admin_init', array( $this, "check_filterless_things" ) );

		// Detect files removed
		add_action( 'setted_transient', array( $this, 'on_setted_transient_for_remove_files' ), 10, 2 );

		add_action("admin_action_delete-selected", array($this, "on_action_delete_selected"), 10, 1);

		// Ajax function to get info from GitHub repo. Used by "View plugin info"-link for plugin installs
		add_action("wp_ajax_SimplePluginLogger_GetGitHubPluginInfo", array($this, "ajax_GetGitHubPluginInfo"));

		// If the Github Update plugin is not installed we will not get extra fields used by it.
		// So need to hook filter "extra_plugin_headers" ourself.
		add_filter( "extra_plugin_headers", function($arr_headers) {
			$arr_headers[] = "GitHub Plugin URI";
			return $arr_headers;
		} );

	}
	
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

		$repo_parts = explode("/", $repo);
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
		
		ob_start();
		?>
			@font-face{font-family:octicons-anchor;src:url(data:font/woff;charset=utf-8;base64,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) format('woff')}.markdown-body{-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%;color:#333;overflow:hidden;font-family:"Helvetica Neue",Helvetica,"Segoe UI",Arial,freesans,sans-serif;font-size:16px;line-height:1.6;word-wrap:break-word}.markdown-body a{background:0 0}.markdown-body a:active,.markdown-body a:hover{outline:0}.markdown-body strong{font-weight:700}.markdown-body h1{margin:.67em 0}.markdown-body img{border:0}.markdown-body hr{box-sizing:content-box}.markdown-body input{color:inherit;margin:0}.markdown-body html input[disabled]{cursor:default}.markdown-body input{line-height:normal}.markdown-body input[type=checkbox]{box-sizing:border-box;padding:0}.markdown-body table{border-collapse:collapse;border-spacing:0}.markdown-body td,.markdown-body th{padding:0}.markdown-body *{box-sizing:border-box}.markdown-body input{font:13px/1.4 Helvetica,arial,nimbussansl,liberationsans,freesans,clean,sans-serif,"Segoe UI Emoji","Segoe UI Symbol"}.markdown-body a{color:#4183c4;text-decoration:none}.markdown-body a:active,.markdown-body a:hover{text-decoration:underline}.markdown-body hr{overflow:hidden;background:0 0}.markdown-body hr:before{display:table;content:""}.markdown-body hr:after{display:table;clear:both;content:""}.markdown-body blockquote{margin:0}.markdown-body ol,.markdown-body ul{padding:0}.markdown-body ol ol,.markdown-body ul ol{list-style-type:lower-roman}.markdown-body ol ol ol,.markdown-body ol ul ol,.markdown-body ul ol ol,.markdown-body ul ul ol{list-style-type:lower-alpha}.markdown-body dd{margin-left:0}.markdown-body code{font-family:Consolas,"Liberation Mono",Menlo,Courier,monospace}.markdown-body pre{font:12px Consolas,"Liberation Mono",Menlo,Courier,monospace}.markdown-body .octicon{font:normal normal normal 16px/1 octicons-anchor;display:inline-block;text-decoration:none;text-rendering:auto;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}.markdown-body .octicon-link:before{content:'\f05c'}.markdown-body>:first-child{margin-top:0!important}.markdown-body>:last-child{margin-bottom:0!important}.markdown-body a:not(:link):not(:visited){color:inherit;text-decoration:none}.markdown-body .anchor{position:absolute;top:0;left:0;display:block;padding-right:6px;padding-left:30px;margin-left:-30px}.markdown-body .anchor:focus{outline:0}.markdown-body h1,.markdown-body h2,.markdown-body h3,.markdown-body h4,.markdown-body h5,.markdown-body h6{position:relative;margin-top:1em;margin-bottom:16px;font-weight:700;line-height:1.4}.markdown-body h1 .octicon-link,.markdown-body h2 .octicon-link,.markdown-body h3 .octicon-link,.markdown-body h4 .octicon-link,.markdown-body h5 .octicon-link,.markdown-body h6 .octicon-link{display:none;color:#000;vertical-align:middle}.markdown-body h1:hover .anchor,.markdown-body h2:hover .anchor,.markdown-body h3:hover .anchor,.markdown-body h4:hover .anchor,.markdown-body h5:hover .anchor,.markdown-body h6:hover .anchor{padding-left:8px;margin-left:-30px;text-decoration:none}.markdown-body h1:hover .anchor .octicon-link,.markdown-body h2:hover .anchor .octicon-link,.markdown-body h3:hover .anchor .octicon-link,.markdown-body h4:hover .anchor .octicon-link,.markdown-body h5:hover .anchor .octicon-link,.markdown-body h6:hover .anchor .octicon-link{display:inline-block}.markdown-body h1{padding-bottom:.3em;font-size:2.25em;line-height:1.2;border-bottom:1px solid #eee}.markdown-body h1 .anchor{line-height:1}.markdown-body h2{padding-bottom:.3em;font-size:1.75em;line-height:1.225;border-bottom:1px solid #eee}.markdown-body h2 .anchor{line-height:1}.markdown-body h3{font-size:1.5em;line-height:1.43}.markdown-body h3 .anchor{line-height:1.2}.markdown-body h4{font-size:1.25em}.markdown-body h4 .anchor{line-height:1.2}.markdown-body h5{font-size:1em}.markdown-body h5 .anchor{line-height:1.1}.markdown-body h6{font-size:1em;color:#777}.markdown-body h6 .anchor{line-height:1.1}.markdown-body blockquote,.markdown-body dl,.markdown-body ol,.markdown-body p,.markdown-body pre,.markdown-body table,.markdown-body ul{margin-top:0;margin-bottom:16px}.markdown-body hr{height:4px;padding:0;margin:16px 0;background-color:#e7e7e7;border:0}.markdown-body ol,.markdown-body ul{padding-left:2em}.markdown-body ol ol,.markdown-body ol ul,.markdown-body ul ol,.markdown-body ul ul{margin-top:0;margin-bottom:0}.markdown-body li>p{margin-top:16px}.markdown-body dl{padding:0}.markdown-body dl dt{padding:0;margin-top:16px;font-size:1em;font-style:italic;font-weight:700}.markdown-body dl dd{padding:0 16px;margin-bottom:16px}.markdown-body blockquote{padding:0 15px;color:#777;border-left:4px solid #ddd}.markdown-body blockquote>:first-child{margin-top:0}.markdown-body blockquote>:last-child{margin-bottom:0}.markdown-body table{display:block;width:100%;overflow:auto;word-break:normal;word-break:keep-all}.markdown-body table th{font-weight:700}.markdown-body table td,.markdown-body table th{padding:6px 13px;border:1px solid #ddd}.markdown-body table tr{background-color:#fff;border-top:1px solid #ccc}.markdown-body table tr:nth-child(2n){background-color:#f8f8f8}.markdown-body img{max-width:100%;box-sizing:border-box}.markdown-body code{padding:.2em 0;margin:0;font-size:85%;background-color:rgba(0,0,0,.04);border-radius:3px}.markdown-body code:after,.markdown-body code:before{letter-spacing:-.2em;content:"\00a0"}.markdown-body pre>code{padding:0;margin:0;font-size:100%;word-break:normal;white-space:pre;background:0 0;border:0}.markdown-body .highlight{margin-bottom:16px}.markdown-body .highlight pre,.markdown-body pre{padding:16px;overflow:auto;font-size:85%;line-height:1.45;background-color:#f7f7f7;border-radius:3px}.markdown-body .highlight pre{margin-bottom:0;word-break:normal}.markdown-body pre{word-wrap:normal}.markdown-body pre code{display:inline;max-width:initial;padding:0;margin:0;overflow:initial;line-height:inherit;word-wrap:normal;background-color:transparent;border:0}.markdown-body pre code:after,.markdown-body pre code:before{content:normal}.markdown-body .pl-c{color:#969896}.markdown-body .pl-c1,.markdown-body .pl-mdh,.markdown-body .pl-mm,.markdown-body .pl-mp,.markdown-body .pl-mr,.markdown-body .pl-s1 .pl-v,.markdown-body .pl-s3,.markdown-body .pl-sc,.markdown-body .pl-sv{color:#0086b3}.markdown-body .pl-e,.markdown-body .pl-en{color:#795da3}.markdown-body .pl-s1 .pl-s2,.markdown-body .pl-smi,.markdown-body .pl-smp,.markdown-body .pl-stj,.markdown-body .pl-vo,.markdown-body .pl-vpf{color:#333}.markdown-body .pl-ent{color:#63a35c}.markdown-body .pl-k,.markdown-body .pl-s,.markdown-body .pl-st{color:#a71d5d}.markdown-body .pl-pds,.markdown-body .pl-s1,.markdown-body .pl-s1 .pl-pse .pl-s2,.markdown-body .pl-sr,.markdown-body .pl-sr .pl-cce,.markdown-body .pl-sr .pl-sra,.markdown-body .pl-sr .pl-sre,.markdown-body .pl-src{color:#183691}.markdown-body .pl-v{color:#ed6a43}.markdown-body .pl-id{color:#b52a1d}.markdown-body .pl-ii{background-color:#b52a1d;color:#f8f8f8}.markdown-body .pl-sr .pl-cce{color:#63a35c;font-weight:700}.markdown-body .pl-ml{color:#693a17}.markdown-body .pl-mh,.markdown-body .pl-mh .pl-en,.markdown-body .pl-ms{color:#1d3e81;font-weight:700}.markdown-body .pl-mq{color:teal}.markdown-body .pl-mi{color:#333;font-style:italic}.markdown-body .pl-mb{color:#333;font-weight:700}.markdown-body .pl-md,.markdown-body .pl-mdhf{background-color:#ffecec;color:#bd2c00}.markdown-body .pl-mdht,.markdown-body .pl-mi1{background-color:#eaffea;color:#55a532}.markdown-body .pl-mdr{color:#795da3;font-weight:700}.markdown-body .pl-mo{color:#1d3e81}.markdown-body kbd{display:inline-block;padding:3px 5px;font:11px Consolas,"Liberation Mono",Menlo,Courier,monospace;line-height:10px;color:#555;vertical-align:middle;background-color:#fcfcfc;border:1px solid #ccc;border-bottom-color:#bbb;border-radius:3px;box-shadow:inset 0 -1px 0 #bbb}.markdown-body .task-list-item{list-style-type:none}.markdown-body .task-list-item+.task-list-item{margin-top:3px}.markdown-body .task-list-item input{margin:0 .35em .25em -1.6em;vertical-align:middle}.markdown-body :checked+.radio-label{z-index:1;position:relative;border-color:#4183c4}
		<?php
		$github_markdown_css = ob_get_clean();
		
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
					
					/*
					github-markdown-css from https://github.com/sindresorhus/github-markdown-css
					License: MIT © Sindre Sorhus
					Compressed using http://cssminifier.com/
					*/
					%3$s
					
				</style>
				<!-- <base href="%4$s/blob/master/"> -->
				
				<header class="repo-info">
					%1$s
				</header>
				
				<div class="markdown-body readme-contents">
					%2$s
				</div>
			',
			$repo_info,
			$response_body,
			$github_markdown_css,
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

		// Same as in plugins.php
		check_admin_referer('bulk-plugins');

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

		update_option( $this->slug . "_plugin_info_before_update", SimpleHistory::json_encode( $plugins ) );

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
	 * Called when plugins is updated or installed
	 */
	function on_upgrader_process_complete( $plugin_upgrader_instance, $arr_data ) {

		// Can't use get_plugins() here to get version of plugins updated from
		// Tested that, and it will get the new version (and that's the correct answer I guess. but too bad for us..)
		// $plugs = get_plugins();
		// $context["_debug_get_plugins"] = SimpleHistory::json_encode( $plugs );
		/*

		Try with these instead:
		$current = get_site_transient( 'update_plugins' );
		add_filter('upgrader_clear_destination', array($this, 'delete_old_plugin'), 10, 4);

		*/

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
							$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_info );
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
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $arr_data["plugin"] );
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

					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );

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
			//error_log("plugin update");

		} else {

			//error_log("other");

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
		
	}

	/**
	 * Plugin is deactivated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 */
	function on_deactivated_plugin($plugin_name) {

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
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

	}


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
	
			if ( isset($context["plugin_description"]) ) {

				// Description includes a link to author, remove that, i.e. all text after and including <cite>
				$plugin_description = $context["plugin_description"];
				$cite_pos = mb_strpos($plugin_description, "<cite>");
				if ($cite_pos) {
					$plugin_description = mb_strcut( $plugin_description, 0, $cite_pos );
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
					$url = admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=changelog&amp;TB_iframe=true&amp;width=772&amp;height=550" );
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

	}


}
