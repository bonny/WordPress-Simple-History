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

`css/styles.css`, block starting around line 736:

-   `.SimpleHistoryLogitem__keyValueTable` → `display: grid; grid-template-columns: fit-content(min(16em, 45%)) minmax(0, 1fr)`.
-   `> tbody`, `> tbody > tr` → `display: contents` so the cells are the grid items.
-   Key cell keeps `text-align: right` and the grey colour, loses `min-width` and
    `padding-right` (replaced by `column-gap: 1em`), gains `overflow-wrap: anywhere`.
-   Selectors use child combinators so the nested `<table class="diff">` inside a value
    cell (post content diffs) is untouched.

The 45% clamp only bites on narrow viewports (below ~560px content width).

Markup is unchanged. Every producer of this table in core emits two `<td>` per row and
no `colspan`/`<th>`: the two group formatters in `inc/event-details/`, the hand-built rows
in `loggers/class-post-logger.php`. Premium does not use the class at all.

### Accessibility note

`display: contents` on `<tbody>`/`<tr>` historically dropped table semantics from the
accessibility tree in Chromium and Safari. Both fixed this in 2023 (Chromium 118-ish,
Safari 17); Firefox never had the bug. The table carries no `<th>`, caption or headers, so
what a screen reader could lose is "table, 2 columns, N rows" navigation, not labelling.
If this ever matters, the fallback is `role="table"/"row"/"cell"` attributes in the two
formatters, which browsers keep regardless of `display`.

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
