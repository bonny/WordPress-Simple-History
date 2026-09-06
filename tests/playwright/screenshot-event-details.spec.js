const { test } = require( '@playwright/test' );
const path = require( 'path' );
const { loginAdmin, hideAdminNotices } = require( './screenshot-helpers' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Opens the event details modal/sidebar for the "About us" page-update event
// — the richest fixture event (logger, message_key, _user_id, post diff
// context). Saves to .wordpress-org/screenshot-6.png.
test.use( {
	viewport: { width: 1200, height: 1200 },
	deviceScaleFactor: 2,
} );

test( 'capture event details modal from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	await loginAdmin( page );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded', {
		timeout: 60_000,
	} );
	await page.waitForTimeout( 2000 );

	// Fetch the "About us" event id from the REST API. Cookie auth alone
	// returns 401 — needs the X-WP-Nonce header that wp.apiFetch sets
	// automatically. We use that instead of fetch().
	const eventId = await page.evaluate( async () => {
		const res = await window.wp.apiFetch( {
			path: '/simple-history/v1/events?per_page=20',
			parse: false,
		} );
		const events = await res.json();
		const list = Array.isArray( events )
			? events
			: events.items || events.events || [];
		const target = list.find(
			( e ) =>
				e.message_html &&
				e.message_html.includes( 'About us' ) &&
				e.message_html.includes( 'Updated page' )
		);
		return target ? target.id : null;
	} );

	if ( ! eventId ) {
		throw new Error( 'Could not find About us event via REST' );
	}

	// Trigger the event-permalink hash directly. The React app listens via
	// useURLFragment() and re-renders into surrounding-events mode.
	await page.evaluate( ( id ) => {
		window.location.hash = `#simple-history/event/${ id }`;
		window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
	}, eventId );

	// Give the focused view time to refetch + render.
	await page.waitForTimeout( 4000 );

	await hideAdminNotices( page );

	// Park cursor.
	const viewport = page.viewportSize();
	await page.mouse.move( 0, viewport.height - 1 );
	await page.waitForTimeout( 200 );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-6.png'
	);

	await page.screenshot( { path: outputPath, fullPage: false } );
} );
