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

/**
 * True for core's own events-list request, not the view-preference endpoint
 * ("…/events-view") or the new-events check ("…/events/has-updates"), both
 * of which contain "/v1/events" as a substring too.
 *
 * @param {string} url
 * @return {boolean}
 */
function isCoreEventsListRequest( url ) {
	return new URL( url ).pathname === '/wp-json/simple-history/v1/events';
}

// PSR-3 severity, least severe first — the same order
// Log_Levels::get_log_levels_by_severity() defines in core, which is what
// the SQL ranks by. Deliberately not alphabetical: sorting the level column
// as text puts emergency between error and info.
const LEVELS_BY_SEVERITY = [
	'debug',
	'info',
	'notice',
	'warning',
	'error',
	'critical',
	'alert',
	'emergency',
];

/**
 * Whether the table's Level column reads back in non-decreasing severity
 * order, which is what an ascending sort on Level should produce.
 *
 * An earlier version of this compared the labels as strings, which happens
 * to agree with severity on a log holding only debug/info/warning — so it
 * passed while the underlying sort was alphabetical and wrong.
 *
 * @param {string[]} levelValues Visible text of every Level cell, top to bottom.
 * @return {boolean} True if sorted (and non-empty); false otherwise.
 */
function isLevelColumnNonDecreasing( levelValues ) {
	if ( levelValues.length === 0 ) {
		return false;
	}

	const rankOf = ( label ) =>
		LEVELS_BY_SEVERITY.indexOf( label.trim().toLowerCase() );

	for ( let i = 1; i < levelValues.length; i++ ) {
		if ( rankOf( levelValues[ i ] ) < rankOf( levelValues[ i - 1 ] ) ) {
			return false;
		}
	}

	return true;
}

// Matches Table_View_Module::DEFAULT_COLUMNS in premium's PHP, which gained
// 'event_id' — keep the two in step, or "reset to default" asserts against a
// column set the product no longer has.
const DEFAULT_COLUMNS = [ 'event_id', 'date', 'user', 'message', 'level' ];

// Matches Table_View_Module::KNOWN_COLUMNS in premium's PHP — every optional
// column at once, which is the state that overflows a row's width soonest
// (see the layout test below). Storing 'reactions' when the site has
// reactions turned off is harmless: sanitize_columns() on the server just
// drops ids get_available_columns() does not currently offer, so this list
// does not need to special-case that setting the way the Reactions column
// test above does.
const ALL_COLUMNS = [
	'date',
	'user',
	'message',
	'level',
	'logger',
	'event_type',
	'ip',
	'reactions',
];

/**
 * Wait until the table has really finished its first load.
 *
 * A `.shp-TableView__row` appears in the DOM before the table's row model is
 * populated, so waiting on a row alone is not enough. Two tests failed about
 * one run in two because of it, on `main` as well as on this branch:
 *
 * - Row controls derive from the row model, so they render against nothing
 *   and clicking one toggles nothing — Playwright reports "Clicking the
 *   checkbox did not change its state", which reads like a broken control
 *   rather than a race.
 * - Scrolling to the bottom fires maybeLoadMore() while there is still
 *   nothing to page past, and since scrollTop is then already at the bottom,
 *   setting it again emits no further scroll event — so the page=2 request
 *   the test is waiting for never happens.
 *
 * A row checkbox's own label is the readable proof that the row model has
 * caught up: it is built from `row.original.id`, so it cannot read as an
 * event id until there is a real row behind it. This used to watch the
 * select-all checkbox's count for the same reason; that control is gone.
 *
 * @param {import('@playwright/test').Page} page The page.
 */
async function waitForLoadedRows( page ) {
	await page.locator( '.shp-TableView__row' ).first().waitFor();

	await expect
		.poll( async () =>
			page
				.locator( '.shp-TableView__row input[type="checkbox"]' )
				.first()
				.getAttribute( 'aria-label' )
		)
		.toMatch( /\d+/ );
}

/**
 * Save the admin's stored table columns directly, so a test starts from a
 * known column set and can be restored to the defaults afterwards.
 *
 * @param {Object}   requestUtils
 * @param {string[]} columns
 */
/**
 * The number in the table's own "N matching events" line.
 *
 * Returns null while the line is absent or has not been given a number yet,
 * so a caller can poll on it rather than racing the first response.
 *
 * @param {Object} page Playwright page.
 * @return {Promise<number|null>} The count.
 */
async function readTotal( page ) {
	const text = await page
		.locator( '.shp-TableView__total' )
		.textContent()
		.catch( () => null );

	if ( ! text ) {
		return null;
	}

	const digits = text.replace( /[^\d]/g, '' );

	return digits === '' ? null : Number( digits );
}

/**
 * What the chart's key adds up to: the two swatch totals, summed.
 *
 * These are the bars' own numbers, so they describe the chart and not the
 * table — which is exactly the difference the hour note exists to explain.
 *
 * @param {Object} page Playwright page.
 * @return {Promise<number>} Info-or-lower plus warning-or-higher.
 */
async function readChartKeyTotal( page ) {
	const keys = await page
		.locator( '.shp-TableView__histogramKey' )
		.allTextContents();

	return keys.reduce(
		( sum, text ) => sum + Number( text.replace( /[^\d]/g, '' ) || 0 ),
		0
	);
}

async function setStoredColumns( requestUtils, columns ) {
	await requestUtils.rest( {
		method: 'POST',
		path: 'simple-history/v1/premium/events-table-columns',
		data: { columns },
	} );
}

test.describe( 'Premium table view', () => {
	test.describe.configure( { mode: 'serial' } );

	test.afterAll( async ( { requestUtils } ) => {
		await setStoredView( requestUtils, 'detailed' );
	} );

	// This preference is stored per user on a shared dev site, so a hidden
	// column left behind by the test below would silently change what later
	// specs and the developer see. Restore the default set every run, not
	// just when the test passes.
	test.afterAll( async ( { requestUtils } ) => {
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );
	} );

	// Set by the Reactions column test below, so this always runs even if an
	// assertion fails partway through — the same pattern as
	// reactions-emoji.spec.js, and needed here for the same reason: a
	// reaction left behind on the dev site's log is state later specs and
	// the developer would see too.
	let reactedEventId = null;

	test.afterEach( async ( { page } ) => {
		if ( ! reactedEventId ) {
			return;
		}

		await page.evaluate( async ( eventId ) => {
			await window.wp.apiFetch( {
				path: `/simple-history/v1/events/${ eventId }/unreact`,
				method: 'POST',
				data: { type: 'thumbsup' },
			} );
		}, reactedEventId );

		reactedEventId = null;
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
		await waitForLoadedRows( page );

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

	test( 'clicking the date does not open anything', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// The date is plain text now, not a button — clicking a date to see
		// details was an implementation detail leaking into the interface.
		// Details live in the row actions menu instead (see the tests below).
		await page
			.locator( '.shp-TableView__row' )
			.first()
			.locator( '.shp-TableView__td--date' )
			.click();

		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
	} );

	test( 'the row actions menu opens on top of the table and its Details item opens the modal for that row', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// Not the first row: the menu carries eleven items and is tall
		// enough to flip upward past a row this close to the top of a
		// short page, which is a real but unrelated quirk of a tall
		// popover with little room below it. A row with room below keeps
		// this test about the stacking behaviour under test, not that.
		const row = page.locator( '.shp-TableView__row' ).nth( 4 );
		const eventId = await row.getAttribute( 'data-event-id' );

		// The dropdown's accessible name identifies its row (see
		// dropdownLabel in PremiumTableView.jsx) rather than reading
		// "Actions…" identically down the whole column.
		const actionsButton = row.getByRole( 'button', {
			name: `Actions for event ${ eventId }`,
		} );

		await expect( actionsButton ).toBeVisible();
		await actionsButton.click();

		const detailsMenuItem = page.getByRole( 'menuitem', {
			name: 'View event details',
		} );
		await expect( detailsMenuItem ).toBeVisible();

		// The table virtualises rows with a `transform` (a new CSS
		// containing block) inside a scrolling `overflow` container —
		// both of which trap an inline popover behind or clipped by
		// sibling rows instead of on top of them (see popoverProps in
		// PremiumTableView.jsx). `toBeVisible()` alone would not catch
		// that: a clipped or buried element can still report visible
		// while unusable. `elementFromPoint` at the item's own on-screen
		// coordinates returning the item itself, rather than a table row
		// painted over it, is a direct test of the stacking bug — it
		// fails against the broken render, where row text sits above the
		// menu instead of under it.
		const isHitTestable = await detailsMenuItem.evaluate( ( el ) => {
			const rect = el.getBoundingClientRect();
			const hit = document.elementFromPoint(
				rect.left + rect.width / 2,
				rect.top + rect.height / 2
			);
			return el === hit || el.contains( hit );
		} );
		expect( isHitTestable ).toBe( true );

		await detailsMenuItem.click();

		const dialog = page.getByRole( 'dialog' );
		await expect( dialog ).toBeVisible();

		// Not just "a dialog appeared" — the modal's own event-details table
		// carries the id of the event it loaded, so this confirms it opened
		// the clicked row's event and not merely the first/last one shown.
		await expect( dialog.locator( 'td:text-is("id") + td' ) ).toHaveText(
			eventId
		);
	} );

	test( 'clicking the row actions button does not toggle row selection or open details', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const firstRow = page.locator( '.shp-TableView__row' ).first();
		const eventId = await firstRow.getAttribute( 'data-event-id' );

		const actionsButton = firstRow.getByRole( 'button', {
			name: `Actions for event ${ eventId }`,
		} );

		await actionsButton.click();
		await page.keyboard.press( 'Escape' );

		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
		await expect(
			firstRow.locator( 'input[type="checkbox"]' )
		).not.toBeChecked();
	} );

	test( 'clicking a row checkbox does not open the event details modal', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page
			.locator( '.shp-TableView__row input[type="checkbox"]' )
			.first()
			.check();

		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );
	} );

	test( 'clicking a sortable header reorders the whole result set', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// Level is sortable and uncorrelated with insertion order, so a real
		// server-side sort changes what is shown. The first click on a column
		// that was not already active sorts descending (see toggleSort() in
		// use-table-sort.js); the second click flips it to ascending, which
		// is the direction this assertion checks.
		const levelHeaderButton = page.getByRole( 'button', {
			name: /Level/,
		} );

		// aria-sort belongs on the <th>, not the button inside it, per
		// ARIA's authoring practices for a sortable column header.
		const levelHeaderCell = page.getByRole( 'columnheader', {
			name: /Level/,
		} );

		await levelHeaderButton.click();
		await levelHeaderButton.click();

		// A positive wait, not the aria-busy-cleared checks this replaced.
		// Those were negative assertions (`not.toHaveAttribute`), which pass
		// the moment they are first polled if the sort fetch has not started
		// yet — aria-busy is only ever "true" while one is in flight — so the
		// chain could short-circuit before the server-side sort even began.
		// Waiting for aria-sort="ascending" instead can only pass once the
		// second click's toggle has actually landed.
		await expect( levelHeaderCell ).toHaveAttribute(
			'aria-sort',
			'ascending'
		);

		// Data-independent: asserting the first row's id changed is data-
		// dependent — it can fail with nothing wrong if the log's own order
		// already happened to put a different row first, or pass with
		// nothing right if the visible levels are all the same. Checking
		// that the Level column's visible values come back in non-decreasing
		// order tests the actual claim instead — that the server sorted by
		// that column — not a side effect that usually but not always
		// follows it.
		//
		// The server sorts the raw `level` column alphabetically, and the
		// visible labels (LOG_LEVEL_LABELS in PremiumTableView.jsx) are just
		// capitalised versions of the same words, so a case-insensitive
		// comparison of consecutive values is enough to catch a sort that
		// never reached the server.
		//
		// aria-sort turning "ascending" only means the client-side toggle
		// landed, not that the fetch has settled or that the virtualizer has
		// mounted rows for the new data yet, and allTextContents() is a
		// point-in-time read that cannot retry on its own. Wrapped in
		// expect.poll so a read that lands before the new rows have painted
		// retries instead of failing once against a table that has not
		// caught up — and returning the values alongside the verdict means a
		// failure prints the actual Level column contents (via Playwright's
		// standard Received: output), not a bare boolean.
		await expect
			.poll( async () => {
				const levelValues = await page
					.locator( '.shp-TableView__td--level' )
					.allTextContents();

				return {
					isNonDecreasing: isLevelColumnNonDecreasing( levelValues ),
					levelValues,
				};
			} )
			.toMatchObject( { isNonDecreasing: true } );
	} );

	test( 'sort survives a reload through the URL', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto(
			SIMPLE_HISTORY_PAGE +
				'&view=table&table_orderby=level&table_order=asc'
		);
		await waitForLoadedRows( page );

		// aria-sort belongs on the <th>, not the button inside it, per ARIA's
		// authoring practices for a sortable column header — so this checks
		// the header cell rather than the button the brief's sample used.
		const levelHeaderCell = page.getByRole( 'columnheader', {
			name: /Level/,
		} );
		await expect( levelHeaderCell ).toHaveAttribute(
			'aria-sort',
			'ascending'
		);
	} );

	test( 'selecting rows reveals a bulk bar with the count', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// One strip above the table, at a fixed height, so that ticking a
		// checkbox does not push the table down the page. With nothing
		// selected it is untinted and announces no count.
		const bulkBar = page.locator( '.shp-TableView__toolbar' );
		await expect( bulkBar ).not.toHaveClass( /--selected/ );
		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toHaveCount( 0 );

		const restingHeight = await bulkBar.evaluate(
			( element ) => element.getBoundingClientRect().height
		);

		const checkboxes = page.locator(
			'.shp-TableView__row input[type="checkbox"]'
		);
		await checkboxes.nth( 0 ).check();
		await checkboxes.nth( 1 ).check();

		await expect( bulkBar ).toBeVisible();
		await expect( bulkBar ).toHaveClass( /--selected/ );
		await expect( bulkBar ).toContainText( '2' );

		// The whole point of the resting state: filling the bar must not
		// change its height, or the table jumps as the first row is ticked.
		const selectedHeight = await bulkBar.evaluate(
			( element ) => element.getBoundingClientRect().height
		);

		expect( selectedHeight ).toBe( restingHeight );
	} );

	// Regression, and a deliberate absence. There was a select-all checkbox in
	// the header; on an infinitely scrolled list it could only ever take what
	// had been fetched, which is not a set anyone chose — it is how far they
	// happened to scroll. Saying that honestly needed a second line, a second
	// number and a Gmail-style "select all 14,054 matching" step, after which
	// three numbers described one screen and Copy could honour none of them.
	//
	// The scope it was reaching for is Export's job, so the checkbox went and
	// Export moved into the bar where it is always available. This asserts
	// the absence, because re-adding the checkbox is the obvious "fix" for a
	// bug report that says you cannot select everything.
	test( 'there is no select-all, and Export covers that scope instead', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await expect(
			page.locator( '.shp-TableView__table thead input[type="checkbox"]' )
		).toHaveCount( 0 );

		// With nothing ticked the strip still offers the wider scope, and the
		// modal states it rather than leaving it to be inferred.
		const bulkBar = page.locator( '.shp-TableView__toolbar' );
		await expect( bulkBar ).not.toHaveClass( /--selected/ );

		await page.getByRole( 'button', { name: 'Export all' } ).click();

		const exportDialog = page.getByRole( 'dialog' );
		await expect( exportDialog ).toContainText(
			'every event matching your current filters'
		);

		await page.keyboard.press( 'Escape' );
	} );

	// Export used to live in core's control bar, whose query params carry
	// core's filter state and not the table's — not the typed query, not the
	// histogram's hour, not the group-by. Side by side the two buttons
	// offered 3,283 and 14,123 events for the same screen, and the smaller
	// one was the one that looked official. Export moved into the table's own
	// bar, and premium hides the control bar's copy while this view is on.
	test( 'Export offers the same total the table says it is showing', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// Only one Export on screen, and it is the table's.
		await expect(
			page.getByRole( 'button', { name: 'Export', exact: true } )
		).toHaveCount( 0 );

		const total = page.locator( '.shp-TableView__total' );
		await expect( total ).toContainText( /\d/ );
		const digits = ( await total.innerText() ).replace( /[^\d]/g, '' );

		await page.getByRole( 'button', { name: 'Export all' } ).click();

		await expect(
			page
				.getByRole( 'dialog' )
				.getByRole( 'button', { name: /^Export \d+ event/ } )
		).toContainText( digits );

		await page.keyboard.press( 'Escape' );
	} );

	// The bar carries controls in both states, so it must not change height
	// when a row is ticked — that movement is the whole reason it is
	// always-on rather than appearing with the first selection.
	test( 'the bar is the same height with and without a selection', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const bulkBarRow = page.locator( '.shp-TableView__toolbar' );
		const heightOf = () =>
			bulkBarRow.evaluate( ( el ) => el.getBoundingClientRect().height );

		const restingHeight = await heightOf();

		const checkbox = page
			.locator( '.shp-TableView__row input[type="checkbox"]' )
			.first();
		await checkbox.check();

		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toHaveText( '1 event selected' );

		expect( await heightOf() ).toBe( restingHeight );

		await checkbox.uncheck();

		await expect(
			page.locator( '.shp-TableView__toolbar--selected' )
		).toHaveCount( 0 );
		expect( await heightOf() ).toBe( restingHeight );
	} );

	test( 'ticking one row does not move the table', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const table = page.locator( '.shp-TableView__scrollContainer' );
		const topOf = () =>
			table.evaluate( ( el ) => el.getBoundingClientRect().top );

		const before = await topOf();
		const checkbox = page
			.locator( '.shp-TableView__row input[type="checkbox"]' )
			.first();

		await checkbox.check();
		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toBeVisible();

		expect( await topOf() ).toBe( before );

		await checkbox.uncheck();
		await expect(
			page.locator( '.shp-TableView__toolbar--selected' )
		).toHaveCount( 0 );

		expect( await topOf() ).toBe( before );
	} );

	test( 'export sends the ids selected before a sort, not the rows visible after it', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const checkboxes = page.locator(
			'.shp-TableView__row input[type="checkbox"]'
		);
		await checkboxes.nth( 0 ).check();
		await checkboxes.nth( 1 ).check();
		await checkboxes.nth( 2 ).check();

		// Record which events were selected before the sort re-fetches the
		// table. If selection were ever keyed by row index instead of event
		// id, this is the set that would silently drift once the sort
		// reorders (or drops) rows.
		const selectedIdsBeforeSort = await page
			.locator( '.shp-TableView__row' )
			.evaluateAll( ( rows ) =>
				rows
					.filter(
						( row ) =>
							row.querySelector( 'input[type="checkbox"]' )
								.checked
					)
					.map( ( row ) =>
						Number( row.getAttribute( 'data-event-id' ) )
					)
			);

		expect( selectedIdsBeforeSort ).toHaveLength( 3 );

		// Level reorders enough to change which events fall into the loaded
		// page, so a selected row can leave the DOM entirely — the case a
		// row-index-based selection could not survive.
		await page.getByRole( 'button', { name: /Level/ } ).click();

		await expect(
			page.locator( '.shp-TableView__table' )
		).not.toHaveAttribute( 'aria-busy', 'true' );

		let exportRequestBody = null;

		await page.route(
			'**/simple-history/v1/premium/export**',
			async ( route ) => {
				exportRequestBody = route.request().postDataJSON();

				// Fulfil with an empty CSV so the modal's download handling
				// completes without the test actually saving a file.
				await route.fulfill( {
					status: 200,
					contentType: 'text/csv',
					body: '',
				} );
			}
		);

		await page
			.locator( '.shp-TableView__toolbar' )
			.getByRole( 'button', { name: 'Export' } )
			.click();

		await page.getByRole( 'button', { name: /Export \d+ events/ } ).click();

		await expect.poll( () => exportRequestBody ).not.toBeNull();

		const exportedIds = exportRequestBody.query.post__in;

		expect( [ ...exportedIds ].sort() ).toEqual(
			[ ...selectedIdsBeforeSort ].sort()
		);
	} );

	test( 'hiding a column persists across a reload', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await expect(
			page.getByRole( 'columnheader', { name: /Level/ } )
		).toBeVisible();

		// Saving is fire-and-forget (see saveColumns() in PremiumTableView.jsx):
		// the checkbox applies locally at once and the POST lands in the
		// background. Waiting for that response here — rather than reloading
		// the instant the checkbox unchecks — mirrors how a reader would
		// actually reload a moment later; without it, this reload can race
		// the save and read the column back from before the toggle.
		const saveResponse = page.waitForResponse(
			( response ) =>
				response.url().includes( 'events-table-columns' ) &&
				response.request().method() === 'POST'
		);

		await page.getByRole( 'button', { name: /Columns/ } ).click();
		await page.getByRole( 'checkbox', { name: /Level/ } ).uncheck();
		await page.keyboard.press( 'Escape' );

		await expect(
			page.getByRole( 'columnheader', { name: /Level/ } )
		).toHaveCount( 0 );

		await saveResponse;

		// The preference is stored per user, so a fresh load keeps it.
		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await expect(
			page.getByRole( 'columnheader', { name: /Level/ } )
		).toHaveCount( 0 );
	} );

	test( 'scrolling to the bottom loads more events', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const initialCount = await page
			.locator( '.shp-TableView__row' )
			.count();

		// Row count growing on its own does not prove loadMore() fetched
		// anything: the virtualizer keeps a bounded number of rows mounted,
		// and its overscan window sliding forward over rows from the first
		// page (already in memory) can grow the mounted count without a
		// second request ever firing. Assert the request itself lands, with
		// the count-rule flags a scroll-triggered fetch must carry, and keep
		// the row-count check alongside it: the response proves the fetch
		// happened, the row count proves the result reached the DOM.
		const nextPageResponse = page.waitForResponse(
			( response ) =>
				response.url().includes( '/simple-history/v1/events' ) &&
				response.url().includes( 'page=2' ) &&
				response.url().includes( 'skip_count_query=true' )
		);

		// scrollIntoViewIfNeeded() on the last row only brings whatever is
		// currently mounted into view — with virtualisation, that is a
		// handful of rows past the visible viewport (the overscan buffer),
		// nowhere near the true bottom of the rows already loaded. Setting
		// scrollTop to scrollHeight directly is what "scrolling to the
		// bottom" means here: the loaded-so-far bottom, which is what
		// should make loadMore() fire.
		await page
			.locator( '.shp-TableView__scrollContainer' )
			.evaluate( ( el ) => {
				el.scrollTop = el.scrollHeight;
			} );

		const response = await nextPageResponse;
		expect( response.ok() ).toBe( true );

		await expect
			.poll(
				async () => await page.locator( '.shp-TableView__row' ).count(),
				{ timeout: 10000 }
			)
			.toBeGreaterThan( initialCount );
	} );

	test( 'loading more events is not confused by events logged while scrolling', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		// The ID column is the only thing that can tell a re-served row from
		// a new one, so this test needs it on.
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const idsOnScreen = async () =>
			page
				.locator( '.shp-TableView__col--event_id' )
				.allTextContents()
				.then( ( values ) =>
					values.map( ( value ) => value.trim() ).filter( Boolean )
				);

		// Newest first, which is the order that makes this hard: the log
		// grows at the top, so anything logged now pushes every loaded row
		// one place further down. Offset pagination then asks for rows
		// 101-200 of a list whose rows 1-100 are no longer the ones already
		// on screen, and page 2 comes back carrying rows the reader has.
		const before = await idsOnScreen();
		expect( before.length ).toBeGreaterThan( 0 );

		// Ten of them, so the overlap is unmistakable rather than a
		// one-row coincidence.
		for ( let index = 0; index < 10; index++ ) {
			await requestUtils.rest( {
				method: 'POST',
				path: 'simple-history/v1/events',
				data: {
					message: `Playwright concurrent write ${ index }`,
					level: 'info',
					// A note, so these events carry a details panel like any
					// other. Without one they render as detail-less rows,
					// and since they land at the top of a shared dev log
					// they became whatever the next test happened to pick —
					// which is how this test broke the row-expand one.
					note: `Row ${ index } written while the reader scrolled.`,
				},
			} );
		}

		const nextPageResponse = page.waitForResponse(
			( response ) =>
				response.url().includes( '/simple-history/v1/events' ) &&
				response.url().includes( 'page=2' )
		);

		await page
			.locator( '.shp-TableView__scrollContainer' )
			.evaluate( ( el ) => {
				el.scrollTop = el.scrollHeight;
			} );

		expect( ( await nextPageResponse ).ok() ).toBe( true );

		await expect
			.poll( async () => ( await idsOnScreen() ).length, {
				timeout: 10000,
			} )
			.toBeGreaterThan( before.length );

		const after = await idsOnScreen();

		// No event renders twice. A duplicate here would not just look
		// wrong: two rows sharing one id share one selection entry, so
		// ticking either ticks both and an export sends the event twice.
		expect( new Set( after ).size ).toBe( after.length );

		// And nothing already on screen was dropped to make room.
		for ( const id of before ) {
			expect( after ).toContain( id );
		}

		// Deliberately no assertion that the ids run strictly downwards.
		// The table is sorted by date, and events logged inside the same
		// second share a timestamp — so id order and row order are allowed
		// to disagree, and pinning it here would fail on a busy log for a
		// reason that has nothing to do with paging.
	} );

	test( "expanding a row fetches and shows details for that row's event, collapsing hides them again", async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// Not the first row: picking a row a few down and checking the fetch
		// carries that exact id is what tells this apart from a bug that
		// always expands whichever event happens to be first/last.
		const row = page.locator( '.shp-TableView__row' ).nth( 3 );
		const eventId = await row.getAttribute( 'data-event-id' );

		const detailsRequest = page.waitForResponse( ( response ) => {
			const { pathname } = new URL( response.url() );

			return pathname.endsWith(
				`/simple-history/v1/events/${ eventId }`
			);
		} );

		await row
			.getByRole( 'button', {
				name: `Show details for event ${ eventId }`,
			} )
			.click();

		const response = await detailsRequest;
		expect( response.ok() ).toBe( true );

		const panel = page.locator(
			`#shp-TableView__detailsPanel-${ eventId }`
		);
		await expect( panel ).toBeVisible();

		// EventDetails always renders this wrapper, even for an event whose
		// details_html happens to be empty — so this confirms core's
		// component rendered inside the panel rather than depending on any
		// particular event carrying non-empty content.
		await expect(
			panel.locator( '.SimpleHistoryLogitem__details' )
		).toHaveCount( 1 );

		const hideToggle = row.getByRole( 'button', {
			name: `Hide details for event ${ eventId }`,
		} );
		await expect( hideToggle ).toHaveAttribute( 'aria-expanded', 'true' );

		await hideToggle.click();

		await expect( panel ).toHaveCount( 0 );
		await expect(
			row.getByRole( 'button', {
				name: `Show details for event ${ eventId }`,
			} )
		).toHaveAttribute( 'aria-expanded', 'false' );
	} );

	test( 'expanding a row does not misposition the rows around it while scrolling', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// A row with rows both above and below it, so growing it taller than
		// the virtualizer's collapsed-row estimate can misplace neighbours on
		// either side, not just below.
		const rowToExpand = page.locator( '.shp-TableView__row' ).nth( 2 );
		const eventId = await rowToExpand.getAttribute( 'data-event-id' );

		await rowToExpand
			.getByRole( 'button', {
				name: `Show details for event ${ eventId }`,
			} )
			.click();

		const panel = page.locator(
			`#shp-TableView__detailsPanel-${ eventId }`
		);
		await expect( panel ).toBeVisible();

		// Scroll to the loaded bottom and back to the top, so every currently
		// loaded row passes through the virtualizer's mount/unmount cycle at
		// least once with the expanded row's real (taller) height in play.
		const scrollContainer = page.locator(
			'.shp-TableView__scrollContainer'
		);

		await scrollContainer.evaluate( ( el ) => {
			el.scrollTop = el.scrollHeight;
		} );
		await page.waitForTimeout( 300 );

		await scrollContainer.evaluate( ( el ) => {
			el.scrollTop = 0;
		} );
		await page.waitForTimeout( 300 );

		// No two rows the virtualizer currently has mounted may overlap
		// vertically. An un-measured expanded row leaves every row below it
		// positioned at its old, collapsed-height offset — this is the direct
		// symptom of that: the next row's top ends up above the expanded
		// row's real bottom edge.
		const rowRects = await page
			.locator( '.shp-TableView__row' )
			.evaluateAll( ( rows ) =>
				rows
					.map( ( row ) => row.getBoundingClientRect() )
					.sort( ( a, b ) => a.top - b.top )
			);

		expect( rowRects.length ).toBeGreaterThan( 1 );

		for ( let i = 1; i < rowRects.length; i++ ) {
			// 1px slack for sub-pixel rounding between measured layout and
			// the virtualizer's translateY offsets.
			expect( rowRects[ i ].top ).toBeGreaterThanOrEqual(
				rowRects[ i - 1 ].bottom - 1
			);
		}

		// Expansion is centralised state (useTableRowDetails), not state on
		// the row's own DOM node, so scrolling the row out of the
		// virtualizer's mounted window and back does not lose it.
		await expect( panel ).toBeVisible();
	} );

	test( 'switching out of table view never renders a stub event', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		// Core's redundant fetch for table view is debounced (500ms) and only
		// fires once the view state has actually settled on "table" — a quick
		// automated flip in and back out again coalesces to a single fetch
		// for the final ("detailed") state and never reproduces the bug.
		// A real user browsing table view for more than a moment does let it
		// land, so wait for it here: this is core's own trimmed request
		// (per_page=1), not Premium's separate, real one.
		const tableViewFetch = page.waitForResponse(
			( response ) =>
				isCoreEventsListRequest( response.url() ) &&
				response.url().includes( 'per_page=1' )
		);

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView' ).waitFor();
		await tableViewFetch;

		// Core's own events request in table view is trimmed to per_page: 1
		// (Premium fetches and renders the real rows). A prior bug wrote that
		// one-row, id-and-date-only response into core's events/eventsMeta
		// state anyway, so switching back to Detailed briefly rendered that
		// single stub row — "Show NaN similar events" (subsequent_occasions_count
		// is missing), an empty initiator, and a pager reading "Page 1 of
		// <event count>" because totalPages came from a per_page: 1 response.
		// The debounced refetch (500ms) then overwrites it with the real
		// list, so a check made after that settles would pass even with the
		// bug present. A MutationObserver installed before the view switch
		// catches every DOM mutation in between instead of sampling on an
		// interval and risking a miss between polls.
		await page.evaluate( () => {
			window.__shStubEventSeen = false;
			window.__shMaxPagerTotalSeen = 0;

			const root = document.getElementById( 'simple-history-react-root' );

			const scan = () => {
				if ( root.innerText.includes( 'NaN' ) ) {
					window.__shStubEventSeen = true;
				}

				// EventsPagination renders one <option> per page plus a
				// leading "…" custom-page option, so option count minus one
				// is the totalPages it is currently showing.
				const pagerSelect = root.querySelector(
					'select[aria-label="Current page"]'
				);

				if ( pagerSelect ) {
					const totalPagesShown = pagerSelect.options.length - 1;

					window.__shMaxPagerTotalSeen = Math.max(
						window.__shMaxPagerTotalSeen,
						totalPagesShown
					);
				}
			};

			scan();

			window.__shObserver = new MutationObserver( scan );
			window.__shObserver.observe( root, {
				childList: true,
				subtree: true,
				characterData: true,
			} );
		} );

		// The real, untrimmed refetch this view switch triggers — as opposed
		// to core's still-pending or already-settled per_page: 1 one.
		const detailedViewFetch = page.waitForResponse(
			( response ) =>
				isCoreEventsListRequest( response.url() ) &&
				! response.url().includes( 'per_page=1' )
		);

		await page.getByRole( 'button', { name: 'Detailed view' } ).click();

		await detailedViewFetch;

		// Let the resolved response's setState calls actually commit before
		// reading the DOM below — otherwise this can read the stub's
		// still-current render from the microtask queue.
		await page.waitForTimeout( 100 );

		const { stubEventSeen, maxPagerTotalSeen, realTotalPages } =
			await page.evaluate( () => {
				window.__shObserver.disconnect();

				const pagerSelect = document
					.getElementById( 'simple-history-react-root' )
					.querySelector( 'select[aria-label="Current page"]' );

				return {
					stubEventSeen: window.__shStubEventSeen,
					maxPagerTotalSeen: window.__shMaxPagerTotalSeen,
					realTotalPages: pagerSelect
						? pagerSelect.options.length - 1
						: null,
				};
			} );

		expect( stubEventSeen ).toBe( false );

		// The real, settled totalPages divides the event count by the page
		// size, so it is small. The stub's per_page: 1 response set
		// totalPages to the event count itself (hundreds), one "page" per
		// event — if the pager ever showed more pages than the correct,
		// settled value, that leaked through.
		expect( realTotalPages ).not.toBeNull();
		expect( maxPagerTotalSeen ).toBeLessThanOrEqual( realTotalPages );
	} );

	test( 'the Level column shows each row as a pill carrying the plain level name', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const firstLevelCell = page
			.locator( '.shp-TableView__row' )
			.first()
			.locator( '.shp-TableView__td--level' );

		const pill = firstLevelCell.locator( '.shp-TableView__levelPill' );
		await expect( pill ).toBeVisible();

		// The pill carries a per-level modifier class (for example
		// shp-TableView__levelPill--info), which is what the pill's colour
		// hangs off — see PremiumTableView.scss.
		await expect( pill ).toHaveClass( /shp-TableView__levelPill--[a-z]+/ );

		// The cell's whole text is the pill's text and nothing else — the
		// sort test above reads .shp-TableView__td--level directly, so
		// wrapping the label in a pill must not add or hide any of it.
		const cellText = ( await firstLevelCell.textContent() ).trim();
		const pillText = ( await pill.textContent() ).trim();
		expect( pillText ).toBe( cellText );
		expect( pillText.length ).toBeGreaterThan( 0 );
	} );

	test( 'the Reactions column can be enabled through the columns menu and shows a count', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page.getByRole( 'button', { name: /Columns/ } ).click();

		const reactionsCheckbox = page.getByRole( 'checkbox', {
			name: /Reactions/,
		} );

		// Table_View_Module::get_available_columns() only offers this column
		// when Helpers::reactions_are_enabled() is true on the site — see the
		// gating comment there. The dev site has reactions on (confirmed via
		// /simple-history/v1/search-options' reactions_enabled), but skip
		// cleanly rather than fail if a future run finds it off.
		if ( ( await reactionsCheckbox.count() ) === 0 ) {
			test.skip(
				true,
				'Reactions are not enabled on this install, so the column is not offered.'
			);
		}

		const eventId = await page
			.locator( '.shp-TableView__row' )
			.first()
			.getAttribute( 'data-event-id' );

		// React to the first loaded row's event via the REST API — same
		// cookie-authenticated wp.apiFetch pattern as reactions-emoji.spec.js
		// — so the column has something other than zero to show.
		const reactResponse = await page.evaluate( async ( id ) => {
			return window.wp.apiFetch( {
				path: `/simple-history/v1/events/${ id }/react`,
				method: 'POST',
				data: { type: 'thumbsup' },
			} );
		}, eventId );
		reactedEventId = eventId;

		expect( reactResponse.reactions.thumbsup.count ).toBeGreaterThan( 0 );

		const saveResponse = page.waitForResponse(
			( response ) =>
				response.url().includes( 'events-table-columns' ) &&
				response.request().method() === 'POST'
		);

		await reactionsCheckbox.check();
		await page.keyboard.press( 'Escape' );
		await saveResponse;

		await expect(
			page.getByRole( 'columnheader', { name: /Reactions/ } )
		).toBeVisible();

		// Enabling the column restarts the table's own fetch from page 1
		// with the `reactions` field now requested (see includeReactions in
		// use-table-events.js), so look the row up by event id rather than
		// position — matches the pattern the export test above uses for the
		// same reason.
		const row = page.locator(
			`.shp-TableView__row[data-event-id="${ eventId }"]`
		);
		await row.waitFor();

		const reactionPill = row
			.locator( '.shp-TableView__reactionPill' )
			.first();
		await expect( reactionPill ).toBeVisible();
		await expect( reactionPill ).toContainText( '1' );
	} );

	// Regression test for a bug where .shp-TableView__row's flex-wrap: wrap
	// (there to drop the details panel to its own line — see
	// PremiumTableView.scss) also wrapped a data cell once the visible
	// columns' widths added up to more than the row's width: the Actions
	// cell broke onto a second line at the far left, doubling every row's
	// height. Every other test in this file only reads cell *content*, so
	// none of them would fail against that render — the row is still there,
	// still holding the right text, just twice as tall. This checks
	// geometry instead: every cell's top edge must match the others', for
	// every column combination, since more columns is exactly what made the
	// row wrap in the first place.
	for ( const [ label, columns ] of [
		[ 'default columns', DEFAULT_COLUMNS ],
		[ 'every optional column enabled', ALL_COLUMNS ],
	] ) {
		test( `no cell in a row wraps onto its own line (${ label })`, async ( {
			page,
			requestUtils,
		} ) => {
			await setStoredView( requestUtils, 'detailed' );
			await setStoredColumns( requestUtils, columns );

			await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
			await waitForLoadedRows( page );

			// A handful of rows, not just the first: the virtualizer mounts
			// several at once and a wrap bug affects all of them the same
			// way, so checking more than one is cheap insurance against a
			// fix that only happens to line up the first row.
			// Measured through a poll, not a single read. Waiting for the
			// first row only proves a row existed at that instant; the
			// table replaces its rows when the first fetch settles, and a
			// measurement taken in that window sees none at all — which
			// used to surface as an empty result rather than as a wrap.
			const measureRows = () =>
				page.locator( '.shp-TableView__row' ).evaluateAll( ( rows ) =>
					rows.slice( 0, 5 ).map( ( row ) => {
						// Excludes the (collapsed, here) details panel
						// on purpose — that cell is *meant* to sit on
						// its own line below the others; it is the data
						// cells beside it in .shp-TableView__rowCells
						// that must never wrap.
						const cellTops = Array.from(
							row.querySelectorAll(
								'.shp-TableView__rowCells > .shp-TableView__td'
							)
						).map( ( cell ) => cell.getBoundingClientRect().top );

						return {
							min: Math.min( ...cellTops ),
							max: Math.max( ...cellTops ),
							count: cellTops.length,
						};
					} )
				);

			await expect
				.poll( async () => ( await measureRows() ).length )
				.toBeGreaterThan( 0 );

			const rowTops = await measureRows();

			for ( const { min, max, count } of rowTops ) {
				// At least expand, select, message and actions — a count of
				// 0 or 1 here would mean the selector above matched nothing
				// (or one cell) and the top comparison below passed
				// vacuously. Not an exact count: 'reactions' silently drops
				// out of ALL_COLUMNS on an install with reactions turned
				// off (see sanitize_columns()), which must not fail this
				// test — it is a real, supported column set, just not this
				// one's full length everywhere.
				expect( count ).toBeGreaterThan( 3 );

				// Sub-pixel slack only: a wrapped cell lands a full row
				// height (around 40px) below the others, nowhere near this
				// tolerance.
				expect( max - min ).toBeLessThanOrEqual( 1 );
			}
		} );
	}

	// Regression test for two totals disagreeing on screen: core's control
	// bar counts grouped occasions (repeated events collapsed into one),
	// while the table counts individual events, because a table row has to
	// be one event — see TableTotal() in PremiumTableView.jsx and the
	// isTableView guard on eventsCount in EventsControlBar.jsx. Both numbers
	// are correct for what they each measure, but showing both at once on
	// one screen reads as a bug, so the control bar's own total is hidden
	// while table view is active — the table already has one of its own.
	test( "table view hides the control bar's own total, since the table already shows one", async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// The table's own total, e.g. "2,915 matching events" — confirms
		// this landed before checking the control bar's total is gone,
		// rather than passing vacuously while the table is still loading.
		await expect( page.locator( '.shp-TableView__total' ) ).toContainText(
			/\d/
		);

		// Anchored and excluding "new" so this cannot match the unrelated
		// "N new events" notifier that lives in the same control bar —
		// eventsCount's own text is exactly "N events" or "N matching
		// events" with nothing else in the string (see EventsControlBar.jsx).
		const controlBarTotal = page
			.locator( '.sh-EventsControlBar-actions' )
			.getByText( /^[\d,.\s]+(matching )?events?$/ );

		await expect( controlBarTotal ).toHaveCount( 0 );

		// Switching to Detailed brings the control bar's own total straight
		// back — this is scoped to table view, not a total removed for
		// good.
		await page.getByRole( 'button', { name: 'Detailed view' } ).click();

		await expect( controlBarTotal ).toBeVisible();
	} );

	// Regression: every per-column style rule used to hang off a `__td--`
	// class, which only reached body cells. The expand column's narrower
	// padding therefore applied to cells and not to its header, and with
	// content-box sizing that shifted every header label from Date onwards
	// 12px right of the data underneath it — visible without measuring, and
	// invisible to every assertion in this file, which all read text.
	test( 'every header cell lines up with its own column of data', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, ALL_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// Content position, not box position. Since box-sizing is border-box
		// the boxes line up on their widths alone, so comparing those would
		// pass even with the per-column padding missing from the header —
		// which is the actual defect. What has to match is where the label
		// starts against where the data starts.
		const offsets = await page.evaluate( () => {
			const contentLeft = ( el ) =>
				el.getBoundingClientRect().x +
				parseFloat( getComputedStyle( el ).paddingLeft );

			const headers = [
				...document.querySelectorAll(
					'.shp-TableView__headerRow .shp-TableView__th'
				),
			];
			const cells = [
				...document.querySelector( '.shp-TableView__rowCells' )
					.children,
			];

			return headers.map( ( th, index ) => ( {
				column:
					th.className.match( /__col--([\w]+)/ )?.[ 1 ] ?? 'unknown',
				dx: cells[ index ]
					? Math.abs(
							contentLeft( cells[ index ] ) - contentLeft( th )
					  )
					: 0,
			} ) );
		} );

		expect( offsets.length ).toBeGreaterThan( 5 );

		for ( const { column, dx } of offsets ) {
			expect(
				dx,
				`header for "${ column }" is ${ dx }px off its data`
			).toBeLessThan( 1 );
		}
	} );

	// Regression: the table used to be virtualised, which unmounted the
	// focused row as the browser scrolled it into view. Focus fell to
	// <body> and the next Tab landed at the top of the admin page, so a
	// keyboard user reached about thirteen rows and could not get back.
	// Dropping the virtualizer fixed the unmounting; memoising the column
	// definitions fixed the rest, since handing TanStack a new `columns`
	// array remounts every cell and destroys focus the same way.
	test( 'every loaded row is reachable with the keyboard', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// The ids present before the walk starts, not a count read after it.
		// This is an infinite-scroll table and tabbing through it scrolls
		// rows into view, which fires maybeLoadMore — so comparing against a
		// fresh count at the end failed whenever a second page arrived
		// mid-walk, reporting 122 of 200 as if rows were unreachable. What
		// the test is actually about is that no loaded row is skipped, so it
		// names the rows it means.
		const startingIds = await page
			.locator( '.shp-TableView__row' )
			.evaluateAll( ( rows ) =>
				rows.map( ( row ) => row.dataset.eventId )
			);

		expect( startingIds.length ).toBeGreaterThan( 20 );

		await page.locator( '.shp-TableView__sortButton' ).first().focus();

		const reached = new Set();

		// Budget taken from the DOM rather than assumed. A row has the
		// expand toggle, the checkbox and the actions menu, plus one button
		// for every cell value that can be filtered on — which depends on
		// the visible columns and on whether that row's value is filterable
		// at all. Hard-coding "three per row" made this test fail the moment
		// filterable cell values arrived, when nothing about reachability
		// had changed.
		const controlCount = await page
			.locator(
				'.shp-TableView__row button, .shp-TableView__row input, .shp-TableView__row a'
			)
			.count();

		// Budget for the rows that were there when it was measured, plus
		// slack. Rows arriving later are welcome in `reached` but are not
		// what is being asserted.
		for ( let i = 0; i < controlCount + 10; i++ ) {
			await page.keyboard.press( 'Tab' );

			const eventId = await page.evaluate( () => {
				const row = document.activeElement?.closest?.(
					'.shp-TableView__row'
				);

				return row?.dataset.eventId ?? null;
			} );

			if ( eventId ) {
				reached.add( eventId );
			} else if ( reached.size > 0 ) {
				// Left the table through its end, which is correct.
				break;
			}
		}

		expect( startingIds.filter( ( id ) => ! reached.has( id ) ) ).toEqual(
			[]
		);
	} );

	// With rows ticked by hand the export carries those rows and nothing
	// else, and the modal must not claim the filter-wide scope. It used to:
	// the sentence was hardcoded for the control-bar export, which really
	// does cover the filters, and the bulk bar reused the component without
	// saying which of the two it was. Wrong scope on an audit log's export
	// is the kind of mistake nobody catches by reading the file afterwards.
	test( 'the export modal names the selection it is about to export', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const checkboxes = page.locator(
			'.shp-TableView__row input[type="checkbox"]'
		);
		await checkboxes.nth( 0 ).check();
		await checkboxes.nth( 1 ).check();

		// Narrow scope keeps the plain Copy label — nothing is being
		// promised that the clipboard cannot deliver.
		const copyToggle = page.getByRole( 'button', {
			name: 'Copy the selected events',
		} );
		await expect( copyToggle ).toHaveText( 'Copy' );

		await page.getByRole( 'button', { name: 'Export selected' } ).click();

		const exportDialog = page.getByRole( 'dialog' );
		await expect( exportDialog ).toContainText(
			'The export will include the 2 events you selected.'
		);

		await page.keyboard.press( 'Escape' );
	} );

	// Regression: unchecking every column left a table of empty rows with
	// no way back. The PHP fell back to the defaults, but only on the next
	// page load, so the stored value and the screen disagreed until then.
	test( 'the columns menu cannot be emptied, and can be reset', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page.getByRole( 'button', { name: 'Columns' } ).click();

		const menu = page.locator( '.shp-TableView__columnsMenuContent' );

		// Addressed by role rather than by a CSS locator over the raw
		// inputs. The popover also holds the row-height radios, and the
		// checkboxes move between the ordered group and the hidden group as
		// they are toggled — so an index into a list of `input` elements
		// points somewhere different on every iteration.
		const checkboxes = menu.getByRole( 'checkbox' );

		// Uncheck until the only one left refuses to be unchecked. Reading
		// the count fresh each time, because the list reorders underneath.
		for ( let i = 0; i < 10; i++ ) {
			const checkedBoxes = checkboxes.and(
				page.locator( 'input:checked' )
			);

			const remaining = await checkedBoxes.count();

			if ( remaining === 1 && ( await checkedBoxes.isDisabled() ) ) {
				break;
			}

			await checkedBoxes.first().click();
		}

		// One column survives, and its checkbox is disabled rather than
		// silently refusing the click.
		const stillChecked = checkboxes.and( page.locator( 'input:checked' ) );

		await expect( stillChecked ).toHaveCount( 1 );
		await expect( stillChecked ).toBeDisabled();

		await page.getByRole( 'button', { name: 'Reset to defaults' } ).click();

		await expect(
			checkboxes.and( page.locator( 'input:checked' ) )
		).toHaveCount( DEFAULT_COLUMNS.length );
	} );

	// Reordering is done with a button per direction rather than by
	// dragging, so that it works from a keyboard at all. The column moving
	// in the table is what matters, not the row moving in the menu.
	test( 'a column can be moved, and the move is remembered', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const dataHeaders = async () =>
			( await page.locator( '.shp-TableView__th' ).allInnerTexts() )
				.map( ( text ) => text.split( '\n' )[ 0 ].trim() )
				.filter( Boolean );

		expect( await dataHeaders() ).toEqual( [
			'Toggle event details',
			'Select',
			'ID',
			'Date',
			'User',
			'Message',
			'Level',
			'Actions',
		] );

		await page.getByRole( 'button', { name: 'Columns' } ).click();
		await page
			.getByRole( 'button', { name: 'Move Level earlier' } )
			.click();
		await page
			.getByRole( 'button', { name: 'Move Level earlier' } )
			.click();

		await expect
			.poll( dataHeaders )
			.toEqual( [
				'Toggle event details',
				'Select',
				'ID',
				'Date',
				'Level',
				'User',
				'Message',
				'Actions',
			] );

		// Persisted server-side, not only in the URL.
		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await expect
			.poll( dataHeaders )
			.toEqual( [
				'Toggle event details',
				'Select',
				'ID',
				'Date',
				'Level',
				'User',
				'Message',
				'Actions',
			] );

		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );
	} );

	// The row menu and the bulk bar render from one action list
	// (table-actions.js), so what a selection can do is decided in one place
	// rather than drifting between two hand-maintained menus.
	test( 'the copy menu offers every clipboard format for a selection', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const boxes = page.locator(
			'.shp-TableView__row input[type="checkbox"]'
		);
		await boxes.nth( 0 ).check();
		await boxes.nth( 1 ).check();

		await page
			.getByRole( 'button', { name: 'Copy the selected events' } )
			.click();

		// The group label states the number of events the copy will contain,
		// which is the honest count even when the selection is wider.
		await expect(
			page.getByRole( 'menu' ).getByText( '2 events', { exact: true } )
		).toBeVisible();

		for ( const label of [
			'Copy',
			'Copy as CSV',
			'Copy as JSON',
			'Copy links',
		] ) {
			await expect(
				page.getByRole( 'menuitem', { name: label, exact: true } )
			).toBeVisible();
		}
	} );
	// A saved view is a named bundle of filters, columns and sort, and
	// applying one is a navigation — the filters it restores belong to core
	// and live in the page URL. The built-ins ship from PHP, so this also
	// covers the localised data reaching the menu.
	test( 'a built-in view applies its filter and reads back as active', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page.getByRole( 'button', { name: 'Saved views' } ).click();
		await page
			.getByRole( 'menuitemradio', { name: 'Errors and warnings' } )
			.click();

		await page.locator( '.shp-TableView__table' ).waitFor();

		// Core stores log levels in the URL as translated labels, not slugs
		// — see LABEL_VALUED_KEYS in premium's table-view-url.js. A view that
		// wrote slugs here would leave the table unfiltered.
		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'levels' ) )
			.toBe( 'Warning,Error,Critical,Alert,Emergency' );

		// The button names the view while the screen still matches it.
		await expect(
			page.getByRole( 'button', { name: 'Saved views' } )
		).toHaveText( 'Errors and warnings' );

		// And the rows actually narrowed: nothing below warning is left.
		// Scoped to the rows: the same class is on the column header, whose
		// text is the word "Level" and is not a level.
		//
		// Polled rather than read once. Applying a view is a navigation and
		// then a fetch; the button above renames itself from the URL, which
		// it has before the new rows land, so a single read here can catch
		// the table between the two.
		const levelTexts = () =>
			page
				.locator( '.shp-TableView__row .shp-TableView__col--level' )
				.allInnerTexts();

		await expect
			.poll( async () => {
				const levels = await levelTexts();

				return (
					levels.length > 0 &&
					levels.every( ( level ) =>
						[
							'Warning',
							'Error',
							'Critical',
							'Alert',
							'Emergency',
						].includes( level.trim() )
					)
				);
			} )
			.toBe( true );
	} );

	test( 'a view can be saved, survives a reload, and can be deleted', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table&levels=Error' );
		await page.locator( '.shp-TableView__table' ).waitFor();

		// Counted relative to whatever this admin already had. Asserting an
		// absolute zero at the end would make this test depend on nothing
		// else having ever saved a view on this install — including an
		// earlier, interrupted run of this same test.
		const viewCount = () =>
			page.evaluate(
				() => window.shpTableViewData?.savedViews?.length ?? 0
			);

		const startingCount = await viewCount();

		await page.getByRole( 'button', { name: 'Saved views' } ).click();
		await page.getByRole( 'menuitem', { name: 'Save this view…' } ).click();

		await page.getByLabel( 'Name' ).fill( 'Playwright view' );
		await page
			.getByRole( 'dialog' )
			.getByRole( 'button', { name: 'Save', exact: true } )
			.click();

		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );

		await expect(
			page.getByRole( 'button', { name: 'Saved views' } )
		).toHaveText( 'Playwright view' );

		// Stored server-side, not just in component state. It also stores
		// the level as a slug rather than the label the URL carries, so the
		// view keeps working on an admin in another language.
		await page.reload();
		await page.locator( '.shp-TableView__table' ).waitFor();

		await expect.poll( viewCount ).toBe( startingCount + 1 );

		await expect
			.poll( () =>
				page.evaluate(
					() =>
						( window.shpTableViewData?.savedViews || [] ).find(
							( view ) => view.name === 'Playwright view'
						)?.query
				)
			)
			.toMatchObject( { levels: 'error' } );

		await page.getByRole( 'button', { name: 'Saved views' } ).click();
		await page
			.getByRole( 'menuitem', { name: 'Delete “Playwright view”' } )
			.click();
		await page
			.getByRole( 'dialog' )
			.getByRole( 'button', { name: 'Delete', exact: true } )
			.click();

		// The dialog closes only once the server has accepted the change,
		// so this is the signal that the delete actually landed. Reloading
		// straight after the click raced the request instead.
		await expect( page.getByRole( 'dialog' ) ).toHaveCount( 0 );

		await page.reload();
		await page.locator( '.shp-TableView__table' ).waitFor();

		await expect.poll( viewCount ).toBe( startingCount );
	} );
	// Several hundred controls sit between the top of the table and whatever
	// follows it. The skip link is how a keyboard user gets past them.
	test( 'a keyboard user can skip past the whole table', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const skipLink = page.getByRole( 'link', {
			name: 'Skip past the events table',
		} );

		// Hidden until it has focus, the same way core's own skip links are.
		await expect( skipLink ).not.toBeInViewport();

		await skipLink.focus();
		await expect( skipLink ).toBeInViewport();

		await page.keyboard.press( 'Enter' );

		// Focus lands after the table, so the next Tab does not go back into
		// the rows.
		await page.keyboard.press( 'Tab' );

		const isInsideTable = await page.evaluate(
			() => !! document.activeElement?.closest?.( '.shp-TableView__body' )
		);

		expect( isInsideTable ).toBe( false );
	} );
	// Both the histogram and the group-by summary count server-side, over
	// the whole filtered set. Counting the hundred rows that happen to be
	// loaded would describe the page size rather than the log — which is
	// the failure mode worth a test, because it looks right.
	/**
	 * Which day button holds the most events.
	 *
	 * Read off the spoken labels, which are the only place the per-day counts
	 * exist outside the canvas. Used to aim at a stretch of the chart that
	 * certainly has something in it — an empty day, and an empty range, are
	 * both deliberately inert.
	 *
	 * @param {Object} days Locator for the day buttons.
	 * @return {Promise<number>} Index of the busiest day.
	 */
	async function busiestDayIndex( days ) {
		const counts = await days.evaluateAll( ( buttons ) =>
			buttons.map( ( button ) => {
				const label = button.getAttribute( 'aria-label' ) || '';
				const match = label.match( /([\d,]+)\s+events?/ );

				return match
					? parseInt( match[ 1 ].replace( /,/g, '' ), 10 )
					: 0;
			} )
		);

		return counts.indexOf( Math.max( ...counts ) );
	}

	test( 'the histogram counts the whole filtered set, and narrows to a day', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// The chart is drawn on a canvas, so the per-day controls are the
		// keyboard layer over it — one button per day, each naming its own
		// counts. That layer is also the only thing here a screen reader or
		// a keyboard can reach; see HistogramDays in TableViewHistogram.jsx.
		const days = page.locator( '.shp-TableView__histogramDay' );

		await expect.poll( () => days.count() ).toBeGreaterThan( 0 );

		// The counts come from the aggregate endpoint, not from the rows:
		// one day alone can hold more events than the table has loaded.
		const loadedCount = await page.locator( '.shp-TableView__row' ).count();
		const counts = await days.evaluateAll( ( buttons ) =>
			buttons.map( ( button ) => {
				const label = button.getAttribute( 'aria-label' ) || '';
				const match = label.match( /([\d,]+)\s+events?/ );

				return match
					? parseInt( match[ 1 ].replace( /,/g, '' ), 10 )
					: 0;
			} )
		);

		const busiest = Math.max( ...counts );

		expect( busiest ).toBeGreaterThan( loadedCount );

		// Activated from the keyboard, not with the mouse, because that is
		// the path that was missing: the chart has always filtered on click,
		// but the click lived on a canvas with role="img" and no keyboard
		// route to it at all — WCAG 2.1.1. The day buttons carry
		// pointer-events: none so the mouse still goes through to the canvas
		// and Chart.js keeps its own hover and click; a real mouse click
		// here would land on the canvas, not the button.
		//
		// The busiest day rather than the last one: the last bucket can be a
		// partial day with nothing in it, and an empty day is aria-disabled
		// and deliberately does nothing.
		await days.nth( counts.indexOf( busiest ) ).focus();
		await page.keyboard.press( 'Enter' );

		await page.locator( '.shp-TableView__table' ).waitFor();

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'date' ) )
			.toBe( 'customRange' );

		// One day, so every row shown carries the same date.
		await expect
			.poll( async () => {
				const dates = await page
					.locator( '.shp-TableView__row .shp-TableView__col--date' )
					.allInnerTexts();

				return new Set(
					dates.map( ( date ) => date.trim().slice( 0, 10 ) )
				).size;
			} )
			.toBe( 1 );
	} );

	test( 'the chart reserves its height before the counts arrive', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		// Not networkidle: the whole point is to catch the strip while the
		// aggregate request is still in flight.
		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table', {
			waitUntil: 'domcontentloaded',
		} );

		const histogram = page.locator( '.shp-TableView__histogram' );
		const heightOf = () =>
			histogram.evaluate( ( el ) =>
				Math.round( el.getBoundingClientRect().height )
			);

		await page.locator( '.shp-TableView__histogramNote' ).waitFor();

		const whileCounting = await heightOf();

		await page.locator( '.shp-TableView__histogramCanvas' ).waitFor();
		await expect
			.poll( () => page.locator( 'canvas' ).count() )
			.toBeGreaterThan( 0 );

		const whenDrawn = await heightOf();

		// "Counting events over time…" used to be one line of text, and the
		// table dropped 75px the moment the chart replaced it — under the
		// reader's pointer, on every filter change. The note holds the
		// chart's height instead. Measured rather than eyeballed, and here
		// rather than only in the stylesheet, so a change to the chart's
		// rows cannot quietly reintroduce the jump.
		expect( Math.abs( whenDrawn - whileCounting ) ).toBeLessThanOrEqual(
			2
		);
	} );

	test( 'quiet buckets are told apart, not flattened onto the floor', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );
		await page.locator( '.shp-TableView__histogramDay' ).first().waitFor();

		// Read straight off Chart.js rather than off the pixels: the bars are
		// a few pixels wide at 168 hourly buckets, so sampling the canvas
		// lands in the gaps as often as on a bar. The plotted value is the
		// square root of the count (see BAR_SCALE_EXPONENT), so squaring the
		// stack total gives the count back.
		const bars = await page.evaluate( () => {
			const canvas = document.querySelector(
				'.shp-TableView__histogramCanvas canvas'
			);
			const chart = window.Chart.getChart( canvas );
			const values = [
				chart.data.datasets[ 0 ].data,
				chart.data.datasets[ 1 ].data,
			];
			const metas = [
				chart.getDatasetMeta( 0 ),
				chart.getDatasetMeta( 1 ),
			];

			return chart.data.labels
				.map( ( bucket, i ) => {
					const plotted =
						( values[ 0 ][ i ] || 0 ) + ( values[ 1 ][ i ] || 0 );

					if ( plotted === 0 ) {
						return null;
					}

					// Two heights per bucket, because the two questions
					// below need different ones. The stack top answers
					// "does the tallest bar fill the plot", and every
					// bucket has one. Ordinary-only answers "are heights
					// ordered by count", and only a bucket with no severe
					// events can: the red cap carries its own
					// minBarLength floor, which exists to make two
					// warnings in a busy hour visible and does push the
					// stack above its honest height.
					//
					// getProps( …, true ) asks for the *final* values.
					// Reading el.y gives wherever the bar has animated to
					// so far — Chart.js grows bars up from the baseline —
					// so a measurement taken mid-flight sees every bar
					// short of its real height, and the tallest one short
					// of the plot. That failed about two runs in three.
					const topOf = ( d ) =>
						metas[ d ].data[ i ].getProps( [ 'y' ], true ).y;
					const tops = [ 0, 1 ]
						.filter( ( d ) => values[ d ][ i ] )
						.map( topOf );

					return {
						count: Math.round( plotted * plotted ),
						severeFree: ! values[ 1 ][ i ],
						stackPx: chart.chartArea.bottom - Math.min( ...tops ),
						px: values[ 0 ][ i ]
							? chart.chartArea.bottom - topOf( 0 )
							: 0,
					};
				} )
				.filter( Boolean );
		} );

		expect( bars.length ).toBeGreaterThan( 2 );

		// The tallest bar uses the whole plot — the axis is pinned to the
		// busiest bucket, so nothing is spent on empty headroom.
		//
		// Measured across every bucket, not just the severe-free ones. The
		// busiest bucket on a real log almost always holds a warning
		// somewhere, so asking the tallest *severe-free* bar to fill the
		// plot asks the wrong bar the wrong question — which is what an
		// earlier version of this test did, and it failed for that reason
		// rather than for a regression.
		const tallest = Math.max( ...bars.map( ( bar ) => bar.stackPx ) );

		expect( tallest ).toBeGreaterThan( 45 );

		// Taller is always more events. This is what rules out a log scale
		// as much as it rules out a regression: both keep quiet buckets
		// apart, only one keeps them ordered against the loud ones.
		const ordered = bars.filter( ( bar ) => bar.severeFree );

		expect( ordered.length ).toBeGreaterThan( 2 );

		const byCount = [ ...ordered ].sort( ( a, b ) => a.count - b.count );

		byCount.forEach( ( bar, i ) => {
			if ( i === 0 ) {
				return;
			}

			expect( bar.px ).toBeGreaterThanOrEqual(
				byCount[ i - 1 ].px - 0.01
			);
		} );

		// The regression itself. On a straight count a 50px plot gives one
		// pixel to peak/50 events, so at a peak in the thousands every bucket
		// under about 2% of it collapsed onto MIN_BAR_PIXELS together and the
		// chart said nothing at all about the quiet stretches — which on an
		// audit log is where the one unexpected login is.
		const peak = Math.max( ...bars.map( ( bar ) => bar.count ) );
		const quiet = byCount.filter( ( bar ) => bar.count < peak * 0.02 );

		test.skip(
			quiet.length < 2,
			'needs at least two quiet buckets to compare'
		);

		const quietHeights = new Set(
			quiet.map( ( bar ) => Math.round( bar.px ) )
		);

		expect( quietHeights.size ).toBeGreaterThan( 1 );
	} );

	test( 'dragging across the histogram narrows to the days dragged over', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		// Thirty days explicitly. A week or less is bucketed hourly, and the
		// range a drag produces is measured in whole days — core's date
		// filter has no way to express an hour. See buildBucketUrl().
		await page.goto(
			SIMPLE_HISTORY_PAGE + '&view=table&date=lastdays%3A30'
		);
		await waitForLoadedRows( page );

		const days = page.locator( '.shp-TableView__histogramDay' );

		await expect.poll( () => days.count() ).toBeGreaterThan( 4 );

		// Anchored on the busiest day so the range certainly holds events: a
		// drag across an empty stretch is refused, the same way clicking an
		// empty bar is.
		const busiest = await busiestDayIndex( days );
		const last = Math.max( 3, busiest );
		const first = last - 3;

		const from = await days.nth( first ).boundingBox();
		const to = await days.nth( last ).boundingBox();

		await page.mouse.move(
			from.x + from.width / 2,
			from.y + from.height / 2
		);
		await page.mouse.down();
		await page.mouse.move( to.x + to.width / 2, to.y + to.height / 2, {
			steps: 10,
		} );

		// The band is the whole of the feedback while the button is down.
		await expect(
			page.locator( '.shp-TableView__histogramBand' )
		).toBeVisible();

		await page.mouse.up();

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'date' ) )
			.toBe( 'customRange' );

		const url = new URL( page.url() );

		// A range, not the single day a click gives.
		expect( url.searchParams.get( 'from' ) ).not.toBe(
			url.searchParams.get( 'to' )
		);

		await expect(
			page.locator( '.shp-TableView__histogramBand' )
		).toHaveCount( 0 );
	} );

	test( 'a click on one bar still filters to its day, after the drag handling', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto(
			SIMPLE_HISTORY_PAGE + '&view=table&date=lastdays%3A30'
		);
		await waitForLoadedRows( page );

		const days = page.locator( '.shp-TableView__histogramDay' );

		await expect.poll( () => days.count() ).toBeGreaterThan( 4 );

		// Here because it broke once and nothing caught it. The drag captures
		// the pointer, and a captured pointer delivers its click to the
		// capturing element — so capturing a moment too early took the click
		// away from the canvas, and clicking a bar silently stopped filtering
		// at all. The capture now waits until the press has travelled.
		const busiest = await busiestDayIndex( days );
		const box = await days.nth( busiest ).boundingBox();

		await page.mouse.click( box.x + box.width / 2, box.y + box.height / 2 );

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'date' ) )
			.toBe( 'customRange' );

		const url = new URL( page.url() );

		expect( url.searchParams.get( 'from' ) ).toBe(
			url.searchParams.get( 'to' )
		);
	} );

	test( 'clicking an hourly bar narrows the table to that hour', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		// One day, so the chart buckets by hour.
		const day = new Date( Date.now() - 86400000 )
			.toISOString()
			.slice( 0, 10 );

		await page.goto(
			SIMPLE_HISTORY_PAGE +
				`&view=table&date=customRange&from=${ day }&to=${ day }`
		);
		await waitForLoadedRows( page );

		// The clock under the chart is how a reader can tell these are hours
		// rather than days. It only appears on a single-day chart.
		const ticks = page.locator( '.shp-TableView__histogramTick' );

		await expect.poll( () => ticks.count() ).toBeGreaterThan( 2 );
		await expect( ticks.first() ).toHaveText( /^\d{2}:00$/ );

		const dayTotal = await readTotal( page );
		const plot = await page
			.locator( '.shp-TableView__histogramCanvas' )
			.boundingBox();

		// Which hours have bars is a property of the fixture, not of the
		// feature, and a click on an empty stretch is deliberately ignored —
		// the same rule the drag follows. So walk the 24 hour slots and take
		// the first one that answers. This used to click the middle of the
		// plot and trust midday to be busy, which failed outright on a day
		// whose noon happened to be quiet.
		//
		// The click pushes state rather than loading a page, so the URL is
		// updated by the time the next one lands.
		let pickedHour = null;

		for ( let hour = 0; hour < 24 && pickedHour === null; hour++ ) {
			await page.mouse.click(
				plot.x + ( plot.width * ( hour + 0.5 ) ) / 24,
				plot.y + plot.height * 0.85
			);
			await page.waitForTimeout( 300 );

			pickedHour = new URL( page.url() ).searchParams.get( 'table_hour' );
		}

		expect( pickedHour ).toMatch( /^\d{4}-\d{2}-\d{2}T\d{2}$/ );

		// The day is kept alongside the hour, so core's own filters and the
		// other two views still describe something true.
		const url = new URL( page.url() );
		expect( url.searchParams.get( 'from' ) ).toBe( day );
		expect( url.searchParams.get( 'to' ) ).toBe( day );

		// An hour of a day holds fewer events than the day.
		await expect.poll( () => readTotal( page ) ).toBeLessThan( dayTotal );

		// Marked on the chart rather than filtered out of it — narrowing the
		// chart to the hour would leave one bar and no way to reach the next.
		await expect(
			page.locator( '.shp-TableView__histogramBand--hour' )
		).toHaveCount( 1 );
		await expect.poll( () => ticks.count() ).toBeGreaterThan( 2 );

		// And it is named where the filters are listed, since the query bar's
		// grammar has no way to write an hour.
		await expect(
			page.locator( '.shp-TableView__chip' ).filter( { hasText: ':00' } )
		).toHaveCount( 1 );

		// The chart keeps counting the whole day while the table counts the
		// hour, on purpose — so the line under the chart has to say so, or
		// the reader is left with two honest numbers and no way to tell
		// which one answers the question they asked.
		await expect(
			page.locator( '.shp-TableView__histogramSummary' )
		).toContainText( 'chart shows the day, table shows the hour' );

		// Said again beside the number it is about, since the chart's
		// caption sits a line up and two-thirds of the way across.
		await expect( page.locator( '.shp-TableView__total' ) ).toContainText(
			'(this hour)'
		);

		const keyTotal = await readChartKeyTotal( page );

		expect( keyTotal ).toBeGreaterThan( await readTotal( page ) );
	} );

	test( 'a keyboard user can pick a range of days in the histogram', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto(
			SIMPLE_HISTORY_PAGE + '&view=table&date=lastdays%3A30'
		);
		await waitForLoadedRows( page );

		const days = page.locator( '.shp-TableView__histogramDay' );

		await expect.poll( () => days.count() ).toBeGreaterThan( 4 );

		const busiest = await busiestDayIndex( days );

		await days.nth( Math.max( 2, busiest ) ).focus();

		await page.keyboard.press( 'Shift+ArrowLeft' );
		await page.keyboard.press( 'Shift+ArrowLeft' );

		// The moving end of the range speaks for the whole range. Focus lands
		// on it after every Shift+Arrow, so each press announces what has
		// been picked so far rather than the name of one more day.
		await expect(
			page.locator( '.shp-TableView__histogramDay:focus' )
		).toHaveAttribute( 'aria-label', /across 3 days\. Filter to this/ );

		await expect(
			page.locator( '.shp-TableView__histogramBand' )
		).toBeVisible();

		// Moving without Shift abandons the range rather than carrying it
		// along, so Enter can never apply one that is no longer drawn.
		await page.keyboard.press( 'ArrowLeft' );
		await expect(
			page.locator( '.shp-TableView__histogramBand' )
		).toHaveCount( 0 );

		await page.keyboard.press( 'Shift+ArrowLeft' );
		await page.keyboard.press( 'Shift+ArrowLeft' );
		await page.keyboard.press( 'Enter' );

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'date' ) )
			.toBe( 'customRange' );

		const url = new URL( page.url() );

		expect( url.searchParams.get( 'from' ) ).not.toBe(
			url.searchParams.get( 'to' )
		);
	} );

	// Replaces "grouping counts the matching events and narrows to a group".
	// The Group by dropdown and the band of counts it opened are gone: the
	// band ran to 15 rows and pushed the table to y=840 on a 1000px window,
	// its bars lived in a 40px column so every group but the largest was a
	// 1px sliver, and two of its three groupings rendered as blue underlined
	// links that did nothing. What it answered now sits on the line that
	// already carries the count.
	test( 'the count line says what the matching events are made of', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		// The control and its band are gone, and nothing replaced them with
		// another band.
		await expect( page.getByLabel( 'Group by' ) ).toHaveCount( 0 );
		await expect( page.locator( '.shp-TableView__groupBy' ) ).toHaveCount(
			0
		);

		const breakdown = page.locator( '.shp-TableView__breakdown' );
		await expect( breakdown ).toBeVisible();

		// Either the all-clear or a count of what is wrong — never nothing,
		// since a line that simply omits the phrase reads as one that has
		// not loaded.
		await expect( breakdown ).toHaveText(
			/no warnings|\d[\d,]* warnings?|\d[\d,]* severe/
		);

		// The summary drops everything below warning, so hovering has to
		// bring the rest back. Levels only, and that is deliberate:
		// .components-tooltip has no max-width and collapses newlines, so
		// both lists together ran off the side of the window.
		await breakdown.hover();

		const tooltip = page.locator( '.components-tooltip' ).first();
		await expect( tooltip ).toContainText( /Info [\d,]+/ );

		// Counted server-side over the whole filtered set. The fixture holds
		// more events than one page, so a level count larger than the rows
		// on screen proves these did not come from what is loaded.
		const biggest = Math.max(
			...( await tooltip.innerText() )
				.split( '\u00b7' )
				.map( ( part ) => Number( part.replace( /[^\d]/g, '' ) ) || 0 )
		);

		expect( biggest ).toBeGreaterThan(
			await page.locator( '.shp-TableView__row' ).count()
		);

		// And it has to fit. Eight log levels is the ceiling, so this width
		// is the worst case rather than a sample of one site's data.
		const box = await tooltip.boundingBox();
		expect( box.x + box.width ).toBeLessThanOrEqual( 1280 );
	} );

	// The bar used to print "Select events to copy, compare or export just
	// those." across itself on every visit for ever, to teach a fact the
	// reader learns for good the first time they tick a box. The band is
	// reserved either way, so carrying the sentence bought no space back.
	test( 'the resting bar holds export, and the column says what ticking does', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const bar = page.locator( '.shp-TableView__toolbar' );

		await expect( bar ).toContainText( 'Export all' );

		// One strip, not two. The 41px band that used to hold this button
		// alone is gone, and with it the hairline that split one white zone
		// into two for no reason.
		await expect( page.locator( '.shp-TableView__bulkBar' ) ).toHaveCount(
			0
		);

		// Still reserved at its full height, which is the whole reason the
		// bar is always on: ticking a box must not move the table.
		const resting = await bar.boundingBox();

		// The explanation moved here, as the column's description. It is a
		// title and not a button because anything pressable in this header
		// reads as select-all, which this table deliberately does not have.
		const selectHeader = page.locator( '.shp-TableView__th', {
			hasText: /^Select$/,
		} );

		await expect( selectHeader ).toHaveAttribute(
			'title',
			'Tick rows to copy, compare or export just those.'
		);

		// The header keeps its own short name — the title is read after it,
		// not instead of it.
		await expect( selectHeader ).toHaveText( 'Select' );

		// And something visible to hover. Without it the cell rendered as
		// blank space, so the explanation was reachable only by hovering
		// nothing in particular.
		await expect(
			selectHeader.locator( '.shp-TableView__selectHint' )
		).toBeVisible();

		await page
			.locator( '.shp-TableView__row input[type="checkbox"]' )
			.first()
			.check();

		await expect( bar ).toContainText( '1 event selected' );
		await expect( bar ).toContainText( 'Export selected' );

		const selected = await bar.boundingBox();

		expect( selected.height ).toBe( resting.height );
	} );

	// The key under the chart said what the two colours meant and never how
	// much of each there was, which is the first thing anyone wants from it.
	// Summed from the bars rather than fetched, so it can only ever describe
	// the chart it is labelling.
	test( 'the chart key carries the totals it is describing', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const keys = page.locator( '.shp-TableView__histogramKey' );
		await expect( keys.first() ).toHaveText( /Info or lower [\d,]+/ );
		await expect( keys.nth( 1 ) ).toHaveText( /Warning or higher [\d,]+/ );
	} );

	// The key, drawn to scale. A per-level version came first and had to
	// floor every level at 3px to keep one Critical event visible, which
	// handed 15% of the bar to levels that were 0.006% of the events. Two
	// buckets need no floor, so the widths can simply be true.
	test( 'the chart key is drawn to scale, and says nothing twice', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const bar = page.locator( '.shp-TableView__levelBar' );

		await expect( bar ).toBeVisible();

		// The two labels beside it carry both numbers, so there is nothing
		// here for assistive tech that the text does not already say — and
		// nothing to land on with Tab, which is what the per-level version
		// got wrong: a focus ring around a bar whose only description was a
		// `title`, which browsers do not show on keyboard focus.
		await expect( bar ).toHaveAttribute( 'aria-hidden', 'true' );
		expect( await bar.getAttribute( 'tabindex' ) ).toBeNull();

		// The squares it replaced are gone; the bar is the key now.
		await expect(
			page.locator( '.shp-TableView__histogramSwatch' )
		).toHaveCount( 0 );

		const [ ordinary, severe ] = await bar
			.locator( 'span' )
			.evaluateAll( ( parts ) =>
				parts.map( ( part ) => part.getBoundingClientRect().width )
			);

		// Two segments filling one 120px strip, and the widths are the
		// shares rather than a drawing of them.
		expect( Math.round( ordinary + severe ) ).toBe( 120 );

		const keys = await page
			.locator( '.shp-TableView__histogramKey' )
			.allTextContents();

		const counts = keys.map( ( text ) =>
			Number( text.replace( /[^\d]/g, '' ) )
		);

		const expectedSevere =
			( counts[ 1 ] / ( counts[ 0 ] + counts[ 1 ] ) ) * 120;

		expect( severe ).toBeCloseTo( expectedSevere, 0 );
	} );

	// A filter matching only info events is the quiet case, and the one a
	// healthy site is in most of the time. It has to say so out loud.
	test( 'a result set with nothing wrong in it says "no warnings"', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const queryBox = page.getByPlaceholder( /level:error/ );
		await queryBox.fill( 'level:info' );
		await queryBox.press( 'Enter' );

		await expect( page.locator( '.shp-TableView__breakdown' ) ).toHaveText(
			/no warnings/
		);
	} );

	// The whole reason this sits inline instead of in a band. Compared
	// against surrounding mode, which is the one state that hides it: the
	// strip has to measure the same with and without.
	test( 'the breakdown does not change the height of the strip', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const toolbar = page.locator( '.shp-TableView__toolbar' );
		const heightOf = () =>
			toolbar.evaluate( ( el ) => el.getBoundingClientRect().height );

		await expect(
			page.locator( '.shp-TableView__breakdown' )
		).toBeVisible();
		const withBreakdown = await heightOf();

		await page
			.locator( '.shp-TableView__row' )
			.nth( 2 )
			.locator( 'input[type="checkbox"]' )
			.check();
		await page
			.getByRole( 'button', { name: 'Show surrounding events' } )
			.click();

		await expect( page.locator( '.shp-TableView__breakdown' ) ).toHaveCount(
			0
		);

		expect( await heightOf() ).toBe( withBreakdown );
	} );

	// Gmail-style shortcuts for people who are in the log daily. Navigation
	// and selection only: no single key writes, sends, or leaves the browser,
	// because a mistyped key in an audit log should never be an incident.
	test( 'j, k, x and o navigate and select without the mouse', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const focusedRowId = () =>
			page.evaluate(
				() =>
					document.activeElement
						?.closest?.( '.shp-TableView__row' )
						?.getAttribute( 'data-event-id' ) ?? null
			);

		await page.keyboard.press( 'j' );
		const first = await focusedRowId();
		expect( first ).not.toBeNull();

		await page.keyboard.press( 'j' );
		const second = await focusedRowId();
		expect( second ).not.toBe( first );

		await page.keyboard.press( 'k' );
		expect( await focusedRowId() ).toBe( first );

		// x selects, and selecting is the most a single key is allowed to do.
		await page.keyboard.press( 'x' );
		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toHaveText( '1 event selected' );

		await page.keyboard.press( 'x' );
		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toHaveCount( 0 );

		// o expands the focused row.
		await page.keyboard.press( 'o' );
		await expect
			.poll( () =>
				page
					.locator(
						'.shp-TableView__row--expanded, .shp-TableView__detailsPanel'
					)
					.count()
			)
			.toBeGreaterThan( 0 );
	} );

	// `o` was the only way to open a row, and nobody finds it without the
	// shortcuts list. Right and Left are what a reader tries first, because
	// every tree control in every file manager works that way, and Enter is
	// what they try second.
	test( 'arrow keys and Enter open and close a row', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const panels = page.locator( '.shp-TableView__detailsPanel' );

		await page.keyboard.press( 'j' );
		await expect( panels ).toHaveCount( 0 );

		await page.keyboard.press( 'ArrowRight' );
		await expect( panels ).toHaveCount( 1 );

		// Directional, not a toggle: holding Right through a list should
		// open every row, not flap the one under the cursor.
		await page.keyboard.press( 'ArrowRight' );
		await expect( panels ).toHaveCount( 1 );

		await page.keyboard.press( 'ArrowLeft' );
		await expect( panels ).toHaveCount( 0 );

		await page.keyboard.press( 'ArrowLeft' );
		await expect( panels ).toHaveCount( 0 );

		// Enter is a toggle, like o.
		await page.keyboard.press( 'Enter' );
		await expect( panels ).toHaveCount( 1 );

		await page.keyboard.press( 'Enter' );
		await expect( panels ).toHaveCount( 0 );
	} );

	// Enter is the one shortcut that can collide. A row carries the expand
	// toggle, the checkbox, the actions menu and a filter button on any cell
	// whose value can be filtered on — pressing Enter on one of those has to
	// reach that control, not the table.
	test( 'Enter on a control inside a row belongs to the control', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page
			.locator( '.shp-TableView__row input[type="checkbox"]' )
			.first()
			.focus();

		await page.keyboard.press( 'Enter' );

		await expect(
			page.locator( '.shp-TableView__detailsPanel' )
		).toHaveCount( 0 );
	} );

	// "Show surrounding events" gathers rows around one event and says so in
	// a banner. Until this, nothing on screen said which row that was, so
	// finding it meant reading ids until one matched the sentence — on a
	// screen whose entire point is the rows either side of it.
	test( 'surrounding mode marks the event it gathered the rows around', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const row = page.locator( '.shp-TableView__row' ).nth( 5 );
		const anchorId = await row.getAttribute( 'data-event-id' );

		await row.locator( 'input[type="checkbox"]' ).check();
		await page
			.getByRole( 'button', { name: 'Show surrounding events' } )
			.click();

		await expect(
			page.locator( '.shp-TableView__surroundingBanner' )
		).toContainText( anchorId );

		const anchor = page.locator( '.shp-TableView__row--anchor' );
		await expect( anchor ).toHaveCount( 1 );
		await expect( anchor ).toHaveAttribute( 'data-event-id', anchorId );

		// Not colour alone.
		await expect( anchor ).toHaveAttribute( 'aria-current', 'true' );

		// And it beats the selected background, so ticking the anchor does
		// not hide which row it is.
		await expect(
			anchor.evaluate( ( el ) => getComputedStyle( el ).backgroundColor )
		).resolves.toBe( 'rgb(252, 249, 232)' );

		await page
			.getByRole( 'button', { name: 'Back to your filters' } )
			.click();

		await expect(
			page.locator( '.shp-TableView__row--anchor' )
		).toHaveCount( 0 );
	} );

	// The shortcuts must not fire while the reader is typing, or `/` eats the
	// search box the first time anyone tries to use it.
	test( 'the shortcuts stay out of the way while typing', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page.keyboard.press( 'j' );
		const focusedBefore = await page.evaluate(
			() =>
				document.activeElement
					?.closest?.( '.shp-TableView__row' )
					?.getAttribute( 'data-event-id' ) ?? null
		);

		// `/` moves focus to the search box.
		await page.keyboard.press( '/' );
		await expect
			.poll( () =>
				page.evaluate( () =>
					document.activeElement?.tagName?.toLowerCase()
				)
			)
			.toBe( 'input' );

		await page.keyboard.type( 'jjjkkkxxx' );

		// Every one of those is a shortcut, and none of them did anything.
		expect(
			await page.evaluate( () => document.activeElement?.value )
		).toBe( 'jjjkkkxxx' );

		expect(
			await page.evaluate(
				() =>
					document.activeElement
						?.closest?.( '.shp-TableView__row' )
						?.getAttribute( 'data-event-id' ) ?? null
			)
		).not.toBe( focusedBefore );

		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toHaveCount( 0 );
	} );

	// Filters live in the URL, so a pivot is a link — which keeps it at the
	// view-history capability. Building it on surrounding_event_id would have
	// inherited that parameter's admin-only capability bypass.
	test( 'a row pivots to everything that user did that day', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const rowDay = (
			await page
				.locator( '.shp-TableView__row .shp-TableView__col--date' )
				.first()
				.innerText()
		)
			.trim()
			.slice( 0, 10 );

		await page
			.locator(
				'.shp-TableView__row .shp-TableView__col--user .shp-TableView__filterValue'
			)
			.first()
			.click();

		await page
			.locator( '.components-popover' )
			.last()
			.getByRole( 'menuitem', {
				name: 'Everything this user did that day',
			} )
			.click();

		await page.locator( '.shp-TableView__table' ).waitFor();

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'from' ) )
			.toBe( rowDay );

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'date' ) )
			.toBe( 'customRange' );

		// One user, one day.
		await expect
			.poll( async () => {
				const users = await page
					.locator( '.shp-TableView__row .shp-TableView__col--user' )
					.allInnerTexts();

				return new Set( users.map( ( u ) => u.trim() ) ).size;
			} )
			.toBe( 1 );
	} );

	test( 'the current filters can be copied as a WP-CLI command', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page.getByRole( 'button', { name: 'Saved views' } ).click();

		await expect(
			page.getByRole( 'menuitem', { name: 'Copy as WP-CLI command' } )
		).toBeVisible();
	} );
	// The query bar is a different keyboard for the filter UI, not a second
	// filtering system: it parses into the same URL parameters the chips
	// produce, so the server cannot tell the difference and the back button
	// undoes it.
	// `day:` wanted a date and nothing else, so the two days anyone actually
	// types had to be looked up on a calendar first. A bare `today` can
	// never work — bare words are the free text search, so it would go
	// looking for the word in the message.
	test( 'day: takes today and yesterday, and shows which day it got', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const queryInput = page.locator( '#shp-table-view-query' );

		// Offered while typing, because nothing else on screen says the
		// grammar takes a word here.
		await queryInput.fill( 'day:' );

		await expect(
			page.getByRole( 'option', { name: /today/ } )
		).toBeVisible();

		// Taken from the list with the keyboard, which is where the key
		// used to be lost: `insert` replaces the whole term, so handing
		// back a bare value left the box reading `today` — a free text
		// search for the word, not a date filter at all.
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'Enter' );

		await expect( queryInput ).toHaveValue( 'day:today' );

		const localDay = ( offset ) => {
			const date = new Date();

			date.setDate( date.getDate() + offset );

			const pad = ( number ) => String( number ).padStart( 2, '0' );

			return `${ date.getFullYear() }-${ pad(
				date.getMonth() + 1
			) }-${ pad( date.getDate() ) }`;
		};

		for ( const [ word, offset ] of [
			[ 'today', 0 ],
			[ 'yesterday', -1 ],
		] ) {
			await queryInput.fill( `day:${ word }` );
			await page.getByRole( 'button', { name: 'Apply' } ).first().click();

			const day = localDay( offset );

			await expect
				.poll( () => new URL( page.url() ).searchParams.get( 'from' ) )
				.toBe( day );

			expect( new URL( page.url() ).searchParams.get( 'to' ) ).toBe(
				day
			);

			// Written back as the date, not as the word. A range is two
			// dates with nowhere to record that one end was a keyword, so
			// leaving the word there would promise a saved view that
			// follows the clock and not deliver one.
			await expect( queryInput ).toHaveValue( `day:${ day }` );
		}

		// Anything else is still a date, and says so.
		await queryInput.fill( 'day:lastweek' );
		await page.getByRole( 'button', { name: 'Apply' } ).first().click();

		// The notice, not wp.a11y's live region, which carries the same
		// sentence for screen readers.
		await expect(
			page.locator( '.components-notice__content', {
				hasText: /needs a date in the form/,
			} )
		).toBeVisible();
	} );

	// Live mode announced arrivals to screen readers and showed everyone
	// else nothing, so rows appeared silently above whatever was being read.
	test( 'live mode marks the rows it just put at the top', async ( {
		page,
		requestUtils,
	} ) => {
		// The highlight runs for six seconds and the poll for ten, so this
		// one waits out real clock time rather than a network response.
		test.setTimeout( 90000 );

		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page.getByRole( 'checkbox', { name: /live/i } ).first().check();

		const lit = page.locator( '.shp-TableView__row--new' );

		await expect( lit ).toHaveCount( 0 );

		await requestUtils.rest( {
			method: 'POST',
			path: 'simple-history/v1/events',
			data: {
				message: 'Playwright live arrival',
				level: 'info',
				note: 'Written while live mode was on.',
			},
		} );

		await expect( lit ).toHaveCount( 1, { timeout: 30000 } );

		// Said in words as well, for anyone who cannot see the colour.
		await expect(
			page.getByText( /new event added at the top/ )
		).toBeAttached();

		// And it lets go. A mark that stayed would be lit on three batches
		// at once and say nothing about any of them.
		await expect( lit ).toHaveCount( 0, { timeout: 15000 } );
	} );

	// Picking a view left the menu open over the table it had just
	// rewritten, which reads as the click not having registered.
	test( 'choosing a view closes the menu it was chosen from', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page
			.locator( '.shp-TableView__savedViews button' )
			.first()
			.click();

		const choice = page.getByRole( 'menuitemradio', {
			name: 'Content changes',
		} );

		await expect( choice ).toBeVisible();
		await choice.click();

		await expect(
			page.getByRole( 'menuitemradio', { name: 'Content changes' } )
		).toHaveCount( 0 );

		// And it did apply, so the close is not hiding a dead click.
		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'messages' ) )
			.toContain( 'Content' );
	} );

	// Absolute timestamps are precise and hard to read at a glance. This is
	// the same value rendered the other way, offered as its own column so a
	// reader can have either or both.
	test( 'the relative date column reads in words and keeps the exact time', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, [
			'date_relative',
			'date',
			'user',
			'message',
			'level',
		] );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const cell = page
			.locator( '.shp-TableView__row .shp-TableView__col--date_relative' )
			.first();

		await expect( cell ).toHaveText( /ago$/ );

		// The precision is not thrown away, it moves to the title — "2
		// hours ago" is easier to read and useless for lining an event up
		// against a deploy.
		await expect( cell.locator( 'span' ) ).toHaveAttribute(
			'title',
			/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/
		);

		// Sorted by the same key as Date, because it is the same ordering.
		await page
			.getByRole( 'button', { name: /Relative date/ } )
			.first()
			.click();

		await expect
			.poll( () =>
				new URL( page.url() ).searchParams.get( 'table_orderby' )
			)
			.toBe( 'date' );

		// Date speaks for the pair while both are on screen, so one sort is
		// announced once rather than twice.
		await expect(
			page.locator( '.shp-TableView__th.shp-TableView__col--date' )
		).toHaveAttribute( 'aria-sort', /ascending|descending/ );

		await expect(
			page.locator(
				'.shp-TableView__th.shp-TableView__col--date_relative'
			)
		).toHaveAttribute( 'aria-sort', 'none' );

		// Restored, so the columns this test chose do not leak into the next.
		await setStoredColumns( requestUtils, [
			'event_id',
			'date',
			'user',
			'message',
			'level',
		] );
	} );

	test( 'a typed query applies the same filters the chips would', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		await page
			.locator( '#shp-table-view-query' )
			.fill( 'level:warning -logger:SimpleHistoryLogger' );
		await page.getByRole( 'button', { name: 'Apply' } ).click();

		await page.locator( '.shp-TableView__table' ).waitFor();

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'levels' ) )
			.toBe( 'Warning' );
		await expect
			.poll( () =>
				new URL( page.url() ).searchParams.get( 'exclude-loggers' )
			)
			.toBe( 'SimpleHistoryLogger' );

		// And it reads back as the same query, so the bar is a view of the
		// URL rather than a separate place filter state lives.
		await expect( page.locator( '#shp-table-view-query' ) ).toHaveValue(
			'level:warning -logger:SimpleHistoryLogger'
		);

		await expect
			.poll( async () => {
				const levels = await page
					.locator( '.shp-TableView__row .shp-TableView__col--level' )
					.allInnerTexts();

				return [
					...new Set( levels.map( ( level ) => level.trim() ) ),
				];
			} )
			.toEqual( [ 'Warning' ] );
	} );

	// A term nobody recognises must be named. Navigating anyway would show
	// the unfiltered log, which looks exactly like a filter that matched
	// everything.
	test( 'an unknown term is reported instead of silently ignored', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table&levels=Warning' );
		await page.locator( '.shp-TableView__table' ).waitFor();

		await page.locator( '#shp-table-view-query' ).fill( 'nonsense:x' );
		await page.getByRole( 'button', { name: 'Apply' } ).click();

		await expect(
			page.locator( '.shp-TableView__queryProblems' )
		).toContainText( 'nonsense' );

		// Did not navigate.
		expect( new URL( page.url() ).searchParams.get( 'levels' ) ).toBe(
			'Warning'
		);
	} );
	// The first thing a person writes into the log. Everything else in the
	// table was written by a logger, so this needs manage_options (matching
	// sticky) and it keeps every earlier version.
	test( 'an event can be annotated, and edits keep the earlier version', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await waitForLoadedRows( page );

		const eventId = await page
			.locator( '.shp-TableView__row' )
			.first()
			.getAttribute( 'data-event-id' );

		const expand = () =>
			page
				.locator(
					`[data-event-id="${ eventId }"] .shp-TableView__col--expand button`
				)
				.click();

		await expand();

		await page
			.getByRole( 'button', { name: 'Add a note' } )
			.first()
			.click();
		await page.getByLabel( 'Note' ).fill( 'First note' );
		await page.getByRole( 'button', { name: 'Save note' } ).click();

		await expect(
			page.locator( '.shp-TableView__annotationText' ).first()
		).toHaveText( 'First note' );

		// The author is shown, and no earlier version yet.
		await expect(
			page.locator( '.shp-TableView__annotationMeta' ).first()
		).not.toContainText( 'earlier version' );

		// Editing does not overwrite: the previous text is kept, which is
		// what stops a note being used to rewrite what a record said.
		await page.getByRole( 'button', { name: 'Edit note' } ).first().click();
		await page.getByLabel( 'Note' ).fill( 'Second note' );
		await page.getByRole( 'button', { name: 'Save note' } ).click();

		await expect(
			page.locator( '.shp-TableView__annotationMeta' ).first()
		).toContainText( '1 earlier version' );

		// Stored server-side, and carried on the row rather than only in the
		// details fetch.
		await page.reload();
		await waitForLoadedRows( page );

		await expect
			.poll( () =>
				page.evaluate(
					( id ) =>
						window.wp?.data === undefined
							? document.querySelector(
									`[data-event-id="${ id }"]`
							  ) !== null
							: document.querySelector(
									`[data-event-id="${ id }"]`
							  ) !== null,
					eventId
				)
			)
			.toBe( true );

		await expand();

		await expect(
			page.locator( '.shp-TableView__annotationText' ).first()
		).toHaveText( 'Second note' );

		// Clean up, so the fixture is left as it was found.
		await page.getByRole( 'button', { name: 'Edit note' } ).first().click();
		await page.getByLabel( 'Note' ).fill( '' );
		await page.getByRole( 'button', { name: 'Save note' } ).click();

		await expect(
			page.locator( '.shp-TableView__annotationText' )
		).toHaveCount( 0 );
	} );
	// Not a binding between a view and a rule — a snapshot. Alerts evaluate
	// at insert time on the raw row, so half a view's filters cannot come
	// along, and the reader is told which before the rule exists rather than
	// during an incident when the alert that should have fired did not.
	test( 'creating an alert from a view says what it can and cannot watch', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		// A level (which a rule can match) and a date range (which it cannot).
		await page.goto(
			SIMPLE_HISTORY_PAGE + '&view=table&levels=Warning&date=lastdays:7'
		);
		await page.locator( '.shp-TableView__table' ).waitFor();

		await page.getByRole( 'button', { name: 'Saved views' } ).click();
		await page
			.getByRole( 'menuitem', { name: /^Alert me about this view/ } )
			.click();

		const dialog = page.getByRole( 'dialog' );
		await dialog.waitFor();

		await expect(
			page.locator( '.shp-TableView__alertCovered' )
		).toContainText( 'Log level' );

		await expect(
			page.locator( '.shp-TableView__alertUncovered' )
		).toContainText( 'Date range' );

		// The conditions travel in the URL, so the rule stores its own copy —
		// editing the view afterwards cannot change what the alert ships.
		const href = await page
			.getByRole( 'link', { name: 'Create the alert' } )
			.getAttribute( 'href' );

		expect( href ).toContain( 'prefill_query=' );
		expect( href ).toContain( 'alerts_tab=custom-rules' );
	} );
} );
