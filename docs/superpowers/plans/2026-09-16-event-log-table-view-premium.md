# Event log Table view — premium implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the working sortable table in Simple History Premium — the feature the free plugin's `TablePreview` is a picture of.

**Architecture:** Premium fills the `SimpleHistorySlotTableView` Slot that core ships, replacing the preview. The fill owns its own data fetching (core passes query params, not events), its own sort state mirrored into the URL, row selection that hands ids to the existing `ExportModal`, a column configuration popover persisted per user through a premium REST route, and virtual scrolling in place of pagination. Row clicks open core's existing `EventInfoModal`.

**Tech Stack:** `@tanstack/react-table` v8 + `@tanstack/react-virtual` v3, `@wordpress/components` Slot/Fill, `@wordpress/api-fetch`, PHP 7.4, WordPress 6.3+.

**Spec:** `docs/superpowers/specs/2026-09-16-event-log-table-view-design.md` (the "Premium work" and "Columns" sections)

**Two repos.** Premium code lives in `/Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium` on branch `issue-240-table-view`. Core code and **all tests** live in `/Users/bonnymacmini/Projects/WordPress-Simple-History` on branch `issue-240-table-view`. Both are already checked out. Commit to whichever repo a change belongs in; a task may commit to both.

## Global Constraints

-   **PHP 7.4 compatibility.** No `match`, union types, constructor property promotion, or nullsafe operator. Premium's lint and analyse run through its own docker service, not host PHP: `npm run php:lint` and `npm run php:phpstan` from the premium directory.
-   **WordPress 6.3+.** Every `@wordpress/*` import must exist in WP 6.3. Both repos pin `@wordpress/scripts` to 27.x — **do not upgrade it**; 28+ makes builds depend on the `react-jsx-runtime` script handle, which only exists in WP 6.6+.
-   **Premium's text domain is `simple-history-add-on`**, NOT `simple-history`. Check an existing premium file before writing any translatable string.
-   **Premium imports core source by relative path.** From a file in `src/components/`, core is `../../../../WordPress-Simple-History/src/...` (four levels up). `src/components/PremiumMenuActions.js` is a working example. Keep all new components directly in `src/components/` so this depth stays constant — a deeper subdirectory needs five levels and is a reliable source of broken builds.
-   **Build premium with `npm run build`** from the premium directory. It compiles four entry points; the table view belongs to `src/index.js`.
-   **Playwright specs go in the CORE repo** (`tests/playwright/`), because premium has no test infrastructure of its own. `tests/playwright/premium-helpers.js` exists for exactly this. Run them from the core directory as `CI=1 npx playwright test --project=<name> --reporter=line`. **Never a bare `npx playwright test`** — it also runs the screenshot project and overwrites committed PNGs.
-   The dev site at `http://wordpress-stable-docker-mariadb.test:8282/` live-mounts **both** repos and has Premium active, so premium PHP is live immediately and premium JS after `npm run build`.
-   Comments on their own line above the code, never trailing. Blank line before `if` and `return` and between logical blocks.
-   **No ad-hoc hover effects, lifts, shadows or transitions.** Match wp-admin's own list tables.
-   Accessibility: WCAG AA, 4.5:1 contrast. A sortable header must be a real `<button>` and announce its sort state with `aria-sort`.
-   No AI filler in prose, comments or commit messages: seamlessly, robust, leverage, streamline, unlock, elevate, empower, delve, "furthermore"/"moreover" as transitions.
-   End every commit message body with, after a blank line:
    `Claude-Session: https://claude.ai/code/session_01C6QQ1THy7PTEZ9649LTbHq`
-   **Do NOT push. Do NOT merge.** Both repos stay local.

## Interfaces core already provides (do not change them)

Read these before Task 1; every task depends on them.

-   **The Slot.** `src/components/EventsGui.jsx:943` renders
    `<Slot name="SimpleHistorySlotTableView" fillProps={ { eventsQueryParams, eventsTotal, hasAnyActiveFilters, eventsIsLoading } }>` with a render-prop fallback to `<TablePreview />` when `fills.length === 0`. Filling it replaces the preview. `bubblesVirtually` is off, so a `<Fill>` with **function children** receives `fillProps`.
-   **`eventsQueryParams`** is REST-shaped (`page`, `per_page`, `loglevels`, `loggers`, `users`, `search`, `date_from`, …), built by `generateAPIQueryParams()` in `src/functions.js`. It carries the user's active filters. It does NOT carry sort or the events themselves.
-   **Sorting.** `GET /simple-history/v1/events` accepts `orderby` (one of `date`, `id`, `level`, `logger`, `message`) and `order` (`asc`/`desc`). An invalid value is a 400. Sorting by anything but `date` implies ungrouped results.
-   **Event fields** on that endpoint: `id`, `date_local`, `date_gmt`, `message`, `loglevel`, `logger`, `initiator`, `initiator_data`, `via`, `occasions_id`, plus the heavy `details_html`, `details_data`, `action_links`.
-   **`EventInfoModal`** — `import { EventInfoModal } from '../../../../WordPress-Simple-History/src/components/EventInfoModal';` Props: `{ eventId, closeModal }`. It fetches the event's details itself.
-   **`ExportModal`** — already in premium at `src/ExportModal.js`. Props: `{ eventsTotal, eventsQueryParams, onRequestClose }`. It spreads `eventsQueryParams` into the body of `POST /simple-history/v1/premium/export`, whose `query` parameter is free-form and goes straight to `Log_Query`.
-   **`Log_Query` honours `post__in`** as `id IN (...)`, with every member `intval`-cast in `prepare_args()`. This is how selection-export works, and it needs **no PHP change in either repo**.

---

### Task 1: The Slot fill — a real table rendering real events

**Files:**

-   Modify: `simple-history-premium/package.json` (add two dependencies)
-   Create: `simple-history-premium/src/components/PremiumTableView.jsx`
-   Create: `simple-history-premium/src/components/use-table-events.js`
-   Create: `simple-history-premium/src/components/PremiumTableView.scss` _(only if premium already ships SCSS; otherwise put styles in the existing premium stylesheet — check first)_
-   Modify: `simple-history-premium/src/filters.js`
-   Create: `simple-history-premium/src/components/PremiumTableViewFill.jsx`
-   Test: `WordPress-Simple-History/tests/playwright/premium-table-view.spec.js` (create)
-   Modify: `WordPress-Simple-History/playwright.config.js` (add a project)

**Interfaces:**

-   Consumes: the Slot, `eventsQueryParams`, the events REST endpoint.
-   Produces:

    -   `PremiumTableView( { eventsQueryParams, eventsTotal, hasAnyActiveFilters, eventsIsLoading } )` — the table component.
    -   `useTableEvents( { eventsQueryParams, orderby, order, page } )` → `{ events, total, isLoading, error }`.
    -   A `<Fill name="SimpleHistorySlotTableView">` registered through `SimpleHistory.FilteredComponent`.
    -   CSS class root `.shp-TableView`, with `.shp-TableView__table`, `__th`, `__td`, `__row`.

-   [ ] **Step 1: Read the existing premium fill for the pattern to copy**

Run: `sed -n '90,140p' /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium/src/components/PremiumControlBarButtons.js`

That component is a `FilteredComponent` HOC that renders a `<Fill>`. Yours follows the same shape, but its Fill's children is a **function** receiving `fillProps`, because this Slot passes them.

Also read `src/filters.js` to see how HOCs are registered.

-   [ ] **Step 2: Add the dependencies**

From the premium directory:

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium
npm install @tanstack/react-table@^8 @tanstack/react-virtual@^3
```

Confirm the installed majors are 8 and 3 respectively, and that `@wordpress/scripts` is still 27.x:

```bash
node -p "require('./node_modules/@tanstack/react-table/package.json').version"
node -p "require('./node_modules/@tanstack/react-virtual/package.json').version"
node -p "require('./node_modules/@wordpress/scripts/package.json').version"
```

If npm bumped `@wordpress/scripts` past 27, stop and tell the controller — that silently breaks WordPress 6.3–6.5.

-   [ ] **Step 3: Write the failing test**

Create `WordPress-Simple-History/tests/playwright/premium-table-view.spec.js`:

```js
const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

/**
 * Save the admin's stored view directly, so each test starts from a known state.
 *
 * @param {Object} requestUtils
 * @param {string} view
 */
async function setStoredView( requestUtils, view ) {
	await requestUtils.rest( {
		method: 'POST',
		path: 'simple-history/v1/events-view',
		data: { view },
	} );
}

test.describe( 'Premium table view', () => {
	test.describe.configure( { mode: 'serial' } );

	test.afterAll( async ( { requestUtils } ) => {
		await setStoredView( requestUtils, 'detailed' );
	} );

	test( 'premium replaces the preview with a real table', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );

		// The premium table, not the free preview.
		await expect( page.locator( '.shp-TableView' ) ).toBeVisible();
		await expect( page.locator( '.sh-TablePreview' ) ).toHaveCount( 0 );
	} );

	test( 'the table shows real events from the log', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		const rowCount = await page.locator( '.shp-TableView__row' ).count();
		expect( rowCount ).toBeGreaterThan( 0 );

		// Real events carry numeric ids; the preview's sample rows do not
		// come from the REST API at all.
		const firstId = await page
			.locator( '.shp-TableView__row' )
			.first()
			.getAttribute( 'data-event-id' );
		expect( Number( firstId ) ).toBeGreaterThan( 0 );
	} );
} );
```

Add a project for it in `WordPress-Simple-History/playwright.config.js`, next to the existing `events-view` project and before `tests`, following that project's shape exactly:

```js
		{
			// The premium table view replaces the whole log area and stores a
			// view preference, so it races specs that assume the detailed
			// list. Own project, like events-view above.
			name: 'premium-table',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState,
			},
			testMatch: /premium-table-view\.spec\.js$/,
			dependencies: [ 'events-view' ],
		},
```

Then change the `tests` project's `dependencies` from `[ 'events-view' ]` to `[ 'premium-table' ]`, and add `/premium-table-view\.spec\.js$/` to its `testIgnore` array.

-   [ ] **Step 4: Run the test to verify it fails**

From the core directory:

Run: `CI=1 npx playwright test --project=premium-table --reporter=line`
Expected: FAIL — `.shp-TableView` never appears, because nothing fills the Slot yet and the free `TablePreview` renders instead.

-   [ ] **Step 5: Write the data hook**

Create `simple-history-premium/src/components/use-table-events.js`:

```js
import apiFetch from '@wordpress/api-fetch';
import { useEffect, useState } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

// Everything the table renders, and nothing it does not. The heavy fields
// (details_html, details_data, action_links) are what make an event response
// ~4.4 kB instead of under 1 kB, and the table never shows them — the details
// modal fetches them on demand when a row is clicked.
//
// initiator_data stays in the list even though it is not the cheapest field:
// the User column is one of the table's default columns, and without it the
// column can only show an initiator type, not who actually did the thing.
const TABLE_FIELDS = [
	'id',
	'date_local',
	'date_gmt',
	'message',
	'loglevel',
	'logger',
	'initiator',
	'initiator_data',
].join( ',' );

/**
 * Fetch one page of events for the table.
 *
 * Premium fetches its own data rather than reusing core's: core's Slot passes
 * the active filters but not the events, because the table needs a different
 * page size, a different field set and a sort order core's list does not have.
 *
 * @param {Object} options
 * @param {Object} options.eventsQueryParams Active filters, from the Slot.
 * @param {string} options.orderby           Column to sort by.
 * @param {string} options.order             'asc' or 'desc'.
 * @param {number} options.page              1-based page number.
 * @param {number} options.perPage           Events per request.
 * @return {Object} { events, total, isLoading, error }
 */
export function useTableEvents( {
	eventsQueryParams,
	orderby = 'date',
	order = 'desc',
	page = 1,
	perPage = 100,
} ) {
	const [ events, setEvents ] = useState( [] );
	const [ total, setTotal ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let ignore = false;

		const load = async () => {
			setIsLoading( true );
			setError( null );

			try {
				const response = await apiFetch( {
					path: addQueryArgs( '/simple-history/v1/events', {
						...eventsQueryParams,
						page,
						per_page: perPage,
						orderby,
						order,
						// One row per event: occasion grouping collapses
						// repeats, which is wrong in a table where every row
						// is meant to be one event.
						ungrouped: true,
						_fields: TABLE_FIELDS,
					} ),
					parse: false,
				} );

				const json = await response.json();

				if ( ignore ) {
					return;
				}

				setEvents( json );
				setTotal( Number( response.headers.get( 'X-Wp-Total' ) ) || 0 );
			} catch ( requestError ) {
				if ( ! ignore ) {
					setError( requestError );
					setEvents( [] );
				}
			} finally {
				if ( ! ignore ) {
					setIsLoading( false );
				}
			}
		};

		load();

		// A filter or sort change while a request is in flight must not let the
		// stale response win.
		return () => {
			ignore = true;
		};
	}, [ eventsQueryParams, orderby, order, page, perPage ] );

	return { events, total, isLoading, error };
}
```

**Before writing this, verify the total header's name.** Run:

```bash
grep -rn "X-Wp-Total\|X-WP-Total" /Users/bonnymacmini/Projects/WordPress-Simple-History/inc/class-wp-rest-events-controller.php | head
```

Use whatever casing the controller actually sends. `Headers.get()` is case-insensitive, but the grep also confirms the header exists at all — if it does not, read `total` from the response body's meta instead and say so in your report.

-   [ ] **Step 6: Write the table component**

Create `simple-history-premium/src/components/PremiumTableView.jsx`. Use TanStack Table's `useReactTable` with `getCoreRowModel`. Sorting, selection, column config and virtualisation arrive in later tasks — keep this one to rendering.

Requirements for this step:

-   Columns: Date (`date_local`), User, Message, Level. Derive the User cell from `initiator_data` when present (a `user_login` or `user_email` field), falling back to a readable label for the `initiator` type (`wp` → "WordPress", `wp_cli` → "WP-CLI", `web_user` → "Anonymous user", `other` → "Other").
-   Every row is `<tr className="shp-TableView__row" data-event-id={ event.id }>`. The test depends on that attribute.
-   Render `message` as text. It arrives already interpolated and translated.
-   Loading state: keep the previous rows on screen and mark the table `aria-busy="true"` rather than blanking it, so sorting later does not flash.
-   Error state: render the message plainly, with a retry button that refetches.
-   Empty state: "No events found." matching the wording core's list uses — grep core for the existing string and reuse it so translations line up.
-   Use premium's text domain, `simple-history-add-on`.
-   Markup should match wp-admin's `wp-list-table` conventions in spirit. No hover lifts, shadows or transitions.

-   [ ] **Step 7: Write the Fill and register it**

Create `simple-history-premium/src/components/PremiumTableViewFill.jsx`:

```jsx
import { Fill } from '@wordpress/components';
import { PremiumTableView } from './PremiumTableView';

/**
 * Fill core's table view Slot with the real table.
 *
 * Core renders its own TablePreview when this Slot has no fills, so simply
 * registering this replaces the free preview. The Slot passes fillProps, so
 * the Fill's children is a function rather than an element.
 *
 * @param {Function} FilteredComponent
 */
export function premiumTableView( FilteredComponent ) {
	return ( props ) => (
		<>
			<FilteredComponent { ...props } />

			<Fill name="SimpleHistorySlotTableView">
				{ ( fillProps ) => <PremiumTableView { ...fillProps } /> }
			</Fill>
		</>
	);
}
```

Register it in `src/filters.js` alongside the existing `addFilter( 'SimpleHistory.FilteredComponent', ... )` calls, following their exact shape.

-   [ ] **Step 8: Build and run the test to verify it passes**

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium && npm run build
cd /Users/bonnymacmini/Projects/WordPress-Simple-History && CI=1 npx playwright test --project=premium-table --reporter=line
```

Expected: PASS, both tests.

Then confirm nothing else regressed:

Run: `CI=1 npx playwright test --project=tests --reporter=line`
Expected: PASS. Run `git status --short` in the core repo afterwards and confirm no PNG changed.

-   [ ] **Step 9: Look at it**

Open `http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&view=table` and check the table renders real events, the columns line up, and it reads like a wp-admin list table. Measure anything you are unsure about with `getBoundingClientRect()` / `getComputedStyle()` rather than eyeballing.

-   [ ] **Step 10: Lint**

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium && npm run lint:js
```

Expected: clean for the files you added.

-   [ ] **Step 11: Commit, in both repos**

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium
git add package.json package-lock.json src/components/PremiumTableView.jsx \
  src/components/PremiumTableViewFill.jsx src/components/use-table-events.js src/filters.js
git commit -m "Add the premium table view behind core's table Slot"

cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add tests/playwright/premium-table-view.spec.js playwright.config.js
git commit -m "Add Playwright coverage for the premium table view"
```

---

### Task 2: Sortable column headers

**Files:**

-   Modify: `simple-history-premium/src/components/PremiumTableView.jsx`
-   Create: `simple-history-premium/src/components/use-table-sort.js`
-   Test: `WordPress-Simple-History/tests/playwright/premium-table-view.spec.js`

**Interfaces:**

-   Consumes: `useTableEvents` from Task 1, which already accepts `orderby` / `order`.
-   Produces: `useTableSort()` → `{ orderby, order, toggleSort( column ) }`, with state mirrored to the URL as `table_orderby` and `table_order`.

**Sortable columns are exactly `date`, `id`, `level`, `logger`, `message`** — core's REST `enum`, which rejects anything else with a 400. **User and IP are NOT sortable**: they live in the contexts table as `longtext` with no index on the value, so sorting them means a JOIN plus a filesort. Their headers stay plain text with a tooltip pointing at the user filter. Do not add them to the sortable set "for completeness"; the endpoint will 400.

Note "Message" sorts by the stored message _template_, not the rendered sentence, because the sentence is interpolated at render time and does not exist in the database. Label that header's sort affordance accordingly, or leave Message unsortable and expose a separate sortable "Event type" column — **prefer leaving Message unsortable**, which is what the spec's column table specifies.

-   [ ] **Step 1: Write the failing test**

Append to `tests/playwright/premium-table-view.spec.js`:

```js
test( 'clicking a sortable header reorders the whole result set', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	const firstIdBefore = await page
		.locator( '.shp-TableView__row' )
		.first()
		.getAttribute( 'data-event-id' );

	// Level is sortable and uncorrelated with insertion order, so a real
	// server-side sort changes which row comes first.
	await page.getByRole( 'button', { name: /Level/ } ).click();
	await expect( page.locator( '.shp-TableView' ) ).not.toHaveAttribute(
		'aria-busy',
		'true'
	);

	const firstIdAfter = await page
		.locator( '.shp-TableView__row' )
		.first()
		.getAttribute( 'data-event-id' );

	expect( firstIdAfter ).not.toBe( firstIdBefore );
} );

test( 'sort survives a reload through the URL', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto(
		SIMPLE_HISTORY_PAGE + '&view=table&table_orderby=level&table_order=asc'
	);
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	const levelHeader = page.getByRole( 'button', { name: /Level/ } );
	await expect( levelHeader ).toHaveAttribute( 'aria-sort', 'ascending' );
} );
```

Note `aria-sort` belongs on the `<th>`, not the button, per ARIA. If you put it on the `<th>`, change the assertion to target the `<th>` and say so in your report — the assertion should follow correct ARIA, not the other way round.

-   [ ] **Step 2: Run the test to verify it fails**

Run: `CI=1 npx playwright test --project=premium-table --reporter=line`
Expected: FAIL — no header buttons exist yet.

-   [ ] **Step 3: Write the sort hook**

Create `simple-history-premium/src/components/use-table-sort.js`.

It must:

-   Read the initial `orderby` / `order` from `window.location.search` (`table_orderby`, `table_order`), validating against the five allowed columns and `asc`/`desc`, falling back to `date` / `desc`.
-   Expose `toggleSort( column )`: clicking a new column sorts it descending; clicking the active column flips direction.
-   Mirror state into the URL with `window.history.replaceState`, preserving every other query parameter.

**Use plain `URLSearchParams` and `history.replaceState`. Do NOT add `nuqs`.** Core uses nuqs for its own URL state, and a second nuqs instance in a separate bundle would fight core's over the same URL. Distinct parameter names (`table_orderby`, not `orderby`) keep the two from colliding.

-   [ ] **Step 4: Wire the headers**

In `PremiumTableView.jsx`, render sortable headers as real `<button>` elements inside the `<th>`, with `aria-sort` on the `<th>` (`ascending` / `descending` / `none`). Show a direction arrow that is not colour-only. Non-sortable headers stay plain text.

Pass `orderby` / `order` from the hook into `useTableEvents`.

-   [ ] **Step 5: Run the test to verify it passes**

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium && npm run build
cd /Users/bonnymacmini/Projects/WordPress-Simple-History && CI=1 npx playwright test --project=premium-table --reporter=line
```

-   [ ] **Step 6: Verify by hand that sorting is server-side, not page-local**

This is the claim most worth checking directly. In the browser, sort by Level ascending and confirm the first page holds the alphabetically-lowest levels across the _whole_ log, not just a reordering of the rows that were already loaded. Compare against:

```bash
cd /Users/bonnymacmini/Projects/_docker-compose-to-run-on-system-boot
docker compose run --rm wpcli_mariadb --skip-plugins=woocommerce simple-history event list --orderby=level --order=asc --count=10 --fields=ID,level
```

The table's first rows should match the CLI's. Paste both into your report.

-   [ ] **Step 7: Lint and commit both repos**

Premium: `npm run lint:js`. Then commit the premium changes and the core test changes separately, as in Task 1 Step 11.

---

### Task 3: Row selection and bulk export

**Files:**

-   Modify: `simple-history-premium/src/components/PremiumTableView.jsx`
-   Create: `simple-history-premium/src/components/TableViewBulkBar.jsx`
-   Test: `WordPress-Simple-History/tests/playwright/premium-table-view.spec.js`

**Interfaces:**

-   Consumes: `ExportModal` from `simple-history-premium/src/ExportModal.js`, props `{ eventsTotal, eventsQueryParams, onRequestClose }`.
-   Produces: `TableViewBulkBar( { selectedCount, onExport, onClear } )`.

**How export-by-selection works, and why it needs no PHP:** `ExportModal` spreads `eventsQueryParams` into the `query` object of `POST /simple-history/v1/premium/export`. That parameter is free-form and goes straight to `Log_Query`, which honours `post__in` as `id IN (...)` with every member `intval`-cast. So passing `{ ...eventsQueryParams, post__in: selectedIds }` exports exactly the selected rows, with the user's filters still applied. Do not add a new endpoint or a new parameter.

-   [ ] **Step 1: Write the failing test**

Append to `tests/playwright/premium-table-view.spec.js`:

```js
test( 'selecting rows reveals a bulk bar with the count', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	await expect( page.locator( '.shp-TableView__bulkBar' ) ).toHaveCount( 0 );

	const checkboxes = page.locator(
		'.shp-TableView__row input[type="checkbox"]'
	);
	await checkboxes.nth( 0 ).check();
	await checkboxes.nth( 1 ).check();

	const bulkBar = page.locator( '.shp-TableView__bulkBar' );
	await expect( bulkBar ).toBeVisible();
	await expect( bulkBar ).toContainText( '2' );
} );

test( 'select-all on the page toggles every row', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	const rowCount = await page.locator( '.shp-TableView__row' ).count();

	await page.locator( '.shp-TableView__selectAll' ).check();

	const checked = page.locator(
		'.shp-TableView__row input[type="checkbox"]:checked'
	);
	await expect( checked ).toHaveCount( rowCount );

	await page.locator( '.shp-TableView__selectAll' ).uncheck();
	await expect( page.locator( '.shp-TableView__bulkBar' ) ).toHaveCount( 0 );
} );
```

-   [ ] **Step 2: Run the test to verify it fails**

Run: `CI=1 npx playwright test --project=premium-table --reporter=line`
Expected: FAIL — no checkboxes exist.

-   [ ] **Step 3: Add selection to the table**

Use TanStack Table's row selection (`enableRowSelection`, `getRowId` returning the event id as a string, `onRowSelectionChange`). Add a first column holding:

-   A header checkbox with class `shp-TableView__selectAll`, wired to select/deselect every row on the page, showing an indeterminate state when only some are selected.
-   A per-row checkbox with an accessible name naming the event, not just "Select row" — e.g. `Select event 12345`. A column of identically-named checkboxes is unusable with a screen reader.

**Selection is keyed by event id and must survive a sort or page change within the same filter set.** Clearing filters clears selection. Say in your report how you verified the survival case.

-   [ ] **Step 4: Add the bulk bar**

Create `TableViewBulkBar.jsx`: a bar showing "N selected", an Export button, and a Clear button. Use `_n()` with premium's text domain for the count. It renders only when at least one row is selected.

-   [ ] **Step 5: Wire Export to the existing modal**

Clicking Export opens `ExportModal` with:

```js
eventsQueryParams={ { ...eventsQueryParams, post__in: selectedIds } }
eventsTotal={ selectedIds.length }
```

`selectedIds` must be an array of integers, not strings — `getRowId` returns strings, so map with `Number`.

-   [ ] **Step 6: Run the tests, then verify an export end to end by hand**

Build, run `--project=premium-table`, then in the browser select three rows, export as CSV, open the file, and confirm it contains exactly those three events and no others. Paste the CSV's row count and the selected ids into your report. **This is the step that proves `post__in` works** — the Playwright tests do not download the file.

-   [ ] **Step 7: Lint and commit both repos**

---

### Task 4: Column configuration

**Files:**

-   Create: `simple-history-premium/src/components/TableViewColumnsMenu.jsx`
-   Modify: `simple-history-premium/src/components/PremiumTableView.jsx`
-   Create: `simple-history-premium/inc/modules/class-table-view-module.php`
-   Modify: whichever premium file registers modules — find it with `grep -rn "Export_Module" inc/ | head`
-   Test: `WordPress-Simple-History/tests/playwright/premium-table-view.spec.js`

**Interfaces:**

-   Produces: `POST /simple-history/v1/premium/events-table-columns`, body `{ columns: [ 'date', 'user', 'message', 'level' ] }`, storing user meta `simple_history_events_table_columns` for the current user; and a `GET` returning the stored value.

**Why a premium route rather than core's:** core's `/simple-history/v1/events-view` accepts only a `view` string and is core's to own. Premium registers its own route in its own namespace. Follow `inc/modules/class-export-module.php` for the shape.

**Do not use `/wp/v2/users/me` to store this.** That endpoint always runs `wp_update_user()` and fires `profile_update`, which third-party plugins act on for real profile changes — Stream logged a profile edit on every view toggle when core tried it, which is why core has its own route.

Available columns: `date`, `user`, `message`, `level`, `logger`, `event_type`, `ip` (premium stores IPs), and `site` on multisite only. Defaults: `date`, `user`, `message`, `level`.

-   [ ] **Step 1: Write the failing test**

```js
test( 'hiding a column persists across a reload', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	await expect(
		page.getByRole( 'columnheader', { name: /Level/ } )
	).toBeVisible();

	await page.getByRole( 'button', { name: /Columns/ } ).click();
	await page.getByRole( 'checkbox', { name: /Level/ } ).uncheck();
	await page.keyboard.press( 'Escape' );

	await expect(
		page.getByRole( 'columnheader', { name: /Level/ } )
	).toHaveCount( 0 );

	// The preference is stored per user, so a fresh load keeps it.
	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	await expect(
		page.getByRole( 'columnheader', { name: /Level/ } )
	).toHaveCount( 0 );
} );
```

Add an `afterAll` that restores the default columns through the REST route, so later specs and the developer's own site are not left with a hidden column.

-   [ ] **Step 2: Run it, expect failure** — no Columns button exists.

-   [ ] **Step 3: Write the PHP module**

Model it on `inc/modules/class-export-module.php`. Requirements:

-   `GET` and `POST` on `/simple-history/v1/premium/events-table-columns`.
-   Permission callback: the same capability the history page requires. Look at how core's `REST_API::events_view_permissions_check()` does it and match — do not hardcode `manage_options`, which would deny editors who can see the log.
-   `columns` is an array of strings, each validated against the known column ids. Unknown ids are dropped rather than erroring.
-   An empty array after validation falls back to the defaults — a table with no columns is a broken screen.
-   Store with `update_user_meta()` for the current user only. No user id parameter.
-   PHP 7.4. Premium's text domain for any string.

-   [ ] **Step 4: Localise the stored value**

The table needs the stored columns on first render without a round trip. Find how premium localises data to its bundle (`grep -rn "wp_localize_script\|wp_add_inline_script" inc/ | head`) and add the stored columns there. If premium has no such object yet, create one; say which you did and why in your report.

-   [ ] **Step 5: Write the columns menu**

`TableViewColumnsMenu.jsx`: a `DropdownMenu` or `Popover` from `@wordpress/components` with a checkbox per available column. Reordering is **out of scope for this task** — the spec mentions it, but show/hide plus persistence is the valuable half, and drag-reorder inside a popover is a large amount of interaction code. Note the omission in your report so the controller can decide.

Saving is fire-and-forget: apply the change locally at once and POST in the background. A failed save must not block the UI — the choice simply does not survive the reload, matching how core treats its view preference.

-   [ ] **Step 6: Run the tests, verify persistence by hand, lint, commit both repos.**

---

### Task 5: Virtual scrolling

**Files:**

-   Modify: `simple-history-premium/src/components/PremiumTableView.jsx`
-   Modify: `simple-history-premium/src/components/use-table-events.js`
-   Test: `WordPress-Simple-History/tests/playwright/premium-table-view.spec.js`

**Interfaces:**

-   Produces: `useTableEvents` gains an append mode — `{ events, total, isLoading, isLoadingMore, hasMore, loadMore }`.

**The count rule:** the first request for a filter set keeps the count query so the table can honestly say "47,812 matching events". Every subsequent page passes `skip_count_query: true`, because re-counting an unchanged result set on every scroll is the expensive half of the query. Changing a filter or the sort resets to page 1 and counts again.

-   [ ] **Step 1: Write the failing test**

```js
test( 'scrolling to the bottom loads more events', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	const initialCount = await page.locator( '.shp-TableView__row' ).count();

	await page.locator( '.shp-TableView__row' ).last().scrollIntoViewIfNeeded();

	await expect
		.poll(
			async () => await page.locator( '.shp-TableView__row' ).count(),
			{ timeout: 10000 }
		)
		.toBeGreaterThan( initialCount );
} );
```

**This test only means something on a log with more events than one page.** Before writing it, check the dev site has enough:

```bash
cd /Users/bonnymacmini/Projects/_docker-compose-to-run-on-system-boot
docker compose run --rm wpcli_mariadb --skip-plugins=woocommerce simple-history event list --count=1 --format=count
```

If that is under ~200, use core's **populate-log** skill to add test data before relying on this test, and say what you did in your report.

-   [ ] **Step 2: Run it, expect failure.**

-   [ ] **Step 3: Add append mode to the hook**

Keep accumulated events in state; `loadMore()` fetches the next page and appends. Reset accumulation whenever `eventsQueryParams`, `orderby` or `order` changes. Pass `skip_count_query: true` on every request after the first for a given filter set.

Guard against a double-fire: `loadMore()` while `isLoadingMore` is true must be a no-op, or a fast scroll fetches the same page twice and duplicates rows.

-   [ ] **Step 4: Virtualise the rows**

Use `@tanstack/react-virtual`'s `useVirtualizer` over the accumulated rows, with a scroll container element and an estimated row height measured from the real rendered row rather than guessed.

Trigger `loadMore()` when the last virtual item approaches the end of the list.

Keep the table header visible while the body scrolls (`position: sticky`).

**Accessibility:** a virtualised table drops rows from the DOM, which breaks screen-reader navigation and Ctrl+F. Mitigate with `aria-rowcount` on the table and `aria-rowindex` on each row so assistive technology knows the real size and position. Say in your report how you verified this with a screen reader or the accessibility tree.

-   [ ] **Step 5: Show the honest total**

Display the count from the first request — "N matching events" — near the table. When the count query was skipped, never show a number derived from the rows currently loaded; that would silently mean "how many I have fetched", which is not what a reader takes it to mean.

-   [ ] **Step 6: Measure**

In the browser console, record the payload size and timing of the first table request and of one `loadMore` request, with 100 events per page. Put the numbers in your report. The spec estimated roughly 950 bytes per event with the trimmed field set, but that estimate excluded `initiator_data`, which the User column needs — so the real figure will be higher. Report the real one.

-   [ ] **Step 7: Run tests, lint, commit both repos.**

---

### Task 6: Row details, the core fetch trim, and release wiring

**Files:**

-   Modify: `simple-history-premium/src/components/PremiumTableView.jsx`
-   Modify: `simple-history-premium/simple-history-premium.php`
-   Modify: `simple-history-premium/readme.txt`
-   Modify: `WordPress-Simple-History/src/components/EventsGui.jsx`
-   Test: `WordPress-Simple-History/tests/playwright/premium-table-view.spec.js`

-   [ ] **Step 1: Write the failing test for row details**

```js
test( 'clicking a row opens the event details modal', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	await page.locator( '.shp-TableView__row' ).first().click();

	await expect( page.getByRole( 'dialog' ) ).toBeVisible();
} );
```

-   [ ] **Step 2: Open core's modal on row click**

```jsx
import { EventInfoModal } from '../../../../WordPress-Simple-History/src/components/EventInfoModal';
```

Props are `{ eventId, closeModal }`. Hold the open event id in state.

Clicking a checkbox must NOT open the modal — stop propagation on the selection cell. The row must also be keyboard-reachable: make the Date cell a `<button>` that opens the modal, rather than putting a click handler on a `<tr>`, which no keyboard user can reach.

-   [ ] **Step 3: Trim core's redundant fetch in table view**

Core still runs its full events query in table view and discards the result, because premium fetches its own. Cutting it entirely is not safe: `NewEventsNotifier` renders in the control bar in every view and needs `eventsMaxId` / `eventsMaxDate`, and `eventsTotal` feeds the control bar and the export modal.

So make it cheap rather than absent. In `src/components/EventsGui.jsx`, when `eventsView === 'table'`, request a single row with a minimal field set, keeping the count query so `eventsTotal` stays correct:

-   `per_page: 1`
-   `_fields: 'id,date_gmt'`

Leave every other view untouched. Verify afterwards that the new-events notifier still appears in table view when a new event arrives — write one event with WP-CLI while the table is open and confirm the notifier shows.

**If this turns out to need more than a small, contained change, stop and report rather than restructuring the fetch.** It is an optimisation, not a requirement, and core is on a branch the maintainer wants kept clean.

-   [ ] **Step 4: Bump the minimum core version**

In `simple-history-premium.php`, change `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION` from `5.29.0` to `5.34.0`. Premium now calls `orderby`/`order` and fills a Slot that only exists in the unreleased core version.

**Flag clearly in your report** that this assumes the next core release is numbered 5.34.0 (current core is 5.33.0 with an Unreleased changelog section). If the maintainer numbers it differently, this constant must match, and premium must not be released before that core version is.

Then verify from the core directory:

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History && npm run addons:check
```

-   [ ] **Step 5: Premium changelog**

Add an entry to `simple-history-premium/readme.txt` following the file's existing convention — read it first. Describe what a user gets: a sortable table view of the event log, with row selection, export of the selected rows, and configurable columns. Run the wording through the **simple-history-voice** skill.

-   [ ] **Step 6: Full verification**

From the core directory:

```bash
npm run test:wpunit
CI=1 npx playwright test --project=tests --reporter=line
CI=1 npx playwright test --project=premium-table --reporter=line
./vendor/bin/phpstan analyse --memory-limit=2G
git status --short
```

From the premium directory:

```bash
npm run lint:js
npm run php:lint
npm run php:phpstan
```

Report every result. If a suite fails, return BLOCKED with the output rather than attempting a broad fix.

-   [ ] **Step 7: Screenshot**

Capture the working premium table and save it to `$SH_NOTES_DIR/Simple History/screenshots/240-table-view-premium.png`. Include the control bar so the view switcher is visible — the free-user screenshot from the core plan cropped it out and that was noted as a gap. Compress:

```bash
pngquant --quality=80-95 --strip --skip-if-larger --force --ext .png <file>.png
oxipng -o max --strip safe <file>.png
```

-   [ ] **Step 8: Commit both repos.**

---

## Out of scope

-   **Column reordering** (Task 4 does show/hide only).
-   **The filtered context-loading server optimisation** — phase 3 on the issue, its own spec and benchmark.
-   **Calendar view** — phase 4.
-   Sorting by user or IP; the schema will not do it cheaply.
