<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;

/**
 * Note events get the same action links as the post or page the note sits
 * on (Edit, View or Preview, All pages), but never the revision link: a
 * note does not create a revision.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit NotesLoggerActionLinksTest
 */
class NotesLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use ActionLinksTestTrait;

	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger_slug = 'NotesLogger';
		$this->logger      = Simple_History::get_instance()->get_instantiated_logger_by_slug( $this->logger_slug );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_note_on_published_page_links_to_edit_view_and_all_pages() {
		$page_id = $this->factory->post->create( [ 'post_status' => 'publish', 'post_type' => 'page' ] );

		$links  = $this->logger->get_action_links( $this->build_row( [
			'_message_key' => 'note_added',
			'note_id'      => '5',
			'post_id'      => (string) $page_id,
			'post_type'    => 'page',
		] ) );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Edit page', $labels );
		$this->assertContains( 'View page', $labels );
		$this->assertContains( 'All pages', $labels );
		$this->assertNotContains( 'Preview page', $labels );
	}

	public function test_note_on_draft_post_links_to_preview_not_view() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'draft', 'post_type' => 'post' ] );

		$links  = $this->logger->get_action_links( $this->build_row( [
			'_message_key' => 'note_resolved',
			'note_id'      => '5',
			'post_id'      => (string) $post_id,
			'post_type'    => 'post',
		] ) );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Edit post', $labels );
		$this->assertContains( 'Preview post', $labels );
		$this->assertNotContains( 'View post', $labels );
	}

	public function test_note_events_never_get_a_revision_link() {
		$post_id = $this->factory->post->create( [ 'post_status' => 'publish', 'post_type' => 'post' ] );
		wp_save_post_revision( $post_id );

		$links   = $this->logger->get_action_links( $this->build_row( [
			'_message_key' => 'note_edited',
			'note_id'      => '5',
			'post_id'      => (string) $post_id,
			'post_type'    => 'post',
		] ) );
		$actions = wp_list_pluck( $links, 'action' );

		$this->assertNotContains( 'revisions', $actions );
	}

	public function test_note_on_a_deleted_post_only_gets_the_overview_link() {
		$links  = $this->logger->get_action_links( $this->build_row( [
			'_message_key' => 'note_added',
			'note_id'      => '5',
			'post_id'      => '999999',
			'post_type'    => 'page',
		] ) );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All pages' ], array_values( $labels ) );
	}

	public function test_no_links_without_edit_capability() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'subscriber' ] ) );
		$page_id = $this->factory->post->create( [ 'post_status' => 'publish', 'post_type' => 'page' ] );

		$links = $this->logger->get_action_links( $this->build_row( [
			'_message_key' => 'note_added',
			'note_id'      => '5',
			'post_id'      => (string) $page_id,
			'post_type'    => 'page',
		] ) );

		$this->assertNotContains( 'Edit page', wp_list_pluck( $links, 'label' ) );
		$this->assertNotContains( 'All pages', wp_list_pluck( $links, 'label' ) );
	}
}
