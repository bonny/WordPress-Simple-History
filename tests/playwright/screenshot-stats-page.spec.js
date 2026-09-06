const { test } = require( '@playwright/test' );
const path = require( 'path' );
const { loginAdmin, hideAdminNotices } = require( './screenshot-helpers' );

const STATS_PAGE = '/wp-admin/admin.php?page=simple_history_stats_page';

// Captures the History Insights / Stats & Summaries admin page — the
// dedicated dashboard view with daily activity, top users, top loggers, etc.
// Saves to .wordpress-org/screenshot-8.png.
test.use( {
	viewport: { width: 1600, height: 1400 },
	deviceScaleFactor: 2,
} );

test( 'capture stats page from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	await loginAdmin( page );

	await page.goto( STATS_PAGE );
	await page.waitForSelector( '#wpbody-content', { timeout: 30_000 } );

	// Give charts a beat to mount + animate in.
	await page.waitForTimeout( 4000 );

	await hideAdminNotices( page );

	const viewport = page.viewportSize();
	await page.mouse.move( 0, viewport.height - 1 );
	await page.waitForTimeout( 200 );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-8.png'
	);

	await page.screenshot( { path: outputPath, fullPage: false } );
} );
