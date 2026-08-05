<?php
/**
 * Plugin Name: Simple History Dev Toolbar
 * Description: Admin bar shortcuts that open the current worktree in local apps (Fork, VS Code, iTerm, Finder) via the parallel-dev localhost helper. Only loaded in parallel-dev Playground instances — this file is mounted as an mu-plugin by scripts/parallel-dev.sh and is never shipped with the plugin.
 *
 * @package SimpleHistoryDev
 */

/**
 * Whether the dev toolbar can render in the current request.
 *
 * @return bool
 */
function simple_history_dev_toolbar_available() {
	if ( ! defined( 'SH_DEV_WORKTREE_PATH' ) || ! defined( 'SH_DEV_HELPER_PORT' ) ) {
		return false;
	}

	return current_user_can( 'manage_options' );
}

// esc_url() (used by the admin bar on every href) strips protocols it
// doesn't know; allow obsidian:// for the issue deep link.
add_filter(
	'kses_allowed_protocols',
	function ( $protocols ) {
		$protocols[] = 'obsidian';

		return $protocols;
	}
);

add_action(
	'admin_bar_menu',
	function ( $wp_admin_bar ) {
		if ( ! simple_history_dev_toolbar_available() ) {
			return;
		}

		$worktree_path = SH_DEV_WORKTREE_PATH;
		$slug          = basename( $worktree_path );
		$helper_url    = 'http://127.0.0.1:' . SH_DEV_HELPER_PORT . '/open';
		$helper_token  = defined( 'SH_DEV_HELPER_TOKEN' ) ? SH_DEV_HELPER_TOKEN : '';

		$wp_admin_bar->add_node(
			[
				'id'    => 'sh-dev-worktree',
				'title' => '🛠 ' . esc_html( $slug ),
				'href'  => false,
			]
		);

		// Issue deep link first — a plain obsidian:// href the OS hands to
		// Obsidian directly; the click handler below leaves it alone.
		if ( defined( 'SH_DEV_ISSUE_URL' ) ) {
			$wp_admin_bar->add_node(
				[
					'id'     => 'sh-dev-worktree-issue',
					'parent' => 'sh-dev-worktree',
					'title'  => 'Open issue document',
					'href'   => SH_DEV_ISSUE_URL,
				]
			);
		}

		$apps = [
			'vscode' => 'Open in VS Code',
			'fork'   => 'Open in Fork',
			'iterm'  => 'Open in iTerm',
			'finder' => 'Reveal in Finder',
		];

		foreach ( $apps as $app => $label ) {
			$open_url = $helper_url . '?app=' . $app . '&path=' . rawurlencode( $worktree_path ) . '&token=' . rawurlencode( $helper_token );

			$wp_admin_bar->add_node(
				[
					'id'     => 'sh-dev-worktree-' . $app,
					'parent' => 'sh-dev-worktree',
					'title'  => esc_html( $label ),
					'href'   => esc_url( $open_url ),
				]
			);
		}
	},
	100
);

/**
 * Intercept clicks on the toolbar links and fire them as background
 * requests instead of navigating. The href works without JS too — it
 * just lands on the helper's plain-text "ok" response.
 *
 * Note: the admin bar runs `meta.onclick` through esc_js(), which
 * backslash-escapes quotes and breaks inline handlers — hence this
 * delegated listener instead.
 */
function simple_history_dev_toolbar_print_script() {
	if ( ! simple_history_dev_toolbar_available() ) {
		return;
	}
	?>
	<script>
	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '#wp-admin-bar-sh-dev-worktree a.ab-item[href*=":<?php echo (int) SH_DEV_HELPER_PORT; ?>/open"]' );

		if ( ! link ) {
			return;
		}

		event.preventDefault();
		fetch( link.href, { mode: 'no-cors' } );
	} );
	</script>
	<?php
}

// Fires right where the admin bar was rendered, on both front end and
// admin — one hook instead of admin_footer + wp_footer.
add_action( 'wp_after_admin_bar_render', 'simple_history_dev_toolbar_print_script' );
