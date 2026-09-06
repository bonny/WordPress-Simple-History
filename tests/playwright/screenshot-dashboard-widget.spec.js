const { test } = require( '@playwright/test' );
const path = require( 'path' );
const { loginAdmin, resetHoverState } = require( './screenshot-helpers' );

// Capture spec for the Simple History dashboard widget at /wp-admin/index.php.
// Reuses the same fresh WordPress Playground instance and curated events as
// the main screenshot — so booting + tearing down once gets both shots.
// Saves to .wordpress-org/screenshot-9.png (placed before the two email
// screenshots so the "where you see your activity" story reads:
// main log → insights → dashboard widget → email reports → email preview).
//
// Frames the shot to include surrounding dashboard chrome (admin sidebar,
// neighbouring widgets like "At a Glance" and "Activity") so a wp.org viewer
// instantly recognises "this lives on the WordPress dashboard you already
// look at every day" — the killer pitch for the widget feature.
test.use( {
	viewport: { width: 1440, height: 900 },
	deviceScaleFactor: 2,
} );

test( 'capture dashboard widget from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	await loginAdmin( page );
	page.on( 'response', ( res ) => {
		if ( res.status() >= 400 ) {
			console.log( 'BAD-RESP', res.status(), res.url() );
		}
	} );

	// /wp-admin/index.php is the dashboard.
	await page.goto( '/wp-admin/index.php' );

	// Wait for the widget container itself first (server-rendered), then for
	// the React event list inside it to finish loading.
	await page.waitForSelector( '#simple_history_dashboard_widget', {
		timeout: 60_000,
	} );
	await page.waitForSelector(
		'#simple_history_dashboard_widget .SimpleHistoryLogitems.is-loaded',
		{ timeout: 60_000 }
	);

	// Let avatars / attachment thumbs finish loading.
	await page.waitForTimeout( 2000 );

	// Hide admin notices, Welcome panel, and the "Screen Options" / "Help"
	// drawers above the widget so the screenshot doesn't include WP-core
	// chrome that's noise in marketing context.
	await page.evaluate( () => {
		document
			.querySelectorAll(
				'#wpbody-content .notice, #welcome-panel, #screen-meta, #screen-meta-links'
			)
			.forEach( ( el ) => ( el.style.display = 'none' ) );

		// Hide widgets that are visually noisy on a fresh install:
		// Site Health Status (warns about "potential issues") and WordPress
		// News (links to wp.org). Keep Quick Draft, At a Glance, and
		// Activity — those are familiar WP elements every user recognises.
		document
			.querySelectorAll( '#dashboard_site_health, #dashboard_primary' )
			.forEach( ( el ) => ( el.style.display = 'none' ) );

		// Hide any empty widget drop-zones (the "Drag boxes here"
		// placeholders) so unused dashboard columns don't pull focus away
		// from the Simple History widget.
		document
			.querySelectorAll( '.meta-box-sortables.empty-container' )
			.forEach( ( el ) => ( el.style.display = 'none' ) );

		// Reorder so Simple History widget appears at the top of the left
		// column — by default it's the 4th widget. Putting it first means
		// it's prominently in-frame without needing to scroll.
		const widget = document.getElementById(
			'simple_history_dashboard_widget'
		);
		if ( widget && widget.parentElement ) {
			widget.parentElement.insertBefore(
				widget,
				widget.parentElement.firstChild
			);
		}

		// Hide WordPress's per-widget reorder controls (Move up / Move down /
		// Toggle panel arrows) on the SH widget — admin chrome that doesn't
		// belong on a marketing shot.
		document
			.querySelectorAll(
				'#simple_history_dashboard_widget .handle-actions, #simple_history_dashboard_widget .handle-order-higher, #simple_history_dashboard_widget .handle-order-lower, #simple_history_dashboard_widget .handlediv'
			)
			.forEach( ( el ) => ( el.style.display = 'none' ) );
	} );

	await resetHoverState( page );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-9.png'
	);

	// Crop from top-left (0, 0) so all admin chrome — the top bar, admin
	// sidebar, and "Dashboard" heading — stays in frame. Then extend ~100px
	// past the widget on the right and bottom so the widget is clearly the
	// focus without forcing the viewer to take in the rest of an empty
	// dashboard. The widget itself is the same shape every WP user knows;
	// the surrounding chrome is what sells "this is on your dashboard".
	const widget = await page
		.locator( '#simple_history_dashboard_widget' )
		.boundingBox();

	if ( ! widget ) {
		throw new Error( 'Could not measure dashboard widget bounding box' );
	}

	const viewportSize = page.viewportSize();
	const pad = 100;
	const clipWidth = Math.min(
		viewportSize.width,
		widget.x + widget.width + pad
	);
	const clipHeight = Math.min(
		viewportSize.height,
		widget.y + widget.height + pad
	);

	await page.screenshot( {
		path: outputPath,
		clip: {
			x: 0,
			y: 0,
			width: clipWidth,
			height: clipHeight,
		},
	} );
} );
