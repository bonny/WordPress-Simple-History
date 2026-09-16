# Event log Table view — design

Date: 2026-09-16
Issue: 240 - Event log views — list, compact, table, calendar (phase 2)
Branch: `issue-240-table-view`
Follows: `2026-09-15-event-log-compact-view-design.md` (phase 1, merged as 0ffa7325)

## Goal

Add a **Table view** to the event log: a dense, sortable, Excel-style rendering of the same events, with row selection that feeds export. The table is a Premium feature. The free plugin shows a preview of it, so the switcher has a fourth button that explains itself.

Two things have to be true at once. Free users keep a complete log viewer, and Premium becomes the obvious choice for anyone who audits a busy site.

## Why this shape

The free/premium line here was decided on measured conversion, not taste. From issue 280 (2026-08-12 → 2026-09-14, GA4 joined to Lemon Squeezy orders):

| Touchpoint                      | Sessions | Sales |     Conv. |
| ------------------------------- | -------: | ----: | --------: |
| `alerts_preview_banner`         |       32 |     5 | **15.6%** |
| `premium_sidebar_compact`       |       56 |     5 |      8.9% |
| `premium_header_cta`            |       58 |     3 |      5.2% |
| `premium_events_endofresults`   |       48 |     2 |      4.2% |
| `premium_global_modal` (export) |      167 |     7 |     4.19% |
| all `wpadmin`                   |    1,090 |    26 |      2.4% |
| `premium_user_card`             |      301 |     2 | **0.66%** |

The best and the worst touchpoints in the plugin are both disabled-form previews of a premium feature, so "do previews convert" is the wrong question. What separates them:

|                      | Alerts teaser, 15.6%          | User card teaser, 0.66%               |
| -------------------- | ----------------------------- | ------------------------------------- |
| How the user arrived | clicked **Alerts** on purpose | hovered a username for something else |
| What they wanted     | alerts                        | the username                          |
| How much is shown    | the whole feature, full page  | a small blurred strip                 |
| Data in the preview  | staged samples                | their own data, blurred               |

Intent is the variable. The alerts preview wins because the user asked for that feature by navigating to it; the user card loses because it interrupts someone mid-task, and it burns 28% of all in-plugin traffic for 8% of in-plugin sales doing it.

Clicking **Table** in the view switcher is a deliberate act, the same shape as clicking the Alerts tab. So the Table preview is modelled on `Alerts_Settings_Page_Teaser` line for line, including its use of staged sample data rather than the reader's own events.

Caveat to keep in mind when reading the numbers back: 15.6% is 5 sales from 32 sessions. Directionally strong, statistically thin.

## Scope

In:

-   Graduating the view switcher and Compact view out of experimental features.
-   `orderby` / `order` in `Log_Query`, the REST events controller, and the `simple-history list` WP-CLI command.
-   A `TablePreview` component in core: inert markup, staged sample rows, banner CTA.
-   The Slot that lets Premium replace the preview with the real table.
-   In Premium: the working table — sortable headers, row selection, bulk bar into the existing `ExportModal`, column configuration, virtual scrolling.

Out:

-   **The filtered context-loading optimisation** (phase 3 on the issue). At 100 rows a page it is not needed. It changes `add_contexts_to_log_rows()` for both database engines and deserves its own spec and benchmark.
-   **Calendar view** (phase 4). Still parked; the surviving work is on `origin/worktree-issue-239-calendar-view`.
-   **Sorting by user or IP address.** See "Columns" below for why the schema will not give it cheaply.
-   **A generic view registry.** Phase 1 decided against one and nothing here needs it. Table adds a third button to the existing array.

## Decisions

| Question                   | Decision                                                           |
| -------------------------- | ------------------------------------------------------------------ |
| Which view first           | Table, ahead of Calendar, heatmap, map and grouped-by-object       |
| Free or premium            | Premium, with a preview in core                                    |
| What the preview shows     | Staged sample rows, not the reader's own events                    |
| Where the table code lives | Entirely in Premium. Core carries no table logic                   |
| How sorting works          | Server-side `orderby`, whole result set, not client-side on a page |
| Sortable columns           | `date`, `level`, `logger`, `message`. Not user, not IP             |
| v1 premium capabilities    | Sorting, select → export, column config, virtual scrolling         |
| Campaign slug              | `premium_table_view`                                               |

### Why the table is not in core, even frozen

An earlier draft had one table component in core with a capabilities object that Premium unlocked. That is the trialware pattern the WordPress.org guidelines prohibit: working code, shipped, withheld pending payment. The distinction the guidelines draw is about where the logic lives, not what the user sees. A preview with no interaction logic in it is a teaser and is explicitly allowed; a real table with its handlers switched off is a locked feature.

Pär reached the same conclusion on the issue back in March, before any of this was designed: "show non-interactive table preview-isch that is perhaps using real data but is not a real table (so we don't misuse any wordpress.org guidance)."

Moving the table to Premium also takes the ~18 kB TanStack dependency out of the core bundle, so the lazy-loading the earlier draft needed disappears.

## Core work

Four pieces. Each ships on its own, in this order.

### 1. Graduate the view switcher

`EventsControlBar.jsx:166` hides `EventsViewToggle` unless experimental features are on, and `EventsGui.jsx:354` forces `'detailed'` when the flag is off. Until both go, the switcher is invisible to almost everyone and a Table teaser reaches nobody.

-   Remove the `experimentalFeaturesEnabled &&` wrapper around `EventsViewToggle`.
-   Remove the `isExperimentalFeaturesEnabled ?` ternary around `eventsView`, leaving `urlEventsView ?? storedEventsView`.
-   Drop the Compact view row from the experimental features page.
-   Changelog entry under **Changed**, framed as a graduation, not as a new feature.

Nothing else in this spec is visible to anyone until this lands.

### 2. Sorting

Today every query in `Log_Query` is hardcoded `ORDER BY date DESC, id DESC` — ten places. The table needs the whole result set sorted, not the loaded page, because sorting 100 rows out of 47,000 and calling it "sorted by user" is the kind of thing someone notices once and never trusts again.

The change is smaller than that count suggests, because `ungrouped` already exists as a `Log_Query` argument (line 136) and as a REST parameter (`class-wp-rest-events-controller.php:591`), and the ungrouped path is its own method.

-   **Target `query_overview_simple()`** (`inc/class-log-query.php:237`–378) and its two `ORDER BY` clauses, at lines 276 and 325. This is the ungrouped query, which is also the only path SQLite ever takes.
-   **Leave `query_overview_mysql()` alone.** The grouped query builds occasion runs with `@a` / `@counter` session variables that depend on rows arriving in date order. Table view always requests `ungrouped => true`, so it never reaches that method.
-   **New args in `prepare_args()`**: `orderby`, default `date`; `order`, default `DESC`.
-   **Allow-list `orderby`** to `date`, `id`, `level`, `logger`, `message`. Anything else falls back to `date` rather than erroring, the same way an out-of-range `posts_per_page` is clamped today. The value is never interpolated into SQL — it maps through a `match` to a literal column name.
-   **Always append `id` as the tiebreaker**, in the same direction. Without it, pagination over a low-cardinality column like `level` can show the same row on two pages.
-   **Sorting by anything but date implies ungrouped.** If `orderby` is set to a column other than `date` and `ungrouped` is not true, `prepare_args()` sets `ungrouped => true`. Occasion grouping and an arbitrary sort order cannot both hold, and silently returning date-ordered rows would be worse than dropping the grouping. State the implication in the REST parameter description and the WP-CLI help text; do not signal it back in the response body.
-   **REST**: `orderby` and `order` on the events controller, with the same allow-list in `args`, so an invalid value is a 400 rather than a silent fallback. Map them in both arg-mapping tables (lines ~904 and ~993).
-   **WP-CLI**: `--orderby=` and `--order=` on `simple-history list`.

The WP-CLI flag is not decoration. It makes sorting a real core capability a free user can use today, which is the difference between core shipping infrastructure and core shipping an API only a paid plugin can reach.

### 3. `TablePreview` (new, `src/components/TablePreview.jsx`)

Rendered when `eventsView === 'table'` and Premium is not active.

-   **Banner on top**, before the table, outside the inert wrapper so its link stays clickable. Same structure as `.sh-AlertsTeaser-banner`, with a table icon in place of its bell. Copy drafted below, to go through the `simple-history-voice` skill before it ships.
-   **Staged sample rows underneath**: a fixed array in the component, not a fetch. Six to eight rows covering a post update, a failed login, a plugin update, a user role change, a settings save and a WP-CLI action. Take the mix from the `wp-org-screenshots` skill, which already documents which events read well when the log is being shown off.
-   **Staged premium state**: one column header shows an active sort arrow, two rows show checked boxes, and the bulk bar reads "2 selected · Export ▾". This is the whole point of staging over real data — a quiet site previews badly, and nobody's real log arrives pre-sorted with rows selected.
-   **Inert, not disabled-looking**: `pointer-events: none` on the wrapper, `disabled` attributes on the checkboxes, `aria-hidden="true"` on the sample table so screen readers get the banner rather than fake data. The banner and its link keep `pointer-events: auto`, exactly as `.sh-AlertsTeaser-banner` does.
-   **No handlers at all.** No sort callbacks, no selection state, no TanStack import. If a reviewer greps this file for logic there is none to find.
-   **Campaign**: `Helpers::get_tracking_url( …, 'premium_table_view' )`, with `utm_content` distinguishing the banner link from any second CTA, so issue 327's re-measurement can read them apart from the start. That was the lesson from 280 — three quarters of `premium_user_card` traffic came from an untagged link and nobody could tell.

Draft banner copy, pending a voice pass:

> **Sort, select and export your log** — Premium
> See your events as a sortable table. Sort by date, level or event type, pick the rows you want, and export them to CSV or JSON.
> Upgrade to Premium →

### 4. The seam

Same idea as the Export button, which has been through WordPress.org review: core renders a promo, Premium replaces it. One difference — Export needs a filter as well as a Slot because its promo and its real button sit in a row of other buttons. The table view owns the whole log area, so a Slot with a fallback is enough:

```jsx
<Slot name="SimpleHistorySlotTableView" fillProps={ … }>
    { ( fills ) => ( fills.length ? fills : <TablePreview /> ) }
</Slot>
```

-   No filter. Whether the preview shows is decided by whether anything filled the Slot, which is the thing we actually mean.
-   A Premium build that is too old to know about the Slot leaves it empty, so the preview renders. There is no combination that produces a blank log area, which a separate `showTablePreview` filter could: return `false`, fail to fill, show nothing.
-   `fillProps` carries what the table needs and already exists in `EventsGui`: `eventsQueryParams`, `eventsTotal`, `hasAnyActiveFilters`, `eventsIsLoading`, and the setters for paging and sort.
-   Keep `bubblesVirtually` off (the default) — render-prop children need it.

A side effect worth knowing while building: until Premium ships its fill, the Slot is empty on every site, so the preview renders even where Premium is active. That is what makes the core Playwright specs work on the dev WordPress, which has Premium enabled.

Add `'table'` to the `parseAsStringLiteral` list in `EventsGui.jsx:348`, to `VIEWS` in `EventsViewToggle.jsx`, and to the sanitiser in `REST_API::save_events_view()`. The Table button carries a Premium pill for free users, per the 2026-06-27 decision that the switcher shows premium views rather than hiding them.

## Premium work

The entire working table. Replaces the preview through the Slot, so a free and a paid site render different components at the same spot rather than one component in two states.

-   **TanStack Table** for the table model, **`@tanstack/react-virtual`** for the rows. ~18 kB combined, headless, so the markup matches wp-admin rather than fighting a theme.
-   **Sortable headers** drive the core `orderby` / `order` params. Each click is a fetch; the whole result set reorders.
-   **Row selection**: first-column checkboxes, select-all-on-page, a bulk bar showing the count. Selection is by event id and survives paging within a filter set.
-   **Bulk export** hands the selected ids to the existing `ExportModal`. No new export code; the modal already applies filters and already converts at 4.19%.
-   **Column configuration**: a popover to show, hide and reorder columns, stored per user. Reuse the `/simple-history/v1/events-view` route pattern from phase 1 rather than `/wp/v2/users/me` — that endpoint fires `profile_update`, which made Stream log a profile edit on every toggle.
-   **Virtual scrolling** replaces pagination in table view. The first request keeps the count query so "47,812 matching events" stays truthful; later pages pass `skip_count_query`.
-   **Field trimming**: request `_fields` without `details_html`, `details_data`, `initiator_data` and `action_links`, taking the payload from ~4.4 kB to ~950 B an event. This saves bandwidth, not server time — contexts are loaded for every event regardless, which is what phase 3 would fix.
-   **Row click** opens the existing `EventInfoModal`, so details stay lazy-loaded.
-   Bump `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION`, since Premium now calls core APIs that only exist after step 2. Verify with `npm run addons:check` from core.

## Columns

| Column     | Source                         | Sortable               | Default       |
| ---------- | ------------------------------ | ---------------------- | ------------- |
| Select     | —                              | —                      | yes (premium) |
| Date       | `date` column                  | yes, indexed           | yes           |
| User       | contexts `_user_login`         | **no**                 | yes           |
| Message    | interpolated at render         | **no**                 | yes           |
| Level      | `level` column                 | yes                    | yes           |
| Event type | `message` template             | yes                    | no            |
| Logger     | `logger` column                | yes, indexed with date | no            |
| IP address | contexts `_server_remote_addr` | **no**                 | no            |
| Site       | contexts, multisite only       | no                     | no            |

The events table is `id, date, logger, level, message, occasionsID, initiator`, indexed on `date` and `(logger, date)`. Everything else lives in `wp_simple_history_contexts` as `key` / `value longtext`, with an index on `key` but none on `value`.

So **user and IP cannot be sorted cheaply**: each needs a JOIN onto the contexts table and then a filesort over a `longtext` column. On a large log that is the kind of query that shows up in a slow query log. Their headers stay unsorted, with a tooltip pointing at the user filter — which already exists, is the better tool for "what did anna do", and is faster.

**Message is not sortable either**, because the sentence the reader sees does not exist in the database. It is a translated template interpolated at render time. Sorting the raw `message` column would sort by untranslated template, which looks like a bug. That capability is broken out as a separate **Event type** column, which sorts honestly and says what it does.

## Error handling

-   An invalid `orderby` through REST is a 400 from the schema. Through `Log_Query` directly it falls back to `date`, matching how `posts_per_page` is clamped.
-   A failed sort fetch leaves the previous rows on screen and surfaces the existing `FetchEventsErrorMessage`. The header does not show a sort state the data does not have.
-   A failed column-config save is not worth interrupting anyone for; the choice applies for the session, same as the phase 1 view preference.
-   If Premium is active but its build is older than the Slot, nothing fills it and the preview renders. That is the Slot fallback doing its job, not an error state.

## Testing

-   **wpunit, `Log_Query`**: default order unchanged; each allow-listed `orderby` in both directions; the `id` tiebreaker; invalid values falling back; `orderby` forcing `ungrouped`. Run against **MySQL and SQLite** — the ungrouped path is the only one SQLite uses, so it is exactly the path being changed.
-   **wpunit, REST**: `orderby` and `order` accepted, invalid values rejected with 400, ordering reflected in the response.
-   **WP-CLI**: `--orderby` and `--order` on `simple-history list`.
-   **Playwright, core**: its own project alongside `events-view`. The Table button appears for a free user, clicking it shows the preview, the banner link carries the campaign parameters, and nothing in the sample table responds to a click.
-   **Premium**: PHP tests live in this repo, per the testing skill. Cover the Slot fill replacing the preview and selection surviving a page change.
-   **Compliance**: run the `wordpress-org-compliance` skill against `TablePreview` before the core release goes out, checking specifically that no sorting or selection logic exists in the free plugin.

## Compliance notes

Three properties keep this inside the WordPress.org guidelines, and they are worth restating because a future change could break any one of them without looking like it did.

1.  **Core is not degraded.** List and Compact remain complete, with no row caps, no time limits and no license check. Table is an additional lens sold by a separate plugin. Nothing was taken away to create it.
2.  **No working code is withheld.** The free plugin contains no table logic to switch on. The preview is markup and a fixed array.
3.  **Core does feature detection, not feature restriction.** The Slot decides which component renders — core asks "did anything fill this", never "has this person paid". No license key is consulted anywhere in the free path.

The sorting added in step 2 is core infrastructure, fully usable by a free user through WP-CLI and the REST API. It is not a premium capability living in core.

## Changelog

-   **Changed** — the Compact event log view is no longer experimental.
-   **Added** — `orderby` and `order` for events in the REST API and `wp simple-history list`.
-   **Added** — a Table view of the event log (Premium), with sortable columns, row selection and export.

## Sequencing

| Step                                    | Where   | Ships on its own |
| --------------------------------------- | ------- | ---------------- |
| 1. Graduate the switcher                | core    | yes              |
| 2. Sorting in `Log_Query`, REST, WP-CLI | core    | yes              |
| 3. `TablePreview` + the seam            | core    | yes              |
| 4. The working table                    | premium | needs 2 and 3    |
| 5. Column config + virtual scrolling    | premium | needs 4          |

Steps 1 to 3 put a converting teaser in front of every free user before the premium table exists. `premium_table_view` can then be measured for a month, the way issue 327 measures the user card work, and the numbers decide how much step 4 is worth.

## Open questions

-   Should the Table button appear in the switcher for a free user on a site where Premium has never been installed, or only after some signal of interest? Shipping it to everyone is the assumption here, because that is what the alerts teaser does.
-   Does the preview need a second CTA at the bottom of the sample rows, or does one banner do it? Start with one, tagged so a second can be compared against it later.
