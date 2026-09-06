const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

/**
 * End-to-end: post status changes flow through to the "Copy as JSON" payload
 * as structured details_data with new_value/prev_value, not just embedded HTML.
 */
test.describe( 'Post update — Copy as JSON exposes structured Status', () => {
	let post;
	const title = `Playwright copy-as-json ${ Date.now() }`;

	// The browser needs clipboard read/write permission for the test to
	// read what the menu item wrote.
	test.use( { permissions: [ 'clipboard-read', 'clipboard-write' ] } );

	test.beforeEach( async ( { requestUtils } ) => {
		post = await requestUtils.createPost( {
			title,
			status: 'draft',
		} );

		// Trigger a post_updated event with a status change so the resulting
		// log event has post_prev_post_status=draft / post_new_post_status=publish.
		await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
			method: 'POST',
			data: { status: 'publish' },
		} );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			path: `/wp/v2/posts/${ post.id }`,
			method: 'DELETE',
			params: { force: true },
		} );
	} );

	test( 'Copy as JSON includes structured Status change', async ( {
		page,
	} ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		// The post_updated event is the most recent for this post.
		// Filter by the unique title to avoid picking up unrelated events.
		const eventRow = page
			.locator( '.SimpleHistoryLogitem', {
				hasText: title,
			} )
			.first();

		await expect( eventRow ).toBeVisible();

		// Hover to surface the actions button (it's revealed on row hover).
		await eventRow.hover();

		// Install a copy-event capture before opening the menu.
		// The dev WP runs on plain http://, so navigator.clipboard.readText()
		// is unavailable. @wordpress/compose's useCopyToClipboard falls back
		// to document.execCommand('copy') which fires a 'copy' event whose
		// clipboardData carries the payload — we listen for that.
		await page.evaluate( () => {
			window.__capturedClipboard = null;
			document.addEventListener(
				'copy',
				( event ) => {
					window.__capturedClipboard =
						event.clipboardData?.getData( 'text/plain' ) ||
						window.getSelection()?.toString() ||
						null;
				},
				{ once: true, capture: true }
			);
		} );

		await eventRow.getByRole( 'button', { name: 'Actions…' } ).click();

		// The dropdown popover is rendered inline (popoverProps.inline=true),
		// so the menu items are queryable from the page root.
		await page.getByRole( 'menuitem', { name: 'Copy as JSON' } ).click();

		const clipboard = await page.evaluate(
			() => window.__capturedClipboard
		);

		expect(
			clipboard,
			'A copy event should have fired with a payload'
		).toBeTruthy();

		// Parse to make sure we got JSON, not HTML or markdown.
		let parsed;
		expect( () => {
			parsed = JSON.parse( clipboard );
		} ).not.toThrow( 'Clipboard payload should be valid JSON' );

		expect( parsed.details_data ).toBeDefined();

		// details_data is an array of group dicts, each with an 'items' array.
		const items = [];
		for ( const group of parsed.details_data || [] ) {
			if ( Array.isArray( group?.items ) ) {
				items.push( ...group.items );
			}
		}

		const statusItem = items.find( ( item ) => item.name === 'Status' );

		expect( statusItem ).toBeDefined();
		expect( statusItem.new_value ).toBe( 'publish' );
		expect( statusItem.prev_value ).toBe( 'draft' );
	} );
} );
