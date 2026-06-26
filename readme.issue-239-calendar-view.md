# Calendar View — Issue 239

Add a List/Calendar toggle to the Simple History event log page, letting users browse events in a month/week/day grid with per-day severity indicators and mini event previews.

## Status

Implementation complete. Playground running at **http://issue-calendar-view.test:9400**

Needs: manual browser smoke test → merge to develop.

## Preview

**Simple History event log:**
http://issue-calendar-view.test:9400/wp-admin/admin.php?page=simple_history_admin_menu_page

## What Was Built

-   **List | Calendar toggle** in the event log header (hidden when viewing surrounding events)
-   **Month / Week / Day views** via `react-big-calendar` with `dateFnsLocalizer`
-   **Per-day severity dots** — red/orange/grey cluster showing error/warning/info counts at a glance
-   **Mini event rows** per day cell — truncated message + colored left border by loglevel
-   **Event detail modal** — clicking a mini event row opens the existing `EventInfoModal` via URL hash (`#simple-history/event/<id>`)
-   **Calendar → List date sync** — switching back to List applies the calendar's current period as a custom date range filter
-   **Lazy loading** — `sh-calendar.js` chunk only downloads when the user first opens Calendar view
-   **CSS isolation** — `postcss-prefix-selector` scopes all `react-big-calendar` styles under `.sh-calendar-wrap`

## Key Technical Decisions

| Decision         | Choice                                                          | Why                                                               |
| ---------------- | --------------------------------------------------------------- | ----------------------------------------------------------------- |
| Calendar library | `react-big-calendar` (MIT)                                      | Fully free, date-fns localizer already in project, 52KB gzip      |
| CSS isolation    | `postcss-prefix-selector` in `postcss.config.js`                | Prevents wp-admin bare element styles bleeding into calendar grid |
| Data fetching    | Paginated REST API (`per_page: 100`, loop on `X-WP-TotalPages`) | API cap is 100; 500 returns HTTP 400                              |
| Modal trigger    | `window.location.hash = '#simple-history/event/<id>'`           | Matches existing `EventsModalIfFragment` mechanism                |
| Code splitting   | `React.lazy()` + `webpackChunkName: "sh-calendar"`              | Zero impact on default list view load time                        |

## Files Introduced

```
src/components/CalendarView/
  CalendarView.jsx        — main component (lazy-loaded default export)
  CalendarView.scss       — all .sh-calendar-* styles
  CalendarToolbar.jsx     — prev/today/next + Month/Week/Day controls
  CalendarDayCell.jsx     — dateCellWrapper: severity dots above {children}
  CalendarEventRow.jsx    — mini event row with modal click handler
  calendarUtils.js        — LOG_LEVEL_COLORS, getPeriodRange, groupEventsByDay, getSeverityCounts
  useCalendarEvents.js    — paginated REST fetch hook with ignore-flag race fix
postcss.config.js         — CSS isolation (includes @wordpress/postcss-plugins-preset)
```

## Files Modified

```
src/components/EventsGui.jsx   — viewMode/calView nuqs state, lazy CalendarView import, toggle UI
readme.txt                     — Unreleased changelog entry
package.json                   — react-big-calendar, postcss-prefix-selector
```

## Commits

```
e0625b9a build: add react-big-calendar and postcss CSS isolation
46fe13e0 feat: add List/Calendar view toggle to event log
aafbdedc feat: scaffold CalendarView with react-big-calendar and toolbar
bf4aec34 feat: fetch and display events in calendar view
b2e9fedb feat: add severity dots and mini event rows to calendar day cells
16f33b6a feat: wire event modal, drilldown navigation, and list date sync
3ba55659 docs: add calendar view changelog entry
c55edc47 fix: paginate calendar events fetch, restore PostCSS defaults, add a11y+i18n fixes
```

## Bugs Caught by Final Code Review

-   **Critical:** `per_page: 500` exceeded REST API cap (100) → calendar always empty → fixed with pagination
-   **Important:** `postcss.config.js` presence disabled autoprefixer + cssnano for entire plugin → fixed by including `@wordpress/postcss-plugins-preset`
-   **Important:** Fetch race condition on rapid prev/next → fixed with `ignore` flag cleanup
-   **Minor:** Space key not handled on event rows (a11y) → fixed
-   **Minor:** Severity dot tooltips not translatable → fixed with `__()` / `sprintf()`

## Manual Smoke Test Checklist

-   [ ] List view loads normally (no regression)
-   [ ] Toggle to Calendar shows current month with event dots
-   [ ] Switching filters (e.g. errors only) re-fetches and calendar updates
-   [ ] Month → Week drilldown (click a day cell)
-   [ ] Week → Day drilldown (click day number in column header)
-   [ ] Click "+N more" → zooms to day view
-   [ ] "Today" button jumps to current period
-   [ ] Prev/next navigation fires new API calls
-   [ ] Clicking a mini event row opens the event detail modal
-   [ ] Switching back to List applies the calendar period as a date filter
-   [ ] `sh-calendar.js` only downloads when Calendar view first opened (network tab)

## Next Steps

1. Complete the manual smoke test above
2. Merge: `superpowers:finishing-a-development-branch`
