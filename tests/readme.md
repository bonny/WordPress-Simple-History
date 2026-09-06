# Tests

-   Tests use [wpbrowser](https://wpbrowser.wptestkit.dev/).
-   Tests are run using Docker.
-   Install required composer dependencies with `$ docker-compose run --rm php-cli composer install`.
-   Copy `dump.sql` to `tests/_data/dump.sql`.
    -   This is the starting database fixture, containing the WordPress state that the tests start from. It's a minimal, starting environment shared by all tests. The file is not included in the repo.
-   Copy `twentysixteen.2.6.zip` and `twentysixteen.2.7.zip` to `tests/_data/twentysixteen.2.6.zip` and `tests/_data/twentysixteen.2.7.zip`.
-   Manually download [Jetpack](https://wordpress.org/plugins/jetpack/) and [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) and place in `tests/plugins`. The plugins are are used to test that Simple History catches changes in those plugins.
-   Start containers required for testing:
    `$ docker compose up -d`.
    This will start **WordPress**, **MariaDB** and a **Headless Chrome** using Selenium.
-   Run _unit_, _acceptance_, and _functional_ tests using PHP 7.4:
    -   `$ docker-compose run --rm php-cli vendor/bin/codecept run wpunit`
        -   Faster tests to test things that does not require so much user input.
    -   `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance`
        -   These are tests that are performed using a Chromium browser, like it was done with users that actually visits the WP admin in a browser and does things. These test are slower but more realistic.
        -   To run a single test run for example
            `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance SimpleUserLoggerCest:logUserCreated` or to run a single suite
            `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance SimpleUserLoggerCest`
    -   `❯ docker-compose run --rm php-cli vendor/bin/codecept run functional`
    -   Run single test:
        -   `docker-compose run --rm php-cli vendor/bin/codecept run acceptance FirstCest:visitPluginPage`

## Run a single test

To run for example `UserCest:logUserProfileUpdated`:

`$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance UserCest:logUserProfileUpdated`

## Setting up the starting database fixture

The `dump.sql` file must match a specific state for tests to pass. Here's what the test WordPress install should look like:

### Required state

-   **WordPress version:** Must match the version in `compose.yaml` (currently 6.8)
-   **Site URL:** `http://wordpress` (the Docker service name)
-   **Site title:** `wp-tests`
-   **Admin user:** `admin` / `admin` / `test@example.com`
-   **Active plugins:** Only `simple-history` — all other plugins must be inactive (tests activate them as needed in `_before()`)
-   **Active theme:** The default theme shipped with the WP version (e.g., Twenty Twenty-Five for WP 6.8). Must be a theme that exists in the Docker image.
-   **Content:** Empty — no posts, pages, or uploads
-   **Simple History tables:** Empty (truncated) — no pre-existing events. Tests expect event IDs to start from 1.
-   **Auto-backfill:** Must have already run (option `simple_history_auto_backfill_status` = completed). This prevents the backfill from creating unexpected events during tests.

### Generating the dump

```sh
# 1. Start containers
docker compose up -d

# 2. Reset and install WordPress fresh
docker compose run --rm wp-cli wp db reset --yes
docker compose run --rm wp-cli wp core install \
    --url=http://wordpress \
    --title=wp-tests \
    --admin_user=admin \
    --admin_email=test@example.com \
    --admin_password=admin \
    --skip-email

# 3. Empty site (removes default post, page, etc.)
docker compose run --rm wp-cli wp site empty --yes --uploads

# 4. Activate only Simple History
docker compose run --rm wp-cli wp plugin deactivate --all
docker compose run --rm wp-cli wp plugin activate simple-history

# 5. Fix uploads directory permissions (wp site empty may recreate it as root)
docker compose exec -u root wordpress chown -R www-data:www-data /var/www/html/wp-content/uploads

# 6. Trigger the auto-backfill so it won't run during tests.
#    The backfill hooks into admin_init, so visit any admin page to trigger it.
#    Open http://localhost:9191/wp-admin/ in a browser, or use curl:
curl -s -o /dev/null -u admin:admin http://localhost:9191/wp-admin/

# 7. Clear all Simple History events created during setup (backfill, login, etc.)
docker compose run --rm wp-cli wp db query \
    "TRUNCATE TABLE wp_simple_history; TRUNCATE TABLE wp_simple_history_contexts;"

# 8. Export
docker compose run --rm wp-cli wp db export - > tests/_data/dump.sql
```

### Verifying the dump state

```sh
# Should show only simple-history as active
docker compose run --rm wp-cli wp plugin list --status=active

# Should show the default theme (e.g., twentytwentyfive)
docker compose run --rm wp-cli wp theme list --status=active

# Should show the correct WP version
docker compose run --rm wp-cli wp core version

# Should show "completed"
docker compose run --rm wp-cli wp option get simple_history_auto_backfill_status
```

## Modify installation using browser

To modify installed WordPress version using a web browser, to for example update WordPress or update plugins:

```sh
# Restore DB so can browse from localhost:9191 again, perhaps to update the fixture.
# Note: to update WP you need to temporary disable mu-plugin.php. (Is this still true?)

docker compose run --rm wp-cli db import /var/www/html/tests/_data/dump.sql
docker compose run --rm wp-cli option set siteurl http://localhost:9191
docker compose run --rm wp-cli option set home http://localhost:9191

# Go to http://localhost:9191 and make changes
# Login using http://localhost:9191/wp-login.php and use admin/admin.

# Then export sql file again:
docker compose run --rm wp-cli wp db export - > db-export-`date +"%Y-%m-%d_%H_%M"`.sql

# Replace the old dump.sql with the new one
# I.e. rename the new one to dump.sql and remove the old one.
```

## Filesystem requirements

Some tests depend on themes/plugins being present on the Docker volume filesystem (not just in the database):

-   **Twenty Sixteen theme** — Required by `SimpleMenuLoggerCest` (classic nav menus) and `SimpleThemeLoggerCest` (install/delete cycle). The `Acceptance` helper's `_beforeSuite` hook auto-installs it from `tests/_data/twentysixteen.2.6.zip` if missing. `SimpleThemeLoggerCest` re-installs it after its delete test so other tests can use it.

## Running the suite against a different WordPress version

`compose.yaml` parameterises the version — `wordpress:${WORDPRESS_VERSION:-6.8}-php${PHP_VERSION:-8.1}` — so it looks like setting the variable is enough:

```bash
# This does NOT work on its own.
WORDPRESS_VERSION=6.9 npm run test:wpunit
```

**It silently does nothing.** WordPress core lives in the named volume `wordpress:/var/www/html`, and the official image's entrypoint only populates that directory when it is empty:

```sh
if [ ! -e index.php ] && [ ! -e wp-includes/version.php ]; then
    echo >&2 "WordPress not found in $PWD - copying now..."
```

It never upgrades an existing install. So the command above starts a newer container against the old filesystem, the tests run on the old version anyway, and it _looks_ like you tested the new one. That is worse than not running it.

The volume has to be discarded first:

```bash
docker compose down -v          # drops WordPress core; the DB is a bind mount and survives
WORDPRESS_VERSION=6.9 docker compose up -d
```

Two things to know before doing this:

-   `dump.sql` is version-specific (see "Required state" above). Running a newer WordPress against a fixture generated for an older one can fail in ways unrelated to what you are testing.
-   Afterwards the volume holds the _new_ version, so a later plain `npm run test:wpunit` runs the old image against new core — the same trap in reverse. Reset with `docker compose down -v` again when you are done.

If you only need the image and the exact tag is not on disk, an already-present image can be retagged rather than pulling a gigabyte: `docker tag wordpress:6.9 wordpress:6.9-php8.1`. The `wordpress` container only populates core files; the tests themselves execute in `php-cli`, so that image's bundled PHP version does not affect the run.

Do **not** reach for `docker compose run --no-deps` to skip starting Chrome. The run container then never joins the project network and every test dies with `getaddrinfo for db failed: Name does not resolve`. Chrome is only needed for acceptance tests, but `wpunit` still has to start with the normal dependency chain.

This procedure is verified: run on 6.9.1 the abilities suite goes from 18 skipped to 24 passing, and `docker compose down -v` followed by a plain `docker compose up -d` returns the volume to 6.8.3 with the skips restored.

### Nothing runs these tests automatically

There is no CI test workflow. `.github/workflows/` contains only `claude`, `claude-code-review`, `deploy`, and `spelling` — none invokes Codeception. The suite runs only when someone runs it locally, so a green branch means "green on whoever last ran it, at whatever WordPress version their volume happens to hold".

This matters most for code gated on a newer WordPress than the default. Tests that `markTestSkipped()` below some version will skip forever without anyone noticing, which is exactly how two runtime defects in the Abilities API work reached a live site undetected — see `docs/superpowers/specs/2026-07-28-abilities-api-design.md` §10. For anything targeting an API newer than the default, verify against a real site of the target version rather than trusting a green suite.

## Upgrading WordPress version

When upgrading the test environment to a new WordPress version, watch for these patterns:

### 1. Post ID offsets in dump.sql

Fresh WP installs create different default content across versions. The `AUTO_INCREMENT` for `wp_posts` affects hardcoded IDs in tests (e.g., `attachment_id`, `new_post_id`, `page_on_front`). After regenerating the dump, check which IDs are already taken and update test assertions accordingly.

### 2. Default option values

WordPress may change default option values between versions (e.g., WP 6.8 changed `blogdescription` from `"Just another WordPress site"` to `""`). Search tests for `old_value` / `new_value` assertions and verify they match the new defaults in the dump.

### 3. Form submissions need explicit waits

Acceptance tests that submit forms and immediately query the database often fail because the page hasn't finished loading. Always add a `waitForText`, `waitForElement`, or `waitForElementVisible` after form submissions and before `seeLogMessage`/`seeLogContext` assertions. Without this, `getHistory(0)` may return stale data.

### 4. Admin UI text/button changes

WordPress often changes button text, notice text, and form element IDs between versions. Check screenshots in `tests/_output/` when tests fail at `click()` or `waitForText()` steps.

### 5. Gutenberg modals and overlays

New Gutenberg features may introduce modal overlays (e.g., the WP 6.8 "Choose a pattern" modal for new pages) that block element clicks. The mu-plugin `tests/_data/mu-plugins/inc/disable-starter-patterns.php` disables the pattern modal. Future WP versions may introduce similar modals that need similar treatment.

### 6. System events polluting assertions

WP may log additional system events (404s for missing thumbnails, `wp_global_styles` updates, theme taxonomy terms) that shift the expected event index. When `getHistory(0)` returns an unexpected event, use `grabFromDatabase()` to search for the event by message instead of relying on a fixed index.

### 7. Plugin install/update behavior changes

WP 6.8 changed plugin upload behavior — uploading an already-installed plugin now shows a "Replace" option instead of failing. Test scenarios that depend on specific WP admin behaviors should be verified against the new version.

## Troubleshooting

### `docker compose` hangs forever but plain `docker` works

Symptom: `docker compose up -d` prints `Container simple-history-database Creating` and sits there at 0% CPU indefinitely, while `docker ps`, `docker run`, and `docker compose config` all respond instantly.

This is Docker Desktop in a degraded state, not a problem with the compose file. Check for wedged helper processes first:

```sh
ps aux | grep -E "docker-credential|docker compose" | grep -v grep
```

A hung `docker-credential-desktop get` blocks every operation that consults the registry — including an ordinary `docker pull`, which then looks like an extremely slow download when it is actually not transferring anything. Check the timestamps; these can sit for days and accumulate one per attempt. Kill them, plus any stale `docker compose up` processes:

```sh
pkill -9 -f docker-credential-desktop
pkill -9 -f "docker compose"
```

If compose still hangs at `Creating` afterwards, restart Docker Desktop. Note that a half-created container left in `Created` state (`docker ps -a`) is worth removing too, though it is usually a symptom rather than the cause.

**When diagnosing this, do not pipe the command through `tail`/`head`** — those buffer, so you see nothing at all and cannot tell a stuck command from a quiet one. Run it unbuffered.

### Full red terminal / every test fails with timeouts

The Chrome or WordPress container is in a bad state. Restart and rerun:

```sh
docker compose restart
```

Only do this when the **first test** fails with a connection/timeout error — not for assertion failures.

### "Passes alone, fails in suite"

Two known causes:

1.  **Event row not yet written** — Gutenberg's `savePost()` returns before Simple History writes to the DB. The `getHistory()` method retries for missing context rows, but not for missing event rows. If you see `getHistory(0)` returning the previous event, add `$I->wait(1)` after the save.

2.  **Plugin already active** — The DB resets between tests but the browser session may retain state. Plugin `_before()` hooks must check if the plugin is already active before calling `activatePlugin()`. Use:
    ```php
    $isActive = $I->executeJS("return !!document.getElementById('deactivate-<slug>')");
    if (!$isActive) {
        $I->activatePlugin('<slug>');
    }
    ```

### React/fetch plugins don't work with waitForJqueryAjax()

Redirection and Jetpack use React/fetch for AJAX, not jQuery. `waitForJqueryAjax()` returns immediately. Use `$I->wait(1)` instead (documented in class docblock).

### Gutenberg saves: "Draft saved" text persists

The snackbar text "Draft saved" stays on screen between saves. Don't use `waitForText('Draft saved')` to detect the second save — it matches the first. Use `wp.data.dispatch('core/editor').savePost()` + `waitForElementVisible('.editor-post-saved-state.is-saved')` + `wait(1)`.

### seeLogContext returns empty array

The event row and context rows are separate DB writes. Under load, context lags behind. `getHistory()` retries up to 10 times (200ms each) for missing context. If still failing, increase `$max_attempts` in `Admin.php`.

## Update log / Changelog

Changes made to the test site and SQL-file.

-   29 mar 2026: Fix acceptance tests for WP 6.8. Added `waitForText`/`waitForElement` after form submissions (timing issue with Chrome 145). Updated hardcoded IDs and option values for WP 6.8 defaults. Added mu-plugin to disable starter patterns modal. Fixed button text changes. Skipped `testPluginInstallFail` (WP 6.8 shows "Replace" instead of failing). Enabled implicit wait (`wait: 2`). See "Upgrading WordPress version" section.
-   29 mar 2026: Update WP from 6.3 to 6.8. Update Yoast Duplicate Post to 4.6. DB version upgraded from 57155 to 60421.
-   15 aug 2025: Add Akismet to docker compose config and add plugin folder to tests/plugins. It stopped working and I can't see how it was loaded before. Also update tests to use updated Akismet plugin names
-   18 jul 2025: Update WP to 6.3.2.
-   24 aug 2024: Try to update from WP 6.1 to WP 6.6.
    -   Update wp using wp cli
    -   Getting messages during test "Upgrading db". So need to make that change and then export the db again.
    -
-   June 2023: Misc changes, updated Jetpack, added support for changed classes, added Developer Loggers, and more.
-   24 june 2022: Added Jetpack 11.0 and WP Crontrol 1.12.1.
-   18 june 2022: Updated to WordPress 6.0 and updated Akismet + languages + themes.

## Clean install of WordPress for tests

-   docker compose up
-   wp running on localhost:9191 but it thinks its on port 80 (because thats the internal port)
-   access at http://localhost:9191/wp-login.php
-   ...forgot the rest.. update this next time I need to do it 🤷‍♀️.
