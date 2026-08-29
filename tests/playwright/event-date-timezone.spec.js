const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

const POST_TITLE = 'Playwright timezone test post';
const DIVIDER_POST_TITLE = 'Playwright divider test post';

// A browser far from the site's timezone. Relative times are built from an
// absolute instant (`getEventDate()`), so they hold whatever zone the visitor
// is in — these tests pin that.
const BROWSER_TIMEZONE = 'Pacific/Kiritimati';

// moment's fromNow() for an event logged seconds ago: "a second ago", "a few
// seconds ago", "a minute ago", "2 minutes ago". Hours, days, or a future
// time all mean the timestamp was read in the wrong zone.
const JUST_NOW = /(a second|a few seconds|a minute|\d+ (seconds|minutes)) ago/;

/**
 * The relative-time element of the row logging our test post.
 *
 * @param {import('@playwright/test').Locator} rows Row locator to filter.
 * @return {import('@playwright/test').Locator} The date element.
 */
function whenForTestPost( rows ) {
	return rows
		.filter( { hasText: POST_TITLE } )
		.first()
		.locator( '.SimpleHistoryLogitem__when' );
}

/**
 * Create the post whose log event the assertions look for.
 *
 * @param {Object} requestUtils The requestUtils fixture.
 * @return {Promise<Object>} The created post.
 */
function createTestPost( requestUtils ) {
	return requestUtils.createPost( { title: POST_TITLE, status: 'publish' } );
}

/**
 * Delete a post created by createTestPost().
 *
 * @param {Object} requestUtils The requestUtils fixture.
 * @param {Object} post         The post to delete.
 */
function deleteTestPost( requestUtils, post ) {
	return requestUtils.rest( {
		path: `/wp/v2/posts/${ post.id }`,
		method: 'DELETE',
		params: { force: true },
	} );
}

/**
 * How far the timezone handed to `wp.date` is pushed away from the site's own,
 * in hours. Any non-zero gap reproduces the bug; five is far enough that the
 * resulting relative time is stated in hours and cannot be mistaken for "just
 * now" whatever the rounding.
 */
const WRONG_TIMEZONE_SHIFT_HOURS = 5;

/**
 * Give `wp.date` a timezone that disagrees with the site's by a known number of
 * hours.
 *
 * A relative time built from a bare site-local timestamp is read in whatever
 * zone `wp.date` was configured with, so any disagreement shifts every event by
 * the gap. The reported case is the package's UTC default surviving because
 * WordPress's inline settings script never ran, but shifting from the site's
 * actual offset reproduces the same fault on a UTC site too — which a plain
 * "reset it to UTC" cannot.
 *
 * Applied to the loaded page rather than injected up front: an init script
 * trapping `window.wp` trips over other plugins, and rewriting the response
 * costs the page its address-space context, which makes Chrome block every
 * asset on a loopback host.
 *
 * @param {import('@playwright/test').Page} page            Page to change the settings on.
 * @param {number}                          siteOffsetHours The site's own UTC offset, in hours.
 */
async function misconfigureWpDateTimezone( page, siteOffsetHours ) {
	// Shift towards zero so the result stays inside the real -12..+14 range
	// whatever the site's offset is.
	const offset =
		siteOffsetHours >= 0
			? siteOffsetHours - WRONG_TIMEZONE_SHIFT_HOURS
			: siteOffsetHours + WRONG_TIMEZONE_SHIFT_HOURS;

	await page.evaluate( ( wrongOffset ) => {
		window.wp.date.setSettings( {
			...window.wp.date.getSettings(),
			timezone: {
				offset: wrongOffset,
				offsetFormatted: String( wrongOffset ),
				// An empty string is what makes @wordpress/date build its zone
				// from the offset above rather than look one up by name.
				string: '',
				abbr: '',
			},
		} );
	}, offset );
}

/**
 * Wait out one tick of the once-a-second timer the relative times re-render
 * on, so the assertion reads a value produced after the timezone was changed
 * rather than whatever happened to render first.
 *
 * @param {import('@playwright/test').Page} page Page to wait on.
 */
async function waitForRelativeTimeToRerender( page ) {
	await page.waitForTimeout( 1500 );
}

test.describe( 'Relative event times in a browser far from the site timezone', () => {
	test.use( { timezoneId: BROWSER_TIMEZONE } );

	let post;

	test.beforeAll( async ( { requestUtils } ) => {
		const settings = await requestUtils.rest( { path: '/wp/v2/settings' } );

		// The gap between the two zones is what these tests measure. If they
		// ever match, every assertion below passes for the wrong reason.
		expect(
			settings.timezone,
			`Site timezone must differ from the forced browser timezone (${ BROWSER_TIMEZONE }) for this spec to test anything.`
		).not.toBe( BROWSER_TIMEZONE );
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		post = await createTestPost( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		// Guarded: if createTestPost failed, an unguarded delete would throw
		// and replace the real post-creation error in the report.
		if ( post ) {
			await deleteTestPost( requestUtils, post );
			post = undefined;
		}
	} );

	test( 'dashboard widget shows a just-logged event as seconds old', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/' );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		// The widget shows the relative time and nothing else, so this is the
		// whole date display the user sees.
		await expect(
			whenForTestPost( page.locator( '.SimpleHistoryLogitem' ) )
		).toHaveText( JUST_NOW );
	} );

	test( 'events page shows a just-logged event as seconds old', async ( {
		page,
	} ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		// Here the relative time sits in parentheses after the absolute date,
		// e.g. "Today 23:37 (a second ago)".
		await expect(
			whenForTestPost( page.locator( '.SimpleHistoryLogitem' ) )
		).toContainText( JUST_NOW );
	} );

	test( 'admin bar quick view shows a just-logged event as seconds old', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/' );

		// The dropdown renders on mount but stays hidden until hover.
		await page.hover( '#wp-admin-bar-simple-history' );

		await expect(
			whenForTestPost(
				page.locator( '.SimpleHistory-adminBarEventsList-item' )
			)
		).toHaveText( JUST_NOW );
	} );
} );

test.describe( "Relative event times when wp.date's timezone is wrong", () => {
	let post;
	let siteOffsetHours;

	test.beforeEach( async ( { requestUtils } ) => {
		post = await createTestPost( requestUtils );

		// Read it after creating the post: the helper needs a logged event.
		siteOffsetHours = await getSiteOffsetHours( requestUtils );
	} );

	test.afterEach( async ( { requestUtils } ) => {
		if ( post ) {
			await deleteTestPost( requestUtils, post );
			post = undefined;
		}
	} );

	test( 'dashboard widget shows a just-logged event as seconds old', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/' );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		const when = whenForTestPost( page.locator( '.SimpleHistoryLogitem' ) );
		await expect( when ).toBeVisible();

		await misconfigureWpDateTimezone( page, siteOffsetHours );
		await waitForRelativeTimeToRerender( page );

		await expect( when ).toHaveText( JUST_NOW );
	} );

	test( 'events page shows a just-logged event as seconds old', async ( {
		page,
	} ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		const when = whenForTestPost( page.locator( '.SimpleHistoryLogitem' ) );
		await expect( when ).toBeVisible();

		await misconfigureWpDateTimezone( page, siteOffsetHours );
		await waitForRelativeTimeToRerender( page );

		// The relative time sits in parentheses after the absolute date here.
		await expect( when ).toContainText( JUST_NOW );
	} );

	test( 'admin bar quick view shows a just-logged event as seconds old', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/' );
		await page.hover( '#wp-admin-bar-simple-history' );

		const when = whenForTestPost(
			page.locator( '.SimpleHistory-adminBarEventsList-item' )
		);
		await expect( when ).toBeVisible();

		await misconfigureWpDateTimezone( page, siteOffsetHours );
		await waitForRelativeTimeToRerender( page );

		await expect( when ).toHaveText( JUST_NOW );
	} );
} );

// Both zones are fixed offsets with no DST, so their offsets are constants.
const SHIFTED_TIMEZONES = [
	{ timezoneId: 'Pacific/Kiritimati', offsetHours: 14 },
	{ timezoneId: 'Pacific/Pago_Pago', offsetHours: -11 },
];

/**
 * Read the site's UTC offset from an event's own two timestamps.
 *
 * @param {Object} requestUtils The requestUtils fixture.
 * @return {Promise<number>} Offset in hours, e.g. 2 for Europe/Stockholm in summer.
 */
async function getSiteOffsetHours( requestUtils ) {
	const events = await requestUtils.rest( {
		path: '/simple-history/v1/events',
		params: { per_page: 1, _fields: 'date_local,date_gmt' },
	} );

	// A site with nothing logged yet would otherwise fail on an undefined
	// row rather than say what is missing.
	expect(
		events.length,
		'Reading the site UTC offset needs at least one logged event.'
	).toBeGreaterThan( 0 );

	// Parsing both as UTC makes their difference the site's offset. Keep it
	// fractional: rounding would misplace the half-hour and quarter-hour
	// zones (Asia/Kolkata, Asia/Kathmandu, Australia/Adelaide), and the
	// candidate below would then be picked on a bad shift.
	const local = Date.parse( events[ 0 ].date_local + 'Z' );
	const gmt = Date.parse( events[ 0 ].date_gmt + 'Z' );

	return ( local - gmt ) / 3600000;
}

test.describe( 'Event date separators', () => {
	let post;

	test.afterEach( async ( { requestUtils } ) => {
		if ( post ) {
			await deleteTestPost( requestUtils, post );
			post = undefined;
		}
	} );

	test( 'dashboard widget groups a just-logged event under today', async ( {
		browser,
		requestUtils,
	} ) => {
		// Create the post first: getSiteOffsetHours() reads a real logged
		// event, and this guarantees the log is not empty.
		post = await requestUtils.createPost( {
			title: DIVIDER_POST_TITLE,
			status: 'publish',
		} );

		const siteOffsetHours = await getSiteOffsetHours( requestUtils );

		const now = new Date();
		const nowUtcHour = now.getUTCHours() + now.getUTCMinutes() / 60;

		// getEventDateKey() compares UTC calendar days, and builds the event's
		// day by parsing the site-local timestamp as if it were browser-local.
		// That misreading only changes the *day* when it pushes the event
		// across a UTC midnight, which depends on the time of day the suite
		// runs — so pick, at runtime, the zone that guarantees it does. Both
		// candidates sit ~12h from most site zones, one east and one west, so
		// whatever the hour, one of them crosses.
		const shifted = SHIFTED_TIMEZONES.find( ( candidate ) => {
			const shiftedHour =
				nowUtcHour + ( siteOffsetHours - candidate.offsetHours );

			return shiftedHour < 0 || shiftedHour >= 24;
		} );

		expect(
			shifted,
			`No candidate timezone crosses a UTC day boundary at ${ nowUtcHour.toFixed(
				2
			) }h UTC with a site offset of ${ siteOffsetHours }h.`
		).toBeDefined();

		// The browser fixture is worker-scoped, so a context made here is not
		// torn down with the test. Close it in a finally, or a failed run (and
		// each retry) leaves its timezone override behind for later tests.
		const context = await browser.newContext( {
			baseURL: test.info().project.use.baseURL,
			storageState: test.info().project.use.storageState,
			timezoneId: shifted.timezoneId,
		} );

		try {
			await runDividerAssertion( context );
		} finally {
			await context.close();
		}
	} );
} );

/**
 * Load the dashboard in the given context and assert our event's day group.
 *
 * @param {import('@playwright/test').BrowserContext} context Browser context to use.
 */
async function runDividerAssertion( context ) {
	const page = await context.newPage();

	await page.goto( '/wp-admin/' );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

	// Only the first row of each day group carries a label, so read the
	// nearest label at or above our row rather than our row's own. Other
	// events logged while this test runs can otherwise take the label.
	const groupLabel = await page.evaluate( ( title ) => {
		const rows = Array.from(
			document.querySelectorAll( '.SimpleHistoryLogitems > li' )
		);
		const index = rows.findIndex( ( row ) =>
			row.textContent.includes( title )
		);

		if ( index === -1 ) {
			return null;
		}

		for ( let i = index; i >= 0; i-- ) {
			const label = rows[ i ].querySelector(
				'.SimpleHistoryEventSeparator__label'
			);

			if ( label ) {
				return label.textContent;
			}
		}

		return null;
	}, DIVIDER_POST_TITLE );

	// "Today" is the right answer whichever zone the grouping settles on:
	// the event and "now" are the same instant, so they share a calendar
	// day in the site's zone and in the browser's alike.
	expect( groupLabel ).toBe( 'Today' );
}
