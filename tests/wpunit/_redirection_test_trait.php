<?php
/**
 * Shared bootstrap for the PluginRedirectionLogger* wpunit suites.
 *
 * `redirection.php` only requires `redirection-admin.php` (which in turn
 * requires `redirection-capabilities.php`) when `red_is_admin()` or WP-CLI
 * is detected. Neither holds in the wpunit process, so `Redirection_Capabilities`
 * is not defined even though `\Red_Options` is. Load it explicitly so the
 * capability-aware code paths run against Redirection's real class.
 */

trait RedirectionTestTrait {
	/**
	 * Load Redirection's capabilities class when the plugin has not.
	 */
	protected static function require_redirection_capabilities(): void {
		if ( ! class_exists( 'Redirection_Capabilities' ) && file_exists( WP_PLUGIN_DIR . '/redirection/redirection-capabilities.php' ) ) {
			require_once WP_PLUGIN_DIR . '/redirection/redirection-capabilities.php';
		}
	}
}
