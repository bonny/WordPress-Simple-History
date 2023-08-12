<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;

/**
 * Class that setups logging using WP hooks.
 */
abstract class Service {
	/** @var Simple_History */
	protected Simple_History $simple_history;

	// Plugins and dropins are loaded using the "after_setup_theme" filter so
	// themes can use filters to modify the loading of them.
	// The drawback with this is that for example logouts done when plugins like
	// iThemes Security is installed is not logged, because those plugins fire wp_logout()
	// using filter "plugins_loaded", i.e. before simple history has loaded its filters.
	public function __construct( Simple_History $simple_history ) {
		$this->simple_history = $simple_history;
	}

	abstract public function loaded();
}
