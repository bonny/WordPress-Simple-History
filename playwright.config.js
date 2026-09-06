const { defineConfig, devices } = require( '@playwright/test' );

// Defaults to the local dev WordPress. Override via env var in CI.
const baseURL =
	process.env.PLAYWRIGHT_BASE_URL ||
	'http://wordpress-stable-docker-mariadb.test:8282';

// Where the cached admin session is written and read from. Overridable so a
// run against another install — wp-env, say — gets its own session file
// instead of overwriting the one for the dev WordPress.
const storageState =
	process.env.PLAYWRIGHT_STORAGE_STATE || 'tests/playwright/.auth/admin.json';

// Specs captured by tests/screenshot/run.sh against the fresh Playground
// instance (plus `banner`, which renders local HTML over file://).
const screenshotSpecs = [
	'playground',
	'dashboard-widget',
	'inline-diff',
	'event-details',
	'user-events',
	'plugin-install',
	'insights-widget',
	'stats-page',
	'email-settings',
	'ip-popover',
	'email-preview',
	'banner',
];
const screenshotTestMatch = new RegExp(
	`screenshot-(${ screenshotSpecs.join( '|' ) })\\.spec\\.js$`
);

// @wordpress/e2e-test-utils-playwright reads WP_BASE_URL from env at module
// load time, so set it here before any test files import the package.
process.env.WP_BASE_URL = baseURL;

module.exports = defineConfig( {
	testDir: './tests/playwright',
	fullyParallel: true,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	reporter: 'html',
	use: {
		baseURL,
		trace: 'on-first-retry',
	},
	projects: [
		{
			name: 'setup',
			testMatch: /auth\.setup\.js/,
		},
		// These two specs flip the site-wide experimental-features option
		// through the dev-tools endpoint. Run in parallel they race each
		// other, so they run as a chain of their own before `tests`.
		{
			name: 'experimental-privacy',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState,
			},
			testMatch: /privacy-data\.spec\.js$/,
			dependencies: [ 'setup' ],
		},
		{
			name: 'experimental-hide',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState,
			},
			testMatch: /hide-event-type\.spec\.js$/,
			dependencies: [ 'experimental-privacy' ],
		},
		{
			name: 'tests',
			use: {
				...devices[ 'Desktop Chrome' ],
				// Reuse admin login session across tests.
				storageState,
			},
			// Screenshot specs are not tests — they run via the dedicated
			// `screenshot` project (tests/screenshot/run.sh) against a fresh
			// Playground instance, so keep them out of the regular suite.
			// The experimental-features specs run in their own projects above.
			testIgnore: [
				/screenshot-.*\.spec\.js$/,
				/privacy-data\.spec\.js$/,
				/hide-event-type\.spec\.js$/,
			],
			dependencies: [ 'experimental-hide' ],
		},
		{
			// Teaser user-card screenshots, captured against the dev WordPress
			// via scripts/capture-teaser-screenshots.sh (logs in as sally).
			// Kept in its own project so it stays out of the regular `tests`
			// suite but still runs against the dev install, not Playground.
			name: 'teaser',
			use: {
				...devices[ 'Desktop Chrome' ],
				storageState,
			},
			testMatch: /screenshot-teaser-.*\.spec\.js$/,
			dependencies: [ 'setup' ],
		},
		{
			// Used by tests/screenshot/run.sh against a fresh WP Playground
			// instance. No setup dependency — logs in fresh per run. Matches
			// both the main log screenshot and the dashboard widget screenshot.
			name: 'screenshot',
			use: {
				...devices[ 'Desktop Chrome' ],
				baseURL:
					process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:9445',
			},
			testMatch: screenshotTestMatch,
		},
	],
} );
