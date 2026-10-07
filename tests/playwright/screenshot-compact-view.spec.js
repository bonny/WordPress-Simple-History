const { test } = require( '@playwright/test' );
const path = require( 'path' );
const {
	loginAdmin,
	hideAdminNotices,
	resetHoverState,
} = require( './screenshot-helpers' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Compact event log view, for the release post. The cookie makes
// tests/screenshot/silence-mu-plugin.php turn on experimental features and
// the compact view for this browser only.
test.use( {
	viewport: { width: 1400, height: 1400 },
	deviceScaleFactor: 2,
} );

test( 'capture compact view screenshot from playground', async ( {
	page,
	context,
} ) => {
	test.setTimeout( 120_000 );

	const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:9445';

	await context.addCookies( [
		{ name: 'sh_screenshot_compact', value: '1', url: baseURL },
	] );

	await loginAdmin( page );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded', {
		timeout: 60_000,
	} );

	// Let avatars finish loading.
	await page.waitForTimeout( 2000 );

	await hideAdminNotices( page );

	// Keep four of today's events so the "Yesterday" group fits in the
	// frame too. The removed rows are the ones other screenshots need.
	await page.evaluate( ( hide ) => {
		document.querySelectorAll( '.SimpleHistoryLogitem' ).forEach( ( row ) => {
			if ( hide.some( ( text ) => row.textContent.includes( text ) ) ) {
				row.remove();
			}
		} );
	}, [
		'Hello Dolly',
		'Activated plugin "Yoast SEO"',
		'Installed plugin "Yoast SEO"',
		'deedee',
		'deploy-bot',
		'Team photo',
	] );

	await resetHoverState( page );

	// Frame the filter bar and the event list, without the admin menu and
	// the sidebar.
	const clip = await page.evaluate( () => {
		const search = document.querySelector(
			'input[placeholder="Search events"]'
		);
		const list = document.querySelector( '.SimpleHistoryLogitems' );
		const rows = [ ...document.querySelectorAll( '.SimpleHistoryLogitem' ) ];

		// End the frame after yesterday's last event (Mike's login).
		const lastRow = rows.find(
			( row ) =>
				row.textContent.includes( 'Mike Patel' ) &&
				row.textContent.includes( 'Logged in' )
		);

		const searchBox = search.getBoundingClientRect();
		const listBox = list.getBoundingClientRect();
		const lastRowBox = lastRow.getBoundingClientRect();
		const padding = 16;
		const top = searchBox.top - padding - 4;

		return {
			x: listBox.left - padding,
			y: top + window.scrollY,
			width: listBox.width + padding * 2,
			height: lastRowBox.bottom + 1 - top,
		};
	} );

	const outputPath =
		process.env.COMPACT_VIEW_SCREENSHOT_PATH ||
		path.join( __dirname, '../../tests/_output/compact-view.png' );

	await page.screenshot( { path: outputPath, clip, fullPage: true } );
} );
