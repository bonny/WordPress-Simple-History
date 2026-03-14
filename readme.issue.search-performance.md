# Search Performance Issue

## Problem

REST API requests with the `search` parameter are extremely slow compared to requests without search.

**Fast request (no search):**

```
/wp-json/simple-history/v1/events?page=1&per_page=20&dates=lastdays:30
```

**Slow request (with search):**

```
/wp-json/simple-history/v1/events?page=1&per_page=20&search=updated&dates=lastdays:7
```

## Root Cause

The search functionality in `inc/class-log-query.php` method `add_search_to_inner_where_query()` searches the contexts table using subqueries with `LIKE '%...%'`:

```sql
id IN (SELECT history_id FROM wp_simple_history_contexts AS c WHERE c.value LIKE '%updated%')
```

This is slow because:

1. **No index on `value` column** - The contexts table only has indexes on `context_id` (PRIMARY), `history_id`, and `key`
2. **Leading wildcard prevents index use** - Even with an index, `LIKE '%...%'` cannot use B-tree indexes
3. **Large table** - ~150K+ rows in contexts table (avg ~19-24 rows per event)
4. **Subquery per word** - Each search word triggers a separate full table scan subquery
5. **Searches ALL context values** - Including internal keys like `_user_id`, `_server_remote_addr`, etc. that users never see

### Measured Impact

On a test DB with 17K events and 421K context rows:

-   **Events-only search: 2.4ms**
-   **Context search: 11,764ms** (~5,000x slower)
-   96.5% of context data by size is `detective_mode_*` keys (backtrace data, avg 86K chars per row)

### Table Statistics

| Metric                 | Value   |
| ---------------------- | ------- |
| Total context rows     | 153,768 |
| Unique events          | 8,227   |
| Avg contexts per event | ~19     |

### Current Indexes on `wp_simple_history_contexts`

| Index      | Column     |
| ---------- | ---------- |
| PRIMARY    | context_id |
| history_id | history_id |
| key        | key        |

## How Search Currently Works

The search has three layers:

1. **Main table columns** - `LIKE` on `message`, `logger`, `level` columns (fast, small table)
2. **Context value scan** - `LIKE` on ALL context `value` rows (slow, huge table)
3. **Translated message matching** - PHP-side matching against translated message templates, then precise SQL lookup by `_message_key` (fast, i18n-aware)

### How Messages Are Displayed

What users see in the UI is an **interpolated message**: a translated message template with context values substituted into `{placeholders}`.

Example:

-   Message template (`_message_key` = `plugin_activated`): `Activated plugin "{plugin_name}"`
-   Context value: `plugin_name` = `Simple History Premium`
-   Displayed text: `Activated plugin "Simple History Premium"`

Each event has ~19-24 context rows, but only a few (the placeholder keys) contribute to the visible message. The rest are internal metadata (`_user_id`, `_user_login`, `_server_remote_addr`, etc.).

### i18n / Translation Support

-   **Template matching works in any language** - `match_logger_messages_with_search()` compares search terms against the current locale's translated message templates, then looks up events by `_message_key`
-   **Context values are language-neutral** - Stored as-is (plugin names, post titles, etc.)
-   Searching "Aktiverade" (Swedish) matches the Swedish translation of the template, which is fast
-   Searching "Simple History Premium" (a context value) requires the slow context scan

## Chosen Solution: Placeholder-Scoped Context Search

Instead of scanning ALL context values, only search the context keys that are actually used as `{placeholders}` in message templates.

### How It Works

For **built-in loggers** (and any logger with registered messages):

1. All loggers register their message templates via `get_info()` / `get_messages()`
2. Extract `{placeholder}` names from each template (e.g. `plugin_name`, `theme_name`, `post_title`)
3. Build a set of searchable context keys
4. Change context search from:
    ```sql
    WHERE c.value LIKE '%term%'
    ```
    to:
    ```sql
    WHERE c.key IN ('plugin_name', 'theme_name', 'post_title', ...) AND c.value LIKE '%term%'
    ```
5. The `key` column already has an index, so this dramatically reduces rows scanned

For **SimpleLogger** (catch-all for `do_action('simple_history_log', ...)`):

-   SimpleLogger has no registered message templates
-   Developers can use ad-hoc placeholders: `do_action('simple_history_log', 'Updated: {setting_name}', ['setting_name' => 'My Setting'])`
-   We cannot know these placeholder keys in advance
-   **Fallback**: Keep the full context `LIKE` scan but only for events where `logger = 'SimpleLogger'`
-   SimpleLogger events are typically a small fraction of total events, so this is acceptable

### Why This Approach

-   **No schema changes** - Works with existing tables and indexes
-   **Uses existing `key` index** - Adding `WHERE key IN (...)` leverages the index on the `key` column
-   **Covers 95%+ of events** - Built-in loggers produce the vast majority of events
-   **Preserves full search for SimpleLogger** - No loss of functionality for custom logging
-   **Works with both MySQL and SQLite** - No database-specific features needed

### Not 100% Guaranteed Faster

Edge cases where performance may not improve:

-   Sites with many SimpleLogger events (heavy `do_action('simple_history_log')` usage)
-   Very small datasets where the current brute-force scan is already fast
-   Searches matching many logger templates, generating many OR clauses

Should benchmark before/after with `EXPLAIN` to verify.

## Benchmark Results

Test environment: 103,444 events, 3,079,234 context rows, MariaDB, Docker (local).
All times are median of 3 runs via REST API (includes PHP + JSON serialization overhead).

### Get Events (no search)

No difference between experimental ON/OFF — search optimizations don't affect non-search queries.

| Query                        | Time  |
| ---------------------------- | ----- |
| Default (14 days, 15 events) | 1.8s  |
| Default + `skip_count_query` | 1.0s  |
| Last 30 days                 | 3.9s  |
| All dates                    | 11.3s |

### Search Queries

| Query                            | Experimental OFF | Experimental ON | Speedup  |
| -------------------------------- | ---------------- | --------------- | -------- |
| "api request" (14 days)          | 12.5s            | 1.3s            | **~10x** |
| "api request" (14d) + skip_count | 6.3s             | 0.4s            | **~17x** |
| "api request" (30 days)          | 23.7s            | 4.6s            | **~5x**  |
| "api request 400" (14 days)      | 12.4s            | 1.7s            | **~7x**  |
| "updated plugin" (30 days)       | 24.0s            | 4.8s            | **~5x**  |
| "updated plugin" (all dates)     | 54.3s            | 4.7s            | **~12x** |
| "login" (14 days)                | 17.7s            | 5.9s            | **~3x**  |
| "api" single word (14 days)      | 1.9s             | 0.8s            | **~2x**  |

### Metadata Search (always unscoped, same ON/OFF)

| Query               | Time  |
| ------------------- | ----- |
| "192.168" (14 days) | 11.5s |
| "admin@" (14 days)  | 12.1s |

### Key Takeaways

-   **Search is 3x–17x faster** with experimental features enabled, depending on query complexity and date range.
-   **Best case**: "api request" with `skip_count_query` went from 6.3s → 0.4s (17x faster).
-   **Worst case**: Single common word "login" still improved from 17.7s → 5.9s (3x faster).
-   **Wider date ranges benefit more**: "updated plugin" all dates went from 54.3s → 4.7s (12x).
-   **No regression** for non-search queries — identical performance with feature ON or OFF.
-   **Metadata search** (unscoped context scan) is consistently ~12s regardless of experimental flag, confirming that the old search behavior was bottlenecked by context scanning.
-   **`skip_count_query`** provides an additional ~2-3x speedup on top of scoped search (0.4s vs 1.3s for "api request" 14d).

## Review Findings

### 1. Broaden the Fallback Beyond SimpleLogger

Not just SimpleLogger needs the full context scan fallback. Any logger with an empty messages array should get it too. Example: `Plugin_ACF_Logger` has no `messages` key in `get_info()` (it only adds context to PostLogger events). Third-party loggers registered via `register_logger()` could also have empty messages.

**Solution**: Build the fallback logger list dynamically — any logger where `get_messages()` returns an empty array gets included in the fallback set.

### 2. Use Two Separate Subqueries (Not Combined)

A combined subquery with OR inside it prevents MySQL from using the `key` index on the scoped branch — the query planner treats the whole thing as needing a full scan. Two separate subqueries are better because the planner can use the `key` index independently on the first branch:

```sql
-- Branch 1: scoped by placeholder keys (uses key index, fast)
id IN (
  SELECT history_id FROM contexts AS c
  WHERE c.key IN (...placeholder_keys...)
  AND c.value LIKE '%term%'
)
-- Branch 2: fallback for loggers without messages (full scan, but small event set)
OR id IN (
  SELECT history_id FROM contexts AS c
  INNER JOIN main ON c.history_id = main.id
  WHERE main.logger IN ('SimpleLogger', ...other_empty...)
  AND c.value LIKE '%term%'
)
```

Verify with `EXPLAIN` during benchmarking.

### 3. Narrowed Search Scope is Intentional

After optimization, searching non-placeholder context values (role names, IP addresses, diff content, `_user_email`, etc.) will no longer work via the default search. This is an acceptable trade-off for performance. Context search could be offered as an advanced filter (see UX section below).

### 4. Multi-Word Search Cross-Key Matching

Currently "john updated" could match "john" in `_user_email` context and "updated" in a message template. After optimization, cross-key matches on non-placeholder keys will be lost. Acceptable trade-off.

### 5. Pre-existing `ltrim` Bug

At lines 2207 and 2304:

```php
$str_sql_search_words = ltrim( $str_sql_search_words, ' AND ' );
```

`ltrim` with a character mask strips individual characters from the set `{' ','A','N','D'}`, not the substring `" AND "`. Works by accident because column names (`message`, `logger`, `level`) don't start with those characters. A latent correctness bomb — if a column starting with A/N/D were added, it would silently mangle the SQL.

**Fix**: Since we're rewriting the search methods anyway, replace the `ltrim` + string-concatenation assembly with `implode`-based construction. Same applies to the trailing `preg_replace('/ AND $/', ...)` at line 2230.

### 6. Third-Party PSR-3 Style Loggers

External loggers using `$this->info('raw text', $context)` without `_message_key` store raw text in the `message` column, which is already searched via LIKE. Their context values won't be searched under the optimization, but the message column match covers the main use case.

### 7. No Caching Needed

Placeholder extraction is ~200 strings with a simple regex — microseconds. Use a static variable if both `search` and `exclude_search` call it in the same request.

### 8. `exclude_search` Must Use Identical Scoping

The `add_exclude_search_to_inner_where_query()` method wraps everything in `NOT (...)`. If it uses different scoping logic than the search method, you get a bug: `search=foo` finds event X but `exclude_search=foo` fails to exclude event X. Both methods must share the same placeholder key set and fallback logger list. Extract a shared helper method.

### 9. Pre-existing Edge Cases to Fix

-   **Empty search string**: `add_search_to_inner_where_query()` checks `isset($args['search'])` but not `empty()`. An empty string proceeds into the loop, `preg_split` returns `['']`, generating `LIKE '%%'` (matches everything). The exclude method correctly checks both. Fix the search method to match.
-   **Empty words in multi-word search**: `preg_split` can produce empty strings. Add per-word empty check inside the loop.
-   **Word count cap**: No limit on search words — a 20-word search generates 20 subqueries. Add a defensive cap (e.g. 10 words).
-   **Third-party loggers without messages**: Any logger where `get_messages()` returns `[]` that also logs its own events (not just adding context to other loggers' events) would lose context search coverage unless included in the fallback set. The dynamic fallback list handles this correctly.

## UX: Search Label and Advanced Context Search

### Main Search Label

Rename **"Containing words"** → **"Event text"**.

-   "Event" matches the plugin's own terminology ("Search events" button, "568 events" count, event log)
-   Noun-form label consistent with WordPress admin conventions ("Post title", "Excerpt")
-   Avoids "Message" which is overloaded in WordPress (post type, notifications, contact forms)
-   Translates cleanly — two words, no verb, no metaphor
-   Alternative "Messages containing" is grammatically incomplete; "Search messages" conflicts with the "Search events" button

### Help Text

Add a one-line help text beneath the label:

```
Searches the text visible in each event.
```

This is critical for communicating the narrowed scope — without it, users who previously searched for emails or IPs will think the plugin is broken when they get zero results.

### Placeholder Text

Add placeholder to the search input:

```
plugin name, post title, username
```

-   No quotes (quoted phrases look like exact-match syntax)
-   No `e.g.` prefix (WordPress admin convention uses just the example value)
-   Three realistic examples that demonstrate the scope: things users actually see in events

### Zero-Results Nudge

When search returns zero results, show a message:

```
No events found. To search email addresses, IP addresses, or other internal data,
use "Event metadata" under Show filters.
```

This prevents the silent-result-drop confusion and provides a recovery path. Without this, the behavioral change will generate support tickets.

### Advanced Context/Metadata Search

The expanded filters already have a "Context" field (`ExpandedFilters.jsx`) with key:value syntax. Rename and improve it:

| Current                                         | Recommended                                                                                                                     |
| ----------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Label: "Context"                                | Label: **"Event metadata"**                                                                                                     |
| Long prose help text with `_user_id`, `_sticky` | Short: "Search internal event data using key:value pairs, one per line. Example: `post_type:page` or `plugin_name:woocommerce`" |
| No context-setting text                         | Add: "For advanced searches not covered by the fields above."                                                                   |

"Event metadata" pairs naturally with "Event text" and creates a coherent mental model: visible text vs. underlying data. "Context" is a developer term most admins won't recognize.

### Hiding Context Search Behind "Show search options"

This is a valid trade-off:

-   The field already exists in the expanded section
-   Auto-expand logic already fires when context filters are active (URL params preserved)
-   Users who need `_user_id:1` are in a diagnostic mindset and expect advanced fields behind a click
-   The 95% of users searching "user deleted post" shouldn't see a monospace key:value textarea

Consider renaming "Show search options" → **"Show filters"** / **"Hide filters"** — "search options" sounds like preferences, not additional filter inputs. Parallels the existing "Clear filters" button.

### Accessibility Fix (While We're Touching This Code)

The current search input uses a `<div>` as label, not a `<label>` element with `htmlFor`. This fails WCAG 1.3.1 and 4.1.2. Since we're renaming the label anyway, convert to a proper `<label htmlFor="...">` and give the input a stable `id`. Same issue exists for the context textarea in `ExpandedFilters.jsx`.

### Changelog Note

The behavioral change (narrower default search scope) must be documented in the changelog. Users and developers integrating with the REST API `search` parameter need to know.

## TODO

### PHP / SQL

-   [x] Dashboard widget: add `lastdays:30` date filter — previously queried all events with no date restriction, causing full table scan
-   [x] Dashboard widget: skip total count — exposed `skip_count_query` via REST API, dashboard now uses it
-   [x] Dashboard widget: add "View stats" link in stats bar (replaced 30-day stat to keep widget compact)
-   [x] Benchmark current search query performance (EXPLAIN)
-   [x] Extract placeholder keys from all registered logger messages (`get_searchable_context_keys()`)
-   [x] Build dynamic list of "loggers without messages" for fallback (`get_loggers_without_messages()`)
-   [x] Extract shared helper for placeholder keys + fallback logger list (used by both search and exclude_search)
-   [x] Modify `add_search_to_inner_where_query()` to scope context search by key
-   [x] Use two separate subqueries (scoped + fallback), not combined
-   [x] Update `add_exclude_search_to_inner_where_query()` with identical scoping logic
-   [x] Use static variable for placeholder keys if both search and exclude_search are used
-   [x] Replace `ltrim`/`preg_replace` string assembly with `implode`-based construction
-   [x] Add `empty()` check for search string (match existing exclude_search guard)
-   [x] Add per-word empty string check inside the loop (`get_sanitized_search_words()`)
-   [x] Add word count cap (e.g. 10 words) as defensive measure (`get_sanitized_search_words()`)
-   [x] Benchmark new query performance (EXPLAIN both branches)
-   [x] Add WP-CLI benchmark command for default event retrieval (no search, matching dashboard/events page requests)
-   [ ] Test with translated site (non-English)
-   [ ] Test with SimpleLogger events containing placeholders
-   [ ] Test with third-party/external loggers

### UX / Frontend

-   [ ] Rename "Containing words" → "Event text"
-   [ ] Add help text: "Searches the text visible in each event."
-   [ ] Add placeholder: `plugin name, post title, username`
-   [ ] Add zero-results nudge pointing to Event metadata in advanced filters
-   [ ] Rename "Context" → "Event metadata" in expanded filters
-   [ ] Update context help text with shorter, example-based version
-   [x] Rename "Show search options" → "Show filters" / "Hide filters"
-   [ ] Fix `<div>` labels → `<label htmlFor>` (a11y, WCAG 1.3.1 / 4.1.2)
-   [x] Add changelog entry noting the behavioral change

## Files Involved

-   `inc/class-log-query.php` - `add_search_to_inner_where_query()`, `add_exclude_search_to_inner_where_query()`, and `match_logger_messages_with_search()`
-   `inc/class-helpers.php` - `interpolate()` method (line 122)
-   `inc/class-wp-rest-events-controller.php` - REST API endpoint
-   `loggers/class-logger.php` - `get_messages()` method
-   `loggers/class-simple-logger.php` - Catch-all logger without message templates
-   `loggers/class-plugin-acf-logger.php` - Example of non-SimpleLogger with no messages
-   `inc/services/class-loggers-loader.php` - Logger registration and instantiation
-   Database table: `wp_simple_history_contexts`

## Related

-   Search is also used by WP-CLI commands in `inc/services/wp-cli-commands/`
-   Consolidated from local issue "Text Search Slow on Context Data" (archived)
-   Feature request origin: nathan@nerdrush.com wanted to search WooCommerce context data like `regular_price_new`
-   Related local issues: "Saved Searches Premium Feature", "Post Diff Use Patches Instead of Full Content"
