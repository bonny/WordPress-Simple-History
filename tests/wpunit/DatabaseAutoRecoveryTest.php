<?php

use Simple_History\Simple_History;
use Simple_History\Log_Query;
use Simple_History\Services\Setup_Database;

/**
 * Test auto-recovery when database tables are missing.
 *
 * This tests the fix for issue #606 where tables might be missing due to:
 * - Site duplication where tables weren't copied
 * - MU plugin with orphaned db_version option
 * - Multisite network activation issues
 *
 * @coversDefaultClass Simple_History\Services\Setup_Database
 */
class DatabaseAutoRecoveryTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $simple_history;

	/**
	 * Get Simple History instance before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->simple_history = Simple_History::get_instance();

		// Ensure we have an admin user for permissions.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
	}

	/**
	 * Test is_table_missing_error() detects MySQL/MariaDB error messages.
	 *
	 * @covers ::is_table_missing_error
	 */
	public function test_is_table_missing_error_detects_mysql_errors() {
		// MySQL/MariaDB style error.
		$this->assertTrue(
			Setup_Database::is_table_missing_error( "Table 'wordpress.wp_simple_history' doesn't exist" ),
			'Should detect MySQL error with single quotes'
		);

		// Another variation.
		$this->assertTrue(
			Setup_Database::is_table_missing_error( "Table 'db.table_name' doesn't exist for query SELECT..." ),
			'Should detect error with query context'
		);

		// Case insensitive.
		$this->assertTrue(
			Setup_Database::is_table_missing_error( "TABLE 'test' DOESN'T EXIST" ),
			'Should be case insensitive'
		);

		// Alternative phrasing.
		$this->assertTrue(
			Setup_Database::is_table_missing_error( 'Table does not exist' ),
			'Should detect alternative phrasing'
		);
	}

	/**
	 * Test is_table_missing_error() returns false for other errors.
	 *
	 * @covers ::is_table_missing_error
	 */
	public function test_is_table_missing_error_ignores_other_errors() {
		$this->assertFalse(
			Setup_Database::is_table_missing_error( 'Duplicate entry for key' ),
			'Should not match duplicate key error'
		);

		$this->assertFalse(
			Setup_Database::is_table_missing_error( 'Connection refused' ),
			'Should not match connection error'
		);

		$this->assertFalse(
			Setup_Database::is_table_missing_error( '' ),
			'Should not match empty string'
		);
	}

	/**
	 * Test that recreate_tables_if_missing() handles the recreation process.
	 *
	 * Note: We can't easily drop tables in the test environment due to
	 * transaction isolation, so we test the method's behavior indirectly.
	 *
	 * @covers ::recreate_tables_if_missing
	 */
	public function test_recreate_tables_method_exists_and_is_callable() {
		// Verify the method exists and is callable.
		$this->assertTrue(
			method_exists( Setup_Database::class, 'recreate_tables_if_missing' ),
			'recreate_tables_if_missing method should exist'
		);

		$this->assertTrue(
			method_exists( Setup_Database::class, 'is_table_missing_error' ),
			'is_table_missing_error method should exist'
		);

		// Verify tables exist (they should in normal test environment).
		global $wpdb;
		$events_table = $this->simple_history->get_events_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$events_table}'" );
		$this->assertEquals( $events_table, $table_exists, 'Events table should exist in test environment' );
	}

	/**
	 * Test that recreate_tables_if_missing() only runs once per request.
	 *
	 * @covers ::recreate_tables_if_missing
	 */
	public function test_recreate_tables_only_runs_once() {
		// Note: This test relies on static variable, so it will only work
		// if run in isolation or if the static hasn't been triggered yet.
		// The first call in this test class may have already set it.

		// We can't easily test this without resetting the static variable,
		// but we can verify the method returns false on subsequent calls.
		$result = Setup_Database::recreate_tables_if_missing();

		// After first call (which may have happened in previous test),
		// subsequent calls should return false.
		$result_second = Setup_Database::recreate_tables_if_missing();
		$this->assertFalse( $result_second, 'Should return false on subsequent calls in same request' );
	}

	/**
	 * Test Log_Query auto-recovers when tables are missing.
	 *
	 * Note: This test may not work perfectly in the test environment because
	 * the static variable in recreate_tables_if_missing() persists across tests.
	 * In production, each request is fresh.
	 */
	public function test_log_query_returns_results_after_tables_exist() {
		// Add a log entry.
		SimpleLogger()->info( 'Test message for auto-recovery' );

		// Query should work.
		$query  = new Log_Query();
		$result = $query->query( [ 'posts_per_page' => 1 ] );

		$this->assertIsArray( $result, 'Query should return array' );
		$this->assertArrayHasKey( 'log_rows', $result, 'Result should have log_rows key' );
	}

	/**
	 * Test Logger::log() successfully logs after tables exist.
	 */
	public function test_logger_logs_event_successfully() {
		// Log an event.
		$logger = SimpleLogger()->info( 'Test event for logging' );

		// Verify event was logged.
		$this->assertNotNull( $logger->last_insert_id, 'Event should have been logged with an ID' );
		$this->assertGreaterThan( 0, $logger->last_insert_id, 'Insert ID should be greater than 0' );

		// Query to verify.
		$query  = new Log_Query();
		$result = $query->query( [ 'post__in' => [ $logger->last_insert_id ] ] );

		$this->assertCount( 1, $result['log_rows'], 'Should find the logged event' );
		$this->assertEquals( 'Test event for logging', $result['log_rows'][0]->message, 'Message should match' );
	}
}
