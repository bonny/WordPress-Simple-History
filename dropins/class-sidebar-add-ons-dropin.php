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
		// Black Week sale at priority 1 to show first.
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_sale_promo' ], 1 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_premium_promo' ], 5 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_debug_and_monitor_promo' ], 5 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_woocommerce_promo' ], 7 );
	}

	/**
	 * Output HTML with promo about sale.
	 */
	public function on_sidebar_html_sale_promo() {
		// Hide if Premium is installed.
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		// If true then always show promotion, regardless of date.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$preview_promotion = isset( $_GET['sh_preview_promotion'] );

		// Get current date/time in the site's timezone.
		$now = current_datetime();

		// Define promotion start and end dates in the site's timezone.
		$start_date = new \DateTimeImmutable( '2025-11-23 00:00:00', wp_timezone() );
		$end_date   = new \DateTimeImmutable( '2025-12-01 23:59:59', wp_timezone() );

		// Hide if before start date.
		if ( ! $preview_promotion && $now < $start_date ) {
			return;
		}

		// Hide if after end date.
		if ( ! $preview_promotion && $now > $end_date ) {
			return;
		}

		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">
				<img style="width: 80px; max-width: 100%; margin: 0 auto; display: block; margin-bottom: 1rem;" src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'assets/images/black-week-2025.svg' ); ?>" alt="" />

				<p style="margin: 0; font-size: 1rem; font-weight: bold;">
					<?php esc_html_e( 'Black Week Sale:', 'simple-history' ); ?>
					<br />
					<?php esc_html_e( 'Premium Features at 30% Off', 'simple-history' ); ?>
				</p>

				<p>Don't lose important history after 60 days. 
					<strong>Simple History Premium</strong> keeps your logs as long as you need, plus adds exports, custom events, and more. 
				</p>
				<p>
					Save 30% with code <strong>BLACKWEEK30</strong> (ends December 1 2025.)
				</p>

				<p>
					<a
						class="sh-PremiumFeaturesPostbox-button"
						href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_blackweek_sidebar' ) ); ?>"
						target="_blank"
						>
						<?php esc_html_e( 'Get Premium Now', 'simple-history' ); ?>
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
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_premium_features_postbox_html();
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

		// Hide if Premium is installed, because one feature of premium is hiding promos.
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_woocommerce_logger_features_postbox_html();
	}

	/**
	 * Output HTML with promo about Debug and Monitor add-on.
	 */
	public function on_sidebar_html_debug_and_monitor_promo() {
		// Don't show if addon is already installed.
		if ( Helpers::is_plugin_active( 'simple-history-debug-and-monitor/index.php' ) ) {
			return;
		}

		// Hide if Premium is installed, because one feature of premium is hiding promos.
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_debug_and_monitor_features_postbox_html();
	}

	/**
	 * Get HTML for Debug and Monitor add-on promo.
	 *
	 * @return string HTML
	 */
	public static function get_debug_and_monitor_features_postbox_html() {
		// Hide if Premium is installed, because one feature of premium is hiding promos.
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		$debug_and_monitor_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/debug-and-monitor/', 'premium_debug_sidebar' );

		ob_start();
		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">

				<p class="sh-PremiumFeaturesPostbox-preTitleFeaturesBadge"><span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Add-on', 'simple-history' ); ?></span></p>

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

		return ob_get_clean();
	}

	/**
	 * Get HTML for Premium add-on promo.
	 *
	 * @return string HTML
	 */
	public static function get_premium_features_postbox_html() {
		$premium_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_dashboard_sidebar' );

		ob_start();
		?>
		<div class="postbox sh-PremiumFeaturesPostbox" style="--box-bg-color: var(--sh-color-cream);">

			<div class="inside">
				<p class="sh-PremiumFeaturesPostbox-preTitleFeaturesBadge"><span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Premium', 'simple-history' ); ?></span></p>

				<h3 class="sh-PremiumFeaturesPostbox-title">
					<?php echo esc_html__( 'Unlock more features with Simple History Premium!', 'simple-history' ); ?>
				</h3>

				<ul class="sh-PremiumFeaturesPostbox-featuresList">
					<!-- Sticky events -->
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">
						<?php esc_html_e( 'Sticky events', 'simple-history' ); ?>

						<span class="sh-Badge sh-Badge--premium" style="background-color: var(--sh-color-yellow);"><strong><?php esc_html_e( 'New!', 'simple-history' ); ?></strong></span>

						<em class="sh-PremiumFeaturesPostbox-featuresList-item-discrete">
							<?php esc_html_e( 'Pin important log entries to the top of the log for easy access.', 'simple-history' ); ?>
						</em>
					</li>

					<!-- Custom events -->
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">
						<?php esc_html_e( 'Add custom events manually', 'simple-history' ); ?>

						<em class="sh-PremiumFeaturesPostbox-featuresList-item-discrete">
							<?php esc_html_e( "Document important changes by creating custom log entries for team actions, content updates, or system changes that aren't automatically tracked.", 'simple-history' ); ?>
						</em>
					</li>

					<li class="sh-PremiumFeaturesPostbox-featuresList-item">
						<?php esc_html_e( 'Stealth Mode', 'simple-history' ); ?>

						<em class="sh-PremiumFeaturesPostbox-featuresList-item-discrete">
							<?php esc_html_e( 'Allow only specified users to see Simple History in the WordPress admin.', 'simple-history' ); ?>
						</em>
					</li>

					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Export search results as CSV and JSON', 'simple-history' ); ?></li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item"><?php echo esc_html__( 'Customize log retention by setting the number of days to keep logs', 'simple-history' ); ?></li>
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

		return ob_get_clean();
	}

	/**
	 * Get HTML for WooCommerce Logger add-on promo.
	 *
	 * @return string HTML
	 */
	public static function get_woocommerce_logger_features_postbox_html() {
		$woocommerce_logger_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/woocommerce/', 'premium_woocommerce_sidebar' );

		ob_start();
		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">

				<p class="sh-PremiumFeaturesPostbox-preTitleFeaturesBadge"><span class="sh-Badge sh-Badge--premium"><?php esc_html_e( 'Add-on', 'simple-history' ); ?></span></p>

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

		return ob_get_clean();
	}
}
