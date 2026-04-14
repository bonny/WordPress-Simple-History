<?php

namespace Simple_History\Services;

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
		add_action( 'simple_history/enqueue_admin_scripts', [ $this, 'localize_network_context' ] );
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
			[
				'isNetworkAdmin' => true,
				'apiNamespace'   => 'simple-history/v1/network',
			]
		);
	}

	/**
	 * Add Simple History page to the Network Admin menu.
	 */
	public function add_network_admin_menu() {
		// Same SVG icon as the site-level menu.
		$logo_icon = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9Ii0yIC0yIDI0IDI0IiBmaWxsPSJub25lIgogICAgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxwYXRoIGQ9Ik0xNC4wNjI3IDAuNzc3NTg2QzkuMjM5MDQgLTEuMjkyNTMgMy43Njk2MiAwLjk1MzcxMSAxLjkxNzEzIDUuMzQ4NjNMMC4zOTEyNCA0LjY5Mzc3QzAuMTY5MDA3IDQuNTk4NCAtMC4wNTg3NTI3IDQuODE2ODcgMC4wMTM3MTIyIDUuMDU1OUwwLjg1MzU3MyA5Ljc3ODU2QzAuOTM1MjQ1IDEwLjA0OCAxLjIzNDM4IDEwLjE3MDEgMS40Njk0MSAxMC4wM0w1LjAzOTQ0IDcuMjA0MTdDNS4yNDQwMyA3LjA4MjIxIDUuMjI0NDQgNi43NjggNS4wMDY0MiA2LjY3NDQzTDMuMzcwODIgNS45NzI0OUM0Ljg5MzM2IDIuNDE0ODQgOS40NDQ4NiAwLjU2ODMgMTMuNDY1NyAyLjI5Mzg4QzE3LjQ4NjUgNC4wMTk0NiAxOS41MDQ2IDguOTI0OTMgMTcuODI2NCAxMy4xODc1QzE2LjE0ODIgMTcuNDUwMSAxMS40NzQ5IDE5LjQ4MzggNy4zODgzMSAxNy43M0M1LjYxNzMxIDE2Ljk3IDQuMjQ3NDUgMTUuNjIzMSAzLjM5OTggMTMuOTk0MUMzLjE5NDA5IDEzLjU5ODcgMi43NDEzMSAxMy40MDIgMi4zNDM0MiAxMy41NzUxQzEuOTQwNDggMTMuNzUwNSAxLjc0NzgxIDE0LjIzNzUgMS45NTAyNCAxNC42NDEyQzIuOTU4MDkgMTYuNjUxIDQuNjIzNDkgMTguMzE2IDYuNzkxMzMgMTkuMjQ2M0MxMS42ODA3IDIxLjM0NDcgMTcuMjcyMiAxOC45MTEzIDE5LjI4IDEzLjgxMTRDMjEuMjg3OSA4LjcxMTQyIDE4Ljk1MiAyLjg3NTkxIDE0LjA2MjcgMC43Nzc1ODZaIiBmaWxsPSJibGFjayIvPgogICAgPHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik05LjI5ODA4IDYuMTcwNzZDOS4yOTgwOCA1Ljc3MzA3IDkuNTk5NTkgNS40NTA2OCA5Ljk3MTUxIDUuNDUwNjhDMTAuMzQzNCA1LjQ1MDY4IDEwLjY0NDkgNS43NzMwNyAxMC42NDQ5IDYuMTcwNzZWMTAuNTIyOUwxMy42ODY3IDEyLjUwMzZDMTQuMDAzNyAxMi43MTAxIDE0LjEwMDggMTMuMTU0NCAxMy45MDIyIDEzLjQ4OTdDMTMuNzA4NCAxMy44MTY4IDEzLjMwNTMgMTMuOTE3NSAxMi45OTYxIDEzLjcxNjFMOS4yOTgwOCAxMS4zMDgxVjYuMTcwNzZaIiBmaWxsPSJibGFjayIvPgo8L3N2Zz4K';

		$this->page_hook = add_menu_page(
			_x( 'Network History - Simple History', 'network admin page title', 'simple-history' ),
			_x( 'Simple History', 'network admin menu name', 'simple-history' ),
			'manage_network',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			$logo_icon,
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
				<div class="sh-PageHeader-titleGroup">
					<h1 class="sh-PageHeader-title SimpleHistoryPageHeadline">
						<img width="1000" height="156" class="sh-PageHeader-logo" src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL ); ?>css/simple-history-logo.png" alt="Simple History logotype"/>
					</h1>
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
