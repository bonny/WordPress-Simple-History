const { test: base, expect } = require( '@playwright/test' );
const { RequestUtils } = require( '@wordpress/e2e-test-utils-playwright' );
const path = require( 'path' );

const baseURL =
	process.env.PLAYWRIGHT_BASE_URL ||
	'http://wordpress-stable-docker-mariadb.test:8282';

// Extends the base test with a requestUtils fixture that authenticates via
// the saved admin session (tests/playwright/.auth/admin.json) so tests can
// create and clean up WordPress data through the REST API without needing a
// separate application password.
const test = base.extend( {
	requestUtils: [
		async ( {}, use ) => {
			const requestUtils = await RequestUtils.setup( {
				baseURL,
				storageStatePath: process.env.PLAYWRIGHT_STORAGE_STATE
					? path.resolve( process.env.PLAYWRIGHT_STORAGE_STATE )
					: path.join( __dirname, '.auth/admin.json' ),
			} );

			await use( requestUtils );
		},
		{ scope: 'worker' },
	],
} );

module.exports = { test, expect };
