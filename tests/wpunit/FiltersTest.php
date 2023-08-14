<?php

require_once 'functions.php';

use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

class FiltersTest extends \Codeception\TestCase\WPTestCase {

	public function test_filters() {
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
					'value' => '127.0.0.x',
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

	function test_pause_resume_filters() {
		// This should be logged.
		apply_filters( 'simple_history_log', 'My log message 1' );
		$this->assertEquals(
			array(
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'message' => 'My log message 1',
				'initiator' => 'other',
			),
			get_latest_row(),
			'First log message should be logged'
		);
		
		// Pause logging.
		do_action('simple_history/pause');
		apply_filters( 'simple_history_log', 'My log message 2' );
		apply_filters( 'simple_history_log', 'My log message 3' );
		$this->assertEquals(
			array(
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'message' => 'My log message 1',
				'initiator' => 'other',
			),
			get_latest_row(),
			'Log messages should not be logged while paused'
		);
		
		// Unpause logging.
		do_action('simple_history/resume');
		apply_filters( 'simple_history_log', 'My log message 4' );
		apply_filters( 'simple_history_log', 'My log message 5' );

		$this->assertEquals(
			array(
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'message' => 'My log message 5',
				'initiator' => 'other',
			),
			get_latest_row(),
			'Log messages should be logged after unpausing'
		);
	}
}
