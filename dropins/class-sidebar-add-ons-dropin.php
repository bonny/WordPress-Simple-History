<?php

namespace Simple_History\Dropins;

use Simple_History\Simple_History;

/**
 * Dropin that displays information about add-ons in the sidebar.
 */
class Sidebar_Add_Ons_Dropin extends Dropin {
	/**
	 * Add actions when dropin is loaded.
	 */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'on_sidebar_html' ], 5 );
	}

	/**
	 * Output HTML.
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
