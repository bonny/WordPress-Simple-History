<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Shows a one-time notice shortly before a new install starts removing the
 * events it has logged itself.
 *
 * Free sites keep events for a limited number of days, and until now the first
 * time anyone noticed was after the history was gone. This tells the user once,
 * a few days before, while upgrading still keeps everything.
 *
 * Only new installs get the notice: the flag is set in the fresh install branch
 * of Setup_Database. Existing sites are already past their first cleanup.
 *
 * The timing is based on the install date, not on the oldest event. The
 * auto-backfill at install adds events dated up to the retention period back,
 * so the oldest event is close to its deletion date from the first day. The
 * purge only runs on one day of the week, so the notice counts down to the
 * first purge day after install date + retention.
 *
 * To show the notice again on a test site:
 *
 * `$ docker compose run --rm wpcli_mariadb option update simple_history_first_purge_notice pending`
 *
 * and set `simple_history_install_date_gmt` to a date about retention days minus 5 ago.
 */
class First_Purge_Notice_Service extends Service {
	/** Option that holds the notice state: "pending", "shown" or "expired". Absent on sites installed before this existed. */
	const OPTION_NAME = 'simple_history_first_purge_notice';

	/** Show the notice this many days before the first purge of events logged after install. */
	const DAYS_BEFORE_PURGE = 5;

	/**
	 * Keep offering the notice this many days after that purge started, reworded,
	 * for sites where nobody opened a Simple History page in the days before.
	 */
	const DAYS_AFTER_PURGE = 7;

	/** UTM campaign for the Premium link. */
	const UTM_CAMPAIGN = 'premium_retention_first_purge';

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		add_action( 'admin_notices', [ $this, 'maybe_show_notice' ] );
	}

	/**
	 * Flag the notice as pending. Called once, on a fresh install.
	 */
	public static function set_pending() {
		update_option( self::OPTION_NAME, 'pending', false );
	}

	/**
	 * Show the notice if this is the right site, user, page and time.
	 */
	public function maybe_show_notice() {
		// Cheap checks first. This runs on every admin page, and the option below does
		// not exist on sites installed before this notice, which costs a query to look up.
		if ( ! function_exists( 'wp_admin_notice' ) || ! current_user_can( 'manage_options' ) || ! self::is_on_plugin_page() ) {
			return;
		}

		// Sites installed before this notice existed have no option and never get it.
		if ( get_option( self::OPTION_NAME ) !== 'pending' ) {
			return;
		}

		$retention_days = Helpers::get_clear_history_interval();

		// Nothing is ever removed, or Premium (which has its own retention setting) is active.
		if ( $retention_days <= 0 || ! Helpers::show_promo_boxes() ) {
			return;
		}

		$days_until_purge = self::get_days_until_first_purge( $retention_days );

		if ( $days_until_purge === null || $days_until_purge > self::DAYS_BEFORE_PURGE ) {
			return;
		}

		// Too late to be useful. Stop checking.
		if ( $days_until_purge < -self::DAYS_AFTER_PURGE ) {
			update_option( self::OPTION_NAME, 'expired', false );

			return;
		}

		// Shown once per site, to whichever admin sees it first.
		update_option( self::OPTION_NAME, 'shown', false );

		$this->output_notice( $retention_days, $days_until_purge );
	}

	/**
	 * Check if the current screen is one of Simple History's own admin pages.
	 *
	 * Helpers::is_on_our_own_pages() also counts the WordPress dashboard when the
	 * dashboard widget is on, and a retention notice does not belong there. The
	 * plugin's own pages always have a `page` query arg, also when the menu is placed
	 * under Dashboard (index.php?page=...), so require one.
	 *
	 * @return bool
	 */
	private static function is_on_plugin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of which admin page is being viewed.
		if ( empty( $_GET['page'] ) ) {
			return false;
		}

		return Helpers::is_on_our_own_pages();
	}

	/**
	 * Get the start of the day, in GMT, when events logged on the install day are first removed.
	 *
	 * The purge cron runs daily but only removes events on one day of the week
	 * (Sunday by default), so the first removal is the first purge day on or
	 * after install date + retention, not install date + retention itself.
	 *
	 * @param int $retention_days Number of days events are kept.
	 * @return int|null Timestamp of midnight GMT on the first purge day. Null if the install date is unknown.
	 */
	public static function get_first_purge_day_timestamp( $retention_days ) {
		$install_date = Helpers::get_plugin_install_date();

		if ( ! $install_date ) {
			return null;
		}

		$install_timestamp = strtotime( $install_date . ' UTC' );

		if ( $install_timestamp === false ) {
			return null;
		}

		// When the events logged at install are older than the retention period.
		$expires_timestamp = $install_timestamp + ( $retention_days * DAY_IN_SECONDS );

		// Walk forward from that day to the purge day. On the purge day itself,
		// count it even if the cron may run before the exact time, to warn early rather than late.
		$day_timestamp      = strtotime( gmdate( 'Y-m-d', $expires_timestamp ) . ' 00:00:00 UTC' );
		$day_of_week_purged = (int) Setup_Purge_DB_Cron::get_day_of_week_to_purge_db();

		for ( $i = 0; $i < 7; $i++ ) {
			if ( (int) gmdate( 'N', $day_timestamp ) === $day_of_week_purged ) {
				return $day_timestamp;
			}

			$day_timestamp += DAY_IN_SECONDS;
		}

		// The filter returned something other than 1-7.
		return null;
	}

	/**
	 * Get the number of whole days until events logged on the install day start being removed.
	 *
	 * @param int      $retention_days Number of days events are kept.
	 * @param int|null $now Current timestamp, for tests. Defaults to time().
	 * @return int|null Days until the first purge day, zero on that day and negative after it. Null if unknown.
	 */
	public static function get_days_until_first_purge( $retention_days, $now = null ) {
		$first_purge_day_timestamp = self::get_first_purge_day_timestamp( $retention_days );

		if ( $first_purge_day_timestamp === null ) {
			return null;
		}

		$now = $now ?? time();

		return (int) ceil( ( $first_purge_day_timestamp - $now ) / DAY_IN_SECONDS );
	}

	/**
	 * Output the notice.
	 *
	 * @param int $retention_days Number of days events are kept.
	 * @param int $days_until_purge Days until the first purge, zero or negative when it has started.
	 */
	private function output_notice( $retention_days, $days_until_purge ) {
		if ( $days_until_purge > 0 ) {
			$heading = sprintf(
				/* translators: %d: number of days until events start being removed. */
				_n(
					'In %d day, Simple History starts removing the activity logged since you installed it.',
					'In %d days, Simple History starts removing the activity logged since you installed it.',
					$days_until_purge,
					'simple-history'
				),
				$days_until_purge
			);
		} else {
			$heading = __( 'Simple History has started removing the activity logged since you installed it.', 'simple-history' );
		}

		$text = sprintf(
			/* translators: %d: number of days events are kept. */
			_n(
				'Once a week, events older than %d day are removed.',
				'Once a week, events older than %d days are removed.',
				$retention_days,
				'simple-history'
			),
			$retention_days
		);

		$premium_link = sprintf(
			/* translators: 1: opening link tag, 2: closing link tag. */
			__( '%1$sSimple History Premium%2$s lets you keep your history for as long as you need, or forever.', 'simple-history' ),
			'<a href="' . esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', self::UTM_CAMPAIGN ) ) . '" target="_blank">',
			'</a>'
		);

		$message = sprintf(
			'<p><strong>%1$s</strong></p><p>%2$s %3$s</p>',
			esc_html( $heading ),
			esc_html( $text ),
			wp_kses(
				$premium_link,
				[
					'a' => [
						'href'   => [],
						'target' => [],
					],
				]
			)
		);

		// The free alternative: a weekly record outside the site. Empty when the
		// user already gets the email or can not turn it on.
		$opt_in_html = Email_Report_Service::get_opt_in_html();

		if ( $opt_in_html !== '' ) {
			$message .= sprintf(
				'<p>%1$s</p>%2$s',
				esc_html__( 'Or get a summary by email every week, so you keep a record of what happened.', 'simple-history' ),
				$opt_in_html
			);
		}

		// Bail if function does not exist, i.e. WordPress < 6.4.
		if ( ! function_exists( 'wp_admin_notice' ) ) {
			return;
		}

		wp_admin_notice(
			$message,
			[
				'paragraph_wrap' => false,
				'type'           => 'info',
				'dismissible'    => true,
			]
		);
	}
}
