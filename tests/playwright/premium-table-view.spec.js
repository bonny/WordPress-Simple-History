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

test.describe( 'Premium table view', () => {
	test.describe.configure( { mode: 'serial' } );

	test.afterAll( async ( { requestUtils } ) => {
		await setStoredView( requestUtils, 'detailed' );
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
} );
