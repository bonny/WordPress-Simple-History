<?php

use Simple_History\Log_Query;

class APHPVersionTest extends \Codeception\TestCase\WPTestCase {
	// Output PHP version so we can see what version is used in the tests.
	// Based on solution:
	// https://stackoverflow.com/questions/7493102/how-to-output-in-cli-during-execution-of-php-unit-tests
	public function test_a_php_version() {
		// Output PHP version.
		fwrite( STDERR, "\nLOG: phpversion(): " . phpversion() );

		fwrite( STDERR, "\nLOG: database: " . $this->get_database_version() . "\n" );
	}

	/**
	 * Database engine and version, for the log line above.
	 *
	 * Not skipped on SQLite: knowing which engine a run used is the whole point
	 * of this test, and that matters most when it is the unusual one. $wpdb->dbh
	 * is a WP_SQLite_Translator there rather than a mysqli handle, so the server
	 * version has to be read a different way.
	 *
	 * @return string
	 */
	private function get_database_version() {
		global $wpdb;

		if ( Log_Query::get_db_engine() === 'sqlite' ) {
			return 'SQLite ' . $wpdb->get_var( 'SELECT sqlite_version()' );
		}

		if ( empty( $wpdb->use_mysqli ) ) {
			return 'MySQL/MariaDB (version unavailable without mysqli)';
		}

		return 'MySQL/MariaDB ' . mysqli_get_server_info( $wpdb->dbh );
	}
}
