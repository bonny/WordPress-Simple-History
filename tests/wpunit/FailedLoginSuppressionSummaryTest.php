<?php

use Simple_History\Simple_History;
use Simple_History\Log_Initiators;
use Simple_History\Loggers\Simple_History_Logger;
use Simple_History\Loggers\User_Logger;
use Simple_History\Services\Failed_Login_Limit_Service;

/**
 * Issue 296: once a burst of failed logins crosses the threshold and then
 * ends, the log itself should say how many attempts were never recorded.
 * Experimental feature: the summary event is only written when experimental
 * features are enabled.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit FailedLoginSuppressionSummaryTest
 */
class FailedLoginSuppressionSummaryTest extends \Codeception\TestCase\WPTestCase {
	private const THRESHOLD = 3;

	/** @var User_Logger */
	private $logger;

	/** @var array<array{0:array,1:array}> Every user logger and Simple History logger write this test saw. */
	private $writes = [];

	/** @var array Copy of $_SERVER to restore after tests that fake request headers. */
	private $server_backup = [];

	public function setUp(): void {
		parent::setUp();

		$this->logger        = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
		$this->server_backup = $_SERVER;

		$this->reset_options();

		add_filter( 'simple_history/failed_login_limit/threshold', [ $this, 'filter_threshold' ] );
		add_filter( 'simple_history/experimental_features_enabled', '__return_true' );

		$this->writes = [];
		add_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10 );
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true' );
		remove_filter( 'simple_history/failed_login_limit/threshold', [ $this, 'filter_threshold' ] );
		$this->reset_options();
		$_SERVER = $this->server_backup;
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function filter_threshold() {
		return self::THRESHOLD;
	}

	public function capture_write( $data_and_context, $instance ) {
		if ( $instance instanceof User_Logger || $instance instanceof Simple_History_Logger ) {
			$this->writes[] = $data_and_context;
		}

		return $data_and_context;
	}

	public function test_summary_key_is_not_treated_as_a_failed_login() {
		$this->assertNotContains(
			Simple_History_Logger::MESSAGE_KEY_FAILED_LOGINS_NOT_RECORDED,
			User_Logger::get_failed_login_message_keys(),
			'The summary must never be counted or suppressed as a failed login itself'
		);
	}

	public function test_summary_is_logged_when_a_burst_over_the_threshold_ends() {
		$this->run_burst( self::THRESHOLD + 5 );
		$this->end_burst();

		$summary = $this->get_summary_write();

		$this->assertNotNull( $summary, 'A summary event should be written when the burst ends' );

		[ $data, $context ] = $summary;

		$this->assertSame( 5, (int) $context['failed_login_not_recorded_count'] );
		$this->assertSame( self::THRESHOLD, (int) $context['failed_login_recorded_count'] );
		$this->assertSame( self::THRESHOLD + 5, (int) $context['failed_login_total_count'] );
		$this->assertSame( Log_Initiators::WORDPRESS, $data['initiator'] );
		$this->assertSame( 'admin', $context['failed_login_username'] );
		$this->assertSame( '203.0.113.10', $context['failed_login_ip'] );
		$this->assertSame( '203.0.113.10', $context['_server_remote_addr'], 'The summary should carry the attacker IP, not the IP of whoever ended the burst' );
		$this->assertNotEmpty( $context['failed_login_first_not_recorded_date'] );
		$this->assertNotEmpty( $context['failed_login_last_date'] );
	}

	public function test_summary_is_dated_at_the_last_suppressed_attempt() {
		$this->run_burst( self::THRESHOLD + 2 );

		// Pretend the burst ended an hour ago and nothing else was logged since.
		$burst              = get_option( 'sh_core_failed_login_burst' );
		$burst['last_date'] = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		update_option( 'sh_core_failed_login_burst', $burst, false );

		$this->end_burst();

		[ $data ] = $this->get_summary_write();

		$this->assertSame( $burst['last_date'], $data['date'], 'The summary should sit in the timeline where the burst ended, not where the next event happened' );
	}

	public function test_summary_is_not_attributed_to_the_user_who_ended_the_burst() {
		$this->run_burst( self::THRESHOLD + 2 );

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->end_burst();

		[ $data, $context ] = $this->get_summary_write();

		$this->assertSame( Log_Initiators::WORDPRESS, $data['initiator'] );
		$this->assertSame( 0, (int) $context['_user_id'] );
		$this->assertArrayNotHasKey( '_user_login', $context, 'The admin who happened to log the next event must not be attached to the summary' );
		$this->assertArrayNotHasKey( '_user_email', $context );
	}

	public function test_summary_captures_forwarded_ip_and_referer_of_the_attacker_not_the_burst_ender() {
		// Production failed-login contexts carry no IP at filter time; the
		// service has to read the request itself, including proxy headers.
		$_SERVER['REMOTE_ADDR']          = '10.0.0.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
		$_SERVER['HTTP_REFERER']         = 'https://example.com/wp-login.php';

		$this->run_burst( self::THRESHOLD + 2, false );

		// The burst is ended by a very different request.
		$_SERVER['REMOTE_ADDR']  = '10.0.0.2';
		$_SERVER['HTTP_REFERER'] = 'https://example.com/wp-admin/post.php?post=1&action=edit';
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->end_burst();

		[ , $context ] = $this->get_summary_write();

		$this->assertSame( '10.0.0.x', $context['_server_remote_addr'] );
		$this->assertSame( '198.51.100.x', $context['_server_http_x_forwarded_for_0'], 'Forwarded IPs from the attack must survive on the summary' );
		$this->assertSame( '198.51.100.x', $context['failed_login_ip'], 'Behind a proxy the forwarded IP is the attacker, not the proxy' );
		$this->assertSame( 'https://example.com/wp-login.php', $context['_server_http_referer'] );
	}

	public function test_no_summary_when_the_burst_stays_under_the_threshold() {
		$this->run_burst( self::THRESHOLD );
		$this->end_burst();

		$this->assertNull( $this->get_summary_write() );
	}

	public function test_no_summary_and_no_tracking_when_experimental_features_are_disabled() {
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true' );
		add_filter( 'simple_history/experimental_features_enabled', '__return_false' );

		$this->run_burst( self::THRESHOLD + 5 );

		$this->assertFalse( get_option( 'sh_core_failed_login_burst' ), 'No burst details are written when the summary can never be logged' );

		$this->end_burst();

		remove_filter( 'simple_history/experimental_features_enabled', '__return_false' );

		$this->assertNull( $this->get_summary_write() );
		$this->assertSame( 0, (int) get_option( 'sh_core_failed_login_count' ), 'The counter still resets even when no summary is written' );
	}

	public function test_burst_details_are_cleared_after_the_summary() {
		$this->run_burst( self::THRESHOLD + 5 );
		$this->end_burst();

		$this->assertFalse( get_option( 'sh_core_failed_login_burst' ) );
		$this->assertSame( 0, (int) get_option( 'sh_core_failed_login_count' ) );
	}

	public function test_burst_details_are_cleared_even_when_nothing_was_skipped_at_reset_time() {
		$this->run_burst( self::THRESHOLD + 2 );
		$this->assertNotFalse( get_option( 'sh_core_failed_login_burst' ) );

		// The threshold is raised mid-burst, so at reset time nothing counts as skipped.
		remove_filter( 'simple_history/failed_login_limit/threshold', [ $this, 'filter_threshold' ] );
		$this->end_burst();
		add_filter( 'simple_history/failed_login_limit/threshold', [ $this, 'filter_threshold' ] );

		$this->assertNull( $this->get_summary_write() );
		$this->assertFalse( get_option( 'sh_core_failed_login_burst' ), 'A stale start date must not leak into the next burst' );
	}

	public function test_each_burst_gets_its_own_summary() {
		$this->run_burst( self::THRESHOLD + 5 );
		$this->end_burst();
		$this->run_burst( self::THRESHOLD + 2 );
		$this->end_burst();

		$summaries = array_values(
			array_filter(
				$this->writes,
				static function ( $write ) {
					return ( $write[1]['_message_key'] ?? '' ) === Simple_History_Logger::MESSAGE_KEY_FAILED_LOGINS_NOT_RECORDED;
				}
			)
		);

		$this->assertCount( 2, $summaries );
		$this->assertSame( 5, (int) $summaries[0][1]['failed_login_not_recorded_count'] );
		$this->assertSame( 2, (int) $summaries[1][1]['failed_login_not_recorded_count'] );
	}

	public function test_end_burst_is_callable_directly_with_tracked_details() {
		// This is the seam premium's own limiter uses: it tracks each skipped
		// attempt and closes the burst with its own count and threshold.
		Failed_Login_Limit_Service::track_suppressed_attempt(
			[
				'login'               => 'editor',
				'_server_remote_addr' => '203.0.113.20',
			]
		);
		Failed_Login_Limit_Service::end_burst( 42, 500 );

		[ , $context ] = $this->get_summary_write();

		$this->assertSame( 42, (int) $context['failed_login_not_recorded_count'] );
		$this->assertSame( 500, (int) $context['failed_login_recorded_count'] );
		$this->assertSame( 'editor', $context['failed_login_username'] );
		$this->assertSame( '203.0.113.20', $context['failed_login_ip'] );
		$this->assertFalse( get_option( 'sh_core_failed_login_burst' ) );
	}

	public function test_summary_renders_a_details_table() {
		$this->run_burst( self::THRESHOLD + 5 );
		$this->end_burst();

		[ , $context ] = $this->get_summary_write();

		$row                      = new stdClass();
		$row->context             = $context;
		$row->context_message_key = $context['_message_key'];
		$row->message             = '';

		$sh_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleHistoryLogger' );
		$details   = $sh_logger->get_log_row_details_output( $row );

		$this->assertInstanceOf( \Simple_History\Event_Details\Event_Details_Group::class, $details );
		$this->assertCount( 7, $details->items, 'Total, recorded, not recorded, last attempt, first unrecorded, username, IP' );
	}

	public function test_summary_message_reads_as_a_sentence_with_formatted_numbers() {
		Failed_Login_Limit_Service::track_suppressed_attempt( [ 'login' => 'admin', '_server_remote_addr' => '203.0.113.10' ] );
		Failed_Login_Limit_Service::end_burst( 4183, 100 );

		[ , $context ] = $this->get_summary_write();

		$row                      = new stdClass();
		$row->context             = $context;
		$row->context_message_key = $context['_message_key'];
		$row->message             = '';

		$sh_logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleHistoryLogger' );

		$this->assertSame(
			'Recorded 100 failed login attempts in a row, then stopped recording to keep the log small. 4,183 more attempts followed.',
			$sh_logger->get_log_row_plain_text_output( $row )
		);
		$this->assertSame( '4183', (string) $context['failed_login_not_recorded_count'], 'Stored value stays a plain integer' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$links = $sh_logger->get_action_links( $row );
		$this->assertSame( 'Configure failed login attempts', $links[0]['label'] ?? null );
	}

	private function run_burst( int $attempts, bool $with_ip_in_context = true ): void {
		$context = [ 'login' => 'admin' ];

		if ( $with_ip_in_context ) {
			$context['_server_remote_addr'] = '203.0.113.10';
		}

		for ( $i = 0; $i < $attempts; $i++ ) {
			$this->logger->warning_message( 'user_login_failed', $context );
		}
	}

	private function end_burst(): void {
		$this->logger->info_message( 'user_logged_in', [ 'user_id' => 1 ] );
	}

	/**
	 * @return array{0:array,1:array}|null
	 */
	private function get_summary_write() {
		foreach ( $this->writes as $write ) {
			if ( ( $write[1]['_message_key'] ?? '' ) === Simple_History_Logger::MESSAGE_KEY_FAILED_LOGINS_NOT_RECORDED ) {
				return $write;
			}
		}

		return null;
	}

	private function reset_options(): void {
		delete_option( 'sh_core_failed_login_count' );
		delete_option( 'sh_core_failed_login_total_suppressed' );
		delete_option( 'sh_core_failed_login_burst' );
	}
}
