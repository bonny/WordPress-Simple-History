<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;
/**
 * Base class for dropins.
 */
abstract class Dropin {
	protected Simple_History $simple_history;

	/**
	 * @param Simple_History $sh
	 */
	public function __construct( $sh ) {
		$this->simple_history = $sh;
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
