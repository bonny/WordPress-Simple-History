<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;

/**
 * Settings page teaser for Alerts feature.
 *
 * Shows a promotional page for the premium Alerts feature when the premium add-on is not active.
 * When premium is active, this teaser is replaced by the real Alerts settings page.
 *
 * @since 5.0.0
 */
class Alerts_Settings_Page_Teaser extends Service {
	/**
	 * Menu slug for the alerts settings page.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'general_settings_subtab_alerts';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Skip if premium is handling alerts.
		// Premium add-on will add this filter to indicate it's taking over.
		if ( $this->is_premium_handling_alerts() ) {
			return;
		}

		// Add menu page.
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
	}

	/**
	 * Check if the premium add-on is handling alerts.
	 *
	 * Premium add-on will add a filter to indicate it's taking over the alerts settings page.
	 *
	 * @return bool True if premium is handling alerts, false otherwise.
	 */
	private function is_premium_handling_alerts() {
		/**
		 * Filter to indicate if premium is handling the alerts settings page.
		 *
		 * Premium add-on should return true to prevent the teaser from being shown.
		 *
		 * @since 5.0.0
		 *
		 * @param bool $is_handling Whether premium is handling alerts. Default false.
		 */
		return apply_filters( 'simple_history/alerts/is_premium_handling', false );
	}

	/**
	 * Add alerts settings tab as a subtab to main settings tab.
	 */
	public function add_settings_menu_tab() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exist (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		// Build menu title with Premium badge.
		$menu_title = __( 'Alerts', 'simple-history' )
			. ' <span class="sh-Badge sh-Badge--premium">' . esc_html__( 'Premium', 'simple-history' ) . '</span>';

		( new Menu_Page() )
			->set_page_title( __( 'Alerts', 'simple-history' ) )
			->set_menu_title( $menu_title )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'render_settings_page' ] )
			->set_order( 35 ) // After general (default) but before log forwarding (40).
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Render the alerts settings page teaser.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap sh-Page-content">
			<?php $this->render_teaser_content(); ?>
		</div>
		<?php
	}

	/**
	 * Render the teaser content.
	 */
	private function render_teaser_content() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'alerts_settings_teaser' );
		?>
		<div class="sh-AlertsTeaser">
			<div class="sh-AlertsTeaser-header">
				<span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span>
				<h2><?php esc_html_e( 'Alerts & Notifications', 'simple-history' ); ?></h2>
				<p class="sh-AlertsTeaser-subtitle">
					<?php esc_html_e( 'Get instant notifications when important events happen on your site.', 'simple-history' ); ?>
				</p>
			</div>

			<div class="sh-AlertsTeaser-comparison">
				<p>
					<?php esc_html_e( 'Unlike Log Forwarding (which streams all events), Alerts notify you only when specific things happen:', 'simple-history' ); ?>
				</p>
			</div>

			<div class="sh-AlertsTeaser-destinations">
				<h3><?php esc_html_e( 'Alert Destinations', 'simple-history' ); ?></h3>
				<div class="sh-AlertsTeaser-destinationGrid">
					<?php
					$destinations = [
						[
							'icon'        => 'email',
							'name'        => __( 'Email', 'simple-history' ),
							'description' => __( 'Get alerts in your inbox', 'simple-history' ),
						],
						[
							'icon'        => 'format-chat',
							'name'        => __( 'Slack', 'simple-history' ),
							'description' => __( 'Post to your team channels', 'simple-history' ),
						],
						[
							'icon'        => 'games',
							'name'        => __( 'Discord', 'simple-history' ),
							'description' => __( 'Notify your Discord server', 'simple-history' ),
						],
						[
							'icon'        => 'admin-site-alt3',
							'name'        => __( 'Telegram', 'simple-history' ),
							'description' => __( 'Send via Telegram bot', 'simple-history' ),
						],
					];

					foreach ( $destinations as $destination ) {
						?>
						<div class="sh-AlertsTeaser-destination">
							<span class="dashicons dashicons-<?php echo esc_attr( $destination['icon'] ); ?>"></span>
							<strong><?php echo esc_html( $destination['name'] ); ?></strong>
							<span><?php echo esc_html( $destination['description'] ); ?></span>
						</div>
						<?php
					}
					?>
				</div>
			</div>

			<div class="sh-AlertsTeaser-presets">
				<h3><?php esc_html_e( 'Pre-built Alert Presets', 'simple-history' ); ?></h3>
				<p><?php esc_html_e( 'Get started in seconds with ready-made alert configurations:', 'simple-history' ); ?></p>
				<div class="sh-AlertsTeaser-presetList">
					<?php
					$presets = [
						[
							'icon'        => 'shield',
							'name'        => __( 'Security Alerts', 'simple-history' ),
							'description' => __( 'Failed logins, user role changes, new admin users', 'simple-history' ),
						],
						[
							'icon'        => 'edit',
							'name'        => __( 'Content Changes', 'simple-history' ),
							'description' => __( 'Posts published, pages deleted, media uploads', 'simple-history' ),
						],
						[
							'icon'        => 'admin-plugins',
							'name'        => __( 'Plugin & Theme Activity', 'simple-history' ),
							'description' => __( 'Installs, updates, activations, deletions', 'simple-history' ),
						],
					];

					foreach ( $presets as $preset ) {
						?>
						<div class="sh-AlertsTeaser-preset">
							<span class="dashicons dashicons-<?php echo esc_attr( $preset['icon'] ); ?>"></span>
							<div class="sh-AlertsTeaser-presetContent">
								<strong><?php echo esc_html( $preset['name'] ); ?></strong>
								<span><?php echo esc_html( $preset['description'] ); ?></span>
							</div>
						</div>
						<?php
					}
					?>
				</div>
			</div>

			<div class="sh-AlertsTeaser-customRules">
				<h3><?php esc_html_e( 'Custom Alert Rules', 'simple-history' ); ?></h3>
				<p><?php esc_html_e( 'Need more control? Create custom rules with specific conditions for power users.', 'simple-history' ); ?></p>
			</div>

			<div class="sh-AlertsTeaser-mockUI" inert>
				<div class="sh-AlertsTeaser-mockCard">
					<div class="sh-AlertsTeaser-mockCardHeader">
						<span class="dashicons dashicons-shield"></span>
						<span><?php esc_html_e( 'Security Alerts', 'simple-history' ); ?></span>
						<span class="sh-AlertsTeaser-mockToggle"></span>
					</div>
					<div class="sh-AlertsTeaser-mockCardBody">
						<p><?php esc_html_e( 'Failed logins, user role changes, new admin users', 'simple-history' ); ?></p>
						<div class="sh-AlertsTeaser-mockDestinations">
							<span class="sh-AlertsTeaser-mockChip"><?php esc_html_e( 'Slack', 'simple-history' ); ?></span>
							<span class="sh-AlertsTeaser-mockChip"><?php esc_html_e( 'Email', 'simple-history' ); ?></span>
						</div>
					</div>
				</div>
				<div class="sh-AlertsTeaser-mockCard">
					<div class="sh-AlertsTeaser-mockCardHeader">
						<span class="dashicons dashicons-admin-plugins"></span>
						<span><?php esc_html_e( 'Plugin & Theme Activity', 'simple-history' ); ?></span>
						<span class="sh-AlertsTeaser-mockToggle"></span>
					</div>
					<div class="sh-AlertsTeaser-mockCardBody">
						<p><?php esc_html_e( 'Installs, updates, activations, deletions', 'simple-history' ); ?></p>
						<div class="sh-AlertsTeaser-mockDestinations">
							<span class="sh-AlertsTeaser-mockChip"><?php esc_html_e( 'Email', 'simple-history' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<div class="sh-AlertsTeaser-cta">
				<a href="<?php echo esc_url( $premium_url ); ?>" class="button button-primary button-hero">
					<?php esc_html_e( 'Get Simple History Premium', 'simple-history' ); ?>
				</a>
				<p class="sh-AlertsTeaser-ctaNote">
					<?php esc_html_e( 'Unlock Alerts, Extended Log Retention, WooCommerce Integration, and more.', 'simple-history' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
