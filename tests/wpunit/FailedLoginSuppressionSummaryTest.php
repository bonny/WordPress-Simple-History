<?php

use Simple_History\Simple_History;
use Simple_History\Log_Initiators;
use Simple_History\Loggers\User_Logger;

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

	/** @var array<array{0:array,1:array}> Every SimpleUserLogger write this test saw. */
	private $writes = [];

	public function setUp(): void {
		parent::setUp();

		$this->logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

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
		parent::tearDown();
	}

	public function filter_threshold() {
		return self::THRESHOLD;
	}

	public function capture_write( $data_and_context, $instance ) {
		if ( $instance instanceof User_Logger ) {
			$this->writes[] = $data_and_context;
		}

		return $data_and_context;
	}

	public function test_summary_key_is_not_treated_as_a_failed_login() {
		$this->assertNotContains(
			User_Logger::MESSAGE_KEY_FAILED_LOGINS_SUPPRESSED,
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

		$this->assertSame( 5, (int) $context['failed_login_suppressed_count'] );
		$this->assertSame( self::THRESHOLD, (int) $context['failed_login_threshold'] );
		$this->assertSame( Log_Initiators::WORDPRESS, $data['initiator'] );
		$this->assertSame( 'admin', $context['failed_login_last_username'] );
		$this->assertSame( '203.0.113.10', $context['failed_login_last_ip'] );
		$this->assertSame( '203.0.113.10', $context['_server_remote_addr'], 'The summary should carry the attacker IP, not the IP of whoever ended the burst' );
		$this->assertNotEmpty( $context['failed_login_suppressed_first_date'] );
		$this->assertNotEmpty( $context['failed_login_suppressed_last_date'] );
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

	public function test_no_summary_when_the_burst_stays_under_the_threshold() {
		$this->run_burst( self::THRESHOLD );
		$this->end_burst();

		$this->assertNull( $this->get_summary_write() );
	}

	public function test_no_summary_when_experimental_features_are_disabled() {
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true' );
		add_filter( 'simple_history/experimental_features_enabled', '__return_false' );

		$this->run_burst( self::THRESHOLD + 5 );
		$this->end_burst();

		remove_filter( 'simple_history/experimental_features_enabled', '__return_false' );

		$this->assertNull( $this->get_summary_write() );
		$this->assertSame( 0, (int) get_option( 'sh_core_failed_login_count' ), 'The counter still resets even when no summary is written' );
		$this->assertFalse( get_option( 'sh_core_failed_login_burst' ), 'Burst details are discarded when the burst ends' );
	}

	public function test_burst_details_are_cleared_after_the_summary() {
		$this->run_burst( self::THRESHOLD + 5 );
		$this->end_burst();

		$this->assertFalse( get_option( 'sh_core_failed_login_burst' ) );
		$this->assertSame( 0, (int) get_option( 'sh_core_failed_login_count' ) );
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
					return ( $write[1]['_message_key'] ?? '' ) === User_Logger::MESSAGE_KEY_FAILED_LOGINS_SUPPRESSED;
				}
			)
		);

		$this->assertCount( 2, $summaries );
		$this->assertSame( 5, (int) $summaries[0][1]['failed_login_suppressed_count'] );
		$this->assertSame( 2, (int) $summaries[1][1]['failed_login_suppressed_count'] );
	}

	public function test_summary_renders_a_details_table() {
		$this->run_burst( self::THRESHOLD + 5 );
		$this->end_burst();

		[ , $context ] = $this->get_summary_write();

		$row          = new stdClass();
		$row->context = $context;

		$details = $this->logger->get_log_row_details_output( $row );

		$this->assertInstanceOf( \Simple_History\Event_Details\Event_Details_Group::class, $details );
		$this->assertNotEmpty( $details->items );
	}

	private function run_burst( int $attempts ): void {
		for ( $i = 0; $i < $attempts; $i++ ) {
			$this->logger->warning_message(
				'user_login_failed',
				[
					'login'               => 'admin',
					'_server_remote_addr' => '203.0.113.10',
				]
			);
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
			if ( ( $write[1]['_message_key'] ?? '' ) === User_Logger::MESSAGE_KEY_FAILED_LOGINS_SUPPRESSED ) {
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
