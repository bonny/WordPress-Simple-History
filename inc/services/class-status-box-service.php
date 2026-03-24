<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Renders a compact status bar inside the page header.
 * Shows active configuration as non-interactive text with a Settings link.
 */
class Status_Box_Service extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'simple_history/admin_page/header_end', [ $this, 'output_status_box' ] );
	}

	/**
	 * Output the status box HTML.
	 */
	public function output_status_box() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$items = $this->get_status_items();

		if ( empty( $items ) ) {
			return;
		}
		?>
		<div class="sh-HeaderStatus">
			<ul class="sh-HeaderStatus-zone">
				<?php foreach ( $items as $item ) { ?>
					<li class="sh-HeaderStatus-item">
						<span class="sh-HeaderStatus-itemText"><?php echo esc_html( $item['text'] ); ?></span>
					</li>
				<?php } ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Get the status items to display.
	 *
	 * @return array<array{text: string}>
	 */
	private function get_status_items() {
		$items = [];

		// Retention period — always shown.
		$days    = Helpers::get_clear_history_interval();
		$items[] = [
			'text' => sprintf(
				/* translators: %d: number of days events are kept */
				_n(
					'Logs kept %d day',
					'Logs kept %d days',
					$days,
					'simple-history'
				),
				$days
			),
		];

		// Alerts — show count of enabled alert rules.
		$alert_rules    = get_option( 'simple_history_alert_rules', [] );
		$enabled_alerts = is_array( $alert_rules ) ? count( array_filter( $alert_rules, fn( $rule ) => ! empty( $rule['enabled'] ) ) ) : 0;
		if ( $enabled_alerts > 0 ) {
			$items[] = [
				'text' => sprintf(
					/* translators: %d: number of active alert rules */
					_n( '%d alert active', '%d alerts active', $enabled_alerts, 'simple-history' ),
					$enabled_alerts
				),
			];
		}

		// Detective mode — only when active.
		if ( Helpers::detective_mode_is_enabled() ) {
			$items[] = [
				'text' => __( 'Detective mode', 'simple-history' ),
			];
		}

		// RSS/JSON feeds — only when active.
		if ( get_option( 'simple_history_enable_rss_feed' ) ) {
			$items[] = [
				'text' => __( 'RSS feed', 'simple-history' ),
			];
		}

		// Log forwarding (file channel) — only when active.
		$file_channel = get_option( 'simple_history_channel_file', [] );
		if ( is_array( $file_channel ) && ! empty( $file_channel['enabled'] ) ) {
			$items[] = [
				'text' => __( 'Log forwarding', 'simple-history' ),
			];
		}

		// Stealth mode — only when active.
		if ( Stealth_Mode::is_stealth_mode_enabled() ) {
			$items[] = [
				'text' => __( 'Stealth mode', 'simple-history' ),
			];
		}

		// Email digests — only when active.
		if ( get_option( 'simple_history_email_report_enabled' ) ) {
			$items[] = [
				'text' => __( 'Email digests', 'simple-history' ),
			];
		}

		return $items;
	}
}
