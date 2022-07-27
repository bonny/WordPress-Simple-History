<?php

namespace SimpleHistory\Dropins;

use SimpleHistory\SimpleHistory;
/**
 * Base class for dropins.
 */
abstract class Dropin {
	protected SimpleHistory $simple_history;

	/**
	 * @param SimpleHistory $sh
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
