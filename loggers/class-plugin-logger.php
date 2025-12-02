<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;
use Simple_History\Log_Initiators;

/**
 * Logs plugin related things, for example installs, updates, and deletions.
 */
class Plugin_Logger extends Logger {

	/**
	 * The logger slug.
	 *
	 * @var string $slug
	 */
	public $slug = 'SimplePluginLogger';

	/**
	 * This variable is set if a plugins has been disabled due to an error,
	 * like when the plugin file does not exist. We need to store this in this
	 * weird way because there is no other way for us to get the reason.
	 *
	 * @var array $latest_plugin_deactivation_because_of_error_reason
	 */
	public $latest_plugin_deactivation_because_of_error_reason = [];

	/**
	 * Array with package results.
	 * The result can contain errors, like if the old plugin could not be removed.
	 * These errors are not added in the final upgrader class response when using bulk update.
	 * So this is the only way to get them.
	 * Used to detect rollback scenarios (WordPress 6.3+ feature).
	 *
	 * Structure: [
	 *   'plugin-slug/plugin-file.php' => [
	 *     'result' => array|WP_Error,
	 *     'hook_extra' => array,
	 *     'rollback_will_occur' => bool,
	 *     'rollback_info' => [
	 *       'backup_slug' => string,
	 *       'backup_dir' => string,
	 *       'error_code' => string,
	 *       'error_message' => string,
	 *     ]
	 *   ]
	 * ]
	 *
	 * @var array $package_results
	 */
	public $package_results = [];

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
	public function get_info() {
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

				'plugin_bulk_updated_failed'    => _x(
					'Failed to update plugin "{plugin_name}"',
					'Plugin failed to update in bulk',
					'simple-history'
				),

				// Plugin disabled due to some error.
				'plugin_disabled_because_error' => _x(
					'Deactivated plugin "{plugin_slug}" because of an error ("{deactivation_reason}").',
					'Plugin was disabled because of an error',
					'simple-history'
				),

				'plugin_auto_updates_enabled'   => _x(
					'Enabled auto-updates for plugin "{plugin_name}"',
					'Plugin was enabled for auto-updates',
					'simple-history'
				),
				'plugin_auto_updates_disabled'  => _x(
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
							'plugin_bulk_updated_failed',
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

		// Fires after a plugin has been activated.
		// If a plugin is silently activated (such as during an update),
		// this hook does not fire.
		add_action( 'activated_plugin', array( $this, 'on_activated_plugin' ), 10, 2 );

		// Fires after a plugin is deactivated.
		// If a plugin is silently deactivated (such as during an update),
		// this hook does not fire.
		add_action( 'deactivated_plugin', array( $this, 'on_deactivated_plugin' ), 10, 1 );

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
		// Only register gettext filters and auto-update hooks when on plugins.php page for better performance.
		add_action( 'load-plugins.php', array( $this, 'on_load_plugins_page' ) );
		add_action( 'wp_ajax_toggle-auto-updates', array( $this, 'handle_auto_update_change' ), 1, 1 );

		// Log plugin deletions, i.e. when a user click "Delete" in the plugins listing
		// or choose plugin(s) and select Bulk actions -> Delete.
		// Since WordPress 4.4 filters exists that are fired before and after plugin deletion.
		add_action( 'delete_plugin', array( $this, 'on_action_delete_plugin' ), 10, 1 );
		add_action( 'deleted_plugin', array( $this, 'on_action_deleted_plugin' ), 10, 2 );

		// Capture individual plugin install/update errors. These errors are not included in the final
		// upgrader response during bulk updates, so this is the only way to get them.
		add_filter( 'upgrader_install_package_result', [ $this, 'on_upgrader_install_package_result' ], 10, 2 );
	}

	/**
	 * Register gettext filters and handle auto-update when loading the plugins.php page for performance optimization.
	 * Only register these filters and run functionality when they're actually needed.
	 */
	public function on_load_plugins_page() {
		add_filter( 'gettext', array( $this, 'on_gettext_detect_plugin_error_deactivation_reason' ), 10, 3 );
		add_filter( 'gettext', array( $this, 'on_gettext' ), 10, 3 );

		// Handle auto-update change detection.
		$this->handle_auto_update_change();
	}

	/**
	 * Filters the result of WP_Upgrader::install_package().
	 * Fired from WP_Upgrader class (or a subclass like Plugin_Upgrader in the run() function.
	 * Here we can get access to any errors that happen during plugin install/update.
	 * This hook is fired once for each plugin.
	 *
	 * @param array|\WP_Error $result     Result from WP_Upgrader::install_package().
	 * @param array           $hook_extra Extra arguments passed to hooked filters.
	 */
	public function on_upgrader_install_package_result( $result, $hook_extra ) {
		/*
		$result example:

		WP_Error Object
		(
			[errors] => Array
				(
					[remove_old_failed] => Array
						(
							[0] => Could not remove the old plugin.
						)

				)

			[error_data] => Array
				(
				)

			[additional_data:protected] => Array
				(
				)

		)

		$hook_extra example:

		Array
		(
			[plugin] => classic-widgets/classic-widgets.php
			[temp_backup] => Array
				(
					[slug] => classic-widgets
					[src] => /var/www/html/wp-content/plugins
					[dir] => plugins
				)

		)
		*/

		$plugin_main_file_path = $hook_extra['plugin'] ?? null;

		if ( ! $plugin_main_file_path ) {
			return $result;
		}

		$this->package_results[ $plugin_main_file_path ] = [
			'result'     => $result,
			'hook_extra' => $hook_extra,
		];

		// Detect if rollback will occur (WordPress 6.3+ feature).
		// Rollback happens when:
		// 1. This is an update (temp_backup exists in hook_extra)
		// 2. The update failed (result is WP_Error)
		// When both conditions are true, WordPress will automatically restore
		// the previous version from the temporary backup on shutdown.
		$is_update = isset( $hook_extra['temp_backup'] );
		$has_error = is_wp_error( $result );

		if ( $is_update && $has_error ) {
			$this->package_results[ $plugin_main_file_path ]['rollback_will_occur'] = true;
			$this->package_results[ $plugin_main_file_path ]['rollback_info']       = [
				'backup_slug'   => $hook_extra['temp_backup']['slug'] ?? '',
				'backup_dir'    => $hook_extra['temp_backup']['dir'] ?? '',
				'error_code'    => $result->get_error_code(),
				'error_message' => $result->get_error_message(),
			];
		}

		return $result;
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
			'plugin'             => $plugin_file,
			'plugin_name'        => $plugin_data['Name'],
			'plugin_title'       => $plugin_data['Title'],
			'plugin_description' => $plugin_data['Description'],
			'plugin_author'      => $plugin_data['Author'],
			'plugin_version'     => $plugin_data['Version'],
			'plugin_url'         => $plugin_data['PluginURI'],
		);

		$this->info_message(
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

		add_action(
			"update_option_{$option}",
			// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
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

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
				if ( ! $action ) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$action = sanitize_text_field( wp_unslash( $_POST['action'] ?? '' ) );
				}

				// Bail if doing ajax and
				// - action is not toggle-auto-updates.
				// - type is not plugin.
				if ( wp_doing_ajax() ) {
					if ( $action !== 'toggle-auto-updates' ) {
						return;
					}

					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$type = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
					if ( $type !== 'plugin' ) {
						return;
					}
				}

				// Bail if screen and not plugin screen.
				$current_screen = get_current_screen();
				if ( is_a( $current_screen, 'WP_Screen' ) && ( $current_screen->base !== 'plugins' ) ) {
					return;
				}

				// Enable or disable, string "enable" or "disable".
				$enableOrDisable = null;

				// Plugin slugs that actions are performed against.
				$plugins = array();

				if ( in_array( $action, array( 'enable-auto-update', 'disable-auto-update' ), true ) ) {
					// Opening single item enable/disable auto update link in plugin list in new window.
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ?? '' ) );

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
					// *    [asset] => redirection/redirection.php.
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$state = sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) );
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					$asset = sanitize_text_field( wp_unslash( $_POST['asset'] ?? '' ) );

					if ( $state === 'enable' ) {
						$enableOrDisable = 'enable';
					} elseif ( $state === 'disable' ) {
						$enableOrDisable = 'disable';
					}

					if ( $asset ) {
						$plugins[] = sanitize_text_field( urldecode( $asset ) );
					}
				} elseif ( in_array( $action, array( 'enable-auto-update-selected', 'disable-auto-update-selected' ), true ) ) {
					// $_POST when checking multiple plugins and choosing Enable auto updates or Disable auto updates.
					// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
					$checked = wp_unslash( $_POST['checked'] ?? null );
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
				// - an array of plugin slugs in $plugins.
				// - if plugin auto updates is to be enabled or disabled din $enableOrDisable.

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
	 * @param string $onePluginSlug slug of plugin, i.e. "hello-dolly/hello.php".
	 * @param string $enableOrDisable String "enable" or "disable".
	 */
	public function logPluginAutoUpdateEnableOrDisable( $onePluginSlug, $enableOrDisable ) {
		$pluginFile = WP_PLUGIN_DIR . '/' . $onePluginSlug;
		$pluginData = get_plugin_data( $pluginFile, true, false );

		$context = array(
			'plugin_slug'    => $onePluginSlug,
			'plugin_name'    => $pluginData['Name'] ?? null,
			'plugin_version' => $pluginData['Version'] ?? null,
		);

		if ( $enableOrDisable === 'enable' ) {
			$this->info_message( 'plugin_auto_updates_enabled', $context );
		} elseif ( $enableOrDisable === 'disable' ) {
			$this->info_message( 'plugin_auto_updates_disabled', $context );
		}
	}

	/**
	 * Detect when a plugin is deactivated due to an error, like if the plugin file has been deleted.
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
		// (Literally these, no translation).
		$untranslated_texts = array(
			// This string is called later than the above.
			'The plugin %1$s has been <strong>deactivated</strong> due to an error: %2$s',
		);

		if ( ! in_array( $text, $untranslated_texts, true ) ) {
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
					$logger_instance->warning_message(
						'plugin_disabled_because_error',
						array(
							'_initiator'          => Log_Initiators::WORDPRESS,
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$repo = isset( $_GET['repo'] ) ? (string) sanitize_text_field( wp_unslash( $_GET['repo'] ) ) : '';

		if ( $repo !== '' ) {
			wp_die( esc_html__( 'Could not find GitHub repository.', 'simple-history' ) );
		}

		$repo_parts = explode( '/', rtrim( $repo, '/' ) );
		if ( count( $repo_parts ) !== 5 ) {
			wp_die( esc_html__( 'Could not find GitHub repository.', 'simple-history' ) );
		}

		$repo_username = $repo_parts[3];
		$repo_repo     = $repo_parts[4];

		// https://developer.github.com/v3/repos/contents/.
		// https://api.github.com/repos/<username>/<repo>/readme.
		$api_url = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/readme',
			rawurlencode( $repo_username ),
			rawurlencode( $repo_repo ) 
		);

		// Get file. Use accept-header to get file as HTML instead of JSON.
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
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
			// translators: %1$s is a link to the repo, %2$s is the repo name.
			__( 'Viewing <code>readme</code> from repository <code><a target="_blank" href="%1$s">%2$s</a></code>.', 'simple-history' ),
			esc_url( $repo ),
			esc_html( $repo )
		) . '</p>';

		$github_markdown_css_path = SIMPLE_HISTORY_PATH . '/css/github-markdown.css';

		$escaped_response_body = wp_kses(
			$response_body,
			array(
				'p'    => array(),
				'div'  => array(),
				'h1'   => array(),
				'h2'   => array(),
				'h3'   => array(),
				'code' => array(),
				'a'    => array(
					'href' => array(),
				),
				'img'  => array(
					'src' => array(),
				),
				'ul'   => array(),
				'li'   => array(),
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
	 * Fired from filter `upgrader_pre_install`.
	 *
	 * @param bool|\WP_Error $bool_or_error Default null.
	 * @param array          $hook_extra Default null.
	 */
	public function save_versions_before_update( $bool_or_error = null, $hook_extra = null ) {
		update_option(
			$this->get_slug() . '_plugin_info_before_update',
			Helpers::json_encode( get_plugins() ),
			false
		);

		return $bool_or_error;
	}

	/**
	 * Called when plugins is updated or installed
	 * Called from class-wp-upgrader.php
	 *
	 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance. In other contexts, $this, might
	 *                                                  be a Theme_Upgrader or Core_Upgrade instance.
	 * @param array            $arr_data                 Array of bulk item update data.
	 */
	public function on_upgrader_process_complete( $plugin_upgrader_instance, $arr_data ) {
		// Bail if not plugin update data.
		if ( ! isset( $arr_data['type'] ) || $arr_data['type'] !== 'plugin' ) {
			return;
		}

		$this->on_upgrader_process_complete_log_single_plugin_install( $plugin_upgrader_instance, $arr_data );
		$this->on_upgrader_process_complete_log_single_plugin_update( $plugin_upgrader_instance, $arr_data );
		$this->on_upgrader_process_complete_log_bulk_plugin_update( $plugin_upgrader_instance, $arr_data );
	}

	/**
	 * Log single, non bulk, plugin update or update failure.
	 * Not sure when this happens anymore, I always get bulk results, no matter where I
	 * update from.
	 *
	 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance.
	 * @param array            $arr_data                 Array of bulk item update data.
	 */
	protected function on_upgrader_process_complete_log_single_plugin_update( $plugin_upgrader_instance, $arr_data ) {
		// Bail if not single plugin non bulk update.
		if ( ! isset( $arr_data['action'] ) || $arr_data['action'] !== 'update' || $plugin_upgrader_instance->bulk ) {
			return;
		}

		// No plugin info in instance, so get it ourself.
		$plugin_data = [];
		if ( file_exists( WP_PLUGIN_DIR . '/' . $arr_data['plugin'] ) ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $arr_data['plugin'], true, false );
		}

		// autoptimize/autoptimize.php.
		$plugin_slug = dirname( $arr_data['plugin'] );

		$context = [
			'plugin_slug'        => $plugin_slug,
			'request'            => Helpers::json_encode( $_REQUEST ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'plugin_name'        => $plugin_data['Name'],
			'plugin_title'       => $plugin_data['Title'],
			'plugin_description' => $plugin_data['Description'],
			'plugin_author'      => $plugin_data['Author'],
			'plugin_version'     => $plugin_data['Version'],
			'plugin_url'         => $plugin_data['PluginURI'],
		];

		// Add Update URI if it is set. Available since WP 5.8.
		if ( isset( $plugin_data['UpdateURI'] ) ) {
			$context['plugin_update_uri'] = $plugin_data['UpdateURI'];
		}

		// update status for plugins are in response
		// plugin folder + index file = key
		// use transient to get url and package.
		$update_plugins = get_site_transient( 'update_plugins' );
		if ( $update_plugins && isset( $update_plugins->response[ $arr_data['plugin'] ] ) ) {
			$plugin_update_info = $update_plugins->response[ $arr_data['plugin'] ];

			// autoptimize/autoptimize.php.
			if ( isset( $plugin_update_info->plugin ) ) {
				$context['plugin_update_info_plugin'] = $plugin_update_info->plugin;
			}

			// https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip.
			if ( isset( $plugin_update_info->package ) ) {
				$context['plugin_update_info_package'] = $plugin_update_info->package;
			}
		}

		// To get old version we use our option.
		$plugins_before_update = json_decode( get_option( $this->get_slug() . '_plugin_info_before_update', false ), true );
		if ( is_array( $plugins_before_update ) && isset( $plugins_before_update[ $arr_data['plugin'] ] ) ) {
			$context['plugin_prev_version'] = $plugins_before_update[ $arr_data['plugin'] ]['Version'];
		}

		if ( is_a( $plugin_upgrader_instance->skin->result, 'WP_Error' ) ) {
			// Add errors
			// Errors is in original wp admin language.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			$context['error_messages'] = json_encode( $plugin_upgrader_instance->skin->result->errors );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			$context['error_data'] = json_encode( $plugin_upgrader_instance->skin->result->error_data );

			// Add rollback context if rollback will occur.
			$context = $this->add_rollback_context( $context, $arr_data['plugin'] );

			$this->info_message(
				'plugin_update_failed',
				$context
			);
		} else {
			$this->info_message(
				'plugin_updated',
				$context
			);
		}
	}

	/**
	 * Log bulk plugin update.
	 *
	 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance.
	 * @param array            $arr_data {
	 *     Array of bulk item update data.
	 *
	 *     @type string $action       Type of action. Default 'update'.
	 *     @type string $type         Type of update process. Accepts 'plugin', 'theme', 'translation', or 'core'.
	 *     @type bool   $bulk         Whether the update process is a bulk update. Default true.
	 *     @type array  $plugins      Array of the basename paths of the plugins' main files.
	 *     @type array  $themes       The theme slugs.
	 *     @type array  $translations {
	 *         Array of translations update data.
	 *
	 *         @type string $language The locale the translation is for.
	 *         @type string $type     Type of translation. Accepts 'plugin', 'theme', or 'core'.
	 *         @type string $slug     Text domain the translation is for. The slug of a theme/plugin or
	 *                                'default' for core translations.
	 *         @type string $version  The version of a theme, plugin, or core.
	 *     }
	 * }
	 */
	protected function on_upgrader_process_complete_log_bulk_plugin_update( $plugin_upgrader_instance, $arr_data ) {
		// Bail if not bulk plugin update.
		if ( ! isset( $arr_data['bulk'] ) || ! $arr_data['bulk'] || ! isset( $arr_data['action'] ) || $arr_data['action'] !== 'update' ) {
			return;
		}

		$plugins_updated = isset( $arr_data['plugins'] ) ? (array) $arr_data['plugins'] : [];

		/** @var string $plugin_main_file_path Plugin folder and main file, i.e. classic-widgets/classic-widgets.php */
		foreach ( $plugins_updated as $plugin_main_file_path ) {
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_main_file_path, true, false );
			$plugin_slug = dirname( $plugin_main_file_path );

			$context = [
				'plugin_main_file_path' => $plugin_main_file_path,
				'plugin_slug'           => $plugin_slug,
				'plugin_name'           => $plugin_data['Name'],
				'plugin_title'          => $plugin_data['Title'],
				'plugin_description'    => $plugin_data['Description'],
				'plugin_author'         => $plugin_data['Author'],
				'plugin_version'        => $plugin_data['Version'],
				'plugin_url'            => $plugin_data['PluginURI'],
			];

			// Add Update URI if it is set. Available since WP 5.8.
			if ( isset( $plugin_data['UpdateURI'] ) ) {
				$context['plugin_update_uri'] = $plugin_data['UpdateURI'];
			}

			// Get url and package.
			$update_plugins = get_site_transient( 'update_plugins' );
			if ( $update_plugins && isset( $update_plugins->response[ $plugin_main_file_path ] ) ) {
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.BlockComment.NoEmptyLineBefore
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

				$plugin_update_info = $update_plugins->response[ $plugin_main_file_path ];

				// autoptimize/autoptimize.php.
				if ( isset( $plugin_update_info->plugin ) ) {
					$context['plugin_update_info_plugin'] = $plugin_update_info->plugin;
				}

				// https://downloads.wordpress.org/plugin/autoptimize.1.9.1.zip.
				if ( isset( $plugin_update_info->package ) ) {
					$context['plugin_update_info_package'] = $plugin_update_info->package;
				}
			}

			// To get old version we use our option.
			$plugins_before_update = json_decode( get_option( $this->get_slug() . '_plugin_info_before_update', false ), true );
			if ( is_array( $plugins_before_update ) && isset( $plugins_before_update[ $plugin_main_file_path ] ) ) {
				$context['plugin_prev_version'] = $plugins_before_update[ $plugin_main_file_path ]['Version'];
			}

			$plugin_errors = $this->package_results[ $plugin_main_file_path ]['result']->errors ?? [];

			if ( count( $plugin_errors ) > 0 ) {
				$context['plugin_errors'] = $plugin_errors;

				// Add rollback context if rollback will occur.
				$context = $this->add_rollback_context( $context, $plugin_main_file_path );

				$this->warning_message(
					'plugin_bulk_updated_failed',
					$context
				);
			} else {
				$this->info_message(
					'plugin_bulk_updated',
					$context
				);
			}
		}
	}

	/**
	 * Log single, non bulk, plugin install or install failure.
	 *
	 * @param \Plugin_Upgrader $plugin_upgrader_instance Plugin_Upgrader instance.
	 * @param array            $arr_data                 Array of bulk item update data.
	 */
	protected function on_upgrader_process_complete_log_single_plugin_install( $plugin_upgrader_instance, $arr_data ) {
		// Bail if not single plugin install data.
		if ( ! isset( $arr_data['action'] ) || $arr_data['action'] !== 'install' || $plugin_upgrader_instance->bulk ) {
			return;
		}

		$upgrader_skin_options = isset( $plugin_upgrader_instance->skin->options ) && is_array( $plugin_upgrader_instance->skin->options ) ? $plugin_upgrader_instance->skin->options : array();
		$upgrader_skin_result  = isset( $plugin_upgrader_instance->skin->result ) && is_array( $plugin_upgrader_instance->skin->result ) ? $plugin_upgrader_instance->skin->result : array();
		$new_plugin_data       = $plugin_upgrader_instance->new_plugin_data ?? array();
		$plugin_slug           = $upgrader_skin_result['destination_name'] ?? '';

		$context = [
			'plugin_slug'         => $plugin_slug,
			'plugin_name'         => $new_plugin_data['Name'] ?? '',
			'plugin_url'          => $new_plugin_data['PluginURI'] ?? '',
			'plugin_version'      => $new_plugin_data['Version'] ?? '',
			'plugin_author'       => $new_plugin_data['Author'] ?? '',
			'plugin_requires_wp'  => $new_plugin_data['RequiresWP'] ?? '',
			'plugin_requires_php' => $new_plugin_data['RequiresPHP'] ?? '',
		];

		// Add Update URI if it is set. Available since WP 5.8.
		if ( isset( $new_plugin_data['UpdateURI'] ) ) {
			$context['plugin_update_uri'] = $new_plugin_data['UpdateURI'];
		}

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

		// If uploaded plugin store name of ZIP.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( 'upload' === $install_source && isset( $_FILES['pluginzip']['name'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$plugin_upload_name            = sanitize_text_field( $_FILES['pluginzip']['name'] );
			$context['plugin_upload_name'] = $plugin_upload_name;
		}

		if ( is_a( $plugin_upgrader_instance->skin->result, 'WP_Error' ) ) {
			// Add errors
			// Errors is in original wp admin language.
			$context['error_messages'] = Helpers::json_encode( $plugin_upgrader_instance->skin->result->errors );
			$context['error_data']     = Helpers::json_encode( $plugin_upgrader_instance->skin->result->error_data );

			$this->info_message(
				'plugin_installed_failed',
				$context
			);
		} else {
			// Plugin was successfully installed
			// Try to grab more info from the readme
			// Would be nice to grab a screenshot, but that is difficult since they often are stored remotely.
			$plugin_destination = $plugin_upgrader_instance->result['destination'] ?? null;

			if ( $plugin_destination ) {
				$plugin_info = $plugin_upgrader_instance->plugin_info();

				$plugin_data = array();
				if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_info ) ) {
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_info, true, false );
				}

				$context['plugin_name']        = $plugin_data['Name'] ?? '';
				$context['plugin_description'] = $plugin_data['Description'] ?? '';
				$context['plugin_url']         = $plugin_data['PluginURI'] ?? '';
				$context['plugin_version']     = $plugin_data['Version'] ?? '';
				$context['plugin_author']      = $plugin_data['AuthorName'] ?? '';

				if ( ! empty( $plugin_data['GitHub Plugin URI'] ) ) {
					$context['plugin_github_url'] = $plugin_data['GitHub Plugin URI'];
				}
			}

			$this->info_message(
				'plugin_installed',
				$context
			);
		}
	}

	/**
	 * Plugin is activated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 *
	 * @param string $plugin_name Plugin name.
	 * @param bool   $network_wide Network wide.
	 */
	public function on_activated_plugin( $plugin_name, $network_wide ) {
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

		$this->info_message( 'plugin_activated', $context );
	}

	/**
	 * Plugin is deactivated
	 * plugin_name is like admin-menu-tree-page-view/index.php
	 *
	 * @param string $plugin_name Plugin name.
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

		$this->info_message( 'plugin_deactivated', $context );
	}

	/**
	 * Get output for detailed log section
	 *
	 * @param object $row Log row.
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'];
		$output      = '';

		switch ( $message_key ) {
			case 'plugin_installed':
				$output = $this->get_plugin_installed_details_output( $context );
				break;
			case 'plugin_bulk_updated':
			case 'plugin_updated':
			case 'plugin_activated':
			case 'plugin_deactivated':
				$output = $this->get_plugin_action_details_output( $context, $message_key );
				break;
		}

		return $output;
	}

	/**
	 * Get detailed output for plugin installation
	 *
	 * @param array $context Log context.
	 * @return string HTML output.
	 */
	private function get_plugin_installed_details_output( $context ) {
		$output = '';

		if ( ! isset( $context['plugin_description'] ) ) {
			return $output;
		}

		// Description includes a link to author, remove that, i.e. all text after and including <cite>.
		$plugin_description = $context['plugin_description'];
		$cite_pos           = strpos( $plugin_description, '<cite>' );
		if ( $cite_pos ) {
			$plugin_description = substr( $plugin_description, 0, $cite_pos );
		}

		// Keys to show.
		$arr_plugin_keys = array(
			'plugin_description'         => _x( 'Description', 'plugin logger - detailed output', 'simple-history' ),
			'plugin_install_source'      => _x( 'Source', 'plugin logger - detailed output install source', 'simple-history' ),
			'plugin_install_source_file' => _x( 'Source file name', 'plugin logger - detailed output install source', 'simple-history' ),
			'plugin_version'             => _x( 'Version', 'plugin logger - detailed output version', 'simple-history' ),
			'plugin_author'              => _x( 'Author', 'plugin logger - detailed output author', 'simple-history' ),
			'plugin_url'                 => _x( 'URL', 'plugin logger - detailed output url', 'simple-history' ),
		);

		$arr_plugin_keys = apply_filters( 'simple_history/plugin_logger/row_details_plugin_info_keys', $arr_plugin_keys );

		// Start output of plugin meta data table.
		$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

		foreach ( $arr_plugin_keys as $key => $desc ) {
			$desc_output = $this->get_plugin_key_description_output( $key, $context, $plugin_description );

			if ( trim( $desc_output ) === '' ) {
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
		}

		// Add link with more info about the plugin.
		$output .= $this->get_plugin_info_link( $context );

		$output .= '</table>';

		return $output;
	}

	/**
	 * Get description output for a specific plugin key
	 *
	 * @param string $key Plugin key.
	 * @param array  $context Log context.
	 * @param string $plugin_description Plugin description.
	 * @return string Description output.
	 */
	private function get_plugin_key_description_output( $key, $context, $plugin_description ) {
		switch ( $key ) {
			case 'plugin_downloaded':
				return esc_html( number_format_i18n( (int) $context[ $key ] ) );

			case 'plugin_author':
				// Author is already formatted.
				return $context[ $key ];

			case 'plugin_url':
				return sprintf( '<a href="%1$s">%2$s</a>', esc_attr( $context['plugin_url'] ), esc_html( $context['plugin_url'] ) );

			case 'plugin_description':
				return $plugin_description;

			case 'plugin_install_source':
				if ( ! isset( $context[ $key ] ) ) {
					return '';
				}

				if ( 'web' === $context[ $key ] ) {
					return esc_html( __( 'WordPress Plugin Repository', 'simple-history' ) );
				} elseif ( 'upload' === $context[ $key ] ) {
					return esc_html( __( 'Uploaded ZIP archive', 'simple-history' ) );
				} else {
					return esc_html( $context[ $key ] );
				}

			case 'plugin_install_source_file':
				if ( ! isset( $context['plugin_upload_name'] ) || ! isset( $context['plugin_install_source'] ) ) {
					return '';
				}

				if ( 'upload' === $context['plugin_install_source'] ) {
					$plugin_upload_name = $context['plugin_upload_name'];
					return esc_html( $plugin_upload_name );
				}

				return '';

			default:
				return esc_html( $context[ $key ] );
		}
	}

	/**
	 * Get plugin info link
	 *
	 * @param array $context Log context.
	 * @return string HTML output.
	 */
	private function get_plugin_info_link( $context ) {
		$output      = '';
		$plugin_slug = empty( $context['plugin_slug'] ) ? '' : $context['plugin_slug'];

		// Slug + web as install source = show link to wordpress.org.
		if ( $plugin_slug && isset( $context['plugin_install_source'] ) && $context['plugin_install_source'] === 'web' ) {
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
		} elseif ( isset( $context['plugin_install_source'] ) && $context['plugin_install_source'] === 'upload' && ! empty( $context['plugin_github_url'] ) ) {
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

		return $output;
	}

	/**
	 * Get detailed output for plugin actions (update, activate, deactivate)
	 *
	 * @param array  $context Log context.
	 * @param string $message_key Message key.
	 * @return string HTML output.
	 */
	private function get_plugin_action_details_output( $context, $message_key ) {
		$output         = '';
		$plugin_slug    = empty( $context['plugin_slug'] ) ? '' : $context['plugin_slug'];
		$plugin_version = empty( $context['plugin_version'] ) ? '' : $context['plugin_version'];

		if ( $plugin_slug && empty( $context['plugin_github_url'] ) ) {
			$link_title = esc_html_x( 'View plugin info', 'plugin logger: plugin info thickbox title', 'simple-history' );
			$url        = admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=&amp;TB_iframe=true&amp;width=640&amp;height=550" );

			if ( in_array( $message_key, [ 'plugin_updated', 'plugin_bulk_updated' ], true ) ) {
				$link_title = esc_html_x( 'View changelog', 'plugin logger: plugin info thickbox title', 'simple-history' );

				if ( is_multisite() ) {
					$url = network_admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=changelog&amp;TB_iframe=true&amp;width=772&amp;height=550" );
				} else {
					$url = admin_url( "plugin-install.php?tab=plugin-information&amp;plugin={$plugin_slug}&amp;section=changelog&amp;TB_iframe=true&amp;width=772&amp;height=550" );
				}

				/**
				 * Allow plugins (or Simple History itself) to add extra details to the plugin update details output.
				 *
				 * The filter name is dynamic and includes the plugin slug and version to ensure specificity:
				 *   simple_history/pluginlogger/plugin_updated_details/{plugin_slug}/{plugin_version}
				 *
				 * Example with actual values:
				 *   simple_history/pluginlogger/plugin_updated_details/simple-history
				 *
				 * @param string $extra_details   Extra HTML to output after the changelog link. Probably empty string.
				 */
				$extra_details = apply_filters( "simple_history/pluginlogger/plugin_updated_details/{$plugin_slug}", '' );

				/**
				 * Allow plugins (or Simple History itself) to add extra details to the plugin update details output.
				 *
				 * The filter name is dynamic and includes the plugin slug and version to ensure specificity:
				 *   simple_history/pluginlogger/plugin_updated_details/{plugin_slug}/{plugin_version}
				 *
				 * Example with actual values:
				 *   simple_history/pluginlogger/plugin_updated_details/simple-history/5.14.0
				 *
				 * @param string $extra_details   Extra HTML to output after the changelog link. Probably empty string.
				 */
				$extra_details = apply_filters( "simple_history/pluginlogger/plugin_updated_details/{$plugin_slug}/{$plugin_version}", $extra_details );

				if ( ! empty( $extra_details ) ) {
					$output .= $extra_details;
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
		}

		return $output;
	}

	/**
	 * Add rollback context to event if rollback will occur.
	 *
	 * @param array  $context Context array.
	 * @param string $plugin_identifier Plugin main file path.
	 * @return array Modified context array.
	 */
	private function add_rollback_context( $context, $plugin_identifier ) {
		// Check if rollback will occur (WordPress 6.3+ feature).
		$package_result = $this->package_results[ $plugin_identifier ] ?? null;
		if ( $package_result && ! empty( $package_result['rollback_will_occur'] ) ) {
			$context['rollback_will_occur'] = true;
			if ( ! empty( $package_result['rollback_info'] ) ) {
				$context['rollback_backup_slug']   = $package_result['rollback_info']['backup_slug'];
				$context['rollback_error_code']    = $package_result['rollback_info']['error_code'];
				$context['rollback_error_message'] = $package_result['rollback_info']['error_message'];
			}
		}

		return $context;
	}
}
