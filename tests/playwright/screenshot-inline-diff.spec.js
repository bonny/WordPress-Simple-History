const { test } = require( '@playwright/test' );
const path = require( 'path' );
const {
	loginAdmin,
	hideAdminNotices,
	resetHoverState,
} = require( './screenshot-helpers' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Zoom on a single event with rich inline diff details ("About us" page
// update from events.php). Clips to the event row + its diff so the
// before/after comparison is the entire frame. Saves to
// .wordpress-org/screenshot-2.png.
test.use( {
	viewport: { width: 1100, height: 1000 },
	deviceScaleFactor: 2,
} );

test( 'capture inline diff zoom from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	await loginAdmin( page );
	page.on( 'response', ( res ) => {
		if ( res.status() >= 400 ) {
			console.log( 'BAD-RESP', res.status(), res.url() );
		}
	} );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded', {
		timeout: 60_000,
	} );
	await page.waitForTimeout( 2000 );

	await hideAdminNotices( page );

	await resetHoverState( page );

	// Locate the "About us" page-update event by its message text. It's the
	// only event with both a title and content diff visible.
	const aboutEvent = page.locator( '.SimpleHistoryLogitem', {
		hasText: 'Updated page "About us"',
	} );

	await aboutEvent.waitFor( { state: 'visible', timeout: 30_000 } );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-2.png'
	);

	await aboutEvent.screenshot( { path: outputPath } );
} );
