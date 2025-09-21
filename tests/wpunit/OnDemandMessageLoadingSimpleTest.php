<?php

use Simple_History\Simple_History;
use Simple_History\Loggers\Post_Logger;

/**
 * Simplified tests for on-demand message loading optimization.
 * 
 * These tests verify the core functionality works without relying 
 * on internal implementation details.
 */
class OnDemandMessageLoadingSimpleTest extends \Codeception\TestCase\WPTestCase {
	
	private Simple_History $simple_history;

	public function setUp(): void {
		parent::setUp();
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Test that the new message access methods exist and work.
	 */
	public function test_message_access_methods_exist() {
		// Get a real logger from the system
		$loggers = $this->simple_history->get_instantiated_loggers();
		$this->assertNotEmpty( $loggers, 'Should have instantiated loggers' );
		
		// Get Post Logger specifically
		$post_logger = null;
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger, 'Post Logger should be available' );
		
		// Test that all new methods exist
		$this->assertTrue( method_exists( $post_logger, 'get_translated_message' ) );
		$this->assertTrue( method_exists( $post_logger, 'get_untranslated_message' ) );
		$this->assertTrue( method_exists( $post_logger, 'get_message_by_key' ) );
		$this->assertTrue( method_exists( $post_logger, 'get_messages' ) );
	}

	/**
	 * Test that get_messages returns expected data structure.
	 */
	public function test_get_messages_returns_expected_structure() {
		$loggers = $this->simple_history->get_instantiated_loggers();
		$post_logger = null;
		
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger );
		
		$messages = $post_logger->get_messages();
		
		$this->assertIsArray( $messages );
		$this->assertNotEmpty( $messages, 'Post Logger should have messages' );
		
		// Check structure of first message
		$first_message = reset( $messages );
		$this->assertIsArray( $first_message );
		$this->assertArrayHasKey( 'translated_text', $first_message );
		$this->assertArrayHasKey( 'untranslated_text', $first_message );
		$this->assertArrayHasKey( 'domain', $first_message );
		
		// Values should be strings
		$this->assertIsString( $first_message['translated_text'] );
		$this->assertIsString( $first_message['untranslated_text'] );
		$this->assertIsString( $first_message['domain'] );
	}

	/**
	 * Test that get_translated_message works for valid keys.
	 */
	public function test_get_translated_message_works() {
		$loggers = $this->simple_history->get_instantiated_loggers();
		$post_logger = null;
		
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger );
		
		// Get all messages to find a valid key
		$messages = $post_logger->get_messages();
		$this->assertNotEmpty( $messages );
		
		$first_key = array_key_first( $messages );
		$translated = $post_logger->get_translated_message( $first_key );
		
		$this->assertNotNull( $translated );
		$this->assertIsString( $translated );
		$this->assertNotEmpty( $translated );
	}

	/**
	 * Test that get_untranslated_message works for valid keys.
	 */
	public function test_get_untranslated_message_works() {
		$loggers = $this->simple_history->get_instantiated_loggers();
		$post_logger = null;
		
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger );
		
		// Get all messages to find a valid key
		$messages = $post_logger->get_messages();
		$this->assertNotEmpty( $messages );
		
		$first_key = array_key_first( $messages );
		$untranslated = $post_logger->get_untranslated_message( $first_key );
		
		$this->assertNotNull( $untranslated );
		$this->assertIsString( $untranslated );
		$this->assertNotEmpty( $untranslated );
	}

	/**
	 * Test that get_message_by_key returns complete message data.
	 */
	public function test_get_message_by_key_works() {
		$loggers = $this->simple_history->get_instantiated_loggers();
		$post_logger = null;
		
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger );
		
		// Get all messages to find a valid key
		$messages = $post_logger->get_messages();
		$this->assertNotEmpty( $messages );
		
		$first_key = array_key_first( $messages );
		$message_data = $post_logger->get_message_by_key( $first_key );
		
		$this->assertNotNull( $message_data );
		$this->assertIsArray( $message_data );
		$this->assertArrayHasKey( 'translated_text', $message_data );
		$this->assertArrayHasKey( 'untranslated_text', $message_data );
		$this->assertArrayHasKey( 'domain', $message_data );
		
		// Should match what we get from get_messages
		$this->assertEquals( $messages[ $first_key ], $message_data );
	}

	/**
	 * Test that invalid message keys return null.
	 */
	public function test_invalid_message_keys_return_null() {
		$loggers = $this->simple_history->get_instantiated_loggers();
		$post_logger = null;
		
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger );
		
		// Test with invalid key
		$invalid_key = 'this_key_does_not_exist_12345';
		
		$this->assertNull( $post_logger->get_translated_message( $invalid_key ) );
		$this->assertNull( $post_logger->get_untranslated_message( $invalid_key ) );
		$this->assertNull( $post_logger->get_message_by_key( $invalid_key ) );
	}

	/**
	 * Test that get_log_row_plain_text_output still works correctly.
	 */
	public function test_get_log_row_plain_text_output_still_works() {
		$loggers = $this->simple_history->get_instantiated_loggers();
		$post_logger = null;
		
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger );
		
		// Create a mock row object
		$row = (object) [
			'message' => 'Test message for interpolation',
			'context' => []
		];
		
		// Call get_log_row_plain_text_output
		$output = $post_logger->get_log_row_plain_text_output( $row );
		
		$this->assertIsString( $output );
		$this->assertNotEmpty( $output );
	}

	/**
	 * Test that the optimization doesn't break logging functionality.
	 */
	public function test_logging_still_works() {
		// Just test that we can access the logging system without errors
		$log_query = new \Simple_History\Log_Query();
		$events = $log_query->query( [
			'posts_per_page' => 10
		] );
		
		// Should be able to query events without error
		$this->assertIsArray( $events );
		
		// Test that we can create posts (basic WordPress functionality)
		$post_id = $this->factory->post->create( [
			'post_title' => 'Test Post for Optimization Verification',
			'post_content' => 'This tests that logging still works.',
			'post_status' => 'publish'
		] );
		
		$this->assertGreaterThan( 0, $post_id, 'Should be able to create posts' );
		
		// Test that Simple History logging system is available
		$loggers = $this->simple_history->get_instantiated_loggers();
		$this->assertNotEmpty( $loggers, 'Loggers should be instantiated and available' );
	}

	/**
	 * Performance test: Ensure accessing messages is reasonably fast.
	 */
	public function test_message_access_performance() {
		$loggers = $this->simple_history->get_instantiated_loggers();
		$post_logger = null;
		
		foreach ( $loggers as $logger_info ) {
			if ( $logger_info['instance'] instanceof Post_Logger ) {
				$post_logger = $logger_info['instance'];
				break;
			}
		}
		
		$this->assertNotNull( $post_logger );
		
		$start_time = microtime( true );
		
		// Access messages multiple times
		for ( $i = 0; $i < 50; $i++ ) {
			$messages = $post_logger->get_messages();
			if ( !empty( $messages ) ) {
				$first_key = array_key_first( $messages );
				$post_logger->get_translated_message( $first_key );
				$post_logger->get_untranslated_message( $first_key );
				$post_logger->get_message_by_key( $first_key );
			}
		}
		
		$end_time = microtime( true );
		$execution_time = $end_time - $start_time;
		
		// Should complete in under 1 second (very generous)
		$this->assertLessThan( 1.0, $execution_time, 'Message access should be fast' );
	}

	/**
	 * Test that search functionality still works (integration test).
	 */
	public function test_search_functionality_still_works() {
		$log_query = new \Simple_History\Log_Query();
		
		// Use reflection to test the private search method
		$reflection = new ReflectionClass( $log_query );
		$search_method = $reflection->getMethod( 'match_logger_messages_with_search' );
		$search_method->setAccessible( true );
		
		// Search for a word that might appear in messages
		$search_results = $search_method->invoke( $log_query, 'post' );
		
		$this->assertIsArray( $search_results );
		// Results might be empty, but method should work without errors
	}
}