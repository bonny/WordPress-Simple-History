<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;

/**
 * Base class for dropins.
 */
abstract class Dropin {
	/** @var Simple_History */
	protected Simple_History $simple_history;

	/**
	 * @param Simple_History $simple_history
	 */
	public function __construct( $simple_history ) {
		$this->simple_history = $simple_history;
	}

	/**
	 * Fired when Simple History has loaded the dropin.
	 *
	 * @return void
	 */
	public function loaded() {
		// ...
	}
}
