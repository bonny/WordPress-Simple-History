<?php

/*
Todo

In example file we use class SimpleLoggerLogInitiators that does not exist any more:
'_initiator' => SimpleLoggerLogInitiators::WEB_USER,

*/

require_once 'functions.php';
// require_once __DIR__ . '/../class-example-404-logger.php';

use Simple_History\Simple_History;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Run with
 * `$ docker-compose run --rm php-cli vendor/bin/codecept run wpunit OldLoggerTest`
 */
class OldLoggerTest extends \Codeception\TestCase\WPTestCase {

	public function test_old_logger() {
		$simple_history = Simple_History::get_instance();
		$simple_history->register_logger( 'FourOhFourLogger' );
		$simple_history->register_logger( 'Bananas' );
	}

	// New logger class that is namespaced.
	public function test_that_new_logger_class_exists() {
		$this->assertTrue(class_exists('Simple_History\Loggers\SimpleLogger'), 'New SimpleLogger class exists');
	}

	// Old logger class without namespace.
	public function test_that_old_logger_class_exists() {
		$this->assertTrue(class_exists('SimpleLogger'), 'Old SimpleLogger class exists');
	}

	public function test_that_404_logger_class_exists() {
		$this->assertTrue(class_exists('FourOhFourLogger'), 'Old SimpleLogger class exists');
	}
}

