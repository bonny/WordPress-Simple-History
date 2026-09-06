const { test } = require( '@playwright/test' );
const path = require( 'path' );
const {
	loginAdmin,
	hideAdminNotices,
	resetHoverState,
} = require( './screenshot-helpers' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Capture spec used by tests/screenshot/run.sh against a fresh WordPress
// Playground instance. Logs in as admin/password (the playground defaults
// from the blueprint's `login` step) and saves to .wordpress-org/screenshot-1.png.
test.use( {
	viewport: { width: 1600, height: 1130 },
	deviceScaleFactor: 2,
} );

test( 'capture main screenshot from playground', async ( { page } ) => {
	// Generous overall budget — playground first-page hit can be slow when
	// the blueprint populated ~70 historical events.
	test.setTimeout( 120_000 );

	await loginAdmin( page );

	// Surface failed network requests so we know if React broke loading data.
	page.on( 'response', ( res ) => {
		if ( res.status() >= 400 ) {
			console.log( 'BAD-RESP', res.status(), res.url() );
		}
	} );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded', {
		timeout: 60_000,
	} );

	// Let avatars / attachment thumbs finish loading.
	await page.waitForTimeout( 2000 );

	// Hide any admin notices for a clean shot.
	await hideAdminNotices( page );

	await resetHoverState( page );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-1.png'
	);
	await page.screenshot( { path: outputPath, fullPage: false } );
} );
