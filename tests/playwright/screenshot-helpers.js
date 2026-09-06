// Shared helpers for the wp.org marketing screenshot specs. Pulled out
// of the per-spec boilerplate so a future change to the login flow,
// hover-reset selectors, or error handling lands in one place instead
// of touching 11+ files.

/**
 * Log into wp-admin as the env-configured admin user. Reads
 * WP_ADMIN_USER / WP_ADMIN_PASSWORD with sensible defaults for the
 * Playground (admin / password).
 *
 * Also wires up a pageerror handler that surfaces uncaught JS errors
 * in the test output — silent React crashes have eaten screenshots
 * before.
 *
 * @param {import('@playwright/test').Page} page
 */
async function loginAdmin( page ) {
	const adminUser = process.env.WP_ADMIN_USER || 'admin';
	const adminPassword = process.env.WP_ADMIN_PASSWORD || 'password';

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', adminUser );
	await page.fill( '#user_pass', adminPassword );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );

	page.on( 'pageerror', ( err ) =>
		console.log( 'PAGE-ERR', err.message, '\n', err.stack )
	);
}

/**
 * Park the cursor in the bottom-left corner and dispatch mouseleave +
 * mouseout on every log row so screenshots don't capture a hovered
 * row with a grey background. Idempotent — safe to call more than
 * once per page.
 *
 * Pass a custom selector for shots that frame a different surface
 * (e.g., the sidebar widget instead of the main log).
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} [selector] CSS selector for elements to un-hover.
 */
async function resetHoverState(
	page,
	selector = '.SimpleHistoryLogitem, .SimpleHistoryLogitem__senderImage, .SimpleHistoryLogitems'
) {
	const viewport = page.viewportSize();
	await page.mouse.move( 0, viewport.height - 1 );
	await page.evaluate( ( sel ) => {
		document.querySelectorAll( sel ).forEach( ( el ) => {
			el.dispatchEvent(
				new MouseEvent( 'mouseleave', { bubbles: true } )
			);
			el.dispatchEvent( new MouseEvent( 'mouseout', { bubbles: true } ) );
		} );
	}, selector );
	await page.waitForTimeout( 200 );
}

/**
 * Hide wp-admin notices inside the main content area. Used before
 * snapping any wp-admin screen so update prompts / dismissable
 * banners don't bleed into marketing imagery.
 *
 * @param {import('@playwright/test').Page} page
 */
async function hideAdminNotices( page ) {
	await page.evaluate( () => {
		document
			.querySelectorAll( '#wpbody-content .notice' )
			.forEach( ( el ) => ( el.style.display = 'none' ) );
	} );
}

module.exports = {
	loginAdmin,
	resetHoverState,
	hideAdminNotices,
};
