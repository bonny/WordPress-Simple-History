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

// Matches Table_View_Module::DEFAULT_COLUMNS in premium's PHP.
const DEFAULT_COLUMNS = [ 'date', 'user', 'message', 'level' ];

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

		await levelHeaderButton.click();

		// aria-busy lives on the <table> (.shp-TableView__table), not the
		// outer wrapper div the brief's sample checked — that div never
		// carries the attribute, so asserting against it cannot detect the
		// sort fetch finishing.
		await expect(
			page.locator( '.shp-TableView__table' )
		).not.toHaveAttribute( 'aria-busy', 'true' );

		await levelHeaderButton.click();

		await expect(
			page.locator( '.shp-TableView__table' )
		).not.toHaveAttribute( 'aria-busy', 'true' );

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
		// aria-busy clearing only means the fetch settled, not that the
		// virtualizer has mounted rows for the new data yet — waiting for
		// the first Level cell closes that gap, rather than reading
		// allTextContents() (a point-in-time query, unlike the auto-waiting
		// assertions above) against a table that has not repainted.
		await page.locator( '.shp-TableView__td--level' ).first().waitFor();

		const levelValues = await page
			.locator( '.shp-TableView__td--level' )
			.allTextContents();

		expect( levelValues.length ).toBeGreaterThan( 0 );

		for ( let i = 1; i < levelValues.length; i++ ) {
			expect(
				levelValues[ i ].localeCompare(
					levelValues[ i - 1 ],
					undefined,
					{ sensitivity: 'base' }
				)
			).toBeGreaterThanOrEqual( 0 );
		}
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
} );
