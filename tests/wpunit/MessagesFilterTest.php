<?php

use Simple_History\Simple_History;
use Simple_History\Log_Query;

/**
 * Tests for the `messages` and `exclude_messages` query filters.
 *
 * Verifies that these filters work in both the grouped (MySQL) and
 * ungrouped (simple) query paths.
 */
class MessagesFilterTest extends \Codeception\TestCase\WPTestCase {

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
	 * Helper: Create test event directly in database.
	 *
	 * @param string      $logger      Logger name.
	 * @param string      $level       Log level.
	 * @param string      $message     Message.
	 * @param string|null $message_key Message key context value.
	 * @return int Insert ID.
	 */
	private function create_event( $logger, $level, $message, $message_key = null ) {
		global $wpdb;
		$events_table = $this->simple_history->get_events_table_name();

		$wpdb->insert(
			$events_table,
			[
				'logger'  => $logger,
				'level'   => $level,
				'date'    => gmdate( 'Y-m-d H:i:s' ),
				'message' => $message,
			]
		);

		$insert_id = $wpdb->insert_id;

		if ( $message_key ) {
			$contexts_table = $this->simple_history->get_contexts_table_name();
			$wpdb->insert(
				$contexts_table,
				[
					'history_id' => $insert_id,
					'key'        => '_message_key',
					'value'      => $message_key,
				]
			);
		}

		return $insert_id;
	}

	/**
	 * GREEN: messages filter works with grouped query (default path).
	 */
	public function test_messages_filter_works_with_grouped_query() {
		$this->create_event( 'SimpleLogger', 'info', 'Action A happened', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'Action A again', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'Action B happened', 'action_b' );

		$log_query = new Log_Query();
		$results   = $log_query->query(
			[
				'posts_per_page' => 10,
				'messages'       => [ 'SimpleLogger:action_a' ],
			]
		);

		$this->assertGreaterThan( 0, $results['total_row_count'] );

		// Verify no action_b in results.
		$message_keys = [];
		foreach ( $results['log_rows'] as $row ) {
			$message_keys[] = $row->context['_message_key'] ?? '';
		}
		$this->assertNotContains( 'action_b', $message_keys );
	}

	/**
	 * GREEN: messages filter with multiple keys in grouped query.
	 */
	public function test_messages_filter_with_multiple_keys_grouped() {
		$this->create_event( 'SimpleLogger', 'info', 'A', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'B', 'action_b' );
		$this->create_event( 'SimpleLogger', 'info', 'C', 'action_c' );

		$log_query = new Log_Query();
		$results   = $log_query->query(
			[
				'posts_per_page' => 10,
				'messages'       => [ 'SimpleLogger:action_a', 'SimpleLogger:action_b' ],
			]
		);

		$this->assertEquals( 2, (int) $results['total_row_count'] );
	}

	/**
	 * GREEN: exclude_messages filter works with grouped query.
	 */
	public function test_exclude_messages_filter_works_with_grouped_query() {
		$this->create_event( 'SimpleLogger', 'info', 'A', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'B', 'action_b' );
		$this->create_event( 'SimpleLogger', 'info', 'C', 'action_c' );

		$log_query = new Log_Query();
		$results   = $log_query->query(
			[
				'posts_per_page'   => 10,
				'exclude_messages' => [ 'SimpleLogger:action_a' ],
			]
		);

		// Should return 2 events (action_b and action_c).
		$this->assertEquals( 2, (int) $results['total_row_count'] );
	}

	/**
	 * RED→GREEN: messages filter must work with ungrouped query.
	 *
	 * This test reveals the bug: query_overview_simple() only calls
	 * get_inner_where(), but messages filter was only in get_outer_where().
	 */
	public function test_messages_filter_works_with_ungrouped() {
		$this->create_event( 'SimpleLogger', 'info', 'Action A happened', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'Action A again', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'Action B happened', 'action_b' );

		$log_query = new Log_Query();
		$results   = $log_query->query(
			[
				'posts_per_page' => 10,
				'ungrouped'      => true,
				'messages'       => [ 'SimpleLogger:action_a' ],
			]
		);

		$this->assertEquals( 2, (int) $results['total_row_count'], 'Ungrouped query with messages filter should return only matching events.' );
	}

	/**
	 * RED→GREEN: messages filter with multiple keys in ungrouped query.
	 */
	public function test_messages_filter_with_multiple_keys_ungrouped() {
		$this->create_event( 'SimpleLogger', 'info', 'A', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'B', 'action_b' );
		$this->create_event( 'SimpleLogger', 'info', 'C', 'action_c' );

		$log_query = new Log_Query();
		$results   = $log_query->query(
			[
				'posts_per_page' => 10,
				'ungrouped'      => true,
				'messages'       => [ 'SimpleLogger:action_a', 'SimpleLogger:action_b' ],
			]
		);

		$this->assertEquals( 2, (int) $results['total_row_count'], 'Ungrouped query with multiple message keys should return only matching events.' );
	}

	/**
	 * RED→GREEN: exclude_messages filter must work with ungrouped query.
	 *
	 * This test reveals the bug: exclude_messages uses bare `contexts.value`
	 * reference which doesn't work without the JOIN in the simple query path.
	 */
	public function test_exclude_messages_filter_works_with_ungrouped() {
		$this->create_event( 'SimpleLogger', 'info', 'A', 'action_a' );
		$this->create_event( 'SimpleLogger', 'info', 'B', 'action_b' );
		$this->create_event( 'SimpleLogger', 'info', 'C', 'action_c' );

		$log_query = new Log_Query();
		$results   = $log_query->query(
			[
				'posts_per_page'   => 10,
				'ungrouped'        => true,
				'exclude_messages' => [ 'SimpleLogger:action_a' ],
			]
		);

		$this->assertEquals( 2, (int) $results['total_row_count'], 'Ungrouped query with exclude_messages should exclude matching events.' );
	}
}
