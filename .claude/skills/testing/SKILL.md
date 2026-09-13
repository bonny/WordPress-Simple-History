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

| Location                                    | What's there                                                                                                                                                                                           |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `tests/wpunit/premium/`                     | 11 test files, ~229 tests. Alerts (evaluator/logger/module), custom rules, destination senders, formatters, extended settings, WP-CLI alerts command, both REST controllers, core-vs-premium behaviour |
| `tests/functional/premium/`                 | `AlertsCliCest.php`                                                                                                                                                                                    |
| `tests/playwright/`                         | `premium-settings-logging.spec.js`, `license-reminder.spec.js`, plus `premium-helpers.js`                                                                                                              |
| `tests/_support/Helper/PremiumTestCase.php` | Base class — call `$this->activate_premium()`; it skips (not fails) when Premium isn't installed                                                                                                       |

Run them:

```bash
docker compose run --rm php-cli vendor/bin/codecept run wpunit premium   # ~15s
```

### How Premium gets loaded

Premium is mounted into the test WordPress at `tests/plugins/simple-history-premium`,
a **symlink** to the local add-ons checkout, wired up in `compose.yaml`. The symlink is
**not tracked in git** — on a fresh machine it's absent and every Premium test silently
_skips_. A green run therefore does not prove Premium passed; check the skip count.
Create the link with `npm run pair -- link` in the add-ons repo (it is what CI runs too).

It is deliberately not in `wpunit.suite.yml`'s `plugins` list — tests activate it
on demand via `activate_premium()`.

### Where to put a new Premium test

-   **PHP logic (loggers, senders, formatters, REST, WP-CLI, settings) → wpunit in `tests/wpunit/premium/`.** This is the default. Extend `Helper\PremiumTestCase`.
-   **Browser-visible behaviour → Playwright in `tests/playwright/`.** The dev WordPress runs core + Premium together. Use this only when a human would need a browser to check it — wpunit runs in seconds, Playwright doesn't.
-   **Cross-repo changes** (a core hook/filter + a Premium consumer): unit-test the mechanism on the core side, add the Premium-side test under `tests/wpunit/premium/`, and run `phpcs`/`phpstan` in **both** repos.

### What the add-ons repo does and doesn't have

The add-ons repo has **no test runner of its own** — no Codeception config, no `tests/`
dir. Its `npm run test:wpunit` runs `codecept run wpunit premium` through **core's**
Docker stack (`SH_CORE_PATH`, default `../WordPress-Simple-History`). Its other local
gates are `phpcs` and `phpstan` (`npm run addons:lint` / `addons:phpstan` from core).
**Don't add a Codeception stack there** — add the test to `tests/wpunit/premium/` here
instead.

### What runs in CI

-   **Core `.github/workflows/test.yml`** — wpunit, functional and acceptance, one job
    each, on every push, via the same `docker compose run --rm php-cli vendor/bin/codecept
    run <suite>` you run locally. Shared setup (`composer install`, `npm run build`,
    `scripts/install-test-plugins.sh`) is the composite action
    `.github/actions/setup-tests`. Premium is absent there, so the ~255 premium tests show
    up as _skipped_, not passed. A failed job uploads `tests/_output` as an artifact —
    for acceptance that is the screenshot and page HTML of each failure.
-   **Add-ons `.github/workflows/test.yml`** — checks out public core (same branch name
    if it exists, else `main`), runs `pair.sh link`, then `codecept run wpunit premium`.
    This is the only place premium tests run automatically.

**The database fixture is generated, not committed.** functional and acceptance import
`tests/_data/dump.sql` before every test. `scripts/build-test-fixture.sh` builds it from
the WordPress in the container (`wp core install`, empty site, only Simple History
active, backfill marked done, Simple History tables truncated) and downloads the plugin
and theme zips the acceptance tests upload. CI runs it every time; locally, rerun it
after bumping `WORDPRESS_VERSION` — that is what used to make the dump drift and send
every admin request to `upgrade.php`. It resets `wp_test_site` and empties
`data/wp-uploads`, and backs up the previous dump first.

A fresh environment also needs the `tests_db` database for wpunit;
`docker/db-init/tests-db.sql` creates it when the db container first starts on an empty
data directory.

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
-   **Two specs must not run in parallel with the rest.** `privacy-data.spec.js` and `hide-event-type.spec.js` flip the site-wide experimental-features option through the dev-tools endpoint, so `playwright.config.js` runs them as their own chained projects before `tests`. Add any new spec that toggles a site-wide option to that chain, not to `tests`.
-   **The dashboard widget shows only five events.** A spec that reads a row from the widget must not run its tests in parallel with each other: `event-date-timezone.spec.js` sets `test.describe.configure( { mode: 'default' } )` because every test there creates and deletes a post, and one test's clean-up pushed another's row out of the widget.

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
-   **Environment:** `.env.testing` in the repo root (params for the suite ymls)
-   All PHP tests run inside Docker via `docker compose run --rm php-cli`
-   `wp` is not on the php-cli image's PATH. Use `vendor/bin/wp --allow-root --path=/wordpress/`.

### When functional or acceptance tests fail, rebuild the fixture first

The functional and acceptance suites load `tests/_data/dump.sql` into the test
database before every test. **That file is gitignored** and is generated by
`scripts/build-test-fixture.sh` from the WordPress in the container. Pär's rule
is that a version does not ship with failing tests, so if a suite that passed
on the last release fails on a different machine, rebuild the fixture before
suspecting the plugin.

Symptoms that meant a stale hand-made dump before the script existed
(2026-09-03, 35 of 50 functional tests "failed"), kept here so they are
recognised if a dump is ever edited by hand again:

-   **Every admin-page test fails with "Form field … not found"** and the saved
    `tests/_output/*.fail.html` pages are titled **"WordPress › Update"**: the
    dump's `db_version` is older than the test container's WordPress.
-   **Wrong fixture values**: stray events (`WPCliCest` expects event id 1 in
    the ten-row `list` output), a tagline, a theme the container does not ship
    (`SimpleThemeLoggerCest` asserts "from Twenty Twenty-Five").
-   **Tests that land on the dashboard or read the previous event.** A
    never-visited install does its one-time work on the first admin request of
    every test, which widens two races the helpers now absorb: `loginAsAdmin()`
    waits for the dashboard, and `seeLogMessage()`/`seeLogContext()`/
    `seeLogInitiator()` retry for up to 5s until the newest row matches.

To tell a fixture problem from a real regression, run the failing Cests against
the previous release tag: `git checkout <tag>`, run them, `git checkout -`.
The test site serves the working directory, so nothing else changes.

### `npm test` stops at the first failing suite

It chains `wpunit && functional && acceptance`. A functional failure means the
acceptance suite never ran. Look for the last suite name in the output before
calling a run green.

### Acceptance suite: it is green, keep it that way

As of 2026-09-12 all 50 acceptance tests pass, twice in a row, on a fixture
built by `scripts/build-test-fixture.sh` with the plugins pinned by
`scripts/install-test-plugins.sh`. Every "known failure" listed here before
had an environmental cause: `PluginDuplicatePostLoggerCest` needed
Duplicate Post 4.6 (4.5 never fires `duplicate_post_after_duplicated`),
`SimplePluginLoggerCest` needed Akismet 5.5 (the local directory was empty),
`SimpleMediaLoggerCest` and `SimpleOptionsLoggerCest` hard-coded post ids and
now read them back, and the rest were the fixture races above. One genuine
flake remains: ChromeDriver's "Node with given id does not belong to the
document" during `waitForText` right after a form submit, roughly once per
fifty runs; CI reruns failed tests once (`codecept run acceptance -g failed`).
Treat any other failure as real.

`tests/plugins/*` (Redirection, Akismet, Jetpack, the premium symlink, …) is
gitignored and bind-mounted into the test site, so third-party plugin
_versions_ are machine-local too. A Redirection test that times out on
"Start Setup" with "Problem starting Redirection" in the saved page means the
local copy is broken, not the logger. Replace the directory with the release
zip from wordpress.org and recreate the container
(`docker compose up -d --force-recreate wordpress`) so the mount follows.

Redirection is **5.10.0** on this machine as of 2026-09-05 (was 5.3.2), and
`scripts/install-test-plugins.sh` pins the same version for CI. Bump
was forced by issue 308: Redirection 5.10.0 moved its REST callbacks from
legacy classes (`Redirection_Api_Redirect`) into namespaced ones
(`Redirection\Api\Route\Redirect`), so `Plugin_Redirection_Logger` needed to
match both spellings — a fixture pinned at 5.3.2 could never have caught that.
Both `PluginRedirectionLoggerCest` tests pass against 5.10.0. Its UI also
relabelled the redirect submit button from "Add Redirect" to "Add redirect"
and reused that same text on a page-title toggle button and a form heading,
so `testRedirects` now targets the submit button by CSS
(`.add-new .table-actions button[type=submit]`) instead of by text.

## Migrating old acceptance tests to Playwright

Don't migrate proactively. When you're already working on a feature that has a Codeception acceptance test (`tests/acceptance/*Cest.php`), migrate it to Playwright at that point. Leave the rest as-is.
