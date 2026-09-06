<?php
/**
 * Plugin Name: Simple History Dev Toolbar
 * Description: Admin bar panel naming the git branch the site is serving, plus the database engine, and — in a parallel-dev worktree — shortcuts that open it in local apps. Mounted as an mu-plugin for local development only; it lives under scripts/, which .distignore excludes, so it is never shipped with the plugin.
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
	return current_user_can( 'manage_options' );
}

/**
 * Whether this site is served by a parallel-dev worktree, which is what the
 * open-in-app shortcuts talk to. Plain local sites get the rest of the panel
 * without them.
 *
 * @return bool
 */
function simple_history_dev_toolbar_has_worktree() {
	return defined( 'SH_DEV_WORKTREE_PATH' ) && defined( 'SH_DEV_HELPER_PORT' );
}

/**
 * The checkout this site is serving, as this process can see it.
 *
 * Deliberately not SH_DEV_WORKTREE_PATH: that is a path on the developer's
 * machine, meaningful to the open-in-app helper which runs there, but absent
 * inside the container serving the site. The plugin directory is the mounted
 * checkout in both the worktree and plain cases, so it is the one path that
 * resolves from in here.
 *
 * @return string Absolute path, or '' if it cannot be determined.
 */
function simple_history_dev_toolbar_repo_path() {
	if ( defined( 'SH_DEV_REPO_PATH' ) ) {
		return SH_DEV_REPO_PATH;
	}

	$plugin_dir = WP_PLUGIN_DIR . '/simple-history';

	return is_dir( $plugin_dir ) ? $plugin_dir : '';
}

/**
 * The git branch checked out in a directory.
 *
 * Reads .git directly rather than shelling out to git, which would be a
 * process per page load and is often unavailable in a container anyway.
 *
 * Handles the two shapes .git comes in: a directory in an ordinary clone, and
 * a file containing "gitdir: <path>" in a worktree.
 *
 * @param string $dir Directory to inspect.
 * @return string Branch name, a short commit hash when detached, or ''.
 */
function simple_history_dev_toolbar_branch( $dir ) {
	// A worktree's .git is a file pointing at an absolute path on the
	// developer's machine, which this process cannot follow, so parallel-dev
	// passes the branch it already knows.
	if ( defined( 'SH_DEV_BRANCH' ) && SH_DEV_BRANCH !== '' ) {
		return SH_DEV_BRANCH;
	}

	static $cache = [];

	if ( isset( $cache[ $dir ] ) ) {
		return $cache[ $dir ];
	}

	$cache[ $dir ] = '';

	if ( $dir === '' ) {
		return '';
	}

	$git_path = $dir . '/.git';

	if ( is_file( $git_path ) ) {
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local .git pointer file, not a remote fetch.
		$pointer = trim( (string) file_get_contents( $git_path ) );

		if ( strpos( $pointer, 'gitdir:' ) !== 0 ) {
			return '';
		}

		$git_path = trim( substr( $pointer, strlen( 'gitdir:' ) ) );
	}

	$head_path = $git_path . '/HEAD';

	if ( ! is_readable( $head_path ) ) {
		return '';
	}

	// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local .git/HEAD, not a remote fetch.
	$head = trim( (string) file_get_contents( $head_path ) );

	if ( strpos( $head, 'ref: refs/heads/' ) === 0 ) {
		$cache[ $dir ] = substr( $head, strlen( 'ref: refs/heads/' ) );
	} elseif ( $head !== '' ) {
		// Detached HEAD — show the short hash so it is at least identifiable.
		$cache[ $dir ] = substr( $head, 0, 7 );
	}

	return $cache[ $dir ];
}

/**
 * Read a single-line value out of a file in the checkout, or '' if absent.
 *
 * @param string $path Absolute path.
 * @return string
 */
function simple_history_dev_toolbar_read_line( $path ) {
	if ( $path === '' || ! is_readable( $path ) ) {
		return '';
	}

	// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local file, not a remote fetch.
	return trim( (string) file_get_contents( $path ) );
}

/**
 * Port the open-in-app helper listens on.
 *
 * @return int
 */
function simple_history_dev_toolbar_helper_port() {
	// Matches HELPER_PORT in scripts/parallel-dev.sh; the constant is only set
	// inside a worktree instance.
	return defined( 'SH_DEV_HELPER_PORT' ) ? (int) SH_DEV_HELPER_PORT : 9399;
}

/**
 * Shared secret the helper requires.
 *
 * @return string
 */
function simple_history_dev_toolbar_helper_token() {
	if ( defined( 'SH_DEV_HELPER_TOKEN' ) ) {
		return SH_DEV_HELPER_TOKEN;
	}

	return simple_history_dev_toolbar_read_line(
		simple_history_dev_toolbar_repo_path() . '/.claude/parallel-dev-helper-token'
	);
}

/**
 * The checkout's path *on the developer's machine*.
 *
 * Distinct from simple_history_dev_toolbar_repo_path(), which is the path this
 * process can read. The helper runs outside the container and opens editors
 * there, so it needs the host's view — which a containerised site cannot work
 * out for itself. A worktree gets it as a constant; otherwise parallel-dev
 * leaves it in a file inside the checkout.
 *
 * @return string
 */
function simple_history_dev_toolbar_host_path() {
	if ( defined( 'SH_DEV_WORKTREE_PATH' ) ) {
		return SH_DEV_WORKTREE_PATH;
	}

	return simple_history_dev_toolbar_read_line(
		simple_history_dev_toolbar_repo_path() . '/.claude/dev-host-path'
	);
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

		$repo_path = simple_history_dev_toolbar_repo_path();
		$branch    = simple_history_dev_toolbar_branch( $repo_path );

		// In a worktree the slug names the work; on a plain checkout the branch
		// is the only thing that identifies what the site is serving. Either way
		// the point is to answer "what am I looking at?" without leaving the page.
		$label = simple_history_dev_toolbar_has_worktree()
			? basename( SH_DEV_WORKTREE_PATH )
			: ( $branch !== '' ? $branch : 'local' );

		$wp_admin_bar->add_node(
			[
				'id'    => 'sh-dev-worktree',
				'title' => '🛠 ' . esc_html( $label ),
				'href'  => false,
			]
		);

		// Spelled out even when it is already the label, because a worktree's
		// slug and its branch are not the same string.
		if ( $branch !== '' ) {
			$wp_admin_bar->add_node(
				[
					'id'     => 'sh-dev-worktree-branch',
					'parent' => 'sh-dev-worktree',
					'title'  => esc_html( 'Branch: ' . $branch ),
					'href'   => false,
				]
			);
		}

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

		$host_path    = simple_history_dev_toolbar_host_path();
		$helper_token = simple_history_dev_toolbar_helper_token();

		// Both are needed for the helper to act: a path on this developer's
		// machine, and the secret it checks. Worktrees get them as constants;
		// plain sites read them out of the checkout. Without either there is
		// nothing to link to, so the panel simply ends here.
		if ( $host_path === '' || $helper_token === '' ) {
			return;
		}

		$helper_url = 'http://127.0.0.1:' . simple_history_dev_toolbar_helper_port() . '/open';

		$apps = [
			'vscode' => 'Open in VS Code',
			'zed'    => 'Open in Zed',
			'fork'   => 'Open in Fork',
			'iterm'  => 'Open in iTerm',
			'finder' => 'Reveal in Finder',
		];

		foreach ( $apps as $app => $app_label ) {
			$open_url = $helper_url . '?app=' . $app . '&path=' . rawurlencode( $host_path ) . '&token=' . rawurlencode( $helper_token );

			$wp_admin_bar->add_node(
				[
					'id'     => 'sh-dev-worktree-' . $app,
					'parent' => 'sh-dev-worktree',
					'title'  => esc_html( $app_label ),
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
		var link = event.target.closest( '#wp-admin-bar-sh-dev-worktree a.ab-item[href*=":<?php echo (int) simple_history_dev_toolbar_helper_port(); ?>/open"]' );

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
