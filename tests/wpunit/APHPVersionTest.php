<?php

class APHPVersionTest extends \Codeception\TestCase\WPTestCase {
	// Output PHP version so we can see what version is used in the tests.
	// Based on solution:
	// https://stackoverflow.com/questions/7493102/how-to-output-in-cli-during-execution-of-php-unit-tests
	public function test_a_php_version() {
		// Output PHP version.
		fwrite(STDERR, "\nLOG: phpversion(): " . phpversion());

		// Output MySQL/MariaDB version.
		global $wpdb;
		if ( empty( $wpdb->use_mysqli ) ) {
			// $mysqlVersion = mysql_get_server_info();
		} else {
			$mysqlVersion = mysqli_get_server_info( $wpdb->dbh );
		}
		fwrite(STDERR, "\nLOG: MySQL/MariaDB version: " . $mysqlVersion . "\n");
	}
}
