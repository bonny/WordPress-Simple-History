const { test } = require( '@playwright/test' );
const path = require( 'path' );
const {
	loginAdmin,
	hideAdminNotices,
	resetHoverState,
} = require( './screenshot-helpers' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Clips the standalone "History Insights" sidebar widget (`.sh-SidebarStats`)
// rendered next to the event log. Highlights the quick stats / activity chart
// / most active users tile that sells the data-density story even when the
// main log isn't in frame. Saves to .wordpress-org/screenshot-7.png.
test.use( {
	viewport: { width: 1600, height: 1200 },
	deviceScaleFactor: 2,
} );

test( 'capture insights sidebar widget from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	await loginAdmin( page );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded', {
		timeout: 60_000,
	} );
	await page.waitForSelector( '.sh-SidebarStats', { timeout: 30_000 } );
	await page.waitForTimeout( 2000 );

	await hideAdminNotices( page );

	await resetHoverState( page, '.sh-SidebarStats, .sh-SidebarStats *' );

	const widget = page.locator( '.sh-SidebarStats' ).first();
	await widget.waitFor( { state: 'visible', timeout: 30_000 } );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-7.png'
	);

	await widget.screenshot( { path: outputPath } );
} );
