<?php

/**
 * Functional tests for auto-recovery when database tables are missing.
 *
 * These tests verify the fix for issue #606 where tables might be missing due to:
 * - Site duplication where tables weren't copied
 * - MU plugin with orphaned db_version option
 * - Multisite network activation issues
 *
 * Functional tests are used instead of wpunit tests because they don't use
 * database transactions, allowing DDL statements like DROP TABLE to work properly.
 */
class DatabaseAutoRecoveryCest {

	/**
	 * Test that tables are automatically recreated when logging after drop.
	 *
	 * Simulates site duplication scenario:
	 * 1. Tables exist and work
	 * 2. Tables get dropped (simulating duplication where custom tables aren't copied)
	 * 3. User logs in (triggers Logger::log())
	 * 4. Tables should be recreated automatically
	 * 5. Event should be logged (not lost!)
	 *
	 * @param FunctionalTester $I The tester instance.
	 */
	public function test_auto_recovery_on_logging( FunctionalTester $I ) {
		// First verify tables exist initially.
		$I->seeInDatabase( 'information_schema.tables', [
			'TABLE_SCHEMA' => 'wp_test_site',
			'TABLE_NAME'   => 'wp_simple_history',
		] );

		// Drop tables (simulates site duplication where custom tables aren't copied).
		$I->dropSimpleHistoryTables();

		// Verify tables are gone.
		$I->dontSeeInDatabase( 'information_schema.tables', [
			'TABLE_SCHEMA' => 'wp_test_site',
			'TABLE_NAME'   => 'wp_simple_history',
		] );

		// Login triggers Logger::log() which should auto-recover.
		$I->haveUserInDatabase(
			'recovery_test_user',
			'editor',
			[
				'user_email' => 'recovery@example.org',
				'user_pass'  => 'testpass123',
			]
		);
		$I->loginAs( 'recovery_test_user', 'testpass123' );

		// Verify tables were recreated.
		$I->seeInDatabase( 'information_schema.tables', [
			'TABLE_SCHEMA' => 'wp_test_site',
			'TABLE_NAME'   => 'wp_simple_history',
		] );

		// Verify the login event was logged (not lost).
		// The logger column contains the logger class name.
		$I->seeInDatabase( 'wp_simple_history', [
			'logger' => 'SimpleUserLogger',
		] );

		// Verify the context was stored (context is stored in separate table).
		$I->seeInDatabase( 'wp_simple_history_contexts', [
			'key'   => '_message_key',
			'value' => 'user_logged_in',
		] );
	}

	/**
	 * Test that tables are automatically recreated when querying after drop.
	 *
	 * Tests the query path (Log_Query::query()) for auto-recovery by visiting
	 * the admin page which triggers event listing.
	 *
	 * @param FunctionalTester $I The tester instance.
	 */
	public function test_auto_recovery_on_query( FunctionalTester $I ) {
		// Drop tables.
		$I->dropSimpleHistoryTables();

		// Verify tables are gone.
		$I->dontSeeInDatabase( 'information_schema.tables', [
			'TABLE_SCHEMA' => 'wp_test_site',
			'TABLE_NAME'   => 'wp_simple_history',
		] );

		// Visit admin page - this triggers Log_Query::query() to load events.
		// The query should auto-recover by recreating tables.
		$I->loginAsAdmin();
		$I->amOnAdminPage( 'admin.php?page=simple_history_admin_menu_page' );

		// Page should not crash - it should show the Simple History interface.
		$I->see( 'Simple History' );

		// Verify tables were recreated.
		$I->seeInDatabase( 'information_schema.tables', [
			'TABLE_SCHEMA' => 'wp_test_site',
			'TABLE_NAME'   => 'wp_simple_history',
		] );
	}

	/**
	 * Test that logging works after tables are recreated.
	 *
	 * This ensures the full flow works: tables missing → recreated → events logged.
	 *
	 * @param FunctionalTester $I The tester instance.
	 */
	public function test_events_logged_after_recovery( FunctionalTester $I ) {
		// Drop tables.
		$I->dropSimpleHistoryTables();

		// Login as a new user - this should:
		// 1. Trigger auto-recovery when trying to log the login event
		// 2. Recreate tables
		// 3. Log the login event
		$I->haveUserInDatabase(
			'new_user_after_recovery',
			'subscriber',
			[
				'user_email' => 'newuser@example.org',
				'user_pass'  => 'testpass456',
			]
		);
		$I->loginAs( 'new_user_after_recovery', 'testpass456' );

		// Verify the login was logged.
		$I->seeInDatabase( 'wp_simple_history', [
			'logger' => 'SimpleUserLogger',
		] );

		// Verify the context was stored (context is stored in separate table).
		$I->seeInDatabase( 'wp_simple_history_contexts', [
			'key'   => '_message_key',
			'value' => 'user_logged_in',
		] );

		// Log out and log back in - this event should also be logged.
		// First navigate away from any page to ensure clean state.
		$I->amOnPage( '/wp-login.php?action=logout' );

		// Login again.
		$I->loginAs( 'new_user_after_recovery', 'testpass456' );

		// Verify we have at least 2 login events (by checking contexts table).
		$I->seeNumRecords( 2, 'wp_simple_history_contexts', [
			'key'   => '_message_key',
			'value' => 'user_logged_in',
		] );
	}
}
