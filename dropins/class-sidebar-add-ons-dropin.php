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
		// add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_general_message' ], 5 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_woocommerce' ], 5 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_premium_promo' ], 5 );
	}

	/**
	 * Output HTML if premium add-on is not installed.
	 */
	public function on_sidebar_html_premium_promo() {
		// Don't show if addon is already installed.
		if ( Helpers::is_plugin_active( 'simple-history-premium/index.php' ) ) {
			return;
		}

		$premium_url = 'https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_content=premium-sidebar';

		?>
		<div class="postbox sh-PremiumFeaturesPostbox">

			<h3 class="hndle">
				<?php esc_html_e( 'Get more out of Simple History', 'simple-history' ); ?>
				<em class="sh-PremiumFeatureBadge"><?php esc_html_e( 'Premium', 'simple-history' ); ?></em>
			</h3>

			<div class="inside">

				<p>Get Simple History Premium and unlock these features:</p>

				<ul class="sh-PremiumFeaturesPostbox-featuresList">
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Export search results as CSV and JSON</li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Option to set number of days to keep the log</li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Limit number of failed login attempts that are logged</li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Control how to store IP Addresses (anonymized or not)</li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Show a map of where a failed login attempt happened</li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Control what messages to log</li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Remove premium upgrade banners</li>
					<li class="sh-PremiumFeaturesPostbox-featuresList-item">Remove review and donate banners</li>
				</ul>
				
					<p>
					<a href="<?php echo esc_url( $premium_url ); ?>" class="sh-ExternalLink" target="_blank">
						<?php esc_html_e( 'View Premium add-on.', 'simple-history' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Output HTML if WooCommerce plugin is installed and active.
	 */
	public function on_sidebar_html_woocommerce() {
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
		<div class="postbox">

			<h3 class="hndle">
				<?php esc_html_e( 'Log important WooCommerce changes', 'simple-history' ); ?>
				<em class="sh-PageHeader-settingsLinkIcon-new"><?php esc_html_e( 'New', 'simple-history' ); ?></em>
			</h3>

			<div class="inside">
				<a href="<?php echo esc_url( $woocommerce_logger_url ); ?>" target="_blank">
					<img 
						width="774" 
						height="303" 
						class="" 
						src="<?php echo esc_attr( SIMPLE_HISTORY_DIR_URL ); ?>assets/images/woocommerce-logger-product-edit.png" 
						alt=""
						style="max-width: 100%;height: auto;"
					/>
				</a>

				<p>
					<?php esc_html_e( 'Log detailed information about many things that happens in your WooCommerce shop: Order edits, Product modifications, Coupon changes, WooCommerce settings adjustments, Email templates updates.', 'simple-history' ); ?>
				</p>
			
				<p>
					<a href="<?php echo esc_url( $woocommerce_logger_url ); ?>" class="sh-ExternalLink" target="_blank">
						<?php esc_html_e( 'View WooCommerce add-on.', 'simple-history' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Output HTML for a general add-ons box in the sidebar.
	 */
	public function on_sidebar_html_general_message() {
		?>
		<div class="postbox">

			<h3 class="hndle">
				<?php esc_html_e( 'Add-ons', 'simple-history' ); ?>
				<em class="sh-PageHeader-settingsLinkIcon-new"><?php esc_html_e( 'New', 'simple-history' ); ?></em>
			</h3>

			<div class="inside">
				<p>
					<?php esc_html_e( 'Now you can enhance and extend Simple History with add-ons.', 'simple-history' ); ?>
				</p>
			
				<?php
				/*
				<ul>
					<li>
						<strong><?php esc_html_e( 'Premium:', 'simple-history' ); ?></strong>
						<?php esc_html_e( 'Control number of days to keep the log, limit number of login attempts logged, store full IP-addresses, and a bonus JSON feed!', 'simple-history' ); ?>
					</li>

					<li>
						<strong><?php esc_html_e( 'WooCommerce Logger:', 'simple-history' ); ?></strong>
						<?php esc_html_e( 'Detailed WooCommerce activity logs. (Coming soon.)', 'simple-history' ); ?>
					</li>

					<li>
						<strong><?php esc_html_e( 'Developer Tools:', 'simple-history' ); ?></strong>
						<?php esc_html_e( 'Email, HTTP request, wp-cron job monitoring. (Coming soon.)', 'simple-history' ); ?>
					</li>
				</ul>
				*/
				?>

				<p>
					<a href="https://simple-history.com/add-ons/?utm_source=wpadmin&utm_content=addons-sidebar" class="sh-ExternalLink" target="_blank">
						<?php esc_html_e( 'View add-ons.', 'simple-history' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
