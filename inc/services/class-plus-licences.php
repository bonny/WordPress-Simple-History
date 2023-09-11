<?php

namespace Simple_History\Services;

use Simple_History\Plugin_Updater;

class Plus_Licences extends Service {

	public function loaded() {
		$this->init_updater();
	}

	private function init_updater() {
		/**
		 * Instanciate the updater class for each Plus plugin.
		 */
		new Plugin_Updater(
			plugin_basename( SIMPLE_HISTORY_PLUS_FILE ), // "simple-history-plus/index.php"
			plugin_basename( SIMPLE_HISTORY_PLUS_DIR ), // "simple-history-plus"
			SIMPLE_HISTORY_PLUS_PLUGIN_VERSION,
			SIMPLE_HISTORY_PLUS_PLUGIN_API_URL
		);
	}

}
