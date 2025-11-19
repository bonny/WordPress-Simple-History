<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Manager;

/**
 * Handles the notification bar in the admin interface.
 */
class Notification_Bar extends Service {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'simple_history/admin_page/after_header', array( $this, 'maybe_output_notification_bar' ) );
	}

	/**
	 * Output the notification bar if conditions are met.
	 */
	public function maybe_output_notification_bar() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->get_notification_bar_html();
	}

	/**
	 * Get the notification bar HTML.
	 *
	 * @return string HTML for the notification bar.
	 */
	private function get_notification_bar_html() {
		if ( ! Helpers::show_promo_boxes() ) {
			return '';
		}

		$stats_page_url = Menu_Manager::get_admin_url_by_slug( 'simple_history_stats_page' );

		$notification_bar_messages = [
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			// [
			// 'message' => __( 'New in premium: Stats and Summaries that gives you insights into your site\'s activity', 'simple-history' ),
			// 'link' => Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/stats-and-summaries/', 'premium_notificationbar_stats' ),
			// 'read_more' => __( 'View stats and summaries', 'simple-history' ),
			// ],
			// [
			// 'message' => __( 'Simple History Premium: Extended log storage and thoughtful new features to explore', 'simple-history' ),
			// 'link' => Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_notificationbar_general' ),
			// 'link_is_external' => true,
			// 'read_more' => __( 'Explore premium features', 'simple-history' ),
			// 'message_available' => true,
			// ],
			// [
			// 'message' => __( 'Preserve your logs longer and gain helpful new tools with Simple History Premium', 'simple-history' ),
			// 'link' => Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_notificationbar_retention' ),
			// 'link_is_external' => true,
			// 'read_more' => __( 'Learn about log retention', 'simple-history' ),
			// 'message_available' => true,
			// ],
			// [
			// 'message' => __( 'Did you know? Simple History Premium lets you set custom log retention periods', 'simple-history' ),
			// 'link' => Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_notificationbar_retention' ),
			// 'read_more' => __( 'See retention options', 'simple-history' ),
			// ],
			// [
			// 'message' => __( 'New feature: Stats & Summaries.', 'simple-history' ),
			// 'link' => $stats_page_url,
			// 'read_more' => __( 'Let\'s try it', 'simple-history' ),
			// 'message_available' => ! empty( $stats_page_url ),
			// ],
		];

		// Filter out messages that are not available.
		$notification_bar_messages = array_filter(
			$notification_bar_messages,
			function ( $message ) {
				return $message['message_available'];
			}
		);

		// Bail if no messages are available for user.
		/** @phpstan-ignore empty.variable */
		if ( empty( $notification_bar_messages ) ) {
			return '';
		}

		$random_message = $notification_bar_messages[ array_rand( $notification_bar_messages ) ];

		ob_start();
		?>
		<aside class="sh-NotificationBar" role="complementary">
			<?php echo esc_html( $random_message['message'] ); ?>
			<?php
			// Only show link if it has a value (can be empty if user has no access or similar).
			if ( ! empty( $random_message['link'] ) ) {
				$link_target = isset( $random_message['link_is_external'] ) && $random_message['link_is_external'] ? '_blank' : '';
				?>
				|
				<a href="<?php echo esc_url( $random_message['link'] ); ?>" class="sh-NotificationBar-link" target="<?php echo esc_attr( $link_target ); ?>">
					<?php echo esc_html( $random_message['read_more'] ); ?>
				</a>
				<?php
			}
			?>
		</aside>
		<?php
		return ob_get_clean();
	}
}
