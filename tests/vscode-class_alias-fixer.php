<?php 

/**
 * File used to fix undefined method-warnings in Intelephense.
 * Solution from:
 * https://github.com/lucatume/wp-browser/issues/513#issuecomment-903322013
 */

namespace tad\WPBrowser\Compat\Codeception;

class Unit extends \tad\WPBrowser\Compat\Codeception\Version2\Unit{}
