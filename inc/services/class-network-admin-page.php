<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Services\Admin_Pages;

/**
 * Register Simple History page in the Network Admin.
 *
 * Shows network-level events to super admins.
 *
 * @since 5.6.0
 */
class Network_Admin_Page extends Service {
	/** Network admin page slug. */
	public const PAGE_SLUG = 'simple_history_network_page';

	/** @var string The hook suffix returned by add_menu_page. */
	private $page_hook = '';

	/** @inheritdoc */
	public function loaded() {
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'network_admin_menu', [ $this, 'add_network_admin_menu' ] );

		// Priority 20: run AFTER React_Dropin (priority 10) has registered
		// the simple_history_wp_scripts handle, otherwise wp_localize_script
		// silently fails and the React app never learns it's in network context.
		add_action( 'simple_history/enqueue_admin_scripts', [ $this, 'localize_network_context' ], 20 );
	}

	/**
	 * Attach the simpleHistoryNetworkContext JS variable to the React bundle
	 * when rendering the network admin page.
	 *
	 * The core Scripts_And_Templates service already enqueues all Simple
	 * History assets (including the React app via React_Dropin) whenever
	 * Helpers::is_on_our_own_pages() is true. Our Network Admin page is
	 * recognized as one of our own pages, so all we need to do is tag the
	 * React bundle with the network context so the frontend routes its API
	 * calls to the network endpoint.
	 */
	public function localize_network_context() {
		if ( ! is_network_admin() ) {
			return;
		}

		wp_localize_script(
			'simple_history_wp_scripts',
			'simpleHistoryNetworkContext',
			self::get_network_context_data()
		);
	}

	/**
	 * Data shape attached to JS via wp_localize_script so the React app
	 * knows it's in the Network Admin context. Shared between the main
	 * bundle (localized by this service) and the admin bar bundle
	 * (localized by Quick_View_Dropin).
	 *
	 * @since 5.6.0
	 * @return array<string,mixed>
	 */
	public static function get_network_context_data() {
		return [
			'isNetworkAdmin' => true,
			'adminPageUrl'   => Helpers::get_network_admin_page_url(),
		];
	}

	/**
	 * Add Simple History page to the Network Admin menu.
	 */
	public function add_network_admin_menu() {
		$this->page_hook = add_menu_page(
			_x( 'Network History - Simple History', 'network admin page title', 'simple-history' ),
			_x( 'Simple History', 'network admin menu name', 'simple-history' ),
			'manage_network',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			Admin_Pages::MENU_ICON,
			3
		);
	}


	/**
	 * Render the network admin page.
	 */
	public function render_page() {
		?>
		<div class="SimpleHistoryWrap">
			<header class="sh-PageHeader">
				<div class="sh-PageHeader-titleGroup sh-PageHeader-titleGroup--stacked">
					<h1 class="sh-PageHeader-title SimpleHistoryPageHeadline">
						<img width="1000" height="156" class="sh-PageHeader-logo" src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL ); ?>css/simple-history-logo.png" alt="Simple History logotype"/>
						<span class="sh-PageHeader-badge sh-PageHeader-badge--network">
							<?php echo esc_html_x( 'Network', 'Network Admin page badge', 'simple-history' ); ?>
						</span>
					</h1>
					<p class="sh-PageHeader-subtitle">
						<?php echo esc_html_x( 'Showing network-wide events — plugin activations, site creation, super admin changes, and more.', 'Network Admin page subtitle', 'simple-history' ); ?>
					</p>
				</div>
			</header>

			<?php Admin_Pages::dev_badges_output(); ?>

			<div class="wrap">
				<div class="SimpleHistoryGuiWrap">
					<?php
					// Fire the same hook the React dropin listens to,
					// so it outputs the #simple-history-react-root mount div.
					do_action( 'simple_history/history_page/gui_wrap_top', $this->simple_history );

					/**
					 * Fires after the main event list on the network admin page.
					 * Dedicated hook (not the shared after_gui) so services can
					 * opt in explicitly — the network context doesn't share
					 * every sidebar widget that makes sense at the site level.
					 *
					 * Fires inside .SimpleHistoryGuiWrap so the sidebar becomes
					 * a flex sibling of the React root for proper layout.
					 *
					 * @since 5.6.0
					 *
					 * @param Simple_History $instance The Simple_History instance.
					 */
					do_action( 'simple_history/network_history_page/after_gui', $this->simple_history );
					?>
				</div>
			</div>
		</div>
		<?php
	}
}
