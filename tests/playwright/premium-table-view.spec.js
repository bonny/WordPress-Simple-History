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

/**
 * Whether the table's Level column reads back in non-decreasing order —
 * what a server-side ascending sort on the raw `level` column produces,
 * since the visible labels are just capitalised versions of the same words.
 *
 * @param {string[]} levelValues Visible text of every Level cell, top to bottom.
 * @return {boolean} True if sorted (and non-empty); false otherwise.
 */
function isLevelColumnNonDecreasing( levelValues ) {
	if ( levelValues.length === 0 ) {
		return false;
	}

	for ( let i = 1; i < levelValues.length; i++ ) {
		if (
			levelValues[ i ].localeCompare( levelValues[ i - 1 ], undefined, {
				sensitivity: 'base',
			} ) < 0
		) {
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
			const rowTops = await page
				.locator( '.shp-TableView__row' )
				.evaluateAll( ( rows ) =>
					rows.slice( 0, 5 ).map( ( row ) => {
						// Excludes the (collapsed, here) details panel on
						// purpose — that cell is *meant* to sit on its own
						// line below the others; it is the data cells
						// beside it in .shp-TableView__rowCells that must
						// never wrap.
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

			expect( rowTops.length ).toBeGreaterThan( 0 );

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
} );
