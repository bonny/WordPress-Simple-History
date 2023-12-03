<?php

use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use Simple_History\Simple_History;
use Simple_History\Log_Query;

class LogQueryTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Add n log entries and then query for them.
	 * 
	 * Then add another login attempt and check for updates since the last above id.
	 * Currently there is a bug in MariaDB that says "1 new event" even if no new event exists.
	 * 
	 * Run test on PHP 7.4 and MariaDB 10.5:
	 * docker compose run --rm php-cli vendor/bin/codecept run wpunit:test_query
	 * 
	 * Run test on PHP 8.1 and MariaDB 10.5:
	 * PHP_CLI_VERSION=81 PHP_VERSION=8.1 docker compose run --rm php-cli vendor/bin/codecept run wpunit:test_query
	 * 
	 * Run test on PHP 8.1 and MySQL 5.5 (should be good):
	 * PHP_CLI_VERSION=81 PHP_VERSION=8.1 DB_IMAGE=biarms/mysql:5.5 DB_DATA_DIR=./data/mysql-5.5 docker compose run --rm php-cli vendor/bin/codecept run wpunit:test_query
	 * 
	 * Run test on PHP 8.1 and MySQL 5.7 (should fail):
	 * PHP_CLI_VERSION=81 PHP_VERSION=8.1 DB_IMAGE=biarms/mysql:5.7 DB_DATA_DIR=./data/mysql-5.7 docker compose run --rm php-cli vendor/bin/codecept run wpunit:test_query
	 */
	function test_query() {
		// I know this fails.
		$this->markTestIncomplete('This test will fail in Mysql >5.5 and MariaDB until SQL bug is fixed.');

		// Add and set current user to admin user, so user can read all logs.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		/*
		action: SimpleHistoryNewRowsNotifier
		apiArgs[since_id]: 1279
		apiArgs[dates]: lastdays:30
		response: 
			"num_new_rows": 1,
        	"num_mysql_queries": 50, (what? why so many??)
		*/
		$added_rows_id = [];
		$num_rows_to_add = 10;
		for ($i = 0; $i < $num_rows_to_add; $i++) {
			$logger = SimpleLogger()->info(
				'Test info message ' . $i,
				[
					'_occasionsID' => 'my_occasion_id',
					'message_num' => $i,
				]
			);
			// sh_d('$logger', $logger->last_insert_context, $logger->last_insert_data, $logger->last_insert_id);
			$added_rows_id[] = $logger->last_insert_id;
		}

		// Now query the log and see what id we get as the latest.
		$log_query_args = array(
			'posts_per_page' => 1,
		);
		
		$log_query = new Log_Query();
		$query_results = $log_query->query( $log_query_args );
		$first_log_row = $query_results['log_rows'][0];

		// On MariaDB $first_log_row->id is the same as the value in $added_rows_id[0] (the first added row)
		// but it should be the id from the last added row, i.e. the value in $added_rows_id[9].
		sh_d('$first_log_row id', $first_log_row->id);
		sh_d('$added_rows_id[0]', $added_rows_id[0]);
		sh_d('$added_rows_id[max]', $added_rows_id[$num_rows_to_add-1]);

		// $this->markTestIncomplete('This test will fail in MariaDB until bug is fixed.');
		$this->assertEquals(
			$added_rows_id[$num_rows_to_add-1], 
			$first_log_row->id, 
			'The id of the first row should be the same as the id of the last added row.'
		);
	}

	 /**
	  * Test that the Log_Query returns the expected things.
	  */
	function test_log_query() {
		// Add and set current user to admin user, so user can read all logs.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$log_query_args = array(
			'posts_per_page' => 1,
		);

		$log_query = new Log_Query();
		$query_results = $log_query->query( $log_query_args );

		// The latest row should be the user we create above
		$this->assertArrayHasKey( 'total_row_count', $query_results );
		$this->assertArrayHasKey( 'pages_count', $query_results );
		$this->assertArrayHasKey( 'page_current', $query_results );
		$this->assertArrayHasKey( 'page_rows_from', $query_results );
		$this->assertArrayHasKey( 'page_rows_to', $query_results );
		$this->assertArrayHasKey( 'max_id', $query_results );
		$this->assertArrayHasKey( 'min_id', $query_results );
		$this->assertArrayHasKey( 'log_rows_count', $query_results );
		$this->assertArrayHasKey( 'log_rows', $query_results );

		$this->assertCount( 1, $query_results['log_rows'] );

		// $this->assertFalse(property_exists($myObject, $nonExistentPropertyName));
		// Can not use ->assertObjectHasAttribute because it's deprecated and wp_browser does not have the
		// recommendeded replacement ->assertObjectHasProperty (yet).
		$first_log_row = $query_results['log_rows'][0];

		$this->assertIsObject($first_log_row);

		$this->assertTrue( property_exists( $first_log_row, 'id' ) );
		$this->assertTrue( property_exists( $first_log_row, 'logger' ) );
		$this->assertTrue( property_exists( $first_log_row, 'level' ) );
		$this->assertTrue( property_exists( $first_log_row, 'date' ) );
		$this->assertTrue( property_exists( $first_log_row, 'message' ) );
		$this->assertTrue( property_exists( $first_log_row, 'initiator' ) );
		$this->assertTrue( property_exists( $first_log_row, 'occasionsID' ) );
		$this->assertTrue( property_exists( $first_log_row, 'subsequentOccasions' ) );
		$this->assertTrue( property_exists( $first_log_row, 'rep' ) );
		$this->assertTrue( property_exists( $first_log_row, 'repeated' ) );
		$this->assertTrue( property_exists( $first_log_row, 'occasionsIDType' ) );
		$this->assertTrue( property_exists( $first_log_row, 'context' ) );
	}
}
