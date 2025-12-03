<?php

/**
 * Tests for negative filter functionality.
 *
 * Tests the exclude_* parameters in Log_Query and REST API.
 */
class NegativeFiltersTest extends \Codeception\TestCase\WPTestCase {
	private $events_endpoint = '/simple-history/v1/events';

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create admin user for authenticated tests.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Create test events with different characteristics.
		$this->create_test_events();
	}

	/**
	 * Create test events for filtering.
	 */
	private function create_test_events() {
		// Create events with different log levels.
		\SimpleLogger()->debug( 'Debug message for testing' );
		\SimpleLogger()->info( 'Info message for testing' );
		\SimpleLogger()->warning( 'Warning message for testing' );
		\SimpleLogger()->error( 'Error message for testing' );

		// Create events with specific content.
		\SimpleLogger()->info( 'Event containing cron keyword' );
		\SimpleLogger()->info( 'Event containing plugin keyword' );
		\SimpleLogger()->info( 'Regular event without keywords' );
	}

	/**
	 * Test exclude_loglevels parameter filters out debug events.
	 */
	public function test_exclude_loglevels_via_log_query() {
		$log_query = new \Simple_History\Log_Query();

		// Query excluding debug level.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'exclude_loglevels' => [ 'debug' ],
			]
		);

		$log_rows = $results['log_rows'];

		// Verify no debug events in results.
		foreach ( $log_rows as $row ) {
			$this->assertNotEquals(
				'debug',
				$row->level,
				'Results should not contain debug level events'
			);
		}
	}

	/**
	 * Test exclude_loglevels with multiple levels.
	 */
	public function test_exclude_multiple_loglevels() {
		$log_query = new \Simple_History\Log_Query();

		// Exclude both debug and info levels.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'exclude_loglevels' => [ 'debug', 'info' ],
			]
		);

		$log_rows = $results['log_rows'];

		// Verify no debug or info events.
		foreach ( $log_rows as $row ) {
			$this->assertNotContains(
				$row->level,
				[ 'debug', 'info' ],
				'Results should not contain debug or info events'
			);
		}
	}

	/**
	 * Test exclude_search parameter filters out events with specific text.
	 */
	public function test_exclude_search_via_log_query() {
		$log_query = new \Simple_History\Log_Query();

		// Exclude events containing "cron".
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'exclude_search' => 'cron',
			]
		);

		$log_rows = $results['log_rows'];

		// Verify no events contain "cron".
		foreach ( $log_rows as $row ) {
			$this->assertStringNotContainsStringIgnoringCase(
				'cron',
				$row->message,
				'Results should not contain events with "cron" in message'
			);
		}
	}

	/**
	 * Test exclude_search with multiple words.
	 */
	public function test_exclude_search_multiple_words() {
		$log_query = new \Simple_History\Log_Query();

		// Exclude events containing both "cron" AND "plugin".
		// (Search uses AND logic - all words must match to be excluded).
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'exclude_search' => 'cron plugin',
			]
		);

		$log_rows = $results['log_rows'];

		// Verify no events contain both words.
		foreach ( $log_rows as $row ) {
			$has_cron = stripos( $row->message, 'cron' ) !== false;
			$has_plugin = stripos( $row->message, 'plugin' ) !== false;

			$this->assertFalse(
				$has_cron && $has_plugin,
				'Results should not contain events with both "cron" AND "plugin"'
			);
		}
	}

	/**
	 * Test combining positive and negative filters.
	 * Exclusion should take precedence.
	 */
	public function test_positive_and_negative_filters_combined() {
		$log_query = new \Simple_History\Log_Query();

		// Include info and debug, but exclude debug.
		// Expected: Only info events.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'loglevels' => [ 'info', 'debug' ],
				'exclude_loglevels' => [ 'debug' ],
			]
		);

		$log_rows = $results['log_rows'];

		// Verify only info events (debug should be excluded despite being in positive filter).
		foreach ( $log_rows as $row ) {
			$this->assertNotEquals(
				'debug',
				$row->level,
				'Debug should be excluded even though it was in positive filter'
			);
		}
	}

	/**
	 * Test exclude_loglevels via REST API.
	 */
	public function test_exclude_loglevels_via_rest_api() {
		$response = $this->dispatch_request(
			'GET',
			$this->events_endpoint,
			[
				'per_page' => 100,
				'exclude_loglevels' => [ 'debug' ],
			]
		);

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		// Verify no debug events in REST response.
		foreach ( $data as $event ) {
			$this->assertNotEquals(
				'debug',
				$event['level'] ?? '',
				'REST API should not return debug events'
			);
		}
	}

	/**
	 * Test exclude_search via REST API.
	 */
	public function test_exclude_search_via_rest_api() {
		$response = $this->dispatch_request(
			'GET',
			$this->events_endpoint,
			[
				'per_page' => 100,
				'exclude_search' => 'cron',
			]
		);

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		// Verify no events contain "cron".
		foreach ( $data as $event ) {
			$this->assertStringNotContainsStringIgnoringCase(
				'cron',
				$event['message'] ?? '',
				'REST API should not return events with "cron" in message'
			);
		}
	}

	/**
	 * Test exclude_loggers parameter.
	 */
	public function test_exclude_loggers() {
		$log_query = new \Simple_History\Log_Query();

		// Exclude SimpleLogger.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'exclude_loggers' => [ 'SimpleLogger' ],
			]
		);

		$log_rows = $results['log_rows'];

		// Verify no SimpleLogger events.
		foreach ( $log_rows as $row ) {
			$this->assertNotEquals(
				'SimpleLogger',
				$row->logger,
				'Results should not contain SimpleLogger events'
			);
		}
	}

	/**
	 * Test parameter validation - exclude_loglevels must be array or string.
	 */
	public function test_exclude_loglevels_validation() {
		$log_query = new \Simple_History\Log_Query();

		// Test with string (should be converted to array).
		$results = $log_query->query(
			[
				'posts_per_page' => 10,
				'exclude_loglevels' => 'debug,info',
			]
		);

		$this->assertIsArray( $results, 'Query should succeed with comma-separated string' );

		// Test with array (should work directly).
		$results = $log_query->query(
			[
				'posts_per_page' => 10,
				'exclude_loglevels' => [ 'debug', 'info' ],
			]
		);

		$this->assertIsArray( $results, 'Query should succeed with array' );
	}

	/**
	 * Test that invalid parameter types throw exceptions.
	 */
	public function test_invalid_exclude_parameter_throws_exception() {
		$log_query = new \Simple_History\Log_Query();

		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid exclude_loglevels' );

		// Pass invalid type (object instead of string/array).
		$log_query->query(
			[
				'exclude_loglevels' => new \stdClass(),
			]
		);
	}

	/**
	 * Test exclude_user parameter.
	 */
	public function test_exclude_user() {
		// Create events from different users.
		$user1 = $this->factory->user->create( [ 'role' => 'editor' ] );
		$user2 = $this->factory->user->create( [ 'role' => 'editor' ] );

		wp_set_current_user( $user1 );
		\SimpleLogger()->info( 'Event from user 1' );

		wp_set_current_user( $user2 );
		\SimpleLogger()->info( 'Event from user 2' );

		// Reset to admin.
		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$log_query = new \Simple_History\Log_Query();

		// Exclude user1 events.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'exclude_user' => $user1,
			]
		);

		$log_rows = $results['log_rows'];

		// Verify no events from user1.
		foreach ( $log_rows as $row ) {
			$context = $row->context ?? [];
			$user_id = $context['_user_id'] ?? null;

			$this->assertNotEquals(
				(string) $user1,
				$user_id,
				'Results should not contain events from excluded user'
			);
		}
	}

	/**
	 * Test exclude_initiator parameter.
	 */
	public function test_exclude_initiator() {
		// Create a WP-CLI event (simulated).
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'simple_history',
			[
				'date' => current_time( 'mysql' ),
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'message' => 'CLI event',
				'initiator' => 'wp_cli',
			]
		);

		$log_query = new \Simple_History\Log_Query();

		// Exclude wp_cli events.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'exclude_initiator' => 'wp_cli',
			]
		);

		$log_rows = $results['log_rows'];

		// Verify no wp_cli events.
		foreach ( $log_rows as $row ) {
			$this->assertNotEquals(
				'wp_cli',
				$row->initiator,
				'Results should not contain wp_cli events'
			);
		}
	}

	/**
	 * Test that malformed message filters are handled gracefully.
	 *
	 * Verifies that the array bounds check prevents errors when message
	 * filters don't contain the required colon separator.
	 */
	public function test_malformed_message_filters_handled_gracefully() {
		$log_query = new \Simple_History\Log_Query();

		// Create some test events that we can filter.
		\SimpleLogger()->info( 'Test event one' );
		\SimpleLogger()->info( 'Test event two' );
		\SimpleLogger()->info( 'Test event three' );

		// Test with positive messages filter containing malformed entries.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'messages'       => [
					'SimpleLogger:info_message',     // Valid format.
					'MalformedNoColon',              // Invalid - no colon separator.
					'SimpleUserLogger:user_login',   // Valid format.
					'',                              // Invalid - empty string.
					'AnotherMalformed',              // Invalid - no colon.
				],
			]
		);

		// Should not throw errors and should process query successfully.
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'log_rows', $results );
		// The malformed entries should be silently skipped.
		// We don't expect specific results since loggers may not have those messages,
		// but the query should complete without errors.
	}

	/**
	 * Test that malformed exclude_messages filters are handled gracefully.
	 *
	 * Verifies that the array bounds check works for exclusion filters too.
	 */
	public function test_malformed_exclude_message_filters_handled_gracefully() {
		$log_query = new \Simple_History\Log_Query();

		// Create some test events.
		\SimpleLogger()->info( 'Test event to exclude' );
		\SimpleLogger()->warning( 'Warning event' );
		\SimpleLogger()->error( 'Error event' );

		// Test with exclude_messages filter containing malformed entries.
		$results = $log_query->query(
			[
				'posts_per_page'  => 100,
				'exclude_messages' => [
					'SimpleLogger:info_message',        // Valid format.
					'MalformedExcludeNoColon',         // Invalid - no colon.
					'SimpleUserLogger:user_updated',   // Valid format.
					'',                                // Invalid - empty string.
					'AnotherMalformedExclude',         // Invalid - no colon.
				],
			]
		);

		// Should not throw errors and should process query successfully.
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'log_rows', $results );
		// The malformed entries should be silently skipped.
		// The query should complete successfully without PHP warnings/errors.
	}

	/**
	 * Test edge case: message filter with only colon.
	 */
	public function test_message_filter_with_only_colon() {
		$log_query = new \Simple_History\Log_Query();

		// Test with edge case of just a colon.
		$results = $log_query->query(
			[
				'posts_per_page' => 100,
				'messages'       => [
					':',                            // Edge case - colon only, will split to ['', ''].
					'SimpleLogger:valid_message',   // Valid.
				],
			]
		);

		// Should handle gracefully - the ':' will create empty logger and message,
		// which should be filtered out or handled without errors.
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'log_rows', $results );
	}

	/**
	 * Helper method to dispatch REST API requests.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param array  $params Query parameters.
	 * @return WP_REST_Response Response object.
	 */
	private function dispatch_request( $method, $route, $params = [] ) {
		$request = new \WP_REST_Request( $method, $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		return $response;
	}
}
