<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\User_Logger;
use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Tests that User_Logger captures profile updates made via REST API and WP-CLI.
 *
 * Issue #185: on_pre_insert_user_data_collect() bails when get_current_screen()
 * returns null (no screen under REST or WP-CLI). With no snapshot, the commit
 * step also bails, producing no event.
 *
 * Affected: wp user update (display_name, user_pass, user_email),
 *           wp user reset-password, REST POST /wp/v2/users/<id>.
 * Unaffected: wp user create/delete, wp user add-role/remove-role (those
 *             already have explicit WP_CLI hooks).
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit UserLoggerRestCliTest
 */
class UserLoggerRestCliTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $sh;

	/** @var User_Logger */
	private $logger;

	/** @var int */
	private $admin_user_id;

	/** @var int */
	private $target_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		$this->admin_user_id  = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$this->target_user_id = $this->factory->user->create( array(
			'role'         => 'subscriber',
			'display_name' => 'Original Name',
			'user_email'   => 'original@example.com',
		) );

		wp_set_current_user( $this->admin_user_id );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_wp_cli' );
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_logger_exists_and_is_loaded() {
		$this->assertNotNull( $this->logger );
		$this->assertInstanceOf( User_Logger::class, $this->logger );
	}

	/**
	 * Negative: wp_update_user() from an arbitrary context (e.g. plugin code)
	 * should NOT log, to avoid noise from background processes.
	 *
	 * NOTE: Whether to allow all wp_update_user() calls or gate by source is a
	 * decision noted in the issue. This test encodes the conservative choice
	 * (allowlist). If the decision is to log all updates, delete this test.
	 */
	public function test_does_not_log_user_update_from_arbitrary_context() {
		$count_before = $this->get_event_count();

		wp_update_user( array(
			'ID'           => $this->target_user_id,
			'display_name' => 'Changed by plugin code',
		) );

		$this->assertEquals(
			$count_before,
			$this->get_event_count(),
			'User_Logger must not log wp_update_user() from a non-admin/REST/CLI context'
		);
	}

	/**
	 * Regression guard: a profile update from within wp-admin (user-edit screen)
	 * must still log. Our refactor restructured the gate to is_admin_user_screen
	 * OR REST OR CLI — verify the admin path didn't break.
	 */
	public function test_admin_profile_update_still_logs() {
		set_current_screen( 'user-edit' );
		$this->assertTrue( is_admin(), 'is_admin() must be true for this test to be meaningful' );

		$count_before = $this->get_event_count();

		wp_update_user( array(
			'ID'           => $this->target_user_id,
			'display_name' => 'Admin Updated Name ' . wp_generate_password( 4, false ),
		) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'Admin profile update must produce exactly one log row'
		);

		set_current_screen( 'front' );
	}

	/**
	 * REST: POST /wp/v2/users/<id> with a display_name change must produce a
	 * user_updated_profile event.
	 */
	public function test_logs_user_display_name_change_via_rest_api() {
		// rest_do_request() doesn't define REST_REQUEST in the test environment
		// (it's set during real HTTP bootstrap), so hook the filter to simulate it.
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', "/wp/v2/users/{$this->target_user_id}" );
		$request->set_param( 'name', 'REST Updated Name' );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'REST user update should succeed' );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'REST user update must produce exactly one log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleUserLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'user_updated_profile' );
	}

	/**
	 * REST: password changes via REST must be logged with the password-changed flag.
	 *
	 * Currently FAILS — same screen-guard root cause.
	 */
	public function test_logs_password_change_via_rest_api() {
		add_filter( 'simple_history/is_rest_request', '__return_true' );

		$count_before = $this->get_event_count();

		$request = new WP_REST_Request( 'POST', "/wp/v2/users/{$this->target_user_id}" );
		$request->set_param( 'password', 'NewP@ssw0rd!' . wp_generate_password( 6, false ) );
		$response = rest_do_request( $request );

		$this->assertEquals( 200, $response->get_status(), 'REST user password update should succeed' );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'REST password change must produce a log row'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, 'edited_user_password_changed', '1' );
	}

	/**
	 * WP-CLI: wp_update_user() with WP_CLI=true must produce a log row.
	 *
	 * Currently FAILS — same screen-guard root cause as REST.
	 */
	public function test_logs_user_display_name_change_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$count_before = $this->get_event_count();

		wp_update_user( array(
			'ID'           => $this->target_user_id,
			'display_name' => 'CLI Updated Name',
		) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_update_user() must produce a user_updated_profile log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleUserLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'user_updated_profile' );
	}

	/**
	 * WP-CLI: password changes via wp_update_user() with WP_CLI=true must log
	 * the password-changed flag.
	 *
	 * Currently FAILS — same root cause.
	 */
	public function test_logs_password_change_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$count_before = $this->get_event_count();

		wp_update_user( array(
			'ID'        => $this->target_user_id,
			'user_pass' => 'NewCLIP@ss' . wp_generate_password( 6, false ),
		) );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI password change must produce a log row'
		);

		$context = get_latest_context();
		$this->assert_context_has( $context, 'edited_user_password_changed', '1' );
	}

	/**
	 * WP-CLI: wp user create must produce a log row.
	 *
	 * Tripwire test for the class of bug found in Post_Logger where create paths
	 * were silently dropped. User_Logger uses the user_register hook which is not
	 * is_admin()-gated, so this currently passes — but a regression here would
	 * otherwise go unnoticed until a user reports it in production.
	 */
	public function test_logs_user_create_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$count_before = $this->get_event_count();

		$new_user_id = wp_insert_user( array(
			'user_login'   => 'cli_create_' . wp_generate_password( 8, false ),
			'user_email'   => 'cli_create_' . wp_generate_password( 8, false ) . '@example.com',
			'user_pass'    => 'TestP@ss' . wp_generate_password( 6, false ),
			'display_name' => 'CLI Created User',
			'role'         => 'subscriber',
		) );

		$this->assertIsInt( $new_user_id );
		$this->assertGreaterThan( 0, $new_user_id );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_insert_user() must produce exactly one log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleUserLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'user_created' );
	}

	/**
	 * WP-CLI: wp user delete must produce a user_deleted log row.
	 *
	 * wp_delete_user() fires deleted_user, which is not is_admin()-gated, so this
	 * should already work — but a regression here would silently drop audit trail
	 * for one of the most security-relevant CLI operations.
	 */
	public function test_logs_user_delete_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$victim_id = $this->factory->user->create( array(
			'role'         => 'subscriber',
			'display_name' => 'To Be Deleted',
		) );

		$count_before = $this->get_event_count();

		// wp_delete_user() lives in wp-admin/includes/user.php, which WordPress
		// only loads on admin requests — explicitly require it under WP-CLI.
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $victim_id );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI wp_delete_user() must produce exactly one log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleUserLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'user_deleted' );
	}

	/**
	 * WP-CLI: wp user set-role must log the role change.
	 *
	 * set_user_role fires set_user_role, which the user logger hooks regardless
	 * of context. Tripwire test — if this stops logging, role escalation via CLI
	 * would silently go unaudited.
	 */
	public function test_logs_user_role_change_via_wp_cli() {
		add_filter( 'simple_history/is_wp_cli', '__return_true' );

		$count_before = $this->get_event_count();

		$user = new WP_User( $this->target_user_id );
		$user->set_role( 'editor' );

		$this->assertEquals(
			$count_before + 1,
			$this->get_event_count(),
			'WP-CLI set_role() must produce exactly one log row'
		);

		$row = get_latest_row();
		$this->assertEquals( 'SimpleUserLogger', $row['logger'] );

		$context = get_latest_context();
		$this->assert_context_has( $context, '_message_key', 'user_role_updated' );
		$this->assert_context_has( $context, 'new_role', 'editor' );
		$this->assert_context_has( $context, 'old_role', 'subscriber' );
	}

	private function get_event_count(): int {
		global $wpdb;
		$db_table = $this->sh->get_events_table_name();
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$db_table} WHERE logger = %s",
				'SimpleUserLogger'
			)
		);
	}

	private function assert_context_has( array $context, string $key, string $value ): void {
		$this->assertContains(
			array( 'key' => $key, 'value' => $value ),
			$context,
			sprintf( 'Context should contain %s=%s', $key, $value )
		);
	}
}
