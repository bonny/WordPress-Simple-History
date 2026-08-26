const { test, expect } = require( '@playwright/test' );
const {
	SETTINGS_GENERAL_PAGE,
	openLogAndWaitForHydration,
	isLoggerEnabled,
	toggleLogger,
} = require( './premium-helpers' );

// First Playwright test that exercises Simple History *Premium* behavior from
// the core repo. It proves the cross-plugin wiring: Premium registers its
// option keys via the `simple_history/settings/tracked_options` (and
// `changed_only_options`) filters, the core Simple_History_Logger watches those
// options, commits one "Modified settings" event on `shutdown`, and renders it
// on the log page. Case 2 specifically covers the issue-233 behavior where a
// structured (array) setting is logged as "(changed)" instead of dumping the
// raw serialized value into the log — and, because Message Control saves via a
// direct update_option() (not options.php), it also proves the shutdown-commit
// path for non-form saves.
//
// Requires both the core plugin and Simple History Premium to be active on the
// dev site. Auth/baseURL come from the `tests` Playwright project. If every
// assertion fails because fields are "missing", the cached admin session is
// likely stale — delete tests/playwright/.auth/admin.json and re-run.

const IP_RADIO_NAME = 'shp_store_full_ip_address';

// Label of the IP setting as rendered in the event's changed-items table. Used
// to pick out this test's own "Modified settings" event.
const IP_SETTING_LABEL = 'Store full IP address';
const IP_VALUE_ANON = 'store_anonymized';
const IP_VALUE_FULL = 'store_full';

// Any always-present logger works for the Message Control toggle.
const MESSAGE_CONTROL_LOGGER_SLUG = 'AlertsLogger';

// Label of the Message Control setting as rendered in the event's changed-items
// table. Used to pick out this test's own "Modified settings" event.
const MESSAGE_CONTROL_SETTING_LABEL = 'Message Control';

/**
 * Read the currently selected value of the Store-full-IP-address radio group.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @return {Promise<string|null>} Currently selected radio value.
 */
async function readIpSetting( page ) {
	await page.goto( SETTINGS_GENERAL_PAGE );
	await page.waitForSelector( `input[name="${ IP_RADIO_NAME }"]` );

	return page.evaluate( ( name ) => {
		const radios = Array.from(
			document.querySelectorAll( `input[name="${ name }"]` )
		);
		const checked = radios.find( ( radio ) => radio.checked );
		return checked ? checked.value : null;
	}, IP_RADIO_NAME );
}

/**
 * Select the given Store-full-IP-address value and save the settings form.
 *
 * @param {import('@playwright/test').Page} page  Playwright page.
 * @param {string}                          value Radio value to select.
 */
async function saveIpSetting( page, value ) {
	await page.goto( SETTINGS_GENERAL_PAGE );
	await page.waitForSelector( `input[name="${ IP_RADIO_NAME }"]` );

	await page.check( `input[name="${ IP_RADIO_NAME }"][value="${ value }"]` );
	await page.click( 'input[type=submit][name=submit]' );

	// The Settings API redirects back with settings-updated=true. Wait for
	// that URL (not for the form, which also exists in the pre-submit DOM)
	// so the save request has fully committed before the test moves on.
	await page.waitForURL( /settings-updated=true/ );
}

test.describe( 'Premium settings logging', () => {
	// Run serially: both cases produce "Modified settings" events, and Case 2
	// asserts on the most recent one, so they must not interleave.
	test.describe.configure( { mode: 'serial' } );

	// ---- Case 1: scalar Premium setting logs before/after ----
	test.describe( 'scalar setting (Store full IP address)', () => {
		let originalIpValue;

		test.beforeAll( async ( { browser } ) => {
			const page = await browser.newPage();
			originalIpValue = await readIpSetting( page );
			await page.close();
		} );

		test.afterAll( async ( { browser } ) => {
			// Restore the original selection so dev-site state and re-runs stay
			// stable.
			if ( originalIpValue ) {
				const page = await browser.newPage();
				await saveIpSetting( page, originalIpValue );
				await page.close();
			}
		} );

		test( 'changing it logs a "Modified settings" event', async ( {
			page,
		} ) => {
			// Flip to whichever value is not currently selected so the option
			// genuinely changes (no change => no event).
			const newValue =
				originalIpValue === IP_VALUE_FULL
					? IP_VALUE_ANON
					: IP_VALUE_FULL;

			await saveIpSetting( page, newValue );

			await openLogAndWaitForHydration( page );

			// Other specs write their own "Modified settings" events, so the
			// newest one is not necessarily ours when the suite runs in
			// parallel. Match on the settings row this test changed instead.
			const settingsItem = page
				.locator( '.SimpleHistoryLogitem', {
					has: page.locator(
						'.SimpleHistoryLogitem__keyValueTable tr',
						{ hasText: IP_SETTING_LABEL }
					),
				} )
				.first();
			await expect( settingsItem ).toBeVisible();

			// The change must be rendered exactly once. A premium add-on must
			// not re-render a setting that core's generic renderer already
			// handles (regression: the misc-settings module used to also add
			// these items via the SimpleHistoryLogger details filter, producing
			// duplicate rows).
			await expect(
				settingsItem.locator(
					'.SimpleHistoryLogitem__keyValueTable tr',
					{ hasText: IP_SETTING_LABEL }
				)
			).toHaveCount( 1 );
		} );
	} );

	// ---- Case 2: structured Premium setting logs as "(changed)" ----
	test.describe( 'structured setting (Message Control)', () => {
		let originalEnabled;

		test.beforeAll( async ( { browser } ) => {
			const page = await browser.newPage();
			originalEnabled = await isLoggerEnabled(
				page,
				MESSAGE_CONTROL_LOGGER_SLUG
			);
			await page.close();
		} );

		test.afterAll( async ( { browser } ) => {
			// Restore the logger to its original enabled/disabled state.
			const page = await browser.newPage();
			const currentlyEnabled = await isLoggerEnabled(
				page,
				MESSAGE_CONTROL_LOGGER_SLUG
			);

			if ( currentlyEnabled !== originalEnabled ) {
				await toggleLogger(
					page,
					MESSAGE_CONTROL_LOGGER_SLUG,
					originalEnabled ? 'enable' : 'disable'
				);
			}

			await page.close();
		} );

		test( 'toggling a logger logs "(changed)" without a serialized blob', async ( {
			page,
		} ) => {
			// Toggle to the opposite of the current state so shp_message_control
			// (an array option) actually changes.
			await toggleLogger(
				page,
				MESSAGE_CONTROL_LOGGER_SLUG,
				originalEnabled ? 'disable' : 'enable'
			);

			await openLogAndWaitForHydration( page );

			// The activity log is global and other specs write their own
			// "Modified settings" events, so the newest one is not necessarily
			// ours when the suite runs in parallel. Match on the settings row
			// this test actually changed instead.
			const modifiedSettingsItem = page
				.locator( '.SimpleHistoryLogitem', {
					has: page.locator(
						'.SimpleHistoryLogitem__keyValueTable tr',
						{ hasText: MESSAGE_CONTROL_SETTING_LABEL }
					),
				} )
				.first();
			await expect( modifiedSettingsItem ).toBeVisible();

			// Details render inline within the log item (no expand needed).
			const details = modifiedSettingsItem.locator(
				'.SimpleHistoryLogitem__details'
			);
			await expect( details ).toBeVisible();

			// Issue-233 sentinel: structured value is logged as "(changed)".
			await expect( details ).toContainText( '(changed)' );

			// And the raw serialized array must NOT be dumped into the log:
			// assert this event's detail contains no JSON object opener.
			await expect( details ).not.toContainText( '{"' );
		} );
	} );
} );
