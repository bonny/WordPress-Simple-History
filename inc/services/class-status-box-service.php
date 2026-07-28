<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Renders a feature discovery bar inside the page header.
 * Surfaces core and premium features so users can discover
 * settings they may not know exist. Hides itself once the
 * discoverable features have been configured.
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

		// This is a feature-discovery aid, not a permanent status readout.
		// Once the discoverable features are configured it has done its job,
		// so hide it rather than let it become permanent header chrome.
		if ( $this->all_discoverable_features_configured() ) {
			return;
		}

		$items = $this->get_status_items();

		/**
		 * Filter the feature discovery bar items.
		 *
		 * Each item is an array with keys:
		 * - 'id'     (string) Stable identifier, e.g. 'email', 'alerts'. Lets consumers target a specific item.
		 * - 'text'   (string) Display text.
		 * - 'icon'   (string) Dashicon class name (e.g. 'dashicons-clock').
		 * - 'url'    (string, optional) Link URL. Omit for non-interactive items.
		 * - 'title'  (string, optional) Tooltip / title attribute.
		 * - 'badge'  (string, optional) Badge label rendered after the text (e.g. 'Premium').
		 * - 'target' (string, optional) Link target, e.g. '_blank' for external links.
		 *
		 * Consumers can match on 'id' to replace or remove an item — the premium
		 * add-on replaces the 'alerts' upsell item with the real Alerts status.
		 *
		 * @since 5.16
		 *
		 * @param array $items Array of status items.
		 */
		$items = apply_filters( 'simple_history/header_status/items', $items );

		// Drop items with nothing to show before any markup is emitted. The check
		// in render_item() cannot do this on its own — by the time it runs, the
		// <li> wrapper is already on the page, leaving an empty styled bullet.
		// Consumers of the filter above do return such items: the premium add-on
		// replaces the alerts entry wholesale.
		$items = array_filter(
			$items,
			function ( $item ) {
				return ! empty( $item['text'] );
			}
		);

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
	 * Whether every discoverable feature has been configured.
	 *
	 * When true the discovery bar is hidden — it has done its job.
	 * Alerts is a premium feature, so the add-on reports whether it is
	 * configured via a filter (defaulting to true so it never keeps the bar
	 * alive on the free version, where Alerts can't be configured).
	 *
	 * @return bool
	 */
	private function all_discoverable_features_configured() {
		$email_on = (bool) get_option( 'simple_history_email_report_enabled' );

		$forwarding_on = $this->get_active_forwarding_channels_count() > 0;

		/**
		 * Whether the premium Alerts feature is configured.
		 *
		 * Alerts lives in the premium add-on, so the add-on answers this.
		 * Defaults to true so alerts never keeps the discovery bar visible on
		 * installs where it isn't available (e.g. the free version).
		 *
		 * @since 5.30.0
		 *
		 * @param bool $configured Whether alerts are configured.
		 */
		$alerts_ok = (bool) apply_filters( 'simple_history/header_status/alerts_configured', true );

		return $email_on && $forwarding_on && $alerts_ok;
	}

	/**
	 * Number of enabled log-forwarding channels.
	 *
	 * Counts every registered channel that is enabled — the core file channel
	 * plus any premium channels (Syslog, webhooks, external databases, etc.) —
	 * via the channels API, so each channel owns its own "enabled" state.
	 *
	 * @return int
	 */
	private function get_active_forwarding_channels_count() {
		$channels_service = $this->simple_history->get_service( Channels_Service::class );

		if ( ! $channels_service instanceof Channels_Service ) {
			return 0;
		}

		$manager = $channels_service->get_channels_manager();

		if ( ! $manager ) {
			return 0;
		}

		$count = 0;

		foreach ( $manager->get_channels() as $channel ) {
			if ( ! $channel->is_enabled() ) {
				continue;
			}

			++$count;
		}

		return $count;
	}

	/**
	 * Render a single status item (icon + text, optional badge, as link or plain text).
	 *
	 * @param array $item Item with id, text, icon, url, title, badge, target keys.
	 */
	private function render_item( $item ) {
		$text = $item['text'] ?? '';

		if ( $text === '' ) {
			return;
		}

		$icon_class = ! empty( $item['icon'] ) ? $item['icon'] : 'dashicons-marker';
		$item_class = 'sh-HeaderStatus-item-inner';

		$title_attr = ! empty( $item['title'] ) ? ' title="' . esc_attr( $item['title'] ) . '"' : '';

		$target_attr = '';

		if ( ! empty( $item['target'] ) ) {
			$target_attr = ' target="' . esc_attr( $item['target'] ) . '" rel="noopener noreferrer"';
		}

		$badge_html = '';

		if ( ! empty( $item['badge'] ) ) {
			$badge_html = ' <span class="sh-Badge sh-Badge--premium">' . esc_html( $item['badge'] ) . '</span>';
		}
		?>
		<?php if ( ! empty( $item['url'] ) ) { ?>
			<a href="<?php echo esc_url( $item['url'] ); ?>" class="<?php echo esc_attr( $item_class ); ?>"<?php echo $title_attr . $target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<span class="dashicons <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $text ); ?><?php echo $badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</a>
		<?php } else { ?>
			<span class="<?php echo esc_attr( $item_class ); ?>">
				<span class="dashicons <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $text ); ?><?php echo $badge_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		<?php } ?>
		<?php
	}

	/**
	 * Get the status items to display.
	 *
	 * @return array<array{id: string, text: string, icon: string, url?: string, title?: string, badge?: string, target?: string}>
	 */
	private function get_status_items() {
		$items = [];

		$settings_url       = Helpers::get_settings_page_url();
		$general_section    = $settings_url . '#simple_history_general_section';
		$email_section      = Helpers::get_settings_page_sub_tab_url( 'general_settings_subtab_email_reports' );
		$forwarding_tab_url = Helpers::get_settings_page_sub_tab_url( 'general_settings_subtab_log_forwarding' );

		// Retention period — always first, always shown as context.
		$days    = Helpers::get_clear_history_interval();
		$items[] = [
			'id'    => 'retention',
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
			'title' => __( 'Change how long events are stored', 'simple-history' ),
		];

		// Email reports (free). Factual when on, benefit-led invite when off.
		if ( get_option( 'simple_history_email_report_enabled' ) ) {
			$items[] = [
				'id'    => 'email',
				'text'  => __( 'Email reports: weekly', 'simple-history' ),
				'icon'  => 'dashicons-email-alt',
				'url'   => $email_section,
				'title' => __( 'Change what the summary includes and who receives it', 'simple-history' ),
			];
		} else {
			$items[] = [
				'id'    => 'email',
				'text'  => __( 'Get weekly email reports', 'simple-history' ),
				'icon'  => 'dashicons-email-alt',
				'url'   => $email_section,
				'title' => __( 'A short weekly summary of site activity, straight to your inbox', 'simple-history' ),
			];
		}

		// Alerts is a premium feature — core shows an upsell teaser linking to
		// the in-plugin Alerts settings page, which carries the premium promo.
		// When the premium add-on is active it replaces this item (matched by
		// id) via the simple_history/header_status/items filter.
		$items[] = [
			'id'    => 'alerts',
			'text'  => __( 'Alerts', 'simple-history' ),
			'icon'  => 'dashicons-bell',
			'url'   => Helpers::get_settings_page_sub_tab_url( 'general_settings_subtab_alerts' ),
			'title' => __( 'Get notified of key changes — plugin deactivations, role changes, failed logins', 'simple-history' ),
			'badge' => __( 'Premium', 'simple-history' ),
		];

		// Log forwarding — the core file channel is free; premium adds more
		// channels. Count all enabled channels so the label stays accurate when
		// several are active. Factual when on, benefit-led invite when off.
		$active_channels = $this->get_active_forwarding_channels_count();

		if ( $active_channels > 0 ) {
			$items[] = [
				'id'    => 'forwarding',
				'text'  => sprintf(
					/* translators: %d: number of active log-forwarding channels */
					_n( 'Log forwarding: %d active', 'Log forwarding: %d active', $active_channels, 'simple-history' ),
					$active_channels
				),
				'icon'  => 'dashicons-migrate',
				'url'   => $forwarding_tab_url,
				'title' => __( 'Change where copies of your log are sent', 'simple-history' ),
			];
		} else {
			$items[] = [
				'id'    => 'forwarding',
				'text'  => __( 'Forward logs to a file', 'simple-history' ),
				'icon'  => 'dashicons-migrate',
				'url'   => $forwarding_tab_url,
				'title' => __( 'Keep a copy of your activity log in a file', 'simple-history' ),
			];
		}

		return $items;
	}
}
