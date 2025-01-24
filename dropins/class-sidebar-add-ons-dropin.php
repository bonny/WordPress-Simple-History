<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Dropin that displays information about add-ons in the sidebar.
 */
class Sidebar_Add_Ons_Dropin extends Dropin {
	/**
	 * Add actions when dropin is loaded.
	 */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_sale_promo' ], 4 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_premium_promo' ], 5 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_debug_and_monitor_promo' ], 5 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_woocommerce_promo' ], 7 );
	}

	/**
	 * Output HTML with promo about sale.
	 */
	public function on_sidebar_html_sale_promo() {
		// Hide if Premium is installed.
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		// Hide if date is after January 31 2025.
		if ( time() > strtotime( '2025-01-31' ) ) {
			return;
		}

		?>
		<!-- 
		Insert promo:
		"Our New Year’s Sale is Here – 50% Off All Add-Ons 🙀".
		Link to: https://simple-history.com/add-ons/?utm_source=wpadmin&utm_content=new-year-sale-sidebar
		-->
		<div class="postbox">
			<div class="inside" style="background-color: var(--sh-color-pink-light); padding: 1rem; margin-top: 0;">
				<p style="margin: 0; font-size: 1rem; font-weight: bold;">
					<?php esc_html_e( 'Our New Year’s Sale is Here – 50% Off All Add-Ons 🙀', 'simple-history' ); ?>
				</p>

				<p>Hurry - this sale ends January 31.</p>
				
				<p>
					<a 
						class="sh-PremiumFeaturesPostbox-button"
						href="https://simple-history.com/add-ons/?utm_source=wpadmin&utm_content=new-year-sale-sidebar" 
						target="_blank"
						>
						<?php esc_html_e( 'Get Add-Ons Now', 'simple-history' ); ?>
					</a>
				</p>
			</div>

		</div>
		<?php
	}

	/**
	 * Output HTML if premium add-on is not installed.
	 */
	public function on_sidebar_html_premium_promo() {
		// Don't show if addon is already installed.
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		$premium_url = 'https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_content=premium-sidebar';

		?>
		<div class="postbox sh-PremiumFeaturesPostbox" style="--box-bg-color: var(--sh-color-cream);">

			<div class="inside">
				<p class="sh-PremiumFeaturesPostbox-preTitleFeaturesBadge"><em class="sh-PremiumFeatureBadge"><?php esc_html_e( 'Premium', 'simple-history' ); ?></em></p>

				<h3 class="sh-PremiumFeaturesPostbox-title">
					<?php echo esc_html__( 'Unlock more features with Simple History Premium!', 'simple-history' ); ?>
				</h3>

				<ul class="sh-PremiumFeaturesPostbox-featuresList">
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Export search results as CSV and JSON', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Customize log retention by setting the number of days to keep logs.', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Limit number of failed login attempts that are logged', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Control how to store IP Addresses – anonymized or not', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'View a map of where failed login attempts happened', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Control what messages that are logged to match your needs', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Remove banners like this one', 'simple-history' ); ?></li>
				</ul>

				<p>
					<a href="<?php echo esc_url( $premium_url ); ?>" target="_blank" class="sh-PremiumFeaturesPostbox-button">
						<?php esc_html_e( 'Upgrade to Premium', 'simple-history' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Output HTML if WooCommerce plugin is installed and active.
	 */
	public function on_sidebar_html_woocommerce_promo() {
		// Only show if WooCommerce is active.
		if ( ! Helpers::is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return;
		}

		// Don't show if addon is already installed.
		if ( Helpers::is_plugin_active( 'simple-history-woocommerce/index.php' ) ) {
			return;
		}

		$woocommerce_logger_url = 'https://simple-history.com/add-ons/woocommerce/?utm_source=wpadmin&utm_content=wc-logger-sidebar';

		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">

				<p class="sh-PremiumFeaturesPostbox-preTitleFeaturesBadge"><em class="sh-PremiumFeatureBadge"><?php esc_html_e( 'Add-on', 'simple-history' ); ?></em></p>

				<h3 class="sh-PremiumFeaturesPostbox-title">
					<?php esc_html_e( 'WooCommerce Logger', 'simple-history' ); ?>
				</h3>

				<p><?php esc_html_e( 'Log detailed information about many things that happen in your WooCommerce shop:', 'simple-history' ); ?></p>
		
				<ul class="sh-PremiumFeaturesPostbox-featuresList">
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'Order edits', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'Product modifications', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'Coupon changes', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'WooCommerce settings adjustments', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'Email templates updates', 'simple-history' ); ?></li>
				</ul>

				<p style="margin-bottom: .25rem;">
					<a href="<?php echo esc_url( $woocommerce_logger_url ); ?>" target="_blank" class="sh-PremiumFeaturesPostbox-button">
						<?php esc_html_e( 'Buy WooCommerce Logger', 'simple-history' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Output HTML with promo about Debug and Monitor add-on.
	 */
	public function on_sidebar_html_debug_and_monitor_promo() {
		// Don't show if addon is already installed.
		if ( Helpers::is_plugin_active( 'simple-history-debug-and-monitor/index.php' ) ) {
			return;
		}

		$debug_and_monitor_url = 'https://simple-history.com/add-ons/debug-and-monitor/?utm_source=wpadmin&utm_content=debug-monitor-sidebar';

		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">

				<p class="sh-PremiumFeaturesPostbox-preTitleFeaturesBadge"><em class="sh-PremiumFeatureBadge"><?php esc_html_e( 'Add-on', 'simple-history' ); ?></em></p>

				<h3 class="sh-PremiumFeaturesPostbox-title">
					<?php esc_html_e( 'Debug & Monitor', 'simple-history' ); ?>
				</h3>

				<p><?php esc_html_e( 'Keep track of WordPress activities with the Debug & Monitor add-on:', 'simple-history' ); ?></p>
		
				<ul class="sh-PremiumFeaturesPostbox-featuresList">
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'Email sending', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'HTTP API requests', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'REST API activity', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php esc_html_e( 'WP cron jobs', 'simple-history' ); ?></li>
				</ul>

				<p><?php esc_html_e( "Great for developers who need to debug issues and for site owners who want to understand what's happening behind the scenes.", 'simple-history' ); ?></p>

				<p style="margin-bottom: .25rem;">
					<a href="<?php echo esc_url( $debug_and_monitor_url ); ?>" target="_blank" class="sh-PremiumFeaturesPostbox-button">
						<?php esc_html_e( 'Buy Debug & Monitor', 'simple-history' ); ?>
					</a>
				</p>

				<p style="margin: 0; text-align: center; font-size: var(--sh-font-size-small); color: var(--sh-color-black-2);">
					<?php esc_html_e( 'Only $29 for 5 sites!', 'simple-history' ); ?>
				</p>
			</div>
		</div>
		<?php
	}
}
