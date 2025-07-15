<?php

use Simple_History\Event;
use Simple_History\Log_Query;

/**
 * Run tests with:
 * `docker compose run --rm php-cli vendor/bin/codecept run wpunit EventTest`
 */
class EventTest extends \Codeception\TestCase\WPTestCase {

	/** @var int The ID of the last inserted event, i.e. the event we will use to test loading. */
	private $event_id;
	
	public function setUp(): void {
		parent::setUp();

		// Set current user to administrator so we can get the events.
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		// Log something to the database, so we
		// have something to test.
		$logger = SimpleLogger()->info( 
			'Test event from user with id {test_user_id}', 
			[ 
				'test_user_id' => $user_id,
				'test_key' => 'test value',
				'some_other_data_key' => 'some other data value',
			] 
		);
		$this->event_id = $logger->last_insert_id;
	}

	public function test_event_class() {
		$event = new Event();
		$this->assertInstanceOf(Event::class, $event);
	}

	public function test_event_class_basics() {
		$event = Event::get( $this->event_id );

		$this->assertInstanceOf(Event::class, $event, 'Event should be an instance of Event class.' );
		$this->assertEquals( $this->event_id, $event->get_id(), 'Event ID should match the inserted event ID.' );
		$this->assertTrue( $event->exists(), 'Event should exist.' );
		
		$this->assertIsObject( $event->get_data(), 'Event data should be an object.' );
		$this->assertIsArray( $event->get_context(), 'Event data should be an object.' );
	}

	public function test_event_class_vs_log_query_result() {
		$event = Event::get( $this->event_id );
		
		$log_query = new Log_Query();
		$query_results = $log_query->query( [ 
				'post__in' => [ $this->event_id ]
			] 
		);
		$first_log_row = $query_results['log_rows'][0];
		
		// Ensure that the event data matches the log query result,
		// so that we can use the event class to get the data just like we use the
		// log query result.
		$this->assertEquals( $first_log_row, $event->get_data(), 'Event data should match the log query result.' );
		$this->assertEquals( $first_log_row->context, $event->get_context(), 'Event context should match the log query result.' );
	}

	public function test_event_class_load_status() {
		$event = Event::get( $this->event_id );
		$this->assertEquals( 'LOADED_FROM_DB', $event->get_load_status(), 'Event load status should be LOADED_FROM_DB.' );
	}

	public function test_event_class_load_status_cache() {
		$event = Event::get( $this->event_id );
		$this->assertEquals( 'LOADED_FROM_DB', $event->get_load_status(), 'Event load status should be LOADED_FROM_DB.' );

		// Get the event again, this time it should be loaded from cache.
		$event = Event::get( $this->event_id );
		$this->assertEquals( 'LOADED_FROM_CACHE', $event->get_load_status(), 'Event load status should be LOADED_FROM_CACHE.' );

		// Get another event, this time it should be loaded from the database.
		// Log something new to the database.
		$logger = SimpleLogger()->info( 
			'Another test event', 
			[ 
				'test_abc' => 'Testing xyz.',
			] 
		);
		$new_event_id = $logger->last_insert_id;

		$event = Event::get( $new_event_id );
		$this->assertEquals( 'LOADED_FROM_DB', $event->get_load_status(), 'Event load status should be LOADED_FROM_DB.' );
	}

	// Test event that does not exist.
	public function test_event_class_load_status_not_found() {
		$event = Event::get( PHP_INT_MAX );
		
		// Event should be null when event does not exist.
		$this->assertNull( $event, 'Event should be null when event does not exist.' );

		// Reload same again to make sure cache does not mess things up.
		$event = Event::get( PHP_INT_MAX );
		$this->assertNull( $event, 'Event should still be null when event does not exist.' );
	}

	public function test_event_class_access_data() {
		$event = Event::get( $this->event_id );
		
		// Test that id, data can be accessable on event object.
		$this->assertEquals( $event->get_data()->id, $event->id, 'ID should be accessable on event object.' );
		// Test that message can be accessable on event object.
		$this->assertEquals( $event->get_data()->message, $event->message, 'Message should be accessable on event object.' );
		// Test that date can be accessable on event object.
		$this->assertEquals( $event->get_data()->date, $event->date, 'Date should be accessable on event object.' );
		// Test that logger can be accessable on event object.
		$this->assertEquals( $event->get_data()->logger, $event->logger, 'Logger should be accessable on event object.' );
		// Test that level can be accessable on event object.
		$this->assertEquals( $event->get_data()->level, $event->level, 'Level should be accessable on event object.' );
		// Test that occasionsID can be accessable on event object.
		$this->assertEquals( $event->get_data()->occasionsID, $event->occasionsID, 'occasionsID should be accessable on event object.' );
		// Test that initiator can be accessable on event object.
		$this->assertEquals( $event->get_data()->initiator, $event->initiator, 'Initiator should be accessable on event object.' );
		// Test that repeatCount can be accessable on event object.
		$this->assertEquals( $event->get_data()->repeatCount, $event->repeatCount, 'repeatCount should be accessable on event object.' );
		// Test that subsequentOccasions can be accessable on event object.
		$this->assertEquals( $event->get_data()->subsequentOccasions, $event->subsequentOccasions, 'subsequentOccasions should be accessable on event object.' );
		// Test that maxId can be accessable on event object.
		$this->assertEquals( $event->get_data()->maxId, $event->maxId, 'maxId should be accessable on event object.' );
		// Test that minId can be accessable on event object.
		$this->assertEquals( $event->get_data()->minId, $event->minId, 'minId should be accessable on event object.' );
		// Test that context_message_key can be accessable on event object.
		$this->assertEquals( $event->get_data()->context_message_key, $event->context_message_key, 'context_message_key should be accessable on event object.' );

		// Test that isset() works on event object.
		$this->assertTrue( isset( $event->id ), 'id should be accessable on event object.' );
		$this->assertTrue( isset( $event->message ), 'message should be accessable on event object.' );
		$this->assertTrue( isset( $event->date ), 'date should be accessable on event object.' );
		$this->assertTrue( isset( $event->logger ), 'logger should be accessable on event object.' );
		$this->assertTrue( isset( $event->level ), 'level should be accessable on event object.' );
		$this->assertTrue( isset( $event->occasionsID ), 'occasionsID should be accessable on event object.' );
	}

	public function test_event_class_access_context() {
		$event = Event::get( $this->event_id );

		// Context should be accessible via get_context()
		$this->assertIsArray( $event->get_context(), 'Context should be an array.' );
		$this->assertIsArray( $event->context, 'Context should exist as a property on event object.' );

		// Check that a known context key exists and has the expected value
		$this->assertArrayHasKey( 'test_key', $event->get_context(), 'Context should contain test_key key.' );
		$this->assertEquals(
			'test value',
			$event->get_data()->context['test_key'],
			'Context value for test_abc should match expected.'
		);

		$this->assertEquals('test value', $event->context['test_key']);
	}

	public function test_event_class_stick_unstick() {
		$event = Event::get( $this->event_id );
		$this->assertFalse( $event->is_sticky(), 'Event should not be sticky.' );

		$event->stick();

		$this->assertTrue( $event->is_sticky(), 'Event should be sticky.' );		
	}

	public function test_event_class_from_array() {
		$log_query = new Log_Query();
		$query_results = $log_query->query( [ 
				'post__in' => [ $this->event_id ]
			] 
		);
		$first_log_row = $query_results['log_rows'][0];

		$event = Event::from_object( $first_log_row );

		$this->assertEquals( $first_log_row, $event->get_data(), 'Event data should match the log query result.' );
		$this->assertEquals( $first_log_row->context, $event->get_context(), 'Event context should match the log query result.' );
	}

	public function test_event_class_get_many() {
		// Get last 10 events.
		$log_query = new Log_Query();
		$query_results = $log_query->query();
		$log_rows = $query_results['log_rows'];

		$log_rows_event_ids = array_map( function( $log_row ) {
			return $log_row->id;
		}, $log_rows );

		$events = Event::get_many( $log_rows_event_ids );

		$this->assertCount( count( $log_rows ), $events, 'Number of events should match number of log rows.' );

		foreach ( $events as $event ) {
			$this->assertInstanceOf( Event::class, $event, 'Event should be an instance of Event class.' );
		}

		// Index log rows so they start with event id.
		$log_rows_indexed = array_combine( $log_rows_event_ids, $log_rows );

		// Compare log rows and events.
		foreach ( $log_rows_indexed as $log_row_event_id => $log_row ) {
			$this->assertEquals( $log_row->id, $events[$log_row_event_id]->id, 'Event ID should match log row ID.' );
		}
	}

	public function test_event_class_exists_using_constructor() {
		$event = new Event( $this->event_id );
		$this->assertTrue( $event->exists(), 'Event should exist.' );

		$event = new Event( PHP_INT_MAX );
		$this->assertFalse( $event->exists(), 'Event should not exist.' );
	}
}