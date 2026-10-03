<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Mail_Failure_Logger;
use Simple_History\Dropins\Sidebar_Mail_Failures_Dropin;
use Simple_History\Services\Mail_Failure_Tracker;

/**
 * Tests the experimental Mail_Failure_Logger, which logs emails WordPress failed to send.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit MailFailureLoggerTest
 */
class MailFailureLoggerTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * @var Simple_History
	 */
	private $sh;

	/**
	 * @var Mail_Failure_Logger
	 */
	private $logger;

	/**
	 * @var Mail_Failure_Tracker
	 */
	private $tracker;

	public function setUp(): void {
		parent::setUp();

		// Enable experimental features so the logger is registered.
		update_option( 'simple_history_experimental_features_enabled', '1' );

		$this->sh     = Simple_History::get_instance();
		$this->logger = $this->sh->get_instantiated_logger_by_slug( 'MailFailureLogger' );

		// If the logger wasn't loaded at boot (experimental flag wasn't set yet),
		// instantiate and load it manually for this test run.
		if ( ! $this->logger instanceof Mail_Failure_Logger ) {
			$this->logger = new Mail_Failure_Logger( $this->sh );
			$this->logger->loaded();
		}

		// The logger keeps its window in memory, and may be the same instance as in the last test.
		$this->set_logger_property( 'window', null );
		$this->set_logger_property( 'window_changed', false );

		// The tracker service was not loaded at boot, since experimental features were off then.
		$this->tracker = new Mail_Failure_Tracker( $this->sh );
		$this->tracker->loaded();

		delete_option( Mail_Failure_Logger::OPTION_WINDOW );
		delete_option( Mail_Failure_Tracker::OPTION_NAME );
		wp_clear_scheduled_hook( Mail_Failure_Logger::CRON_HOOK );

		// Start each test without events from earlier tests.
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->sh->get_events_table_name()} WHERE logger = %s", 'MailFailureLogger' ) );
	}

	public function tearDown(): void {
		remove_action( 'wp_mail_failed', [ $this->tracker, 'on_wp_mail_failed' ] );
		remove_all_filters( 'simple_history/mail_failures/log_events' );
		delete_option( Mail_Failure_Logger::OPTION_WINDOW );
		delete_option( Mail_Failure_Tracker::OPTION_NAME );
		wp_clear_scheduled_hook( Mail_Failure_Logger::CRON_HOOK );

		parent::tearDown();
	}

	/**
	 * Set a private property on the logger.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Value.
	 */
	private function set_logger_property( $name, $value ) {
		$property = new ReflectionProperty( Mail_Failure_Logger::class, $name );
		$property->setAccessible( true );
		$property->setValue( $this->logger, $value );
	}

	/**
	 * Do what happens on shutdown: write the logger window and the tracker counts.
	 */
	private function end_request() {
		$this->logger->save_window();
		$this->tracker->save();
	}

	/**
	 * Build the WP_Error that wp_mail() passes to wp_mail_failed.
	 *
	 * @param string $message Error message.
	 * @return WP_Error
	 */
	private function make_error( $message = 'Could not instantiate mail function.' ) {
		return new WP_Error(
			'wp_mail_failed',
			$message,
			[
				'to'          => [ 'alice@example.com', 'bob@example.org' ],
				'subject'     => 'Password reset for secret-project',
				'message'     => 'Your reset link is https://example.com/reset?key=abc',
				'headers'     => [],
				'attachments' => [],
			]
		);
	}

	/**
	 * Count events from this logger with a given message key.
	 *
	 * @param string $message_key Message key.
	 * @return int
	 */
	private function count_events( $message_key ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$this->sh->get_events_table_name()} e
				INNER JOIN {$this->sh->get_contexts_table_name()} c ON c.history_id = e.id
				WHERE e.logger = %s AND c.`key` = '_message_key' AND c.value = %s",
				'MailFailureLogger',
				$message_key
			)
		);
	}

	/**
	 * Get the context of the latest event from this logger.
	 *
	 * @return array<string,string>
	 */
	private function get_latest_logger_context() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->sh->get_events_table_name()} WHERE logger = %s ORDER BY id DESC LIMIT 1", 'MailFailureLogger' ) );

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT `key`, value FROM {$this->sh->get_contexts_table_name()} WHERE history_id = %d", $event_id ),
			ARRAY_A
		);

		return wp_list_pluck( $rows, 'value', 'key' );
	}

	public function test_logs_failure_with_masked_error_and_recipient_count() {
		do_action( 'wp_mail_failed', $this->make_error( 'SMTP Error: The following recipients failed: alice@example.com' ) );

		$this->assertSame( 1, $this->count_events( 'mail_send_failed' ) );

		$context = $this->get_latest_logger_context();

		$this->assertSame( 'SMTP Error: The following recipients failed: ***@example.com', $context['mail_error'] );
		$this->assertSame( '2', $context['mail_recipient_count'] );

		// Addresses, subject and message body are never stored.
		$all_values = implode( ' ', $context );
		$this->assertStringNotContainsString( 'alice@', $all_values );
		$this->assertStringNotContainsString( 'bob@', $all_values );
		$this->assertStringNotContainsString( 'secret-project', $all_values );
		$this->assertStringNotContainsString( 'reset?key', $all_values );
	}

	public function test_logs_at_error_level() {
		do_action( 'wp_mail_failed', $this->make_error() );

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$level = $wpdb->get_var( $wpdb->prepare( "SELECT level FROM {$this->sh->get_events_table_name()} WHERE logger = %s ORDER BY id DESC LIMIT 1", 'MailFailureLogger' ) );

		$this->assertSame( 'error', $level );
	}

	public function test_throttles_failures_within_a_window() {
		$total = Mail_Failure_Logger::MAX_LOGGED_PER_WINDOW + 3;

		for ( $i = 0; $i < $total; $i++ ) {
			do_action( 'wp_mail_failed', $this->make_error() );
		}

		$this->assertSame( Mail_Failure_Logger::MAX_LOGGED_PER_WINDOW, $this->count_events( 'mail_send_failed' ) );

		// The window is written once, at the end of the request.
		$this->assertFalse( get_option( Mail_Failure_Logger::OPTION_WINDOW ) );
		$this->end_request();

		$window = get_option( Mail_Failure_Logger::OPTION_WINDOW );
		$this->assertSame( 3, $window['skipped'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $window['start'], 'Start is an ISO 8601 UTC datetime.' );

		// The summary is scheduled for when the window ends.
		$this->assertSame(
			strtotime( $window['start'] ) + Mail_Failure_Logger::WINDOW_SECONDS,
			wp_next_scheduled( Mail_Failure_Logger::CRON_HOOK )
		);
	}

	public function test_summary_written_when_window_ends() {
		for ( $i = 0; $i < Mail_Failure_Logger::MAX_LOGGED_PER_WINDOW + 4; $i++ ) {
			do_action( 'wp_mail_failed', $this->make_error() );
		}

		do_action( Mail_Failure_Logger::CRON_HOOK );

		$this->assertSame( 1, $this->count_events( 'mail_send_failures_skipped' ) );
		$this->assertSame( '4', $this->get_latest_logger_context()['mail_skipped_count'] );

		// State and schedule are cleared, also at the end of the request.
		$this->end_request();
		$this->assertFalse( get_option( Mail_Failure_Logger::OPTION_WINDOW ) );
		$this->assertFalse( wp_next_scheduled( Mail_Failure_Logger::CRON_HOOK ) );
	}

	public function test_no_summary_when_nothing_was_skipped() {
		do_action( 'wp_mail_failed', $this->make_error() );
		do_action( Mail_Failure_Logger::CRON_HOOK );

		$this->assertSame( 0, $this->count_events( 'mail_send_failures_skipped' ) );
	}

	public function test_failure_after_window_ended_writes_summary_then_logs() {
		update_option(
			Mail_Failure_Logger::OPTION_WINDOW,
			[
				'start'   => gmdate( 'Y-m-d\TH:i:s\Z', time() - Mail_Failure_Logger::WINDOW_SECONDS - 10 ),
				'logged'  => Mail_Failure_Logger::MAX_LOGGED_PER_WINDOW,
				'skipped' => 7,
			],
			false
		);

		do_action( 'wp_mail_failed', $this->make_error() );

		$this->assertSame( 1, $this->count_events( 'mail_send_failures_skipped' ) );
		$this->assertSame( 1, $this->count_events( 'mail_send_failed' ) );

		$this->end_request();

		$window = get_option( Mail_Failure_Logger::OPTION_WINDOW );
		$this->assertSame( 1, $window['logged'] );
		$this->assertSame( 0, $window['skipped'] );
	}

	public function test_skips_when_debug_and_monitor_logs_mail() {
		$logger = new class( $this->sh ) extends Mail_Failure_Logger {
			protected function is_debug_and_monitor_mail_logger_active() {
				return true;
			}
		};

		$logger->on_wp_mail_failed( $this->make_error() );

		$this->assertSame( 0, $this->count_events( 'mail_send_failed' ) );
		$this->assertFalse( get_option( Mail_Failure_Logger::OPTION_WINDOW ) );
	}

	public function test_mask_error_message_caps_length() {
		$masked = Mail_Failure_Tracker::mask_error_message( 'Error ' . str_repeat( 'x', 1000 ) );

		$this->assertStringStartsWith( 'Error x', $masked );
		$this->assertSame( Mail_Failure_Tracker::MAX_ERROR_LENGTH + 1, preg_match_all( '/./us', $masked ) );
		$this->assertStringEndsWith( '…', $masked );

		// Multibyte text is cut on a character boundary, never inside one.
		$cut = Mail_Failure_Tracker::mask_error_message( str_repeat( 'ö', 600 ) );
		$this->assertSame( 1, preg_match( '//u', $cut ) );
		$this->assertSame( str_repeat( 'ö', Mail_Failure_Tracker::MAX_ERROR_LENGTH ) . '…', $cut );

		// Short messages are left alone.
		$this->assertSame( 'Short', Mail_Failure_Tracker::mask_error_message( 'Short' ) );
	}

	public function test_mask_error_message_masks_every_address() {
		$this->assertSame(
			'Failed: ***@example.com, <***@mail.example.org>',
			Mail_Failure_Tracker::mask_error_message( 'Failed: first.last+tag@example.com, <x@mail.example.org>' )
		);
	}

	public function test_tracker_counts_every_failure_including_throttled_ones() {
		$total = Mail_Failure_Logger::MAX_LOGGED_PER_WINDOW + 3;

		for ( $i = 0; $i < $total; $i++ ) {
			do_action( 'wp_mail_failed', $this->make_error( 'Error number ' . $i . ' for alice@example.com' ) );
		}

		$this->end_request();

		$stats = Mail_Failure_Tracker::get_stats();

		$this->assertSame( $total, $stats['count'] );
		$this->assertSame( 'Error number 7 for ***@example.com', $stats['last_error'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $stats['last_at'] );

		// Only day counts, the last time and the masked error are stored.
		$stored = get_option( Mail_Failure_Tracker::OPTION_NAME );
		$this->assertSame( [ 'daily', 'last_at', 'last_error' ], array_keys( $stored ) );
		$this->assertStringNotContainsString( 'alice@', wp_json_encode( $stored ) );
	}

	public function test_tracker_counts_when_logging_events_is_off() {
		add_filter( 'simple_history/mail_failures/log_events', '__return_false' );

		do_action( 'wp_mail_failed', $this->make_error() );
		do_action( 'wp_mail_failed', $this->make_error() );
		$this->end_request();

		$this->assertSame( 0, $this->count_events( 'mail_send_failed' ) );
		$this->assertFalse( get_option( Mail_Failure_Logger::OPTION_WINDOW ) );
		$this->assertSame( 2, Mail_Failure_Tracker::get_stats()['count'] );
	}

	public function test_tracker_writes_the_option_once_per_request() {
		$writes = 0;
		$count_writes = function ( $value ) use ( &$writes ) {
			++$writes;
			return $value;
		};
		add_filter( 'pre_update_option_' . Mail_Failure_Tracker::OPTION_NAME, $count_writes );

		for ( $i = 0; $i < 200; $i++ ) {
			$this->tracker->on_wp_mail_failed( $this->make_error() );
		}

		$this->assertSame( 0, $writes, 'Nothing is written before the end of the request.' );

		$this->tracker->save();
		$this->tracker->save();

		remove_filter( 'pre_update_option_' . Mail_Failure_Tracker::OPTION_NAME, $count_writes );

		$this->assertSame( 1, $writes );
		$this->assertSame( 200, Mail_Failure_Tracker::get_stats()['count'] );
	}

	public function test_tracker_adds_to_counts_from_earlier_requests_and_drops_old_days() {
		$today = gmdate( 'Y-m-d' );

		update_option(
			Mail_Failure_Tracker::OPTION_NAME,
			[
				'daily'      => [
					gmdate( 'Y-m-d', time() - 40 * DAY_IN_SECONDS ) => 9,
					gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS )  => 2,
					$today                                          => 1,
				],
				'last_at'    => gmdate( 'Y-m-d\TH:i:s\Z', time() - 3600 ),
				'last_error' => 'Older error',
			],
			false
		);

		$this->assertSame( 3, Mail_Failure_Tracker::get_stats()['count'], 'Days older than 30 are not counted.' );

		do_action( 'wp_mail_failed', $this->make_error( 'Newer error' ) );
		$this->end_request();

		$stored = get_option( Mail_Failure_Tracker::OPTION_NAME );

		$this->assertSame( 2, $stored['daily'][ $today ] );
		$this->assertCount( 2, $stored['daily'], 'The old day is removed when the option is written.' );
		$this->assertSame( 'Newer error', $stored['last_error'] );
	}

	public function test_stats_are_empty_without_failures() {
		$stats = Mail_Failure_Tracker::get_stats();

		$this->assertSame( 0, $stats['count'] );
		$this->assertSame( '', $stats['last_at'] );
		$this->assertSame( '', $stats['last_error'] );
	}

	public function test_notice_shown_to_admins_with_failures_only() {
		$admin = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$editor = $this->factory->user->create( [ 'role' => 'editor' ] );

		wp_set_current_user( $admin );
		$this->assertSame( '', Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' ), 'No notice without failures.' );

		do_action( 'wp_mail_failed', $this->make_error( 'Could not <b>connect</b>' ) );
		$this->end_request();

		$html = Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' );
		$this->assertStringContainsString( '1 email failed to send', $html );
		$this->assertStringContainsString( 'Could not &lt;b&gt;connect&lt;/b&gt;', $html, 'The error is escaped.' );
		$this->assertStringContainsString( 'MailFailureLogger', urldecode( $html ), 'Links to the failed email events.' );

		wp_set_current_user( $editor );
		$this->assertSame( '', Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' ), 'Hidden from non-admins.' );
	}

	public function test_notice_without_events_link_when_logging_is_off() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		add_filter( 'simple_history/mail_failures/log_events', '__return_false' );

		do_action( 'wp_mail_failed', $this->make_error() );
		$this->end_request();

		$html = Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' );

		$this->assertStringContainsString( '1 email failed to send', $html );
		$this->assertStringNotContainsString( 'View failed emails', $html );
	}

	public function test_dismissed_notice_returns_on_a_newer_failure() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		update_option(
			Mail_Failure_Tracker::OPTION_NAME,
			[
				'daily'      => [ gmdate( 'Y-m-d' ) => 1 ],
				'last_at'    => '2026-10-03T06:00:00Z',
				'last_error' => 'Error',
			],
			false
		);

		$this->assertStringContainsString( 'Dismiss until the next failure', Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' ) );

		update_user_meta( $user_id, Sidebar_Mail_Failures_Dropin::USER_META_DISMISSED, '2026-10-03T06:00:00Z' );
		$this->assertSame( '', Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' ), 'Hidden after dismissing.' );

		$stored            = get_option( Mail_Failure_Tracker::OPTION_NAME );
		$stored['last_at'] = '2026-10-03T07:30:00Z';
		update_option( Mail_Failure_Tracker::OPTION_NAME, $stored, false );

		$this->assertStringContainsString( '1 email failed to send', Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' ), 'Back after a newer failure.' );
	}
}
