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

## Not done yet (later tasks in the plan)

-   Task 3: Licenses tab renders `get_license_state_description()` with a
    renew/support link.
-   Task 4: changelog entry, full phpcs/phpstan/wpunit run, `npm run addons:check`.
