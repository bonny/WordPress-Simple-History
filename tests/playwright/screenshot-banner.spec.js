const { test } = require( '@playwright/test' );
const path = require( 'path' );

// Renders tests/screenshot/banner-mockup.html — a self-contained
// 1544×500 HTML banner with cream/beige text panel on the left and a
// curated Simple History log mockup on the right (4-event marketing mix
// per the wp-org-screenshots skill). Captured by Playwright into
// .wordpress-org/banner-1544x500.png. run.sh then downscales it to
// banner-772x250.png with magick.
//
// Standalone from the Playground pipeline — no WP boot needed, just
// loads the local HTML over file:// and waits for Google Fonts to
// settle before snapping.
test.use( {
	viewport: { width: 1544, height: 500 },
	deviceScaleFactor: 1,
} );

test( 'capture wp.org banner from local HTML mockup', async ( { page } ) => {
	test.setTimeout( 60_000 );

	const htmlPath = path.join( __dirname, '../screenshot/banner-mockup.html' );

	await page.goto( 'file://' + htmlPath );

	// Wait for Google Fonts (Crimson Pro, Inter) to be parsed and ready
	// before screenshotting — otherwise we get a brief FOUT/FOIT.
	await page.waitForFunction(
		() => document.fonts && document.fonts.status === 'loaded',
		{ timeout: 20_000 }
	);
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 300 );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/banner-1544x500.png'
	);

	await page.screenshot( {
		path: outputPath,
		clip: { x: 0, y: 0, width: 1544, height: 500 },
	} );
} );
