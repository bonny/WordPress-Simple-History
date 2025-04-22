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
		if ( Helpers::is_premium_add_on_active() ) {
			return '';
		}

		$notification_bar_messages = [
			// [
			// 'message' => __( 'New in premium: Stats and Summaries that gives you insights into your site\'s activity', 'simple-history' ),
			// 'link' => 'https://simple-history.com/add-ons/premium/stats-and-summaries/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=premium_upsell&utm_content=notification_bar_stats',
			// 'read_more' => __( 'View stats and summaries', 'simple-history' ),
			// ],
			// [
			// 'message' => __( 'Simple History Premium: Extended log storage and thoughtful new features to explore', 'simple-history' ),
			// 'link' => 'https://simple-history.com/add-ons/premium/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=premium_upsell&utm_content=notification_bar',
			// 'read_more' => __( 'Explore premium features', 'simple-history' ),
			// ],
			// [
			// 'message' => __( 'Preserve your logs longer and gain helpful new tools with Simple History Premium', 'simple-history' ),
			// 'link' => 'https://simple-history.com/add-ons/premium/log-retention/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=premium_upsell&utm_content=notification_bar_retention',
			// 'read_more' => __( 'Learn about log retention', 'simple-history' ),
			// ],
			// [
			// 'message' => __( 'Did you know? Simple History Premium lets you set custom log retention periods', 'simple-history' ),
			// 'link' => 'https://simple-history.com/add-ons/premium/log-retention/?utm_source=wordpress_admin&utm_medium=Simple_History&utm_campaign=premium_upsell&utm_content=notification_bar_retention',
			// 'read_more' => __( 'See retention options', 'simple-history' ),
			// ],
			[
				'message' => __( 'New feature: Stats & Summaries.', 'simple-history' ),
				'link' => Menu_Manager::get_admin_url_by_slug( 'simple_history_stats_page' ),
				'read_more' => __( 'Let\'s try it', 'simple-history' ),
			],
		];

		$random_message = $notification_bar_messages[ array_rand( $notification_bar_messages ) ];

		ob_start();
		?>
		<aside class="sh-NotificationBar" role="complementary">
			<?php echo esc_html( $random_message['message'] ); ?>
			|
			<a href="<?php echo esc_url( $random_message['link'] ); ?>" class="sh-NotificationBar-link">
				<?php echo esc_html( $random_message['read_more'] ); ?>
			</a>
		</aside>
		<?php
		return ob_get_clean();
	}
}
