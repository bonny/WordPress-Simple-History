<?php

namespace Simple_History\Services;

/**
 * Class that setups logging using WP hooks.
 */
class Language_Loader extends Service {
	public function loaded() {
		// Prio 5 so it's loaded before the loggers etc. are setup.
		add_action( 'after_setup_theme', array( $this, 'load_plugin_textdomain' ), 5 );
	}

	/**
	 * Load language files.
	 * Uses the method described at URL:
	 * http://geertdedeckere.be/article/loading-wordpress-language-files-the-right-way
	 *
	 * @since 2.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'simple-history';

		// The "plugin_locale" filter is also used in load_plugin_textdomain()
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		load_textdomain( $domain, WP_LANG_DIR . '/simple-history/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( $this->simple_history->plugin_basename ) . '/languages/' );
	}

}
