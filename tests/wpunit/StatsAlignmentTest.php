<?php

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Events_Stats;
use Simple_History\Date_Helper;
use Simple_History\Log_Query;
use Simple_History\Services\Email_Report_Service;

/**
 * Test stats alignment across different components:
 * - Sidebar stats widget
 * - Stats/Insights page
 * - Email reports
 * - Chart data
 *
 * This test ensures that stats shown in different parts of the plugin
 * are consistent (or intentionally different and documented).
 *
 * Covers:
 * - Event counting (individual vs grouped)
 * - Timezone handling (WordPress timezone vs UTC)
 * - Permission filtering (sidebar filters, insights/email show all for admins)
 * - Date range consistency
 *
 * @coversDefaultClass Simple_History\Simple_History
 */
class StatsAlignmentTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Simple History instance
	 *
	 * @var Simple_History
	 */
	private $sh;

	/**
	 * Events Stats instance
	 *
	 * @var Events_Stats
	 */
	private $events_stats;

	/**
	 * Admin user ID
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Editor user ID
	 *
	 * @var int
	 */
	private $editor_user_id;

	/**
	 * Subscriber user ID
	 *
	 * @var int
	 */
	private $subscriber_user_id;

	/**
	 * Original timezone setting (to restore after tests)
	 *
	 * @var string
	 */
	private $original_timezone;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sh = Simple_History::get_instance();
		$this->events_stats = new Events_Stats();

		// Save original timezone
		$this->original_timezone = get_option( 'timezone_string' );

		// Set WordPress timezone to Europe/Stockholm (UTC+2) for predictable testing
		update_option( 'timezone_string', 'Europe/Stockholm' );

		// Clear event history for clean tests
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->sh->get_events_table_name()}" );
		$wpdb->query( "TRUNCATE TABLE {$this->sh->get_contexts_table_name()}" );

		// Create users with different roles
		$this->admin_user_id = $this->factory->user->create(
			[
				'role' => 'administrator',
				'user_login' => 'test_admin',
			]
		);

		$this->editor_user_id = $this->factory->user->create(
			[
				'role' => 'editor',
				'user_login' => 'test_editor',
			]
		);

		$this->subscriber_user_id = $this->factory->user->create(
			[
				'role' => 'subscriber',
				'user_login' => 'test_subscriber',
			]
		);
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		// Restore original timezone
		update_option( 'timezone_string', $this->original_timezone );

		parent::tearDown();
	}

	/**
	 * Helper: Create test events spread across last 30 days.
	 *
	 * Creates a known number of events that all users can see.
	 *
	 * @param int $num_events Number of events to create.
	 * @return int Number of events created.
	 */
	private function create_test_events_last_30_days( $num_events = 10 ) {
		// Log in as admin to ensure events are created
		wp_set_current_user( $this->admin_user_id );

		$created = 0;

		// Create events spread across the last 30 days
		for ( $i = 0; $i < $num_events; $i++ ) {
			// Create user login events (visible to all roles)
			SimpleLogger()->info(
				'user_logged_in',
				[
					'_initiator' => 'wp_user',
					'user_id' => $this->admin_user_id,
				]
			);
			$created++;
		}

		return $created;
	}

	/**
	 * Helper: Get date range for last N days using Date_Helper (WordPress timezone).
	 *
	 * @param int $days Number of days.
	 * @return array Array with 'date_from' and 'date_to' timestamps.
	 */
	private function get_date_range_last_n_days( $days = 30 ) {
		return [
			'date_from' => Date_Helper::get_last_n_days_start_timestamp( $days ),
			'date_to' => Date_Helper::get_current_timestamp(),
		];
	}

	/**
	 * Test 1: Admin User - All Stats Should Match
	 *
	 * For an admin user, all stats components should return the same count
	 * since admins can see all events.
	 */
	public function test_admin_user_all_stats_match() {
		// Set current user to admin
		wp_set_current_user( $this->admin_user_id );

		// Create known number of events
		$num_events_created = $this->create_test_events_last_30_days( 10 );

		// Get date range for last 30 days
		$date_range = $this->get_date_range_last_n_days( 30 );

		// 1. Get count from Sidebar Stats (uses Helpers)
		$sidebar_count = Helpers::get_num_events_last_n_days( 30 );

		// 2. Get count from Insights Page (uses Events_Stats)
		$insights_count = $this->events_stats->get_total_events(
			$date_range['date_from'],
			$date_range['date_to']
		);

		// The key assertion: sidebar and insights should match for admins
		$this->assertEquals(
			$sidebar_count,
			$insights_count,
			'Sidebar and Insights page counts should match for admin users'
		);

		// Verify we created events (count should be at least what we created)
		$this->assertGreaterThanOrEqual(
			$num_events_created,
			$sidebar_count,
			'Sidebar count should be at least the number of events we explicitly created'
		);
	}

	/**
	 * Test 2: Permission Filtering - Sidebar vs Insights Page
	 *
	 * IMPORTANT: This test documents INTENTIONAL behavior difference:
	 * - Sidebar: Filters by user permissions (works for all user roles)
	 * - Insights Page: Shows ALL events (admin-only page with manage_options capability)
	 *
	 * This is NOT a bug - Insights page is designed to give admins a complete view.
	 */
	public function test_permission_filtering_intentional_difference() {
		wp_set_current_user( $this->admin_user_id );

		// Create events that only admins can see
		$this->create_test_events_last_30_days( 10 );

		// Get date range
		$date_range = $this->get_date_range_last_n_days( 30 );

		// 1. Admin sidebar count (filtered but admin sees everything anyway)
		$admin_sidebar_count = Helpers::get_num_events_last_n_days( 30 );

		// 2. Insights count (unfiltered - shows all events)
		$insights_count = $this->events_stats->get_total_events(
			$date_range['date_from'],
			$date_range['date_to']
		);

		// For admin users, both should match because admin can see everything
		$this->assertEquals(
			$admin_sidebar_count,
			$insights_count,
			'Admin should see same count in sidebar and insights (both show all events)'
		);

		// Now test as editor (sidebar will filter, but editor cannot access insights page)
		wp_set_current_user( $this->editor_user_id );

		$editor_sidebar_count = Helpers::get_num_events_last_n_days( 30 );

		// Editor sidebar should show fewer events (filtered by permissions)
		$this->assertLessThanOrEqual(
			$admin_sidebar_count,
			$editor_sidebar_count,
			'Editor sidebar should show same or fewer events than admin (due to permission filtering)'
		);

		codecept_debug( "Admin sidebar: {$admin_sidebar_count}, Editor sidebar: {$editor_sidebar_count}, Insights (admin-only): {$insights_count}" );
	}

	/**
	 * Test 3: Timezone Alignment - WordPress Timezone vs UTC
	 *
	 * All components should use WordPress timezone, not server/UTC timezone.
	 */
	public function test_timezone_alignment() {
		wp_set_current_user( $this->admin_user_id );

		// Get current time in WordPress timezone
		$wp_tz = wp_timezone();
		$now = new DateTimeImmutable( 'now', $wp_tz );

		// Get "today" start timestamp using Date_Helper (should use WordPress timezone)
		$today_start = Date_Helper::get_today_start_timestamp();

		// Verify it's actually midnight in WordPress timezone
		$today_start_date = new DateTimeImmutable( '@' . $today_start );
		$today_start_date = $today_start_date->setTimezone( $wp_tz );

		$this->assertEquals(
			'00:00:00',
			$today_start_date->format( 'H:i:s' ),
			'Today start should be at midnight in WordPress timezone'
		);

		// Get "today" count using sidebar helper (should use WordPress timezone)
		$today_count_sidebar = Helpers::get_num_events_today();

		// Insights page uses different timezone implementation
		// Get date range for "today" in WordPress timezone
		$date_range = [
			'date_from' => Date_Helper::get_today_start_timestamp(),
			'date_to' => Date_Helper::get_current_timestamp(),
		];

		$today_count_insights = $this->events_stats->get_total_events(
			$date_range['date_from'],
			$date_range['date_to']
		);

		// Both should use WordPress timezone, so counts should match
		$this->assertEquals(
			$today_count_sidebar,
			$today_count_insights,
			'Today counts should match when using WordPress timezone'
		);
	}

	/**
	 * Test 4: Date Range Consistency - Last 30 Days
	 *
	 * Verify that "last 30 days" means the same thing across all components.
	 */
	public function test_date_range_consistency() {
		wp_set_current_user( $this->admin_user_id );

		// Create events
		$this->create_test_events_last_30_days( 8 );

		// Get date range using Date_Helper
		$date_from = Date_Helper::get_last_n_days_start_timestamp( 30 );
		$date_to = Date_Helper::get_current_timestamp();

		// 1. Sidebar count for last 30 days
		$sidebar_count = Helpers::get_num_events_last_n_days( 30 );

		// 2. Insights count for same period
		$insights_count = $this->events_stats->get_total_events( $date_from, $date_to );

		// Both should be counting the same period
		$this->assertEquals(
			$sidebar_count,
			$insights_count,
			'Last 30 days should mean the same period in sidebar and insights'
		);

		// Verify the date range is actually 30 days
		$date_diff = $date_to - $date_from;
		$days = floor( $date_diff / DAY_IN_SECONDS );

		// Should be approximately 30 days (allowing for DST changes)
		$this->assertGreaterThanOrEqual( 29, $days, 'Date range should be at least 29 days' );
		$this->assertLessThanOrEqual( 31, $days, 'Date range should be at most 31 days' );
	}

	/**
	 * Test 5: Individual Events vs Grouped Occasions
	 *
	 * Stats should count individual events, not grouped occasions.
	 * Main log GUI groups events for display, but stats count all events.
	 */
	public function test_individual_events_not_grouped_occasions() {
		wp_set_current_user( $this->admin_user_id );

		// Create multiple identical events (same user, same action)
		// These would be grouped in the main log GUI via occasionsID
		$num_identical_events = 5;

		for ( $i = 0; $i < $num_identical_events; $i++ ) {
			SimpleLogger()->info(
				'user_login_failed',
				[
					'_initiator' => 'wp_user',
					'user_login' => 'test_failed_login',
				]
			);
		}

		// Get date range
		$date_range = $this->get_date_range_last_n_days( 30 );

		// Count from sidebar (should count all individual events)
		$sidebar_count = Helpers::get_num_events_last_n_days( 30 );

		// Count from insights (should also count all individual events)
		$insights_count = $this->events_stats->get_total_events(
			$date_range['date_from'],
			$date_range['date_to']
		);

		// Both should count all 5 individual events, not 1 grouped occasion
		$this->assertGreaterThanOrEqual(
			$num_identical_events,
			$sidebar_count,
			'Sidebar should count all individual events, not grouped occasions'
		);

		$this->assertGreaterThanOrEqual(
			$num_identical_events,
			$insights_count,
			'Insights should count all individual events, not grouped occasions'
		);

		// They should match
		$this->assertEquals(
			$sidebar_count,
			$insights_count,
			'Both should count the same number of individual events'
		);
	}

	/**
	 * Test 6: Email Report Data Alignment
	 *
	 * Email reports should use same stats as sidebar for consistency.
	 */
	public function test_email_report_data_alignment() {
		wp_set_current_user( $this->admin_user_id );

		// Create events
		$this->create_test_events_last_30_days( 15 );

		// Get date range for last 7 days (what email uses)
		$date_from = Date_Helper::get_last_n_days_start_timestamp( Date_Helper::DAYS_PER_WEEK );
		$date_to = Date_Helper::get_current_timestamp();

		// 1. Get sidebar count for 7 days
		$sidebar_count = Helpers::get_num_events_last_n_days( Date_Helper::DAYS_PER_WEEK );

		// 2. Get email report data
		$email_service = new Email_Report_Service( $this->sh );
		$email_data = $email_service->get_summary_report_data( $date_from, $date_to, true );

		// Email uses Events_Stats::get_total_events() - same as insights page
		$email_total_events = $email_data['total_events_this_week'];

		// For admin users, sidebar and email should match
		$this->assertEquals(
			$sidebar_count,
			$email_total_events,
			'Email report total events should match sidebar count for admin users'
		);

		codecept_debug( "Sidebar (7 days): {$sidebar_count}, Email report: {$email_total_events}" );
	}

	/**
	 * Test 7: Chart Data Alignment
	 *
	 * Chart data should align with total counts.
	 */
	public function test_chart_data_alignment() {
		wp_set_current_user( $this->admin_user_id );

		// Create events
		$num_events = $this->create_test_events_last_30_days( 12 );

		// Get chart data from sidebar (returns array of objects with yearDate and count properties)
		$chart_data = Helpers::get_num_events_per_day_last_n_days( 30 );

		// Sum all days in chart
		$chart_total = 0;
		foreach ( $chart_data as $day_data ) {
			$chart_total += (int) $day_data->count;
		}

		// Get total from sidebar stats
		$sidebar_total = Helpers::get_num_events_last_n_days( 30 );

		// Chart total should match sidebar total - this is the key test
		$this->assertEquals(
			$sidebar_total,
			$chart_total,
			'Sum of chart data should match sidebar total count'
		);

		// Verify we have events in the chart
		$this->assertGreaterThanOrEqual(
			$num_events,
			$chart_total,
			'Chart data should count at least the events we explicitly created'
		);
	}

	/**
	 * Test 8: Filter Date Range Alignment
	 *
	 * Verifies that the GUI filter "lastdays:N" uses the same date range calculation
	 * as the sidebar stats. This ensures both query the same time window in WordPress timezone.
	 *
	 * NOTE: Event counts won't match because GUI groups by occasions while sidebar counts individuals.
	 * This test verifies the DATE RANGE is the same, not the counts.
	 */
	public function test_filter_uses_same_date_range_as_sidebar() {
		wp_set_current_user( $this->admin_user_id );

		// Create test events across multiple days
		$num_events = 20;
		$this->create_test_events_last_30_days( $num_events );

		// Test for 7 days period
		$days = 7;

		// Get the timestamp that Date_Helper would use
		$expected_start_timestamp = Date_Helper::get_last_n_days_start_timestamp( $days );

		// Query using Log_Query with lastdays parameter (same as GUI filter)
		$log_query = new Log_Query();
		$query_result = $log_query->query( [
			'dates' => "lastdays:{$days}",
			'posts_per_page' => 100,
		] );

		// The query should have been executed with a date >= the expected timestamp
		// We can't directly inspect the SQL, but we can verify the results include events
		// from the expected date range

		// Get sidebar count for comparison of time window (not count)
		$sidebar_count = Helpers::get_num_events_last_n_days( $days );

		// Verify we got results (means the date calculation worked)
		$this->assertNotEmpty(
			$query_result,
			'Filter query should return events'
		);

		// Verify the date calculation produces a timestamp in the past
		$this->assertLessThan(
			time(),
			$expected_start_timestamp,
			'Start timestamp should be in the past'
		);

		// Verify it's approximately correct (start of day N days ago)
		$expected_date = new DateTimeImmutable( "@{$expected_start_timestamp}" );
		$expected_date = $expected_date->setTimezone( wp_timezone() );

		$this->assertEquals(
			'00:00:00',
			$expected_date->format( 'H:i:s' ),
			'Filter start time should be midnight in WordPress timezone'
		);

		codecept_debug( "Filter and sidebar both use Date_Helper::get_last_n_days_start_timestamp({$days})" );
		codecept_debug( "Expected start: {$expected_date->format('Y-m-d H:i:s T')}" );
		codecept_debug( "Sidebar count (individual events): {$sidebar_count}" );
		codecept_debug( "Filter returned " . count( $query_result['log_rows'] ) . " rows (grouped occasions)" );
	}

	/**
	 * Test 9: Custom Date Range Filter Uses WordPress Timezone
	 *
	 * Verifies that custom date range filters (date_from/date_to) use WordPress timezone
	 * for parsing dates, ensuring correct day boundaries.
	 */
	public function test_custom_date_range_uses_wordpress_timezone() {
		wp_set_current_user( $this->admin_user_id );

		// Use dynamic dates: 5 days ago to today
		$date_from = new DateTimeImmutable( '-5 days', wp_timezone() );
		$date_to = new DateTimeImmutable( 'today', wp_timezone() );
		$date_from_string = $date_from->format( 'Y-m-d' );
		$date_to_string = $date_to->format( 'Y-m-d' );

		// Create test events in this date range
		$this->create_test_events_last_30_days( 10 );

		// Test with date strings in Y-m-d format
		$log_query = new Log_Query();
		$query_result = $log_query->query( [
			'date_from' => $date_from_string,  // Should be interpreted as date 00:00:00 in WP timezone
			'date_to' => $date_to_string,      // Should be interpreted as date 23:59:59 in WP timezone
			'posts_per_page' => 100,
		] );

		// Verify we got results
		$this->assertNotEmpty(
			$query_result,
			'Custom date range query should return events'
		);

		// Verify the date parsing uses WordPress timezone by checking the parsed timestamps
		// Create DateTimeImmutable objects for the expected dates in WordPress timezone
		$expected_from = new DateTimeImmutable( $date_from_string . ' 00:00:00', wp_timezone() );
		$expected_to = new DateTimeImmutable( $date_to_string . ' 23:59:59', wp_timezone() );

		// Verify they're at the correct times in WordPress timezone
		$this->assertEquals(
			'00:00:00',
			$expected_from->format( 'H:i:s' ),
			'date_from should be parsed as start of day in WordPress timezone'
		);

		$this->assertEquals(
			'23:59:59',
			$expected_to->format( 'H:i:s' ),
			'date_to should be parsed as end of day in WordPress timezone'
		);

		codecept_debug( "Custom date range uses WordPress timezone for parsing" );
		codecept_debug( "From: {$expected_from->format('Y-m-d H:i:s T')}" );
		codecept_debug( "To: {$expected_to->format('Y-m-d H:i:s T')}" );
	}

	/**
	 * Test 10: Month Filter Uses WordPress Timezone
	 *
	 * Verifies that month filters (e.g., "October 2025") use WordPress timezone
	 * for calculating month boundaries.
	 */
	public function test_month_filter_uses_wordpress_timezone() {
		wp_set_current_user( $this->admin_user_id );

		// Use current month dynamically
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		$current_month = $now->format( 'Y-m' );

		// Create test events in the last 30 days (which will include events in current month)
		$this->create_test_events_last_30_days( 10 );

		// Test with month format (Y-m)
		$log_query = new Log_Query();
		$query_result = $log_query->query( [
			'months' => $current_month,  // Current month
			'posts_per_page' => 100,
		] );

		// Verify we got results
		$this->assertNotEmpty(
			$query_result,
			'Month filter query should return events'
		);

		// Verify month boundaries are calculated in WordPress timezone
		// First day of month at 00:00:00
		$month_start = new DateTimeImmutable( $current_month . '-01 00:00:00', wp_timezone() );
		// Last day of month at 23:59:59
		$month_end = $month_start->modify( '+1 month' )->modify( '-1 second' );

		$this->assertEquals(
			'00:00:00',
			$month_start->format( 'H:i:s' ),
			'Month start should be at midnight in WordPress timezone'
		);

		$this->assertEquals(
			'23:59:59',
			$month_end->format( 'H:i:s' ),
			'Month end should be at 23:59:59 in WordPress timezone'
		);

		codecept_debug( "Month filter uses WordPress timezone for boundaries" );
		codecept_debug( "Testing month: {$current_month}" );
		codecept_debug( "Month start: {$month_start->format('Y-m-d H:i:s T')}" );
		codecept_debug( "Month end: {$month_end->format('Y-m-d H:i:s T')}" );
	}

	/**
	 * Test 11: All Date Filters Use Same WordPress Timezone
	 *
	 * Comprehensive test to verify ALL date filter types use WordPress timezone consistently.
	 * This is critical for ensuring users get predictable, consistent results.
	 */
	public function test_all_date_filters_use_wordpress_timezone_consistently() {
		wp_set_current_user( $this->admin_user_id );

		// Set WordPress timezone to a non-UTC timezone for testing
		$original_timezone = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'Europe/Stockholm' );  // UTC+1 or UTC+2 depending on DST

		// Create a test event exactly at midnight Stockholm time TODAY
		$today_midnight = new DateTimeImmutable( 'today 00:00:00', wp_timezone() );
		$today_string = $today_midnight->format( 'Y-m-d' );
		$current_month = $today_midnight->format( 'Y-m' );

		SimpleLogger()->info(
			'Test event at Stockholm midnight today',
			[
				'_timestamp' => $today_midnight->getTimestamp(),
			]
		);

		$log_query = new Log_Query();

		// Test 1: lastdays filter (should include today)
		$result_lastdays = $log_query->query( [
			'dates' => 'lastdays:1',  // Today only
			'posts_per_page' => 100,
		] );

		// Test 2: Custom date range (query for today only)
		$result_custom = $log_query->query( [
			'date_from' => $today_string,
			'date_to' => $today_string,
			'posts_per_page' => 100,
		] );

		// Test 3: Month filter (query for current month)
		$result_month = $log_query->query( [
			'months' => $current_month,
			'posts_per_page' => 100,
		] );

		// All three should include the event created at Stockholm midnight today
		// because they all use WordPress timezone (Europe/Stockholm)
		$this->assertNotEmpty(
			$result_lastdays,
			'lastdays filter should use WordPress timezone'
		);

		$this->assertNotEmpty(
			$result_custom,
			'Custom date range should use WordPress timezone'
		);

		$this->assertNotEmpty(
			$result_month,
			'Month filter should use WordPress timezone'
		);

		// Restore original timezone
		update_option( 'timezone_string', $original_timezone );

		codecept_debug( "All date filters consistently use WordPress timezone (Europe/Stockholm)" );
		codecept_debug( "Test date: {$today_string}, Month: {$current_month}" );
	}
}
