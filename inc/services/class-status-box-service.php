<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Manager;

/**
 * Renders a feature discovery bar inside the page header.
 * Shows both active and inactive features to help users
 * discover settings and premium capabilities.
 */
class Status_Box_Service extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'simple_history/admin_page/title_group_end', [ $this, 'output_status_box' ] );
	}

	/**
	 * Output the status box HTML.
	 */
	public function output_status_box() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show when experimental features are enabled (testing phase).
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		$items = $this->get_status_items();

		/**
		 * Filter the feature discovery bar items.
		 *
		 * Each item is an array with keys:
		 * - 'text'     (string) Display text.
		 * - 'icon'     (string) Dashicon class name (e.g. 'dashicons-clock').
		 * - 'url'      (string, optional) Link URL. Omit for non-interactive items.
		 * - 'inactive' (bool, optional) True if the feature is off/discoverable.
		 *
		 * @since 5.16
		 *
		 * @param array $items Array of status items.
		 */
		$items = apply_filters( 'simple_history/header_status/items', $items );

		if ( empty( $items ) ) {
			return;
		}
		?>

		<div class="sh-HeaderStatus">
			<ul class="sh-HeaderStatus-zone">
				<?php foreach ( $items as $item ) { ?>
					<li class="sh-HeaderStatus-item">
						<?php $this->render_item( $item ); ?>
					</li>
				<?php } ?>
			</ul>
		</div>

		<?php
	}

	/**
	 * Render a single status item (dot + link or text).
	 *
	 * @param array $item Item with text, url, inactive, title keys.
	 */
	private function render_item( $item ) {
		$is_inactive = ! empty( $item['inactive'] );
		$item_class  = 'sh-HeaderStatus-item-inner' . ( $is_inactive ? ' sh-HeaderStatus-item-inner--inactive' : '' );
		$icon_class  = ! empty( $item['icon'] ) ? $item['icon'] : 'dashicons-marker';
		?>
		<?php if ( ! empty( $item['url'] ) ) { ?>
			<a href="<?php echo esc_url( $item['url'] ); ?>" class="<?php echo esc_attr( $item_class ); ?>"<?php echo ! empty( $item['title'] ) ? ' title="' . esc_attr( $item['title'] ) . '"' : ''; ?>>
				<span class="dashicons <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $item['text'] ); ?>
			</a>
		<?php } else { ?>
			<span class="<?php echo esc_attr( $item_class ); ?>">
				<span class="dashicons <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $item['text'] ); ?>
			</span>
		<?php } ?>
		<?php
	}

	/**
	 * Get the status items to display.
	 *
	 * @return array<array{text: string, url?: string, inactive?: bool}>
	 */
	private function get_status_items() {
		$items      = [];
		$is_premium = Helpers::is_premium_add_on_active();

		$settings_url       = Helpers::get_settings_page_url();
		$general_section    = $settings_url . '#simple_history_general_section';
		$email_section      = add_query_arg(
			[
				'selected-tab'     => 'general_settings_subtab_general',
				'selected-sub-tab' => 'general_settings_subtab_email_reports',
			],
			$settings_url
		);
		$alerts_tab_url     = add_query_arg( 'selected-tab', 'general_settings_subtab_alerts', $settings_url );
		$forwarding_tab_url = add_query_arg( 'selected-tab', 'general_settings_subtab_log_forwarding', $settings_url );
		$upsell_url         = Menu_Manager::get_admin_url_by_slug( 'simple_history_promo_upsell' );

		// Retention period — always first.
		$days    = Helpers::get_clear_history_interval();
		$items[] = [
			'text'  => sprintf(
				/* translators: %d: number of days events are kept */
				_n(
					'History kept: %d day',
					'History kept: %d days',
					$days,
					'simple-history'
				),
				$days
			),
			'icon'  => 'dashicons-clock',
			'url'   => $general_section,
			'title' => __( 'Go to retention settings', 'simple-history' ),
		];

		// Email reports.
		$email_title = __( 'Go to Email reports settings', 'simple-history' );
		if ( get_option( 'simple_history_email_report_enabled' ) ) {
			$items[] = [
				'text'  => __( 'Email reports: on', 'simple-history' ),
				'icon'  => 'dashicons-email-alt',
				'url'   => $email_section,
				'title' => $email_title,
			];
		} else {
			$items[] = [
				'text'     => __( 'Email reports: off', 'simple-history' ),
				'icon'     => 'dashicons-email-alt',
				'url'      => $email_section,
				'title'    => $email_title,
				'inactive' => true,
			];
		}

		// Alerts.
		// Alerts require premium to function — ignore saved rules when premium is inactive.
		$alerts_title = __( 'Go to Alerts settings', 'simple-history' );
		if ( $is_premium ) {
			$alert_rules    = get_option( 'simple_history_alert_rules', [] );
			$enabled_alerts = is_array( $alert_rules ) ? count( array_filter( $alert_rules, fn( $rule ) => ! empty( $rule['enabled'] ) ) ) : 0;
			if ( $enabled_alerts > 0 ) {
				$items[] = [
					'text'  => sprintf(
						/* translators: %d: number of active alert rules */
						_n( 'Alerts: %d active', 'Alerts: %d active', $enabled_alerts, 'simple-history' ),
						$enabled_alerts
					),
					'icon'  => 'dashicons-bell',
					'url'   => $alerts_tab_url,
					'title' => $alerts_title,
				];
			} else {
				$items[] = [
					'text'     => __( 'Alerts: not set up', 'simple-history' ),
					'icon'     => 'dashicons-bell',
					'url'      => $alerts_tab_url,
					'title'    => $alerts_title,
					'inactive' => true,
				];
			}
		} else {
			$items[] = [
				'text'     => __( 'Alerts: Premium only', 'simple-history' ),
				'icon'     => 'dashicons-bell',
				'url'      => $upsell_url,
				'title'    => __( 'Learn about Alerts in Premium', 'simple-history' ),
				'inactive' => true,
			];
		}

		// Log forwarding — file channel is a core feature, always available.
		$forwarding_title     = __( 'Go to Log Forwarding settings', 'simple-history' );
		$file_channel         = get_option( 'simple_history_channel_file', [] );
		$is_forwarding_active = is_array( $file_channel ) && ! empty( $file_channel['enabled'] );
		if ( $is_forwarding_active ) {
			$items[] = [
				'text'  => __( 'Log forwarding: on', 'simple-history' ),
				'icon'  => 'dashicons-migrate',
				'url'   => $forwarding_tab_url,
				'title' => $forwarding_title,
			];
		} else {
			$items[] = [
				'text'     => __( 'Log forwarding: off', 'simple-history' ),
				'icon'     => 'dashicons-migrate',
				'url'      => $forwarding_tab_url,
				'title'    => $forwarding_title,
				'inactive' => true,
			];
		}

		return $items;
	}
}
