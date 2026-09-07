# Issue 316 – Core stores license status and expiry from update checks

Local issue: `316 - Core stores license status and expiry from update checks`.
Depends on local issue `315 - Update endpoint returns license status and expiry`
(deployed).

## Problem

Core stores the Lemon Squeezy activation response once per add-on
(`simple_history_plusplugin_message_<slug>` option, `inc/class-addon-plugin.php`)
and never refreshes it, so a renewed customer's key looks expired locally. The
update endpoint on simple-history.com now returns a `license` object on every
check; core needs to store and derive state from it.

Implementation plan: `docs/superpowers/plans/2026-09-06-license-status-from-update-checks.md`.
Four tasks; this branch covers Task 1 only.

## Done

-   Task 1: `AddOn_Plugin` state + tests
    -   `AddOn_Plugin::update_license_status_from_response( $license )` merges a
        decoded update-response `license` object into the stored option. Ignores
        anything that is not a well-formed license object (missing/non-string
        `checked_at`) and never creates a license option where none exists —
        a missing or malformed object means "could not check", previous state
        survives.
    -   `AddOn_Plugin::get_license_state()` derives `state`
        (`none|active|expired|disabled|invalid`), `source`
        (`none|activation|update_check`), `expires_at`, `expires_timestamp`,
        `is_lifetime`, `checked_at`, `error`, `activation_limit`,
        `activation_usage` from the stored option. Falls back to the
        activation-time expiry when no update check has run yet.
    -   `AddOn_Plugin::get_license_state_description()` renders one plain-text
        sentence for the Licenses tab ("Renews on …", "Expired on …", "Lifetime
        license.", etc.), empty string for state `none`.
    -   `AddOn_Plugin::purge_updater_cache()` (private) deletes the updater's
        cached response transient; called after `set_licence_message()` in both
        `activate_license()` and `deactivate_license()` so a renewed customer who
        re-activates isn't shown a stale cached refusal for up to an hour.
    -   Added the six `license_*` keys to `$message_defaults` and to the reset
        array in `deactivate_license()`.
    -   Tests: `tests/wpunit/AddOnPluginLicenseStateTest.php`, 13 tests covering
        no-key, failed-activation, activation-only expiry (future/past/lifetime),
        update-check refresh of a stale activation expiry, update-check reporting
        expired over stale activation data, disabled/invalid states, `inactive`
        counting as active, null/malformed responses keeping previous state,
        never creating a license for a site without one, description text per
        state, and deactivation clearing status + updater cache.
-   Task 2: updater stores license, 401 bodies accepted + cached 1h.
-   Task 3: Licenses tab shows state, verified with seeded option.
-   Task 4: changelog entries, full phpcs/phpstan/wpunit run, `npm run addons:check`.

## Task 4: backwards-compatibility checklist

-   New core, old server (no `license` key): proven by
    `test_old_server_without_license_key_changes_nothing` and
    `test_old_server_401_without_body_json_returns_false` in
    `PluginUpdaterLicenseStatusTest` — an update response or 401 body with no
    `license` object leaves the stored option untouched.
-   New core, new server, Lemon Squeezy down (`license: null`): proven by
    `test_401_with_license_stores_expired_and_returns_no_update` (a 401 with a
    real license object stores `expired`) and its counterpart
    `test_401_with_null_license_keeps_previous_state` (a 401 with `license: null`
    keeps whatever state was already stored) in `PluginUpdaterLicenseStatusTest`.
-   Old stored option without `license_*` keys: proven by every
    `seed_activated_option()`-based test in `AddOnPluginLicenseStateTest`
    (`test_activation_only_option_with_future_expiry_is_active`,
    `test_activation_only_option_with_past_expiry_is_expired`,
    `test_activation_only_option_without_expiry_is_lifetime`,
    `test_update_check_refreshes_a_stale_activation_expiry`,
    `test_update_check_reporting_expired_wins_over_activation_data`,
    `test_update_check_never_creates_a_license_for_a_site_without_one`) — a
    pre-issue-316 option that only has the activation-time keys still derives a
    correct state.
-   Old core, new server: the update endpoint's 200 keys and 401 status are
    unchanged from before issue 315 — issue 315's own tests cover the server
    side; this branch's tests only add the new `license` key/behaviour and
    don't assume anything about the response shape beyond it, so an old core
    talking to the new server still parses the response the same way it always
    did.
-   Premium untouched: `SH_ADDONS_PATH=... npm run addons:check` (specifically
    the `php:phpstan:min-core` step) shows premium calling no method beyond
    what `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION` (5.29.0) already requires —
    see Task 4 run log below.

## Task 4: command results (2026-09-07)

-   `docker compose -p wordpress-simple-history -f compose.yaml -f
compose.worktree-tests.yaml run --rm --no-deps -T php-cli vendor/bin/codecept
run wpunit` — OK, 1371 tests, 5097 assertions, 33 skipped (pre-existing,
    unrelated to this branch), exit 0. The two new test files individually:
    `AddOnPluginLicenseStateTest` OK (13 tests, 47 assertions),
    `PluginUpdaterLicenseStatusTest` OK (9 tests, 32 assertions).
-   `./vendor/bin/phpcs` — no errors or warnings.
-   `./vendor/bin/phpstan analyse --memory-limit=2G` — no errors (182 files).
-   `SH_ADDONS_PATH=/Users/bonnymacmini/Projects/Simple-History-Add-Ons npm run
addons:check` — `check:versions` fails on a pre-existing, unrelated
    mismatch in the add-ons repo (premium `Version:` header 1.15.0 vs
    `package.json` 1.14.0 in `simple-history-premium`, present on the add-ons
    repo's `main` before this session touched anything), which stops the
    `&&`-chained `php:phpstan:min-core` step from running. Ran
    `npm run addons:phpstan` (the underlying min-core PHPStan pass) directly
    instead: 23 pre-existing errors, none related to this branch's new core
    methods (`update_license_status_from_response`, `get_license_state`,
    `get_license_state_description`, `purge_updater_cache`) — no
    `method.notFound` against those names, confirming premium does not call
    them and `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION` stays at 5.29.0.
