<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Services\Mail_Failure_Tracker;

/**
 * Logs emails that WordPress failed to send.
 *
 * Hooks wp_mail_failed only, so a normal send runs no code from here. Only the
 * error is stored, with email addresses masked, and the number of recipients.
 * Addresses, subjects and message bodies are not stored. Logging every email,
 * including the content, is what the Debug and Monitor add-on does, and when
 * that add-on is active this logger stays quiet so failures are not logged twice.
 *
 * A broken mail setup can fail hundreds of times an hour, so at most
 * MAX_LOGGED_PER_WINDOW failures are logged per WINDOW_SECONDS. The rest are
 * counted and written as one summary event when the window ends. The window
 * is kept in memory and written once per request, on shutdown.
 *
 * Counting failures for the notice is Mail_Failure_Tracker's job, not this
 * logger's, so the notice works even when the
 * simple_history/mail_failures/log_events filter turns logging off.
 *
 * Requires experimental features to be enabled.
 */
class Mail_Failure_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'MailFailureLogger';

	/** @var int Failures logged individually per window. */
	public const MAX_LOGGED_PER_WINDOW = 5;

	/** @var int Length of the throttling window, in seconds. */
	public const WINDOW_SECONDS = HOUR_IN_SECONDS;

	/** @var string Option holding the current throttling window. */
	public const OPTION_WINDOW = 'simple_history_mail_failure_window';

	/** @var string Cron hook that writes the summary when a window ends. */
	public const CRON_HOOK = 'simple_history/mail_failure_logger/write_summary';

	/** @var string Slug of the Debug and Monitor add-on's mail logger. */
	public const DEBUG_AND_MONITOR_MAIL_LOGGER_SLUG = 'WPMailLogger';

	/**
	 * The current throttling window, null until read in this request.
	 *
	 * @var array{start: string, logged: int, skipped: int}|null
	 */
	private $window = null;

	/**
	 * Whether the window changed in this request and needs to be written.
	 *
	 * @var bool
	 */
	private $window_changed = false;

	/**
	 * Get array with information about this logger.
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => __( 'Failed Email Logger', 'simple-history' ),
			'description' => __( 'Logs emails that WordPress failed to send', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'mail_send_failed'           => _x(
					'Failed to send an email',
					'Failed email logger: email failed',
					'simple-history'
				),
				'mail_send_failures_skipped' => _x(
					'Failed to send {mail_skipped_count} more emails that were not logged one by one',
					'Failed email logger: summary of failures not logged individually',
					'simple-history'
				),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'Failed emails', 'Failed email logger: search', 'simple-history' ),
					'options' => array(
						_x( 'Failed emails', 'Failed email logger: search', 'simple-history' ) => array(
							'mail_send_failed',
							'mail_send_failures_skipped',
						),
					),
				),
			),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		add_action( 'wp_mail_failed', array( $this, 'on_wp_mail_failed' ) );
		add_action( self::CRON_HOOK, array( $this, 'write_summary' ) );
	}

	/**
	 * Whether failed emails are logged as events.
	 *
	 * @return bool
	 */
	public static function is_logging_events() {
		/**
		 * Filters whether failed emails are logged as events.
		 *
		 * When false, failures are still counted for the notice in the
		 * sidebar and the email settings, but nothing is added to the log.
		 *
		 * @since 5.35.0
		 *
		 * @param bool $log_events Whether to log failed emails. Default true.
		 */
		return (bool) apply_filters( 'simple_history/mail_failures/log_events', true );
	}

	/**
	 * Log a failed email, unless the throttle or the Debug and Monitor add-on says not to.
	 *
	 * @param \WP_Error $error Error from wp_mail(). Its data has the recipients,
	 *                         subject, message, headers and attachments.
	 */
	public function on_wp_mail_failed( $error ) {
		if ( ! $error instanceof \WP_Error ) {
			return;
		}

		if ( ! self::is_logging_events() ) {
			return;
		}

		if ( $this->is_debug_and_monitor_mail_logger_active() ) {
			return;
		}

		$window = $this->get_current_window();

		if ( $window['logged'] >= self::MAX_LOGGED_PER_WINDOW ) {
			++$window['skipped'];
			$this->set_window( $window );

			// Write the summary when the window ends, even if no more emails fail.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( strtotime( $window['start'] ) + self::WINDOW_SECONDS, self::CRON_HOOK );
			}

			return;
		}

		++$window['logged'];
		$this->set_window( $window );

		$error_data = $error->get_error_data();
		$recipients = is_array( $error_data ) ? (array) ( $error_data['to'] ?? array() ) : array();

		$this->error_message(
			'mail_send_failed',
			array(
				'mail_error'           => Mail_Failure_Tracker::mask_error_message( $error->get_error_message() ),
				'mail_recipient_count' => count( $recipients ),
			)
		);
	}

	/**
	 * Write the summary event for the window that ended, if any failures were skipped.
	 *
	 * Runs from cron when a window ends, and from get_current_window() when a new
	 * failure arrives after the window ended but before cron ran.
	 */
	public function write_summary() {
		$window = $this->window ?? self::read_window();

		delete_option( self::OPTION_WINDOW );
		wp_clear_scheduled_hook( self::CRON_HOOK );

		$this->window         = null;
		$this->window_changed = false;

		if ( $window === null || $window['skipped'] === 0 ) {
			return;
		}

		$start = strtotime( $window['start'] );

		$this->error_message(
			'mail_send_failures_skipped',
			array(
				'mail_skipped_count' => $window['skipped'],
				'mail_window_start'  => wp_date( 'Y-m-d H:i:s', $start ),
				'mail_window_end'    => wp_date( 'Y-m-d H:i:s', $start + self::WINDOW_SECONDS ),
			)
		);
	}

	/**
	 * Write the window to its option, if it changed in this request.
	 */
	public function save_window() {
		if ( ! $this->window_changed || $this->window === null ) {
			return;
		}

		update_option( self::OPTION_WINDOW, $this->window, false );

		$this->window_changed = false;
	}

	/**
	 * Get the current throttling window, starting a new one if the last one ended.
	 *
	 * @return array{start: string, logged: int, skipped: int} start is ISO 8601 UTC.
	 */
	private function get_current_window() {
		if ( $this->window === null ) {
			$this->window = self::read_window();
		}

		if ( $this->window !== null && time() < strtotime( $this->window['start'] ) + self::WINDOW_SECONDS ) {
			return $this->window;
		}

		// The last window ended. Write its summary before starting a new one.
		if ( $this->window !== null ) {
			$this->write_summary();
		}

		return array(
			'start'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'logged'  => 0,
			'skipped' => 0,
		);
	}

	/**
	 * Keep the changed window in memory, to be written on shutdown.
	 *
	 * @param array{start: string, logged: int, skipped: int} $window Window.
	 */
	private function set_window( $window ) {
		$this->window         = $window;
		$this->window_changed = true;

		if ( has_action( 'shutdown', array( $this, 'save_window' ) ) ) {
			return;
		}

		add_action( 'shutdown', array( $this, 'save_window' ) );
	}

	/**
	 * Read the window option.
	 *
	 * @return array{start: string, logged: int, skipped: int}|null Null when there is none, or it can't be read.
	 */
	private static function read_window() {
		$window = get_option( self::OPTION_WINDOW );

		if ( ! is_array( $window ) || ! isset( $window['start'] ) || ! is_string( $window['start'] ) || strtotime( $window['start'] ) === false ) {
			return null;
		}

		return array(
			'start'   => $window['start'],
			'logged'  => (int) ( $window['logged'] ?? 0 ),
			'skipped' => (int) ( $window['skipped'] ?? 0 ),
		);
	}

	/**
	 * Check if the Debug and Monitor add-on logs emails, failures included.
	 *
	 * @return bool
	 */
	protected function is_debug_and_monitor_mail_logger_active() {
		return $this->simple_history->get_instantiated_logger_by_slug( self::DEBUG_AND_MONITOR_MAIL_LOGGER_SLUG ) !== false;
	}

	/**
	 * Get output for the event details.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|string
	 */
	public function get_log_row_details_output( $row ) {
		$message_key = $row->context['_message_key'] ?? '';
		$group       = new Event_Details_Group();

		if ( $message_key === 'mail_send_failed' ) {
			$group->add_item( new Event_Details_Item( 'mail_error', __( 'Error', 'simple-history' ) ) );
			$group->add_item( new Event_Details_Item( 'mail_recipient_count', __( 'Recipients', 'simple-history' ) ) );
		} elseif ( $message_key === 'mail_send_failures_skipped' ) {
			$group->add_item( new Event_Details_Item( 'mail_window_start', __( 'From', 'simple-history' ) ) );
			$group->add_item( new Event_Details_Item( 'mail_window_end', __( 'To', 'simple-history' ) ) );
		}

		if ( empty( $group->items ) ) {
			return '';
		}

		return $group;
	}
}
