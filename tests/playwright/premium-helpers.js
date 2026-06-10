/**
 * Shared helpers for Playwright tests that exercise Simple History **Premium**
 * behavior from the core repo.
 *
 * Premium has no test infrastructure of its own; its behavior is covered by
 * Playwright specs here, run against the dev WordPress where both the core
 * plugin and Premium are active. These helpers capture the non-obvious bits
 * (settings URLs, the Message Control logger-toggle flow, and locating the
 * resulting log event) so new premium specs can reuse them instead of
 * rediscovering selectors.
 *
 * Usage:
 *   const sh = require( './premium-helpers' );
 *   await sh.openLogAndWaitForHydration( page );
 */

const SIMPLE_HISTORY_LOG_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

// Premium "misc" settings (retention, IP storage, etc.) render on the core
// general settings tab.
const SETTINGS_GENERAL_PAGE =
	'/wp-admin/admin.php?page=simple_history_settings_page';

// Message Control is a sub-tab of the general settings tab. Saving here writes
// the `shp_message_control` option directly (a custom enable/disable handler,
// NOT the options.php Settings API), which is why it exercises the global
// option watcher + shutdown-commit path rather than the form-save path.
const MESSAGE_CONTROL_PAGE =
	'/wp-admin/admin.php?page=simple_history_settings_page' +
	'&selected-tab=general_settings_subtab_general' +
	'&selected-sub-tab=message-control';

/**
 * Go to the Simple History log page (fresh) and wait for the REST-hydrated list
 * to finish loading. The list renders empty first, then hydrates — asserting
 * before `.is-loaded` is the #1 cause of flaky log assertions.
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 */
async function openLogAndWaitForHydration( page ) {
	await page.goto( SIMPLE_HISTORY_LOG_PAGE );
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
}

/**
 * Locator for the most recent log item whose text matches `messageText`.
 * The log is newest-first, so `.first()` is the event a test just produced.
 *
 * @param {import('@playwright/test').Page} page        Playwright page.
 * @param {string}                          messageText Text the log row contains.
 * @return {import('@playwright/test').Locator} The matching log item locator.
 */
function latestLogItem( page, messageText ) {
	return page
		.locator( '.SimpleHistoryLogitem', {
			has: page.locator( '.SimpleHistoryLogitem__text', {
				hasText: messageText,
			} ),
		} )
		.first();
}

/**
 * On the Message Control page, return the nonced enable/disable action URL for a
 * logger row. The link carries a fresh nonce and is hover-revealed, so we read
 * its href from the DOM and navigate directly rather than clicking.
 *
 * @param {import('@playwright/test').Page} page   Playwright page.
 * @param {string}                          slug   Logger slug (e.g. 'AlertsLogger').
 * @param {'enable'|'disable'}              action Desired action.
 * @return {Promise<string|null>} Nonced action URL, or null if not present.
 */
async function getLoggerActionHref( page, slug, action ) {
	await page.goto( MESSAGE_CONTROL_PAGE );
	await page.waitForSelector( 'table.wp-list-table' );

	return page.evaluate(
		( { loggerSlug, desiredAction } ) => {
			const id =
				desiredAction === 'enable'
					? `activate-${ loggerSlug }`
					: `deactivate-${ loggerSlug }`;
			const link = document.getElementById( id );
			return link ? link.getAttribute( 'href' ) : null;
		},
		{ loggerSlug: slug, desiredAction: action }
	);
}

/**
 * Whether a logger is currently enabled in Message Control. An enabled logger
 * shows a "Disable" link; a disabled one shows "Enable".
 *
 * @param {import('@playwright/test').Page} page Playwright page.
 * @param {string}                          slug Logger slug.
 * @return {Promise<boolean>} True when enabled.
 */
async function isLoggerEnabled( page, slug ) {
	const disableHref = await getLoggerActionHref( page, slug, 'disable' );
	return disableHref !== null;
}

/**
 * Toggle a logger's enabled state in Message Control and wait for the save to
 * redirect back. Throws if the requested action link is absent (state already
 * matches).
 *
 * @param {import('@playwright/test').Page} page   Playwright page.
 * @param {string}                          slug   Logger slug.
 * @param {'enable'|'disable'}              action Action to perform.
 */
async function toggleLogger( page, slug, action ) {
	const href = await getLoggerActionHref( page, slug, action );

	if ( ! href ) {
		throw new Error(
			`No "${ action }" link found for logger "${ slug }" — its current state may already match.`
		);
	}

	await page.goto( href );
	await page.waitForSelector( 'table.wp-list-table' );
}

module.exports = {
	SIMPLE_HISTORY_LOG_PAGE,
	SETTINGS_GENERAL_PAGE,
	MESSAGE_CONTROL_PAGE,
	openLogAndWaitForHydration,
	latestLogItem,
	getLoggerActionHref,
	isLoggerEnabled,
	toggleLogger,
};
