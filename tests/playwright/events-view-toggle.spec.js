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

// Shared with both describe blocks below: the free preview only renders when
// nothing fills SimpleHistorySlotTableView, and Premium is active on the dev
// site by default (see premium-helpers.js), so testing the preview means
// toggling the real plugin off rather than faking a flag.
const PREMIUM_FILE = 'simple-history-premium/simple-history-premium.php';

/**
 * Set the premium plugin's active state, toggling only if it differs
 * from what's asked for.
 *
 * @param {Object}  requestUtils
 * @param {boolean} desiredActive
 */
async function setPremiumActive( requestUtils, desiredActive ) {
	const status = await requestUtils.rest( {
		path: 'simple-history/v1/dev-tools/plugin-status',
		params: { plugin: PREMIUM_FILE },
	} );

	if ( status.is_active !== desiredActive ) {
		await requestUtils.rest( {
			method: 'POST',
			path: 'simple-history/v1/dev-tools/toggle-plugin',
			data: { plugin: PREMIUM_FILE },
		} );
	}
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
			page.getByRole( 'button', { name: 'Detailed view' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
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

		await page.getByRole( 'button', { name: 'Compact view' } ).click();

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

		await page.getByRole( 'button', { name: 'Compact view' } ).click();
		expect( ( await saved ).ok() ).toBe( true );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'button', { name: 'Compact view' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
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
			page.getByRole( 'button', { name: 'Detailed view' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' )
		).toHaveCount( 0 );
	} );

	test( 'view toggle shows when experimental features are off', async ( {
		page,
		requestUtils,
	} ) => {
		await setExperimentalFeatures( requestUtils, false );
		await setStoredView( requestUtils, 'detailed' );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.locator( '.sh-EventsViewToggle' ).waitFor();

		await expect(
			page.getByRole( 'button', { name: 'Compact view' } )
		).toBeVisible();
	} );

	test( 'compact view works when experimental features are off', async ( {
		page,
		requestUtils,
	} ) => {
		await setExperimentalFeatures( requestUtils, false );
		await setStoredView( requestUtils, 'compact' );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.locator( '.sh-EventsViewToggle' ).waitFor();

		await expect(
			page.getByRole( 'button', { name: 'Compact view' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	// Tab has to reach the button and Space or Enter has to switch the view:
	// the first version was a radio group, where Tab landed on the already
	// selected option and Space did nothing at all.
	for ( const key of [ 'Space', 'Enter' ] ) {
		test( `tab and ${ key } switch the view`, async ( { page } ) => {
			await page.goto( SIMPLE_HISTORY_PAGE );
			await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

			const saved = page.waitForResponse( isEventsViewSaveResponse );

			// Tab from the button before the toggle, so the walk through the
			// control bar is part of the test rather than a direct focus() call.
			await page.getByRole( 'button', { name: /Share view/i } ).focus();
			await page.keyboard.press( 'Tab' );

			await expect(
				page.getByRole( 'button', { name: 'Detailed view' } )
			).toBeFocused();

			await page.keyboard.press( 'Tab' );

			await expect(
				page.getByRole( 'button', { name: 'Compact view' } )
			).toBeFocused();

			await page.keyboard.press( key );

			await expect(
				page.getByRole( 'button', { name: 'Compact view' } )
			).toHaveAttribute( 'aria-pressed', 'true' );
			await expect(
				page.locator( '.SimpleHistoryLogitem--variant-compact' ).first()
			).toBeVisible();

			expect( ( await saved ).ok() ).toBe( true );
		} );
	}

	// The free TablePreview only renders when nothing fills
	// SimpleHistorySlotTableView. Since Premium now fills that Slot with a
	// real table (see premium-table-view.spec.js), these tests deactivate
	// Premium for their duration to exercise the free-plugin fallback that
	// they were written to cover.
	test.describe( 'table view free preview (Premium deactivated)', () => {
		let premiumWasActive;

		test.beforeAll( async ( { requestUtils } ) => {
			try {
				const status = await requestUtils.rest( {
					path: 'simple-history/v1/dev-tools/plugin-status',
					params: { plugin: PREMIUM_FILE },
				} );
				premiumWasActive = status.is_active;

				await setPremiumActive( requestUtils, false );
			} catch ( err ) {
				test.skip(
					true,
					`Dev-tools REST endpoint unreachable — is SIMPLE_HISTORY_DEV enabled? (${ err.message })`
				);
			}
		} );

		test.afterAll( async ( { requestUtils } ) => {
			if ( typeof premiumWasActive === 'boolean' ) {
				await setPremiumActive( requestUtils, premiumWasActive );
			}
		} );

		test( 'table view shows the premium preview', async ( {
			page,
			requestUtils,
		} ) => {
			await setStoredView( requestUtils, 'detailed' );

			await page.goto( SIMPLE_HISTORY_PAGE );
			await page.locator( '.sh-EventsViewToggle' ).waitFor();

			await page.getByRole( 'button', { name: 'Table view' } ).click();

			await expect( page.locator( '.sh-TablePreview' ) ).toBeVisible();
		} );

		test( 'table view can be linked to with ?view=table', async ( {
			page,
			requestUtils,
		} ) => {
			await setStoredView( requestUtils, 'detailed' );

			await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );

			await expect( page.locator( '.sh-TablePreview' ) ).toBeVisible();
			await expect(
				page.getByRole( 'button', { name: 'Table view' } )
			).toHaveAttribute( 'aria-pressed', 'true' );
		} );

		// Every other spec above reaches the table view through a click or
		// ?view=table, which is exactly why a stored `table` preference being
		// silently downgraded to `detailed` on a fresh, param-less load went
		// unnoticed (dropins/class-react-dropin.php and EventsGui.jsx both used
		// to accept only 'compact', collapsing anything else to 'detailed').
		test( 'table is remembered after a reload without the parameter', async ( {
			page,
			requestUtils,
		} ) => {
			await setStoredView( requestUtils, 'table' );

			await page.goto( SIMPLE_HISTORY_PAGE );

			await expect( page.locator( '.sh-TablePreview' ) ).toBeVisible();
			await expect(
				page.getByRole( 'button', { name: 'Table view' } )
			).toHaveAttribute( 'aria-pressed', 'true' );
		} );

		test( 'table preview is inert and its CTA is not', async ( {
			page,
			requestUtils,
		} ) => {
			await setStoredView( requestUtils, 'detailed' );

			await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
			await page.locator( '.sh-TablePreview' ).waitFor();

			// The sample table is decoration: hidden from screen readers and
			// unreachable by the mouse.
			const sampleTable = page.locator( '.sh-TablePreview__table' );
			await expect( sampleTable ).toHaveAttribute(
				'aria-hidden',
				'true'
			);
			await expect( sampleTable ).toHaveCSS( 'pointer-events', 'none' );

			// Every checkbox in it is disabled.
			const boxes = sampleTable.locator( 'input[type="checkbox"]' );
			const count = await boxes.count();
			expect( count ).toBeGreaterThan( 0 );

			for ( let i = 0; i < count; i++ ) {
				await expect( boxes.nth( i ) ).toBeDisabled();
			}

			// The upgrade link stays clickable and carries the campaign.
			const cta = page.locator( '.sh-TablePreview__banner a' ).first();
			await expect( cta ).toBeVisible();

			const href = await cta.getAttribute( 'href' );
			expect( href ).toContain( 'utm_campaign=premium_table_view' );
			expect( href ).toContain( 'utm_content=' );
		} );
	} );
} );

// The Table button carries a Premium indication for free users only — a
// paying customer already owns the feature and shouldn't see it marked as an
// upsell. This shares the "toggle the real premium plugin" approach from
// license-reminder.spec.js since hasPremiumAddOn comes from whether that
// plugin is active, not from a flag we can fake through the dev-tools option
// toggles used above.
test.describe( 'Table view button premium indicator', () => {
	test.describe.configure( { mode: 'serial' } );

	let premiumWasActive;

	test.beforeAll( async ( { requestUtils } ) => {
		try {
			const status = await requestUtils.rest( {
				path: 'simple-history/v1/dev-tools/plugin-status',
				params: { plugin: PREMIUM_FILE },
			} );
			premiumWasActive = status.is_active;
		} catch ( err ) {
			test.skip(
				true,
				`Dev-tools REST endpoint unreachable — is SIMPLE_HISTORY_DEV enabled? (${ err.message })`
			);
		}
	} );

	test.afterAll( async ( { requestUtils } ) => {
		if ( typeof premiumWasActive === 'boolean' ) {
			await setPremiumActive( requestUtils, premiumWasActive );
		}
	} );

	test( 'shows the premium indicator on the Table button when premium is not active', async ( {
		page,
		requestUtils,
	} ) => {
		await setPremiumActive( requestUtils, false );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.locator( '.sh-EventsViewToggle' ).waitFor();

		const tableButton = page.getByRole( 'button', { name: 'Table view' } );
		await expect( tableButton ).toHaveAttribute(
			'aria-label',
			'Table view'
		);
		await expect(
			tableButton.locator( '.sh-PremiumIndicator' )
		).toHaveCount( 1 );
	} );

	test( 'hides the premium indicator on the Table button when premium is active', async ( {
		page,
		requestUtils,
	} ) => {
		await setPremiumActive( requestUtils, true );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.locator( '.sh-EventsViewToggle' ).waitFor();

		const tableButton = page.getByRole( 'button', { name: 'Table view' } );
		await expect( tableButton ).toHaveAttribute(
			'aria-label',
			'Table view'
		);
		await expect(
			tableButton.locator( '.sh-PremiumIndicator' )
		).toHaveCount( 0 );
	} );
} );

/**
 * Put one view's sidebar into a known state, through the UI.
 *
 * Deliberately not through the REST route. The preference is user meta and so
 * outlives the run, and requestUtils authenticates as a different user than
 * the browser session does — so seeding it over REST wrote the preference for
 * an account this page never loads as, and the spec failed in the confusing
 * direction, reporting that a view it had just configured ignored the setting.
 *
 * @param {Object}  page       Playwright page.
 * @param {string}  view       View to visit.
 * @param {boolean} shouldHide Whether the sidebar should end up hidden.
 */
async function setSidebar( page, view, shouldHide ) {
	const toggle = page.locator( '.sh-EventsSidebarToggle' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=' + view );
	await toggle.waitFor();

	const isHidden = ( await toggle.getAttribute( 'aria-pressed' ) ) === 'true';

	if ( isHidden !== shouldHide ) {
		await clickAndSave( page, toggle );
	}

	await expect( toggle ).toHaveAttribute(
		'aria-pressed',
		shouldHide ? 'true' : 'false'
	);
}

/**
 * Click the sidebar toggle and wait for the preference to be written.
 *
 * The toggle does not block on its own save — a failed one is not worth
 * interrupting anyone for, and the sidebar moves immediately either way. That
 * makes clicking and then reloading a race, which is fine for a person and not
 * fine for a test: reloading inside the same tick lost the write, and this
 * spec failed about one run in two.
 *
 * @param {Object} page   Playwright page.
 * @param {Object} toggle Locator for the toggle button.
 */
async function clickAndSave( page, toggle ) {
	await Promise.all( [
		page.waitForResponse(
			( response ) =>
				response.url().includes( '/sidebar-visibility' ) &&
				response.request().method() === 'POST'
		),
		toggle.click(),
	] );
}

test.describe( 'Page sidebar toggle', () => {
	test( 'the sidebar is remembered per view, not per page', async ( {
		page,
	} ) => {
		const sidebar = page.locator( '.SimpleHistory__pageSidebar' );
		const toggle = page.locator( '.sh-EventsSidebarToggle' );

		// A known starting point, since the preference outlives the run.
		await setSidebar( page, 'compact', false );
		await setSidebar( page, 'detailed', false );

		await expect( sidebar ).toBeVisible();

		// Hiding it here must stick across a reload...
		await clickAndSave( page, toggle );
		await expect( sidebar ).toBeHidden();

		await page.reload();
		await toggle.waitFor();
		await expect( sidebar ).toBeHidden();

		// ...and must not follow the reader into a view they never touched,
		// which is the whole reason the preference is a set of views rather
		// than one boolean.
		await page.goto( SIMPLE_HISTORY_PAGE + '&view=compact' );
		await toggle.waitFor();
		await expect( sidebar ).toBeVisible();

		// The table view, the other direction: showing it there sticks too.
		await setSidebar( page, 'table', true );
		await expect( sidebar ).toBeHidden();

		await clickAndSave( page, toggle );
		await expect( sidebar ).toBeVisible();

		await page.reload();
		await toggle.waitFor();
		await expect( sidebar ).toBeVisible();

		// Back to the shipped defaults, so the specs that follow see the
		// detailed list with its sidebar where they expect it.
		await setSidebar( page, 'table', true );
		await setSidebar( page, 'compact', false );
		await setSidebar( page, 'detailed', false );
	} );
} );
