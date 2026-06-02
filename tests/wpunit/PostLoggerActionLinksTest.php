<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Post_Logger;

/**
 * Tests for Post_Logger::get_action_links().
 *
 * Covers:
 * - Per-post links (Edit / View / Preview / Revisions) and their status gates.
 * - Overview "All <plural>" link, gated by the post type's edit_posts cap.
 * - post_deleted: per-post block suppressed, overview link still rendered
 *   from the $context['post_type'] fallback.
 * - Custom post types use registered plural label and own cap mapping.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit PostLoggerActionLinksTest
 */
class PostLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use ActionLinksTestTrait;

	/** @var Post_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->logger_slug = 'SimplePostLogger';

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( $this->logger_slug );

		$this->admin_user_id      = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $this->admin_user_id );
	}

	/* -------------------------------------------------------------------- */
	/* Per-post links — Edit / View / Preview / Revisions                   */
	/* -------------------------------------------------------------------- */

	public function test_published_post_shows_edit_view_and_overview() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish', 'post_type' => 'post' ] );

		$row = $this->build_row( [
			'_message_key' => 'post_updated',
			'post_id'      => (string) $post_id,
			'post_type'    => 'post',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Edit post', $labels );
		$this->assertContains( 'View post', $labels );
		$this->assertContains( 'All posts', $labels );
		$this->assertNotContains( 'Preview post', $labels, 'Preview only for non-published statuses' );
	}

	public function test_draft_post_shows_preview_not_view() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'draft', 'post_type' => 'post' ] );

		$row = $this->build_row( [
			'_message_key' => 'post_updated',
			'post_id'      => (string) $post_id,
			'post_type'    => 'post',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Edit post', $labels );
		$this->assertContains( 'Preview post', $labels );
		$this->assertNotContains( 'View post', $labels, 'No public permalink for drafts' );
	}

	public function test_post_updated_with_revisions_renders_revisions_link() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish', 'post_type' => 'post' ] );

		// Create at least one revision.
		wp_save_post_revision( $post_id );

		$row = $this->build_row( [
			'_message_key' => 'post_updated',
			'post_id'      => (string) $post_id,
			'post_type'    => 'post',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Revisions', $labels );
	}

	public function test_post_created_does_not_render_revisions_link() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish', 'post_type' => 'post' ] );

		// Even if revisions exist, post_created should not surface the revisions link.
		wp_save_post_revision( $post_id );

		$row = $this->build_row( [
			'_message_key' => 'post_created',
			'post_id'      => (string) $post_id,
			'post_type'    => 'post',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertNotContains( 'Revisions', $labels );
	}

	/* -------------------------------------------------------------------- */
	/* post_deleted — per-post suppressed, overview survives                */
	/* -------------------------------------------------------------------- */

	public function test_post_deleted_suppresses_per_post_links_but_keeps_overview() {
		// Deleted posts no longer exist — but we still get a sensible overview link.
		$row = $this->build_row( [
			'_message_key' => 'post_deleted',
			'post_id'      => '99999',
			'post_type'    => 'post',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertNotContains( 'Edit post', $labels );
		$this->assertNotContains( 'View post', $labels );
		$this->assertContains( 'All posts', $labels );
	}

	public function test_post_deleted_for_custom_post_type_uses_registered_plural() {
		register_post_type( 'sh_book', [
			'public'        => true,
			'show_ui'       => true,
			'labels'        => [
				'name'          => 'Books',
				'singular_name' => 'Book',
			],
			'capability_type' => 'post',
		] );

		try {
			$row = $this->build_row( [
				'_message_key' => 'post_deleted',
				'post_id'      => '99999',
				'post_type'    => 'sh_book',
			] );

			$links  = $this->logger->get_action_links( $row );
			$labels = wp_list_pluck( $links, 'label' );

			$this->assertContains( 'All books', $labels );

			$overview = $this->find_by_label( $links, 'All books' );
			$this->assertNotNull( $overview );
			$this->assertStringContainsString( 'edit.php?post_type=sh_book', $overview['url'] );
		} finally {
			unregister_post_type( 'sh_book' );
		}
	}

	/* -------------------------------------------------------------------- */
	/* Capability gating                                                    */
	/* -------------------------------------------------------------------- */

	public function test_subscriber_without_edit_posts_gets_no_overview_link() {
		wp_set_current_user( $this->subscriber_user_id );

		$post_id = $this->factory->post->create( [ 'post_status' => 'publish', 'post_type' => 'post' ] );

		$row = $this->build_row( [
			'_message_key' => 'post_updated',
			'post_id'      => (string) $post_id,
			'post_type'    => 'post',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertNotContains( 'All posts', $labels );
		$this->assertNotContains( 'Edit post', $labels, 'Subscriber lacks edit_post on a post they did not author' );
	}

}
