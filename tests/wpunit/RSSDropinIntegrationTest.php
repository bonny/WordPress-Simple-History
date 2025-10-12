<?php

use Simple_History\Simple_History;
use Simple_History\Dropins\RSS_Dropin;

/**
 * Integration tests for RSS feed filtering.
 *
 * Tests that the RSS feed is actually filtered based on query string parameters.
 * These tests:
 * - Create test events
 * - Generate RSS feed XML
 * - Parse and verify filtered results
 */
class RSSDropinIntegrationTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var Simple_History
	 */
	private $simple_history;

	/**
	 * @var RSS_Dropin
	 */
	private $rss_dropin;

	/**
	 * @var int Admin user ID for tests.
	 */
	private $admin_user_id;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Get Simple History instance.
		$this->simple_history = Simple_History::get_instance();

		// Create RSS dropin instance.
		$this->rss_dropin = new RSS_Dropin( $this->simple_history );

		// Enable RSS feed.
		update_option( 'simple_history_enable_rss_feed', '1' );

		// Set RSS secret.
		update_option( 'simple_history_rss_secret', 'test_secret' );

		// Create admin user once (to avoid creating new users in each test which generates events).
		$this->admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );

		// Clear any existing events (including user creation event).
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->simple_history->get_events_table_name()}" );
		$wpdb->query( "TRUNCATE TABLE {$this->simple_history->get_contexts_table_name()}" );
	}

	/**
	 * Helper: Create test event directly in database.
	 *
	 * @param string $logger Logger name.
	 * @param string $level Log level.
	 * @param string $message Message.
	 * @param string $date Date (optional).
	 * @param string $message_key Message key (optional).
	 * @return int Insert ID.
	 */
	private function create_event( $logger, $level, $message, $date = null, $message_key = null ) {
		global $wpdb;
		$events_table = $this->simple_history->get_events_table_name();

		$wpdb->insert(
			$events_table,
			[
				'logger' => $logger,
				'level' => $level,
				'date' => $date ?? gmdate( 'Y-m-d H:i:s' ),
				'message' => $message,
			]
		);

		$insert_id = $wpdb->insert_id;

		// Add message_key context if provided.
		if ( $message_key ) {
			$contexts_table = $this->simple_history->get_contexts_table_name();
			$wpdb->insert(
				$contexts_table,
				[
					'history_id' => $insert_id,
					'key' => '_message_key',
					'value' => $message_key,
				]
			);
		}

		return $insert_id;
	}

	/**
	 * Helper: Get RSS feed output with query parameters.
	 *
	 * @param array $query_args Query string arguments.
	 * @return string RSS feed XML output.
	 */
	private function get_rss_feed_output( $query_args = [] ) {
		// Set admin user for query (use the one created in setUp).
		wp_set_current_user( $this->admin_user_id );

		// Override capability check - same as RSS feed does at line 259 of class-rss-dropin.php.
		$action_tag = 'simple_history/loggers_user_can_read/can_read_single_logger';
		add_filter( $action_tag, '__return_true', 10, 3 );

		// Prepare args using the method we're testing.
		$args = $this->rss_dropin->set_log_query_args_from_query_string( $query_args );

		// Apply the filter that would normally be applied in output_rss().
		$args = apply_filters( 'simple_history/rss_feed_args', $args );

		// Execute log query.
		$log_query = new \Simple_History\Log_Query();
		$query_results = $log_query->query( $args );

		// Remove capability override filter.
		remove_filter( $action_tag, '__return_true', 10 );

		// Generate RSS XML (simplified version of output_rss()).
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<rss version="2.0"><channel>';

		foreach ( $query_results['log_rows'] as $row ) {
			$text_output = $this->simple_history->get_log_row_plain_text_output( $row );
			echo '<item>';
			echo '<title>' . esc_xml( wp_strip_all_tags( $text_output ) ) . '</title>';
			echo '<logger>' . esc_xml( $row->logger ) . '</logger>';
			echo '<level>' . esc_xml( $row->level ) . '</level>';
			echo '<message_key>' . esc_xml( $row->context_message_key ?? '' ) . '</message_key>';
			echo '</item>';
		}

		echo '</channel></rss>';

		return ob_get_clean();
	}

	/**
	 * Helper: Parse RSS feed XML and return items.
	 *
	 * @param string $xml RSS feed XML.
	 * @return array Array of items with logger, level, message_key, title.
	 */
	private function parse_rss_items( $xml ) {
		$items = [];

		// Suppress XML errors.
		libxml_use_internal_errors( true );

		$rss = simplexml_load_string( $xml );

		if ( $rss === false ) {
			return $items;
		}

		foreach ( $rss->channel->item as $item ) {
			$items[] = [
				'title' => (string) $item->title,
				'logger' => (string) $item->logger,
				'level' => (string) $item->level,
				'message_key' => (string) $item->message_key,
			];
		}

		return $items;
	}

	/**
	 * Test filter by loggers parameter - single logger.
	 */
	public function test_filter_by_single_logger() {
		// Create events from different loggers.
		$this->create_event( 'SimpleUserLogger', 'info', 'User logged in' );
		$this->create_event( 'SimplePostLogger', 'info', 'Post created' );
		$this->create_event( 'SimplePluginLogger', 'info', 'Plugin activated' );

		// Get RSS with only SimpleUserLogger.
		$xml = $this->get_rss_feed_output( [ 'loggers' => 'SimpleUserLogger' ] );
		$items = $this->parse_rss_items( $xml );

		// Should have 1 item.
		$this->assertCount( 1, $items, 'Should have 1 item from SimpleUserLogger' );

		// Should be from SimpleUserLogger.
		$this->assertEquals( 'SimpleUserLogger', $items[0]['logger'], 'Item should be from SimpleUserLogger' );
	}

	/**
	 * Test filter by loggers parameter - multiple loggers.
	 */
	public function test_filter_by_multiple_loggers() {
		// Create events from different loggers.
		$this->create_event( 'SimpleUserLogger', 'info', 'User logged in' );
		$this->create_event( 'SimplePostLogger', 'info', 'Post created' );
		$this->create_event( 'SimplePluginLogger', 'info', 'Plugin activated' );

		// Get RSS with two loggers.
		$xml = $this->get_rss_feed_output( [ 'loggers' => 'SimpleUserLogger,SimplePostLogger' ] );
		$items = $this->parse_rss_items( $xml );

		// Should have 2 items.
		$this->assertCount( 2, $items, 'Should have 2 items from two loggers' );

		// Extract loggers.
		$loggers = array_column( $items, 'logger' );

		// Should contain both loggers.
		$this->assertContains( 'SimpleUserLogger', $loggers, 'Should contain SimpleUserLogger' );
		$this->assertContains( 'SimplePostLogger', $loggers, 'Should contain SimplePostLogger' );

		// Should NOT contain excluded logger.
		$this->assertNotContains( 'SimplePluginLogger', $loggers, 'Should NOT contain SimplePluginLogger' );
	}

	/**
	 * Test filter by messages parameter.
	 */
	public function test_filter_by_messages() {
		// Create user events with different message keys.
		$this->create_event( 'SimpleUserLogger', 'info', 'User logged in', null, 'user_logged_in' );
		$this->create_event( 'SimpleUserLogger', 'info', 'User updated', null, 'user_updated' );

		// Get RSS filtered by message.
		$xml = $this->get_rss_feed_output( [ 'messages' => 'SimpleUserLogger:user_logged_in' ] );
		$items = $this->parse_rss_items( $xml );

		// Should have 1 item.
		$this->assertCount( 1, $items, 'Should have 1 login item' );

		// Should be the login message.
		$this->assertEquals( 'user_logged_in', $items[0]['message_key'], 'Should be user_logged_in message' );
	}

	/**
	 * Test filter by log levels parameter - single level.
	 */
	public function test_filter_by_single_loglevel() {
		// Create events at different levels.
		$this->create_event( 'SimpleLogger', 'info', 'Info message' );
		$this->create_event( 'SimpleLogger', 'warning', 'Warning message' );
		$this->create_event( 'SimpleLogger', 'error', 'Error message' );

		// Get RSS with only error level.
		$xml = $this->get_rss_feed_output( [ 'loglevels' => 'error' ] );
		$items = $this->parse_rss_items( $xml );

		// Should have 1 item.
		$this->assertCount( 1, $items, 'Should have 1 error item' );

		// Should be error level.
		$this->assertEquals( 'error', $items[0]['level'], 'Item should be error level' );
	}

	/**
	 * Test filter by log levels parameter - multiple levels.
	 */
	public function test_filter_by_multiple_loglevels() {
		// Create events at different levels.
		$this->create_event( 'SimpleLogger', 'info', 'Info message' );
		$this->create_event( 'SimpleLogger', 'warning', 'Warning message' );
		$this->create_event( 'SimpleLogger', 'error', 'Error message' );

		// Get RSS with error and warning levels.
		$xml = $this->get_rss_feed_output( [ 'loglevels' => 'error,warning' ] );
		$items = $this->parse_rss_items( $xml );

		// Should have 2 items.
		$this->assertCount( 2, $items, 'Should have 2 items (error and warning)' );

		// Extract levels.
		$levels = array_column( $items, 'level' );

		// Should contain both levels.
		$this->assertContains( 'error', $levels, 'Should contain error level' );
		$this->assertContains( 'warning', $levels, 'Should contain warning level' );

		// Should NOT contain info.
		$this->assertNotContains( 'info', $levels, 'Should NOT contain info level' );
	}

	/**
	 * Test filter by date range.
	 */
	public function test_filter_by_date_range() {
		global $wpdb;

		// Create events on different dates by directly inserting with specific dates.
		$events_table = $this->simple_history->get_events_table_name();

		// Event from 2025-10-01.
		$wpdb->insert(
			$events_table,
			[
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'date' => '2025-10-01 12:00:00',
				'message' => 'Old event',
			]
		);

		// Event from 2025-10-10.
		$wpdb->insert(
			$events_table,
			[
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'date' => '2025-10-10 12:00:00',
				'message' => 'In range event',
			]
		);

		// Event from 2025-10-20.
		$wpdb->insert(
			$events_table,
			[
				'logger' => 'SimpleLogger',
				'level' => 'info',
				'date' => '2025-10-20 12:00:00',
				'message' => 'Future event',
			]
		);

		// Get RSS filtered by date range (Oct 10-15).
		$xml = $this->get_rss_feed_output( [
			'date_from' => '2025-10-10',
			'date_to' => '2025-10-15',
		] );
		$items = $this->parse_rss_items( $xml );

		// Should have 1 item (only the one from Oct 10).
		$this->assertCount( 1, $items, 'Should have 1 item in date range' );
		$this->assertStringContainsString( 'In range', $items[0]['title'], 'Should be the in-range event' );
	}

	/**
	 * Test posts_per_page limits results.
	 */
	public function test_posts_per_page_limits_results() {
		// Create 10 events.
		for ( $i = 1; $i <= 10; $i++ ) {
			$this->create_event( 'SimpleLogger', 'info', "Event $i" );
		}

		// Get RSS with limit of 5.
		$xml = $this->get_rss_feed_output( [ 'posts_per_page' => '5' ] );
		$items = $this->parse_rss_items( $xml );

		// Should have exactly 5 items.
		$this->assertCount( 5, $items, 'Should have exactly 5 items when posts_per_page=5' );
	}

	/**
	 * Test paged parameter for pagination.
	 */
	public function test_paged_parameter_pagination() {
		// Create 10 events.
		for ( $i = 1; $i <= 10; $i++ ) {
			$this->create_event( 'SimpleLogger', 'info', "Event $i" );
		}

		// Get page 1 with 5 items per page.
		$xml_page1 = $this->get_rss_feed_output( [
			'posts_per_page' => '5',
			'paged' => '1',
		] );
		$items_page1 = $this->parse_rss_items( $xml_page1 );

		// Get page 2 with 5 items per page.
		$xml_page2 = $this->get_rss_feed_output( [
			'posts_per_page' => '5',
			'paged' => '2',
		] );
		$items_page2 = $this->parse_rss_items( $xml_page2 );

		// Both pages should have items.
		$this->assertCount( 5, $items_page1, 'Page 1 should have 5 items' );
		$this->assertCount( 5, $items_page2, 'Page 2 should have 5 items' );

		// Pages should have different content.
		$this->assertNotEquals(
			$items_page1[0]['title'],
			$items_page2[0]['title'],
			'Page 1 and Page 2 should have different items'
		);
	}

	/**
	 * Test combined filters (multiple parameters together).
	 */
	public function test_combined_filters() {
		// Create user events.
		$this->create_event( 'SimpleUserLogger', 'info', 'User info event' );
		$this->create_event( 'SimpleUserLogger', 'warning', 'User warning event' );

		// Create post events.
		$this->create_event( 'SimplePostLogger', 'info', 'Post info event' );

		// Get RSS with combined filters: SimpleUserLogger + info level.
		$xml = $this->get_rss_feed_output( [
			'loggers' => 'SimpleUserLogger',
			'loglevels' => 'info',
		] );
		$items = $this->parse_rss_items( $xml );

		// Should have 1 item (SimpleUserLogger + info level).
		$this->assertCount( 1, $items, 'Should have 1 item matching both filters' );

		// Verify it matches both criteria.
		$this->assertEquals( 'SimpleUserLogger', $items[0]['logger'], 'Should be SimpleUserLogger' );
		$this->assertEquals( 'info', $items[0]['level'], 'Should be info level' );
	}

	/**
	 * Test empty parameters show all events.
	 */
	public function test_empty_parameters_show_all_events() {
		// Create 3 different events.
		$this->create_event( 'SimpleUserLogger', 'info', 'User event' );
		$this->create_event( 'SimplePostLogger', 'info', 'Post event' );
		$this->create_event( 'SimplePluginLogger', 'info', 'Plugin event' );

		// Get RSS with no filters.
		$xml = $this->get_rss_feed_output( [] );
		$items = $this->parse_rss_items( $xml );

		// Should have all 3 items.
		$this->assertCount( 3, $items, 'Should have all 3 items when no filters applied' );

		// Extract loggers.
		$loggers = array_column( $items, 'logger' );

		// Should contain all loggers.
		$this->assertContains( 'SimpleUserLogger', $loggers, 'Should contain SimpleUserLogger' );
		$this->assertContains( 'SimplePostLogger', $loggers, 'Should contain SimplePostLogger' );
		$this->assertContains( 'SimplePluginLogger', $loggers, 'Should contain SimplePluginLogger' );
	}

	/**
	 * Test default posts_per_page is respected.
	 */
	public function test_default_posts_per_page() {
		// Create 15 events.
		for ( $i = 1; $i <= 15; $i++ ) {
			$this->create_event( 'SimpleLogger', 'info', "Event $i" );
		}

		// Get RSS with no parameters (should default to 10 per page).
		$xml = $this->get_rss_feed_output( [] );
		$items = $this->parse_rss_items( $xml );

		// Should have 10 items (default).
		$this->assertCount( 10, $items, 'Should have 10 items by default (posts_per_page default)' );
	}

	/**
	 * Test null parameters vs not provided parameters.
	 * This validates our fix where optional parameters default to null.
	 */
	public function test_null_parameters_work_correctly() {
		// Create events.
		$this->create_event( 'SimpleUserLogger', 'info', 'User event' );
		$this->create_event( 'SimplePostLogger', 'info', 'Post event' );

		// Get RSS with null logger parameter (simulates not provided).
		$args_with_null = $this->rss_dropin->set_log_query_args_from_query_string( [] );

		// Verify loggers is null.
		$this->assertNull( $args_with_null['loggers'], 'loggers should be null when not provided' );

		// Set admin user and capability override (same as get_rss_feed_output does).
		wp_set_current_user( $this->admin_user_id );
		$action_tag = 'simple_history/loggers_user_can_read/can_read_single_logger';
		add_filter( $action_tag, '__return_true', 10, 3 );

		// Execute query with null parameters.
		$log_query = new \Simple_History\Log_Query();
		$query_results = $log_query->query( $args_with_null );

		// Remove capability override filter.
		remove_filter( $action_tag, '__return_true', 10 );

		// Should return all events (null means no filter).
		$this->assertCount( 2, $query_results['log_rows'], 'Null loggers parameter should return all events' );
	}
}
