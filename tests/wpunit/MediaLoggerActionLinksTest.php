<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Media_Logger;

/**
 * Tests for Media_Logger::get_action_links().
 *
 * Covers:
 * - Existing attachment: Edit + View per-item links and the "All media" overview.
 * - attachment_deleted: per-item links suppressed but overview survives.
 * - Missing-attachment fallback: when get_post() returns null but the event
 *   is not a deletion, per-item links are skipped and only the overview
 *   shows (matches the new "fall through to overview" behavior).
 * - Capability gating on overview via upload_files.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit MediaLoggerActionLinksTest
 */
class MediaLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use ActionLinksTestTrait;

	/** @var Media_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->logger_slug = 'SimpleMediaLogger';

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( $this->logger_slug );

		$this->admin_user_id      = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $this->admin_user_id );
	}

	public function test_existing_attachment_shows_edit_view_and_overview() {
		$attachment_id = $this->factory->attachment->create( [
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
		] );

		$row = $this->build_row( [
			'_message_key'  => 'attachment_updated',
			'attachment_id' => (string) $attachment_id,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Edit attachment', $labels );
		$this->assertContains( 'View attachment', $labels );
		$this->assertContains( 'All media', $labels );
	}

	public function test_attachment_deleted_suppresses_per_item_keeps_overview() {
		$row = $this->build_row( [
			'_message_key'  => 'attachment_deleted',
			'attachment_id' => '999999',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertNotContains( 'Edit attachment', $labels );
		$this->assertNotContains( 'View attachment', $labels );
		$this->assertContains( 'All media', $labels );

		$overview = $this->find_by_label( $links, 'All media' );
		$this->assertStringEndsWith( '/wp-admin/upload.php', $overview['url'] );
	}

	public function test_missing_attachment_post_falls_through_to_overview_only() {
		// Attachment ID points to nothing — get_post() returns null.
		// Per-item links should be skipped silently and overview rendered.
		$row = $this->build_row( [
			'_message_key'  => 'attachment_updated',
			'attachment_id' => '999999',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertSame( [ 'All media' ], $labels );
	}

	public function test_subscriber_without_upload_files_gets_no_overview() {
		wp_set_current_user( $this->subscriber_user_id );

		$row = $this->build_row( [
			'_message_key'  => 'attachment_deleted',
			'attachment_id' => '999999',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertSame( [], $links, 'Subscriber lacks upload_files — no overview link' );
	}

}
