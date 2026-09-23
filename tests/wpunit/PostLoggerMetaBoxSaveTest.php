<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Post_Logger;
use Simple_History\Loggers\Plugin_ACF_Logger;

/**
 * Tests for the block editor's meta-box-loader request.
 *
 * Clicking Update in the block editor sends the post content via a REST API
 * request (logged normally by Post_Logger), followed by a separate
 * "meta-box-loader" admin-ajax-like request that saves the core Custom
 * Fields box, ACF, Yoast, etc. Post_Logger deliberately does not log that
 * second request as its own event (see the meta-box-loader bail in
 * maybe_log_post_change()) so the save isn't logged twice — but that means
 * custom field changes made through it were never logged at all.
 *
 * This links the meta-box-loader request back to the REST request's event by
 * matching on post_id + post_modified_gmt + user, not time proximity. See
 * Post_Logger::on_admin_action_editpost_meta_box_loader(),
 * on_wp_after_insert_post_meta_box_loader(), and
 * find_matching_post_updated_event_id().
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PostLoggerMetaBoxSaveTest
 */
class PostLoggerMetaBoxSaveTest extends \Codeception\TestCase\WPTestCase {

	/** @var Simple_History */
	private $sh;

	/** @var Post_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/post.php';

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// Meta-box-loader requests happen in wp-admin.
		set_current_screen( 'post' );
	}

	public function tearDown(): void {
		unset( $_GET['meta-box-loader'], $_POST );

		remove_all_filters( 'simple_history/is_rest_request' );
		set_current_screen( 'front' );

		parent::tearDown();
	}

	/**
	 * Step 1 of the design: whenever Post_Logger logs post_updated, the
	 * post's post_modified_gmt after the update must be stored in context.
	 */
	public function test_post_modified_gmt_is_stored_when_post_updated_logged() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$this->log_rest_content_update( $post_id, 'updated content' );

		$post = get_post( $post_id );

		$context = $this->get_context_for_event( $this->get_latest_event_id() );

		$this->assertSame( $post->post_modified_gmt, $context['post_modified_gmt'] ?? null );
	}

	/**
	 * The main scenario: a REST API save logs a post_updated event, then the
	 * meta-box-loader request changes a custom field. The custom field
	 * change must land on the same event, not a new one.
	 */
	public function test_meta_box_loader_request_appends_custom_field_change_to_matching_event() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		add_post_meta( $post_id, 'color', 'blue' );

		// Request 2: the REST API content save. Logs the event we expect the
		// meta-box-loader request to attach to.
		$this->log_rest_content_update( $post_id, 'content from the REST save' );

		$event_id     = $this->get_latest_event_id();
		$count_before = $this->get_event_count();

		// Request 3: the meta-box-loader request, changing "color".
		$meta_id = $this->get_meta_id( $post_id, 'color' );
		$this->assertGreaterThan( 0, $meta_id, 'precondition: color meta row must exist' );

		$this->run_meta_box_loader_request(
			$post_id,
			array(
				$meta_id => array(
					'key'   => 'color',
					'value' => 'pink',
				),
			)
		);

		$this->assertSame(
			$count_before,
			$this->get_event_count(),
			'the custom field change must be appended to the existing event, not logged as a new one'
		);

		$context = $this->get_context_for_event( $event_id );

		$this->assertSame( 1, (int) ( $context['post_meta_changed'] ?? 0 ) );
		$this->assertSame( array( 'color' ), json_decode( $context['post_meta_changed_keys'] ?? '', true ) );
	}

	/**
	 * When no event matches (nothing was logged for the REST request, or it
	 * simply doesn't exist), the meta-box-loader request must still log a
	 * new post_updated event containing just the meta change.
	 */
	public function test_meta_box_loader_request_without_matching_event_logs_new_event() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish', 'post_title' => 'No REST save' ) );
		add_post_meta( $post_id, 'color', 'blue' );

		// No REST save happened first — there is no post_updated event to
		// attach to.
		$count_before = $this->get_event_count();

		$meta_id = $this->get_meta_id( $post_id, 'color' );

		$this->run_meta_box_loader_request(
			$post_id,
			array(
				$meta_id => array(
					'key'   => 'color',
					'value' => 'pink',
				),
			)
		);

		$this->assertSame(
			$count_before + 1,
			$this->get_event_count(),
			'a new post_updated event must be logged when there is nothing to attach to'
		);

		$context = $this->get_context_for_event( $this->get_latest_event_id() );

		$this->assertSame( 'post_updated', $context['_message_key'] ?? null );
		$this->assertSame( (string) $post_id, $context['post_id'] ?? null );
		$this->assertSame( 1, (int) ( $context['post_meta_changed'] ?? 0 ) );
	}

	/**
	 * A meta-box-loader request from a different user than the one who made
	 * the matching event must not be attached to that user's event, even
	 * though post_id and post_modified_gmt both match.
	 */
	public function test_meta_box_loader_request_from_different_user_is_not_matched() {
		$other_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		add_post_meta( $post_id, 'color', 'blue' );

		// Request 2, logged as the first admin user.
		$this->log_rest_content_update( $post_id, 'content from the REST save' );

		$event_id     = $this->get_latest_event_id();
		$count_before = $this->get_event_count();

		// Request 3, as a different user. post_modified_gmt has not changed.
		wp_set_current_user( $other_user_id );

		$meta_id = $this->get_meta_id( $post_id, 'color' );

		$this->run_meta_box_loader_request(
			$post_id,
			array(
				$meta_id => array(
					'key'   => 'color',
					'value' => 'pink',
				),
			)
		);

		$this->assertSame(
			$count_before + 1,
			$this->get_event_count(),
			'a different user must get a new event, not an append to the first user\'s event'
		);

		$original_event_context = $this->get_context_for_event( $event_id );
		$this->assertArrayNotHasKey(
			'post_meta_changed',
			$original_event_context,
			'the first user\'s event must be left untouched'
		);

		$new_event_context = $this->get_context_for_event( $this->get_latest_event_id() );
		$this->assertSame( (string) $other_user_id, $new_event_context['_user_id'] ?? null );
	}

	/**
	 * A meta-box-loader request that produces no diff at all (nothing
	 * changed, or everything that changed is on the ignore list) must not
	 * touch the existing event or log a new one.
	 */
	public function test_meta_box_loader_request_with_no_changes_does_nothing() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		add_post_meta( $post_id, 'color', 'blue' );

		$this->log_rest_content_update( $post_id, 'content from the REST save' );

		$event_id     = $this->get_latest_event_id();
		$count_before = $this->get_event_count();

		// No 'meta' in $_POST at all — nothing for the meta box to save.
		$this->run_meta_box_loader_request( $post_id );

		$this->assertSame(
			$count_before,
			$this->get_event_count(),
			'no event should be logged when there is no diff'
		);

		$context = $this->get_context_for_event( $event_id );
		$this->assertArrayNotHasKey( 'post_meta_changed', $context );
		$this->assertArrayNotHasKey( 'post_meta_added', $context );
		$this->assertArrayNotHasKey( 'post_meta_removed', $context );
	}

	/**
	 * find_matching_post_updated_event_id() must require post_id,
	 * post_modified_gmt AND user to all match, newest first.
	 */
	public function test_find_matching_event_requires_post_modified_gmt_and_user_to_match() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$this->log_rest_content_update( $post_id, 'v1' );

		$event_id = $this->get_latest_event_id();
		$post     = get_post( $post_id );

		// Wrong post_modified_gmt: no match.
		$this->assertSame(
			0,
			$this->call_find_matching_event( $post_id, '2000-01-01 00:00:00', $this->admin_user_id )
		);

		// Wrong user: no match.
		$other_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->assertSame(
			0,
			$this->call_find_matching_event( $post_id, $post->post_modified_gmt, $other_user_id )
		);

		// Everything matches: found.
		$this->assertSame(
			$event_id,
			$this->call_find_matching_event( $post_id, $post->post_modified_gmt, $this->admin_user_id )
		);

		// A post with no post_updated event at all: no match.
		$other_post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$this->assertSame(
			0,
			$this->call_find_matching_event( $other_post_id, $post->post_modified_gmt, $this->admin_user_id )
		);
	}

	/**
	 * Item 6 of the design: the ACF ignore-keys callback must ignore not
	 * only the "_fieldname" reference key, but the "fieldname" value key it
	 * points to — but only during an actual ACF form submission
	 * ($_POST['acf'] set), where Plugin_ACF_Logger's own diff reports the
	 * field instead. See test_acf_ignore_callback_leaves_value_key_alone_outside_an_acf_form_submission().
	 *
	 * ACF itself is not loaded in the wpunit test suite, so this calls the
	 * callback directly rather than going through a real ACF save.
	 */
	public function test_acf_ignore_callback_ignores_both_reference_key_and_value_key() {
		$_POST['acf'] = array();

		$acf_logger = new Plugin_ACF_Logger( $this->sh );

		$old_meta = array();
		$new_meta = array(
			'my_field'  => array( 'Some value' ),
			'_my_field' => array( 'field_5f9a1b2c3d4e' ),
		);

		$ignored = $acf_logger->add_acf_meta_keys_to_ignore( array(), array(), $old_meta, $new_meta );

		$this->assertContains( '_my_field', $ignored, 'the reference key must still be ignored' );
		$this->assertContains( 'my_field', $ignored, 'the value key must now be ignored too' );
	}

	/**
	 * Finding 3 of the code review: outside an ACF form submission (no
	 * $_POST['acf']) — e.g. a WP-CLI or programmatic
	 * `wp_update_post( [ 'meta_input' => [...] ] )` update — ACF's own save
	 * routine never runs, so nothing else reports the field change. The
	 * value key must stay visible; only the reference key is ignored.
	 */
	public function test_acf_ignore_callback_leaves_value_key_alone_outside_an_acf_form_submission() {
		$acf_logger = new Plugin_ACF_Logger( $this->sh );

		$old_meta = array();
		$new_meta = array(
			'my_field'  => array( 'Some value' ),
			'_my_field' => array( 'field_5f9a1b2c3d4e' ),
		);

		$ignored = $acf_logger->add_acf_meta_keys_to_ignore( array(), array(), $old_meta, $new_meta );

		$this->assertContains( '_my_field', $ignored, 'the reference key is always ignored' );
		$this->assertNotContains( 'my_field', $ignored, 'the value key must not be ignored outside an ACF form submission' );
	}

	/**
	 * A key that merely looks like an ACF reference ("_orphan" with a
	 * field_-shaped value) but has no matching bare field in the meta set is
	 * not recognised as one, so neither key is ignored.
	 */
	public function test_acf_ignore_callback_leaves_unrelated_keys_alone() {
		$acf_logger = new Plugin_ACF_Logger( $this->sh );

		$new_meta = array(
			'_orphan' => array( 'field_5f9a1b2c3d4e' ),
		);

		$ignored = $acf_logger->add_acf_meta_keys_to_ignore( array(), array(), array(), $new_meta );

		$this->assertNotContains( '_orphan', $ignored );
		$this->assertNotContains( 'orphan', $ignored );
	}

	/**
	 * Finding 1 of the code review: the fallback log-a-new-event path (no
	 * matching event found) must respect the post type gate, same as every
	 * other save path — see ok_to_log_post_posttype() and the early exit it
	 * gives on_admin_action_editpost_meta_box_loader().
	 */
	public function test_meta_box_loader_request_for_skipped_posttype_logs_nothing() {
		$skip_filter = function ( $skip_posttypes ) {
			$skip_posttypes[] = 'post';

			return $skip_posttypes;
		};

		add_filter( 'simple_history/post_logger/skip_posttypes', $skip_filter );

		try {
			$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
			add_post_meta( $post_id, 'color', 'blue' );

			$count_before = $this->get_event_count();

			$meta_id = $this->get_meta_id( $post_id, 'color' );

			$this->run_meta_box_loader_request(
				$post_id,
				array(
					$meta_id => array(
						'key'   => 'color',
						'value' => 'pink',
					),
				)
			);

			$this->assertSame(
				$count_before,
				$this->get_event_count(),
				'a skipped post type must not produce an event from the meta-box-loader path'
			);
		} finally {
			remove_filter( 'simple_history/post_logger/skip_posttypes', $skip_filter );
		}
	}

	/**
	 * Finding 1 of the code review: the fallback log-a-new-event path must
	 * also respect the post_updated/ok_to_log filter, same as
	 * maybe_log_post_change().
	 */
	public function test_meta_box_loader_request_with_ok_to_log_false_logs_nothing() {
		add_filter( 'simple_history/post_logger/post_updated/ok_to_log', '__return_false' );

		try {
			$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
			add_post_meta( $post_id, 'color', 'blue' );

			// No REST save happened first, so this exercises the fallback
			// (no matching event) path.
			$count_before = $this->get_event_count();

			$meta_id = $this->get_meta_id( $post_id, 'color' );

			$this->run_meta_box_loader_request(
				$post_id,
				array(
					$meta_id => array(
						'key'   => 'color',
						'value' => 'pink',
					),
				)
			);

			$this->assertSame(
				$count_before,
				$this->get_event_count(),
				'ok_to_log returning false must prevent the fallback event from being logged'
			);
		} finally {
			remove_filter( 'simple_history/post_logger/post_updated/ok_to_log', '__return_false' );
		}
	}

	/**
	 * Finding 2 of the code review: when the matched event already has a
	 * post_meta_changed/_keys pair — here from "footnotes", a core,
	 * REST-registered post meta field changed by the earlier REST save —
	 * the meta-box-loader request's own change ("color") must be merged
	 * into it, ending with exactly one post_meta_changed row, not two
	 * (which would leave one set of names hidden on read).
	 */
	public function test_meta_box_loader_request_merges_into_existing_post_meta_changed_keys() {
		// Core registers "footnotes" as REST-updatable post meta on init
		// (see wp-includes/blocks/footnotes.php), but the WP test framework's
		// own tearDown() calls unregister_all_meta_keys() after every test —
		// init doesn't fire again between tests, so only the very first test
		// in the whole run would still have it registered. Register it here
		// too so this test doesn't depend on run order.
		register_post_meta(
			'post',
			'footnotes',
			array(
				'show_in_rest'      => true,
				'single'            => true,
				'type'              => 'string',
				'revisions_enabled' => true,
			)
		);

		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		add_post_meta( $post_id, 'footnotes', 'old footnotes' );
		add_post_meta( $post_id, 'color', 'blue' );

		// Request 2: the REST API save, changing "footnotes".
		$this->log_rest_content_and_meta_update(
			$post_id,
			'content from the REST save',
			array( 'footnotes' => 'new footnotes' )
		);

		$event_id = $this->get_latest_event_id();

		$context = $this->get_context_for_event( $event_id );
		$this->assertSame(
			array( 'footnotes' ),
			json_decode( $context['post_meta_changed_keys'] ?? '', true ),
			'precondition: the REST save must have logged the footnotes change'
		);

		$count_before = $this->get_event_count();

		// Request 3: the meta-box-loader request, changing "color".
		$meta_id = $this->get_meta_id( $post_id, 'color' );

		$this->run_meta_box_loader_request(
			$post_id,
			array(
				$meta_id => array(
					'key'   => 'color',
					'value' => 'pink',
				),
			)
		);

		$this->assertSame(
			$count_before,
			$this->get_event_count(),
			'the change must be merged into the existing event, not logged as a new one'
		);

		$this->assertSame(
			1,
			$this->get_context_row_count_for_key( $event_id, 'post_meta_changed' ),
			'there must be exactly one post_meta_changed row, not a duplicate'
		);

		$context = $this->get_context_for_event( $event_id );
		$this->assertSame( 2, (int) ( $context['post_meta_changed'] ?? 0 ) );
		$this->assertSame(
			array( 'footnotes', 'color' ),
			json_decode( $context['post_meta_changed_keys'] ?? '', true )
		);
	}

	/**
	 * Simulate request 2 of the block editor's Update click: a REST API save
	 * of the post content. Logs the post_updated event the meta-box-loader
	 * request (request 3) is expected to find and attach to.
	 *
	 * rest_do_request() doesn't define REST_REQUEST in the test environment
	 * (it's set during real HTTP bootstrap), so this hooks the
	 * simple_history/is_rest_request filter, same as PostLoggerRestCliTest.
	 */
	private function log_rest_content_update( int $post_id, string $content ): void {
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$post_id}" );
		$request->set_param( 'content', $content );
		rest_do_request( $request );

		remove_filter( 'simple_history/is_rest_request', '__return_true' );
	}

	/**
	 * Same as log_rest_content_update(), but also sets registered post meta
	 * via the REST "meta" param — e.g. "footnotes", which core registers as
	 * REST-updatable meta for post types that support the editor, custom
	 * fields, and revisions (see wp-includes/blocks/footnotes.php).
	 *
	 * @param array<string, mixed> $meta REST "meta" param value.
	 */
	private function log_rest_content_and_meta_update( int $post_id, string $content, array $meta ): void {
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$request = new WP_REST_Request( 'POST', "/wp/v2/posts/{$post_id}" );
		$request->set_param( 'content', $content );
		$request->set_param( 'meta', $meta );
		rest_do_request( $request );

		remove_filter( 'simple_history/is_rest_request', '__return_true' );
	}

	/**
	 * Simulate the meta-box-loader request: admin_action_editpost fires
	 * (snapshotting the "before" state), then edit_post() runs (writing the
	 * submitted meta and firing wp_after_insert_post).
	 *
	 * @param int   $post_id        Post being saved.
	 * @param array $meta_post_data $_POST['meta'] shaped array, e.g.
	 *                              [ $meta_id => [ 'key' => ..., 'value' => ... ] ].
	 */
	private function run_meta_box_loader_request( int $post_id, array $meta_post_data = array() ): void {
		$_GET['meta-box-loader'] = '1';

		$_POST = array(
			'post_ID' => (string) $post_id,
			'action'  => 'editpost',
		);

		if ( $meta_post_data ) {
			$_POST['meta'] = $meta_post_data;
		}

		do_action( 'admin_action_editpost' );

		edit_post();
	}

	/**
	 * @return int Real meta_id for a post meta row, or 0 if not found.
	 */
	private function get_meta_id( int $post_id, string $meta_key ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				$meta_key
			)
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

	private function get_latest_event_id(): int {
		global $wpdb;

		$db_table = $this->sh->get_events_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT MAX(id) FROM {$db_table} WHERE logger = %s",
				'SimplePostLogger'
			)
		);
	}

	/**
	 * @return array<string, string> Context as key => value, last write wins
	 *                                (matches how the plugin reads it back).
	 */
	private function get_context_for_event( int $event_id ): array {
		global $wpdb;

		$db_table_contexts = $this->sh->get_contexts_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `key`, value FROM {$db_table_contexts} WHERE history_id = %d ORDER BY context_id ASC",
				$event_id
			),
			ARRAY_A
		);

		$context = array();

		foreach ( $rows as $row ) {
			$context[ $row['key'] ] = $row['value'];
		}

		return $context;
	}

	/**
	 * Count how many context rows exist for a given event and key, to
	 * assert that a merge replaced an existing row instead of duplicating
	 * it (get_context_for_event() alone can't tell the two apart, since it
	 * only keeps the last value for a key).
	 */
	private function get_context_row_count_for_key( int $event_id, string $key ): int {
		global $wpdb;

		$db_table_contexts = $this->sh->get_contexts_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$db_table_contexts} WHERE history_id = %d AND `key` = %s",
				$event_id,
				$key
			)
		);
	}

	private function call_find_matching_event( int $post_id, string $post_modified_gmt, int $user_id ): int {
		$method = new ReflectionMethod( Post_Logger::class, 'find_matching_post_updated_event_id' );
		$method->setAccessible( true );

		return $method->invoke( $this->logger, $post_id, $post_modified_gmt, $user_id );
	}
}
