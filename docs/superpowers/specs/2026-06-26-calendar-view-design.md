# Calendar View — Design Spec

**Date:** 2026-06-26  
**Status:** Approved

---

## Overview

Add a Calendar view to the Simple History event log page. The calendar shows when events happened using a month/week/day grid — each day cell displays a severity summary (colored dots) and a mini list of the top events for that day. A view toggle (`List | Calendar`) on the existing log page switches between modes. All existing filters apply to both views.

---

## 1. Overall Structure

The existing log page gains a **view toggle** near the top of the page (alongside or near the filter bar): `List | Calendar`. State is stored in the URL via `nuqs` as `?view=calendar` (default: `list`). All existing filters (loglevels, loggers, user, search, date range) carry over and apply to both views.

When Calendar view is active, the `EventsList` component is replaced entirely by the `CalendarView` component. The filter bar above remains visible in both modes.

The calendar has **three zoom levels**: Month → Week → Day. A segmented control inside the calendar header switches between them. The current period (e.g. "June 2025") is shown with prev/next navigation arrows. Zoom level is stored in `nuqs` as `?calView=month|week|day`.

---

## 2. Data Fetching

When calendar view is active, events for the visible period are fetched using the existing REST API (`/wp-json/simple-history/v1/events`) with `date_from` + `date_to` + any active filters:

-   **Month view:** fetch all events within the month
-   **Week view:** fetch events within the 7-day window
-   **Day view:** fetch events for that single day

Results are stored in separate `calendarEvents` state (independent from the list view state). When the user navigates to a new period (prev/next), a fresh fetch fires. A loading skeleton is shown on the calendar grid during fetches.

Events are grouped by `date_local` client-side using `date-fns` — one array per calendar day, used to populate each day cell.

**Pagination caveat:** the REST API paginates by default. The calendar fetch requests a high per-page limit (e.g. 500). Very busy sites may see truncated day counts. A follow-up issue can add a dedicated aggregation endpoint if this becomes a real problem in practice.

---

## 3. Day Cell Layout

Each day cell in **month and week view** shows:

**Top row:** day number + a cluster of severity indicator dots (colored by loglevel — error = red, warning = orange, info = grey/blue). Gives at-a-glance severity without reading numbers.

**Event rows:** up to 3 mini event rows, each showing:

-   A colored loglevel indicator (dot or left border)
-   Truncated event message (single line, ellipsis)
-   No avatar or timestamp (space is too tight)

**Overflow:** if there are more than 3 events, a `+N more` link at the bottom of the cell. Clicking it navigates into the day zoom level to see all events for that day.

**Empty days:** just the day number. No clutter.

**Day view** (zoomed in to a single day) renders the full `Event` component — the same rich event rows used in the list view today. No new event component needed. The calendar chrome (header, navigation) stays visible above.

---

## 4. Interactions & Navigation

-   **Clicking a day cell** (month view) → zooms into week view, centered on that week, clicked day highlighted
-   **Clicking a day number** (week view) → zooms into day view for that day
-   **Clicking `+N more`** → zooms directly into day view
-   **Prev/next arrows** → navigate one period (month/week/day), triggers new fetch
-   **"Today" button** → jumps to the current period at the current zoom level
-   **Switching back to List view** → the date range currently viewed in the calendar is preserved and applied as a date filter in the list, keeping the two views connected
-   **Clicking a mini event row** in a day cell → opens the existing event detail modal (no new modal needed)

---

## 5. Visual Design & Styling

The calendar follows the existing Simple History design language. Built with plain React + CSS — no external UI framework beyond `react-big-calendar`.

**Grid:** CSS Grid for layout. 7 columns for month/week. Day cells have a fixed minimum height in month view; expand to fill in week/day view. The `react-big-calendar` library provides the grid structure; day cell content is rendered via custom event and date cell components.

**Colors:** Loglevel colors already defined in the codebase (error = red, warning = orange/yellow, info = grey/blue) — reused directly for severity dots and event row indicators.

**Current day:** highlighted with a subtle background tint on the day number (standard calendar convention).

**Responsive:** on narrow screens, month view collapses to day numbers + dot indicators only (no event rows). Week/day views remain full.

---

## 6. Library & Build Integration

**Library:** `react-big-calendar` — MIT licensed, no premium tier, no commercial pressure. Uses the `dateFnsLocalizer` (date-fns is already a project dependency — no new runtime dependency added).

**Why react-big-calendar over alternatives:**

-   Lighter than FullCalendar v6 for the plugins needed (52 KB gzip vs ~66 KB)
-   100% free — FullCalendar has a paid Scheduler tier; schedule-x has a €479/yr premium tier
-   date-fns localizer works out of the box with what's already in the project
-   Confirmed production usage in WordPress plugins with known CSS isolation patterns

**Lazy loading:** The calendar component is code-split into its own webpack chunk via `React.lazy()` + `Suspense` + a `webpackChunkName` magic comment. The 52 KB only downloads when the user first switches to Calendar view — zero impact on the default list view load time.

**CSS isolation:** Add `postcss-prefix-selector` (devDependency) to the webpack build. All `react-big-calendar` CSS is scoped under `.sh-calendar-wrap`, preventing wp-admin global element styles (bare `input`, `table`, `td`, etc.) from bleeding into the calendar grid.

---

## 7. Known Limitations & Follow-up Opportunities

-   **Truncated counts on busy sites:** the 500-event per-page limit means very active sites may see incomplete day counts. A future REST endpoint returning per-day aggregates (grouped SQL query) would fix this without a large payload.
-   **No drag-and-drop:** not relevant for a read-only event log.
-   **React 19 compatibility:** `react-big-calendar` added React 19 peer dep support in PR #2710 (December 2024). Verify peer deps are current before shipping.

---

## Out of Scope

-   Creating, editing, or deleting events from the calendar (Simple History is read-only)
-   Resource/room views
-   iCal export from the calendar view
-   Recurring event visualization
