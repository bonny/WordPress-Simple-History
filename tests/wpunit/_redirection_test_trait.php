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
	 * Skip when Redirection is not among the plugins WPLoader activated.
	 *
	 * The wpunit-min suite leaves it out, because Redirection 5.10.0 requires
	 * WordPress 6.7 and refuses to activate on the 6.3 floor the plugin header
	 * declares. Without the plugin its classes do not exist and these tests
	 * have nothing to assert against.
	 */
	protected function skip_without_redirection(): void {
		if ( class_exists( '\Red_Options' ) ) {
			return;
		}

		$this->markTestSkipped( 'Redirection is not active in this suite — it cannot run on the oldest supported WordPress.' );
	}

	/**
	 * Load Redirection's capabilities class when the plugin has not.
	 */
	protected static function require_redirection_capabilities(): void {
		if ( ! class_exists( 'Redirection_Capabilities' ) && file_exists( WP_PLUGIN_DIR . '/redirection/redirection-capabilities.php' ) ) {
			require_once WP_PLUGIN_DIR . '/redirection/redirection-capabilities.php';
		}
	}
}
