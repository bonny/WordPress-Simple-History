<?php

namespace Simple_History\Services;

use Simple_History\Plugin_Updater;

class Plus_Licences extends Service {
	/**
	 * Array with plugins that are plus-plugins.
	 * Array contains plugin id as key and valis is arrays where each array has:
	 * - plugin_slug
	 * - plugin_id
	 * - version
	 *
	 * @var array {
	 *    @type string $id Id of plugin, eg basenamed path + index file: "simple-history-plus-woocommerce/index.php".
	 *    @type string $slug Slug of plugin, eg "simple-history-plus-woocommerce".
	 *    @type string $version Current version of plugin, eg "1.0.0".
	 * }
	 */
	private $plus_plugins = [];

	public function loaded() {
		// $this->init_updater();

		// When loggers are instantiated, register plugin updaters simple_history/loggers/instantiated.
		add_action( 'simple_history/loggers/instantiated', [ $this, 'init_plugin_updater_for_registered_licence_plugins' ] );
	}

	public function get_plus_plugins() {
		return $this->plus_plugins;
	}

	/**
	 * Register plugin updaters for all added plus-plugins.
	 */
	public function init_plugin_updater_for_registered_licence_plugins() {
		foreach ( $this->get_plus_plugins() as $plugin ) {
			$this->init_updater_for_plugin( $plugin['id'], $plugin['slug'], $plugin['version'] );
		}
	}

	/**
	 * Register a plugin that is a plus-logger
	 * and requires a licence code to be updated.
	 *
	 * @param string $plugin_id Id of plugin, eg basenamed path + index file: "simple-history-plus-woocommerce/index.php".
	 * @param string $plugin_slug Slug of plugin, eg "simple-history-plus-woocommerce".
	 * @param string $version Current version of plugin, eg "1.0.0".
	 */
	public function register_plugin_for_license( $plugin_id, $plugin_slug, $version, $name ) {
		$this->plus_plugins[ $plugin_slug ] = [
			'id' => $plugin_id,
			'slug' => $plugin_slug,
			'version' => $version,
			'name' => $name,
		];
	}

	/**
	 * Init the plugin updater for a plugin.
	 *
	 * @param string $plugin_id Id of plugin, eg basenamed path + index file: "simple-history-plus-woocommerce/index.php".
	 * @param string $plugin_slug Slug of plugin, eg "simple-history-plus-woocommerce".
	 * @param string $version Current version of plugin, eg "1.0.0".
	 */
	private function init_updater_for_plugin( $plugin_id, $plugin_slug, $version ) {
		// sh_d( 'init_updater_for_plugin', $plugin_id, $plugin_slug, $version, SIMPLE_HISTORY_LICENCES_API_URL );exit;
		/**
		 * Instanciate the updater class for each Plus plugin.
		 */
		new Plugin_Updater(
			plugin_basename( $plugin_id ), // "simple-history-plus/index.php"
			$plugin_slug, // "simple-history-plus"
			$version,
			SIMPLE_HISTORY_LICENCES_API_URL
		);
	}

}
