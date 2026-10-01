<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;
use Simple_History\Loggers\Mail_Failure_Logger;

/**
 * Tells admins when emails from their site failed to send.
 *
 * Shown in the sidebar and on the weekly email settings, only when the
 * experimental Mail_Failure_Logger logged failures in the last 30 days. The
 * free part is the count, the last error and a link to the events. A single
 * line under it mentions the Debug and Monitor add-on, which logs every email,
 * and is left out when promo boxes are hidden.
 *
 * Requires experimental features to be enabled, since the logger does.
 */
class Sidebar_Mail_Failures_Dropin extends Dropin {
	/**
	 * Called when dropin is loaded.
	 */
	public function loaded() {
		// Priority 5, above the promo cards: this is about a problem on the site.
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html' ], 5 );
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

		// The logger is only loaded when experimental features are on.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return '';
		}

		$stats = Mail_Failure_Logger::get_recent_failure_stats();

		if ( $stats['count'] === 0 ) {
			return '';
		}

		$events_url = Helpers::get_filtered_history_url(
			[
				'date'     => 'lastdays:' . Mail_Failure_Logger::STATS_DAYS,
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
					(int) Mail_Failure_Logger::STATS_DAYS
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
					esc_html( human_time_diff( strtotime( $stats['last_date'] . ' UTC' ) ) ),
					esc_html( $stats['last_error'] )
				);
				?>
			</p>
			<?php
		}
		?>

		<p>
			<a href="<?php echo esc_url( $events_url ); ?>"><?php esc_html_e( 'View failed emails', 'simple-history' ); ?></a>
		</p>

		<?php
		if ( Helpers::show_promo_boxes() ) {
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

		return (string) ob_get_clean();
	}
}
