<?php

use Simple_History\Dropins\RSS_Dropin;

/**
 * Test RSS_Dropin class methods.
 *
 * Tests the set_log_query_args_from_query_string() method to ensure it:
 * - Sets correct default values
 * - Properly converts types
 * - Sanitizes input
 * - Handles null vs empty string correctly
 */
class RSSDropinTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var RSS_Dropin
	 */
	private $rss_dropin;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Get Simple_History instance.
		$simple_history = \Simple_History\Simple_History::get_instance();

		// Create instance of RSS_Dropin for testing.
		$this->rss_dropin = new RSS_Dropin( $simple_history );
	}

	/**
	 * Test default values when no parameters provided.
	 */
	public function test_default_values_when_no_parameters_provided() {
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [] );

		$this->assertEquals( 10, $result['posts_per_page'], 'Default posts_per_page should be 10' );
		$this->assertEquals( 1, $result['paged'], 'Default paged should be 1' );
		$this->assertNull( $result['date_from'], 'Default date_from should be null' );
		$this->assertNull( $result['date_to'], 'Default date_to should be null' );
		$this->assertNull( $result['loggers'], 'Default loggers should be null' );
		$this->assertNull( $result['messages'], 'Default messages should be null' );
		$this->assertNull( $result['loglevels'], 'Default loglevels should be null' );
	}

	/**
	 * Test posts_per_page parameter conversion.
	 */
	public function test_posts_per_page_parameter() {
		// Test string to int conversion.
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [ 'posts_per_page' => '20' ] );
		$this->assertSame( 20, $result['posts_per_page'], 'String "20" should convert to int 20' );

		// Test actual integer.
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [ 'posts_per_page' => 50 ] );
		$this->assertSame( 50, $result['posts_per_page'], 'Integer 50 should remain int 50' );

		// Test float conversion.
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [ 'posts_per_page' => '15.7' ] );
		$this->assertSame( 15, $result['posts_per_page'], 'Float string "15.7" should convert to int 15' );
	}

	/**
	 * Test paged parameter conversion.
	 */
	public function test_paged_parameter() {
		// Test string to int conversion.
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [ 'paged' => '3' ] );
		$this->assertSame( 3, $result['paged'], 'String "3" should convert to int 3' );

		// Test actual integer.
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [ 'paged' => 5 ] );
		$this->assertSame( 5, $result['paged'], 'Integer 5 should remain int 5' );
	}

	/**
	 * Test optional parameters default to null.
	 * This validates our fix where we changed from empty strings/arrays to null.
	 */
	public function test_optional_parameters_default_to_null() {
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [] );

		// All optional parameters should be null when not provided.
		$this->assertNull( $result['date_from'], 'date_from should default to null' );
		$this->assertNull( $result['date_to'], 'date_to should default to null' );
		$this->assertNull( $result['loggers'], 'loggers should default to null' );
		$this->assertNull( $result['messages'], 'messages should default to null' );
		$this->assertNull( $result['loglevels'], 'loglevels should default to null' );

		// Verify they are actually null, not empty strings or arrays.
		$this->assertNotSame( '', $result['date_from'], 'date_from should not be empty string' );
		$this->assertNotSame( [], $result['loggers'], 'loggers should not be empty array' );
	}

	/**
	 * Test optional parameters are sanitized.
	 */
	public function test_optional_parameters_are_sanitized() {
		// Test with potentially malicious input.
		$args = [
			'date_from' => '<script>alert(1)</script>2025-01-01',
			'date_to' => '<b>2025-12-31</b>',
			'loggers' => 'SimpleUserLogger<script>',
			'messages' => 'SimpleUserLogger:user_logged_in<img src=x>',
			'loglevels' => 'info<script>alert(2)</script>',
		];

		$result = $this->rss_dropin->set_log_query_args_from_query_string( $args );

		// All should be sanitized (HTML tags stripped).
		$this->assertStringNotContainsString( '<script>', $result['date_from'], 'date_from should be sanitized' );
		$this->assertStringNotContainsString( '<b>', $result['date_to'], 'date_to should be sanitized' );
		$this->assertStringNotContainsString( '<script>', $result['loggers'], 'loggers should be sanitized' );
		$this->assertStringNotContainsString( '<img', $result['messages'], 'messages should be sanitized' );
		$this->assertStringNotContainsString( '<script>', $result['loglevels'], 'loglevels should be sanitized' );

		// Verify sanitize_text_field behavior.
		$this->assertEquals( '2025-01-01', $result['date_from'], 'date_from should have clean value' );
		$this->assertEquals( '2025-12-31', $result['date_to'], 'date_to should have clean value' );
	}

	/**
	 * Test empty string parameters.
	 * When an empty string is explicitly passed, it should remain an empty string (not null).
	 */
	public function test_empty_string_parameters() {
		$args = [
			'date_from' => '',
			'date_to' => '',
			'loggers' => '',
			'messages' => '',
			'loglevels' => '',
		];

		$result = $this->rss_dropin->set_log_query_args_from_query_string( $args );

		// Empty strings should be preserved (this is current behavior).
		$this->assertSame( '', $result['date_from'], 'Empty string date_from should remain empty string' );
		$this->assertSame( '', $result['date_to'], 'Empty string date_to should remain empty string' );
		$this->assertSame( '', $result['loggers'], 'Empty string loggers should remain empty string' );
		$this->assertSame( '', $result['messages'], 'Empty string messages should remain empty string' );
		$this->assertSame( '', $result['loglevels'], 'Empty string loglevels should remain empty string' );
	}

	/**
	 * Test all parameters together.
	 */
	public function test_all_parameters_together() {
		$args = [
			'posts_per_page' => '25',
			'paged' => '2',
			'date_from' => '2025-01-01',
			'date_to' => '2025-12-31',
			'loggers' => 'SimpleUserLogger,SimplePostLogger',
			'messages' => 'SimpleUserLogger:user_logged_in',
			'loglevels' => 'info,warning',
		];

		$result = $this->rss_dropin->set_log_query_args_from_query_string( $args );

		$this->assertSame( 25, $result['posts_per_page'], 'posts_per_page should be 25' );
		$this->assertSame( 2, $result['paged'], 'paged should be 2' );
		$this->assertEquals( '2025-01-01', $result['date_from'], 'date_from should be 2025-01-01' );
		$this->assertEquals( '2025-12-31', $result['date_to'], 'date_to should be 2025-12-31' );
		$this->assertEquals( 'SimpleUserLogger,SimplePostLogger', $result['loggers'], 'loggers should contain both loggers' );
		$this->assertEquals( 'SimpleUserLogger:user_logged_in', $result['messages'], 'messages should be correct format' );
		$this->assertEquals( 'info,warning', $result['loglevels'], 'loglevels should contain both levels' );
	}

	/**
	 * Test parameter sanitization with special characters.
	 */
	public function test_parameter_sanitization_with_special_characters() {
		$args = [
			'date_from' => "2025-01-01\n\r",
			'loggers' => 'SimpleUserLogger&amp;',
			'messages' => 'SimpleUserLogger:user_logged_in"\'',
		];

		$result = $this->rss_dropin->set_log_query_args_from_query_string( $args );

		// sanitize_text_field removes line breaks and normalizes whitespace.
		$this->assertStringNotContainsString( "\n", $result['date_from'], 'Newlines should be removed' );
		$this->assertStringNotContainsString( "\r", $result['date_from'], 'Carriage returns should be removed' );

		// Verify values are strings.
		$this->assertIsString( $result['date_from'], 'date_from should be string' );
		$this->assertIsString( $result['loggers'], 'loggers should be string' );
		$this->assertIsString( $result['messages'], 'messages should be string' );
	}

	/**
	 * Test that method handles missing keys gracefully.
	 */
	public function test_handles_partial_parameters() {
		// Only provide some parameters.
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [
			'posts_per_page' => '15',
			'loggers' => 'SimpleUserLogger',
		] );

		$this->assertSame( 15, $result['posts_per_page'], 'Provided posts_per_page should be set' );
		$this->assertEquals( 'SimpleUserLogger', $result['loggers'], 'Provided loggers should be set' );
		$this->assertSame( 1, $result['paged'], 'Missing paged should default to 1' );
		$this->assertNull( $result['date_from'], 'Missing date_from should default to null' );
	}

	/**
	 * Test return value structure.
	 */
	public function test_return_value_structure() {
		$result = $this->rss_dropin->set_log_query_args_from_query_string( [] );

		// Should return array.
		$this->assertIsArray( $result, 'Should return array' );

		// Should have all expected keys.
		$this->assertArrayHasKey( 'posts_per_page', $result, 'Should have posts_per_page key' );
		$this->assertArrayHasKey( 'paged', $result, 'Should have paged key' );
		$this->assertArrayHasKey( 'date_from', $result, 'Should have date_from key' );
		$this->assertArrayHasKey( 'date_to', $result, 'Should have date_to key' );
		$this->assertArrayHasKey( 'loggers', $result, 'Should have loggers key' );
		$this->assertArrayHasKey( 'messages', $result, 'Should have messages key' );
		$this->assertArrayHasKey( 'loglevels', $result, 'Should have loglevels key' );

		// Should have exactly 7 keys (no extra keys).
		$this->assertCount( 7, $result, 'Should have exactly 7 keys' );
	}
}
