<?php

use Simple_History\Simple_History;
use Simple_History\Loggers\Comments_Logger;

/**
 * How the comments logger handles the post a comment belongs to.
 *
 * A comment can outlive its post, and a post can legitimately have no title, and
 * the logger has to tell those apart: one should read as deleted, the other
 * should not. Both used to be handled wrong — reading properties off a null post
 * emitted PHP warnings, and the first fix labelled untitled posts as deleted.
 *
 * @coversDefaultClass Simple_History\Loggers\Comments_Logger
 */
class CommentsLoggerParentPostTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var Comments_Logger
	 */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = new Comments_Logger( Simple_History::get_instance() );
	}

	/**
	 * Render a comment event the way the log does.
	 *
	 * @param array  $context Context for the row.
	 * @param string $message_key Message key to render.
	 * @return string Rendered message.
	 */
	private function render( $context, $message_key = 'user_comment_added' ) {
		$messages = $this->logger->get_info()['messages'];

		$row = (object) array(
			'message' => $messages[ $message_key ],
			'context' => array_merge( $context, array( '_message_key' => $message_key ) ),
		);

		return $this->logger->get_log_row_plain_text_output( $row );
	}

	/**
	 * Delete a post row directly, leaving its comments behind.
	 *
	 * wp_delete_post() would take the comments with it, which is the opposite of
	 * the state under test.
	 *
	 * @param int $post_id Post to orphan the comments of.
	 */
	private function delete_post_row( $post_id ) {
		global $wpdb;

		$wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );

		clean_post_cache( $post_id );
		wp_cache_flush();
	}

	/**
	 * A comment whose post is gone still logs, without a PHP warning.
	 *
	 * Regression test for the support report of "Attempt to read property
	 * post_type on null" — get_post() returns null for an orphaned comment and
	 * the context was built by reading properties off it.
	 */
	function test_context_for_comment_with_deleted_post_has_no_warning() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Post that will be deleted' ) );
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->delete_post_row( $post_id );

		$context = $this->logger->get_context_for_comment( $comment_id );

		$this->assertIsArray( $context, 'The comment should still be loggable without its post' );
		$this->assertSame( '', $context['comment_post_title'], 'No title can be captured for a post that is gone' );
		$this->assertSame( '', $context['comment_post_type'], 'No post type can be captured either' );
	}

	/**
	 * An unattached comment must not borrow the global post.
	 *
	 * get_post() falls back to $GLOBALS['post'] when handed an empty id, so a
	 * comment with comment_post_ID 0 would otherwise be logged with the title and
	 * type of whatever post happened to be global during the request.
	 */
	function test_context_for_unattached_comment_does_not_use_global_post() {
		$unrelated_post_id = $this->factory->post->create( array( 'post_title' => 'Unrelated global post' ) );
		$GLOBALS['post'] = get_post( $unrelated_post_id );

		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => 0 ) );

		$context = $this->logger->get_context_for_comment( $comment_id );

		unset( $GLOBALS['post'] );

		$this->assertSame( '', $context['comment_post_title'], 'An unattached comment must not pick up the global post title' );
		$this->assertNotEquals( 'Unrelated global post', $context['comment_post_title'] );
	}

	/**
	 * A missing post renders as deleted rather than as an empty title.
	 */
	function test_message_for_deleted_post_renders_placeholder() {
		$post_id = $this->factory->post->create();
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$this->delete_post_row( $post_id );

		$message = $this->render( $this->logger->get_context_for_comment( $comment_id ) );

		$this->assertStringContainsString( '(deleted)', $message, 'A comment on a deleted post should say so' );
		$this->assertStringNotContainsString( '""', $message, 'The message should never render an empty title' );
	}

	/**
	 * A post with no title is not a deleted post.
	 *
	 * Regression test: the first version of the fix keyed the placeholder off an
	 * empty title, but attachments and the aside/status/quote post formats
	 * legitimately have none. Those were labelled "(deleted)" while the post sat
	 * right there — and worse, the title was then linked to it.
	 */
	function test_message_for_untitled_but_existing_post_has_no_placeholder() {
		$post_id = $this->factory->post->create( array( 'post_title' => '' ) );
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$message = $this->render( $this->logger->get_context_for_comment( $comment_id ), 'comment_status_approve' );

		$this->assertStringNotContainsString( '(deleted)', $message, 'An untitled post that exists must not be called deleted' );
	}

	/**
	 * A post deleted after the event keeps the title recorded at the time.
	 *
	 * The placeholder is only for events that never captured a title, so the log
	 * stays a record of what was true when it happened.
	 */
	function test_message_keeps_title_recorded_before_post_was_deleted() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Title recorded while alive' ) );
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		// Context captured while the post still existed, as it would be at log time.
		$context = $this->logger->get_context_for_comment( $comment_id );

		$this->delete_post_row( $post_id );

		$message = $this->render( $context, 'comment_status_approve' );

		$this->assertStringContainsString( 'Title recorded while alive', $message, 'The recorded title should survive the post' );
		$this->assertStringNotContainsString( '(deleted)', $message, 'A recorded title must not be replaced by the placeholder' );
	}

	/**
	 * Nothing links to a post that no longer exists.
	 */
	function test_message_for_deleted_post_is_not_linked() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'Soon gone' ) );
		$comment_id = $this->factory->comment->create( array( 'comment_post_ID' => $post_id ) );

		$context = $this->logger->get_context_for_comment( $comment_id );

		$this->delete_post_row( $post_id );

		$message = $this->render( $context, 'comment_status_approve' );

		$this->assertStringNotContainsString( '<a href', $message, 'A deleted post must not be linked' );
	}
}
