<?php

require_once 'functions.php';

use function SimpleHistory\tests\get_latest_row;
use function SimpleHistory\tests\get_latest_context;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable Squiz.Scope.MethodScope.Missing
class FiltersTest extends WP_UnitTestCase {

	public static function setUpBeforeClass() {
	}

	function test_filters() {
		apply_filters( 'simple_history_log', 'This is a logged message' );
		$latest_row = get_latest_row();

		$this->assertEquals(
			array(
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'message' => 'This is a logged message',
				'initiator' => 'other',
			),
			$latest_row
		);

		// Or with some context and with log level debug:
		apply_filters(
			'simple_history_log',
			'My message about something',
			array(
				'debugThing' => 'debugThingValue',
				'anotherThing' => 'anotherThingValue',
			),
			'debug'
		);

		$latest_row = get_latest_row();

		$this->assertEquals(
			array(
				'logger' => 'SimpleLogger',
				'level' => 'debug',
				'message' => 'My message about something',
				'initiator' => 'other',
			),
			$latest_row
		);

		$context = get_latest_context();
		$this->assertEquals(
			array(
				array(
					'key' => 'anotherThing',
					'value' => 'anotherThingValue',
				),
				array(
					'key' => 'debugThing',
					'value' => 'debugThingValue',
				),
				array(
					'key' => '_server_remote_addr',
					'value' => '127.0.0.0',
				),
			),
			$context
		);

		// Or just debug a message quickly
		apply_filters( 'simple_history_log_debug', 'My debug message' );

		$latest_row = get_latest_row();

		$this->assertEquals(
			array(
				'logger' => 'SimpleLogger',
				'level' => 'debug',
				'message' => 'My debug message',
				'initiator' => 'other',
			),
			$latest_row
		);
	}
}
