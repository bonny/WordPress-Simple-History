<?php

use Simple_History\Simple_History;

/**
 * The _wp_put_post_revision handler must survive every supported WordPress.
 *
 * Core only started passing the post id with that action in 6.4:
 *
 *   @since 2.6.0
 *   @since 6.4.0 The `$post_id` parameter was added.
 *
 * The plugin supports 6.3, where core fires the action with a single argument.
 * Declaring the second parameter as required made that a fatal
 * ArgumentCountError under PHP 8 — on any post save that creates a revision,
 * which is most of them.
 *
 * These tests only fail on WordPress below 6.4. They are kept because that is
 * the floor the plugin advertises, and because the failure is silent on a
 * modern development site.
 *
 * @coversDefaultClass Simple_History\Loggers\Post_Logger
 */
class PostLoggerRevisionHookTest extends \Codeception\TestCase\WPTestCase {
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	/**
	 * Saving a revision must not fatal, however many arguments core sends.
	 */
	function test_saving_a_revision_does_not_error() {
		$post_id = $this->factory->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => 'First version',
			]
		);

		// Fatals with "Too few arguments" on WordPress < 6.4 when the handler
		// declares $post_id as required.
		$revision_id = wp_save_post_revision( $post_id );

		$this->assertNotNull( $revision_id, 'Saving a revision should complete without a fatal' );
	}

	/**
	 * Updating a post is the path real users take to this hook.
	 */
	function test_updating_a_post_does_not_error() {
		$post_id = $this->factory->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => 'First version',
			]
		);

		wp_update_post(
			[
				'ID'           => $post_id,
				'post_content' => 'Second version, which creates a revision',
			]
		);

		$this->assertNotEmpty( wp_get_post_revisions( $post_id ), 'The update should have created a revision' );
	}

	/**
	 * Calling the handler with one argument works, as core does before 6.4.
	 *
	 * Asserted directly as well as through core, so the contract is pinned even
	 * when the suite runs on a WordPress that passes both arguments.
	 */
	function test_handler_accepts_being_called_with_only_a_revision_id() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish' ] );
		$revision_id = wp_save_post_revision( $post_id );

		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		$this->assertNotEmpty( $logger, 'Sanity check: the post logger should be loaded' );

		// Would throw ArgumentCountError if the second parameter were required.
		$logger->on_wp_put_post_revision( $revision_id );

		$this->assertTrue( true, 'Handler accepted a single argument' );
	}

	/**
	 * Log an update to a post through the funnel every logging path uses.
	 *
	 * The logger only records post changes from admin, REST and WP-CLI requests,
	 * none of which a plain unit test is, so the change is driven through
	 * maybe_log_post_change() directly.
	 *
	 * @param int $post_id Post that was updated.
	 * @return array Context of the logged event.
	 */
	private function log_post_update( $post_id ) {
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		$old_post = get_post( $post_id );

		// Something has to actually differ, or the logger decides there is
		// nothing worth logging and never sets a context.
		$new_post               = clone $old_post;
		$new_post->post_content = $old_post->post_content . ' — changed';

		$logger->last_insert_context = null;

		// The logger declines to log outside admin, REST and WP-CLI requests, and
		// a unit test is none of those. This is the documented filter for
		// overriding that decision, so no globals need faking.
		$force_logging = '__return_true';

		add_filter( 'simple_history/post_logger/post_updated/ok_to_log', $force_logging, 99 );

		$logger->maybe_log_post_change(
			[
				'new_post'       => $new_post,
				'new_post_meta'  => get_post_custom( $post_id ),
				'new_post_terms' => [],
				'old_post'       => $old_post,
				'old_post_meta'  => get_post_custom( $post_id ),
				'old_post_terms' => [],
				'old_status'     => $old_post->post_status,
			]
		);

		remove_filter( 'simple_history/post_logger/post_updated/ok_to_log', $force_logging, 99 );

		$this->assertNotEmpty(
			$logger->last_insert_context,
			'Sanity check: the change should have been logged, otherwise this test proves nothing'
		);

		return (array) $logger->last_insert_context;
	}

	/**
	 * The revision id ends up on the event it belongs to.
	 *
	 * Core saves the revision before this logger logs — both revision paths run
	 * first — so the id has to be held and picked up when the context is built.
	 * Reading it back off last_insert_id, as the original code did, meant it was
	 * never recorded on any WordPress version, and the "view revision" link in
	 * the compact event list never appeared.
	 */
	function test_revision_id_is_recorded_on_the_logged_event() {
		$post_id = $this->factory->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => 'First version',
			]
		);

		// Creates the revision, which fires _wp_put_post_revision before anything
		// is logged — the ordering that made this fail.
		$revision_id = wp_save_post_revision( $post_id );

		$this->assertNotNull( $revision_id, 'Sanity check: a revision should have been created' );

		$context = $this->log_post_update( $post_id );

		$this->assertArrayHasKey( 'post_revision_id', $context, 'The event should carry the revision id' );
		$this->assertEquals( $revision_id, $context['post_revision_id'] );
	}

	/**
	 * An event with no revision before it carries no revision id.
	 *
	 * Guards the held id from leaking onto an unrelated event.
	 */
	function test_event_without_a_revision_has_no_revision_id() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish' ] );

		$context = $this->log_post_update( $post_id );

		$this->assertArrayNotHasKey( 'post_revision_id', $context, 'Nothing to point at, so nothing recorded' );
	}

	/**
	 * The held id is consumed, not reused.
	 *
	 * A second change to the same post in one request must not inherit the first
	 * one's revision.
	 */
	function test_revision_id_is_not_reused_for_a_later_event() {
		$post_id = $this->factory->post->create(
			[
				'post_status'  => 'publish',
				'post_content' => 'First version',
			]
		);

		wp_save_post_revision( $post_id );

		$first_context = $this->log_post_update( $post_id );

		$this->assertArrayHasKey( 'post_revision_id', $first_context, 'Sanity check: the first event should get it' );

		$second_context = $this->log_post_update( $post_id );

		$this->assertArrayNotHasKey(
			'post_revision_id',
			$second_context,
			'A later event must not inherit the revision from an earlier one'
		);
	}
}
