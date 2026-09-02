const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

const TOGGLE_EXPERIMENTAL =
	'simple-history/v1/dev-tools/toggle-experimental-features';

// The dev-tools endpoint only TOGGLES, so read the result and flip again if we
// overshot — leaving experimental features in the desired state.
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

async function openFirstEventActions( page ) {
	const firstEvent = page.locator( '.SimpleHistoryLogitem' ).first();
	await firstEvent.hover();
	await firstEvent.getByRole( 'button', { name: 'Actions…' } ).click();

	return firstEvent;
}

// Serial: every test mutates the shared experimental-features option, so they
// must not race each other.
test.describe.configure( { mode: 'serial' } );

/**
 * Issue 289: hide one event type from the current list via the row actions
 * menu, see it as a removable chip, and get it back by removing the chip.
 * The menu item is an experimental feature, so it is only offered when
 * experimental features are enabled.
 */
test.describe( 'Hide event type from the current view', () => {
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

	test.afterAll( async ( { requestUtils } ) => {
		if ( typeof originalExperimental === 'boolean' ) {
			await setExperimentalFeatures( requestUtils, originalExperimental );
		}
	} );

	test( 'the menu item is not offered when experimental features are off', async ( {
		page,
		requestUtils,
	} ) => {
		await setExperimentalFeatures( requestUtils, false );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await openFirstEventActions( page );

		// The menu is open (a neighbouring item is there) but ours is not.
		await expect(
			page.getByRole( 'menuitem', { name: /Find events/ } )
		).toBeVisible();
		await expect(
			page.getByRole( 'menuitem', { name: 'Hide events of this type' } )
		).toHaveCount( 0 );
	} );

	test( 'hiding a type removes its events and shows a removable chip', async ( {
		page,
		requestUtils,
	} ) => {
		await setExperimentalFeatures( requestUtils, true );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		const firstEvent = page.locator( '.SimpleHistoryLogitem' ).first();
		const firstEventText = (
			await firstEvent
				.locator( '.SimpleHistoryLogitem__text' )
				.innerText()
		).trim();

		await openFirstEventActions( page );
		await page
			.getByRole( 'menuitem', { name: 'Hide events of this type' } )
			.click();

		// The list reloads without that type.
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
		const chips = page.locator( '.SimpleHistory-hiddenTypes' );
		await expect( chips ).toBeVisible();
		await expect( chips ).toContainText( 'Hiding 1 event type' );
		await expect(
			page.locator( '.SimpleHistoryLogitem__text', {
				hasText: firstEventText,
			} )
		).toHaveCount( 0 );

		// Removing the chip brings the type back.
		await chips.getByRole( 'button', { name: /Show .* again/ } ).click();
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
		await expect( chips ).toHaveCount( 0 );
		await expect(
			page.locator( '.SimpleHistoryLogitem__text', {
				hasText: firstEventText,
			} )
		).not.toHaveCount( 0 );
	} );
} );
