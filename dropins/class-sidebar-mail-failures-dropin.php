<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;
use Simple_History\Loggers\Mail_Failure_Logger;
use Simple_History\Services\Mail_Failure_Tracker;
use Simple_History\Simple_History;

/**
 * Tells admins when emails from their site failed to send.
 *
 * Shown in the sidebar and on the weekly email settings when emails failed
 * in the last 30 days, as counted by Mail_Failure_Tracker. It works from that
 * count alone, so it is shown even when failures are not logged as events.
 * The free part is the count, the last error and, when failures are logged, a
 * link to the events. A single line under it mentions the Debug and Monitor
 * add-on, which logs every email, and is left out when promo boxes are hidden
 * or that add-on already logs mail.
 *
 * Each user can dismiss it. It comes back when a newer failure happens.
 *
 * Requires experimental features to be enabled.
 */
class Sidebar_Mail_Failures_Dropin extends Dropin {
	/** @var string User meta with the last_at of the failures the user dismissed. ISO 8601 UTC. */
	public const USER_META_DISMISSED = 'simple_history_mail_failures_dismissed';

	/** @var string admin-post action that dismisses the notice. */
	public const DISMISS_ACTION = 'simple_history_dismiss_mail_failures';

	/**
	 * Called when dropin is loaded.
	 */
	public function loaded() {
		// Priority 5, above the promo cards: this is about a problem on the site.
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html' ], 5 );
		add_action( 'admin_post_' . self::DISMISS_ACTION, [ $this, 'on_dismiss' ] );
	}

	/**
	 * Dismiss the notice for the current user, until a newer failure happens.
	 */
	public function on_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'simple-history' ), 403 );
		}

		check_admin_referer( self::DISMISS_ACTION );

		// Store what the user saw, not "now", so a failure between page load and click still shows.
		$last_at = isset( $_POST['last_at'] ) ? sanitize_text_field( wp_unslash( $_POST['last_at'] ) ) : '';

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $last_at ) ) {
			update_user_meta( get_current_user_id(), self::USER_META_DISMISSED, $last_at );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : Helpers::get_history_admin_url() );
		exit;
	}

	/**
	 * Whether the current user dismissed the notice for these failures.
	 *
	 * @param string $last_at Time of the newest failure, ISO 8601 UTC.
	 * @return bool
	 */
	private static function is_dismissed( $last_at ) {
		$dismissed_at = get_user_meta( get_current_user_id(), self::USER_META_DISMISSED, true );

		// Same format on both sides, so comparing strings compares times.
		return is_string( $dismissed_at ) && $dismissed_at !== '' && $last_at <= $dismissed_at;
	}

	/**
	 * Whether failed emails can be found in the log.
	 *
	 * Not when logging is turned off, and not when the Debug and Monitor add-on
	 * logs mail, since Mail_Failure_Logger stays quiet then.
	 *
	 * @return bool
	 */
	private static function failures_are_logged() {
		// The logger is loaded whenever the notice is shown, since both need experimental features.
		return Mail_Failure_Logger::is_logging_events() && ! self::debug_and_monitor_logs_mail();
	}

	/**
	 * Whether the Debug and Monitor add-on logs mail.
	 *
	 * @return bool
	 */
	private static function debug_and_monitor_logs_mail() {
		return Simple_History::get_instance()->get_instantiated_logger_by_slug( Mail_Failure_Logger::DEBUG_AND_MONITOR_MAIL_LOGGER_SLUG ) !== false;
	}

	/**
	 * Output the sidebar box.
	 */
	public function on_sidebar_html() {
		$html = self::get_notice_html( 'sidebar' );

		if ( $html === '' ) {
			return;
		}

		?>
		<div class="postbox sh-MailFailuresPostbox">
			<div class="inside">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in get_notice_html().
				echo $html;
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the notice content, or an empty string when there is nothing to show.
	 *
	 * @param string $location Where it is shown, 'sidebar' or 'email_settings'. Used for link tracking.
	 * @return string HTML.
	 */
	public static function get_notice_html( $location ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		// Failures are only counted when experimental features are on.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return '';
		}

		$stats = Mail_Failure_Tracker::get_stats();

		if ( $stats['count'] === 0 || self::is_dismissed( $stats['last_at'] ) ) {
			return '';
		}

		$events_url = Helpers::get_filtered_history_url(
			[
				'date'     => 'lastdays:' . Mail_Failure_Tracker::DAYS,
				'messages' => [
					[
						'value'          => __( 'Failed emails', 'simple-history' ),
						'search_options' => [
							'MailFailureLogger:mail_send_failed',
							'MailFailureLogger:mail_send_failures_skipped',
						],
					],
				],
			]
		);

		ob_start();
		?>
		<p>
			<strong>
				<?php
				printf(
					/* translators: 1: number of failed emails, 2: number of days. */
					esc_html( _n( '%1$d email failed to send in the last %2$d days.', '%1$d emails failed to send in the last %2$d days.', $stats['count'], 'simple-history' ) ),
					(int) $stats['count'],
					(int) Mail_Failure_Tracker::DAYS
				);
				?>
			</strong>
		</p>

		<?php
		if ( $stats['last_error'] !== '' ) {
			?>
			<p>
				<?php
				printf(
					/* translators: 1: time since the failure, e.g. "2 hours", 2: error message. */
					esc_html__( 'Last error, %1$s ago: "%2$s"', 'simple-history' ),
					esc_html( human_time_diff( (int) strtotime( $stats['last_at'] ) ) ),
					esc_html( $stats['last_error'] )
				);
				?>
			</p>
			<?php
		}
		?>

		<?php
		if ( self::failures_are_logged() ) {
			?>
			<p>
				<a href="<?php echo esc_url( $events_url ); ?>"><?php esc_html_e( 'View failed emails', 'simple-history' ); ?></a>
			</p>
			<?php
		}

		if ( Helpers::show_promo_boxes() && ! self::debug_and_monitor_logs_mail() ) {
			$debug_and_monitor_url = Helpers::get_tracking_url(
				'https://simple-history.com/add-ons/debug-and-monitor/',
				'premium_mailfailures_' . ( $location === 'email_settings' ? 'emailsettings' : 'sidebar' )
			);
			?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to the Debug & Monitor add-on. */
					esc_html__( '%s logs every email, so you can see which ones failed and who they were for.', 'simple-history' ),
					'<a href="' . esc_url( $debug_and_monitor_url ) . '" target="_blank">' . esc_html__( 'Debug & Monitor', 'simple-history' ) . '</a>'
				);
				?>
			</p>
			<?php
		}
		?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::DISMISS_ACTION ); ?>">
			<input type="hidden" name="last_at" value="<?php echo esc_attr( $stats['last_at'] ); ?>">
			<?php wp_nonce_field( self::DISMISS_ACTION ); ?>
			<button type="submit" class="button-link"><?php esc_html_e( 'Dismiss until the next failure', 'simple-history' ); ?></button>
		</form>
		<?php

		return (string) ob_get_clean();
	}
}
