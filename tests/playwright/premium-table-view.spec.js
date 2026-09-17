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

	test( 'clicking the date button opens the event details modal for that row', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
		await page.locator( '.shp-TableView__row' ).first().waitFor();

		const firstRow = page.locator( '.shp-TableView__row' ).first();
		const eventId = await firstRow.getAttribute( 'data-event-id' );

		await firstRow.locator( '.shp-TableView__dateButton' ).click();

		const dialog = page.getByRole( 'dialog' );
		await expect( dialog ).toBeVisible();

		// Not just "a dialog appeared" — the modal's own event-details table
		// carries the id of the event it loaded, so this confirms it opened
		// the clicked row's event and not merely the first/last one shown.
		await expect( dialog.locator( 'td:text-is("id") + td' ) ).toHaveText(
			eventId
		);
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

		const firstIdBefore = await page
			.locator( '.shp-TableView__row' )
			.first()
			.getAttribute( 'data-event-id' );

		// Level is sortable and uncorrelated with insertion order, so a real
		// server-side sort changes which row comes first.
		await page.getByRole( 'button', { name: /Level/ } ).click();

		// aria-busy lives on the <table> (.shp-TableView__table), not the
		// outer wrapper div the brief's sample checked — that div never
		// carries the attribute, so asserting against it cannot detect the
		// sort fetch finishing.
		await expect(
			page.locator( '.shp-TableView__table' )
		).not.toHaveAttribute( 'aria-busy', 'true' );

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
} );
