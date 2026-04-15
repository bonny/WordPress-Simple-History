<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Show a Network Admin menu item on multisite that explains
 * network-wide event logging is available in Simple History Premium.
 *
 * When the premium add-on is active (and its network module running), it
 * registers its own menu at the same slug, which takes over the page.
 *
 * @since 5.13.0
 */
class Network_Teaser_Page extends Service {
	/** @var string Menu slug shared with the premium network page so the add-on can take over. */
	public const MENU_SLUG = 'simple_history_network_page';

	/** @inheritdoc */
	public function loaded() {
		if ( ! is_multisite() ) {
			return;
		}

		// Network event logging is rolling out behind the experimental
		// features flag while the premium add-on lands.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_action( 'network_admin_menu', [ $this, 'add_menu_page' ] );
	}

	/**
	 * Register the Network Admin menu item.
	 */
	public function add_menu_page() {
		// Premium's Network_Module registers at the same slug when active;
		// bail out cleanly so there's no duplicate registration warning.
		if ( Helpers::is_premium_add_on_active() ) {
			return;
		}

		add_menu_page(
			_x( 'Network History — Simple History', 'Network Admin teaser page title', 'simple-history' ),
			_x( 'Simple History', 'Network Admin teaser menu label', 'simple-history' ),
			'manage_network',
			self::MENU_SLUG,
			[ $this, 'render_page' ],
			Admin_Pages::MENU_ICON,
			3
		);
	}

	/**
	 * Render the teaser page.
	 *
	 * Presented as an informative product page, not as a disabled/blocked
	 * feature — core has never offered network logging and none of the
	 * existing functionality is affected on sub-sites.
	 */
	public function render_page() {
		$upgrade_url = 'https://simple-history.com/premium/?utm_source=wpadmin&utm_medium=network-teaser&utm_campaign=network-log';
		$license_url = network_admin_url( 'settings.php#simple-history-license' );
		?>
		<div class="wrap sh-NetworkTeaser-wrap">
			<div class="sh-NetworkTeaser">
				<h1 class="sh-NetworkTeaser-title">
					<?php echo esc_html_x( 'Network Event Log', 'Network Admin teaser heading', 'simple-history' ); ?>
				</h1>

				<p class="sh-NetworkTeaser-lede">
					<?php echo esc_html_x( 'Track what happens across all sites in your network — from one place.', 'Network Admin teaser lede', 'simple-history' ); ?>
				</p>

				<p>
					<?php
					echo esc_html_x(
						"Simple History's free version logs activity on each site individually. Network-wide logging is available in Simple History Premium.",
						'Network Admin teaser explainer',
						'simple-history'
					);
					?>
				</p>

				<p>
					<?php
					echo esc_html_x(
						'Super admins managing multiple sites often need to know: who changed a setting on the marketing site? Which editor deleted content on the client portal? The Network Event Log gives you a single stream of activity across your entire WordPress network.',
						'Network Admin teaser body',
						'simple-history'
					);
					?>
				</p>

				<h2 class="sh-NetworkTeaser-subheading">
					<?php echo esc_html_x( "What's included in Simple History Premium on multisite", 'Network Admin teaser feature list heading', 'simple-history' ); ?>
				</h2>

				<ul class="sh-NetworkTeaser-features">
					<li><?php echo esc_html_x( 'A dedicated Network Admin event log — site creation and deletion, super admin grants and revokes, plugin network activations, network settings changes, and more (25+ event types)', 'Network Admin teaser feature', 'simple-history' ); ?></li>
					<li><?php echo esc_html_x( 'Unified activity feed across every site in your network', 'Network Admin teaser feature', 'simple-history' ); ?></li>
					<li><?php echo esc_html_x( 'Filter network events by user, event type, or date', 'Network Admin teaser feature', 'simple-history' ); ?></li>
					<li>
						<?php
						printf(
							/* translators: %s: the WP-CLI command with --network flag */
							esc_html_x( 'WP-CLI support with %s for scripted audits', 'Network Admin teaser feature', 'simple-history' ),
							'<code>wp simple-history list --network</code>'
						);
						?>
					</li>
					<li><?php echo esc_html_x( 'Full event details for every action — before/after diffs, affected sites, user metadata', 'Network Admin teaser feature', 'simple-history' ); ?></li>
				</ul>

				<div class="sh-NetworkTeaser-cta">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero">
						<?php echo esc_html_x( 'Upgrade to Simple History Premium', 'Network Admin teaser upgrade button', 'simple-history' ); ?>
					</a>

					<p class="sh-NetworkTeaser-licenseLink">
						<?php
						printf(
							/* translators: %s: link to activate license */
							esc_html_x( 'Already have Premium? %s', 'Network Admin teaser license prompt', 'simple-history' ),
							'<a href="' . esc_url( $license_url ) . '">' . esc_html_x( 'Activate your license', 'Network Admin teaser license link', 'simple-history' ) . '</a>'
						);
						?>
					</p>
				</div>

				<p class="sh-NetworkTeaser-footer">
					<?php
					printf(
						/* translators: %s: link to simple-history.com premium page */
						esc_html_x( 'Need more information? %s', 'Network Admin teaser learn more prompt', 'simple-history' ),
						'<a href="' . esc_url( $upgrade_url ) . '">' . esc_html_x( 'Learn more about Simple History Premium', 'Network Admin teaser learn more link', 'simple-history' ) . '</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
