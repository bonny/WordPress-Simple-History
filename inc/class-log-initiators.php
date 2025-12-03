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
	 * @return string Human readable initiator string.
	 */
	public static function get_initiator_text_from_row( $row ) {
		$context = array();

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
						$context['_user_id'] ?? '', // 1
						$context['_user_email'] ?? '', // 2
						$context['_user_login'] ?? '' // 3
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
