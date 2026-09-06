<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;
use function Simple_History\tests\get_latest_context;

/**
 * Issue 294: the block editor persists editor preferences by POSTing user meta
 * to /wp/v2/users/me. WP_REST_Users_Controller::update_item() then calls
 * wp_update_user() unconditionally, so profile_update fires although nothing
 * in the users table changed. Those must not become "Edited your profile"
 * events with no changed fields.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit UserLoggerGhostProfileEditTest
 */
class UserLoggerGhostProfileEditTest extends \Codeception\TestCase\WPTestCase {
	/** @var int */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// rest_do_request() doesn't define REST_REQUEST in the test environment.
		add_filter( 'simple_history/is_rest_request', '__return_true' );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_meta_only_rest_update_of_own_user_does_not_log_profile_edit() {
		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', '/wp/v2/users/me' );
		$request->set_body_params(
			array(
				'meta' => array(
					'persisted_preferences' => array(
						'_modified'      => '2026-09-02T10:00:00.000Z',
						'core/edit-post' => array( 'isComplementaryAreaVisible' => true ),
					),
				),
			)
		);
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'REST meta update should succeed' );

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'A REST request that only writes user meta must not log a profile edit'
		);
	}

	public function test_rest_update_with_no_changed_fields_does_not_log_profile_edit() {
		$count_before = $this->get_event_count();

		$request  = new WP_REST_Request( 'POST', "/wp/v2/users/{$this->admin_user_id}" );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'A REST request that changes nothing must not log a profile edit'
		);
	}

	public function test_rest_update_with_a_real_change_still_logs() {
		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', "/wp/v2/users/{$this->admin_user_id}" );
		$request->set_param( 'name', 'Changed Display Name' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'A REST request that changes a users-table field must still log'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'user_updated_profile' );
		$this->assert_context_has( $context, 'user_new_display_name', 'Changed Display Name' );
	}

	public function test_rest_password_change_still_logs() {
		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', "/wp/v2/users/{$this->admin_user_id}" );
		$request->set_param( 'password', 'NewP@ssw0rd!' . wp_generate_password( 6, false ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status() );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'A password change must still log even though it produces no field diff'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, 'edited_user_password_changed', '1' );
	}

	/**
	 * user-edit.php posts every profile field, including role and any fields
	 * plugins add to the form. Keys that are not WP_User properties resolve to
	 * empty user meta on the old user object, so they produced a "" -> value
	 * diff on every save and the guard above let the ghost event through.
	 */
	public function test_saving_another_user_with_unchanged_role_does_not_log_profile_edit() {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		$editor    = get_user_by( 'ID', $editor_id );

		$count_before = $this->get_event_count();

		// The shape edit_user() hands to wp_update_user() when nothing was changed.
		wp_update_user(
			array(
				'ID'           => $editor_id,
				'role'         => 'editor',
				'user_email'   => $editor->user_email,
				'display_name' => $editor->display_name,
				'first_name'   => '',
				'last_name'    => '',
				'nickname'     => $editor->nickname,
			)
		);

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'Saving another user without changing anything must not log a profile edit'
		);
	}

	public function test_unknown_plugin_field_does_not_log_profile_edit() {
		$editor_id    = $this->factory->user->create( array( 'role' => 'editor' ) );
		$count_before = $this->get_event_count();

		wp_update_user(
			array(
				'ID'                 => $editor_id,
				'infinite_scrolling' => 'true',
			)
		);

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'A field the logger does not track must not produce a profile edit'
		);
	}

	public function test_role_change_still_logs() {
		$editor_id    = $this->factory->user->create( array( 'role' => 'editor' ) );
		$count_before = $this->get_event_count();

		wp_update_user(
			array(
				'ID'   => $editor_id,
				'role' => 'author',
			)
		);

		// Under REST the set_user_role handler logs a user_role_updated event
		// as well, so only require that the profile edit was logged on top.
		$this->assertGreaterThan( $count_before, $this->get_event_count(), 'A role change must still log' );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'user_updated_profile' );
		$this->assert_context_has( $context, 'user_added_roles', 'author' );
		$this->assert_context_has( $context, 'user_removed_roles', 'editor' );
		$this->assertNotContains(
			'user_prev_role',
			array_column( $context, 'key' ),
			'role must not be diffed as a plain field; the roles diff covers it'
		);
	}

	public function test_core_profile_meta_change_still_logs() {
		$editor_id    = $this->factory->user->create( array( 'role' => 'editor' ) );
		$count_before = $this->get_event_count();

		wp_update_user(
			array(
				'ID'         => $editor_id,
				'first_name' => 'Erik',
			)
		);

		$this->assertEquals( $count_before + 1, $this->get_event_count(), 'A first name change must still log' );
		$this->assert_context_has( get_latest_context(), 'user_new_first_name', 'Erik' );
	}

	private function assert_context_has( array $context, string $key, string $value ): void {
		$this->assertContains(
			array( 'key' => $key, 'value' => $value ),
			$context,
			sprintf( 'Context should contain %s=%s', $key, $value )
		);
	}

	private function get_event_count(): int {
		global $wpdb;
		$table = Simple_History::get_instance()->get_events_table_name();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
