const { test, expect } = require( '@playwright/test' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

test.describe( 'Simple History log page', () => {
	test.beforeEach( async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		// Wait for the log list to finish loading before each test.
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
	} );

	test( 'shows the log page with loaded events', async ( { page } ) => {
		await expect( page.locator( '.sh-PageHeader-title' ) ).toBeVisible();
		await expect(
			page.locator( '.SimpleHistoryLogitem__text' ).first()
		).toBeVisible();
	} );

	test( 'filter panel is hidden by default and shows on click', async ( {
		page,
	} ) => {
		// Filter labels are in the DOM but hidden inside the collapsed panel.
		await expect(
			page.locator( '.SimpleHistory__filters__filterLabel', {
				hasText: 'Log levels',
			} )
		).not.toBeVisible();

		await page.getByRole( 'button', { name: 'Filters' } ).click();

		await expect(
			page.locator( '.SimpleHistory__filters__filterLabel', {
				hasText: 'Log levels',
			} )
		).toBeVisible();
		await expect(
			page.locator( '.SimpleHistory__filters__filterLabel', {
				hasText: 'Message types',
			} )
		).toBeVisible();
		await expect(
			page.locator( '.SimpleHistory__filters__filterLabel', {
				hasText: 'Users',
			} )
		).toBeVisible();
	} );

	test( 'shows History Insights sidebar box', async ( { page } ) => {
		await expect(
			page.getByRole( 'heading', { name: 'History Insights' } )
		).toBeVisible();
	} );
} );
