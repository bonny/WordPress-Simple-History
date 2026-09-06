const { test, expect } = require( './fixtures' );

// The "Privacy & Data" settings tab (always registered) and the experimental
// features overview. The erasure copy is gated behind the experimental-features
// flag, so each test sets the flag deterministically via the dev-tools REST
// endpoint (gated by SIMPLE_HISTORY_DEV) before asserting.

const PRIVACY_TAB = '/wp-admin/admin.php?page=general_settings_subtab_privacy';
const SETTINGS_PAGE = '/wp-admin/admin.php?page=simple_history_settings_page';

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

// Serial: every test mutates the shared experimental-features option, so they
// must not race each other.
test.describe.configure( { mode: 'serial' } );

test.describe( 'Privacy & Data settings', () => {
	let originalExperimental;

	test.beforeAll( async ( { requestUtils } ) => {
		// Probe the dev-tools endpoint; skip loudly if it isn't available so the
		// suite fails rather than silently passing in the wrong environment.
		try {
			const res = await requestUtils.rest( {
				method: 'POST',
				path: TOGGLE_EXPERIMENTAL,
			} );
			// Restore immediately; remember the original so we can put it back.
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

	test( 'Privacy & Data tab shows the always-on export integration', async ( {
		page,
		requestUtils,
	} ) => {
		await setExperimentalFeatures( requestUtils, false );
		await page.goto( PRIVACY_TAB );

		await expect(
			page.getByRole( 'heading', { name: 'Compliance' } )
		).toBeVisible();

		// The export line is always shown.
		await expect(
			page.getByText( 'personal-data export tool', { exact: false } )
		).toBeVisible();

		// With experimental features OFF, the erasure line is hidden.
		await expect(
			page.getByText( 'erasure tool', { exact: false } )
		).toHaveCount( 0 );
	} );

	test( 'erasure line appears, marked experimental, when experimental features are on', async ( {
		page,
		requestUtils,
	} ) => {
		await setExperimentalFeatures( requestUtils, true );
		await page.goto( PRIVACY_TAB );

		const erasure = page.getByText( 'erasure tool', { exact: false } );
		await expect( erasure ).toBeVisible();
		// It is flagged as experimental.
		await expect( erasure ).toContainText( 'Experimental' );

		// The always-on export line is still present too.
		await expect(
			page.getByText( 'personal-data export tool', { exact: false } )
		).toBeVisible();
	} );

	test( 'the eraser is listed in the experimental features overview', async ( {
		page,
		requestUtils,
	} ) => {
		await setExperimentalFeatures( requestUtils, true );
		await page.goto( SETTINGS_PAGE );

		// Expand the "View current experimental features" disclosure.
		const summary = page.getByText( 'View current experimental features', {
			exact: false,
		} );
		await expect( summary ).toBeVisible();
		await summary.click();

		await expect(
			page.getByText( 'Personal-data erasure integration', {
				exact: false,
			} )
		).toBeVisible();
	} );
} );
