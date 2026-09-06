const { test, expect } = require( './fixtures' );

const HISTORY_PAGE = '/wp-admin/admin.php?page=simple_history_admin_menu_page';
const SETTINGS_PAGE = '/wp-admin/admin.php?page=simple_history_settings_page';
const LICENSES_TAB =
	'/wp-admin/admin.php?page=simple_history_settings_page&selected-tab=general_settings_subtab_general&selected-sub-tab=general_settings_subtab_licenses';

const PREMIUM_FILE = 'simple-history-premium/simple-history-premium.php';
const PREMIUM_SLUG = 'simple-history-premium';

// Sets up the "premium active, no license key" state via the dev-tools REST
// endpoints (gated by SIMPLE_HISTORY_DEV). If premium isn't installed at all,
// skips the suite so the tests fail loudly rather than silently passing in the
// wrong environment.
//
// Serial mode: tests share the underlying premium plugin state and the test
// user's dismissed-addons meta. Running them in parallel would race those
// mutations against each other.
test.describe.configure( { mode: 'serial' } );

test.describe( 'License reminder card', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		let status;
		try {
			status = await requestUtils.rest( {
				path: 'simple-history/v1/dev-tools/plugin-status',
				params: { plugin: PREMIUM_FILE },
			} );
		} catch ( err ) {
			test.skip(
				true,
				`Dev-tools REST endpoint unreachable — is SIMPLE_HISTORY_DEV enabled? (${ err.message })`
			);
			return;
		}

		if ( ! status.is_active ) {
			await requestUtils.rest( {
				method: 'POST',
				path: 'simple-history/v1/dev-tools/toggle-plugin',
				data: { plugin: PREMIUM_FILE },
			} );
		}

		await requestUtils.rest( {
			method: 'POST',
			path: 'simple-history/v1/dev-tools/set-license-key',
			data: { slug: PREMIUM_SLUG, key: '' },
		} );
	} );

	// Reset to the canonical "no license key, no dismissals" state before
	// each test so they don't depend on each other. Wrap each REST call so a
	// missing endpoint surfaces a focused error instead of an opaque
	// rest_forbidden / 404 from inside Playwright's request util.
	test.beforeEach( async ( { requestUtils } ) => {
		try {
			await requestUtils.rest( {
				method: 'POST',
				path: 'simple-history/v1/dev-tools/set-license-key',
				data: { slug: PREMIUM_SLUG, key: '' },
			} );
			await requestUtils.rest( {
				method: 'POST',
				path: 'simple-history/v1/dev-tools/reset-license-reminder-dismissals',
			} );
		} catch ( err ) {
			throw new Error(
				`License-reminder test setup failed — is SIMPLE_HISTORY_DEV enabled and the dev-tools controller loaded? (${ err.message })`
			);
		}
	} );

	test( 'shows on history page when premium active without license', async ( {
		page,
	} ) => {
		await page.goto( HISTORY_PAGE );
		const card = page.locator( '.sh-LicenseReminder' );
		await expect( card ).toBeVisible();
		await expect( card ).toContainText(
			'Add your Simple History Premium license key'
		);
		await expect(
			card.getByRole( 'link', { name: 'Add license key' } )
		).toBeVisible();
	} );

	test( 'does not show on settings page (sidebar-only surface)', async ( {
		page,
	} ) => {
		await page.goto( SETTINGS_PAGE );
		await expect( page.locator( '.sh-LicenseReminder' ) ).toHaveCount( 0 );
	} );

	test( 'CTA links to the licenses sub-tab', async ( { page } ) => {
		await page.goto( HISTORY_PAGE );
		const link = page.locator( '.sh-LicenseReminder a.button-primary' );
		const href = await link.getAttribute( 'href' );
		expect( href ).toContain( 'general_settings_subtab_licenses' );
	} );

	test( 'explicit dismiss hides the card and it stays gone after reload', async ( {
		page,
	} ) => {
		await page.goto( HISTORY_PAGE );
		const card = page.locator( '.sh-LicenseReminder' );
		await expect( card ).toBeVisible();

		// Use class selector rather than getByRole({name: 'Dismiss'}) so the
		// test stays stable under non-en_US locales.
		await card.locator( '.sh-LicenseReminder-dismiss' ).click();

		// The fadeOut() animation hides the card client-side.
		await expect( card ).toBeHidden();

		// And after a reload, the server-side check filters it out.
		await page.goto( HISTORY_PAGE );
		await expect( page.locator( '.sh-LicenseReminder' ) ).toHaveCount( 0 );
	} );

	test( 'visiting the licenses sub-tab implicitly dismisses the card', async ( {
		page,
		requestUtils,
	} ) => {
		// Baseline: card visible AND the dismissed-addons meta is empty.
		await page.goto( HISTORY_PAGE );
		await expect( page.locator( '.sh-LicenseReminder' ) ).toBeVisible();

		const before = await requestUtils.rest( {
			path: 'simple-history/v1/dev-tools/license-reminder-dismissals',
		} );
		expect( before.dismissed_addons ).toEqual( [] );

		// User finds the licenses tab on their own.
		await page.goto( LICENSES_TAB );

		// Direct invariant: admin_init wrote the premium slug into user meta.
		// This locks in the actual cause, so a regression that hides the card
		// for any other reason (e.g. should_show filter side effect) still
		// fails the test.
		const after = await requestUtils.rest( {
			path: 'simple-history/v1/dev-tools/license-reminder-dismissals',
		} );
		expect( after.dismissed_addons ).toContain( PREMIUM_SLUG );

		// Downstream effect: returning to the history page, the card is gone.
		await page.goto( HISTORY_PAGE );
		await expect( page.locator( '.sh-LicenseReminder' ) ).toHaveCount( 0 );
	} );

	test( 'hides when license key is set, reappears when cleared', async ( {
		page,
		requestUtils,
	} ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: 'simple-history/v1/dev-tools/set-license-key',
			data: { slug: PREMIUM_SLUG, key: 'TEST-KEY-VISIBLE-WHEN-CLEARED' },
		} );

		await page.goto( HISTORY_PAGE );
		await expect( page.locator( '.sh-LicenseReminder' ) ).toHaveCount( 0 );

		await requestUtils.rest( {
			method: 'POST',
			path: 'simple-history/v1/dev-tools/set-license-key',
			data: { slug: PREMIUM_SLUG, key: '' },
		} );

		await page.goto( HISTORY_PAGE );
		await expect( page.locator( '.sh-LicenseReminder' ) ).toBeVisible();
	} );
} );
