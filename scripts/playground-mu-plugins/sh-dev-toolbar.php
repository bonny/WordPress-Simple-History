<?php
/**
 * Plugin Name: Simple History Dev Toolbar
 * Description: Admin bar shortcuts that open the current worktree in local apps (Fork, VS Code, iTerm, Finder) via the parallel-dev localhost helper. Only loaded in parallel-dev Playground instances — this file is mounted as an mu-plugin by scripts/parallel-dev.sh and is never shipped with the plugin.
 *
 * @package SimpleHistoryDev
 */

/**
 * Label describing the database this instance runs on.
 *
 * Reads the engine the same way Simple History does, so the toolbar cannot
 * disagree with the plugin about which query path is in use.
 *
 * @return string
 */
function simple_history_dev_toolbar_db_label() {
	global $wpdb;

	$is_sqlite = defined( 'DB_ENGINE' ) && constant( 'DB_ENGINE' ) === 'sqlite';

	if ( $is_sqlite ) {
		$version = class_exists( 'SQLite3' ) ? \SQLite3::version()['versionString'] : '';

		// Named so the consequence travels with the fact — the whole reason to
		// surface this is that ungrouped events look like a bug on SQLite.
		return $version
			? sprintf( 'DB: SQLite %s — events not grouped', $version )
			: 'DB: SQLite — events not grouped';
	}

	$server = '';

	if ( $wpdb instanceof \wpdb && method_exists( $wpdb, 'db_server_info' ) ) {
		$server = (string) $wpdb->db_server_info();
	}

	if ( $server === '' ) {
		return 'DB: MySQL';
	}

	$is_maria = stripos( $server, 'mariadb' ) !== false;

	return sprintf(
		'DB: %s %s',
		$is_maria ? 'MariaDB' : 'MySQL',
		preg_replace( '/[^0-9.].*$/', '', $server )
	);
}

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

		// Which database this instance runs on, because it changes behaviour in
		// ways that look like bugs. Playground is SQLite, and Simple History
		// deliberately skips occasion grouping there (Log_Query::query_overview()
		// routes SQLite to query_overview_simple(), which returns every event
		// individually) — so repeated events appear ungrouped and it is easy to
		// go hunting for a regression that is not there.
		$wp_admin_bar->add_node(
			[
				'id'     => 'sh-dev-worktree-db',
				'parent' => 'sh-dev-worktree',
				'title'  => esc_html( simple_history_dev_toolbar_db_label() ),
				'href'   => false,
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
