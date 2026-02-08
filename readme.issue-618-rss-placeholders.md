# Issue #618: RSS feed contains unresolved placeholders

## Problem

The RSS feed output contains literal template placeholders (`{edit_link}` and `{attachment_parent_edit_link}`) instead of resolved URLs, causing:

-   RSS validation failures
-   Feed readers rejecting the feed (e.g., Slack RSS integration)

## Root Cause

1. **RSS feed context has no authenticated user**: When the RSS feed is generated, WordPress doesn't have a logged-in user context
2. **`get_edit_post_link()` returns null**: This function checks user capabilities and returns `null`/`false` when no user is authenticated
3. **Interpolation skips null values**: The `helpers::interpolate()` method only replaces string/numeric values (line 173-176 in `inc/class-helpers.php`), so `null` values are skipped
4. **Placeholders remain unresolved**: The literal placeholders `{edit_link}` and `{attachment_parent_edit_link}` remain in the HTML output

## Affected Loggers

-   `class-media-logger.php` - Uses `{edit_link}` and `{attachment_parent_edit_link}`
-   Potentially other loggers that use `get_edit_post_link()` in RSS context

## Solution Strategy

### Option 1: Manually construct URLs in RSS context

**Approach**: Detect RSS feed context and manually construct admin URLs instead of using `get_edit_post_link()` which requires authentication.

**Pros**:

-   Clean solution that works regardless of user context
-   URLs will always be valid (even if user can't access them)
-   Minimal code changes

**Cons**:

-   Need to detect RSS context
-   URLs might not be accessible to RSS feed readers (but that's expected)

### Option 2: Use a filter to ensure strings

**Approach**: Filter `simple_history/logger/interpolate/context` in RSS feed to convert null edit links to empty strings or constructed URLs.

**Pros**:

-   Centralized fix
-   Works for all loggers automatically

**Cons**:

-   Empty strings would create broken links (`href=""`)
-   Less explicit than Option 1

### Option 3: Remove links in RSS context

**Approach**: Detect RSS context and use message templates without links.

**Pros**:

-   Simplest solution
-   No broken links

**Cons**:

-   Loses useful link information
-   Less user-friendly

### Option 4: Post-process RSS output with WP_HTML_Tag_Processor (Recommended)

**Approach**: After loggers produce their output, clean up problematic `<a>` tags in `class-rss-dropin.php` before writing the feed. Use WordPress's `WP_HTML_Tag_Processor` (available since WP 6.2; Simple History requires 6.3) to find and fix broken links without regex.

Two types of broken links exist in the output:

1. **Unresolved placeholders**: `<a href="{edit_link}">text</a>` — href contains literal `{...}` placeholder
2. **Empty href**: `<a href="">text</a>` — href is empty string (from loggers that coerce null to `''`)

For both cases, the `href` attribute is simply removed. An `<a>` without `href` is valid HTML per the spec and renders as plain text — no link behavior, no underline. This keeps the solution entirely within `WP_HTML_Tag_Processor` with no regex needed.

```php
/**
 * Clean broken links from HTML output.
 *
 * Finds <a> tags with unresolved {placeholder} hrefs or empty hrefs
 * and removes the href attribute. An <a> without href is valid HTML
 * and renders as plain text with no link behavior.
 *
 * @param string $html HTML output from a logger.
 * @return string Cleaned HTML with broken links neutralized.
 */
private function clean_broken_links( $html ) {
    $processor = new \WP_HTML_Tag_Processor( $html );

    while ( $processor->next_tag( array( 'tag_name' => 'a' ) ) ) {
        $href = $processor->get_attribute( 'href' );

        $is_empty       = $href === '' || $href === null;
        $is_placeholder = is_string( $href ) && str_contains( $href, '{' );

        if ( ! $is_empty && ! $is_placeholder ) {
            continue;
        }

        $processor->remove_attribute( 'href' );
    }

    return $processor->get_updated_html();
}
```

Then in `output_rss()`, wrap each logger output call:

```php
$header_output  = $this->clean_broken_links(
    $this->simple_history->get_log_row_header_output( $row )
);
$text_output    = $this->clean_broken_links(
    $this->simple_history->get_log_row_plain_text_output( $row )
);
$details_output = $this->clean_broken_links(
    $this->simple_history->get_log_row_details_output( $row )
);
```

**Pros**:

-   **Single fix point** in `class-rss-dropin.php` — no per-logger changes needed
-   **Future-proof** — any new logger with the same pattern is automatically handled
-   Catches both unresolved `{placeholder}` and empty `href=""` patterns
-   No admin URL leakage in feeds (links are neutralized, not constructed)
-   **Zero regex** — uses only `WP_HTML_Tag_Processor`, WordPress's own HTML5-spec tokenizer
-   `WP_HTML_Tag_Processor` available since WP 6.2 (Simple History requires 6.3)
-   Lightweight — streaming tokenizer with negligible overhead
-   `<a>` without `href` is valid HTML — renders as plain text, no link behavior

**Cons**:

-   Neutralizes links rather than providing valid URLs (but RSS readers can't use admin links anyway)
-   Leaves the `<a>` tag in the markup (without `href`) rather than fully removing it — semantically fine but slightly verbose
-   Doesn't fix the underlying logger issue for other non-RSS unauthenticated contexts (REST API exports, WP-CLI) — but the same helper can be reused if needed later

## Recommended Implementation (Option 4)

### Step 1: Add `clean_broken_links()` method to RSS dropin

Added to `dropins/class-rss-dropin.php`. See code sample in Option 4 description above.

### Step 2: Apply cleanup in `output_rss()`

Wrap the logger output calls in the RSS output loop:

```php
$header_output  = $this->clean_broken_links(
    $this->simple_history->get_log_row_header_output( $row )
);
$text_output    = $this->clean_broken_links(
    $this->simple_history->get_log_row_plain_text_output( $row )
);
$details_output = $this->clean_broken_links(
    $this->simple_history->get_log_row_details_output( $row )
);
```

### Step 3: Testing

1. **Unit tests** (12 tests in `tests/wpunit/RSSDropinTest.php`):
    - `<a href="{edit_link}">text</a>` → href removed, text kept
    - `<a href="">text</a>` → href removed
    - `<a href="https://example.com">text</a>` → unchanged
    - `<a>text</a>` (no href) → unchanged
    - Mixed valid and broken links → only broken ones cleaned
    - Nested HTML preserved: `<a href="{x}"><strong>text</strong></a>` → `<a><strong>text</strong></a>`
    - Other attributes (`class`, `title`) preserved when href removed
    - Empty string input → empty string
    - Real patterns from media logger and user logger
2. **Manual testing**:
    - Upload attachment to a post
    - Access RSS feed
    - Verify no `{placeholder}` or empty `href` in output
    - Validate feed at https://validator.w3.org/feed/

## Implementation Checklist

-   [x] Add `clean_broken_links()` method to `class-rss-dropin.php`
-   [x] Wrap logger output calls in `output_rss()` with cleanup
-   [x] Add unit tests for `clean_broken_links()` (12 tests, all passing)
-   [ ] Test manually with attachment uploads
-   [ ] Validate RSS feed output
-   [ ] Update changelog

## Files Modified

1. `dropins/class-rss-dropin.php` — Added `clean_broken_links()` method + wrapped output calls
2. `tests/wpunit/RSSDropinTest.php` — Added 12 tests for `clean_broken_links()`

## Notes

-   RSS readers can't use admin links anyway, so neutralizing them is the right trade-off
-   `WP_HTML_Tag_Processor` is available since WP 6.2; Simple History requires WP 6.3
-   The same `clean_broken_links()` helper could be reused for other unauthenticated contexts (REST API, WP-CLI export) if needed in the future
-   This fix is purely at output time — loggers continue to work as before in the admin UI where users are authenticated and links resolve normally
-   An `<a>` without `href` is valid HTML per the spec — renders as plain text with no link behavior
