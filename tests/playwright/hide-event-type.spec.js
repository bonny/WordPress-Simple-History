const { test, expect } = require( '@playwright/test' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

/**
 * Issue 289: hide one event type from the current list via the row actions
 * menu, see it as a removable chip, and get it back by removing the chip.
 */
test.describe( 'Hide event type from the current view', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
	} );

	test( 'hiding a type removes its events and shows a removable chip', async ( {
		page,
	} ) => {
		const firstEvent = page.locator( '.SimpleHistoryLogitem' ).first();
		const firstEventText = (
			await firstEvent.locator( '.SimpleHistoryLogitem__text' ).innerText()
		 ).trim();

		await firstEvent.hover();
		await firstEvent.getByRole( 'button', { name: 'Actions…' } ).click();
		await page
			.getByRole( 'menuitem', { name: 'Hide events of this type' } )
			.click();

		// The list reloads without that type.
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
		const chips = page.locator( '.SimpleHistory-hiddenTypes' );
		await expect( chips ).toBeVisible();
		await expect( chips ).toContainText( 'Hiding 1 event type' );
		await expect(
			page.locator( '.SimpleHistoryLogitem__text', {
				hasText: firstEventText,
			} )
		).toHaveCount( 0 );

		// Removing the chip brings the type back.
		await chips.getByRole( 'button', { name: /Show .* again/ } ).click();
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
		await expect( chips ).toHaveCount( 0 );
		await expect(
			page.locator( '.SimpleHistoryLogitem__text', {
				hasText: firstEventText,
			} )
		).not.toHaveCount( 0 );
	} );
} );
