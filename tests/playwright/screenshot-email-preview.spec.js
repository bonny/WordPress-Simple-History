const { test } = require( '@playwright/test' );
const path = require( 'path' );
const { loginAdmin } = require( './screenshot-helpers' );

const EMAIL_REPORTS_SETTINGS =
	'/wp-admin/admin.php?page=simple_history_settings_page' +
	'&selected-tab=general_settings_subtab_general' +
	'&selected-sub-tab=general_settings_subtab_email_reports';

// Renders the weekly email-report HTML preview from
// /wp-json/simple-history/v1/email-report/preview/html. Shows what
// subscribers see in their inbox — top loggers, top users, daily counts,
// etc. Saves to .wordpress-org/screenshot-11.png.
test.use( {
	viewport: { width: 900, height: 1600 },
	deviceScaleFactor: 2,
} );

test( 'capture email report preview from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	await loginAdmin( page );

	// The email settings page renders a "Show email preview" anchor whose
	// href already includes a fresh wp_rest nonce — grab that instead of
	// trying to mint our own.
	await page.goto( EMAIL_REPORTS_SETTINGS );
	await page.waitForSelector( '#wpbody-content', { timeout: 30_000 } );

	const previewURL = await page.evaluate( () => {
		const links = Array.from( document.querySelectorAll( 'a' ) );
		const link = links.find( ( a ) =>
			a.href.includes( '/email-report/preview/html' )
		);
		return link ? link.href : null;
	} );

	if ( ! previewURL ) {
		throw new Error( 'Could not find email preview link on settings page' );
	}

	await page.goto( previewURL );
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 1000 );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-11.png'
	);

	await page.screenshot( { path: outputPath, fullPage: true } );
} );
