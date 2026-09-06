const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

test.describe( 'Post creation logging', () => {
	let post;

	test.beforeEach( async ( { requestUtils } ) => {
		post = await requestUtils.createPost( {
			title: 'Playwright test post',
			status: 'publish',
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	} );

	test( 'logs post creation in Simple History', async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page
				.locator( '.SimpleHistoryLogitem__text', {
					hasText: 'Playwright test post',
				} )
				.first()
		).toBeVisible();
	} );
} );
