<?php

namespace Simple_History\Services;

use Simple_History\Dropins\Export_Dropin;
use Simple_History\Helpers;
use Simple_History\Menu_Manager;

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
	/**
	 * Option that holds the notice state: "pending", "dismissed" or "expired".
	 * Legacy sites may still have "shown" from before dismissal was tracked;
	 * that also means done, same as any other value that is not "pending".
	 * Absent on sites installed before this existed.
	 */
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

	/** Action name for the AJAX request that dismisses the notice. */
	const DISMISS_AJAX_ACTION = 'simple_history_dismiss_first_purge_notice';

	/** Nonce action used to verify the dismiss request. */
	const DISMISS_NONCE_ACTION = 'simple_history_dismiss_first_purge_notice';

	/** @var bool Whether the notice was output during this request, so admin_footer knows whether to print the dismiss script. */
	private $notice_was_output = false;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		add_action( 'admin_notices', [ $this, 'maybe_show_notice' ] );
		add_action( 'wp_ajax_' . self::DISMISS_AJAX_ACTION, [ $this, 'ajax_dismiss' ] );
		add_action( 'admin_footer', [ $this, 'maybe_print_dismiss_script' ] );
	}

	/**
	 * Flag the notice as pending. Called once, on a fresh install.
	 */
	public static function set_pending() {
		update_option( self::OPTION_NAME, 'pending', false );
	}

	/**
	 * Flag the notice as dismissed, so it stops showing for good.
	 *
	 * Called from the AJAX handler when the user closes the notice.
	 */
	public static function dismiss() {
		update_option( self::OPTION_NAME, 'dismissed', false );
	}

	/**
	 * Handle the AJAX request sent when the user dismisses the notice.
	 */
	public function ajax_dismiss() {
		check_ajax_referer( self::DISMISS_NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		self::dismiss();

		wp_send_json_success();
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

		// Stays pending, and keeps showing on reload, until the user dismisses it
		// (see dismiss()) or the window above closes and marks it "expired".
		$this->output_notice( $retention_days, $days_until_purge );
	}

	/**
	 * Print the script that posts the dismissal to the server.
	 *
	 * WordPress adds the notice's close button (`.notice-dismiss`) itself, via
	 * its own admin-notices JavaScript, so the click listener uses event
	 * delegation on the document rather than binding directly to the button.
	 *
	 * Only prints when the notice was actually output this request, so pages
	 * without it do not carry the extra script.
	 */
	public function maybe_print_dismiss_script() {
		if ( ! $this->notice_was_output ) {
			return;
		}

		$nonce = wp_create_nonce( self::DISMISS_NONCE_ACTION );
		?>
		<script>
		document.addEventListener( 'click', function ( event ) {
			var dismissButton = event.target.closest( '.sh-FirstPurgeNotice .notice-dismiss' );

			if ( ! dismissButton ) {
				return;
			}

			var body = new URLSearchParams();
			body.append( 'action', <?php echo wp_json_encode( self::DISMISS_AJAX_ACTION ); ?> );
			body.append( 'nonce', <?php echo wp_json_encode( $nonce ); ?> );

			fetch( ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body.toString(),
			} );
		} );
		</script>
		<?php
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
		$heading = sprintf(
			/* translators: %d: number of days events are kept. */
			_n(
				'Simple History keeps the last %d day of events',
				'Simple History keeps the last %d days of events',
				$retention_days,
				'simple-history'
			),
			$retention_days
		);

		// Say why first, so the cleanup reads as housekeeping and not as a deadline.
		if ( $days_until_purge > 0 ) {
			$text = sprintf(
				/* translators: %d: number of days until the first cleanup. */
				_n(
					'That keeps your database small and the log fast. Once a week, older events are cleared out, and the first cleanup on this site is in %d day.',
					'That keeps your database small and the log fast. Once a week, older events are cleared out, and the first cleanup on this site is in %d days.',
					$days_until_purge,
					'simple-history'
				),
				$days_until_purge
			);
		} else {
			$text = __( 'That keeps your database small and the log fast. Once a week, older events are cleared out, and the first cleanup on this site has started.', 'simple-history' );
		}

		$premium_text = __( 'If you would rather keep them longer, Premium lets you choose how long, even forever. It also adds alerts, log forwarding and more.', 'simple-history' );

		$premium_cta = sprintf(
			'<p><a href="%1$s" class="sh-FirstPurgeNotice-cta" target="_blank" rel="noopener">%2$s</a></p>',
			esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', self::UTM_CAMPAIGN ) ),
			esc_html__( 'Keep your full history →', 'simple-history' )
		);

		$message = sprintf(
			'<p><strong>%1$s</strong></p><p>%2$s</p><p>%3$s</p>%4$s',
			esc_html( $heading ),
			esc_html( $text ),
			esc_html( $premium_text ),
			wp_kses(
				$premium_cta,
				[
					'p' => [],
					'a' => [
						'href'   => [],
						'class'  => [],
						'target' => [],
						'rel'    => [],
					],
				]
			)
		);

		// The free way to keep a copy, not tied to the deadline. Left out when the
		// Export tab is not available, e.g. because Export_Dropin was filtered out.
		$export_url = Menu_Manager::get_admin_url_by_slug( Export_Dropin::MENU_SLUG );

		if ( $export_url !== '' ) {
			$export_line = sprintf(
				/* translators: 1: opening link tag, 2: closing link tag. */
				__( 'You can also %1$sexport your log%2$s any time you want a copy.', 'simple-history' ),
				'<a href="' . esc_url( $export_url ) . '">',
				'</a>'
			);

			$message .= sprintf(
				'<p>%s</p>',
				wp_kses(
					$export_line,
					[
						'a' => [
							'href' => [],
						],
					]
				)
			);
		}

		// The other free alternative: a weekly record outside the site. Empty when
		// the user already gets the email or can not turn it on.
		$opt_in_html = Email_Report_Service::get_opt_in_html();

		if ( $opt_in_html !== '' ) {
			$message .= sprintf(
				'<p>%1$s</p>%2$s',
				esc_html__( 'Want a weekly overview too?', 'simple-history' ),
				$opt_in_html
			);
		}

		// Bail if function does not exist, i.e. WordPress < 6.4.
		if ( ! function_exists( 'wp_admin_notice' ) ) {
			return;
		}

		$this->notice_was_output = true;

		wp_admin_notice(
			$message,
			[
				'paragraph_wrap'     => false,
				'dismissible'        => true,
				'additional_classes' => [ 'sh-FirstPurgeNotice' ],
			]
		);
	}
}
