const { test } = require( '@playwright/test' );
const path = require( 'path' );
const { hideAdminNotices } = require( './screenshot-helpers' );

// Captures the user card popover on the events page in two visual flavours
// (premium-active and free-only), each as a close-up PNG and a wider
// "in context" PNG that includes the surrounding event row so a reader can
// see where the popover anchors. Output files feed the embedded teaser in
// `PremiumTeaserBlurred` + marketing / blog / docs assets.
//
// Premium activation is the orchestrator's job (scripts/capture-teaser-
// screenshots.sh) — this spec just reads SH_TEASER_MODE and adapts
// selectors and output filenames accordingly. Re-run via
// `npm run screenshots:teaser-user-card`.

const MODE = process.env.SH_TEASER_MODE === 'free' ? 'free' : 'premium';

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';
const ASSETS_DIR = path.join( __dirname, '../../assets/images' );

const OUTPUT = {
	premium: {
		closeup: path.join( ASSETS_DIR, 'user-card-with-premium.png' ),
		context: path.join( ASSETS_DIR, 'user-card-with-premium-context.png' ),
	},
	free: {
		closeup: path.join( ASSETS_DIR, 'user-card-without-premium.png' ),
		context: path.join(
			ASSETS_DIR,
			'user-card-without-premium-context.png'
		),
	},
};

// Selector + locator used to confirm the popover has finished loading the
// content we care about for this mode. Premium waits for the meta line
// rendered below the stats inside the WP-user card specifically (an IP +
// browser string — only present once the REST call has populated the
// premium details). Free waits for the WP-user teaser's embedded
// screenshot (unique to that variant — non-WP initiators have a simpler
// teaser block).
//
// Both selectors are scoped under `.sh-UserCard--wp-user` because non-user
// initiator cards (wp_cli, wp, web_user, other) now also render
// `.sh-UserCard__meta--belowStats` once premium populates their "Last
// event" detail — without the wp-user scope, the screenshot pipeline
// would happily satisfy readyLocator on the wrong card variant and
// overwrite the marketing asset with the wrong popover.
function readyLocator( page ) {
	if ( MODE === 'premium' ) {
		return page
			.locator(
				'.sh-UserCard--wp-user .sh-UserCard__meta--belowStats .sh-UserCard__detail'
			)
			.first();
	}
	return page.locator(
		'.sh-UserCard--wp-user .sh-UserCard__teaserScreenshot'
	);
}

test.use( {
	viewport: { width: 1600, height: 1100 },
	deviceScaleFactor: 2,
} );

test( `capture user card popover (${ MODE })`, async ( { page } ) => {
	test.setTimeout( 120_000 );

	await page.goto( SIMPLE_HISTORY_PAGE );

	const firstTrigger = page.locator( '.sh-UserCard__trigger' ).first();
	await firstTrigger.waitFor( { state: 'visible', timeout: 30_000 } );
	await page.waitForTimeout( 1500 );

	await hideAdminNotices( page );

	// Walk through triggers and click each until we find a WP-user card.
	// The most recent events are usually the admin's just-logged-in session
	// (low/no history), so it often takes a few attempts.
	const triggers = page.locator( '.sh-UserCard__trigger' );
	const triggerCount = await triggers.count();
	let popover;
	let activeTrigger;
	let foundCard = false;

	for ( let i = 0; i < Math.min( triggerCount, 15 ); i++ ) {
		const trigger = triggers.nth( i );
		await trigger.scrollIntoViewIfNeeded();
		await trigger.click();

		popover = page.locator( '.sh-UserCard' ).first();
		try {
			await popover.waitFor( { state: 'visible', timeout: 3_000 } );
			await readyLocator( page ).waitFor( { timeout: 3_000 } );
			activeTrigger = trigger;
			foundCard = true;
			break;
		} catch ( e ) {
			// Close popover (toggle) then try next.
			await trigger.click().catch( () => {} );
			await page.waitForTimeout( 200 );
		}
	}

	if ( ! foundCard ) {
		const hint =
			MODE === 'premium'
				? 'Check premium add-on is active and at least one user in the visible log has IP/UA history.'
				: 'Check premium is DEACTIVATED so the teaser block renders.';
		throw new Error(
			`No user-card trigger yielded a usable ${ MODE } popover. ${ hint }`
		);
	}

	await page.waitForTimeout( 400 );

	// Wait for any embedded teaser image to fully load — its rendered size
	// drives the popover height and getBoundingClientRect reports wrong
	// bounds for an in-flight lazy-load image.
	if ( MODE === 'free' ) {
		await page.waitForFunction(
			() => {
				const img = document.querySelector(
					'.sh-UserCard__teaserScreenshot img'
				);
				return img && img.complete && img.naturalWidth > 0;
			},
			{ timeout: 5_000 }
		);
		await page.waitForTimeout( 200 );
	}

	// Compute the popover's *full* bounding box including any descendant
	// content that overflows the popover element itself. The cream teaser
	// block can extend past the popover's own bounds via overflow:visible,
	// so element.boundingBox() / element.screenshot() both undermeasure.
	// Walking all descendants and taking the outer rect gives the correct
	// area to clip.
	const popoverBox = await page.evaluate( () => {
		const candidates = [
			document.querySelector( '.sh-UserCard' ),
			document.querySelector( '.sh-UserCard__popover' ),
		].filter( Boolean );
		if ( candidates.length === 0 ) {
			return null;
		}
		let top = Infinity;
		let left = Infinity;
		let bottom = -Infinity;
		let right = -Infinity;
		const measure = ( el ) => {
			const r = el.getBoundingClientRect();
			if ( r.width === 0 && r.height === 0 ) {
				return;
			}
			top = Math.min( top, r.top );
			left = Math.min( left, r.left );
			bottom = Math.max( bottom, r.bottom );
			right = Math.max( right, r.right );
		};
		candidates.forEach( ( root ) => {
			measure( root );
			root.querySelectorAll( '*' ).forEach( measure );
		} );
		return { x: left, y: top, width: right - left, height: bottom - top };
	} );
	if ( ! popoverBox ) {
		throw new Error( 'Could not measure popover bounding box' );
	}

	// Tight box for the close-up: just `.sh-UserCard` itself, no popover
	// wrapper or arrow. The descendant walk above includes the WP Popover
	// arrow, which extends from the popover toward the anchored trigger
	// row — that pulls the top edge up into the log row above. For the
	// close-up we only want the card content. The context shot still uses
	// popoverBox so the arrow + cream overflow are accounted for.
	const cardBox = await page.evaluate( () => {
		const el = document.querySelector( '.sh-UserCard' );
		if ( ! el ) {
			return null;
		}
		const r = el.getBoundingClientRect();
		return { x: r.x, y: r.y, width: r.width, height: r.height };
	} );
	if ( ! cardBox ) {
		throw new Error( 'Could not measure .sh-UserCard bounding box' );
	}

	const viewport = page.viewportSize();
	const clamp = ( box ) => ( {
		x: Math.max( 0, box.x ),
		y: Math.max( 0, box.y ),
		width: Math.min( viewport.width - Math.max( 0, box.x ), box.width ),
		height: Math.min( viewport.height - Math.max( 0, box.y ), box.height ),
	} );

	// Close-up: page.screenshot with a clip computed from popoverBox,
	// shaving a strip off the right edge so the WP Popover close (×)
	// button doesn't appear in the embedded teaser preview (reads as a
	// ghost affordance — "can I close something inside the screenshot?").
	// Done IMMEDIATELY after the bounding-box measurement (no debug
	// captures, no DOM mutations in between). The WordPress Popover
	// removes itself from the DOM on focus events triggered by other
	// Playwright operations, so we keep the gap tight.
	const CLOSE_X_CROP = 40;
	await page.screenshot( {
		path: OUTPUT[ MODE ].closeup,
		clip: clamp( {
			x: cardBox.x,
			y: cardBox.y,
			width: cardBox.width - CLOSE_X_CROP,
			height: cardBox.height,
		} ),
	} );

	// Context clip: combine popover bounds with the trigger's event row so
	// the screenshot shows "popover anchored to this event in the log."
	// Walk up from the trigger to the .SimpleHistoryLogitem ancestor.
	const triggerHandle = await activeTrigger.elementHandle();
	const rowBox = await page.evaluate( ( el ) => {
		const row = el.closest( '.SimpleHistoryLogitem' );
		if ( ! row ) {
			return null;
		}
		const r = row.getBoundingClientRect();
		return { x: r.x, y: r.y, width: r.width, height: r.height };
	}, triggerHandle );

	// WP Popover programmatically focuses its close (×) button on open, and
	// the resulting outline reads as "the user is hovering the close
	// button" in the marketing asset. A click on a non-interactive area
	// inside the card moves focus off the close button without dismissing
	// the popover (only focusable elements receive focus; the avatar img
	// doesn't qualify, so activeElement falls back to <body>).
	await page.locator( '.sh-UserCard__avatar' ).first().click();
	await page.waitForTimeout( 100 );

	if ( rowBox ) {
		const PAD = 24;
		const left = Math.max( 0, Math.min( popoverBox.x, rowBox.x ) - PAD );
		const top = Math.max( 0, Math.min( popoverBox.y, rowBox.y ) - PAD );
		// Cap right edge at the popover's right edge + padding instead of
		// the row's full width. Event rows span the entire log content area
		// (~1500px @ 1600 viewport), making the context shot uselessly wide.
		// The popover is what we're showcasing — clip just past it, with
		// a little extra room on the right so neighbouring row content is
		// visible.
		const RIGHT_EXTRA = 280;
		const right = Math.min(
			viewport.width,
			popoverBox.x + popoverBox.width + PAD + RIGHT_EXTRA
		);
		const bottom = Math.min(
			viewport.height,
			Math.max(
				popoverBox.y + popoverBox.height,
				rowBox.y + rowBox.height
			) + PAD
		);

		await page.screenshot( {
			path: OUTPUT[ MODE ].context,
			clip: {
				x: left,
				y: top,
				width: right - left,
				height: bottom - top,
			},
		} );
	} else {
		// Fallback: pad the popover bounds generously if the row wasn't found.
		const PAD = 80;
		const padded = {
			x: popoverBox.x - PAD,
			y: popoverBox.y - PAD,
			width: popoverBox.width + PAD * 2,
			height: popoverBox.height + PAD * 2,
		};
		await page.screenshot( {
			path: OUTPUT[ MODE ].context,
			clip: clamp( padded ),
		} );
	}
} );
