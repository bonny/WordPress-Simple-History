# Issue 314 – Key-value table: column only as wide as the widest key

Local issue: `314 - Left-align keys in event details key-value table` (the title predates
the decision below; the outcome is right-aligned keys with a tight column, not left-aligned).

## Problem

`.SimpleHistoryLogitem__keyValueTable` (user profile edits, plugin meta, Redirection
changes, option diffs) had `text-align: right; min-width: 8em` on the key cell. Pär's
recollection: right-aligned keys looked good, and the min-width was added so most keys
would fit on one line. Side effect: short keys ("URL", "Nickname", "Source URL") float
8em away from the left edge, so the table looks indented compared with the message above
and the action links below.

## Decision

Keep keys right-aligned, but make the key column exactly as wide as the widest key in
that event, capped so runaway keys wrap instead of pushing values off-screen.

## Why a plain table cannot do it

Tested live in the worktree instance (Chromium) with the 16 example events described
below. Measured key-column widths per variant:

| Variant                                    | Short keys   | Long WooCommerce keys (ex. 03/04)            | Keys next to long values (ex. 05/06/10) |
| ------------------------------------------ | ------------ | -------------------------------------------- | --------------------------------------- |
| Current (`right; min-width 8em`)           | 8em always   | column grows to fit, values pushed right     | 8em                                     |
| `width: 1%` + normal wrapping              | longest word | 526px for an unbreakable key                 | wraps "Target URL" onto two lines       |
| `max-width: 16em` only                     | tight        | capped (Chrome honours it, Firefox does not) | squeezed to ~43px by long values        |
| `table-layout: fixed; width: 11em`         | 11em always  | wraps                                        | 11em                                    |
| **Grid `fit-content(16em) minmax(0,1fr)`** | **tight**    | **capped, wraps**                            | **not squeezed**                        |

Auto table layout hands width to whichever column has the largest content, so a long
value shrinks the key column until short keys wrap; the only table-side cure is a
min-width, which is what caused the indentation. A grid with `fit-content()` for the key
track and `minmax(0, 1fr)` for the value track gives all three properties at once.

## Change

Two commits plus follow-up work; the current state is:

- **Markup**: `<dl class="SimpleHistoryLogitem__keyValueTable"><dt>key</dt><dd>value</dd>…</dl>`,
  produced by the two group formatters, four row formatters, and the hand-built rows in the
  post, ACF and options loggers. The class name keeps "Table" for third-party CSS.
- **CSS** (`css/styles.css` around line 736): grid on the `<dl>` with
  `grid-template-columns: fit-content(min(16em, 45%)) minmax(0, 1fr)`; `dt` right-aligned.
  A `<table>` block below it lays out pre-5.33 markup from third-party loggers the same way.
- **Legacy rows through the post-updated filter**: if a callback appends `<tr>` rows, the
  post logger renders the whole list as a table instead of a `<dl>` (browsers drop `tr`/`td`
  inside a `dl`). Detection is a `<tr` count before vs after the filter.
- **Plain text** (`inc/class-details-text.php`, used by `Event::get_details_text()`):
  parsed with `WP_HTML_Processor`, falling back to a few simple regexes on WordPress
  without it (6.3) or where it refuses the markup (tables before 6.7). Both paths are
  tested against the same fixtures in `tests/wpunit/DetailsTextTest.php`.
- **RSS**: `dl`/`dt`/`dd` added to the feed's kses allowlist.

## Test data

`scripts/playground-mu-plugins/sh-dev-kv-examples.php` registers a dev-only logger
(`SHDevKeyValueExamplesLogger`) and logs 16 example events when an administrator visits
any wp-admin URL with `?sh-dev-kv-examples=1`. Mounted automatically by
`scripts/parallel-dev.sh` (it lives in the mu-plugins dir), excluded from the release zip
by `.distignore` (`/scripts`).

Examples: short keys (profile edit), added-only rows (plugin installed), single row, long
keys with spaces, very long unbreakable key, very long URL value, long token value,
added/removed-only rows, wrapping prose values, 15 rows, diff table, diff table with an
unbreakable line, two groups in one event, empty/whitespace keys, escaped markup and
newlines, numeric values, single value-only row (settings "changed" marker), added-only
short rows (template part), diff table with an empty previous value (post created), diff
table with an empty new value (field cleared).

Gotcha found while writing it: a logger's `messages` array entries must pass through
`__()`, otherwise `load_messages()` (which hooks `gettext`) never learns the key and
`info_message()` silently logs nothing.

## Screenshots

In `.playwright-mcp/` of the main checkout (gitignored): `before-1400.png`,
`variant-B-grid.png` (left-aligned grid), `variant-C-fixed.png`, `variant-B-grid-right.png`
(chosen), `after-480.png`.

## Verified

-   Event log page at 1400px and 480px: keys right-aligned, column hugs the widest key,
    long keys wrap at the cap, diffs render as before.
-   Dashboard widget never renders `__details`, so it is unaffected.
-   phpcs clean on the mu-plugin; stylelint reports nothing new in the edited range
    (`css/styles.css` is not part of `npm run lint:css` and has hundreds of pre-existing
    findings).

## Todo

-   [ ] Pär: eyeball the worktree instance, especially example 03 (right-aligned wrapped
        long keys give a ragged left edge; alternative is `text-align: left` for the
        wrapped case only, not possible in pure CSS) and example 04.
-   [ ] Changelog entry (Changed) before merge.
-   [ ] Remove the untracked copy of `sh-dev-kv-examples.php` from the main checkout's
        `scripts/playground-mu-plugins/` once this branch is merged (it was copied there so
        the running instance, which mounts the main checkout's mu-plugins dir, could load it).

## Blog post images

Before/after pairs of five realistic events (profile edit, plugin installed, post created,
Simple History settings modified, template part updated) are stored in the Obsidian vault at
`attachments/simple-history-key-value-table-{profile-edit,plugin-installed,post-created,settings-modified,template-part}-{before,after}.png`
and embedded in issue 314. The "before" shots were taken on the new build with the old
rules re-applied through an injected stylesheet, so both halves of each pair show the same
event and timestamp.

## Markup switch: `<table>` → `<dl>` (second step, uncommitted)

Decided with Pär after the CSS commit: since the layout is a grid anyway, use the
element that means "key-value pairs" and drop `display: contents` on table rows.

Markup is now `<dl class="SimpleHistoryLogitem__keyValueTable"><dt>key</dt><dd>value</dd>…</dl>`,
`dt`/`dd` as direct children (no per-row wrapper), so they are the grid items directly.
The class name keeps "Table" so third-party CSS and loggers keep working.

Changed producers:

- `inc/event-details/`: both group formatters (wrapper), the four row formatters
  (table row, diff row, image-diff row, raw row). The raw row formatter is used by the
  Debug & Monitor add-on, which therefore gets the new markup without a change of its own.
- `loggers/class-post-logger.php`: 7 hand-built rows + wrapper, `extra_diff_record()`.
  The `simple_history/post_logger/post_updated/diff_table_output` filter now carries
  `<dt>/<dd>` pairs; its docblock says so and warns not to mix in `<tr>`.
- `loggers/class-plugin-acf-logger.php` (5 rows, appended via that filter),
  `loggers/class-options-logger.php` (4 rows in a deprecated method).
- `inc/class-event.php` `get_details_text()`: converts `<dt>/<dd>` to "Label: Value" for
  plain-text output (Copy as text, abilities). The `<tr><td>` branch stays for legacy markup.
- `dropins/class-rss-dropin.php`: `dl`/`dt`/`dd` added to the kses allowlist. Without it
  the feed stripped the new tags and ran keys and values together. Verified in the
  worktree feed: 10 lists, 39 rows, 0 tables.
- `css/styles.css`: grid on the `<dl>`; the `<table>` rules are kept below it as a
  fallback for third-party loggers still emitting the old markup.
- Tests: `Event_Details_*Test`, `PostLoggerFeaturedImageDiffTest` assertions moved from
  `<tr>/<td>` to `<dt>/<dd>`.

Verified: full wpunit suite 1278 tests, one failure (`PostLoggerFeaturedImageDiffTest`)
fixed by updating its assertion and re-run green. phpstan clean, phpcs clean on all
changed PHP files, stylelint clean in the edited CSS range.

### Running wpunit from this worktree

The repo's `compose.yaml` hard-codes container names, so it cannot run next to the main
checkout's stack. An override with `sh314-*` names, port 9314 and main's `vendor/`
bind-mounted (the worktree's `vendor` is a host symlink the container cannot follow)
lives in the session scratchpad as `compose.sh314.yml`. `tests_db` had to be created by
hand in the fresh MariaDB. Stop it with
`docker compose -p sh314 down -v` from the worktree when done.

## Code review follow-ups (2026-09-06)

`/code-review` on the `<dl>` switch found ten things; 1–7 fixed, 8–10 left as follow-ups:

1. Acceptance helper `seeInLogKeyValueTable()` and three Playwright locators selected
   `… tr`; now select the `.SimpleHistoryLogitem__keyValueTable` element.
2. Legacy `<tr>` rows appended through the post-updated filter: the post logger now counts
   `<tr` before/after the filter and renders the whole list as a table if a callback added
   rows (see "Change" above).
3. Changelog entries added (Changed ×2, Fixed ×1).
4. `get_details_text()` dropped a value of `"0"` (`empty()`): fixed, `=== ''` now.
5. No coverage for plain-text conversion: `tests/wpunit/DetailsTextTest.php` runs ten
   fixtures through both converters plus one end-to-end `Event::get_details_text()` check.
6. Screen-reader group titles ran into the first key: both converters drop
   `.screen-reader-text` elements of any tag.
7. The alternation regex is gone. Plain text is now produced by `Details_Text`, which
   walks the markup with `WP_HTML_Processor` (WP ≥ 6.6, falls back when the processor
   reports an error on older tag support) and otherwise uses a handful of single-purpose
   regexes. phpstan needed a scoped `WPCompat.methodNotAvailable` ignore for that file.

Not done: 8 (delete or harden the `<table>` CSS fallback), 9 (a shared row-template helper
for the 20 hand-written `<dt>/<dd>` literals), 10 (dev logger nits: example 15 sorts last,
`__( '{example_title}' )` could be `$logger->info()`).

Also tested afterwards: the post logger's legacy-row fallback and its default `<dl>` output
(`PostLoggerEventDetailsTest`), and the RSS feed's kses allowlist keeping `dl`/`dt`/`dd`,
the legacy table and the diff table (`RSSDropinIntegrationTest`; the allowlist moved out of
the per-item loop into `RSS_Dropin::get_allowed_html()` to make it reachable).

Verified after the fixes: full wpunit suite green (1313 tests, 35 new), phpstan clean,
phpcs clean on all changed PHP. Acceptance and Playwright suites not run here (need the
main docker stack / a Playwright run); their selectors were fixed by inspection.
