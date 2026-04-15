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
			self::get_menu_icon(),
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

	/**
	 * Menu icon SVG (base64-encoded). Matches the main Simple History menu icon.
	 */
	private static function get_menu_icon() {
		return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9Ii0yIC0yIDI0IDI0IiBmaWxsPSJub25lIgogICAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxwYXRoIGQ9Ik0xNC4wNjI3IDAuNzc3NTg2QzkuMjM5MDQgLTEuMjkyNTMgMy43Njk2MiAwLjk1MzcxMSAxLjkxNzEzIDUuMzQ4NjNMMC4zOTEyNCA0LjY5Mzc3QzAuMTY5MDA3IDQuNTk4NCAtMC4wNTg3NTI3IDQuODE2ODcgMC4wMTM3MTIyIDUuMDU1OUwwLjg1MzU3MyA5Ljc3ODU2QzAuOTM1MjQ1IDEwLjA0OCAxLjIzNDM4IDEwLjE3MDEgMS40Njk0MSAxMC4wM0w1LjAzOTQ0IDcuMjA0MTdDNS4yNDQwMyA3LjA4MjIxIDUuMjI0NDQgNi43NjggNS4wMDY0MiA2LjY3NDQzTDMuMzcwODIgNS45NzI0OUM0Ljg5MzM2IDIuNDE0ODQgOS40NDQ4NiAwLjU2ODMgMTMuNDY1NyAyLjI5Mzg4QzE3LjQ4NjUgNC4wMTk0NiAxOS41MDQ2IDguOTI0OTMgMTcuODI2NCAxMy4xODc1QzE2LjE0ODIgMTcuNDUwMSAxMS40NzQ5IDE5LjQ4MzggNy4zODgzMSAxNy43M0M1LjYxNzMxIDE2Ljk3IDQuMjQ3NDUgMTUuNjIzMSAzLjM5OTggMTMuOTk0MUMzLjE5NDA5IDEzLjU5ODcgMi43NDEzMSAxMy40MDIgMi4zNDM0MiAxMy41NzUxQzEuOTQwNDggMTMuNzUwNSAxLjc0NzgxIDE0LjIzNzUgMS45NTAyNCAxNC42NDEyQzIuOTU4MDkgMTYuNjUxIDQuNjIzNDkgMTguMzE2IDYuNzkxMzMgMTkuMjQ2M0MxMS42ODA3IDIxLjM0NDcgMTcuMjcyMiAxOC45MTEzIDE5LjI4IDEzLjgxMTRDMjEuMjg3OSA4LjcxMTQyIDE4Ljk1MiAyLjg3NTkxIDE0LjA2MjcgMC43Nzc1ODZaIiBmaWxsPSJibGFjayIvPgogICAgPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik05LjI5ODA4IDYuMTcwNzZDOS4yOTgwOCA1Ljc3MzA3IDkuNTk5NTkgNS40NTA2OCA5Ljk3MTUxIDUuNDUwNjhDMTAuMzQzNCA1LjQ1MDY4IDEwLjY0NDkgNS43NzMwNyAxMC42NDQ5IDYuMTcwNzZWMTAuNTIyOUwxMy42ODY3IDEyLjUwMzZDMTQuMDAzNyAxMi43MTAxIDE0LjEwMDggMTMuMTU0NCAxMy45MDIyIDEzLjQ4OTdDMTMuNzA4NCAxMy44MTY4IDEzLjMwNTMgMTMuOTE3NSAxMi45OTYxIDEzLjcxNjFMOS4yOTgwOCAxMS4zMDgxVjYuMTcwNzZaIiBmaWxsPSJibGFjayIvPgo8L3N2Zz4K';
	}
}
