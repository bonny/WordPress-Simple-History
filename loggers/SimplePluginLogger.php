<?php

defined( 'ABSPATH' ) || die();

/**
 * Logs plugin related things, for example installs, updates, and deletions.
 */
class SimplePluginLogger extends SimpleLogger {

	/**
	 * The logger slug.
	 *
	 * @var string $slug
	 */
	public $slug = __CLASS__;

	/**
	 * This variable is set if a plugins has been disabled due to an error,
	 * like when the plugin file does not exist. We need to store this in this
	 * weird way because there is no other way for us to get the reason.
	 *
	 * @var array $latest_plugin_deactivation_because_of_error_reason
	 */
	public $latest_plugin_deactivation_because_of_error_reason = array();

	/**
	 * Used to collect information about a plugin (using get_plugin_data()) before it is deleted.
	 * Plugin info is stored with plugin file as the key.
	 *
	 * @var array
	 */
	protected $plugins_data = array();

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function getInfo() {
		$arr_info = array(
			'name'        => __( 'Plugin Logger', 'simple-history' ),
			'description' => __( 'Logs plugin installs, uninstalls and updates', 'simple-history' ),
			'capability'  => 'activate_plugins',
			'messages'    => array(

				'plugin_activated'              => _x(
					'Activated plugin "{plugin_name}"',
					'Plugin was non-silently activated by a user',
					'simple-history'
				),

				'plugin_deactivated'            => _x(
					'Deactivated plugin "{plugin_name}"',
					'Plugin was non-silently deactivated by a user',
					'simple-history'
				),

				'plugin_installed'              => _x(
					'Installed plugin "{plugin_name}"',
					'Plugin was installed',
					'simple-history'
				),

				'plugin_installed_failed'       => _x(
					'Failed to install plugin "{plugin_name}"',
					'Plugin failed to install',
					'simple-history'
				),

				'plugin_updated'                => _x(
					'Updated plugin "{plugin_name}" to version {plugin_version} from {plugin_prev_version}',
					'Plugin was updated',
					'simple-history'
				),

				'plugin_update_failed'          => _x(
					'Failed to update plugin "{plugin_name}"',
					'Plugin update failed',
					'simple-history'
				),

				'plugin_deleted'                => _x(
					'Deleted plugin "{plugin_name}"',
					'Plugin files was deleted',
					'simple-history'
				),

				// Bulk versions.
				'plugin_bulk_updated'           => _x(
					'Updated plugin "{plugin_name}" to {plugin_version} from {plugin_prev_version}',
					'Plugin was updated in bulk',
					'simple-history'
				),

				// Plugin disabled due to some error.
				'plugin_disabled_because_error' => _x(
					'Deactivated plugin "{plugin_slug}" because of an error ("{deactivation_reason}").',
					'Plugin was disabled because of an error',
					'simple-history'
				),

				'plugin_auto_updates_enabled' => _x(
					'Enabled auto-updates for plugin "{plugin_name}"',
					'Plugin was enabled for auto-updates',
					'simple-history'
				),
				'plugin_auto_updates_disabled' => _x(
					'Disabled auto-updates for plugin "{plugin_name}"',
					'Plugin was enabled for auto-updates',
					'simple-history'
				),

			), // Messages.
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Plugins', 'Plugin logger: search', 'simple-history' ),
					'label_all' => _x( 'All plugin activity', 'Plugin logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Activated plugins', 'Plugin logger: search', 'simple-history' ) => array(
							'plugin_activated',
						),
						_x( 'Deactivated plugins', 'Plugin logger: search', 'simple-history' ) => array(
							'plugin_deactivated',
							'plugin_disabled_because_error',
						),
						_x( 'Installed plugins', 'Plugin logger: search', 'simple-history' ) => array(
							'plugin_installed',
						),
						_x( 'Failed plugin installs', 'Plugin logger: search', 'simple-history' ) => array(
							'plugin_installed_failed',
						),
						_x( 'Updated plugins', 'Plugin logger: search', 'simple-history' ) => array(
							'plugin_updated',
							'plugin_bulk_updated',
						),
						_x( 'Failed plugin updates', 'Plugin logger: search', 'simple-history' ) => array(
							'plugin_update_failed',
						),
						_x( 'Deleted plugins', 'Plugin logger: search', 'simple-history' ) => array(
							'plugin_deleted',
						),
					),
				), // search array.
			), // labels.
		);

		return $arr_info;
	}

	/**
	 * Plugin loaded.
	 */
	public function loaded() {
		/**
		 * At least the plugin bulk upgrades fires this action before upgrade
		 * We use it to fetch the current version of all plugins, before they are upgraded
		 */
		add_filter( 'upgrader_pre_install', array( $this, 'save_versions_before_update' ), 10, 2 );

		// Clear our transient after an update is done
		// Removed because something probably changed in core and this was fired earlier than it used to be
		// add_action( 'delete_site_transient_update_plugins', array( $this, "remove_saved_versions" ) );
		// Fires after a plugin has been activated.
		// If a plugin is silently activated (such as during an update),
		// this hook does not fire.
		add_action( 'activated_plugin', array( $this, 'on_activated_plugin' ), 10, 2 );

		// Fires after a plugin is deactivated.
		// If a plugin is silently deactivated (such as during an update),
		// this hook does not fire.
		add_action( 'deactivated_plugin', array( $this, 'on_deactivated_plugin' ), 10, 2 );

		// Fires after the upgrades has done it's thing.
		// Check hook extra for upgrader initiator.
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete' ), 10, 2 );

		// Ajax function to get info from GitHub repo. Used by "View plugin info"-link for plugin installs.
		add_action( 'wp_ajax_SimplePluginLogger_GetGitHubPluginInfo', array( $this, 'ajax_GetGitHubPluginInfo' ) );

		// If the Github Update plugin is not installed we need to get extra fields used by it.
		// So need to hook filter "extra_plugin_headers" ourself.
		add_filter(
			'extra_plugin_headers',
			function ( $arr_headers ) {
				$arr_headers[] = 'GitHub Plugin URI';
				return $arr_headers;
			}
		);

		// There is no way to use a filter and detect a plugin that is disabled because it can't be found or similar error.
		// So we hook into gettext and look for the usage of the error that is returned when this happens.
		add_filter( 'gettext', array( $this, 'on_gettext_detect_plugin_error_deactivation_reason' ), 10, 3 );
		add_filter( 'gettext', array( $this, 'on_gettext' ), 10, 3 );

		// Detect plugin auto update change.
		add_action( 'load-plugins.php', array( $this, 'handle_auto_update_change' ) );
		add_action( 'wp_ajax_toggle-auto-updates', array( $this, 'handle_auto_update_change' ), 1, 1 );

		// Log plugin deletions, i.e. when a user click "Delete" in the plugins listing
		// or choose plugin(s) and select Bulk actions -> Delete.
		// Since WordPress 4.4 filters exists that are fired before and after plugin deletion.
		add_action( 'delete_plugin', array( $this, 'on_action_delete_plugin' ), 10, 1 );
		add_action( 'deleted_plugin', array( $this, 'on_action_deleted_plugin' ), 10, 2 );
	}

	/**
	 * Store information about a plugin before it gets deleted.
	 * Called from action `deleted_plugin` that is fired just before the plugin will be deleted.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return void
	 */
	public function on_action_delete_plugin( $plugin_file ) {
		$this->plugins_data[ $plugin_file ] = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, true, false );
	}

	/**
	 * Log plugin deletion.
	 * Called from action `deleted_plugin` that is fired just after a plugin has been deleted.
	 *
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param bool   $deleted     Whether the plugin deletion was successful.
	 * @return void
	 */
	public function on_action_deleted_plugin( $plugin_file, $deleted ) {
		if ( ! $deleted ) {
			return;
		}

		if ( empty( $this->plugins_data[ $plugin_file ] ) ) {
			return;
		}

		$plugin_data = $this->plugins_data[ $plugin_file ];

		$context = array(
			'plugin' => $plugin_file,
			'plugin_name' => $plugin_data['Name'],
			'plugin_title' => $plugin_data['Title'],
			'plugin_description' => $plugin_data['Description'],
			'plugin_author' => $plugin_data['Author'],
			'plugin_version' => $plugin_data['Version'],
			'plugin_url' => $plugin_data['PluginURI'],
		);

		$this->infoMessage(
			'plugin_deleted',
			$context
		);
	}

	/**
	 * Detect when a plugin is enable or disabled to be auto updated.
	 * This can be changed from the plugins-page, either from a GET request to
	 * the plugins-page or via an AJAX call.
	 *
	 * The result of the action is stored in
	 * site_option 'auto_update_plugins'.
	 * Check the value of that option after the option is updated.
	 */
	public function handle_auto_update_change() {
		$option = 'auto_update_plugins';

		add_filter(
			"update_option_{$option}",
			function ( $old_value, $value, $option ) {
				/**
				 * Option contains array with plugin that are set to be auto updated.
				 * Example:
				 * Array
				 *   (
				 *       [1] => query-monitor/query-monitor.php
				 *       [2] => akismet/akismet.php
				 *       [3] => wp-crontrol/wp-crontrol.php
				 *       [4] => redirection/redirection.php
				 *   )
				 *
				 * $_GET when opening single item enable/disable auto update link in plugin list in new window
				 *   Array
				 *   (
				 *       [action] => disable-auto-update | enable-auto-update
				 *       [plugin] => akismet/akismet.php
				 *   )
				 *
				 *
				 * $_POST from ajax call when clicking single item enable/disable link in plugin list
				 *    [action] => toggle-auto-updates
				 *    [state] => disable | enable
				 *    [type] => plugin
				 *    [asset] => redirection/redirection.php
				 *
				 *
				 * $_POST when selecting multiple plugins and choosing Enable auto updates or Disable auto updates
				 *     [action] => enable-auto-update-selected | disable-auto-update-selected
				 *     [checked] => Array
				 *         (
				 *             [0] => query-monitor/query-monitor.php
				 *             [1] => redirection/redirection.php
				 *         )
				 */

				$action = isset( $_GET['action'] ) ? $_GET['action'] : null;
				if ( ! $action ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$action = isset( $_POST['action'] ) ? $_POST['action'] : null;
				}

				// Bail if doing ajax and
				// - action is not toggle-auto-updates
				// - type is not plugin
				if ( wp_doing_ajax() ) {
					if ( $action !== 'toggle-auto-updates' ) {
						return;
					}

					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$type = isset( $_POST['type'] ) ? $_POST['type'] : null;
					if ( $type !== 'plugin' ) {
						return;
					}
				}

				// Bail if screen and not plugin screen
				$current_screen = get_current_screen();
				if ( is_a( $current_screen, 'WP_Screen' ) && ( $current_screen->base !== 'plugins' ) ) {
					return;
				}

				// Enable or disable, string "enable" or "disable".
				$enableOrDisable = null;

				// Plugin slugs that actions are performed against.
				$plugins = array();

				if ( in_array( $action, array( 'enable-auto-update', 'disable-auto-update' ) ) ) {
					// Opening single item enable/disable auto update link in plugin list in new window.
					$plugin = isset( $_GET['plugin'] ) ? $_GET['plugin'] : null;

					if ( $plugin ) {
						$plugins[] = sanitize_text_field( urldecode( $plugin ) );
					}

					if ( $action === 'enable-auto-update' ) {
						$enableOrDisable = 'enable';
					} elseif ( $action === 'disable-auto-update' ) {
						$enableOrDisable = 'disable';
					}
				} elseif ( $action === 'toggle-auto-updates' ) {
					// Ajax post call when clicking single item enable/disable link in plugin list.
					// *    [action] => toggle-auto-updates
					// *    [state] => disable | enable
					// *    [type] => plugin
					// *    [asset] => redirection/redirection.php
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$state = isset( $_POST['state'] ) ? $_POST['state'] : null;
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$asset = isset( $_POST['asset'] ) ? $_POST['asset'] : null;

					if ( $state === 'enable' ) {
						$enableOrDisable = 'enable';
					} elseif ( $state === 'disable' ) {
						$enableOrDisable = 'disable';
					}

					if ( $asset ) {
						$plugins[] = sanitize_text_field( urldecode( $asset ) );
					}
				} elseif ( in_array( $action, array( 'enable-auto-update-selected', 'disable-auto-update-selected' ) ) ) {
					// $_POST when checking multiple plugins and choosing Enable auto updates or Disable auto updates.
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$checked = isset( $_POST['checked'] ) ? $_POST['checked'] : null;
					if ( $checked ) {
						$plugins = (array) $checked;
					}

					if ( $action === 'enable-auto-update-selected' ) {
						$enableOrDisable = 'enable';
					} elseif ( $action === 'disable-auto-update-selected' ) {
						$enableOrDisable = 'disable';
					}
				}

				// Now we have:
				// - an array of plugin slugs in $plugins
				// - if plugin auto updates is to be enabled or disabled din $enableOrDisable

				// Bail if required values not set.
				if ( ! $plugins || ! $enableOrDisable ) {
					return;
				}

				// Finally log each plugin.
				foreach ( $plugins as $onePluginSlug ) {
					$this->logPluginAutoUpdateEnableOrDisable( $onePluginSlug, $enableOrDisable );
				}
			},
			10,
			3
		);
	}

	/**
	 * Log plugin that is enable or disabled for auto updates.
	 *
	 * @param string $onePluginSlug slug of plugin, i.e. "hello-dolly/hello.php"
	 * @param string $enableOrDisable String "enable" or "disable"
	 */
	public function logPluginAutoUpdateEnableOrDisable( $onePluginSlug, $enableOrDisable ) {
		$pluginFile = WP_PLUGIN_DIR . '/' . $onePluginSlug;
		$pluginData = get_plugin_data( $pluginFile, true, false );

		$context = array(
			'plugin_slug'        => $onePluginSlug,
			'plugin_name'        => isset( $pluginData['Name'] ) ? $pluginData['Name'] : null,
			'plugin_version'     => isset( $pluginData['Version'] ) ? $pluginData['Version'] : null,
		);

		if ( $enableOrDisable === 'enable' ) {
			$this->infoMessage( 'plugin_auto_updates_enabled', $context );
		} elseif ( $enableOrDisable === 'disable' ) {
			$this->infoMessage( 'plugin_auto_updates_disabled', $context );
		}
	}

	/**
	 * Things
	 *
	 * @param string $translation Translation.
	 * @param string $text Text.
	 * @param string $domain Domain.
	 */
	public function on_gettext_detect_plugin_error_deactivation_reason( $translation, $text, $domain ) {

		global $pagenow;

		// We only act on page plugins.php.
		if ( ! isset( $pagenow ) || 'plugins.php' !== $pagenow ) {
			return $translation;
		}

		// We only act if the untranslated text is among the following ones
		// (Literally these, no translation).
		$untranslated_texts = array(
			'Plugin file does not exist.',
			'Invalid plugin path.',
			'The plugin does not have a valid header.',
		);

		if ( ! in_array( $text, $untranslated_texts, true ) ) {
			return $translation;
		}

		// Text was among our wanted texts.
		switch ( $text ) {
			case 'Plugin file does not exist.':
				$this->latest_plugin_deactivation_because_of_error_reason[] = 'file_does_not_exist';
				break;
			case 'Invalid plugin path.':
				$this->latest_plugin_deactivation_because_of_error_reason[] = 'invalid_path';
				break;
			case 'The plugin does not have a valid header.':
				$this->latest_plugin_deactivation_because_of_error_reason[] = 'no_valid_header';
				break;
		}

		return $translation;
	}

	/**
	 * There is no way to use a filter and detect a plugin that is disabled because it can't be found or similar error.
	 * we hook into gettext and look for the usage of the error that is returned when this happens.
	 *
	 * A plugin gets deactivated when plugins.php is visited function validate_active_plugins()
	 *      return new WP_Error('plugin_not_found', __('Plugin file does not exist.'));
	 * and if invalid plugin is found then this is outputted
	 *  printf(
	 *  /* translators: 1: plugin file 2: error message
	 *  __( 'The plugin %1$s has been <strong>deactivated</strong> due to an error: %2$s' ),
	 *  '<code>' . esc_html( $plugin_file ) . '</code>',
	 *  $error->get_error_message() );
	 *
	 * @param string $translation Translation.
	 * @param string $text Text.
	 * @param string $domain Domain.
	 */
	public function on_gettext( $translation, $text, $domain ) {

		global $pagenow;

		// We only act on page plugins.php.
		if ( ! isset( $pagenow ) || 'plugins.php' !== $pagenow ) {
			return $translation;
		}

		// We only act if the untranslated text is among the following ones
		// (Literally these, no translation)
		$untranslated_texts = array(
			// This string is called later than the above
			'The plugin %1$s has been <strong>deactivated</strong> due to an error: %2$s',
		);

		if ( ! in_array( $text, $untranslated_texts ) ) {
			return $translation;
		}

		// Directly after the string is translated 'esc_html' is called with the plugin name.
		// This is one of the few ways we can get the name of the plugin.
		// The esc_html filter is used pretty much but we make sure we only do our.
		// stuff the first time it's called (directly after the gettet for the plugin disabled-error..).
		$logger_instance = $this;

		add_filter(
			'esc_html',
			function ( $safe_text, $text ) use ( $logger_instance ) {
				static $is_called = false;

				if ( false === $is_called ) {
					$is_called = true;

					$deactivation_reason = array_shift( $logger_instance->latest_plugin_deactivation_because_of_error_reason );

					// We don't know what plugin that was that got this error and currently there does not seem to be a way to determine that.
					// So that's why we use such generic log messages.
					$logger_instance->warningMessage(
						'plugin_disabled_because_error',
						array(
							'_initiator'          => SimpleLoggerLogInitiators::WORDPRESS,
							'plugin_slug'         => $text,
							'deactivation_reason' => $deactivation_reason,
						)
					);
				}

				return $safe_text;
			},
			10,
			2
		);

		return $translation;
	}

	/**
	 * Show readme from github in a modal win
	 */
	public function ajax_GetGitHubPluginInfo() {

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( "You don't have access to this page.", 'simple-history' ) );
		}

		$repo = isset( $_GET['repo'] ) ? (string) $_GET['repo'] : '';

		if ( ! $repo ) {
			wp_die( esc_html__( 'Could not find GitHub repository.', 'simple-history' ) );
		}

		$repo_parts = explode( '/', rtrim( $repo, '/' ) );
		if ( count( $repo_parts ) !== 5 ) {
			wp_die( esc_html__( 'Could not find GitHub repository.', 'simple-history' ) );
		}

		$repo_username = $repo_parts[3];
		$repo_repo     = $repo_parts[4];

		// https://developer.github.com/v3/repos/contents/
		// https://api.github.com/repos/<username>/<repo>/readme
		$api_url = sprintf( 'https://api.github.com/repos/%1$s/%2$s/readme', urlencode( $repo_username ), urlencode( $repo_repo ) );

		// Get file. Use accept-header to get file as HTML instead of JSON
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => array(
					'accept' => 'application/vnd.github.VERSION.html',
				),
			)
		);

		$response_body = wp_remote_retrieve_body( $response );

		$repo_info = '<p>' . sprintf(
			__( 'Viewing <code>readme</code> from repository <code><a target="_blank" href="%1$s">%2$s</a></code>.', 'simple-history' ),
			esc_url( $repo ),
			esc_html( $repo )
		) . '</p>';

		$github_markdown_css_path = SIMPLE_HISTORY_PATH . '/css/github-markdown.css';

		$escaped_response_body = wp_kses(
			$response_body,
			array(
				'p' => array(),
				'div' => array(),
				'h1' => array(),
				'h2' => array(),
				'h3' => array(),
				'code' => array(),
				'a' => array(
					'href' => array(),
				),
				'img' => array(
					'src' => array(),
				),
				'ul' => array(),
				'li' => array(),
			)
		);

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
				<base target="_blank">

				<header class="repo-info">
					%1$s
				</header>

				<div class="markdown-body readme-contents">
					%2$s
				</div>
			',
			$repo_info, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$escaped_response_body, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( $github_markdown_css_path ), // 3
			esc_url( $repo ) // 4
		);

		exit;
	}

	/**
	 * Saves info about all installed plugins to an option.
	 * When we are done logging then we remove the option.
	 */
	public function save_versions_before_update( $bool = null, $hook_extra = null ) {

		$plugins = get_plugins();

		// does not work
		$option_name = $this->slug . '_plugin_info_before_update';

		$r = update_option( $option_name, SimpleHistory::json_encode( $plugins ) );

		return $bool;
	}

	/**
	 * when plugin updates are done wp_clean_plugins_cache() is called,
	 * which in its turn run:
	 * delete_site_transient( 'update_plugins' );
	 * do_action( 'delete_site_transient_' . $transient, $transient );
	 * delete_site_transient_update_plugins
	 */
	public function remove_saved_versions() {
		delete_option( $this->slug . '_plugin_info_before_update' );
	}

	/**
	 * Called when plugins is updated or installed
	 * Called from class-wp-upgrader.php
	 *
	 * @param Plugin_Upgrader $this Plugin_Upgrader instance. In other contexts, $this, might
	 *                              be a Theme_Upgrader or Core_Upgrade instance.
	 * @param array           $data {
	 *     Array of bulk item update data.
	 */
	public function on_upgrader_process_complete( $plugin_upgrader_instance, $arr_data ) {
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

		if ( isset( $arr_data['type'] ) && 'plugin' == $arr_data['type'] ) {
			// Single plugin install
			if ( isset( $arr_data['action'] ) && 'install' == $arr_data['action'] && ! $plugin_upgrader_instance->bulk ) {
				$upgrader_skin_options = isset( $plugin_upgrader_instance->skin->options ) && is_array( $plugin_upgrader_instance->skin->options ) ? $plugin_upgrader_instance->skin->options : array();
				$upgrader_skin_result  = isset( $plugin_upgrader_instance->skin->result ) && is_array( $plugin_upgrader_instance->skin->result ) ? $plugin_upgrader_instance->skin->result : array();
				// $upgrader_skin_api  = isset( $plugin_upgrader_instance->skin->api ) ? $plugin_upgrader_instance->skin->api : (object) array();
				$new_plugin_data       = isset( $plugin_upgrader_instance->new_plugin_data ) ? $plugin_upgrader_instance->new_plugin_data : array();
				$plugin_slug           = isset( $upgrader_skin_result['destination_name'] ) ? $upgrader_skin_result['destination_name'] : '';

				$context = array(
					'plugin_slug'         => $plugin_slug,
					'plugin_name'         => isset( $new_plugin_data['Name'] ) ? $new_plugin_data['Name'] : '',
					'plugin_version'      => isset( $new_plugin_data['Version'] ) ? $new_plugin_data['Version'] : '',
					'plugin_author'       => isset( $new_plugin_data['Author'] ) ? $new_plugin_data['Author'] : '',
					'plugin_requires_wp'  => isset( $new_plugin_data['RequiresWP'] ) ? $new_plugin_data['RequiresWP'] : '',
					'plugin_requires_php' => isset( $new_plugin_data['RequiresPHP'] ) ? $new_plugin_data['RequiresPHP'] : '',
				);

				/*
				Detect install plugin from wordpress.org
					- options[type] = "web"
					- options[api] contains all we need

				Detect install from upload ZIP
					- options[type] = "upload"

				Also: plugins hosted at GitHub have a de-facto standard field of "GitHub Plugin URI"
				*/
				$install_source = 'web';
				if ( isset( $upgrader_skin_options['type'] ) ) {
					$install_source = (string) $upgrader_skin_options['type'];
				}

				$context['plugin_install_source'] = $install_source;

				// If uploaded plugin store name of ZIP
				if ( 'upload' == $install_source ) {
					/*
					_debug_files
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

					if ( isset( $_FILES['pluginzip']['name'] ) ) {
						$plugin_upload_name            = $_FILES['pluginzip']['name'];
						$context['plugin_upload_name'] = $plugin_upload_name;
					}
				}

				if ( is_a( $plugin_upgrader_instance->skin->result, 'WP_Error' ) ) {
					// Add errors
					// Errors is in original wp admin language
					$context['error_messages'] = $this->simpleHistory->json_encode( $plugin_upgrader_instance->skin->result->errors );
					$context['error_data']     = $this->simpleHistory->json_encode( $plugin_upgrader_instance->skin->result->error_data );

					$this->infoMessage(
						'plugin_installed_failed',
						$context
					);

					$did_log = true;
				} else {
					// Plugin was successfully installed
					// Try to grab more info from the readme
					// Would be nice to grab a screenshot, but that is difficult since they often are stored remotely
					$plugin_destination = isset( $plugin_upgrader_instance->result['destination'] ) ? $plugin_upgrader_instance->result['destination'] : null;

					if ( $plugin_destination ) {
						$plugin_info = $plugin_upgrader_instance->plugin_info();

						$plugin_data = array();
						if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_info ) ) {
							$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_info, true, false );
						}

						$context['plugin_name']        = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '';
						$context['plugin_description'] = isset( $plugin_data['Description'] ) ? $plugin_data['Description'] : '';
						$context['plugin_url']         = isset( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : '';
						$context['plugin_version']     = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
						$context['plugin_author']      = isset( $plugin_data['AuthorName'] ) ? $plugin_data['AuthorName'] : '';

						// Comment out these to debug plugin installs
						// $context["debug_plugin_data"] = $this->simpleHistory->json_encode( $plugin_data );
						// $context["debug_plugin_info"] = $this->simpleHistory->json_encode( $plugin_info );
						if ( ! empty( $plugin_data['GitHub Plugin URI'] ) ) {
							$context['plugin_github_url'] = $plugin_data['GitHub Plugin URI'];
						}
					}

					$this->infoMessage(
						'plugin_installed',
						$context
					);

					$did_log = true;
				}// End if().
			} // End if().

			// Single plugin update
			if ( isset( $arr_data['action'] ) && 'update' == $arr_data['action'] && ! $plugin_upgrader_instance->bulk ) {
				// No plugin info in instance, so get it ourself
				$plugin_data = array();
				if ( file_exists( WP_PLUGIN_DIR . '/' . $arr_data['plugin'] ) ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $arr_data['plugin'], true, false );
				}

				// autoptimize/autoptimize.php
				$plugin_slug = dirname( $arr_data['plugin'] );

				$context = array(
					'plugin_slug'         => $plugin_slug,
					'request'             => $this->simpleHistory->json_encode( $_REQUEST ),
					'plugin_name'         => $plugin_data['Name'],
					'plugin_title'        => $plugin_data['Title'],
					'plugin_description'  => $plugin_data['Description'],
					'plugin_author'       => $plugin_data['Author'],
					'plugin_version'      => $plugin_data['Version'],
					'plugin_url'          => $plugin_data['PluginURI'],
				);

				// update status for plugins are in response
				// plugin folder + index file = key
				// use transient to get url and package
				$update_plugins = get_site_transient( 'update_plugins' );
				if ( $update_plugins && isset( $update_plugins->response[ $arr_data['plugin'] ] ) ) {
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
					$plugin_update_info = $update_plugins->response[ $arr_data['plugin'] ];

					// autoptimize/autoptimize.php
					if ( isset( $plugin_update_info->plugin ) ) {
						$context['plugin_update_info_plugin'] = $plugin_update_info->plugin;
					}

					// https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip
					if ( isset( $plugin_update_info->package ) ) {
						$context['plugin_update_info_package'] = $plugin_update_info->package;
					}
				}

				// To get old version we use our option
				$plugins_before_update = json_decode( get_option( $this->slug . '_plugin_info_before_update', false ), true );
				if ( is_array( $plugins_before_update ) && isset( $plugins_before_update[ $arr_data['plugin'] ] ) ) {
					$context['plugin_prev_version'] = $plugins_before_update[ $arr_data['plugin'] ]['Version'];
				}

				if ( is_a( $plugin_upgrader_instance->skin->result, 'WP_Error' ) ) {
					// Add errors
					// Errors is in original wp admin language
					$context['error_messages'] = json_encode( $plugin_upgrader_instance->skin->result->errors );
					$context['error_data']     = json_encode( $plugin_upgrader_instance->skin->result->error_data );

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

					// echo "on_upgrader_process_complete";
					// sf_d( $plugin_upgrader_instance, '$plugin_upgrader_instance' );
					// sf_d( $arr_data, '$arr_data' );
					$did_log = true;
				}
			} // End if().

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
			if ( isset( $arr_data['bulk'] ) && $arr_data['bulk'] && isset( $arr_data['action'] ) && 'update' == $arr_data['action'] ) {
				$plugins_updated = isset( $arr_data['plugins'] ) ? (array) $arr_data['plugins'] : array();

				foreach ( $plugins_updated as $plugin_name ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name, true, false );

					$plugin_slug = dirname( $plugin_name );

					$context = array(
						'plugin_slug'        => $plugin_slug,
						'plugin_name'        => $plugin_data['Name'],
						'plugin_title'       => $plugin_data['Title'],
						'plugin_description' => $plugin_data['Description'],
						'plugin_author'      => $plugin_data['Author'],
						'plugin_version'     => $plugin_data['Version'],
						'plugin_url'         => $plugin_data['PluginURI'],
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
							$context['plugin_update_info_plugin'] = $plugin_update_info->plugin;
						}

						// https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip
						if ( isset( $plugin_update_info->package ) ) {
							$context['plugin_update_info_package'] = $plugin_update_info->package;
						}
					}

					// To get old version we use our option
					// @TODO: this does not always work, why?
					$plugins_before_update = json_decode( get_option( $this->slug . '_plugin_info_before_update', false ), true );
					if ( is_array( $plugins_before_update ) && isset( $plugins_before_update[ $plugin_name ] ) ) {
						$context['plugin_prev_version'] = $plugins_before_update[ $plugin_name ]['Version'];
					}

					$this->infoMessage(
						'plugin_bulk_updated',
						$context
					);
				}// End foreach().
			}// End if().
		} // End if().

		$this->remove_saved_versions();
	}

	/**
	 * Plugin is activated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 */
	public function on_activated_plugin( $plugin_name, $network_wide ) {

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
			'plugin_name'        => $plugin_data['Name'],
			'plugin_slug'        => $plugin_slug,
			'plugin_title'       => $plugin_data['Title'],
			'plugin_description' => $plugin_data['Description'],
			'plugin_author'      => $plugin_data['Author'],
			'plugin_version'     => $plugin_data['Version'],
			'plugin_url'         => $plugin_data['PluginURI'],
		);

		if ( ! empty( $plugin_data['GitHub Plugin URI'] ) ) {
			$context['plugin_github_url'] = $plugin_data['GitHub Plugin URI'];
		}

		$this->infoMessage( 'plugin_activated', $context );
	}

	/**
	 * Plugin is deactivated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 */
	public function on_deactivated_plugin( $plugin_name ) {

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name, true, false );
		$plugin_slug = dirname( $plugin_name );

		$context = array(
			'plugin_name'        => $plugin_data['Name'],
			'plugin_slug'        => $plugin_slug,
			'plugin_title'       => $plugin_data['Title'],
			'plugin_description' => $plugin_data['Description'],
			'plugin_author'      => $plugin_data['Author'],
			'plugin_version'     => $plugin_data['Version'],
			'plugin_url'         => $plugin_data['PluginURI'],
		);

		if ( ! empty( $plugin_data['GitHub Plugin URI'] ) ) {
			$context['plugin_github_url'] = $plugin_data['GitHub Plugin URI'];
		}

		$this->infoMessage( 'plugin_deactivated', $context );
	}


	/**
	 * Get output for detailed log section
	 */
	public function getLogRowDetailsOutput( $row ) {

		$context     = $row->context;
		$message_key = $context['_message_key'];
		$output      = '';

		// When a plugin is installed we show a bit more information
		// We do it only on install because we don't want to clutter to log,
		// and when something is installed the description is most useful for other
		// admins on the site
		if ( 'plugin_installed' === $message_key ) {
			if ( isset( $context['plugin_description'] ) ) {
				// Description includes a link to author, remove that, i.e. all text after and including <cite>
				$plugin_description = $context['plugin_description'];
				$cite_pos           = strpos( $plugin_description, '<cite>' );
				if ( $cite_pos ) {
					$plugin_description = substr( $plugin_description, 0, $cite_pos );
				}

				// Keys to show
				$arr_plugin_keys = array(
					'plugin_description'         => _x( 'Description', 'plugin logger - detailed output', 'simple-history' ),
					'plugin_install_source'      => _x( 'Source', 'plugin logger - detailed output install source', 'simple-history' ),
					'plugin_install_source_file' => _x( 'Source file name', 'plugin logger - detailed output install source', 'simple-history' ),
					'plugin_version'             => _x( 'Version', 'plugin logger - detailed output version', 'simple-history' ),
					'plugin_author'              => _x( 'Author', 'plugin logger - detailed output author', 'simple-history' ),
					'plugin_url'                 => _x( 'URL', 'plugin logger - detailed output url', 'simple-history' ),
					// "plugin_downloaded" => _x("Downloads", "plugin logger - detailed output downloaded", "simple-history"),
					// "plugin_requires" => _x("Requires", "plugin logger - detailed output author", "simple-history"),
					// "plugin_tested" => _x("Compatible up to", "plugin logger - detailed output compatible", "simple-history"),
					// also available: plugin_rating, plugin_num_ratings
				);

				$arr_plugin_keys = apply_filters( 'simple_history/plugin_logger/row_details_plugin_info_keys', $arr_plugin_keys );

				// Start output of plugin meta data table
				$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

				foreach ( $arr_plugin_keys as $key => $desc ) {
					$desc_output = '';

					switch ( $key ) {
						case 'plugin_downloaded':
							$desc_output = esc_html( number_format_i18n( (int) $context[ $key ] ) );
							break;

						// author is already formatted
						case 'plugin_author':
							$desc_output = $context[ $key ];
							break;

						// URL needs a link
						case 'plugin_url':
							$desc_output = sprintf( '<a href="%1$s">%2$s</a>', esc_attr( $context['plugin_url'] ), esc_html( $context['plugin_url'] ) );
							break;

						case 'plugin_description':
							$desc_output = $plugin_description;
							break;

						case 'plugin_install_source':
							if ( ! isset( $context[ $key ] ) ) {
								break;
							}

							if ( 'web' == $context[ $key ] ) {
								$desc_output = esc_html( __( 'WordPress Plugin Repository', 'simple-history' ) );
							} elseif ( 'upload' == $context[ $key ] ) {
								// $plugin_upload_name = isset( $context["plugin_upload_name"] ) ? $context["plugin_upload_name"] : __("Unknown archive name", "simple-history");
								$desc_output = esc_html( __( 'Uploaded ZIP archive', 'simple-history' ) );
								// $desc_output = esc_html( sprintf( __('Uploaded ZIP archive (%1$s)', "simple-history"), $plugin_upload_name ) );
								// $desc_output = esc_html( sprintf( __('%1$s (uploaded ZIP archive)', "simple-history"), $plugin_upload_name ) );
							} else {
								$desc_output = esc_html( $context[ $key ] );
							}

							break;

						case 'plugin_install_source_file':
							if ( ! isset( $context['plugin_upload_name'] ) || ! isset( $context['plugin_install_source'] ) ) {
								break;
							}

							if ( 'upload' == $context['plugin_install_source'] ) {
								$plugin_upload_name = $context['plugin_upload_name'];
								$desc_output        = esc_html( $plugin_upload_name );
							}

							break;

						default:
							$desc_output = esc_html( $context[ $key ] );
							break;
					}// End switch().

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
						esc_html( $desc ),
						$desc_output
					);
				}// End foreach().

				// Add link with more info about the plugin
				// If plugin_install_source = web then it should be a wordpress.org-plugin
				// If plugin_github_url is set then it's a zip from a github thingie
				// so use link to that.
				$plugin_slug = ! empty( $context['plugin_slug'] ) ? $context['plugin_slug'] : '';

				// Slug + web as install source = show link to wordpress.org
				if ( $plugin_slug && isset( $context['plugin_install_source'] ) && $context['plugin_install_source'] == 'web' ) {
					$output .= sprintf(
						'
						<tr>
							<td></td>
							<td><a title="%2$s" class="thickbox" href="%1$s">%2$s</a></td>
						</tr>
						',
						admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=&amp;TB_iframe=true&amp;width=640&amp;height=550" ),
						esc_html_x( 'View plugin info', 'plugin logger: plugin info thickbox title view all info', 'simple-history' )
					);
				} elseif ( isset( $context['plugin_install_source'] ) && $context['plugin_install_source'] == 'upload' && ! empty( $context['plugin_github_url'] ) ) {
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
						admin_url( sprintf( 'admin-ajax.php?action=SimplePluginLogger_GetGitHubPluginInfo&getrepo&amp;repo=%1$s&amp;TB_iframe=true&amp;width=640&amp;height=550', esc_url_raw( $context['plugin_github_url'] ) ) ),
						esc_html_x( 'View plugin info', 'plugin logger: plugin info thickbox title view all info', 'simple-history' )
					);
				}

				$output .= '</table>';
			}// End if().
		} elseif ( 'plugin_bulk_updated' === $message_key || 'plugin_updated' === $message_key || 'plugin_activated' === $message_key || 'plugin_deactivated' === $message_key ) {
			$plugin_slug = ! empty( $context['plugin_slug'] ) ? $context['plugin_slug'] : '';

			if ( $plugin_slug && empty( $context['plugin_github_url'] ) ) {
				$link_title = esc_html_x( 'View plugin info', 'plugin logger: plugin info thickbox title', 'simple-history' );
				$url        = admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=&amp;TB_iframe=true&amp;width=640&amp;height=550" );

				if ( 'plugin_updated' == $message_key || 'plugin_bulk_updated' == $message_key ) {
					$link_title = esc_html_x( 'View changelog', 'plugin logger: plugin info thickbox title', 'simple-history' );

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
			} elseif ( ! empty( $context['plugin_github_url'] ) ) {
				$output .= sprintf(
					'
					<tr>
						<td></td>
						<td><a title="%2$s" class="thickbox" href="%1$s">%2$s</a></td>
					</tr>
					',
					admin_url( sprintf( 'admin-ajax.php?action=SimplePluginLogger_GetGitHubPluginInfo&getrepo&amp;repo=%1$s&amp;TB_iframe=true&amp;width=640&amp;height=550', esc_url_raw( $context['plugin_github_url'] ) ) ),
					esc_html_x( 'View plugin info', 'plugin logger: plugin info thickbox title view all info', 'simple-history' )
				);
			} // End if().
		} // End if().

		return $output;
	}
}
