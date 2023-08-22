<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use function Simple_History\tests\get_latest_row;

/**
 * Run with
 * `$ docker-compose run --rm php-cli vendor/bin/codecept run wpunit OldLoggerTest`
 */
class OldLoggerTest extends \Codeception\TestCase\WPTestCase {

	// New logger class that is namespaced.
	public function test_that_new_logger_class_exists() {
		$this->assertTrue(class_exists('Simple_History\Loggers\Simple_Logger'), 'New Simple_Logger class exists');
	}

	// Old logger class without namespace.
	public function test_that_old_logger_class_exists() {
		$this->assertTrue(class_exists('SimpleLogger'), 'Old SimpleLogger class exists');
	}

	public function test_that_old_logger_exists() {
		$this->assertTrue(class_exists('Example_Logger'), 'Old SimpleLogger class exists');
	}

	public function test_old_logger_logging() {
		// Trigger 404_template filter to trigger logger.
		apply_filters( "404_template", 'template', 'type', array() );

		$this->assertEquals(
			array(
				'logger' => 'FourOhFourLogger',
				'level' => 'warning',
				'message' => 'Got a 404-page when trying to visit "{request_uri}"',
				'initiator' => 'web_user',
			),
			get_latest_row()
		);

	}

	public function test_old_log_query_class() {
		// Be admin user to be able to read logs.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		SimpleLogger()->info('This is an info message');
		
		$log_query = new SimpleHistoryLogQuery();
		$query_results = $log_query->query( [
			'posts_per_page' => 1,
		] );

		$expected_object = new stdClass();
		$expected_object->logger = 'SimpleLogger';
		$expected_object->level = 'info';
		$expected_object->message = 'This is an info message';
		$expected_object->context_message_key = null;
		$expected_object->initiator = 'wp_user';

		$actual = $query_results['log_rows'][0];
		unset($actual->id, $actual->date, $actual->occasionsID, $actual->subsequentOccasions, $actual->rep, $actual->repeated, $actual->occasionsIDType, $actual->context);

		$this->assertEquals($expected_object, $actual);
	}

	// Test that loggers can access deprecated property "slug".
	public function test_that_logger_can_access_slug() {
		$simple_history = Simple_History::get_instance();		
		$logger = $simple_history->get_instantiated_logger_by_slug('SimpleHistoryLogger');
		$this->assertEquals('SimpleHistoryLogger', $logger->slug);
	}
}
