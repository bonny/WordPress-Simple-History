<?php

use Simple_History\Helpers;
use Simple_History\Loggers\Logger;

class LoggerTest extends \Codeception\TestCase\WPTestCase {
	private Logger $testedLoggerClass;

    public function setUp(): void
    {
		parent::setUp();

        // $this->testedLoggerClass = new class extends Logger {
		// 	protected $slug = 'Testlogger';

		// 	public function get_info() {
		// 		return [
		// 			'name'  => 'Testlogger'
		// 		];
		// 	}
        // };
    }
	function test_append_remote_addr_to_context() {
		$context = [];
		// $context = $this->testedLoggerClass->append_remote_addr_to_context();
		//exit;
		// $this->invokeMethod( $this->testedLoggerClass, 'append_remote_addr_to_context', [ &$context ] );
		// TODO: Test private functions.
	}
}
