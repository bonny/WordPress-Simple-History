# Compact JSON Diff Storage for Post Content

Branch: `compact-json-diff-storage`

## Overview

When a post is edited, Simple History stores the full old and new `post_content` in the context table. For large Gutenberg posts this can be 50-100 KB+ per edit. This feature uses `jfcherng/php-diff` to store a compact JSON diff instead, typically reducing storage significantly.

Gated behind `Helpers::experimental_features_is_enabled()` for both storage and display.

## How it works

1. **Storage**: When `post_content` changes and experimental features are enabled, compute a JSON diff using `DiffHelper::calculate()` with the `JsonHtml` renderer. Store as `post_content_diff` context key.
2. **Display**: Decode the stored JSON and render via `SideBySide` renderer into the same visual format as the existing WP text diff.
3. **Fallback**: On any error during diff calculation, fall back to full content storage.

## Library

-   `jfcherng/php-diff` v6.16 (PHP 7.4 compatible)
-   Namespace-prefixed via Strauss to `Simple_History\Vendor\Jfcherng\Diff\*`
-   Committed in `vendor-prefixed/` directory

## Current differ/renderer options

```php
// Differ options
'context'           => 1,    // Match WP's 1-line context
'ignoreLineEndings' => true,
'ignoreWhitespace'  => true,

// Renderer options
'detailLevel'       => 'word',  // Word-level ins/del highlighting
'outputTagAsString' => true,    // Readable tags in stored JSON
```

Content is normalized with `normalize_whitespace()` before diffing, matching WP's built-in `text_diff()` behavior.

## Size findings

Tested with the WordPress 6.4 block test data (`64-block-test-data.xml`, 76 block types). The "Cover" post has ~46 KB of `post_content`.

### Event 18834 — Small edit (one word change)

| Storage                 | Size          |
| ----------------------- | ------------- |
| Old format (prev + new) | 90.9 KB       |
| Compact diff            | **638 bytes** |
| **Savings**             | **99.3%**     |

### Event 18835 — Moderate edit (two changes)

| Storage                 | Size       |
| ----------------------- | ---------- |
| Old format (prev + new) | 90.1 KB    |
| Compact diff            | **3.6 KB** |
| **Savings**             | **96%**    |

### Event 18832 — First edit after import (Gutenberg reformats content)

| Storage                 | Size             |
| ----------------------- | ---------------- |
| Old format (prev + new) | 90 KB            |
| Compact diff            | **103 KB**       |
| **Savings**             | **-14% (worse)** |

The first edit after import is pathological: Gutenberg re-serializes the entire post content on save (reformats whitespace, attribute ordering, etc.), making nearly every line a "change". The JSON diff becomes larger than two full copies because:

-   `rep` blocks store both old AND new lines
-   `JsonHtml` HTML-escapes content (`<` -> `&lt;`, `"` -> `&quot;`), inflating HTML-heavy Gutenberg markup
-   Word-level `<ins>`/`<del>` tags embedded in lines add more bytes
-   JSON structural overhead (keys, brackets)

## Known issues / TODO

-   [ ] **Size guard**: If the compact diff is larger than old+new combined, fall back to full content storage. This handles the Gutenberg-reformats-everything case.
-   [ ] During testing phase, both compact diff AND full content are stored (for comparison). Remove full content storage once the feature is validated.
-   [ ] The diff algorithm differs from WP's built-in `Text_Diff` — jfcherng detects block moves more accurately (shows as insert+delete) while WP shows positional changes. Both are valid but look different visually.
-   [ ] The stored JSON format can be rendered by any of jfcherng's renderers (`SideBySide`, `Inline`, `Unified` for patch files, etc.) — potential for "Download patch" feature.

## Options considered but not implemented

-   **`JsonText` instead of `JsonHtml`**: Would skip HTML-escaping for ~10-15% smaller storage, but `renderArray()` on the HTML renderers assumes pre-escaped content. Would break display.
-   **`outputTagAsString: false`**: Integer tags save ~5% but hurt readability when inspecting the database. Not worth the tradeoff.
-   **`detailLevel: 'none'`**: Would reduce storage but loses word-level inline highlighting. The word-level detail is valuable for spotting small changes.
-   **`context: 0`**: Would lose all context lines, making diffs unreadable.

## Files changed

| File                            | Change                                     |
| ------------------------------- | ------------------------------------------ |
| `composer.json`                 | Added php-diff dependency + Strauss config |
| `index.php`                     | Load Strauss autoloader                    |
| `phpcs.xml.dist`                | Exclude vendor-prefixed                    |
| `inc/class-helpers.php`         | `render_json_diff_to_html()` method        |
| `loggers/class-post-logger.php` | Storage + display logic                    |
| `css/styles.css`                | Side-by-side diff styles                   |
| `.gitattributes`                | Mark vendor-prefixed as linguist-vendored  |
| `readme.txt`                    | Changelog entry                            |
| `vendor-prefixed/`              | Namespace-prefixed jfcherng library        |
