# JavaScript source

## WordPress JavaScript Compatibility

Simple History supports WordPress 6.3+, which means the `@wordpress/*` packages available in wp-admin vary across versions. Important rules:

-   **`@wordpress/components`, `@wordpress/element`, `@wordpress/i18n`, etc. are NOT bundled** — they are loaded as externals from the host WordPress. The `@wordpress/scripts` build tool auto-extracts imports into a `.asset.php` dependency array, and WordPress provides them as global scripts.
-   **Never adopt new `@wordpress/*` packages or components that only exist in recent WordPress versions** unless you verify they are available in WordPress 6.3. For example, `@wordpress/ui` (introduced in Gutenberg 22.9 / WP 7.0+) would break on older installs.
-   **Packages listed in `dependencies` in package.json** (e.g., `@wordpress/icons`, `clsx`, `date-fns`) ARE bundled into the plugin's JS build and are safe to use regardless of WP version.
-   **Packages listed only in `devDependencies`** (e.g., `@wordpress/scripts`, `@wordpress/eslint-plugin`) are build tools, not runtime code.
-   When considering a new `@wordpress/*` import, check whether `@wordpress/scripts` will treat it as an external (loaded from WP) or bundle it. Externals must exist in the minimum supported WP version.
-   If you need a component from a newer `@wordpress/*` package, either build a custom equivalent or conditionally load it with version detection.

The `@wordpress/scripts` version pin is a hard constraint — see code.md.
