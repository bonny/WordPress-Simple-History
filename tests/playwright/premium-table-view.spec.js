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

// Matches Table_View_Module::DEFAULT_COLUMNS in premium's PHP.
const DEFAULT_COLUMNS = [ 'date', 'user', 'message', 'level' ];

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
 * Save the admin's stored table columns directly, so a test starts from a
 * known column set and can be restored to the defaults afterwards.
 *
 * @param {Object}   requestUtils
 * @param {string[]} columns
 */
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

	test( 'clicking the date does not open anything', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		await expect( page.locator( '.shp-TableView__bulkBar' ) ).toHaveCount(
			0
		);

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
		await expect( page.locator( '.shp-TableView__bulkBar' ) ).toHaveCount(
			0
		);
	} );

	test( 'export sends the ids selected before a sort, not the rows visible after it', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
			.locator( '.shp-TableView__bulkBar' )
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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

	test( "expanding a row fetches and shows details for that row's event, collapsing hides them again", async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
			await page.locator( '.shp-TableView__row' ).first().waitFor();

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
				page
					.locator( '.shp-TableView__row' )
					.evaluateAll( ( rows ) =>
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
							).map(
								( cell ) => cell.getBoundingClientRect().top
							);

							return {
								min: Math.min( ...cellTops ),
								max: Math.max( ...cellTops ),
								count: cellTops.length,
							};
						} )
					);

			await expect.poll( async () => ( await measureRows() ).length )
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
					? Math.abs( contentLeft( cells[ index ] ) - contentLeft( th ) )
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		const rowCount = await page.locator( '.shp-TableView__row' ).count();
		expect( rowCount ).toBeGreaterThan( 20 );

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

		for ( let i = 0; i < controlCount + 10; i++ ) {
			await page.keyboard.press( 'Tab' );

			const eventId = await page.evaluate( () => {
				const row =
					document.activeElement?.closest?.(
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

		expect( reached.size ).toBe(
			await page.locator( '.shp-TableView__row' ).count()
		);
	} );

	// Regression: the header checkbox was labelled "Select all events on
	// this page" and reported "100 events selected" against a total in the
	// thousands. On an export path that is a confident wrong answer, so the
	// label now states the real scope and the wider one is a separate,
	// explicit choice.
	test( 'select-all states how many events it actually selects, and offers the full set separately', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );
		await setStoredColumns( requestUtils, DEFAULT_COLUMNS );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		const loadedCount = await page.locator( '.shp-TableView__row' ).count();

		await expect(
			page.locator( '.shp-TableView__selectAll' )
		).toHaveAttribute(
			'aria-label',
			`Select the ${ loadedCount } events loaded so far`
		);

		await page.locator( '.shp-TableView__selectAll' ).check();

		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toHaveText( `${ loadedCount } events selected` );

		// The total is ungrouped and larger than one page on this fixture,
		// so the wider-scope line must be offered.
		const scope = page.locator( '.shp-TableView__bulkBarScope' );
		await expect( scope ).toContainText(
			`Only the ${ loadedCount } events loaded so far are selected.`
		);

		await scope.getByRole( 'button' ).click();

		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toContainText( 'All' );
		await expect(
			page.locator( '.shp-TableView__bulkBarCount' )
		).toContainText( 'matching events selected' );
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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

		await page
			.getByRole( 'button', { name: 'Reset to defaults' } )
			.click();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		const dataHeaders = async () =>
			(
				await page.locator( '.shp-TableView__th' ).allInnerTexts()
			 )
				.map( ( text ) => text.split( '\n' )[ 0 ].trim() )
				.filter( Boolean );

		expect( await dataHeaders() ).toEqual( [
			'Toggle event details',
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
				'Date',
				'Level',
				'User',
				'Message',
				'Actions',
			] );

		// Persisted server-side, not only in the URL.
		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		await expect
			.poll( dataHeaders )
			.toEqual( [
				'Toggle event details',
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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

	// The clipboard can only hold rows the browser has fetched. After the
	// two-step "select all matching", the selection is a number the page has
	// no data for — copying must say so rather than quietly serialising the
	// loaded subset, which is the same failure the export scope line exists
	// to prevent.
	test( 'copying says so when the selection is wider than what is loaded', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		await page
			.locator( '.shp-TableView__headerRow input[type="checkbox"]' )
			.check();

		const scope = page.locator( '.shp-TableView__bulkBarScope' );
		await scope.getByRole( 'button' ).click();

		const loadedCount = await page.locator( '.shp-TableView__row' ).count();

		await page
			.getByRole( 'button', { name: 'Copy the selected events' } )
			.click();

		await expect(
			page.locator( '.shp-TableView__bulkBarCopyNote' )
		).toContainText(
			`Only the ${ loadedCount } events loaded so far can be copied.`
		);
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page
			.getByRole( 'menuitem', { name: 'Save this view…' } )
			.click();

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
				page.evaluate( () =>
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
	test( 'the histogram counts the whole filtered set, and narrows to a day', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		const bars = page.locator( '.shp-TableView__histogramBar' );

		await expect.poll( () => bars.count() ).toBeGreaterThan( 0 );

		// The counts come from the aggregate endpoint, not from the rows:
		// one bar alone can hold more events than the table has loaded.
		const loadedCount = await page.locator( '.shp-TableView__row' ).count();
		const busiest = await page
			.locator( '.shp-TableView__histogramButton' )
			.evaluateAll( ( buttons ) =>
				Math.max(
					...buttons.map( ( button ) => {
						const label = button.getAttribute( 'aria-label' ) || '';

						return parseInt( label.replace( /\D.*$/, '' ), 10 ) || 0;
					} )
				)
			);

		expect( busiest ).toBeGreaterThan( loadedCount );

		await bars.last().getByRole( 'link' ).click();
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

	test( 'grouping counts the matching events and narrows to a group', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		await page.getByLabel( 'Group by' ).selectOption( 'level' );

		const groups = page.locator( '.shp-TableView__groupByRow' );

		await expect.poll( () => groups.count() ).toBeGreaterThan( 0 );

		// Largest first, which is the whole point of grouping.
		const counts = await groups.evaluateAll( ( rows ) =>
			rows.map(
				( row ) =>
					parseInt(
						(
							row.querySelector(
								'.shp-TableView__groupByCount'
							)?.textContent || '0'
						 ).replace( /\D/g, '' ),
						10
					) || 0
			)
		);

		expect( counts ).toEqual( [ ...counts ].sort( ( a, b ) => b - a ) );

		// The biggest group holds more events than the table has loaded, so
		// these counts cannot have come from the rows on screen.
		expect( counts[ 0 ] ).toBeGreaterThan(
			await page.locator( '.shp-TableView__row' ).count()
		);

		await groups.first().getByRole( 'link' ).click();
		await page.locator( '.shp-TableView__table' ).waitFor();

		await expect
			.poll( () => new URL( page.url() ).searchParams.get( 'levels' ) )
			.not.toBeNull();
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
				page.locator( '.shp-TableView__row--expanded, .shp-TableView__detailsPanel' ).count()
			)
			.toBeGreaterThan( 0 );
	} );

	// The shortcuts must not fire while the reader is typing, or `/` eats the
	// search box the first time anyone tries to use it.
	test( 'the shortcuts stay out of the way while typing', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
					.locator(
						'.shp-TableView__row .shp-TableView__col--user'
					)
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		await page.getByRole( 'button', { name: 'Saved views' } ).click();

		await expect(
			page.getByRole( 'menuitem', { name: 'Copy as WP-CLI command' } )
		).toBeVisible();
	} );
	// The query bar is a different keyboard for the filter UI, not a second
	// filtering system: it parses into the same URL parameters the chips
	// produce, so the server cannot tell the difference and the back button
	// undoes it.
	test( 'a typed query applies the same filters the chips would', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
					.locator(
						'.shp-TableView__row .shp-TableView__col--level'
					)
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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

		await page.getByRole( 'button', { name: 'Add a note' } ).first().click();
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
		await page.locator( '.shp-TableView__row' ).first().waitFor();

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
} );
