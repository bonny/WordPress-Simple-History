---
name: testing
description: Guidance for writing and running tests in Simple History, including the Premium add-on. Covers which framework to use, how to run existing tests, how to create new ones (codegen recording workflow), and how to test Premium (whose PHP tests live in this repo, not the add-ons repo).
allowed-tools: Read, Bash, Edit, Write
---

# Testing in Simple History

## Which framework to use

| What you're testing                                | Framework                    | Why                                              |
| -------------------------------------------------- | ---------------------------- | ------------------------------------------------ |
| Browser UI, admin pages, visual behaviour          | **Playwright**               | Fast, visible, modern — new default for UI tests |
| PHP logic, WordPress integration, database queries | **Codeception / WPUnit**     | Full WordPress environment loaded in PHP         |
| HTTP-level WordPress behaviour                     | **Codeception / Functional** | No browser needed                                |

**Rule of thumb:** If a human would test it by clicking around in a browser, use Playwright. If it's PHP logic, use Codeception.

## Running tests

```bash
# Playwright (UI tests) — runs on host machine against the dev WordPress
npm run test:playwright          # headless, output in playwright-report/
npm run test:playwright:ui       # interactive UI mode (recommended for writing/debugging)

# Codeception (PHP tests) — runs inside Docker
npm run test:wpunit              # PHP unit + WordPress integration
npm run test:functional          # HTTP-level tests
npm run test:acceptance          # legacy browser tests (Selenium — prefer Playwright for new ones)

# Full PHP suite (Codeception only — does NOT include Playwright)
npm test
```

**Note:** `npm test` runs only the Codeception suite. To get full coverage, run both `npm run test:playwright` and `npm test` separately.

## Testing Simple History Premium

Premium **is** covered by PHP tests — but they live in **this** (core) repo, not in the
add-ons repo. One harness, in core; Premium borrows it.

| Location | What's there |
| --- | --- |
| `tests/wpunit/premium/` | 11 test files, ~229 tests. Alerts (evaluator/logger/module), custom rules, destination senders, formatters, extended settings, WP-CLI alerts command, both REST controllers, core-vs-premium behaviour |
| `tests/functional/premium/` | `AlertsCliCest.php` |
| `tests/playwright/` | `premium-settings-logging.spec.js`, `license-reminder.spec.js`, plus `premium-helpers.js` |
| `tests/_support/Helper/PremiumTestCase.php` | Base class — call `$this->activate_premium()`; it skips (not fails) when Premium isn't installed |

Run them:

```bash
docker compose run --rm php-cli vendor/bin/codecept run wpunit premium   # ~15s
```

### How Premium gets loaded

Premium is mounted into the test WordPress at `tests/plugins/simple-history-premium`,
a **symlink** to the local add-ons checkout, wired up in `compose.yaml`. The symlink is
**not tracked in git** — on a fresh machine it's absent and every Premium test silently
*skips*. A green run therefore does not prove Premium passed; check the skip count.

It is deliberately not in `wpunit.suite.yml`'s `plugins` list — tests activate it
on demand via `activate_premium()`.

### Where to put a new Premium test

-   **PHP logic (loggers, senders, formatters, REST, WP-CLI, settings) → wpunit in `tests/wpunit/premium/`.** This is the default. Extend `Helper\PremiumTestCase`.
-   **Browser-visible behaviour → Playwright in `tests/playwright/`.** The dev WordPress runs core + Premium together. Use this only when a human would need a browser to check it — wpunit runs in seconds, Playwright doesn't.
-   **Cross-repo changes** (a core hook/filter + a Premium consumer): unit-test the mechanism on the core side, add the Premium-side test under `tests/wpunit/premium/`, and run `phpcs`/`phpstan` in **both** repos.

### What the add-ons repo does and doesn't have

The add-ons repo has **no test runner** — no Codeception config, no `tests/` dir, and its
`npm test` is a stub that exits 1. Its only local gates are `phpcs` and `phpstan`
(`npm run addons:lint` / `addons:phpstan` from core). **Don't add a Codeception stack
there** — add the test to `tests/wpunit/premium/` here instead.

### Nothing runs in CI yet

There is no GitHub Actions workflow running any test suite, in either repo — core's
`.github/workflows/` has spelling, the Claude bots, and deploy, and the add-ons repo has
no workflows at all. Every suite is local-and-manual. Tracked in local issue
`293 - Run the PHP test suites in CI`; coverage gaps in `292 - Close premium test coverage gaps`.

## Playwright setup

-   **Config:** `playwright.config.js`
-   **Tests:** `tests/playwright/*.spec.js`
-   **Auth:** login cached in `tests/playwright/.auth/admin.json` (gitignored). `auth.setup.js` logs in and writes this file **only when it is absent** — an existing file is reused as-is and is **never refreshed**, even after its WordPress session expires. So a stale/old cache makes every test silently redirect to `wp-login.php`, which shows up as `waitForSelector` timeouts and fields/tabs that "don't exist". **Fix: delete `tests/playwright/.auth/admin.json` and re-run** — `auth.setup` then logs in fresh. Delete it too if credentials change or the dev site was unreachable. Tip: if a brand-new spec fails on selectors that are definitely on the page, suspect stale auth first.
-   **Target:** dev WordPress at `http://wordpress-stable-docker-mariadb.test:8282` (override with `PLAYWRIGHT_BASE_URL` env var)
-   **Admin credentials:** `claude` / `claude` (override with `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD`)
-   **HTML report:** written to `playwright-report/` after each run — open it to debug failures

### Running against wp-env instead of the dev WordPress

The dev WordPress is shared and long-lived, so tests that need to change global
site state — the timezone, say — are better run against the disposable wp-env
install:

```bash
npm run wp-env:start                 # http://localhost:8888, admin/password
npm run test:playwright:wp-env       # same suite, pointed at wp-env
npm run test:playwright:wp-env -- tests/playwright/my-feature.spec.js
```

`test:playwright:wp-env` sets `PLAYWRIGHT_BASE_URL`, `WP_ADMIN_USER`,
`WP_ADMIN_PASSWORD` and `PLAYWRIGHT_STORAGE_STATE`. The separate storage-state
path matters: without it the wp-env login would overwrite
`tests/playwright/.auth/admin.json` and every later run against the dev
WordPress would silently redirect to `wp-login.php`.

wp-env mounts this repo as the plugin, so **run `npm run build` first** — it
serves whatever is in `build/`, not the source.

### CLI shortcuts

```bash
# Record a test by clicking through the browser — outputs ready-to-paste code
npx playwright codegen http://wordpress-stable-docker-mariadb.test:8282/wp-admin/

# Run a single spec file (faster iteration than the full suite)
npx playwright test tests/playwright/my-feature.spec.js
```

See https://playwright.dev/docs/getting-started-cli for the full CLI reference.

### Creating a new test (codegen workflow)

When the user asks for help creating a new Playwright test, walk them through this flow:

**1. Record by clicking through the browser:**

```bash
npx playwright codegen --load-storage=tests/playwright/.auth/admin.json http://wordpress-stable-docker-mariadb.test:8282/wp-admin/
```

`--load-storage` reuses the cached admin session, so codegen lands straight in wp-admin without making the user log in again. Without it, the recorded test will include the login form fill — noise you'd delete anyway.

In the inspector toolbar, use the **Pick locator** (cursor icon) and assertion tools (eye / `ab` / form) to add `expect()` calls — clicking through alone produces a click log, not a test.

**2. Clean up the codegen output.** The raw spec looks like this:

```js
import { test, expect } from '@playwright/test';

test.use( { storageState: 'tests/playwright/.auth/admin.json' } ); // remove
test( 'test', async ( { page } ) => {
	// rename
	await page.goto( 'http://wordpress-stable-docker-mariadb.test:8282/...' ); // make relative
	// ...
} );
```

Apply these conventions to match the rest of the suite:

-   Use `require()` (CommonJS), not `import` — matches `log-page.spec.js`, `post-logging.spec.js`.
-   Drop `test.use({ storageState })` — the chromium project in `playwright.config.js` already sets it.
-   Use relative URLs (`/wp-admin/...`) — `baseURL` is configured.
-   Give the test a descriptive name — it shows up in reports.
-   Group related tests with `test.describe()` and share setup in `beforeEach()`.
-   **Always wait for `.SimpleHistoryLogitems.is-loaded`** before asserting on log rows — the list renders empty first, then hydrates from the REST API.

**3. Save to `tests/playwright/<feature-name>.spec.js`** — testDir picks it up automatically.

**4. Run just that file while iterating:**

```bash
npx playwright test tests/playwright/<feature-name>.spec.js
```

Or use UI mode for fast edit-and-rerun: `npm run test:playwright:ui`.

**5. Debug failures** with `npx playwright show-report` — frame-by-frame trace replay.

### Writing a new Playwright test

Basic test (no test data needed) — import from `@playwright/test`:

```js
const { test, expect } = require( '@playwright/test' );

test( 'my test', async ( { page } ) => {
	await page.goto(
		'/wp-admin/admin.php?page=simple_history_admin_menu_page'
	);
	// Always wait for the log list to finish loading before asserting.
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
	await expect(
		page.locator( '.SimpleHistoryLogitem__text' ).first()
	).toBeVisible();
} );
```

Test that needs to create WordPress data — import from `./fixtures` to get `requestUtils`:

```js
const { test, expect } = require( './fixtures' );

test.beforeEach( async ( { requestUtils } ) => {
	post = await requestUtils.createPost( {
		title: 'Test post',
		status: 'publish',
	} );
} );

test.afterEach( async ( { requestUtils } ) => {
	// Delete by ID — never use deleteAllPosts() against the live dev site (see warning below).
	await requestUtils.rest( {
		path: `/wp/v2/posts/${ post.id }`,
		method: 'DELETE',
		params: { force: true },
	} );
} );

test( 'logs post creation', async ( { page } ) => {
	await page.goto(
		'/wp-admin/admin.php?page=simple_history_admin_menu_page'
	);
	await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );
	await expect(
		page
			.locator( '.SimpleHistoryLogitem__text', { hasText: 'Test post' } )
			.first()
	).toBeVisible();
} );
```

`requestUtils` uses the saved admin session (cookie auth) — no application password needed.

### Test data and state

-   Tests run against the live dev WordPress — your existing log events and content stay untouched
-   Write assertions that are true regardless of existing data ("at least one event", not "exactly 5 events")
-   When a test needs specific data, create it in `beforeEach` via `requestUtils` and clean up in `afterEach`
-   Cleanup deletions are also logged by Simple History — that's expected and correct, just accept it

### Available `requestUtils` methods (selection)

```js
requestUtils.createPost( payload ); // returns post object with .id
requestUtils.createPage( payload ); // returns page object with .id
requestUtils.createUser( payload ); // returns user object
requestUtils.rest( { path, method, params, data } ); // arbitrary REST API call
```

> **Warning:** Do NOT use `deleteAllPosts()`, `deleteAllPages()`, or similar bulk-delete methods. Tests run against the live dev WordPress — bulk deletes will wipe real content. Always delete by ID using `requestUtils.rest()`.

## Codeception PHP tests

-   **Config:** `codeception.dist.yml`, `tests/*.suite.yml`
-   **Tests:** `tests/wpunit/`, `tests/functional/`, `tests/acceptance/`
-   **Environment:** `tests/.env.testing`
-   All PHP tests run inside Docker via `docker compose run --rm php-cli`

## Migrating old acceptance tests to Playwright

Don't migrate proactively. When you're already working on a feature that has a Codeception acceptance test (`tests/acceptance/*Cest.php`), migrate it to Playwright at that point. Leave the rest as-is.
