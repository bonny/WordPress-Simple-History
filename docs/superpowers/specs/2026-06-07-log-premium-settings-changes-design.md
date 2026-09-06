# Log Simple History Premium's own settings changes

**Issue:** 232 - Log Simple History Premium's own settings changes
**Project:** premium (with supporting changes in core)
**Date:** 2026-06-07
**Status:** Design approved

## Summary

Simple History Premium should log changes to its **own** settings out of the
box — an audit tool should record changes to the audit tool itself. This is the
bounded, on-mission part split out of issue 20 (generic options logger).

All Simple History settings changes — core _and_ Premium — are logged uniformly
by core's existing `Simple_History_Logger` as a `modified_settings` event,
attributed via "Using plugin Simple History". There is no separate Premium
logger and no Premium-specific "via" label: to the person reading the log,
Simple History is one product, and the core-vs-Premium packaging split is an
implementation detail they do not need surfaced.

## Goals

-   Premium's own settings changes are logged with zero configuration (enabled by
    default, no firewall of options to filter).
-   Changes are attributed to "Using plugin Simple History" (matches the decision
    recorded in issue 4: log "Via Simple History").
-   Every save path is covered: WordPress Settings API, direct `update_option()`,
    and REST writes.
-   The event details show _which_ setting changed and its old → new value with a
    human-readable label.
-   No double-logging: one save produces one event.

## Non-goals

-   A generic, configurable logger for arbitrary `wp_options` keys. That remains
    parked in issue 20 (it is a firehose needing disabled-by-default + filter UI +
    an "is on" reminder, none of which this feature needs).
-   A Premium-specific "via" label or logger. Considered and rejected as more
    confusing than helpful (two via labels for one settings experience) and
    technically expensive (the via label is static per-logger; a per-event
    override would require threading context through REST, WP-CLI, and the React
    UI).

## Background: how settings are saved today

Premium modules persist settings through three different mechanisms, which is
why a single detection seam (e.g. matching `$_POST['option_page']`) is
insufficient:

| Module                | Option keys                                                                                                                                                                                                             | Save mechanism                                                            | Option group                                                    |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------- | --------------------------------------------------------------- |
| Misc Settings         | `shp_days_to_keep_type`, `shp_days_to_keep_log`, `shp_store_full_ip_address`, `shp_google_maps_api_key`, `shp_enable_post_activity_panel`                                                                               | Settings API                                                              | core group (`simple_history_settings_group`)                    |
| Stealth Mode          | `shp_stealth_mode_enabled`, `shp_stealth_mode_email_addresses`                                                                                                                                                          | Settings API form **+ direct `update_option()`** (enable/disable methods) | core group                                                      |
| Failed Login Attempts | `sh_existing_users_failed_login_attempts`, `sh_existing_users_failed_login_attempts_count`, `sh_unknown_users_failed_login_attempts`, `sh_unknown_users_failed_login_attempts_count`, `sh_combine_consecutive_attempts` | Settings API                                                              | own group (`simple_history_failed_login_attempts_option_group`) |
| Alerts                | `simple_history_alert_destinations`, `simple_history_alert_preset_settings`, `simple_history_alert_custom_rules`, `simple_history_alert_tracking`                                                                       | Settings API **+ direct `update_option()` (REST)**                        | own group (`simple_history_alerts_option_group`)                |
| Message Control       | `shp_message_control`                                                                                                                                                                                                   | **direct `update_option()`** (custom handler)                             | none                                                            |

The "detection mechanism from issue 4" referenced by issue 232 is core's
existing `Simple_History_Logger` (`loggers/class-simple-history-logger.php`),
which today watches the core option group via `load-options.php` /
`$_POST['option_page']`, captures `updated_option`, and logs `modified_settings`
on `wp_redirect`. Because this is scoped to the core option group and to the
options.php request, it currently:

-   already (partially) logs Premium misc + stealth form saves, but renders
    nothing useful for them — the details output only knows a hardcoded set of
    core keys, so Premium keys are invisible in the event;
-   never sees own-group saves (Failed Login, Alerts) or direct `update_option()`
    saves (Message Control, Alerts REST, stealth toggles).

## Design

### 1. Tracked-options registry (filter)

Introduce a filter that returns a map of option key → human-readable label:

```php
$tracked = apply_filters( 'simple_history/settings/tracked_options', [] );
// e.g. [ 'shp_message_control' => 'Message Control settings', ... ]
```

Core seeds this map from settings registered under its own option group
(`SETTINGS_GENERAL_OPTION_GROUP`) via `$wp_registered_settings`, which
auto-includes Premium's misc and stealth keys (they register under the core
group) without Premium having to enumerate them.

Premium hooks into the filter and contributes only the keys that core cannot
auto-discover — its own-group and direct-save keys:

-   Failed Login Attempts (5 keys)
-   Message Control (`shp_message_control`)
-   Alerts (4 keys)

Each contributed key gets a friendly label for the details output.

### 2. Detection: global watcher, commit on `shutdown`

Core's `Simple_History_Logger` watches the global option hooks
`updated_option`, `added_option`, and `deleted_option`. For each fired hook,
if the option key is in the tracked-options map, the change is accumulated into
a request-scoped array as `{option}_prev` / `{option}_new` (core keys keep the
existing `simple_history_` prefix-stripping; Premium keys are stored as-is).

A single `modified_settings` event is committed on `shutdown` if any tracked
changes were accumulated. Committing on `shutdown` (rather than the current
`wp_redirect`) means the mechanism is independent of how the save happened —
options.php, a custom POST handler, or a REST request all reach `shutdown` —
and a save that touches several settings still produces exactly one event.

This unifies and replaces the existing `on_load_options_page` /
`commit_log_on_wp_redirect` flow. Because there is now a single accumulation
path keyed on the tracked-options map, there is no double-logging: a key is
recorded once regardless of how many times `update_option` fires.

The separate, already-working events on this logger (`regenerated_rss_feed_secret`,
`cleared_log`, `purged_events`, backfill, channel) are unaffected — they are
driven by their own dedicated actions, not the option watcher.

### 3. Generic details rendering

`get_log_row_details_output()` currently renders a fixed list of
`Event_Details_Item`s, one per known core key. Replace/extend this with a
generic renderer that iterates the changed `*_prev` / `*_new` pairs present in
the event context and renders each as _label: old → new_. Labels are resolved
from the tracked-options map; unknown keys fall back to the raw key name. This
makes Premium settings visible in the event details and keeps core keys
rendering as before (their labels move into core's seed of the tracked map).

## Components and responsibilities

-   **Core `Simple_History_Logger`** (`loggers/class-simple-history-logger.php`)
    -   Builds the tracked-options map (core-group auto-discovery ∪ filter).
    -   Registers the global option watchers and the `shutdown` commit.
    -   Renders generic settings-change details.
-   **Core: the `simple_history/settings/tracked_options` filter** — the seam
    between core and add-ons. Core defines and applies it; any add-on can
    contribute keys + labels.
-   **Premium** — a small piece of bootstrap (e.g. in `Extended_Settings` or a
    dedicated module) that hooks the filter and returns Premium's own-group /
    direct-save keys with labels. No logging logic lives in Premium.

## Data flow

1. An admin (or code) changes a tracked option through any mechanism.
2. WordPress fires `updated_option` / `added_option` / `deleted_option`.
3. Core's watcher checks the key against the tracked-options map; if matched,
   it accumulates `{key}_prev` / `{key}_new`.
4. On `shutdown`, if anything accumulated, core logs one `modified_settings`
   event via "Using plugin Simple History".
5. In the log UI, the generic details renderer shows each changed setting as
   _label: old → new_.

## Error handling and edge cases

-   **No-op saves** (`update_option` called with an unchanged value) do not fire
    `updated_option`, so they are naturally ignored.
-   **Serialized/array options** (e.g. `shp_message_control`,
    `simple_history_alert_custom_rules`) — the renderer must handle non-scalar
    old/new values gracefully (summarize or render readably rather than dumping
    raw serialized data). Detailed treatment is deferred to the implementation
    plan.
-   **Sensitive values** — option values are stored in event context. Watch for
    any key whose value is sensitive (e.g. API keys like
    `shp_google_maps_api_key`); decide per-key whether to log the value or mark it
    as changed without the value. Flag for the implementation plan.
-   **High-frequency option churn** — only keys in the tracked map are recorded,
    so transient/cron/housekeeping option writes are ignored by construction.

## Testing

-   Extend the existing acceptance coverage (`tests/acceptance/SimpleHistoryLoggerCest.php`)
    to confirm core settings saves still log `modified_settings` with the expected
    context after moving the commit to `shutdown`.
-   Add coverage for Premium-style saves through each mechanism: a Settings API
    own-group save (Failed Login), a direct `update_option()` save (Message
    Control), and a REST/direct save (Alerts) — each produces exactly one
    `modified_settings` event with the changed key and old → new value.
-   Verify no double-logging when a single core-settings-page save changes both a
    core key and a Premium misc key (one event, both keys present).

## Open questions for the implementation plan

-   Exact rendering of array/serialized option values in the details output.
-   Per-key decision on logging values vs. marking-changed for sensitive keys.
-   Whether core's tracked-map seed should auto-discover from
    `$wp_registered_settings` at `admin_init`/`shutdown` timing, or use an
    explicit core key list (auto-discovery is preferred for maintainability;
    confirm registration timing is reliable for the option groups involved).

## References

-   Issue 232 - Log Simple History Premium's own settings changes
-   Issue 20 - Add options logger (generic logger, parked)
-   Issue 4 - Add logger for Premium settings (archived; "Via Simple History"
    decision)
-   `loggers/class-simple-history-logger.php` (core logger to extend)
