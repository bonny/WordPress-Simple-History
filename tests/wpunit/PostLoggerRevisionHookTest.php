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
}
