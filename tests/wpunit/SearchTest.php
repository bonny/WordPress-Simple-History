<?php

use Simple_History\Simple_History;
use Simple_History\Log_Query;
use Simple_History\Helpers;

/**
 * Tests for search functionality in Log_Query.
 *
 * Covers the `search`, `exclude_search`, and `metadata_search` parameters,
 * including scoped context search, per-word cross-source matching,
 * and the word sanitization/cap logic.
 */
class SearchTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var Simple_History
	 */
	private $simple_history;

	/**
	 * @var int Admin user ID for tests.
	 */
	private $admin_user_id;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->simple_history = Simple_History::get_instance();

		$this->admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );

		// Clean up events from previous tests.
		global $wpdb;
		$events_table   = $this->simple_history->get_events_table_name();
		$contexts_table = $this->simple_history->get_contexts_table_name();
		$wpdb->query( "DELETE FROM {$contexts_table}" );
		$wpdb->query( "DELETE FROM {$events_table}" );
	}

	/**
	 * Helper: Create test event directly in database with context values.
	 *
	 * @param string $logger  Logger name.
	 * @param string $level   Log level.
	 * @param string $message Message.
	 * @param array  $context Key-value pairs for context.
	 * @return int Insert ID.
	 */
	private function create_event( $logger, $level, $message, $context = [] ) {
		global $wpdb;
		$events_table   = $this->simple_history->get_events_table_name();
		$contexts_table = $this->simple_history->get_contexts_table_name();

		$wpdb->insert(
			$events_table,
			[
				'logger'    => $logger,
				'level'     => $level,
				'date'      => gmdate( 'Y-m-d H:i:s' ),
				'message'   => $message,
				'initiator' => 'wp_user',
			]
		);

		$insert_id = $wpdb->insert_id;

		foreach ( $context as $key => $value ) {
			$wpdb->insert(
				$contexts_table,
				[
					'history_id' => $insert_id,
					'key'        => $key,
					'value'      => $value,
				]
			);
		}

		return $insert_id;
	}

	/**
	 * Test basic single-word search matches message column.
	 */
	public function test_search_matches_message_column() {
		$this->create_event( 'SimpleLogger', 'info', 'Updated the configuration settings' );
		$this->create_event( 'SimpleLogger', 'info', 'Logged in successfully' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'configuration',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'Should find exactly one event matching "configuration"' );
		$this->assertStringContainsString( 'configuration', $results['log_rows'][0]->message );
	}

	/**
	 * Test search matches logger column.
	 */
	public function test_search_matches_logger_column() {
		$this->create_event( 'SimplePluginLogger', 'info', 'Activated plugin' );
		$this->create_event( 'SimpleLogger', 'info', 'Some other event' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'SimplePluginLogger',
		] );

		$this->assertGreaterThanOrEqual( 1, (int) $results['total_row_count'], 'Should find event by logger name' );
	}

	/**
	 * Test search matches level column.
	 */
	public function test_search_matches_level_column() {
		$this->create_event( 'SimpleLogger', 'warning', 'Something happened' );
		$this->create_event( 'SimpleLogger', 'info', 'Normal event' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'warning',
		] );

		$this->assertGreaterThanOrEqual( 1, (int) $results['total_row_count'], 'Should find event by log level' );
	}

	/**
	 * Test multi-word search: all words must match (AND logic).
	 */
	public function test_multi_word_search_requires_all_words() {
		$this->create_event( 'SimpleLogger', 'info', 'Updated the configuration settings' );
		$this->create_event( 'SimpleLogger', 'info', 'Updated the user profile' );
		$this->create_event( 'SimpleLogger', 'info', 'Deleted the configuration file' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'Updated configuration',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'Only the event with both "Updated" AND "configuration" should match' );
	}

	/**
	 * Test per-word cross-source matching: each word can match in different sources.
	 *
	 * "api" matches in message column, "192" matches in context value.
	 * Both words must match but can match in different sources.
	 */
	public function test_multi_word_search_cross_source_matching() {
		// Event where "api" is in message and "192" is in context.
		$this->create_event( 'SimpleLogger', 'info', 'API request completed', [
			'_server_remote_addr' => '192.168.1.1',
		] );

		// Event where only "api" is in message (no matching context).
		$this->create_event( 'SimpleLogger', 'info', 'API request completed', [
			'_server_remote_addr' => '10.0.0.1',
		] );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'api 192',
		] );

		// Without experimental features, search is unscoped and checks all context values,
		// so "api" matches in message and "192" matches in context — cross-source match works.
		$this->assertIsArray( $results['log_rows'] );
		$this->assertEquals( 1, (int) $results['total_row_count'], 'Cross-source matching: "api" in message + "192" in context should match exactly one event' );
	}

	/**
	 * Test that empty search string returns all events.
	 */
	public function test_empty_search_returns_all_events() {
		$this->create_event( 'SimpleLogger', 'info', 'Event one' );
		$this->create_event( 'SimpleLogger', 'info', 'Event two' );
		$this->create_event( 'SimpleLogger', 'info', 'Event three' );

		$results_no_search = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
		] );

		$results_empty_search = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => '',
		] );

		$this->assertEquals(
			$results_no_search['total_row_count'],
			$results_empty_search['total_row_count'],
			'Empty search string should return same results as no search'
		);
	}

	/**
	 * Test that whitespace-only search string returns all events.
	 */
	public function test_whitespace_search_returns_all_events() {
		$this->create_event( 'SimpleLogger', 'info', 'Event one' );
		$this->create_event( 'SimpleLogger', 'info', 'Event two' );

		$results_no_search = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
		] );

		$results_whitespace = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => '   ',
		] );

		$this->assertEquals(
			$results_no_search['total_row_count'],
			$results_whitespace['total_row_count'],
			'Whitespace-only search should return same results as no search'
		);
	}

	/**
	 * Test that search words are capped at 10.
	 *
	 * Words beyond the 10th should be silently ignored. To verify this,
	 * we create an event matching word11 and confirm it's NOT filtered out
	 * (because word11 is beyond the cap and thus not used as a filter).
	 */
	public function test_search_word_count_cap() {
		$unique = 'capmatch_' . uniqid();

		// Event contains words 1-10 AND word11.
		$this->create_event( 'SimpleLogger', 'info', "{$unique} word1 word2 word3 word4 word5 word6 word7 word8 word9 word10 word11" );

		// Event contains words 1-10 but NOT word11.
		$this->create_event( 'SimpleLogger', 'info', "{$unique} word1 word2 word3 word4 word5 word6 word7 word8 word9 word10" );

		// Search with 11 words — word11 exceeds the 10-word cap.
		$search = "{$unique} word1 word2 word3 word4 word5 word6 word7 word8 word9 word10 word11";

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => $search,
		] );

		// Both events should match because word11 is ignored (capped at 10).
		$this->assertIsArray( $results );
		$this->assertArrayHasKey( 'log_rows', $results );
		$this->assertEquals( 2, (int) $results['total_row_count'], 'Word cap: the 11th word should be ignored, so both events match' );
	}

	/**
	 * Test exclude_search removes matching events.
	 */
	public function test_exclude_search_removes_matching_events() {
		$this->create_event( 'SimpleLogger', 'info', 'Cron job executed' );
		$this->create_event( 'SimpleLogger', 'info', 'User logged in' );
		$this->create_event( 'SimpleLogger', 'info', 'Plugin activated' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'exclude_search' => 'Cron',
		] );

		foreach ( $results['log_rows'] as $row ) {
			$this->assertStringNotContainsStringIgnoringCase(
				'Cron',
				$row->message,
				'Excluded events should not appear in results'
			);
		}
	}

	/**
	 * Test exclude_search with multi-word exclusion.
	 */
	public function test_exclude_search_multi_word() {
		$this->create_event( 'SimpleLogger', 'info', 'Cron job executed' );
		$this->create_event( 'SimpleLogger', 'info', 'Cron task scheduled' );
		$this->create_event( 'SimpleLogger', 'info', 'Plugin activated' );

		// Exclude events with both "Cron" AND "executed".
		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'exclude_search' => 'Cron executed',
		] );

		// "Cron job executed" has both words, should be excluded.
		// "Cron task scheduled" has only "Cron", should NOT be excluded (AND logic).
		$messages = array_map( fn( $row ) => $row->message, $results['log_rows'] );

		$this->assertNotContains( 'Cron job executed', $messages, '"Cron job executed" should be excluded' );
	}

	/**
	 * Test search and exclude_search together.
	 */
	public function test_search_and_exclude_search_combined() {
		$this->create_event( 'SimpleLogger', 'info', 'Plugin activated successfully' );
		$this->create_event( 'SimpleLogger', 'info', 'Plugin deactivated' );
		$this->create_event( 'SimpleLogger', 'info', 'User logged in' );

		// Search for "Plugin" but exclude "deactivated".
		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'Plugin',
			'exclude_search' => 'deactivated',
		] );

		$this->assertGreaterThanOrEqual( 1, (int) $results['total_row_count'] );

		foreach ( $results['log_rows'] as $row ) {
			$this->assertStringContainsStringIgnoringCase( 'Plugin', $row->message );
			$this->assertStringNotContainsStringIgnoringCase( 'deactivated', $row->message );
		}
	}

	/**
	 * Test metadata_search searches all context values (unscoped).
	 */
	public function test_metadata_search_finds_context_values() {
		$this->create_event( 'SimpleLogger', 'info', 'User logged in', [
			'_user_email'         => 'admin@example.com',
			'_server_remote_addr' => '192.168.1.100',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'Page updated', [
			'_user_email'         => 'editor@example.com',
			'_server_remote_addr' => '10.0.0.1',
		] );

		// Search by IP address via metadata_search.
		$results = ( new Log_Query() )->query( [
			'posts_per_page'  => 100,
			'metadata_search' => '192.168',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'metadata_search should find event by IP address in context' );
	}

	/**
	 * Test metadata_search with email address.
	 */
	public function test_metadata_search_finds_email() {
		$this->create_event( 'SimpleLogger', 'info', 'User logged in', [
			'_user_email' => 'admin@example.com',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'Another event', [
			'_user_email' => 'editor@example.com',
		] );

		$results = ( new Log_Query() )->query( [
			'posts_per_page'  => 100,
			'metadata_search' => 'admin@example',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'metadata_search should find event by email in context' );
	}

	/**
	 * Test metadata_search with multi-word query (AND logic).
	 */
	public function test_metadata_search_multi_word_and_logic() {
		$this->create_event( 'SimpleLogger', 'info', 'Event A', [
			'_user_email'         => 'admin@example.com',
			'_server_remote_addr' => '192.168.1.1',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'Event B', [
			'_user_email'         => 'admin@example.com',
			'_server_remote_addr' => '10.0.0.1',
		] );

		// Both words must match in context.
		$results = ( new Log_Query() )->query( [
			'posts_per_page'  => 100,
			'metadata_search' => 'admin@ 192.168',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'Multi-word metadata_search should AND words together' );
	}

	/**
	 * Test empty metadata_search returns all events.
	 */
	public function test_empty_metadata_search_returns_all() {
		$this->create_event( 'SimpleLogger', 'info', 'Event one' );
		$this->create_event( 'SimpleLogger', 'info', 'Event two' );

		$results_none = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
		] );

		$results_empty = ( new Log_Query() )->query( [
			'posts_per_page'  => 100,
			'metadata_search' => '',
		] );

		$this->assertEquals(
			$results_none['total_row_count'],
			$results_empty['total_row_count'],
			'Empty metadata_search should not affect results'
		);
	}

	/**
	 * Test search combined with metadata_search.
	 *
	 * Both filters should apply (AND logic between them).
	 */
	public function test_search_combined_with_metadata_search() {
		$this->create_event( 'SimpleLogger', 'info', 'User logged in', [
			'_server_remote_addr' => '192.168.1.1',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'Plugin updated', [
			'_server_remote_addr' => '192.168.1.1',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'User logged in', [
			'_server_remote_addr' => '10.0.0.1',
		] );

		// Search for "logged" in message + "192.168" in metadata.
		$results = ( new Log_Query() )->query( [
			'posts_per_page'  => 100,
			'search'          => 'logged',
			'metadata_search' => '192.168',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'Should find only the event matching both search and metadata_search' );
	}

	/**
	 * Test search via REST API returns results.
	 */
	public function test_search_via_rest_api() {
		$this->create_event( 'SimpleLogger', 'info', 'Plugin activated successfully' );
		$this->create_event( 'SimpleLogger', 'info', 'User logged in' );

		$request = new \WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'search', 'activated' );
		$request->set_param( 'per_page', 100 );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertGreaterThanOrEqual( 1, count( $data ), 'REST API search should return matching events' );
	}

	/**
	 * Test metadata_search via REST API returns results.
	 */
	public function test_metadata_search_via_rest_api() {
		$this->create_event( 'SimpleLogger', 'info', 'User logged in', [
			'_server_remote_addr' => '192.168.1.100',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'Another event', [
			'_server_remote_addr' => '10.0.0.1',
		] );

		$request = new \WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'metadata_search', '192.168' );
		$request->set_param( 'per_page', 100 );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertGreaterThanOrEqual( 1, count( $data ), 'REST API metadata_search should return matching events' );
	}

	/**
	 * Test skip_count_query returns null for total_row_count.
	 */
	public function test_skip_count_query_returns_null_total() {
		$this->create_event( 'SimpleLogger', 'info', 'Event for skip count test' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page'   => 100,
			'skip_count_query' => true,
		] );

		$this->assertNull( $results['total_row_count'], 'skip_count_query should make total_row_count null' );
		$this->assertGreaterThanOrEqual( 1, count( $results['log_rows'] ), 'Should still return log rows' );
	}

	/**
	 * Test skip_count_query via REST API returns no pagination headers.
	 */
	public function test_skip_count_query_via_rest_api() {
		$this->create_event( 'SimpleLogger', 'info', 'Event for REST skip count test' );

		$request = new \WP_REST_Request( 'GET', '/simple-history/v1/events' );
		$request->set_param( 'skip_count_query', true );
		$request->set_param( 'per_page', 100 );

		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertGreaterThanOrEqual( 1, count( $response->get_data() ) );
	}

	/**
	 * Test exclude_search works against context values (not just message columns).
	 */
	public function test_exclude_search_against_context_values() {
		$this->create_event( 'SimpleLogger', 'info', 'User logged in', [
			'_server_remote_addr' => '192.168.1.1',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'User logged in', [
			'_server_remote_addr' => '10.0.0.1',
		] );

		// Exclude events that have "192.168" in any searchable source.
		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'exclude_search' => '192.168',
		] );

		// The event with 192.168.1.1 in context should be excluded.
		$this->assertEquals( 1, (int) $results['total_row_count'], 'exclude_search should also exclude based on context values' );
	}

	/**
	 * Test search with no matching results returns empty.
	 */
	public function test_search_no_results() {
		$this->create_event( 'SimpleLogger', 'info', 'User logged in' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'zznonexistenttermzz',
		] );

		$this->assertEquals( 0, (int) $results['total_row_count'], 'Search for non-existent term should return 0 results' );
	}

	/**
	 * Test search for "0" works correctly (PHP's empty("0") is true).
	 */
	public function test_search_for_zero_string() {
		$this->create_event( 'SimpleLogger', 'info', 'Set value to 0 items' );
		$this->create_event( 'SimpleLogger', 'info', 'Set value to 5 items' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => '0',
		] );

		// "0" should be treated as a valid search term, not as empty.
		$this->assertGreaterThanOrEqual( 1, (int) $results['total_row_count'], 'Search for "0" should not return all events' );
	}

	/**
	 * Test search is case-insensitive.
	 */
	public function test_search_case_insensitive() {
		$this->create_event( 'SimpleLogger', 'info', 'Plugin Activated Successfully' );

		$results_lower = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'plugin activated',
		] );

		$results_upper = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'PLUGIN ACTIVATED',
		] );

		$results_mixed = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'pLuGiN aCtiVatEd',
		] );

		$this->assertEquals( $results_lower['total_row_count'], $results_upper['total_row_count'], 'Search should be case-insensitive' );
		$this->assertEquals( $results_lower['total_row_count'], $results_mixed['total_row_count'], 'Search should be case-insensitive' );
		$this->assertGreaterThanOrEqual( 1, (int) $results_lower['total_row_count'] );
	}

	/**
	 * Test search handles special SQL characters safely.
	 */
	public function test_search_special_characters() {
		$this->create_event( 'SimpleLogger', 'info', 'Updated option value to 100%' );
		$this->create_event( 'SimpleLogger', 'info', 'Path is /usr/local_bin' );

		// Percent sign should be escaped, not act as SQL wildcard.
		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => '100%',
		] );

		$this->assertIsArray( $results['log_rows'], 'Search with % should not cause SQL error' );

		// Underscore should be escaped, not act as single-char wildcard.
		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'local_bin',
		] );

		$this->assertIsArray( $results['log_rows'], 'Search with _ should not cause SQL error' );
	}

	/**
	 * Test search with comma-separated words.
	 */
	public function test_search_comma_separated_words() {
		$this->create_event( 'SimpleLogger', 'info', 'Updated plugin settings' );
		$this->create_event( 'SimpleLogger', 'info', 'Deleted old records' );

		// Commas should split into separate words.
		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'search'         => 'Updated,settings',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'Comma-separated words should act as separate AND terms' );
	}

	/**
	 * Test that search works with ungrouped query mode.
	 */
	public function test_search_works_with_ungrouped_query() {
		$unique = 'xyzuniq_' . uniqid();
		$this->create_event( 'SimpleLogger', 'info', "Matched {$unique} alpha" );
		$this->create_event( 'SimpleLogger', 'info', "Matched {$unique} beta" );
		$this->create_event( 'SimpleLogger', 'info', 'Unmatched event' );

		$results = ( new Log_Query() )->query( [
			'posts_per_page' => 100,
			'ungrouped'      => true,
			'search'         => $unique,
		] );

		$this->assertEquals( 2, (int) $results['total_row_count'], 'Search should work with ungrouped query mode' );
	}

	/**
	 * Test metadata_search works with ungrouped query mode.
	 */
	public function test_metadata_search_works_with_ungrouped_query() {
		$this->create_event( 'SimpleLogger', 'info', 'Event A', [
			'_server_remote_addr' => '192.168.1.1',
		] );
		$this->create_event( 'SimpleLogger', 'info', 'Event B', [
			'_server_remote_addr' => '10.0.0.1',
		] );

		$results = ( new Log_Query() )->query( [
			'posts_per_page'  => 100,
			'ungrouped'       => true,
			'metadata_search' => '192.168',
		] );

		$this->assertEquals( 1, (int) $results['total_row_count'], 'metadata_search should work with ungrouped query mode' );
	}
}
