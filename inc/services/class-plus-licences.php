<?php

namespace Simple_History\Services;

use Simple_History\Plugin_Updater;
use Simple_History\Plus_Plugin;

class Plus_Licences extends Service {
	/**
	 * Array with info about all plus-plugins.
	 *
	 * @var array<Plus_Plugin>
	 */
	private $plus_plugins = [];

	public function loaded() {
		// When loggers are instantiated, register plugin updaters simple_history/loggers/instantiated.
		add_action( 'simple_history/loggers/instantiated', [ $this, 'init_plugin_updater_for_registered_licence_plugins' ] );
	}

	/**
	 * Get all plus-plugins.
	 *
	 * @return array<Plus_Plugin>
	 */
	public function get_plus_plugins() {
		return $this->plus_plugins;
	}

	/**
	 * Check if any add-ons are installed.
	 *
	 * @return bool
	 */
	public function has_add_ons() {
		return count( $this->get_plus_plugins() ) > 0;
	}

	/**
	 * Register plugin updaters for all added plus-plugins.
	 */
	public function init_plugin_updater_for_registered_licence_plugins() {
		foreach ( $this->get_plus_plugins() as $plugin ) {
			$this->init_updater_for_plugin( $plugin );
		}
	}

	/**
	 * Register a plugin that is a plus-logger
	 * and requires a licence code to be updated.
	 *
	 * @param string $plugin_id Id of plugin, eg basenamed path + index file: "simple-history-plus-woocommerce/index.php".
	 * @param string $plugin_slug Slug of plugin, eg "simple-history-plus-woocommerce".
	 * @param string $version Current version of plugin, eg "1.0.0".
	 * @param string $name Name of plugin, eg "Simple History Plus for WooCommerce".
	 * @param int $product_id Product ID of plugin, eg 112341.
	 */
	public function register_plugin_for_license( $plugin_id, $plugin_slug, $version, $name, $product_id ) {
		$this->plus_plugins[ $plugin_slug ] = new Plus_Plugin( $plugin_id, $plugin_slug, $version, $name, $product_id );
	}

	/**
	 * Init the plugin updater for a plugin.
	 *
	 * @param Plus_Plugin $plugin Plugin to init updater for.
	 */
	private function init_updater_for_plugin( $plugin ) {
		/**
		 * Instantiate the updater class for each Plus plugin.
		 */
		new Plugin_Updater(
			plugin_basename( $plugin->id ), // "simple-history-plus/index.php"
			$plugin->slug, // "simple-history-plus"
			$plugin->version, // "1.0.0"
			SIMPLE_HISTORY_LICENCES_API_URL // "https://simple-history.com/wp-json/lsq/v1"
		);
	}
}
