<?php

require_once 'functions.php';

use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

class OldLoggerTest extends \Codeception\TestCase\WPTestCase {

	// New logger class that is namespaced.
	public function test_that_new_logger_class_exists() {
		$this->assertTrue(class_exists('Simple_History\Loggers\SimpleLogger'), 'New SimpleLogger class exists');
	}

	// Old logger class without namespace.
	public function test_that_old_logger_class_exists() {
		$this->assertTrue(class_exists('SimpleLogger'), 'Old SimpleLogger class exists');
	}
}
