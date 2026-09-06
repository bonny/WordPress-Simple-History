const { test, expect } = require( '@playwright/test' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

/**
 * Issue 275: WordPress's wp-emoji script replaces emoji characters with
 * <img> tags pointing at a remote CDN. Our reaction emoji are UI chrome and
 * must opt out via the wp-exclude-emoji class so they render as native text
 * with no external request.
 */
test.describe( 'Reaction emoji opt out of wp-emoji replacement', () => {
	// Set once a reaction has been added, so afterEach can always remove it
	// even if an assertion fails halfway through.
	let reactedEventId = null;

	test.beforeEach( async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
	} );

	test.afterEach( async ( { page } ) => {
		if ( ! reactedEventId ) {
			return;
		}

		// wp.apiFetch adds the nonce the cookie-authenticated REST call needs.
		await page.evaluate( async ( eventId ) => {
			await window.wp.apiFetch( {
				path: `/simple-history/v1/events/${ eventId }/unreact`,
				method: 'POST',
				data: { type: 'thumbsup' },
			} );
		}, reactedEventId );

		reactedEventId = null;
	} );

	test( 'picker and reaction pill emoji carry the wp-exclude-emoji class', async ( {
		page,
	} ) => {
		const firstEvent = page.locator( '.SimpleHistoryLogitem' ).first();
		const activePill = firstEvent.locator(
			'.SimpleHistoryLogitem__reactionButton--active'
		);

		// A previous run that died mid-test may have left a reaction behind.
		if ( ( await activePill.count() ) > 0 ) {
			const unreacted = page.waitForResponse( ( response ) =>
				response.url().includes( '/unreact' )
			);
			await activePill.click();
			await unreacted;
			await expect( activePill ).toHaveCount( 0 );
		}

		await firstEvent.hover();
		await firstEvent
			.getByRole( 'button', { name: 'Add reaction…' } )
			.first()
			.click();

		const picker = page.locator( '.sh-ReactionPicker' );
		await expect( picker ).toBeVisible();

		// Every free reaction button wraps its emoji in an excluded span.
		const freeEmoji = picker.locator( '.sh-ReactionPicker__emoji span' );
		await expect( freeEmoji.first() ).toHaveClass( /wp-exclude-emoji/ );

		// The premium teaser row, when shown, is excluded too.
		const premiumEmoji = picker.locator(
			'.sh-ReactionPicker__premiumEmoji'
		);
		if ( ( await premiumEmoji.count() ) > 0 ) {
			await expect( premiumEmoji.first() ).toHaveClass(
				/wp-exclude-emoji/
			);
		}

		// Add a reaction so a pill renders, then check the pill too.
		const reacted = page.waitForResponse( ( response ) =>
			response.url().includes( '/react' )
		);
		await picker.locator( '.sh-ReactionPicker__emoji' ).first().click();
		const response = await reacted;
		expect( response.status() ).toBe( 200 );
		reactedEventId = ( await response.json() ).id;

		const pillEmoji = activePill.locator(
			'.SimpleHistoryLogitem__reactionEmoji'
		);
		await expect( pillEmoji ).toBeVisible();
		await expect( pillEmoji ).toHaveClass( /wp-exclude-emoji/ );
	} );
} );
