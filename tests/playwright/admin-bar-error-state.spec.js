const { test, expect } = require( '@playwright/test' );

test.describe( 'Admin bar quick view error state', () => {
	test( 'shows a generic error, not the empty state, when the events request fails', async ( {
		page,
	} ) => {
		await page.route( '**/simple-history/v1/events*', ( route ) => {
			route.fulfill( {
				status: 503,
				contentType: 'text/html',
				body: 'Service Unavailable',
			} );
		} );

		await page.goto( '/wp-admin/' );

		// The dropdown renders on mount but stays hidden until hover.
		await page.hover( '#wp-admin-bar-simple-history' );

		await expect( page.getByText( "Couldn't load events." ) ).toBeVisible();

		await expect(
			page.getByText( 'No events in the last 7 days.' )
		).toHaveCount( 0 );
		await expect( page.getByText( 'No events found.' ) ).toHaveCount( 0 );
	} );

	test( 'shows a sign-in message when the events request fails with rest_forbidden', async ( {
		page,
	} ) => {
		await page.route( '**/simple-history/v1/events*', ( route ) => {
			route.fulfill( {
				status: 403,
				contentType: 'application/json',
				body: JSON.stringify( {
					code: 'rest_forbidden',
					message: 'Sorry, you are not allowed to do that.',
					data: { status: 403 },
				} ),
			} );
		} );

		await page.goto( '/wp-admin/' );
		await page.hover( '#wp-admin-bar-simple-history' );

		await expect(
			page.getByText( 'Session expired. Reload the page to see events.' )
		).toBeVisible();

		await expect( page.getByText( "Couldn't load events." ) ).toHaveCount(
			0
		);
		await expect(
			page.getByText( 'No events in the last 7 days.' )
		).toHaveCount( 0 );
	} );

	test( 'shows the empty state, not the error state, for a genuine empty result', async ( {
		page,
	} ) => {
		await page.route( '**/simple-history/v1/events*', ( route ) => {
			route.fulfill( {
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify( [] ),
			} );
		} );

		try {
			await page.goto( '/wp-admin/' );
			await page.hover( '#wp-admin-bar-simple-history' );

			await expect(
				page.getByText( 'No events in the last 7 days.' )
			).toBeVisible();

			await expect(
				page.getByText( "Couldn't load events." )
			).toHaveCount( 0 );
		} finally {
			await page.unroute( '**/simple-history/v1/events*' );
		}
	} );

	test( 'clears the error state after a Reload succeeds', async ( {
		page,
	} ) => {
		await page.route( '**/simple-history/v1/events*', ( route ) => {
			route.fulfill( {
				status: 503,
				contentType: 'text/html',
				body: 'Service Unavailable',
			} );
		} );

		try {
			await page.goto( '/wp-admin/' );
			await page.hover( '#wp-admin-bar-simple-history' );

			await expect(
				page.getByText( "Couldn't load events." )
			).toBeVisible();

			await page.unroute( '**/simple-history/v1/events*' );
			await page.route( '**/simple-history/v1/events*', ( route ) => {
				route.fulfill( {
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify( [] ),
				} );
			} );

			// The 503 mock also puts the dashboard widget into its error state,
			// which adds a "Reload page" button, so match the admin bar's
			// button exactly.
			await page
				.getByRole( 'button', { name: 'Reload', exact: true } )
				.click();

			await expect(
				page.getByText( "Couldn't load events." )
			).toHaveCount( 0 );
			await expect(
				page.getByText( 'No events in the last 7 days.' )
			).toBeVisible();
		} finally {
			await page.unroute( '**/simple-history/v1/events*' );
		}
	} );
} );
