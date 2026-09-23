<?php
namespace Simple_History;

/**
 * Describes log initiator, i.e. who caused to log event to happened
 */
class Log_Initiators {
	// A WordPress user that at the log event created did exist in the wp database
	// May have been deleted when the log is viewed.
	public const WP_USER = 'wp_user';

	// Cron job run = WordPress initiated
	// Email sent to customer on webshop = system/wordpress/anonymous web user
	// Javascript error occurred on website = anonymous web user.
	public const WEB_USER = 'web_user';

	// WordPress core or plugins updated automatically via wp-cron.
	public const WORDPRESS = 'wp';

	// WP CLI / terminal.
	public const WP_CLI = 'wp_cli';

	// Unknown.
	public const OTHER = 'other';

	/**
	 * Translate the initiator value from a log row to a human readable string.
	 * E.g.
	 * "wp" becomes "WordPress".
	 * "wp_user" becomes "User (email@example.com)".
	 * "web_user" becomes "Anonymous web user".
	 * "other" becomes "Other".
	 *
	 * @param object $row Initiator value.
	 * @return string|false Human readable initiator string, or false if initiator is not set.
	 */
	public static function get_initiator_text_from_row( $row ) {
		if ( ! isset( $row->initiator ) ) {
			return false;
		}

		$initiator     = $row->initiator;
		$initiatorText = '';

		switch ( $initiator ) {
			case 'wp':
				$initiatorText = 'WordPress';
				break;
			case 'wp_cli':
				$initiatorText = 'WP-CLI';
				break;
			case 'wp_user':
				$user_id = $row->context['_user_id'] ?? null;
				$user    = get_user_by( 'id', $user_id );

				if ( $user_id > 0 && $user ) {
					// User still exists.
					$initiatorText = sprintf(
						'%1$s (%2$s)',
						$user->user_login,  // 1
						$user->user_email   // 2
					);
				} elseif ( $user_id > 0 ) {
					// Sender was a user, but user is deleted now.
					$initiatorText = sprintf(
						/* translators: 1: user id, 2: user email address, 3: user account name. */
						__( 'Deleted user (had id %1$s, email %2$s, login %3$s)', 'simple-history' ),
						$row->context['_user_id'] ?? '', // 1
						$row->context['_user_email'] ?? '', // 2
						$row->context['_user_login'] ?? '' // 3
					);
				} else {
					// No user context provided (e.g., for filter options), use generic label.
					$initiatorText = __( 'WordPress user', 'simple-history' );
				}
				break;
			case 'web_user':
				$initiatorText = __( 'Anonymous web user', 'simple-history' );
				break;
			case 'other':
				$initiatorText = _x( 'Other', 'Event header output, when initiator is unknown', 'simple-history' );
				break;
			default:
				$initiatorText = $initiator;
		}

		return $initiatorText;
	}

	/**
	 * Get a human-readable label for an initiator constant.
	 * Used for filter options and similar contexts where no specific event context is available.
	 *
	 * @param string $initiator The initiator constant.
	 * @return string Human readable initiator label.
	 */
	public static function get_initiator_label( $initiator ) {
		$labels = [
			self::WP_USER   => __( 'WordPress user', 'simple-history' ),
			self::WEB_USER  => __( 'Anonymous web user', 'simple-history' ),
			self::WORDPRESS => 'WordPress',
			self::WP_CLI    => 'WP-CLI',
			self::OTHER     => _x( 'Other', 'Event header output, when initiator is unknown', 'simple-history' ),
		];

		return $labels[ $initiator ] ?? $initiator;
	}

	/**
	 * Get the initiator implied by how the current request is running, if any.
	 *
	 * WP-CLI is always WP_CLI. A WP-Cron run or an executing Action Scheduler
	 * action is WordPress, even when an administrator is logged in: with
	 * ALTERNATE_WP_CRON, cron runs inside the visitor's own request, and Action
	 * Scheduler's async runner forwards the admin's cookies.
	 *
	 * Returns null for an ordinary request, where who is responsible depends on
	 * the event. Loggers that log later than the change happened (for example at
	 * shutdown) should call this when the change happens, since the Action
	 * Scheduler state is gone by then.
	 *
	 * @return string|null One of the initiator constants, or null.
	 */
	public static function get_automatic_initiator() {
		if ( Helpers::is_wp_cli() ) {
			return self::WP_CLI;
		}

		if ( wp_doing_cron() ) {
			return self::WORDPRESS;
		}

		if ( Services\Action_Scheduler_Tracker::is_running_scheduled_action() ) {
			return self::WORDPRESS;
		}

		return null;
	}

	/**
	 * Get all valid initiator constants.
	 *
	 * @return array Array of valid initiator constants.
	 */
	public static function get_valid_initiators() {
		return [
			self::WP_USER,
			self::WEB_USER,
			self::WORDPRESS,
			self::WP_CLI,
			self::OTHER,
		];
	}
}
