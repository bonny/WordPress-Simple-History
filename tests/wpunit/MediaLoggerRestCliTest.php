<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Media_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Tests that Media_Logger captures alt-text diffs for REST and WP-CLI updates.
 *
 * Issue #185: the attachment_updated event fires globally for all contexts, but
 * alt-text diff capture is admin-only (load-post.php hook + $_POST check).
 * Under REST or WP-CLI, attachment_updated logs fine but the diff keys
 * attachment_alt_text_prev / attachment_alt_text_new are absent.
 *
 * Fix: add an update_post_metadata filter that snapshots the old alt text
 * before any write, replacing the admin-only load-post.php approach.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit MediaLoggerRestCliTest
 */
class MediaLoggerRestCliTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var Media_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $attachment_id;

	public function setUp(): void {
		parent::setUp();

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimpleMediaLogger' );

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// Create a minimal attachment post for testing.
		$this->attachment_id = $this->factory->post->create( array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'post_title'  => 'Test attachment',
			'post_mime_type' => 'image/jpeg',
		) );

		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'original alt text' );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_wp_cli' );
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_logger_exists_and_is_loaded() {
		$this->assertNotNull( $this->logger );
		$this->assertInstanceOf( Media_Logger::class, $this->logger );
	}

	/**
	 * Regression: attachment_updated event itself must fire for REST updates.
	 * This test should already pass — it verifies the event-level coverage that
	 * the issue confirms is already working.
	 */
	public function test_logs_attachment_updated_event_via_rest() {
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$this->attachment_id}" );
		$request->set_param( 'title', 'Updated attachment title' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'REST media update should succeed' );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'attachment_updated event must fire for REST media updates'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'attachment_updated' );
	}

	/**
	 * Regression guard: an alt text change from within wp-admin must still produce
	 * exactly one event (with the diff appended), not duplicate events. The new
	 * update_post_metadata filter is global, so we must verify it cooperates with
	 * the pre-existing admin path rather than emitting a second event.
	 */
	public function test_admin_alt_text_change_does_not_double_log() {
		set_current_screen( 'upload' );
		$this->assertTrue( is_admin(), 'is_admin() must be true for this test to be meaningful' );

		$new_alt      = 'admin updated alt';
		$count_before = $this->get_event_count();

		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', $new_alt );

		$prev = get_post( $this->attachment_id );
		wp_update_post( array( 'ID' => $this->attachment_id, 'post_title' => $prev->post_title ) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'Admin alt text change must produce exactly one event, not duplicates'
		);

		set_current_screen( 'front' );
	}

	/**
	 * REST: updating alt text via POST /wp/v2/media/<id> must include the
	 * before/after diff in the logged context.
	 *
	 * Currently FAILS — the update_post_metadata hook doesn't exist yet, so
	 * no prev-state snapshot is captured for REST alt-text writes.
	 */
	public function test_logs_alt_text_diff_via_rest_api() {
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$new_alt = 'new alt text set via REST';

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$this->attachment_id}" );
		$request->set_param( 'alt_text', $new_alt );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'REST alt text update should succeed' );

		$context      = get_latest_context();
		$context_keys = array_column( $context, 'key' );

		$this->assertContains(
			'attachment_alt_text_prev',
			$context_keys,
			'attachment_alt_text_prev must be in context when alt text changes via REST'
		);
		$this->assertContains(
			'attachment_alt_text_new',
			$context_keys,
			'attachment_alt_text_new must be in context when alt text changes via REST'
		);

		$this->assert_context_has( $context, 'attachment_alt_text_prev', 'original alt text' );
		$this->assert_context_has( $context, 'attachment_alt_text_new', $new_alt );
	}

	/**
	 * WP-CLI: updating alt text via update_post_meta() with WP_CLI=true,
	 * followed by wp_update_post() to trigger attachment_updated, must include
	 * the diff in context.
	 *
	 * Currently FAILS — same root cause as the REST gap.
	 */
	public function test_logs_alt_text_diff_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$new_alt = 'new alt text set via WP-CLI';

		// Simulate: wp post meta update <id> _wp_attachment_image_alt "new alt"
		// followed by a post update that fires attachment_updated.
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', $new_alt );

		// Trigger attachment_updated by touching the post row.
		$prev = get_post( $this->attachment_id );
		wp_update_post( array( 'ID' => $this->attachment_id, 'post_title' => $prev->post_title ) );

		$context      = get_latest_context();
		$context_keys = array_column( $context, 'key' );

		$this->assertContains(
			'attachment_alt_text_prev',
			$context_keys,
			'attachment_alt_text_prev must be in context when alt text changes via WP-CLI'
		);
		$this->assertContains(
			'attachment_alt_text_new',
			$context_keys,
			'attachment_alt_text_new must be in context when alt text changes via WP-CLI'
		);

		$this->assert_context_has( $context, 'attachment_alt_text_prev', 'original alt text' );
		$this->assert_context_has( $context, 'attachment_alt_text_new', $new_alt );
	}

	/**
	 * Issue #218: a bare `wp post meta update <id> _wp_attachment_image_alt "..."`
	 * call (no wp_update_post() follow-up) must still produce a log event with the
	 * before/after diff. Without this fix, only update_post_meta() runs and
	 * attachment_updated never fires, so the change is silent.
	 *
	 * Currently FAILS — the only event-producing hook is attachment_updated.
	 */
	public function test_logs_alt_text_change_via_bare_update_post_meta() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$new_alt      = 'alt text from bare meta update';
		$count_before = $this->get_event_count();

		// Simulate: wp post meta update <id> _wp_attachment_image_alt "..."
		// No subsequent wp_update_post() — the meta write is the only call.
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', $new_alt );

		// Emulate end-of-request — the deferred logger flushes on shutdown.
		$this->logger->on_shutdown_log_pending_alt_text_changes();

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'Bare update_post_meta for alt text must log exactly one event'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'attachment_updated' );
		$this->assert_context_has( $context, 'attachment_alt_text_prev', 'original alt text' );
		$this->assert_context_has( $context, 'attachment_alt_text_new', $new_alt );
	}

	/**
	 * Regression: a no-op meta write (same value as current) must not produce an
	 * event. Otherwise tools that re-set the same alt text would spam the log.
	 */
	public function test_no_event_when_bare_update_post_meta_same_value() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$count_before = $this->get_event_count();

		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'original alt text' );
		$this->logger->on_shutdown_log_pending_alt_text_changes();

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'A meta write with no value change must not log an event'
		);
	}

	/**
	 * No duplicate: changing alt text must produce exactly one attachment_updated
	 * event — not two (one from the old admin hook, one from the new filter).
	 * Regression guard for after both hooks exist simultaneously.
	 */
	public function test_no_duplicate_event_when_alt_text_changes_via_rest() {
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', "/wp/v2/media/{$this->attachment_id}" );
		$request->set_param( 'alt_text', 'deduplicated alt text' );
		rest_do_request( $request );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'Exactly one event must be logged per REST alt-text update — no duplicates'
		);
	}

	private function get_event_count(): int {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$db_table} WHERE logger = %s",
				'SimpleMediaLogger'
			)
		);
	}

	private function assert_context_has( array $context, string $key, string $value ): void {
		$this->assertContains(
			array( 'key' => $key, 'value' => $value ),
			$context,
			sprintf( 'Context should contain %s=%s', $key, $value )
		);
	}
}
