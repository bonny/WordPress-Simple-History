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
function sh_dev_toolbar_available() {
	if ( ! defined( 'SH_DEV_WORKTREE_PATH' ) || ! defined( 'SH_DEV_HELPER_PORT' ) ) {
		return false;
	}

	return current_user_can( 'manage_options' );
}

add_action(
	'admin_bar_menu',
	function ( $wp_admin_bar ) {
		if ( ! sh_dev_toolbar_available() ) {
			return;
		}

		$worktree_path = SH_DEV_WORKTREE_PATH;
		$slug          = basename( $worktree_path );
		$helper_url    = 'http://127.0.0.1:' . SH_DEV_HELPER_PORT . '/open';

		$wp_admin_bar->add_node(
			[
				'id'    => 'sh-dev-worktree',
				'title' => '🛠 ' . esc_html( $slug ),
				'href'  => false,
			]
		);

		$apps = [
			'vscode' => 'Open in VS Code',
			'fork'   => 'Open in Fork',
			'iterm'  => 'Open in iTerm',
			'finder' => 'Reveal in Finder',
		];

		foreach ( $apps as $app => $label ) {
			$open_url = $helper_url . '?app=' . $app . '&path=' . rawurlencode( $worktree_path );

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
function sh_dev_toolbar_print_script() {
	if ( ! sh_dev_toolbar_available() ) {
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

add_action( 'admin_footer', 'sh_dev_toolbar_print_script' );
add_action( 'wp_footer', 'sh_dev_toolbar_print_script' );
