<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Mail_Failure_Logger;
use Simple_History\Dropins\Sidebar_Mail_Failures_Dropin;

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

		delete_option( Mail_Failure_Logger::OPTION_WINDOW );
		wp_clear_scheduled_hook( Mail_Failure_Logger::CRON_HOOK );
		delete_transient( Mail_Failure_Logger::TRANSIENT_STATS );

		// Start each test without events from earlier tests.
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->sh->get_events_table_name()} WHERE logger = %s", 'MailFailureLogger' ) );
	}

	public function tearDown(): void {
		delete_option( Mail_Failure_Logger::OPTION_WINDOW );
		wp_clear_scheduled_hook( Mail_Failure_Logger::CRON_HOOK );

		parent::tearDown();
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

		$window = get_option( Mail_Failure_Logger::OPTION_WINDOW );
		$this->assertSame( 3, $window['skipped'] );

		// The summary is scheduled for when the window ends.
		$this->assertSame(
			$window['start'] + Mail_Failure_Logger::WINDOW_SECONDS,
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

		// State and schedule are cleared.
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
				'start'   => time() - Mail_Failure_Logger::WINDOW_SECONDS - 10,
				'logged'  => Mail_Failure_Logger::MAX_LOGGED_PER_WINDOW,
				'skipped' => 7,
			],
			false
		);

		do_action( 'wp_mail_failed', $this->make_error() );

		$this->assertSame( 1, $this->count_events( 'mail_send_failures_skipped' ) );
		$this->assertSame( 1, $this->count_events( 'mail_send_failed' ) );

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
		$masked = Mail_Failure_Logger::mask_error_message( 'Error ' . str_repeat( 'x', 1000 ) );

		$this->assertStringStartsWith( 'Error x', $masked );
		$this->assertSame( Mail_Failure_Logger::MAX_ERROR_LENGTH + 1, preg_match_all( '/./us', $masked ) );
		$this->assertStringEndsWith( '…', $masked );

		// Multibyte text is cut on a character boundary, never inside one.
		$cut = Mail_Failure_Logger::mask_error_message( str_repeat( 'ö', 600 ) );
		$this->assertSame( 1, preg_match( '//u', $cut ) );
		$this->assertSame( str_repeat( 'ö', Mail_Failure_Logger::MAX_ERROR_LENGTH ) . '…', $cut );

		// Short messages are left alone.
		$this->assertSame( 'Short', Mail_Failure_Logger::mask_error_message( 'Short' ) );
	}

	public function test_mask_error_message_masks_every_address() {
		$this->assertSame(
			'Failed: ***@example.com, <***@mail.example.org>',
			Mail_Failure_Logger::mask_error_message( 'Failed: first.last+tag@example.com, <x@mail.example.org>' )
		);
	}

	public function test_stats_count_logged_and_skipped_failures() {
		$total = Mail_Failure_Logger::MAX_LOGGED_PER_WINDOW + 3;

		for ( $i = 0; $i < $total; $i++ ) {
			do_action( 'wp_mail_failed', $this->make_error( 'Error number ' . $i ) );
		}

		// Writes the summary for the 3 skipped ones.
		do_action( Mail_Failure_Logger::CRON_HOOK );

		$stats = Mail_Failure_Logger::get_recent_failure_stats();

		$this->assertSame( $total, $stats['count'] );
		$this->assertSame( 'Error number 4', $stats['last_error'], 'Last error is from the newest logged failure, not the summary.' );
		$this->assertNotSame( '', $stats['last_date'] );
	}

	public function test_stats_are_empty_without_failures() {
		$stats = Mail_Failure_Logger::get_recent_failure_stats();

		$this->assertSame( 0, $stats['count'] );
		$this->assertSame( '', $stats['last_error'] );
	}

	public function test_stats_cache_is_cleared_when_a_failure_is_logged() {
		$this->assertSame( 0, Mail_Failure_Logger::get_recent_failure_stats()['count'] );

		do_action( 'wp_mail_failed', $this->make_error() );

		$this->assertSame( 1, Mail_Failure_Logger::get_recent_failure_stats()['count'] );
	}

	public function test_notice_shown_to_admins_with_failures_only() {
		$admin = $this->factory->user->create( [ 'role' => 'administrator' ] );
		$editor = $this->factory->user->create( [ 'role' => 'editor' ] );

		wp_set_current_user( $admin );
		$this->assertSame( '', Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' ), 'No notice without failures.' );

		do_action( 'wp_mail_failed', $this->make_error( 'Could not <b>connect</b>' ) );

		$html = Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' );
		$this->assertStringContainsString( '1 email failed to send', $html );
		$this->assertStringContainsString( 'Could not &lt;b&gt;connect&lt;/b&gt;', $html, 'The error is escaped.' );
		$this->assertStringContainsString( 'MailFailureLogger', urldecode( $html ), 'Links to the failed email events.' );

		wp_set_current_user( $editor );
		$this->assertSame( '', Sidebar_Mail_Failures_Dropin::get_notice_html( 'sidebar' ), 'Hidden from non-admins.' );
	}
}
