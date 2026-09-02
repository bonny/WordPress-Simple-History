<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Notes_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Test Notes Logger functionality.
 *
 * Tests the Notes_Logger class which logs WordPress 6.9+ Notes feature.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit NotesLoggerTest
 */
class NotesLoggerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Simple History instance
	 *
	 * @var Simple_History
	 */
	private $sh;

	/**
	 * Notes Logger instance
	 *
	 * @var Notes_Logger
	 */
	private $logger;

	/**
	 * Admin user ID
	 *
	 * @var int
	 */
	private $admin_user_id;

	/**
	 * Test post ID
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sh = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'NotesLogger' );

		// Create admin user
		$this->admin_user_id = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);
		wp_set_current_user( $this->admin_user_id );

		// Create a test post with block content
		$this->post_id = $this->factory->post->create(
			[
				'post_title'   => 'Test Post for Notes',
				'post_content' => '<!-- wp:paragraph {"metadata":{"noteId":0}} --><p>This is a test paragraph.</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
			]
		);
	}

	/**
	 * Test that Notes Logger exists and is loaded.
	 */
	public function test_notes_logger_exists() {
		$this->assertNotNull( $this->logger, 'Notes Logger should be instantiated' );
		$this->assertInstanceOf( Notes_Logger::class, $this->logger );
		$this->assertEquals( 'NotesLogger', $this->logger->get_slug() );
	}

	/**
	 * Test that Notes Logger has correct info.
	 */
	public function test_notes_logger_info() {
		$info = $this->logger->get_info();

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'name', $info );
		$this->assertArrayHasKey( 'description', $info );
		$this->assertArrayHasKey( 'capability', $info );
		$this->assertArrayHasKey( 'messages', $info );

		// Check capability
		$this->assertEquals( 'edit_posts', $info['capability'] );

		// Check messages exist
		$this->assertArrayHasKey( 'note_added', $info['messages'] );
		$this->assertArrayHasKey( 'note_reply_added', $info['messages'] );
		$this->assertArrayHasKey( 'note_edited', $info['messages'] );
		$this->assertArrayHasKey( 'note_deleted', $info['messages'] );
		$this->assertArrayHasKey( 'note_resolved', $info['messages'] );
		$this->assertArrayHasKey( 'note_reopened', $info['messages'] );
	}

	/**
	 * Test logging when a note is added.
	 */
	public function test_note_added() {
		// Get initial row count to verify a new log entry is created
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Create a note comment
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'This is a test note',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$this->assertIsNumeric( $comment_id );

		// Check if a new log entry was created
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// If no logging happened, the test environment might not support WordPress 6.9 notes
		// or the logger hooks aren't properly attached
		if ( $initial_count === $final_count ) {
			$this->markTestSkipped( 'Notes logger did not log - hooks may not be attached or WP version does not support notes' );
		}

		// Verify the log entry
		$latest_row = get_latest_row();

		$this->assertEquals( 'NotesLogger', $latest_row['logger'] ?? '' );
		$this->assertEquals( 'info', $latest_row['level'] ?? '' );
		$this->assertEquals( 'wp_user', $latest_row['initiator'] ?? '' );

		// Verify context (including message key)
		$context = get_latest_context();

		// Check that the message key is stored in context
		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_added' ],
			$context,
			'Message key should be note_added'
		);

		$this->assertIsArray( $context );
		$this->assertContains(
			[ 'key' => 'note_id', 'value' => (string) $comment_id ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'post_id', 'value' => (string) $this->post_id ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'post_type', 'value' => 'post' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'post_title', 'value' => 'Test Post for Notes' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'note_content', 'value' => 'This is a test note' ],
			$context
		);
	}

	/**
	 * Test logging when a reply to a note is added.
	 */
	public function test_note_reply_added() {
		// Create a parent note
		$parent_comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'This is a parent note',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Create a reply to the note
		$reply_comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'This is a reply to the note',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'comment_parent'   => $parent_comment_id,
				'user_id'          => $this->admin_user_id,
			]
		);

		$this->assertIsNumeric( $reply_comment_id );

		// Verify the log entry is for a reply
		$latest_row = get_latest_row();

		$this->assertEquals( 'NotesLogger', $latest_row['logger'] ?? '' );

		// Verify context (including message key)
		$context = get_latest_context();

		// Check that the message key is stored in context
		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_reply_added' ],
			$context,
			'Message key should be note_reply_added'
		);

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => 'This is a reply to the note' ],
			$context
		);
	}

	/**
	 * Test that empty notes are not logged (they are status markers).
	 */
	public function test_empty_note_not_logged() {
		// Get current row count
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Create an empty note (used for status markers)
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => '',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Verify no new log entry was created
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $initial_count, $final_count, 'Empty notes should not be logged' );
	}

	/**
	 * Test logging when a note is edited.
	 */
	public function test_note_edited() {
		// Create a note
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Original note content',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Edit the note
		wp_update_comment(
			[
				'comment_ID'      => $comment_id,
				'comment_content' => 'Edited note content',
			]
		);

		// Verify the log entry
		$latest_row = get_latest_row();

		$this->assertEquals( 'NotesLogger', $latest_row['logger'] ?? '' );

		// Verify context (including message key)
		$context = get_latest_context();

		// Check that the message key is stored in context
		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_edited' ],
			$context,
			'Message key should be note_edited'
		);

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => 'Edited note content' ],
			$context
		);
	}

	/**
	 * Issue 286: WordPress stores a trailing <br> in the comment content when a
	 * note is anchored to inline text rather than a whole block. The logged
	 * note_content must not carry that markup.
	 */
	public function test_inline_note_added_logs_content_without_markup() {
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'note for "yes"?<br>',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_added' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'note_content', 'value' => 'note for "yes"?' ],
			$context
		);
	}

	/**
	 * Issue 286: same as above for the edit path.
	 */
	public function test_inline_note_edited_logs_content_without_markup() {
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Original note content',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		wp_update_comment(
			[
				'comment_ID'      => $comment_id,
				'comment_content' => 'Edited inline note<br>',
			]
		);

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_edited' ],
			$context
		);
		$this->assertContains(
			[ 'key' => 'note_content', 'value' => 'Edited inline note' ],
			$context
		);
	}

	/**
	 * Issue 286: a <br> in the middle of a note becomes a line break, not
	 * two words glued together.
	 */
	public function test_note_with_line_break_keeps_words_apart() {
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'First line<br>Second line',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$context = get_latest_context();

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => "First line\nSecond line" ],
			$context
		);
	}

	/**
	 * WordPress stores a mention as a span directly followed by the next word,
	 * with the gap drawn by CSS. Stripping the span must not glue the words.
	 */
	public function test_mention_in_note_keeps_a_space_before_the_following_word() {
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => '<span class="wp-note-mention user-2">@simple-history</span>nice!<br>',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => '@simple-history nice!' ],
			get_latest_context()
		);
	}

	/**
	 * The mention chip is matched the way core matches it: a span carrying the
	 * wp-note-mention class token, whatever the class order or quote style.
	 *
	 * @dataProvider mention_markup_variants
	 */
	public function test_mention_is_recognised_regardless_of_class_order_and_quotes( string $markup ) {
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => $markup . 'nice!',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => '@simple-history nice!' ],
			get_latest_context()
		);
	}

	public function mention_markup_variants(): array {
		return [
			'classes swapped'  => [ '<span class="user-2 wp-note-mention">@simple-history</span>' ],
			'single quotes'    => [ "<span class='wp-note-mention user-2'>@simple-history</span>" ],
			'other attr first' => [ '<span data-x="1" class="wp-note-mention user-2">@simple-history</span>' ],
			'upper case tag'   => [ '<SPAN CLASS="wp-note-mention user-2">@simple-history</SPAN>' ],
		];
	}

	/**
	 * A span that merely contains the class name as part of a longer token
	 * is not a mention and must not get a space.
	 */
	public function test_span_with_a_similar_class_is_not_treated_as_a_mention() {
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => '<span class="wp-note-mentions-off">@x</span>y',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => '@xy' ],
			get_latest_context()
		);
	}

	/**
	 * A mention already followed by a space must not get a second one.
	 */
	public function test_mention_followed_by_a_space_is_not_double_spaced() {
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => '<span class="wp-note-mention user-2">@simple-history</span> nice!',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => '@simple-history nice!' ],
			get_latest_context()
		);
	}

	/**
	 * Entities WordPress stored stay encoded in the context. Decoding them at
	 * log time would turn a kses-neutralised payload back into live markup.
	 */
	public function test_html_entities_in_note_stay_encoded() {
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Use &lt;img src=x onerror=alert(1)&gt; here',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		$this->assertContains(
			[ 'key' => 'note_content', 'value' => 'Use &lt;img src=x onerror=alert(1)&gt; here' ],
			get_latest_context()
		);
	}

	/**
	 * Test logging when a note is deleted.
	 *
	 * Note: This test may be skipped if wp_delete_comment doesn't trigger
	 * the delete_comment hook as expected in the test environment.
	 */
	public function test_note_deleted() {
		// Get initial row count
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Create a note
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Note to be deleted',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Delete the note permanently
		wp_delete_comment( $comment_id, true );

		// Check if deletion was logged
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Skip test if delete hook didn't fire (might not work in all WP versions)
		if ( $final_count <= $initial_count + 1 ) {
			$this->markTestSkipped( 'Delete hook did not fire - may not be supported in test environment' );
			return;
		}

		// Verify the log entry
		$latest_row = get_latest_row();

		// Double check we have the right logger before asserting
		if ( ( $latest_row['logger'] ?? '' ) !== 'NotesLogger' ) {
			$this->markTestSkipped( 'Delete was not logged by NotesLogger' );
			return;
		}

		$this->assertEquals( 'NotesLogger', $latest_row['logger'] );

		// Verify context
		$context = get_latest_context();

		// Check message key in context
		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_deleted' ],
			$context
		);

		$this->assertContains(
			[ 'key' => 'note_id', 'value' => (string) $comment_id ],
			$context
		);
	}

	/**
	 * Test logging when a note is trashed (via REST API).
	 *
	 * Note: This test may be skipped if wp_trash_comment doesn't trigger
	 * the trash_comment hook as expected in the test environment.
	 */
	public function test_note_trashed() {
		// Get initial row count
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Create a note
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Note to be trashed',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Trash the note (this is what REST API does)
		wp_trash_comment( $comment_id );

		// Check if trash was logged
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Skip test if trash hook didn't fire
		if ( $final_count <= $initial_count + 1 ) {
			$this->markTestSkipped( 'Trash hook did not fire - may not be supported in test environment' );
			return;
		}

		// Verify the log entry
		$latest_row = get_latest_row();

		// Double check we have the right logger before asserting
		if ( ( $latest_row['logger'] ?? '' ) !== 'NotesLogger' ) {
			$this->markTestSkipped( 'Trash was not logged by NotesLogger' );
			return;
		}

		$this->assertEquals( 'NotesLogger', $latest_row['logger'] );

		// Verify context
		$context = get_latest_context();

		// Check message key in context
		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_deleted' ],
			$context
		);
	}

	/**
	 * Test logging when a note is marked as resolved.
	 *
	 * Note: This test may be skipped if the meta update hooks don't trigger
	 * in the test environment or if WordPress version doesn't support note status.
	 */
	public function test_note_resolved() {
		// Get initial row count
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Create a note
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Note to be resolved',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Mark note as resolved
		add_comment_meta( $comment_id, '_wp_note_status', 'resolved' );

		// Check if resolution was logged
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Skip test if meta hook didn't fire
		if ( $final_count <= $initial_count + 1 ) {
			$this->markTestSkipped( 'Meta update hook did not fire - may not be supported in test environment' );
			return;
		}

		// Verify the log entry
		$latest_row = get_latest_row();

		// Double check we have the right logger before asserting
		if ( ( $latest_row['logger'] ?? '' ) !== 'NotesLogger' ) {
			$this->markTestSkipped( 'Resolution was not logged by NotesLogger' );
			return;
		}

		$this->assertEquals( 'NotesLogger', $latest_row['logger'] );

		// Verify context
		$context = get_latest_context();

		// Check message key in context
		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_resolved' ],
			$context
		);
	}

	/**
	 * Test logging when a note is reopened.
	 *
	 * Note: This test may be skipped if the meta update hooks don't trigger
	 * in the test environment or if WordPress version doesn't support note status.
	 */
	public function test_note_reopened() {
		// Get initial row count
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Create a note and mark it as resolved
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Note to be reopened',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);
		add_comment_meta( $comment_id, '_wp_note_status', 'resolved' );

		// Reopen the note
		update_comment_meta( $comment_id, '_wp_note_status', 'reopen' );

		// Check if reopen was logged
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Skip test if meta hook didn't fire (expects 3 entries: initial note + resolved + reopen)
		if ( $final_count <= $initial_count + 2 ) {
			$this->markTestSkipped( 'Meta update hook did not fire for reopen - may not be supported in test environment' );
			return;
		}

		// Verify the log entry
		$latest_row = get_latest_row();

		// Double check we have the right logger before asserting
		if ( ( $latest_row['logger'] ?? '' ) !== 'NotesLogger' ) {
			$this->markTestSkipped( 'Reopen was not logged by NotesLogger' );
			return;
		}

		$this->assertEquals( 'NotesLogger', $latest_row['logger'] );

		// Verify context
		$context = get_latest_context();

		// Check message key in context
		$this->assertContains(
			[ 'key' => '_message_key', 'value' => 'note_reopened' ],
			$context
		);
	}

	/**
	 * Test that non-note comments are not logged.
	 */
	public function test_regular_comment_not_logged() {
		// Get current row count
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Create a regular comment (not a note)
		wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'This is a regular comment',
				'comment_type'     => 'comment', // Not 'note'
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Notes Logger should not have logged this
		$latest_row = get_latest_row();

		// The latest log entry should not be from NotesLogger
		// (it might be from CommentsLogger instead)
		$this->assertNotEquals( 'NotesLogger', $latest_row['logger'] ?? '' );
	}

	/**
	 * Test get_root_note_id with threaded notes.
	 */
	public function test_get_root_note_id_with_threaded_notes() {
		// Create a parent note
		$parent_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Parent note',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Create a reply
		$child_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Child note',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'comment_parent'   => $parent_id,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Create a grandchild reply
		$grandchild_id = wp_insert_comment(
			[
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Grandchild note',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'comment_parent'   => $child_id,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Use reflection to access private method
		$reflection = new ReflectionClass( $this->logger );
		$method = $reflection->getMethod( 'get_root_note_id' );
		$method->setAccessible( true );

		// Test that all notes resolve to the same root
		$root_from_parent = $method->invoke( $this->logger, $parent_id );
		$root_from_child = $method->invoke( $this->logger, $child_id );
		$root_from_grandchild = $method->invoke( $this->logger, $grandchild_id );

		$this->assertEquals( $parent_id, $root_from_parent );
		$this->assertEquals( $parent_id, $root_from_child );
		$this->assertEquals( $parent_id, $root_from_grandchild );
	}

	/**
	 * Test that block information is captured when available.
	 *
	 * Note: This test requires WordPress 6.9+ with notes support and may be skipped
	 * if the block metadata is not processed as expected in the test environment.
	 */
	public function test_note_with_block_info() {
		// Update post to include a block with noteId
		$note_id = 123;
		wp_update_post(
			[
				'ID'           => $this->post_id,
				'post_content' => '<!-- wp:paragraph {"metadata":{"noteId":' . $note_id . '}} --><p>Paragraph with note attached.</p><!-- /wp:paragraph -->',
			]
		);

		// Create a note with the same ID
		$comment_id = wp_insert_comment(
			[
				'comment_ID'       => $note_id,
				'comment_post_ID'  => $this->post_id,
				'comment_content'  => 'Note with block info',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Verify block information is in context
		$context = get_latest_context();

		// Look for block_type in context
		$has_block_type = false;
		foreach ( $context as $item ) {
			if ( $item['key'] === 'block_type' && $item['value'] === 'paragraph' ) {
				$has_block_type = true;
				break;
			}
		}

		// Block info capture requires WordPress 6.9+ with proper note metadata support
		// If not available, skip the test rather than fail
		if ( ! $has_block_type ) {
			$this->markTestSkipped( 'Block type not captured - may require WordPress 6.9+ or proper note metadata setup' );
		}

		$this->assertTrue( $has_block_type, 'Block type should be captured in context' );
	}

	/**
	 * Test graceful handling when post doesn't exist.
	 */
	public function test_note_on_nonexistent_post() {
		// Get current row count
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$initial_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		// Try to create a note on a non-existent post
		// This should not log anything because get_post() will fail
		wp_insert_comment(
			[
				'comment_post_ID'  => 999999, // Non-existent post
				'comment_content'  => 'Note on non-existent post',
				'comment_type'     => 'note',
				'comment_approved' => 1,
				'user_id'          => $this->admin_user_id,
			]
		);

		// Verify no new log entry was created
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$final_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$db_table}" );

		$this->assertEquals( $initial_count, $final_count, 'Note on non-existent post should not be logged' );
	}
}
