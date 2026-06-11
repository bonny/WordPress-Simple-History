# Upgrading @wordpress/scripts (and why it is pinned to 27.x)

`@wordpress/scripts` is intentionally pinned to `^27.0` in package.json. Do not bump it to 28+ — not manually, not via `npm audit fix --force`, not via dependabot.

## Why the pin exists

Version 28 switched JSX compilation to React's automatic runtime. Built assets then list `react-jsx-runtime` in their `.asset.php` dependency arrays, and that script handle only exists in WordPress 6.6+. On WP 6.3–6.5 — which this plugin supports ("Requires at least: 6.3") — the scripts silently fail to enqueue: no error, the admin UI just never loads.

This is expected behavior, not a bug. The official guidance in the [JSX in WordPress 6.6 dev note](https://make.wordpress.org/core/2024/06/06/jsx-in-wordpress-6-6/) is to keep the old build tooling until the plugin's minimum WP version is 6.6+.

## Accepted trade-off

Some npm audit advisories (~15 as of June 2026) are only fixable via a wp-scripts major bump. They are dev-only — build toolchain, nothing ships to wordpress.org users (`node_modules/`, `src/`, `scripts/`, `vendor/` are all in `.distignore`) — and are accepted until one of the upgrade paths below is taken. Everything else clears with a plain non-breaking `npm audit fix`.

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
