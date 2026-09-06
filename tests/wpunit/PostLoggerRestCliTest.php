<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Post_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Tests that Post_Logger captures post changes made outside wp-admin:
 * REST API (already works via on_rest_after_insert) and WP-CLI.
 *
 * Issue #185: wp_update_post() via WP-CLI produces no event because
 * maybe_log_post_change() bails when is_admin() is false and no WP_CLI
 * branch exists. A second gap: save_prev_post_data() is never called for
 * WP-CLI, so diffs are empty even if the gate were lifted.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit PostLoggerRestCliTest
 */
class PostLoggerRestCliTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var Post_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_wp_cli' );
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_logger_exists_and_is_loaded() {
		$this->assertNotNull( $this->logger );
		$this->assertInstanceOf( Post_Logger::class, $this->logger );
	}

	/**
	 * Negative: a plain wp_update_post() from a non-admin, non-REST, non-CLI
	 * context (e.g. a cron task or plugin hook) must NOT produce a log row.
	 */
	public function test_does_not_log_post_update_from_arbitrary_context() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$count_before = $this->get_event_count();

		// Plain wp_update_post() — no admin, no REST, no CLI.
		wp_update_post( array( 'ID' => $post_id, 'post_content' => 'arbitrary update' ) );

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'Post_Logger must not log plain wp_update_post() from a non-admin/REST/CLI context'
		);
	}

	/**
	 * Regression guard: a classic admin post save (auto-draft → publish) must still
	 * produce exactly one post_created event. We added pre_post_update and
	 * wp_after_insert_post hooks for WP-CLI; they must bail in admin context so the
	 * existing transition_post_status path remains the single source of truth.
	 */
	public function test_admin_classic_post_save_still_logs() {
		set_current_screen( 'post' );
		$this->assertTrue( is_admin(), 'is_admin() must be true for this test to be meaningful' );

		// Create an auto-draft first, then publish — mirrors the classic editor flow.
		$post_id = wp_insert_post( array(
			'post_status' => 'auto-draft',
			'post_type'   => 'post',
			'post_title'  => 'Auto Draft Title',
		) );

		$count_before = $this->get_event_count();

		wp_update_post( array(
			'ID'          => $post_id,
			'post_status' => 'publish',
			'post_title'  => 'Published Title',
		) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'Admin auto-draft → publish must produce exactly one log row (no duplicates from WP-CLI hooks)'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'post_created' );

		set_current_screen( 'front' );
	}

	/**
	 * Regression: REST API post updates already work via on_rest_after_insert.
	 * This test must stay green after the WP-CLI fix lands (no regressions).
	 *
	 * rest_do_request() doesn't define REST_REQUEST in the test environment
	 * (it's set during real HTTP bootstrap), so we hook the
	 * simple_history/is_rest_request filter to simulate it.
	 */
	public function test_logs_post_update_via_rest_api() {
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$post_id = $this->factory->post->create( array(
			'post_status'  => 'publish',
			'post_content' => 'original content',
		) );

		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$post_id}" );
		$request->set_param( 'content', 'content updated via REST' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'REST post update should succeed' );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'REST post update must produce exactly one log row'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'post_updated' );
	}

	/**
	 * WP-CLI: wp_update_post() with WP_CLI=true must produce a log row.
	 *
	 * Currently FAILS — maybe_log_post_change() bails at the is_admin() gate
	 * with no WP_CLI override branch.
	 */
	public function test_logs_post_update_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$count_before = $this->get_event_count();

		wp_update_post( array( 'ID' => $post_id, 'post_content' => 'updated via wp-cli' ) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_update_post() must produce a post_updated log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimplePostLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'post_updated' );
	}

	/**
	 * WP-CLI: wp_insert_post() must produce a post_created log row.
	 *
	 * Regression test for the bug where `wp post create` was silently dropped:
	 * on_wp_after_insert_post bailed for !$update, and on_transition_post_status
	 * bails for WP-CLI to avoid double-logging — leaving no logging path.
	 */
	public function test_logs_post_create_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$count_before = $this->get_event_count();

		$post_id = wp_insert_post( array(
			'post_title'   => 'CLI created post',
			'post_status'  => 'publish',
			'post_content' => 'content from cli create',
		) );

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_insert_post() must produce exactly one log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimplePostLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'post_created' );
		$this->assert_context_has( $context, 'post_title', 'CLI created post' );
	}

	/**
	 * WP-CLI: the logged event must include a content diff (prev vs new).
	 *
	 * Currently FAILS — save_prev_post_data() is never called for WP-CLI, so
	 * old_post_data is absent and the diff is empty even if the gate is lifted.
	 */
	public function test_logs_post_update_includes_content_diff_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$original_content = 'original content before cli edit';
		$new_content      = 'new content after cli edit';

		$post_id = $this->factory->post->create( array(
			'post_status'  => 'publish',
			'post_content' => $original_content,
		) );

		wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );

		$context = get_latest_context();

		$context_keys = array_column( $context, 'key' );
		$this->assertContains(
			'post_prev_post_content',
			$context_keys,
			'post_prev_post_content must be present in context when content changes via WP-CLI'
		);
		$this->assertContains(
			'post_new_post_content',
			$context_keys,
			'post_new_post_content must be present in context when content changes via WP-CLI'
		);
	}

	/**
	 * WP-CLI: wp post delete --force must produce a post_deleted log row.
	 *
	 * wp_delete_post() fires deleted_post (and before that, trashed_post for trash
	 * deletions). The post logger handles these through hooks that aren't
	 * is_admin()-gated, so this should already work — but a regression would silently
	 * lose audit coverage for content deletion.
	 */
	public function test_logs_post_delete_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$post_id = $this->factory->post->create( array(
			'post_status' => 'publish',
			'post_title'  => 'Post To Delete',
		) );

		$count_before = $this->get_event_count();

		// Force-delete a published post: skips trash, fires deleted_post once.
		wp_delete_post( $post_id, true );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_delete_post() must produce exactly one log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimplePostLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'post_deleted' );
		$context_values = array_column( $context, 'value' );
		$this->assertContains(
			'Post To Delete',
			$context_values,
			'The deleted post title must appear in the logged context'
		);
	}

	private function get_event_count(): int {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$db_table} WHERE logger = %s",
				'SimplePostLogger'
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
