# Upgrading @wordpress/scripts (and why it is pinned to 27.x)

`@wordpress/scripts` is intentionally pinned to `^27.0` in package.json. Do not bump it to 28+ — not manually, not via `npm audit fix --force`, not via dependabot.

## Why the pin exists

Version 28 switched JSX compilation to React's automatic runtime. Built assets then list `react-jsx-runtime` in their `.asset.php` dependency arrays, and that script handle only exists in WordPress 6.6+. On WP 6.3–6.5 — which this plugin supports ("Requires at least: 6.3") — the scripts silently fail to enqueue: no error, the admin UI just never loads.

This is expected behavior, not a bug. The official guidance in the [JSX in WordPress 6.6 dev note](https://make.wordpress.org/core/2024/06/06/jsx-in-wordpress-6-6/) is to keep the old build tooling until the plugin's minimum WP version is 6.6+.

## Accepted trade-off

Some npm audit advisories are only fixable via a wp-scripts major bump. They are dev-only — build toolchain, nothing ships to wordpress.org users (`node_modules/`, `src/`, `scripts/`, `vendor/`, `package.json` and `package-lock.json` are all in `.distignore`) — and are accepted until one of the upgrade paths below is taken. Everything else clears with a plain non-breaking `npm audit fix`.

### State as of August 2026

27 open Dependabot alerts (16 high, 10 moderate, 1 low). Every one is npm, scope `development`, in `package-lock.json`. **Zero Composer alerts and zero runtime alerts** — the packages under `dependencies` in package.json are all clean, so nothing in the released plugin is affected.

**The pin is not the whole story.** Roughly two thirds reach us only through `@wordpress/scripts@27.9.0` — adm-zip, cookie, linkify-it, markdown-it, serialize-javascript, uuid, webpack-dev-server. The rest also arrive via direct dev dependencies that are *not* pinned and could be addressed independently:

| Package | Also pulled in by |
| --- | --- |
| tar-fs, extract-zip, ws | `@wordpress/e2e-test-utils-playwright` |
| minimatch, brace-expansion | `@wordpress/eslint-plugin`, `load-grunt-tasks` |
| lodash | `grunt`, `grunt-wp-i18n` |

Two things worth knowing before anyone tries to drive the count to zero:

-   **`extract-zip@2.0.1` has no fix.** The advisory range is `<= 2.0.1` with `first_patched_version: null`, so no update resolves it — only dropping or overriding the chain would.
-   **The `tar-fs` count overstates the exposure.** Three copies are installed. `3.1.2` already satisfies its advisory; only `3.0.4` and `2.1.1`, both under wp-scripts, are actually vulnerable.

The largest cluster (tar-fs, extract-zip, adm-zip, ws) is puppeteer/lighthouse browser-download machinery, which only runs when unpacking a browser binary fetched from Google's servers. The `webpack-dev-server` advisories need a developer to browse a hostile site while the dev server is running. Both are bounded to a developer machine or CI.

If the alert count needs reducing without touching the pin, the route is `overrides` in package.json forcing safe transitive versions. Overriding puppeteer's `tar-fs` can break the e2e suite, so run the Playwright tests afterwards — and it still will not clear `extract-zip`.

## Upgrade paths (when the time comes)

### Option 1: Raise "Requires at least" to 6.6+

Then wp-scripts can be bumped freely. Check the WP version distribution among actual users first (wordpress.org plugin stats) — WP 6.6 shipped June 2024.

### Option 2: Keep older WP support and ship a react-jsx-runtime polyfill

The approach Jetpack used ([Automattic/jetpack#38424](https://github.com/Automattic/jetpack/issues/38424)), recommended by Gutenberg maintainers ([Gutenberg #62202](https://github.com/WordPress/gutenberg/issues/62202#issuecomment-2156796649), [discussion #64423](https://github.com/WordPress/gutenberg/discussions/64423)).

Add a second webpack entry that bundles React's jsx-runtime (~1 KB) as a global, with React itself still external:

```js
const reactJSXRuntimePolyfill = {
	entry: { 'react-jsx-runtime': { import: 'react/jsx-runtime' } },
	output: {
		filename: 'react-jsx-runtime.js',
		path: path.resolve( __dirname, 'build' ),
		library: { name: 'ReactJSXRuntime', type: 'window' },
	},
	externals: { react: 'React' },
};
```

Then register it only when WordPress has not (i.e. on WP < 6.6, where core does not provide the handle):

```php
add_action( 'init', function () {
	if ( ! wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
		wp_register_script( 'react-jsx-runtime', $url_to_built_file, [ 'react' ], '18.3.0', true );
	}
} );
```

On WP 6.6+ the core-registered script wins; on older versions the shim satisfies the `.asset.php` dependency.
