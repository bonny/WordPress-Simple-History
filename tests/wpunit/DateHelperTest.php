<?php

use Simple_History\Date_Helper;

/**
 * Test Date_Helper class functions.
 *
 * Tests all timezone-aware date helper functions to ensure they:
 * - Respect WordPress timezone setting
 * - Calculate correct date ranges
 * - Return proper timestamps
 */
class DateHelperTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test get_current_timestamp() returns current timestamp.
	 */
	public function test_get_current_timestamp() {
		$timestamp = Date_Helper::get_current_timestamp();
		$now = time();

		// Should be within 1 second of current time
		$this->assertEqualsWithDelta( $now, $timestamp, 1, 'get_current_timestamp() should return current timestamp' );
	}

	/**
	 * Test get_today_start_timestamp() returns midnight today in WordPress timezone.
	 */
	public function test_get_today_start_timestamp() {
		// Set WordPress timezone to Europe/Stockholm (UTC+2 in summer)
		update_option( 'timezone_string', 'Europe/Stockholm' );

		$timestamp = Date_Helper::get_today_start_timestamp();
		// Create date from timestamp and set to WordPress timezone
		$date = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() );

		// Should be today at 00:00:00
		$this->assertEquals( '00:00:00', $date->format( 'H:i:s' ), 'Should be midnight (00:00:00)' );

		// Get today's date in WordPress timezone for comparison
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$this->assertEquals( $today->format( 'Y-m-d' ), $date->format( 'Y-m-d' ), 'Should be today' );
	}

	/**
	 * Test get_today_end_timestamp() returns end of day today in WordPress timezone.
	 */
	public function test_get_today_end_timestamp() {
		// Set WordPress timezone to Europe/Stockholm (UTC+2 in summer)
		update_option( 'timezone_string', 'Europe/Stockholm' );

		$timestamp = Date_Helper::get_today_end_timestamp();
		// Create date from timestamp and set to WordPress timezone
		$date = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() );

		// Should be today at 23:59:59
		$this->assertEquals( '23:59:59', $date->format( 'H:i:s' ), 'Should be end of day (23:59:59)' );

		// Get today's date in WordPress timezone for comparison
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$this->assertEquals( $today->format( 'Y-m-d' ), $date->format( 'Y-m-d' ), 'Should be today' );
	}

	/**
	 * Test get_last_n_days_start_timestamp() returns correct start for "last N days".
	 *
	 * For "last N days including today", should return start of day N-1 days ago.
	 */
	public function test_get_last_n_days_start_timestamp() {
		// Set WordPress timezone
		update_option( 'timezone_string', 'Europe/Stockholm' );

		// Test "last 1 day" = today
		$timestamp_1_day = Date_Helper::get_last_n_days_start_timestamp( 1 );
		$date_1_day = ( new DateTimeImmutable( '@' . $timestamp_1_day ) )->setTimezone( wp_timezone() );
		$today = new DateTimeImmutable( 'today', wp_timezone() );

		$this->assertEquals(
			$today->format( 'Y-m-d' ),
			$date_1_day->format( 'Y-m-d' ),
			'Last 1 day should start today'
		);
		$this->assertEquals( '00:00:00', $date_1_day->format( 'H:i:s' ), 'Should be midnight' );

		// Test "last 7 days" = 6 days ago to today
		$timestamp_7_days = Date_Helper::get_last_n_days_start_timestamp( 7 );
		$date_7_days = ( new DateTimeImmutable( '@' . $timestamp_7_days ) )->setTimezone( wp_timezone() );
		$six_days_ago = new DateTimeImmutable( '-6 days', wp_timezone() );

		$this->assertEquals(
			$six_days_ago->format( 'Y-m-d' ),
			$date_7_days->format( 'Y-m-d' ),
			'Last 7 days should start 6 days ago'
		);
		$this->assertEquals( '00:00:00', $date_7_days->format( 'H:i:s' ), 'Should be midnight' );

		// Test "last 30 days" = 29 days ago to today
		$timestamp_30_days = Date_Helper::get_last_n_days_start_timestamp( 30 );
		$date_30_days = ( new DateTimeImmutable( '@' . $timestamp_30_days ) )->setTimezone( wp_timezone() );
		$twenty_nine_days_ago = new DateTimeImmutable( '-29 days', wp_timezone() );

		$this->assertEquals(
			$twenty_nine_days_ago->format( 'Y-m-d' ),
			$date_30_days->format( 'Y-m-d' ),
			'Last 30 days should start 29 days ago'
		);
		$this->assertEquals( '00:00:00', $date_30_days->format( 'H:i:s' ), 'Should be midnight' );
	}

	/**
	 * Test get_last_n_days_start_timestamp() gives exactly N days when combined with end of today.
	 */
	public function test_get_last_n_days_start_timestamp_exact_day_count() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		// Test that "last 30 days" gives exactly 30 days
		$start = Date_Helper::get_last_n_days_start_timestamp( 30 );
		$end = Date_Helper::get_today_end_timestamp();

		// Calculate number of days
		$days = ( $end - $start ) / ( 60 * 60 * 24 );

		// Should be approximately 30 days (accounting for rounding)
		$this->assertEqualsWithDelta(
			30.0,
			$days,
			0.01,
			'Last 30 days should give exactly 30 days of data'
		);

		// Test that "last 7 days" gives exactly 7 days
		$start_7 = Date_Helper::get_last_n_days_start_timestamp( 7 );
		$days_7 = ( $end - $start_7 ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			7.0,
			$days_7,
			0.01,
			'Last 7 days should give exactly 7 days of data'
		);
	}

	/**
	 * Test get_default_date_range() returns last 30 days.
	 */
	public function test_get_default_date_range() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		$range = Date_Helper::get_default_date_range();

		$this->assertIsArray( $range, 'Should return array' );
		$this->assertArrayHasKey( 'from', $range, 'Should have from key' );
		$this->assertArrayHasKey( 'to', $range, 'Should have to key' );

		// Calculate days
		$days = ( $range['to'] - $range['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			30.0,
			$days,
			0.01,
			'Default range should be exactly 30 days'
		);
	}

	/**
	 * Test get_last_n_days_range() returns correct range.
	 */
	public function test_get_last_n_days_range() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		// Test 7 days
		$range_7 = Date_Helper::get_last_n_days_range( 7 );
		$days_7 = ( $range_7['to'] - $range_7['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			7.0,
			$days_7,
			0.01,
			'get_last_n_days_range(7) should return exactly 7 days'
		);

		// Test 14 days
		$range_14 = Date_Helper::get_last_n_days_range( 14 );
		$days_14 = ( $range_14['to'] - $range_14['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			14.0,
			$days_14,
			0.01,
			'get_last_n_days_range(14) should return exactly 14 days'
		);
	}

	/**
	 * Test get_period_range() returns correct ranges for named periods.
	 */
	public function test_get_period_range() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		// Test week (7 days)
		$range_week = Date_Helper::get_period_range( 'week' );
		$days_week = ( $range_week['to'] - $range_week['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			7.0,
			$days_week,
			0.01,
			'Week period should be 7 days'
		);

		// Test fortnight (14 days)
		$range_fortnight = Date_Helper::get_period_range( 'fortnight' );
		$days_fortnight = ( $range_fortnight['to'] - $range_fortnight['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			14.0,
			$days_fortnight,
			0.01,
			'Fortnight period should be 14 days'
		);

		// Test month (30 days)
		$range_month = Date_Helper::get_period_range( 'month' );
		$days_month = ( $range_month['to'] - $range_month['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			30.0,
			$days_month,
			0.01,
			'Month period should be 30 days'
		);

		// Test quarter (90 days)
		$range_quarter = Date_Helper::get_period_range( 'quarter' );
		$days_quarter = ( $range_quarter['to'] - $range_quarter['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			90.0,
			$days_quarter,
			0.01,
			'Quarter period should be 90 days'
		);
	}

	/**
	 * Test that all functions respect WordPress timezone setting.
	 */
	public function test_respects_wordpress_timezone() {
		// Test with different timezone
		update_option( 'timezone_string', 'America/New_York' );

		$start = Date_Helper::get_last_n_days_start_timestamp( 30 );
		$end = Date_Helper::get_today_end_timestamp();

		// Create dates and convert to WordPress timezone
		$start_date = ( new DateTimeImmutable( '@' . $start ) )->setTimezone( wp_timezone() );
		$end_date = ( new DateTimeImmutable( '@' . $end ) )->setTimezone( wp_timezone() );

		// Should be midnight and end of day in New York time
		$this->assertEquals( '00:00:00', $start_date->format( 'H:i:s' ), 'Start should be midnight in NY timezone' );
		$this->assertEquals( '23:59:59', $end_date->format( 'H:i:s' ), 'End should be 23:59:59 in NY timezone' );

		// Test with Tokyo timezone
		update_option( 'timezone_string', 'Asia/Tokyo' );

		$start_tokyo = Date_Helper::get_last_n_days_start_timestamp( 7 );
		$start_tokyo_date = ( new DateTimeImmutable( '@' . $start_tokyo ) )->setTimezone( wp_timezone() );

		$this->assertEquals( '00:00:00', $start_tokyo_date->format( 'H:i:s' ), 'Start should be midnight in Tokyo timezone' );
	}

	/**
	 * Test get_wp_timezone() returns correct DateTimeZone.
	 */
	public function test_get_wp_timezone() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		$timezone = Date_Helper::get_wp_timezone();

		$this->assertInstanceOf( DateTimeZone::class, $timezone, 'Should return DateTimeZone object' );
		$this->assertEquals( 'Europe/Stockholm', $timezone->getName(), 'Should return correct timezone name' );
	}

	/**
	 * Test get_wp_timezone_string() returns correct timezone string.
	 */
	public function test_get_wp_timezone_string() {
		update_option( 'timezone_string', 'America/Los_Angeles' );

		$timezone_string = Date_Helper::get_wp_timezone_string();

		$this->assertEquals( 'America/Los_Angeles', $timezone_string, 'Should return correct timezone string' );
	}

	/**
	 * Test that date constants are correct.
	 */
	public function test_date_constants() {
		$this->assertEquals( 7, Date_Helper::DAYS_PER_WEEK, 'Week should be 7 days' );
		$this->assertEquals( 14, Date_Helper::DAYS_PER_FORTNIGHT, 'Fortnight should be 14 days' );
		$this->assertEquals( 30, Date_Helper::DAYS_PER_MONTH, 'Month should be 30 days' );
		$this->assertEquals( 90, Date_Helper::DAYS_PER_QUARTER, 'Quarter should be 90 days' );
	}

	/**
	 * Test get_last_n_complete_days_range() excludes today.
	 */
	public function test_get_last_n_complete_days_range() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		// Test 7 complete days (should be yesterday back 7 days)
		$range_7 = Date_Helper::get_last_n_complete_days_range( 7 );

		// Calculate number of days
		$days = ( $range_7['to'] - $range_7['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			7.0,
			$days,
			0.01,
			'Should return exactly 7 complete days'
		);

		// Verify end date is yesterday at 23:59:59
		$end_date = ( new DateTimeImmutable( '@' . $range_7['to'] ) )->setTimezone( wp_timezone() );
		$yesterday = new DateTimeImmutable( 'yesterday', wp_timezone() );

		$this->assertEquals(
			$yesterday->format( 'Y-m-d' ),
			$end_date->format( 'Y-m-d' ),
			'End date should be yesterday'
		);
		$this->assertEquals( '23:59:59', $end_date->format( 'H:i:s' ), 'End should be 23:59:59' );

		// Verify start date is 7 days ago from yesterday at 00:00:00
		$start_date = ( new DateTimeImmutable( '@' . $range_7['from'] ) )->setTimezone( wp_timezone() );
		$seven_days_before_yesterday = $yesterday->modify( '-6 days' );

		$this->assertEquals(
			$seven_days_before_yesterday->format( 'Y-m-d' ),
			$start_date->format( 'Y-m-d' ),
			'Start date should be 7 days before yesterday'
		);
		$this->assertEquals( '00:00:00', $start_date->format( 'H:i:s' ), 'Start should be 00:00:00' );
	}

	/**
	 * Test get_last_complete_week_range() returns Monday-Sunday.
	 */
	public function test_get_last_complete_week_range() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		$range = Date_Helper::get_last_complete_week_range();

		// Calculate number of days (should be 7)
		$days = ( $range['to'] - $range['from'] ) / ( 60 * 60 * 24 );

		$this->assertEqualsWithDelta(
			7.0,
			$days,
			0.01,
			'Complete week should be exactly 7 days'
		);

		// Verify start is Monday at 00:00:00
		$start_date = ( new DateTimeImmutable( '@' . $range['from'] ) )->setTimezone( wp_timezone() );
		$this->assertEquals( '1', $start_date->format( 'N' ), 'Start should be Monday (1)' );
		$this->assertEquals( '00:00:00', $start_date->format( 'H:i:s' ), 'Start should be midnight' );

		// Verify end is Sunday at 23:59:59
		$end_date = ( new DateTimeImmutable( '@' . $range['to'] ) )->setTimezone( wp_timezone() );
		$this->assertEquals( '7', $end_date->format( 'N' ), 'End should be Sunday (7)' );
		$this->assertEquals( '23:59:59', $end_date->format( 'H:i:s' ), 'End should be 23:59:59' );

		// Verify they are the same week (6 days apart)
		$diff_days = ( $range['to'] - $range['from'] ) / 86400;
		$this->assertEqualsWithDelta( 7.0, $diff_days, 0.01, 'Monday to Sunday should be ~7 days' );
	}

	/**
	 * Test get_last_complete_week_range() on different days of week.
	 */
	public function test_get_last_complete_week_range_different_days() {
		update_option( 'timezone_string', 'Europe/Stockholm' );

		// Get the range (should always be last complete Mon-Sun)
		$range = Date_Helper::get_last_complete_week_range();

		// The range should always end on a Sunday
		$end_date = ( new DateTimeImmutable( '@' . $range['to'] ) )->setTimezone( wp_timezone() );
		$this->assertEquals( '7', $end_date->format( 'N' ), 'Should always end on Sunday' );

		// The range should always start on a Monday
		$start_date = ( new DateTimeImmutable( '@' . $range['from'] ) )->setTimezone( wp_timezone() );
		$this->assertEquals( '1', $start_date->format( 'N' ), 'Should always start on Monday' );

		// The end should be in the past (not including this week if today is Mon-Sat)
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		$this->assertLessThan(
			$now->getTimestamp(),
			$range['to'],
			'Complete week should be in the past'
		);
	}
}
