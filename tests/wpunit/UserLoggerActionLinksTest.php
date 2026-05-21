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
 * - "All users" overview link only on user-management events (profile, created, deleted).
 * - Auth events (login, logout, failed login, session) do NOT show "All users".
 * - "All users" gated by list_users capability.
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

	public function test_auth_events_do_not_show_overview() {
		// Login / logout / failed-login are auth events — "All users" is not relevant.
		foreach ( [ 'user_logged_in', 'user_logged_out', 'user_login_failed', 'user_session_destroy_others', 'user_session_destroy_everywhere' ] as $message_key ) {
			$row    = $this->build_row( [ '_message_key' => $message_key ] );
			$links  = $this->logger->get_action_links( $row );
			$labels = wp_list_pluck( $links, 'label' );

			$this->assertNotContains( 'Edit user', $labels, "Edit user should not appear for {$message_key}" );
			$this->assertNotContains( 'All users', $labels, "All users should not appear for {$message_key}" );
		}
	}

	public function test_user_deleted_shows_overview() {
		$row    = $this->build_row( [ '_message_key' => 'user_deleted' ] );
		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

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

		// Use a user-management event so we're testing the capability gate, not the message-key gate.
		$row = $this->build_row( [
			'_message_key'   => 'user_updated_profile',
			'edited_user_id' => (string) $this->editor_user_id,
		] );

		$links  = $this->logger->get_action_links( $row );
		$labels = wp_list_pluck( $links, 'label' );

		$this->assertNotContains( 'All users', $labels, 'Subscriber lacks list_users — no overview link' );
	}

}
