<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

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
 * counted and written as one summary event when the window ends.
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

	/** @var int Longest error message stored, in characters. */
	public const MAX_ERROR_LENGTH = 500;

	/** @var string Option holding the current throttling window. */
	public const OPTION_WINDOW = 'simple_history_mail_failure_window';

	/** @var string Cron hook that writes the summary when a window ends. */
	public const CRON_HOOK = 'simple_history/mail_failure_logger/write_summary';

	/** @var string Slug of the Debug and Monitor add-on's mail logger. */
	private const DEBUG_AND_MONITOR_MAIL_LOGGER_SLUG = 'WPMailLogger';

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
	 * Log a failed email, unless the throttle or the Debug and Monitor add-on says not to.
	 *
	 * @param \WP_Error $error Error from wp_mail(). Its data has the recipients,
	 *                         subject, message, headers and attachments.
	 */
	public function on_wp_mail_failed( $error ) {
		if ( ! $error instanceof \WP_Error ) {
			return;
		}

		if ( $this->is_debug_and_monitor_mail_logger_active() ) {
			return;
		}

		$window = $this->get_current_window();

		if ( $window['logged'] >= self::MAX_LOGGED_PER_WINDOW ) {
			++$window['skipped'];
			update_option( self::OPTION_WINDOW, $window, false );

			// Write the summary when the window ends, even if no more emails fail.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( $window['start'] + self::WINDOW_SECONDS, self::CRON_HOOK );
			}

			return;
		}

		++$window['logged'];
		update_option( self::OPTION_WINDOW, $window, false );

		$error_data = $error->get_error_data();
		$recipients = is_array( $error_data ) ? (array) ( $error_data['to'] ?? array() ) : array();

		$this->error_message(
			'mail_send_failed',
			array(
				'mail_error'           => self::mask_error_message( $error->get_error_message() ),
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
		$window = get_option( self::OPTION_WINDOW );

		delete_option( self::OPTION_WINDOW );
		wp_clear_scheduled_hook( self::CRON_HOOK );

		if ( ! is_array( $window ) || empty( $window['skipped'] ) ) {
			return;
		}

		$this->error_message(
			'mail_send_failures_skipped',
			array(
				'mail_skipped_count' => (int) $window['skipped'],
				'mail_window_start'  => wp_date( 'Y-m-d H:i:s', (int) $window['start'] ),
				'mail_window_end'    => wp_date( 'Y-m-d H:i:s', (int) $window['start'] + self::WINDOW_SECONDS ),
			)
		);
	}

	/**
	 * Get the current throttling window, starting a new one if the last one ended.
	 *
	 * @return array{start: int, logged: int, skipped: int}
	 */
	private function get_current_window() {
		$window = get_option( self::OPTION_WINDOW );

		if ( is_array( $window ) && time() < (int) $window['start'] + self::WINDOW_SECONDS ) {
			return array(
				'start'   => (int) $window['start'],
				'logged'  => (int) $window['logged'],
				'skipped' => (int) $window['skipped'],
			);
		}

		// The last window ended. Write its summary before starting a new one.
		if ( is_array( $window ) ) {
			$this->write_summary();
		}

		return array(
			'start'   => time(),
			'logged'  => 0,
			'skipped' => 0,
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
	 * Mask email addresses in an error message and cap its length.
	 *
	 * Tags are kept as they are, since mail errors write addresses as <x@y>.
	 * Output is escaped like all other context values.
	 *
	 * Mail errors can name the recipient ("The following recipients failed: ...").
	 * The domain is kept, since "failed for every gmail.com address" is useful
	 * when debugging, but the part before the @ is not.
	 *
	 * @param string $message Error message.
	 * @return string
	 */
	public static function mask_error_message( $message ) {
		$message = (string) preg_replace( '/[^\s<>"\'(),;:]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/', '***@$1', (string) $message );
		$message = trim( $message );

		// Cut on a character boundary. PCRE's u flag works without mbstring.
		if ( preg_match( '/^.{' . self::MAX_ERROR_LENGTH . '}(?=.)/us', $message, $matches ) ) {
			$message = $matches[0] . '…';
		}

		return $message;
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
