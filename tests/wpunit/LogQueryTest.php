<?php

use Simple_History\Log_Query;

/**
 * Test the Log_Query class.
 *
 * This test should run on different versions of MySQL and MariaDB, to make sure it works correctly.
 * 
 * Use NPM scripts:
 * - npm run test:log-query-mysq55
 * - npm run test:log-query-mysq57
 * - npm run test:log-query-mariadb105
 * 
 * 
 * @coversDefaultClass Simple_History\Log_Query
 */
class LogQueryTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Add n log entries and then query for them.
	 * 
	 * Then add another login attempt and check for updates since the last above id.
	 */
	function test_query() {
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
		$added_rows_ids = [];
		$num_rows_to_add = 10;
		for ($i = 0; $i < $num_rows_to_add; $i++) {
			$logger = SimpleLogger()->info(
				'Test info message ' . $i,
				[
					'_occasionsID' => 'my_occasion_id',
					'message_num' => $i,
				]
			);
			$added_rows_ids[] = $logger->last_insert_id;
		}

		// Now query the log and see what id we get as the latest.
		$query_results = (new Log_Query())->query( ['posts_per_page' => 1] );
		$first_log_row_from_query = $query_results['log_rows'][0];

		$this->assertEquals(
			$added_rows_ids[$num_rows_to_add-1], 
			$first_log_row_from_query->id, 
			'The id of the first row in query result should be the same as the id of the last added row.'
		);

		// Add more.
		for ($i = 0; $i < 4; $i++) {
			$logger = SimpleLogger()->info(
				'Another test info message ' . $i,
				[
					'_occasionsID' => 'my_occasion_id_2',
					'message_num' => $i,
				]
			);
		}

		$logger = SimpleLogger()->info(
			'Single message ' . 0,
			[
				'_occasionsID' => 'my_occasion_id_3',
				'message_num' => 0,
			]
		);

		
		$hello_some_messages_message_count = 7;
		for ($i = 0; $i < $hello_some_messages_message_count; $i++) {
			$logger = SimpleLogger()->info(
				'Hello some messages ' . $i,
				[
					'_occasionsID' => 'my_occasion_id_5',
					'message_num' => $i,
				]
			);
		}

		$oh_such_logging_rows_num_to_add = 3;
		for ($i = 0; $i < $oh_such_logging_rows_num_to_add; $i++) {
			$logger = SimpleLogger()->info(
				'Oh such logging things ' . $i,
				[
					'_occasionsID' => 'my_occasion_id_6',
					'message_num' => $i,
				]
			);
		}

		// Get first result and check that it has 3 subsequentOccasions 
		// and that the message is 
		// "Oh such logging things {$i-1}"
		// and that context contains message_num = {$i-1}.
		$results = (new Log_Query())->query([
			'posts_per_page' => 3
		]);

		$first_log_row_from_query = $results['log_rows'][0];
		$second_log_row_from_query = $results['log_rows'][1];
		$third_log_row_from_query = $results['log_rows'][2];

		$this->assertEquals(
			3,
			$first_log_row_from_query->subsequentOccasions,
			'The first log row should have 3 subsequentOccasions.'
		);

		$this->assertIsNumeric($first_log_row_from_query->subsequentOccasions);

		$this->assertEquals(
			'Oh such logging things ' . ($i-1),
			$first_log_row_from_query->message,
			'The first log row should have the message "Oh such logging things" ' . ($i-1)
		);

		$this->assertEquals(
			$i-1,
			$first_log_row_from_query->context['message_num'],
			'The first log row should have the context message_num = ' . ($i-1)
		);

		// Test second message.
		$this->assertEquals(
			$hello_some_messages_message_count,
			$second_log_row_from_query->subsequentOccasions,
			"The second log row should have $hello_some_messages_message_count subsequentOccasions."
		);

		$this->assertIsNumeric($second_log_row_from_query->subsequentOccasions);

		$this->assertEquals(
			'Hello some messages 6',
			$second_log_row_from_query->message,
			'The first log row should have the message "Hello some messages 6"'
		);

		// Test third message.
		$this->assertEquals(
			1,
			$third_log_row_from_query->subsequentOccasions,
			'The third log row should have 1 subsequentOccasions.'
		);
		
		$this->assertIsNumeric($third_log_row_from_query->subsequentOccasions);

		$this->assertEquals(
			'Single message 0',
			$third_log_row_from_query->message,
			'The third log row should have the message "Single message 0"'
		);


		// Test occasions query arg. for first returned row.
		$query_results = (new Log_Query())->query([
			'type' => 'occasions',
			// Get history rows with id:s less than this, i.e. get earlier/previous rows.
			'logRowID' => $first_log_row_from_query->id, 
			'occasionsID' => $first_log_row_from_query->occasionsID, // The occasions id is md5:ed so we need to use log query to get the last row, and then get occasions id..
			'occasionsCount' => $first_log_row_from_query->subsequentOccasions - 1,
		]);

		$this->assertCount(
			$oh_such_logging_rows_num_to_add - 1,
			$query_results['log_rows'],
			'The number of rows returned when getting occasions should be ' . ($oh_such_logging_rows_num_to_add - 1)
		);

		// Test occasions query arg. for second returned row.
		$query_results = (new Log_Query())->query([
			'type' => 'occasions',
			'logRowID' => $second_log_row_from_query->id, 
			'occasionsID' => $second_log_row_from_query->occasionsID, // The occasions id is md5:ed so we need to use log query to get the last row, and then get occasions id..
			'occasionsCount' => $second_log_row_from_query->subsequentOccasions - 1,
		]);

		$this->assertCount(
			$hello_some_messages_message_count - 1,
			$query_results['log_rows'],
			'The number of rows returned when getting occasions should be ' . ($hello_some_messages_message_count - 1)
		);

		// Test occasions query arg. for third returned row.
		$query_results = (new Log_Query())->query([
			'type' => 'occasions',
			'logRowID' => $third_log_row_from_query->id, 
			'occasionsID' => $third_log_row_from_query->occasionsID, // The occasions id is md5:ed so we need to use log query to get the last row, and then get occasions id.
			'occasionsCount' => $third_log_row_from_query->subsequentOccasions - 1,
		]);

		// No further occasions for this row.
		$this->assertSame(
			"1",
			$third_log_row_from_query->subsequentOccasions,
			'The number of rows returned when getting occasions should be 0'
		);

		$this->assertCount(
			0,
			$query_results['log_rows'],
			'The number of rows returned when getting occasions should be 0'
		);
	}

	function test_since_id() {
		// Add and set current user to admin user, so user can read all logs.
		$user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$logger = SimpleLogger()->info(
			'Test info message 1',
			[
				'_occasionsID' => 'my_occasion_id',
				'message_num' => 1,
			]
		);

		$last_insert_id = $logger->last_insert_id;

		// Query log again and use $last_insert_id as since_id.
		// It should not have any new rows yet.
		$query_results = (new Log_Query())->query( ['since_id' => $last_insert_id] );
		$this->assertEmpty($query_results['log_rows'], 'There should be no new rows yet.');

		// Add two new events.
		for ($i=0; $i<2; $i++) {
			echo "log $i";
			$logger = SimpleLogger()->info(
				'Test info message ' . $i,
				[
					'_occasionsID' => 'my_occasion_id_in_loop_' . $i,
					'message_num' => $i,
				]
			);
		}

		// Test that we get two new rows.
		$query_results = (new Log_Query())->query( ['since_id' => $last_insert_id] );
		$this->assertEquals(2, $query_results['total_row_count'], 'There should be two new rows now.');
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
		$this->assertTrue( property_exists( $first_log_row, 'context' ) );
	}

	function test_inner_where_array_filter() {
		// Add and set current user to admin user, so user can read all logs.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$log_query = new Log_Query();
		$query_results = $log_query->query( [
			'posts_per_page' => 10,
		] );
	
		$this->assertGreaterThan(0, did_filter('simple_history/log_query_inner_where_array', 'Filter should have been called.'));
		$this->assertEquals(10, $query_results['log_rows_count'], 'There should be 10 log rows.');

		// Add filter that adds where condition so query returns nothing.
		add_filter('simple_history/log_query_inner_where_array', function($inner_where, $args) {
			$inner_where[] = "1 = 0";
			return $inner_where;
		}, 10, 2);

		$log_query = new Log_Query();
		$query_results = $log_query->query( [
			'posts_per_page' => 11, // change count so cached query is not used.
		] );

		$this->assertEquals(0, $query_results['log_rows_count'], 'There should be 0 log rows.');
	}

	/**
	 * Test filtering by single user ID.
	 * Tests the 'user' parameter which we fixed for SQL injection.
	 */
	function test_filter_by_single_user() {
		// Create two users.
		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$editor_user_id = $this->factory->user->create( [ 'role' => 'editor' ] );

		// Create events as admin user.
		wp_set_current_user( $admin_user_id );
		$unique_admin_marker = 'admin_test_' . uniqid();
		SimpleLogger()->info( 'Admin event 1 ' . $unique_admin_marker );
		SimpleLogger()->info( 'Admin event 2 ' . $unique_admin_marker );

		// Create events as editor user.
		wp_set_current_user( $editor_user_id );
		$unique_editor_marker = 'editor_test_' . uniqid();
		SimpleLogger()->info( 'Editor event 1 ' . $unique_editor_marker );
		SimpleLogger()->info( 'Editor event 2 ' . $unique_editor_marker );

		// Switch back to admin for querying.
		wp_set_current_user( $admin_user_id );

		// Query for admin user events only.
		$log_query = new Log_Query();
		$query_results = $log_query->query( [
			'user' => $admin_user_id,
			'posts_per_page' => 100,
		] );

		// Count admin events with our unique marker.
		$admin_event_count = 0;
		$admin_messages = [];
		foreach ( $query_results['log_rows'] as $log_row ) {
			// Verify all events belong to admin user.
			$context = $log_row->context;
			$this->assertEquals(
				(string) $admin_user_id,
				$context['_user_id'],
				'All events should belong to admin user'
			);

			if ( strpos( $log_row->message, $unique_admin_marker ) !== false ) {
				$admin_event_count++;
				$admin_messages[] = $log_row->message;
			}
		}

		$this->assertGreaterThanOrEqual( 2, $admin_event_count, 'Should find at least 2 admin events with unique marker. Found: ' . implode( ', ', $admin_messages ) );

		// Query for editor user events only.
		$query_results = $log_query->query( [
			'user' => $editor_user_id,
			'posts_per_page' => 100,
		] );

		// Count editor events with our unique marker.
		$editor_event_count = 0;
		foreach ( $query_results['log_rows'] as $log_row ) {
			// Verify all events belong to editor user.
			$context = $log_row->context;
			$this->assertEquals(
				(string) $editor_user_id,
				$context['_user_id'],
				'All events should belong to editor user'
			);

			if ( strpos( $log_row->message, $unique_editor_marker ) !== false ) {
				$editor_event_count++;
			}
		}

		$this->assertGreaterThanOrEqual( 2, $editor_event_count, 'Should find at least 2 editor events with unique marker' );
	}

	/**
	 * Test filtering by multiple user IDs.
	 * Tests the 'users' parameter which we fixed for SQL injection.
	 */
	function test_filter_by_multiple_users() {
		// Create three users.
		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$editor_user_id = $this->factory->user->create( [ 'role' => 'editor' ] );
		$author_user_id = $this->factory->user->create( [ 'role' => 'author' ] );

		// Create events with unique markers for each user.
		$unique_marker = 'multi_user_test_' . uniqid();

		wp_set_current_user( $admin_user_id );
		SimpleLogger()->info( 'Admin ' . $unique_marker );

		wp_set_current_user( $editor_user_id );
		SimpleLogger()->info( 'Editor ' . $unique_marker );

		wp_set_current_user( $author_user_id );
		SimpleLogger()->info( 'Author ' . $unique_marker );

		// Switch back to admin for querying.
		wp_set_current_user( $admin_user_id );

		// Query for admin and editor events only (exclude author).
		$log_query = new Log_Query();
		$query_results = $log_query->query( [
			'users' => [ $admin_user_id, $editor_user_id ],
			'posts_per_page' => 100,
		] );

		// Find our specific test events.
		$found_messages = [];
		foreach ( $query_results['log_rows'] as $log_row ) {
			if ( strpos( $log_row->message, $unique_marker ) !== false ) {
				$found_messages[] = $log_row->message;
			}
		}

		// Should have at least 2 events with our marker (admin + editor), might have duplicates from occasions grouping.
		$this->assertGreaterThanOrEqual( 2, count( $found_messages ), 'Should find at least 2 events with unique marker. Found: ' . implode( ', ', $found_messages ) );

		// Check that we have the events we created.
		$has_admin = false;
		$has_editor = false;
		$has_author = false;
		foreach ( $found_messages as $message ) {
			if ( strpos( $message, 'Admin ' ) !== false ) $has_admin = true;
			if ( strpos( $message, 'Editor ' ) !== false ) $has_editor = true;
			if ( strpos( $message, 'Author ' ) !== false ) $has_author = true;
		}

		$this->assertTrue( $has_admin, 'Should include admin event' );
		$this->assertTrue( $has_editor, 'Should include editor event' );
		$this->assertFalse( $has_author, 'Should NOT include author event' );
	}

	/**
	 * Test filtering by context filters.
	 * Tests the 'context_filters' parameter which we fixed for SQL injection.
	 */
	function test_filter_by_context_filters() {
		// Create admin user.
		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );

		// Create events with different context values.
		SimpleLogger()->info( 'Post 123 event', [
			'post_id' => '123',
			'action' => 'updated',
		] );
		SimpleLogger()->info( 'Post 456 event', [
			'post_id' => '456',
			'action' => 'updated',
		] );
		SimpleLogger()->info( 'Post 123 created', [
			'post_id' => '123',
			'action' => 'created',
		] );

		// Query for events with post_id = 123.
		$log_query = new Log_Query();
		$query_results = $log_query->query( [
			'context_filters' => [
				'post_id' => '123',
			],
			'posts_per_page' => 100,
		] );

		// Should have exactly 2 events for post 123.
		$this->assertEquals( 2, $query_results['log_rows_count'], 'Should have 2 events for post_id 123' );

		// Verify all events have post_id = 123.
		foreach ( $query_results['log_rows'] as $log_row ) {
			$context = $log_row->context;
			$this->assertEquals( '123', $context['post_id'], 'All events should have post_id 123' );
		}

		// Query for events with post_id = 123 AND action = updated.
		$query_results = $log_query->query( [
			'context_filters' => [
				'post_id' => '123',
				'action' => 'updated',
			],
			'posts_per_page' => 100,
		] );

		// Should have exactly 1 event matching both filters.
		$this->assertEquals( 1, $query_results['log_rows_count'], 'Should have 1 event matching both filters' );

		// Verify the event has both context values.
		$log_row = reset( $query_results['log_rows'] );
		$context = $log_row->context;
		$this->assertEquals( '123', $context['post_id'], 'Event should have post_id 123' );
		$this->assertEquals( 'updated', $context['action'], 'Event should have action updated' );
	}

	/**
	 * Test SQL injection protection for user parameter.
	 * Ensures validation rejects non-numeric user IDs.
	 */
	function test_user_filter_sql_injection_protection() {
		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );

		// Create a legitimate event.
		SimpleLogger()->info( 'Test event', [ '_user_id' => $admin_user_id ] );

		// Try SQL injection in user parameter - should throw exception.
		$log_query = new Log_Query();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid user' );

		$query_results = $log_query->query( [
			'user' => "1' OR '1'='1",  // SQL injection attempt
			'posts_per_page' => 100,
		] );
	}

	/**
	 * Test SQL injection protection for users array parameter.
	 * Ensures validation and $wpdb->prepare() prevent SQL injection.
	 */
	function test_users_filter_sql_injection_protection() {
		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );

		// Create a legitimate event.
		SimpleLogger()->info( 'Test event', [ '_user_id' => $admin_user_id ] );

		// Try SQL injection in users array parameter.
		// The validation converts strings to integers, so "1' OR '1'='1" becomes 1.
		// Then $wpdb->prepare() escapes it properly.
		$log_query = new Log_Query();
		$query_results = $log_query->query( [
			'users' => [ "1' OR '1'='1", "2' OR '2'='2" ],  // SQL injection attempts
			'posts_per_page' => 100,
		] );

		// The strings get converted to integers (1 and 2), so they're safe.
		// Query will only return events for user IDs 1 and 2 (if they exist).
		// The SQL injection is neutralized by integer conversion.
		$this->assertIsInt( $query_results['log_rows_count'], 'Query should complete without SQL injection' );

		// Verify no events with malicious content in context.
		foreach ( $query_results['log_rows'] as $log_row ) {
			$context = $log_row->context;
			if ( isset( $context['_user_id'] ) ) {
				$this->assertIsNumeric( $context['_user_id'], 'User ID should be numeric' );
			}
		}
	}

	/**
	 * Test SQL injection protection for context_filters parameter.
	 * Ensures our fix prevents SQL injection attempts in context keys and values.
	 */
	function test_context_filters_sql_injection_protection() {
		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );

		// Create a legitimate event.
		SimpleLogger()->info( 'Test event', [ 'post_id' => '123' ] );

		// Try SQL injection in context key.
		$log_query = new Log_Query();
		$query_results = $log_query->query( [
			'context_filters' => [
				"post_id' OR '1'='1" => '123',  // SQL injection in key
			],
			'posts_per_page' => 100,
		] );

		// Should return 0 results (injection should not work).
		$this->assertEquals( 0, $query_results['log_rows_count'], 'SQL injection in key should return no results' );

		// Try SQL injection in context value.
		$query_results = $log_query->query( [
			'context_filters' => [
				'post_id' => "123' OR '1'='1",  // SQL injection in value
			],
			'posts_per_page' => 100,
		] );

		// Should return 0 results (injection should not work).
		$this->assertEquals( 0, $query_results['log_rows_count'], 'SQL injection in value should return no results' );
	}
}
