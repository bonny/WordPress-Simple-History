const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

const TOGGLE_EXPERIMENTAL =
	'simple-history/v1/dev-tools/toggle-experimental-features';

/**
 * Set the site-wide experimental features option. The dev-tools endpoint only
 * toggles, so read the result and flip again if we overshot.
 *
 * @param {Object}  requestUtils
 * @param {boolean} desired
 * @return {Promise<boolean>} The resulting state.
 */
async function setExperimentalFeatures( requestUtils, desired ) {
	let res = await requestUtils.rest( {
		method: 'POST',
		path: TOGGLE_EXPERIMENTAL,
	} );

	if ( res.is_enabled !== desired ) {
		res = await requestUtils.rest( {
			method: 'POST',
			path: TOGGLE_EXPERIMENTAL,
		} );
	}

	return res.is_enabled;
}

/**
 * Save the admin's stored view directly, so each test starts from a known state.
 *
 * @param {Object} requestUtils
 * @param {string} view
 */
async function setStoredView( requestUtils, view ) {
	await requestUtils.rest( {
		path: '/simple-history/v1/events-view',
		method: 'POST',
		data: { view },
	} );
}

/**
 * Match the response of the events-view save request. Works with both
 * pretty permalinks (`/wp-json/simple-history/v1/events-view`) and plain
 * permalinks (`?rest_route=/simple-history/v1/events-view`), since both
 * forms carry the route in the decoded URL.
 *
 * @param {import('@playwright/test').Response} response
 * @return {boolean} Whether this response is the save request's response.
 */
function isEventsViewSaveResponse( response ) {
	return (
		decodeURIComponent( response.url() ).includes(
			'simple-history/v1/events-view'
		) && response.request().method() === 'POST'
	);
}

test.describe( 'Event log view toggle', () => {
	// All tests share the admin's stored preference and the site-wide
	// experimental features option.
	test.describe.configure( { mode: 'serial' } );

	let originalExperimental;

	test.beforeAll( async ( { requestUtils } ) => {
		// Probe the dev-tools endpoint; skip loudly if it isn't available so the
		// suite fails rather than silently passing in the wrong environment.
		try {
			const res = await requestUtils.rest( {
				method: 'POST',
				path: TOGGLE_EXPERIMENTAL,
			} );
			originalExperimental = ! res.is_enabled;
			await setExperimentalFeatures( requestUtils, originalExperimental );
		} catch ( err ) {
			test.skip(
				true,
				`Dev-tools toggle-experimental-features endpoint unreachable — is SIMPLE_HISTORY_DEV enabled? (${ err.message })`
			);
		}
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		await setExperimentalFeatures( requestUtils, true );
		await setStoredView( requestUtils, 'detailed' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await setStoredView( requestUtils, 'detailed' );

		if ( typeof originalExperimental === 'boolean' ) {
			await setExperimentalFeatures( requestUtils, originalExperimental );
		}
	} );

	test( 'defaults to the detailed view', async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'radio', { name: 'Detailed view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-normal' ).first()
		).toBeVisible();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' )
		).toHaveCount( 0 );
	} );

	test( 'switching to compact keeps the actions menu and sets the URL', async ( {
		page,
	} ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		const saved = page.waitForResponse( isEventsViewSaveResponse );

		await page.getByRole( 'radio', { name: 'Compact view' } ).check();

		const firstCompact = page
			.locator( '.SimpleHistoryLogitem--variant-compact' )
			.first();

		await expect( firstCompact ).toBeVisible();
		await expect(
			page.locator(
				'.SimpleHistoryLogitem--variant-compact .SimpleHistoryLogitem__details'
			)
		).toHaveCount( 0 );
		await expect(
			firstCompact.locator( '.SimpleHistoryLogitem__actions' )
		).toHaveCount( 1 );
		await expect( page ).toHaveURL( /[?&]view=compact/ );

		expect( ( await saved ).ok() ).toBe( true );
	} );

	test( 'compact is remembered after a reload without the parameter', async ( {
		page,
	} ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		const saved = page.waitForResponse( isEventsViewSaveResponse );

		await page.getByRole( 'radio', { name: 'Compact view' } ).check();
		expect( ( await saved ).ok() ).toBe( true );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'radio', { name: 'Compact view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' ).first()
		).toBeVisible();
	} );

	test( 'the URL parameter overrides the stored view', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'compact' );

		await page.goto( `${ SIMPLE_HISTORY_PAGE }&view=detailed` );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'radio', { name: 'Detailed view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' )
		).toHaveCount( 0 );
	} );

	test( 'the toggle is gone and the log is detailed when experimental features are off', async ( {
		page,
		requestUtils,
	} ) => {
		// A stored compact preference from when the flag was on must not keep
		// the compact view alive after the site turns experimental features off.
		await setStoredView( requestUtils, 'compact' );
		await setExperimentalFeatures( requestUtils, false );

		await page.goto( `${ SIMPLE_HISTORY_PAGE }&view=compact` );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'radio', { name: 'Compact view' } )
		).toHaveCount( 0 );
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' )
		).toHaveCount( 0 );
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-normal' ).first()
		).toBeVisible();
	} );

	test( 'arrow keys switch the view', async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		const saved = page.waitForResponse( isEventsViewSaveResponse );

		await page.getByRole( 'radio', { name: 'Detailed view' } ).focus();
		await page.keyboard.press( 'ArrowRight' );

		await expect(
			page.getByRole( 'radio', { name: 'Compact view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' ).first()
		).toBeVisible();

		expect( ( await saved ).ok() ).toBe( true );
	} );
} );
