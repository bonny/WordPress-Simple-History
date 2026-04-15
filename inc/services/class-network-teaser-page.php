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

		// The teaser uses add_menu_page() directly, not the Menu_Manager, so
		// Helpers::is_on_our_own_pages() doesn't recognize the page slug on
		// its own. Opt in via the filter so the core stylesheet (and the
		// rest of the Simple History asset pipeline) loads here too.
		add_filter( 'simple_history/is_on_our_own_pages', [ $this, 'mark_teaser_as_our_own_page' ] );
	}

	/**
	 * Filter callback that marks the Network Admin teaser page as a
	 * Simple History page so the main stylesheet gets enqueued.
	 *
	 * @param bool $is_on_our_own_pages Current value.
	 * @return bool
	 */
	public function mark_teaser_as_our_own_page( $is_on_our_own_pages ) {
		if ( $is_on_our_own_pages ) {
			return $is_on_our_own_pages;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen detection, no state change.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : null;

		return $page === self::MENU_SLUG;
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

			<header class="sh-NetworkTeaser-hero">
				<div class="sh-NetworkTeaser-heroCopy">
					<span class="sh-NetworkTeaser-badge">
						<?php echo esc_html_x( 'Premium feature', 'Network Admin teaser badge', 'simple-history' ); ?>
					</span>

					<h1 class="sh-NetworkTeaser-title">
						<?php echo esc_html_x( 'Finally, a log for the Network Admin.', 'Network Admin teaser heading', 'simple-history' ); ?>
					</h1>

					<p class="sh-NetworkTeaser-lede">
						<?php
						echo esc_html_x(
							"Super admin actions — creating sites, granting super admin, network-activating plugins, changing network settings — aren't logged today, or land silently on the main site's log. Premium adds a dedicated log for network-level events.",
							'Network Admin teaser lede',
							'simple-history'
						);
						?>
					</p>
				</div>

				<div class="sh-NetworkTeaser-heroActions">
					<a href="<?php echo esc_url( $upgrade_url ); ?>" class="button button-primary button-hero sh-NetworkTeaser-primaryCta">
						<?php echo esc_html_x( 'Get Simple History Premium — $79/year', 'Network Admin teaser upgrade button', 'simple-history' ); ?>
					</a>

					<a href="<?php echo esc_url( $license_url ); ?>" class="sh-NetworkTeaser-secondaryLink">
						<?php echo esc_html_x( 'Already have Premium? Activate your license →', 'Network Admin teaser license link', 'simple-history' ); ?>
					</a>
				</div>
			</header>

			<ul class="sh-NetworkTeaser-cards">
				<li class="sh-NetworkTeaser-card">
					<span class="dashicons dashicons-networking sh-NetworkTeaser-cardIcon" aria-hidden="true"></span>
					<h2 class="sh-NetworkTeaser-cardTitle">
						<?php echo esc_html_x( 'Dedicated log for network-level events', 'Network Admin teaser card heading', 'simple-history' ); ?>
					</h2>
					<p class="sh-NetworkTeaser-cardBody">
						<?php echo esc_html_x( 'Site creation and deletion, super admin grants, network-activated plugins, network settings changes — all kept in their own log instead of silently dumped on the main site.', 'Network Admin teaser card body', 'simple-history' ); ?>
					</p>
				</li>

				<li class="sh-NetworkTeaser-card">
					<span class="dashicons dashicons-filter sh-NetworkTeaser-cardIcon" aria-hidden="true"></span>
					<h2 class="sh-NetworkTeaser-cardTitle">
						<?php echo esc_html_x( 'Filter by user, event type, or date', 'Network Admin teaser card heading', 'simple-history' ); ?>
					</h2>
					<p class="sh-NetworkTeaser-cardBody">
						<?php echo esc_html_x( 'Pinpoint exactly who changed what and when. Same filter UX as the per-site log you already know.', 'Network Admin teaser card body', 'simple-history' ); ?>
					</p>
				</li>

				<li class="sh-NetworkTeaser-card">
					<span class="dashicons dashicons-editor-code sh-NetworkTeaser-cardIcon" aria-hidden="true"></span>
					<h2 class="sh-NetworkTeaser-cardTitle">
						<?php echo esc_html_x( 'WP-CLI for scripted audits', 'Network Admin teaser card heading', 'simple-history' ); ?>
					</h2>
					<p class="sh-NetworkTeaser-cardBody">
						<?php
						printf(
							/* translators: %s: the WP-CLI command with --network flag */
							esc_html_x( 'Run %s to pipe events into scripts, cron, or your monitoring of choice.', 'Network Admin teaser card body', 'simple-history' ),
							'<code>wp simple-history list --network</code>'
						);
						?>
					</p>
				</li>
			</ul>

			<section class="sh-NetworkTeaser-preview" aria-label="<?php esc_attr_e( 'Sample events', 'simple-history' ); ?>">
				<p class="sh-NetworkTeaser-previewLabel">
					<?php echo esc_html_x( 'What the Network Event Log looks like', 'Network Admin teaser preview heading', 'simple-history' ); ?>
				</p>

				<ul class="sh-NetworkTeaser-previewRows">
					<?php
					$sample_rows = [
						[ 'A', __( 'Anna added Ben as Super Admin', 'simple-history' ), __( '2 min ago', 'simple-history' ), 'network.example.com' ],
						[ 'J', __( 'Plugin "WooCommerce" network-activated', 'simple-history' ), __( '1 hour ago', 'simple-history' ), __( 'Network Admin', 'simple-history' ) ],
						[ 'M', __( 'Site "shop.example.com" created', 'simple-history' ), __( 'Yesterday', 'simple-history' ), __( 'Network Admin', 'simple-history' ) ],
						[ 'A', __( 'Network setting "Registration" changed', 'simple-history' ), __( '3 days ago', 'simple-history' ), __( 'Network Admin', 'simple-history' ) ],
					];
					foreach ( $sample_rows as $row ) {
						list( $initial, $message, $when, $where ) = $row;
						?>
						<li class="sh-NetworkTeaser-previewRow">
							<span class="sh-NetworkTeaser-previewAvatar" aria-hidden="true"><?php echo esc_html( $initial ); ?></span>
							<span class="sh-NetworkTeaser-previewMessage"><?php echo esc_html( $message ); ?></span>
							<span class="sh-NetworkTeaser-previewMeta">
								<?php echo esc_html( $when ); ?>
								<span class="sh-NetworkTeaser-previewSep" aria-hidden="true">·</span>
								<?php echo esc_html( $where ); ?>
							</span>
						</li>
					<?php } ?>
				</ul>
			</section>

			<p class="sh-NetworkTeaser-trust">
				<?php
				echo esc_html_x(
					'Used on 300,000+ sites · 4.8 on WordPress.org · 30-day money-back guarantee',
					'Network Admin teaser trust line',
					'simple-history'
				);
				?>
			</p>

			<section class="sh-NetworkTeaser-faq" aria-label="<?php esc_attr_e( 'Frequently asked questions', 'simple-history' ); ?>">
				<details class="sh-NetworkTeaser-faqItem">
					<summary><?php echo esc_html_x( 'Does this replace the per-site logs?', 'Network Admin teaser FAQ question', 'simple-history' ); ?></summary>
					<p><?php echo esc_html_x( "No. The per-site logs keep working exactly as they do today. The network log is a separate view that captures actions super admins take at the network level — things like site creation or network settings changes that currently aren't logged or end up on the main site's log by accident.", 'Network Admin teaser FAQ answer', 'simple-history' ); ?></p>
				</details>

				<details class="sh-NetworkTeaser-faqItem">
					<summary><?php echo esc_html_x( 'One license for a whole multisite — how does that work?', 'Network Admin teaser FAQ question', 'simple-history' ); ?></summary>
					<p><?php echo esc_html_x( 'A multisite network counts as one site for licensing. Running 30 sub-sites on one network? One $79/year license.', 'Network Admin teaser FAQ answer', 'simple-history' ); ?></p>
				</details>

				<details class="sh-NetworkTeaser-faqItem">
					<summary><?php echo esc_html_x( 'Can I try it first?', 'Network Admin teaser FAQ question', 'simple-history' ); ?></summary>
					<p><?php echo esc_html_x( 'Yes. There is a 30-day money-back guarantee — install it, run it on real data, get a refund if it is not pulling its weight.', 'Network Admin teaser FAQ answer', 'simple-history' ); ?></p>
				</details>
			</section>

		</div>
		<?php
	}
}
