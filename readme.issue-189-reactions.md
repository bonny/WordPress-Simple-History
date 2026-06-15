# issue-189 — Reactions: graduate out of experimental, on by default

Follow-up to [[189 - Evaluate experimental — Reactions]]. Decision: **graduate**
reactions out of the experimental-features gate into a dedicated, always-on-by-default
setting, and tighten the premium teaser copy.

## What changed

**New "Reactions" setting** (Settings → General): a single checkbox, **enabled by
default**, that can be turned off. The reaction button only appears on hover, so it is
unobtrusive even on single-user sites — no need to gate it on user count.

Reactions are no longer tied to "experimental features".

### Files

-   `inc/class-helpers.php` — `reactions_are_enabled()`: reads
    `simple_history_reactions_enabled` (default `'1'`) through the
    `simple_history/reactions_enabled` filter. Mirrors `experimental_features_is_enabled()`.
-   `dropins/class-reactions-dropin.php` — new dropin: registers the option
    (default on, `Helpers::sanitize_checkbox_input`) and renders the checkbox.
-   `inc/class-simple-history.php` — register `Reactions_Dropin`.
-   `inc/class-wp-rest-events-controller.php` — gate the `reactions` field + react/unreact
    endpoints on `reactions_are_enabled()` (was `experimental_features_is_enabled()`); error
    copy no longer says "experimental".
-   `inc/class-wp-rest-searchoptions-controller.php` — expose `reactions_enabled`.
-   `dropins/class-experimental-features-dropin.php` — removed reactions from the experimental list.
-   `loggers/class-simple-history-logger.php` — track `simple_history_reactions_enabled`
    (label "Reactions") so changes show a diff in the log.
-   Frontend (`EventReactions.jsx`, `EventsSettingsContext.jsx`, `EventsGui.jsx`,
    `EventsSearchFilters.jsx`) — gate the reaction UI on `reactionsEnabled`; teaser copy
    `More with Premium →` → `More ways to react →`.

## Design notes

Earlier iterations gated reactions on "2+ users who can view the log" (an `auto` mode).
Dropped as needless complexity: the hover-only button is unobtrusive, so default-on for
everyone with a simple opt-out is simpler and behaves better. This removed a `WP_User_Query`,
a per-request cache, and several cache-invalidation hooks.

## Verification

-   `npm run build` — clean. `phpcs` — clean. `phpstan` — no errors.
-   REST `search-options` on the Playground instance: `reactions_enabled: true` by default
    (option unset).

## Manual test

1. Open the instance → hover an event → discreet reaction button; the log shows pills + the
   "+" picker. Free users see "More ways to react →" at the bottom of the picker.
2. Settings → General → **Reactions**: uncheck → reactions disappear; check → they return.
3. Toggling the setting logs a "Modified settings" event showing the **Reactions** change.
