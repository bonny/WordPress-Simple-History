<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;

/**
 * Outputs the sidebar on the Network Admin Simple History page.
 *
 * Uses a dedicated inner hook so only services that explicitly opt in
 * render on the network sidebar. This prevents site-level widgets (like
 * History Insights) from showing misleading data when viewing
 * network-level events.
 *
 * @since 5.6.0
 */
class Network_Sidebar_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'simple_history/network_history_page/after_gui', [ $this, 'output_sidebar_html' ] );
		add_action( 'simple_history/network_dropin/sidebar/sidebar_html', [ $this, 'output_support_box' ], 100 );
		add_action( 'simple_history/network_dropin/sidebar/sidebar_html', [ $this, 'output_multisite_premium_box' ], 10 );
	}

	/**
	 * Output the sidebar wrapper and fire the inner hook for sidebar boxes.
	 */
	public function output_sidebar_html() {
		?>
		<div class="SimpleHistory__pageSidebar">
			<div class="metabox-holder">
				<?php
				/**
				 * Fires inside the network admin sidebar.
				 *
				 * Services that want to render in the network sidebar should
				 * hook here — using a dedicated action (not the shared site
				 * sidebar action) forces intentional context handling.
				 *
				 * @since 5.6.0
				 */
				do_action( 'simple_history/network_dropin/sidebar/sidebar_html' );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the "Need help?" support box.
	 *
	 * Always shown — this is a utility link, not a promo, so it's useful
	 * to Premium customers too.
	 */
	public function output_support_box() {
		$support_url = Helpers::is_premium_add_on_active()
			? 'https://simple-history.com/support/'
			: 'https://wordpress.org/support/plugin/simple-history';
		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<h3 class="hndle"><?php echo esc_html_x( 'Need help?', 'Sidebar box', 'simple-history' ); ?></h3>
			<div class="inside">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: 1 is a link to the support forum. */
							_x( '<a href="%1$s">Visit the support forum</a> if you need help or have questions.', 'Sidebar box', 'simple-history' ),
							[ 'a' => [ 'href' => [] ] ]
						),
						esc_url( $support_url )
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a multisite-specific premium upgrade card.
	 *
	 * Anchored to the scale the super admin is operating at — dynamic
	 * site count makes the pitch feel bespoke for agencies managing many sites.
	 */
	public function output_multisite_premium_box() {
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		// Don't pitch premium to customers who already have it.
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		$site_count = function_exists( 'get_blog_count' ) ? (int) get_blog_count() : 0;

		$headline = $site_count > 0
			? sprintf(
				/* translators: %d: number of sites in the network. */
				_nx( 'Managing %d site?', 'Managing %d sites?', $site_count, 'Network sidebar premium box', 'simple-history' ),
				$site_count
			)
			: _x( 'Built for multisite networks', 'Network sidebar premium box', 'simple-history' );

		$upgrade_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'network_sidebar_multisite_card' );
		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<h3 class="hndle"><?php echo esc_html( $headline ); ?></h3>
			<div class="inside">
				<p>
					<?php echo esc_html_x( 'Simple History Premium adds tools built for multisite operators:', 'Network sidebar premium box', 'simple-history' ); ?>
				</p>
				<ul>
					<li><?php echo esc_html_x( 'Cross-site event search and filtering', 'Network sidebar premium box', 'simple-history' ); ?></li>
					<li><?php echo esc_html_x( 'Email alerts when something changes on any site', 'Network sidebar premium box', 'simple-history' ); ?></li>
					<li><?php echo esc_html_x( 'Export network-wide activity for client reports', 'Network sidebar premium box', 'simple-history' ); ?></li>
					<li><?php echo esc_html_x( 'Retention beyond 60 days across all sites', 'Network sidebar premium box', 'simple-history' ); ?></li>
				</ul>
				<p>
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html_x( 'See Premium for Multisite →', 'Network sidebar premium box', 'simple-history' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}
}
