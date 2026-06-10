# Issue 234 — View plugin info action link on plugin events

Local issue: `234 - View plugin info action link on plugin events`

## Scope (corrected during review)

The original issue pointed at the Plugin logger, but the screenshot event
("Found an update to plugin …") comes from the **Available Updates logger**.
The Plugin logger already shows "Plugin info" on install/activate/deactivate
events, so the actual gap was `plugin_update_available`.

## Changes

-   `loggers/class-available-updates-logger.php` — `get_action_links()`:
    -   New "Plugin info" thickbox link on `plugin_update_available` events
        with a known plugin slug. Order: Changelog · Plugin info · All updates.
    -   Per-plugin links (Changelog, Plugin info) now gated on `install_plugins`
        instead of the update\_\* caps — the thickbox destination
        (`plugin-install.php`) wp_dies without it, so the old gate could surface
        a link that landed on a permission-denied screen.
    -   Changelog link action type fixed from `edit` (pencil icon) to `view`
        (eye icon) per the action-links icon vocabulary.
    -   "All updates" and per-plugin links route to `network_admin_url()` on
        multisite.
-   `loggers/class-plugin-logger.php` — consistency fix: the "Plugin info" link
    on install/activate/deactivate events now uses `network_admin_url()` on
    multisite, matching its Changelog sibling.
-   `tests/wpunit/AvailableUpdatesLoggerActionLinksTest.php` — new suite, 6 tests.
-   `readme.txt` — changelog entry under Unreleased → Added.

## Verification

-   `docker compose run --rm php-cli vendor/bin/codecept run wpunit AvailableUpdatesLoggerActionLinksTest` — 6 tests OK
-   `docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginLoggerActionLinksTest` — 8 tests OK (touched file)
-   phpcs clean, phpstan clean
