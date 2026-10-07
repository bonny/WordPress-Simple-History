<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Counts emails that WordPress failed to send, in an option.
 *
 * Separate from Mail_Failure_Logger: the count and the last error are kept
 * here whether or not failures are logged as events, so the notice about
 * failed emails works on its own.
 *
 * The option holds day counts for the last DAYS days, the time of the last
 * failure and its error message, with email addresses masked. Nothing else:
 * no addresses, subjects or message bodies.
 *
 * Failures are counted in memory and written once per request, on shutdown,
 * so a bulk send that fails thousands of times costs one option write.
 * Two requests failing at the same moment can each overwrite the other's
 * count, so the count is approximate. That is fine for a notice.
 *
 * Requires experimental features to be enabled.
 */
class Mail_Failure_Tracker extends Service {
	/** @var string Option holding the failure counts. Not autoloaded. */
	public const OPTION_NAME = 'simple_history_mail_failures';

	/** @var int Days of failures kept and counted. */
	public const DAYS = 30;

	/** @var int Longest error message stored, in characters. */
	public const MAX_ERROR_LENGTH = 500;

	/**
	 * Failures counted in this request and not written yet.
	 *
	 * @var int
	 */
	private $pending_count = 0;

	/**
	 * Masked error of the newest failure in this request.
	 *
	 * @var string
	 */
	private $pending_last_error = '';

	/**
	 * Time of the newest failure in this request, ISO 8601 UTC.
	 *
	 * @var string
	 */
	private $pending_last_at = '';

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_action( 'wp_mail_failed', [ $this, 'on_wp_mail_failed' ] );
	}

	/**
	 * Count a failed email. Written to the option at the end of the request.
	 *
	 * @param \WP_Error $error Error from wp_mail().
	 */
	public function on_wp_mail_failed( $error ) {
		if ( ! $error instanceof \WP_Error ) {
			return;
		}

		++$this->pending_count;
		$this->pending_last_error = self::mask_error_message( $error->get_error_message() );
		$this->pending_last_at    = gmdate( 'Y-m-d\TH:i:s\Z' );

		if ( has_action( 'shutdown', [ $this, 'save' ] ) ) {
			return;
		}

		add_action( 'shutdown', [ $this, 'save' ] );
	}

	/**
	 * Write the failures counted in this request to the option.
	 */
	public function save() {
		if ( $this->pending_count === 0 ) {
			return;
		}

		$stored = self::get_stored();
		$day    = substr( $this->pending_last_at, 0, 10 );

		$stored['daily'][ $day ] = ( $stored['daily'][ $day ] ?? 0 ) + $this->pending_count;
		$stored['last_at']       = $this->pending_last_at;
		$stored['last_error']    = $this->pending_last_error;

		update_option( self::OPTION_NAME, $stored, false );

		$this->pending_count      = 0;
		$this->pending_last_error = '';
		$this->pending_last_at    = '';
	}

	/**
	 * Get the failures in the last DAYS days.
	 *
	 * @return array{count: int, last_at: string, last_error: string} last_at is ISO 8601 UTC, or '' when count is 0.
	 */
	public static function get_stats() {
		$stored = self::get_stored();
		$count  = (int) array_sum( $stored['daily'] );

		if ( $count === 0 ) {
			return [
				'count'      => 0,
				'last_at'    => '',
				'last_error' => '',
			];
		}

		return [
			'count'      => $count,
			'last_at'    => $stored['last_at'],
			'last_error' => $stored['last_error'],
		];
	}

	/**
	 * Read the option, with days older than DAYS left out.
	 *
	 * @return array{daily: array<string, int>, last_at: string, last_error: string}
	 */
	private static function get_stored() {
		$stored = get_option( self::OPTION_NAME );
		$stored = is_array( $stored ) ? $stored : [];
		$oldest = gmdate( 'Y-m-d', time() - ( self::DAYS - 1 ) * DAY_IN_SECONDS );
		$daily  = [];

		foreach ( (array) ( $stored['daily'] ?? [] ) as $day => $count ) {
			// Keys are Y-m-d, so comparing them as strings compares the dates.
			if ( ! is_string( $day ) || $day < $oldest ) {
				continue;
			}

			$daily[ $day ] = (int) $count;
		}

		return [
			'daily'      => $daily,
			'last_at'    => is_string( $stored['last_at'] ?? null ) ? $stored['last_at'] : '',
			'last_error' => is_string( $stored['last_error'] ?? null ) ? $stored['last_error'] : '',
		];
	}

	/**
	 * Mask email addresses in an error message and cap its length.
	 *
	 * Tags are kept as they are, since mail errors write addresses as <x@y>.
	 * Output is escaped wherever the message is shown.
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
}
