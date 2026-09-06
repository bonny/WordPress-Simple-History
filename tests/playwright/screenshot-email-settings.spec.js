const { test } = require( '@playwright/test' );
const path = require( 'path' );
const { loginAdmin, hideAdminNotices } = require( './screenshot-helpers' );

const EMAIL_REPORTS_SETTINGS =
	'/wp-admin/admin.php?page=simple_history_settings_page' +
	'&selected-tab=general_settings_subtab_general' +
	'&selected-sub-tab=general_settings_subtab_email_reports';

// Captures the Email Reports settings tab — where users enable weekly email
// summaries, pick recipients, and preview the report. Saves to
// .wordpress-org/screenshot-10.png (adjacent to screenshot-11.png which shows
// the actual email preview, so the email story reads as a pair).
test.use( {
	viewport: { width: 950, height: 950 },
	deviceScaleFactor: 2,
} );

test( 'capture email reports settings from playground', async ( { page } ) => {
	test.setTimeout( 120_000 );

	await loginAdmin( page );

	await page.goto( EMAIL_REPORTS_SETTINGS );
	await page.waitForSelector( '#wpbody-content', { timeout: 30_000 } );
	await page.waitForTimeout( 2000 );

	await hideAdminNotices( page );

	// Park cursor off-screen so the settings page doesn't capture a hover.
	await page.mouse.move( 0, page.viewportSize().height - 1 );
	await page.waitForTimeout( 200 );

	const outputPath = path.join(
		__dirname,
		'../../.wordpress-org/screenshot-10.png'
	);

	await page.screenshot( { path: outputPath, fullPage: false } );
} );
