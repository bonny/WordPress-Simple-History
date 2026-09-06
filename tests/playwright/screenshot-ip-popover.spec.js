const { test } = require( '@playwright/test' );
const path = require( 'path' );
const { loginAdmin, hideAdminNotices } = require( './screenshot-helpers' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Clicks the IP address on the failed-login row and captures the popover
// that opens. ipinfo.io can't be reached from inside the playground sandbox,
// so we intercept the fetch with mock geolocation data — same shape ipinfo
// returns for a real lookup. Saves to .wordpress-org/screenshot-5.png.
test.use( {
	viewport: { width: 1600, height: 1100 },
	deviceScaleFactor: 2,
} );

test( 'capture IP geolocation popover from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	// Mock ipinfo.io with realistic-looking data for the failed-login IP.
	// 203.0.113.42 is reserved-for-docs in real life, so we make up an
	// attribution that reads like an unfamiliar hosting provider — the kind
	// of lookup that would worry an admin reviewing a failed login.
	await page.route( /^https:\/\/ipinfo\.io\//, ( route ) => {
		route.fulfill( {
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify( {
				ip: '203.0.113.42',
				hostname: 'rdns-203-0-113-42.example.net',
				city: 'Frankfurt am Main',
				region: 'Hesse',
				country: 'DE',
				loc: '50.1109,8.6821',
				org: 'AS24940 Hetzner Online GmbH',
				postal: '60311',
				timezone: 'Europe/Berlin',
			} ),
		} );
	} );

	await loginAdmin( page );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded', {
		timeout: 60_000,
	} );
	await page.waitForTimeout( 2000 );

	await hideAdminNotices( page );

	// Click the IP address button — it lives inside the failed-login row.
	const ipButton = page
		.locator( '.SimpleHistoryLogitem', { hasText: 'Failed to login' } )
		.locator( 'button', { hasText: '203.0.113.42' } )
		.first();

	await ipButton.waitFor( { state: 'visible', timeout: 30_000 } );
	await ipButton.click();

	// Popover should open and the fetch should resolve from the route mock.
	const popover = page.locator( '.components-popover' ).first();
	await popover.waitFor( { state: 'visible', timeout: 10_000 } );

	// Wait for the geolocation data to render (looking for city which only
	// appears after the mock fetch resolves).
	const populatedPopover = page.locator( '.components-popover', {
		hasText: 'Frankfurt am Main',
	} );
	await populatedPopover.waitFor( { timeout: 10_000 } );
	await page.waitForTimeout( 600 );

	const box = await populatedPopover.boundingBox();
	if ( ! box ) {
		throw new Error( 'Could not measure popover bounding box' );
	}

	// Clip to popover + ~100px padding on each side, clamped to viewport.
	const pad = 100;
	const viewportSize = page.viewportSize();
	const left = Math.max( 0, box.x - pad );
	const top = Math.max( 0, box.y - pad );
	const right = Math.min( viewportSize.width, box.x + box.width + pad );
	const bottom = Math.min( viewportSize.height, box.y + box.height + pad );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-5.png'
	);

	await page.screenshot( {
		path: outputPath,
		clip: {
			x: left,
			y: top,
			width: right - left,
			height: bottom - top,
		},
	} );
} );
