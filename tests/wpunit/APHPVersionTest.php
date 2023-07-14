<?php

class APHPVersionTest extends \Codeception\TestCase\WPTestCase {
	// Output PHP version so we can see what version is used in the tests.
	// Based on solution found here:
	// https://stackoverflow.com/questions/7493102/how-to-output-in-cli-during-execution-of-php-unit-tests
	public function test_a_php_version() {
		fwrite(STDERR, "\nLOG: phpversion(): " . phpversion() . "\n");
	}
}
