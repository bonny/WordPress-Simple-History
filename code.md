# Simple History code standard

## Code Quality

For comprehensive code quality guidelines, see the **code-quality** skill.

## Quick Reference

### Common Commands

```bash
# Build JavaScript/CSS assets
# IMPORTANT: Always use `npm run build`, not `npx wp-scripts build`.
# npm run build compiles all entry points (index, admin-bar, command-palette).
# npx wp-scripts build only compiles index.js, causing missing asset errors.
npm run build
```

## Whitespace: Let the Code Breathe

Add blank lines before `if` statements, `return` statements, and between logical blocks. Code should "breathe" — don't pack statements tightly together.

```php
// Good: Blank lines before if and return.
$simple_history = Simple_History::get_instance();

if ( $this->is_network_query ) {
    return $simple_history->get_network_events_table_name();
}

return $simple_history->get_events_table_name();

// Avoid: Everything packed together.
$simple_history = Simple_History::get_instance();
if ( $this->is_network_query ) {
    return $simple_history->get_network_events_table_name();
}
return $simple_history->get_events_table_name();
```

## Comments

### Placement: Above the Code

Place comments on their own line above the code they explain, not as trailing comments on the same line. This follows WordPress coding standards and improves readability.

```php
// Good: Comment above the code.
// Return early if user is not authorized.
return $result;

// Avoid: Trailing comment.
return $result; // Return because user is not authorized
```

## Frontend Development

### WordPress JavaScript Compatibility

Simple History supports WordPress 6.3+, so `@wordpress/*` imports must exist in WP 6.3. The full externals-vs-bundled rules live in [src/CLAUDE.md](src/CLAUDE.md), loaded when working in `src/`.

-   **Do NOT upgrade `@wordpress/scripts` beyond 27.x.** Version 28+ makes built assets depend on the `react-jsx-runtime` script handle, which only exists in WP 6.6+ — scripts silently fail to load on WP 6.3–6.5. The leftover npm audit advisories are dev-only and accepted — 27 open as of August 2026, all transitive dev dependencies in `package-lock.json`, none of which ship to users. Note that not all of them come from this pin. See [docs/upgrading-wordpress-scripts.md](docs/upgrading-wordpress-scripts.md) for the full rationale and the two upgrade paths.

### Prefer Web Standards Over JavaScript

Use native HTML elements and CSS before reaching for JavaScript:

-   **`<details>`/`<summary>`** for expand/collapse instead of JS toggles
-   **`<dialog>`** for modals instead of custom JS implementations
-   **CSS `:focus-visible`** for focus states instead of JS focus management
-   **Form validation attributes** (`required`, `pattern`, `type="email"`) before JS validation
-   **CSS Grid/Flexbox** for layouts instead of JS-based positioning

### Accessibility

-   Follow WCAG AA: minimum 4.5:1 contrast ratio for text, 3:1 for large text and UI components
-   Always provide accessible names for interactive elements (`aria-label` on inputs without visible labels, `alt` on images)
-   Don't rely on color alone to convey meaning (add text labels, icons, or patterns)
-   Use semantic HTML (`<button>` for actions, `<a>` for navigation, `<nav>`, `<main>`, etc.)

## Changelog

-   Try to use format from https://keepachangelog.com
-   Also read and try to follow https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/
-   Use the **changelog** skill to add entries to readme.txt

## Git

-   One long-lived branch: `main`. It is always releasable. There is no `develop` branch (dropped 2026-09-05; the release-to-develop merge-back bought nothing at a one-to-three-week release cadence).
-   **Tiny changes** (a typo, changelog wording, a colour value) go straight on `main` as one commit, if they cannot break anything.
-   **Everything else** goes on an `issue-NNN-slug` branch and merges into `main` when the issue is done and looked at. Big or risky work uses a worktree with its own site (`scripts/parallel-dev.sh`), merges `main` into itself every few days, and is either merged early behind the experimental features flag or split if it lives longer than a release cycle.
-   **Releases** cut `release-X.Y.Z` from `main` for the version bump and changelog heading, tag on that branch, merge back into `main` once. See the `release` skill.
-   **Hotfixes** branch from the tag, tag `X.Y.Z+1`, merge into `main`.
-   Run phpstan after making php changes in many files or making a larger change in a single file.
-   Run it as `./vendor/bin/phpstan analyse --memory-limit=2G`. The default limit crashes a parallel worker part-way through with "PHPStan process crashed because it reached configured PHP memory limit", which reads like a code problem but is not.
-   Run it with **no path argument** before committing. Passing a single file skips the check for unmatched `ignoreErrors` entries, and an ignore that no longer matches anything is a hard error here — so a per-file run can pass while the full run fails.
