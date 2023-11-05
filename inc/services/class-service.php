<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Class for core services to extend,
 * i.e. services that are loaded early and are required for Simple History to work.
 */
abstract class Service {
	/** @var Simple_History */
	protected Simple_History $simple_history;

	/**
	 * Plugins and dropins are loaded using the "after_setup_theme" filter so
	 * themes can use filters to modify the loading of them.
	 * The drawback with this is that for example logouts done when plugins like
	 * iThemes Security is installed is not logged, because those plugins fire wp_logout()
	 * using filter "plugins_loaded", i.e. before simple history has loaded its filters.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	public function __construct( Simple_History $simple_history ) {
		$this->simple_history = $simple_history;
	}

	/**
	 * Get the slug for the service,
	 * i.e. the unqualified class name.
	 *
	 * @return string
	 */
	public function get_slug() {
		return Helpers::get_class_short_name( $this );
	}

	/**
	 * Called when service is loaded.
	 */
	abstract public function loaded();
}
