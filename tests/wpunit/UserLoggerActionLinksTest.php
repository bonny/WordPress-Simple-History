<?php

require_once 'functions.php';
require_once __DIR__ . '/_action_links_trait.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;

/**
 * Tests for User_Logger::get_action_links().
 *
 * Covers:
 * - user_updated_profile / user_created surface "Edit user" for an existing target user.
 * - Non-user-targeting messages (e.g. login events) skip per-user link.
 * - "All users" overview link gated by list_users capability.
 * - Deleted target user: per-user link suppressed, overview still rendered.
 *
 * Run with:
 *   docker compose run --rm php-cli vendor/bin/codecept run wpunit UserLoggerActionLinksTest
 */
class UserLoggerActionLinksTest extends \Codeception\TestCase\WPTestCase {
	use ActionLinksTestTrait;

	/** @var User_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $editor_user_id;

	/** @var int */
	private $subscriber_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->logger_slug = 'SimpleUserLogger';

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( $this->logger_slug );

		$this->admin_user_id      = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$this->editor_user_id     = $this->factory->user->create( [ 'role' => 'editor' ] );
		$this->subscriber_user_id = $this->factory->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $this->admin_user_id );
	}

	public function test_user_updated_profile_shows_edit_user_and_overview() {
		$row = $this->build_row( [
			'_message_key'    => 'user_updated_profile',
			'edited_user_id'  => (string) $this->editor_user_id,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Edit user', $labels );
		$this->assertContains( 'All users', $labels );
	}

	public function test_user_created_shows_edit_user_and_overview() {
		$row = $this->build_row( [
			'_message_key'    => 'user_created',
			'created_user_id' => (string) $this->editor_user_id,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertContains( 'Edit user', $labels );
		$this->assertContains( 'All users', $labels );
	}

	public function test_non_user_targeting_message_only_shows_overview() {
		// e.g. login / logout events don't carry an edited_user_id /
		// created_user_id; still surface the overview link.
		$row = $this->build_row( [
			'_message_key' => 'user_logged_in',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertNotContains( 'Edit user', $labels );
		$this->assertContains( 'All users', $labels );
	}

	public function test_deleted_target_user_keeps_overview_drops_edit() {
		// Reference a user id that no longer exists.
		$row = $this->build_row( [
			'_message_key'    => 'user_updated_profile',
			'edited_user_id'  => '999999',
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertNotContains( 'Edit user', $labels );
		$this->assertContains( 'All users', $labels );
	}

	public function test_subscriber_without_list_users_gets_no_overview() {
		wp_set_current_user( $this->subscriber_user_id );

		$row = $this->build_row( [
			'_message_key' => 'user_logged_in',
		] );

		$links = $this->logger->get_action_links( $row );

		$this->assertSame( [], $links, 'Subscriber lacks list_users — no overview link' );
	}

}
