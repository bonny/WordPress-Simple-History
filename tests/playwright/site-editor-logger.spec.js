const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Requires a block theme (the Site Editor is only available for block themes).
test.describe( 'Site Editor logging', () => {
	let themeSlug;
	let headerWasAlreadyCustomized;

	test.beforeEach( async ( { requestUtils } ) => {
		const themes = await requestUtils.rest( {
			path: '/wp/v2/themes',
			params: { status: 'active' },
		} );

		const activeTheme = themes?.[ 0 ];

		test.skip(
			! activeTheme?.is_block_theme,
			'Active theme is not a block theme'
		);

		themeSlug = activeTheme.stylesheet;

		// Only the customization this test creates may be deleted afterwards.
		// Deleting one that already existed would permanently destroy the
		// site's own header, which matters on a shared dev or staging install.
		const header = await requestUtils
			.rest( {
				path: `/wp/v2/template-parts/${ themeSlug }//header`,
			} )
			.catch( () => null );

		headerWasAlreadyCustomized = header?.source === 'custom';
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( headerWasAlreadyCustomized ) {
			return;
		}

		// Reset the header template part to the theme default
		// so the test can run again from a clean state.
		await requestUtils
			.rest( {
				path: `/wp/v2/template-parts/${ themeSlug }//header`,
				method: 'DELETE',
				params: { force: true },
			} )
			.catch( () => {
				// Ignore - the template part may not have been customized.
			} );
	} );

	test( 'logs template part edit from the Site Editor', async ( {
		page,
	} ) => {
		// Open the Header template part in the Site Editor canvas.
		await page.goto(
			`/wp-admin/site-editor.php?postType=wp_template_part&postId=${ encodeURIComponent(
				`${ themeSlug }//header`
			) }&canvas=edit`
		);

		const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
		await canvas.locator( 'body' ).waitFor( { timeout: 30000 } );

		// Dismiss the Site Editor welcome guide if shown.
		const welcomeGuideButton = page
			.locator( '.components-modal__screen-overlay' )
			.getByRole( 'button', { name: /Get started|Close/ } )
			.first();

		if ( await welcomeGuideButton.isVisible().catch( () => false ) ) {
			await welcomeGuideButton.click();
		}

		// Type into the canvas to make the editor dirty.
		await canvas.locator( 'body' ).click();
		await page.keyboard.press( 'Enter' );
		await page.keyboard.type( 'Simple History Playwright edit' );

		// Save and wait for the template part REST request to finish.
		const saveResponsePromise = page.waitForResponse(
			( response ) =>
				response.url().includes( '/template-parts/' ) &&
				[ 'POST', 'PUT' ].includes( response.request().method() )
		);

		await page
			.getByRole( 'region', { name: /Editor top bar|Header/i } )
			.getByRole( 'button', { name: 'Save', exact: true } )
			.first()
			.click();

		// A multi-entity save panel may appear - confirm it.
		const panelSaveButton = page
			.locator( '.entities-saved-states__panel' )
			.getByRole( 'button', { name: 'Save', exact: true } );

		if (
			await panelSaveButton
				.waitFor( { timeout: 3000 } )
				.then( () => true )
				.catch( () => false )
		) {
			await panelSaveButton.click();
		}

		await saveResponsePromise;

		// The edit should be logged with a Site Editor specific message,
		// not a generic "Updated wp_template_part" post message.
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page
				.locator( '.SimpleHistoryLogitem__text', {
					hasText: 'Updated template part "Header"',
				} )
				.first()
		).toBeVisible();
	} );
} );
