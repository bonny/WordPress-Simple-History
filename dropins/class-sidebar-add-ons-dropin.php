<?php

namespace Simple_History\Dropins;

/**
 * Dropin that displays information about add-ons in the sidebar.
 */
class Sidebar_Add_Ons_Dropin extends Dropin {
	/**
	 * Add actions when dropin is loaded.
	 */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html_woocommerce' ], 5 );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html' ], 5 );
	}

	/**
	 * Output HTML if WooCommerce plugin is installed and active.
	 */
	public function on_sidebar_html_woocommerce() {
		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			return;
		}

		$woocommerce_logger_url = 'https://simple-history.com/add-ons/woocommerce/?utm_source=wpadmin';

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
	public function on_sidebar_html() {
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
						<strong><?php esc_html_e( 'Extended Settings:', 'simple-history' ); ?></strong>
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
					<a href="https://simple-history.com/add-ons/?utm_source=wpadmin" class="sh-ExternalLink" target="_blank">
						<?php esc_html_e( 'View add-ons.', 'simple-history' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
