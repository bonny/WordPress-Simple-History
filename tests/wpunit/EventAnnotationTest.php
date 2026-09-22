<?php

use Simple_History\Event;

/**
 * Test annotations on an event.
 *
 * An annotation is the first thing a human writes into the log — every other
 * field was written by a logger. Two things follow, and both are tested here:
 * edits must not overwrite, or a note becomes a way to rewrite what a record
 * said; and the text is sanitised at the point of storage, because it ends up
 * in CSV exports, webhook payloads and syslog frames.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit EventAnnotationTest
 */
class EventAnnotationTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var int
	 */
	private $user_id;

	public function setUp(): void {
		parent::setUp();

		$this->user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->user_id );
	}

	/**
	 * Log an event and return it.
	 *
	 * @return Event
	 */
	private function make_event() {
		SimpleLogger()->info( 'Annotation test event' );

		$rows = ( new \Simple_History\Log_Query() )->query(
			[
				'posts_per_page' => 1,
				'ungrouped'      => true,
			]
		);

		return Event::get( (int) $rows['log_rows'][0]->id );
	}

	public function test_an_event_starts_without_an_annotation() {
		$this->assertNull( $this->make_event()->get_annotation() );
	}

	public function test_a_note_is_stored_with_its_author() {
		$event = $this->make_event();

		$this->assertTrue( $event->annotate( 'Broke checkout', $this->user_id ) );

		$annotation = $event->get_annotation();

		$this->assertSame( 'Broke checkout', $annotation['text'] );
		$this->assertSame( $this->user_id, $annotation['user_id'] );
		$this->assertNotEmpty( $annotation['updated_at'] );
		$this->assertSame( [], $annotation['history'] );
	}

	/**
	 * The point of the whole design: an edit keeps what was there before.
	 */
	public function test_editing_keeps_the_previous_version() {
		$event = $this->make_event();

		$event->annotate( 'First', $this->user_id );
		$event->annotate( 'Second', $this->user_id );

		$annotation = $event->get_annotation();

		$this->assertSame( 'Second', $annotation['text'] );
		$this->assertCount( 1, $annotation['history'] );
		$this->assertSame( 'First', $annotation['history'][0]['text'] );
		$this->assertSame( $this->user_id, $annotation['history'][0]['user_id'] );
	}

	public function test_saving_the_same_text_does_not_add_a_version() {
		$event = $this->make_event();

		$event->annotate( 'Same', $this->user_id );
		$event->annotate( 'Same', $this->user_id );

		$this->assertCount( 0, $event->get_annotation()['history'] );
	}

	/**
	 * History is capped, so one event cannot grow without bound.
	 */
	public function test_history_is_capped() {
		$event = $this->make_event();

		for ( $i = 0; $i < Event::ANNOTATION_MAX_HISTORY + 5; $i++ ) {
			$event->annotate( 'Version ' . $i, $this->user_id );
		}

		$annotation = $event->get_annotation();

		$this->assertCount( Event::ANNOTATION_MAX_HISTORY, $annotation['history'] );

		// Oldest dropped first, so the most recent versions are the ones kept.
		$this->assertSame(
			'Version ' . ( Event::ANNOTATION_MAX_HISTORY + 3 ),
			end( $annotation['history'] )['text']
		);
	}

	public function test_an_empty_note_clears_the_text_but_keeps_the_history() {
		$event = $this->make_event();

		$event->annotate( 'Something', $this->user_id );
		$event->annotate( '', $this->user_id );

		$annotation = $event->get_annotation();

		$this->assertSame( '', $annotation['text'] );
		$this->assertCount( 1, $annotation['history'] );
		$this->assertSame( 'Something', $annotation['history'][0]['text'] );
	}

	/**
	 * The route that would otherwise defeat the whole append-only design:
	 * remove the note, then write a new one, and the record of what it used
	 * to say is gone.
	 */
	public function test_removing_a_note_cannot_be_used_to_erase_earlier_versions() {
		$event = $this->make_event();

		$event->annotate( 'First', $this->user_id );
		$event->annotate( 'Second', $this->user_id );
		$event->annotate( '', $this->user_id );
		$event->annotate( 'Third', $this->user_id );

		$annotation = $event->get_annotation();

		$this->assertSame( 'Third', $annotation['text'] );

		$texts = wp_list_pluck( $annotation['history'], 'text' );

		$this->assertContains( 'First', $texts );
		$this->assertContains( 'Second', $texts );
	}

	/**
	 * An event that never had a note does not gain an empty one.
	 */
	public function test_removing_a_note_that_was_never_written_stores_nothing() {
		$event = $this->make_event();

		$event->annotate( '', $this->user_id );

		$this->assertNull( $event->get_annotation() );
	}

	/**
	 * Newlines and tabs survive, because people write notes in paragraphs.
	 * Everything else in the C0 range does not: those are invisible, they
	 * survive every export, and a carriage return is half of the line ending
	 * that ends a syslog frame.
	 */
	public function test_control_characters_are_stripped_but_newlines_are_kept() {
		$event = $this->make_event();

		$event->annotate( "line one\nline two\ttabbed", $this->user_id );
		$this->assertSame( "line one\nline two\ttabbed", $event->get_annotation()['text'] );

		$event->annotate( "carriage\r\nreturn\x00null\x1bescape", $this->user_id );

		$text = $event->get_annotation()['text'];

		$this->assertStringNotContainsString( "\r", $text );
		$this->assertStringNotContainsString( "\x00", $text );
		$this->assertStringNotContainsString( "\x1b", $text );
		$this->assertStringContainsString( "\n", $text );
	}

	public function test_a_note_is_capped_in_length() {
		$event = $this->make_event();

		$event->annotate( str_repeat( 'a', Event::ANNOTATION_MAX_LENGTH + 500 ), $this->user_id );

		$this->assertSame(
			Event::ANNOTATION_MAX_LENGTH,
			strlen( $event->get_annotation()['text'] )
		);
	}

	/**
	 * Tags are stripped rather than stored, so nothing downstream has to
	 * remember to escape a note it was handed.
	 */
	public function test_markup_does_not_survive_storage() {
		$event = $this->make_event();

		$event->annotate( '<script>alert(1)</script>hello', $this->user_id );

		$this->assertStringNotContainsString( '<script', $event->get_annotation()['text'] );
	}

	/**
	 * The note comes back as it was typed.
	 *
	 * This ran through sanitize_textarea_field(), which quietly broke two
	 * ordinary kinds of note. It strips every percent-encoded octet, so a
	 * pasted encoded URL — an entirely normal thing to put in a note about a
	 * request — lost characters from the middle of it. And it HTML-escaped,
	 * so `A < B` was stored as `A &lt; B` and read back that way, with no
	 * route to the original. Both were silent, and an audit note that
	 * silently differs from what was typed is the one thing this feature
	 * cannot do.
	 */
	public function test_a_note_keeps_the_characters_people_actually_type() {
		$event = $this->make_event();

		$cases = [
			'see https://example.com/?s=foo%20bar',
			'A < B in the cart',
			'Price > 100 & < 200',
			'100% sure',
		];

		foreach ( $cases as $case ) {
			$event->annotate( $case, $this->user_id );

			$this->assertSame(
				$case,
				$event->get_annotation()['text'],
				'Stored note differs from what was written: ' . $case
			);
		}
	}

	/**
	 * Two writers racing cannot erase the note.
	 *
	 * The row is written before the old one is deleted, so a failed insert
	 * cannot take the history with it. The delete that follows used to be
	 * `context_id <> $inserted_id`, which made two concurrent saves destroy
	 * each other: A inserts row 100, B inserts row 101, A deletes everything
	 * that is not 100 (taking 101) and B deletes everything that is not 101
	 * (taking 100). Nothing left, the note and all twenty previous versions
	 * gone, and both callers told it worked.
	 *
	 * Interleaving two real requests is not something a unit test can do, so
	 * this reproduces the shape: a second row is planted with a higher id,
	 * as a concurrent writer's insert would leave it, and the save that
	 * follows must not end with an event that has no note at all.
	 */
	public function test_a_concurrent_write_cannot_erase_the_note() {
		global $wpdb;

		$event = $this->make_event();
		$event->annotate( 'the note', $this->user_id );

		$contexts_table = \Simple_History\Simple_History::get_instance()->get_contexts_table_name();

		// A second writer's row, landing after ours.
		$wpdb->insert(
			$contexts_table,
			[
				'history_id' => $event->get_id(),
				'key'        => '_annotation',
				'value'      => wp_json_encode( [ 'text' => 'the other note', 'user_id' => $this->user_id, 'history' => [] ] ),
			],
			[ '%d', '%s', '%s' ]
		);

		// Our own save runs again, as the slower request would.
		$event->annotate( 'the note, edited', $this->user_id );

		$rows = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$contexts_table} WHERE history_id = %d AND `key` = '_annotation'",
				$event->get_id()
			)
		);

		$this->assertSame( 1, $rows, 'The race left the event with no note at all.' );
		$this->assertNotNull( $event->get_annotation() );
	}
}
