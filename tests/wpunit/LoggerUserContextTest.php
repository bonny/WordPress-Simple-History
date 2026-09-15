<?php

require_once 'functions.php';

use Simple_History\Log_Initiators;
use Simple_History\Services\Action_Scheduler_Tracker;

use function Simple_History\tests\get_latest_row;
use function Simple_History\tests\get_latest_context;

/**
 * Which events get the current user's identity attached.
 *
 * Events that WordPress, an anonymous visitor or an unknown source initiated
 * must not carry the administrator who happens to be logged in during the
 * request. Issue 307.
 */
class LoggerUserContextTest extends \Codeception\TestCase\WPTestCase {
	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
	}

	public function tearDown(): void {
		// Leave the tracker clean whatever a test did.
		do_action( 'action_scheduler_after_process_queue' );

		parent::tearDown();
	}

	/**
	 * Latest event's context as key => value.
	 *
	 * @return array<string, string>
	 */
	private function latest_context() {
		return array_column( get_latest_context( false ), 'value', 'key' );
	}

	/**
	 * Assert the latest event carries no user identity.
	 *
	 * @param array<string, string> $context Context.
	 */
	private function assert_no_user( $context ) {
		foreach ( [ '_user_id', '_user_login', '_user_email', '_user_role' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $context, "Context should not have {$key}." );
		}
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function initiators_without_user() {
		return [
			'wordpress' => [ Log_Initiators::WORDPRESS ],
			'web user'  => [ Log_Initiators::WEB_USER ],
			'other'     => [ Log_Initiators::OTHER ],
		];
	}

	/**
	 * @dataProvider initiators_without_user
	 *
	 * @param string $initiator Initiator.
	 */
	public function test_non_user_initiator_gets_no_user_while_admin_is_logged_in( $initiator ) {
		SimpleLogger()->info( 'Non-user initiator test', [ '_initiator' => $initiator ] );

		$this->assertSame( $initiator, get_latest_row()['initiator'] );
		$this->assert_no_user( $this->latest_context() );
	}

	public function test_wp_cli_initiator_keeps_user() {
		SimpleLogger()->info( 'WP-CLI with --user test', [ '_initiator' => Log_Initiators::WP_CLI ] );

		$context = $this->latest_context();

		$this->assertSame( Log_Initiators::WP_CLI, get_latest_row()['initiator'] );
		$this->assertSame( (string) $this->admin_id, $context['_user_id'] );
	}

	public function test_explicit_user_id_is_kept() {
		SimpleLogger()->info(
			'Explicit user test',
			[
				'_initiator' => Log_Initiators::WORDPRESS,
				'_user_id'   => 12345,
			]
		);

		$this->assertSame( '12345', $this->latest_context()['_user_id'] );
	}

	public function test_auto_detected_user_gets_full_identity() {
		SimpleLogger()->info( 'Auto-detected user test' );

		$context = $this->latest_context();

		$this->assertSame( Log_Initiators::WP_USER, get_latest_row()['initiator'] );
		$this->assertSame( (string) $this->admin_id, $context['_user_id'] );
		$this->assertArrayHasKey( '_user_login', $context );
		$this->assertArrayHasKey( '_user_email', $context );
		$this->assertSame( 'administrator', $context['_user_role'] );
	}

	/**
	 * ALTERNATE_WP_CRON runs wp-cron.php inside the visitor's own request,
	 * so cron and a logged-in user coexist.
	 */
	public function test_cron_with_logged_in_user_is_wordpress_without_user() {
		add_filter( 'wp_doing_cron', '__return_true' );
		SimpleLogger()->info( 'Cron with logged in user test' );
		remove_filter( 'wp_doing_cron', '__return_true' );

		$context = $this->latest_context();

		$this->assertSame( Log_Initiators::WORDPRESS, get_latest_row()['initiator'] );
		$this->assertArrayHasKey( '_wp_cron_running', $context );
		$this->assert_no_user( $context );
	}

	public function test_action_scheduler_async_request_is_wordpress_without_user() {
		do_action( 'action_scheduler_before_execute', 1, 'Async Request' );
		SimpleLogger()->info( 'Action Scheduler async test' );

		$this->assertSame( Log_Initiators::WORDPRESS, get_latest_row()['initiator'] );
		$this->assert_no_user( $this->latest_context() );

		// Once the action has finished, the same request logs as the user again.
		do_action( 'action_scheduler_after_execute', 1, null, 'Async Request' );
		SimpleLogger()->info( 'After Action Scheduler action test' );

		$this->assertSame( Log_Initiators::WP_USER, get_latest_row()['initiator'] );
		$this->assertSame( (string) $this->admin_id, $this->latest_context()['_user_id'] );
	}

	public function test_action_scheduler_run_from_admin_list_table_keeps_user() {
		do_action( 'action_scheduler_before_execute', 1, 'Admin List Table' );
		SimpleLogger()->info( 'Action Scheduler run by admin test' );

		$this->assertSame( Log_Initiators::WP_USER, get_latest_row()['initiator'] );
		$this->assertSame( (string) $this->admin_id, $this->latest_context()['_user_id'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function action_scheduler_finish_hooks() {
		return [
			'after execute'     => [ 'action_scheduler_after_execute' ],
			'failed execution'  => [ 'action_scheduler_failed_execution' ],
			'failed validation' => [ 'action_scheduler_failed_validation' ],
			'execution ignored' => [ 'action_scheduler_execution_ignored' ],
			'corrupted action'  => [ 'action_scheduler_canceled_corrupted_action' ],
			'end of queue run'  => [ 'action_scheduler_after_process_queue' ],
		];
	}

	/**
	 * @dataProvider action_scheduler_finish_hooks
	 *
	 * @param string $hook Hook that ends an action.
	 */
	public function test_action_scheduler_tracker_clears_on_every_finish_path( $hook ) {
		do_action( 'action_scheduler_before_execute', 1, 'WP Cron' );
		$this->assertTrue( Action_Scheduler_Tracker::is_running_scheduled_action() );

		do_action( $hook );

		$this->assertFalse( Action_Scheduler_Tracker::is_running_scheduled_action() );
	}
}
