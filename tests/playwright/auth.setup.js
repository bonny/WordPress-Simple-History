const { test: setup } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

const adminUser = process.env.WP_ADMIN_USER || 'claude';
const adminPassword = process.env.WP_ADMIN_PASSWORD || 'claude';
const storagePath = process.env.PLAYWRIGHT_STORAGE_STATE
	? path.resolve( process.env.PLAYWRIGHT_STORAGE_STATE )
	: path.join( __dirname, '.auth/admin.json' );

// Logs in once and saves the authenticated browser state so all tests can
// reuse it without logging in again on each run.
setup( 'authenticate as admin', async ( { page } ) => {
	fs.mkdirSync( path.dirname( storagePath ), { recursive: true } );

	// Skip login if storage state already exists — re-running creates noise
	// in the activity log. Delete .auth/admin.json to force a fresh login.
	if ( fs.existsSync( storagePath ) ) {
		setup.skip( true, 'Re-using cached admin session' );
		return;
	}

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', adminUser );
	await page.fill( '#user_pass', adminPassword );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin|wp-login/ );

	// WordPress may show a "confirm admin email" interstitial — skip it.
	if ( page.url().includes( 'action=confirm_admin_email' ) ) {
		await page.click(
			'#confirm_admin_email_form [name="remind_me_later"]'
		);
		await page.waitForURL( '**/wp-admin/**' );
	}

	// A wrong password does not throw here — WordPress re-renders the login
	// form and Playwright happily saves a session with no auth cookie in it,
	// after which every test fails somewhere else entirely. Check for the
	// cookie and say so here instead.
	//
	// The dev password is no longer the word `claude` (Chrome's breach check
	// raised a bubble on every login), so this fires when
	// WP_ADMIN_PASSWORD is not exported. See CLAUDE.local.md.
	const state = await page.context().storageState( { path: storagePath } );

	const isLoggedIn = state.cookies.some( ( cookie ) =>
		cookie.name.startsWith( 'wordpress_logged_in_' )
	);

	if ( ! isLoggedIn ) {
		throw new Error(
			`Logged in as "${ adminUser }" but no wordpress_logged_in cookie came back. ` +
				'Set WP_ADMIN_PASSWORD to the password in CLAUDE.local.md and try again.'
		);
	}
} );
