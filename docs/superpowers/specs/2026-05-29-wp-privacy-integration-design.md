# WordPress Privacy export/erasure integration

**Status:** Design approved, ready for implementation plan
**Date:** 2026-05-29
**Issue:** Roadmap [[3 - Add privacy-GDPR settings group]], feature #4 (WP Privacy Integration)
**Plugin:** Core (free)
**Branch:** `issue-3-wp-privacy-integration`

## Summary

Simple History does not currently register with WordPress's personal-data
privacy tools. When an admin uses **Tools → Export Personal Data** or
**Tools → Erase Personal Data**, the activity log — which can hold a user's IP
addresses, user-agent strings, login, email, and user ID across many events —
is silently excluded. This is the plugin's biggest live privacy-compliance gap.

This feature registers Simple History as a personal-data **exporter** and
**eraser** so a user's activity-log data is included in WordPress's standard
export and erasure flows, and adds a new core **"Privacy & Data"** settings tab
to surface the integration (and to act as the container that premium privacy
features later extend).

Per the build→evaluate decision (see _Release & lifecycle_ below), the **exporter ships always-on** and
the **eraser ships gated behind experimental features** for one release cycle
before graduating to always-on.

## Goals

-   Include a user's Simple History events in WordPress's personal-data **export**.
-   **Anonymize** (not delete) a user's personal data in their events when an
    erasure request is processed — preserving the audit trail with PII scrubbed.
-   Add a core "Privacy & Data" settings tab with always-on info text describing
    the integration, structured so premium can register additional controls into it.
-   Follow WordPress conventions exactly; no new user-facing toggles for the
    integration itself (it is correctness, not a feature flag).

## Non-goals

-   Per-user _subject_ anonymization (events where the user is the target of
    someone else's action) — tracked separately in [[68 - Anonymize User Data in Events]].
-   Premium privacy controls (IP storage choice, user-agent toggle, login notice,
    data snapshot, bulk anonymization) — separate roadmap features #1, #2, #3, #5, #6.
-   Network-wide / multisite-table handling beyond WordPress's standard per-site
    privacy model (noted as a follow-up under _Edge cases & constraints_).

## Decisions

These were settled during brainstorming:

1. **Scope — initiator-only.** Export and erasure operate on events the user
   _performed_, matched via the standard `_user_id` context key. This is
   robustly queryable and matches the issue wording. Subject-references (events
   _about_ the user, stored under per-logger keys) are out of scope.
2. **Erasure — full PII scrub, scrub-not-delete.** All identifying context keys
   are removed/masked; event rows are retained as an anonymized audit record.
   (Field list under _The eraser_.)
3. **Footprint — new core "Privacy & Data" settings tab** holding the Compliance
   info text, built as the shared container for later premium subsections.
4. **Rollout — split (build→evaluate).** Exporter always-on; eraser gated behind
   `Helpers::experimental_features_is_enabled()` for one release cycle.
5. **Architecture — two dedicated services**, leaving the existing
   `Privacy_Logger` untouched.

## Architecture

Two new service classes following existing conventions; one existing class left
untouched.

| File                                                           | Role                                                                                                                                                               |
| -------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `inc/services/class-privacy-data-handler.php` (new `Service`)  | Registers the exporter (always) and eraser (experimental-gated) callbacks. Owns the shared "fetch this user's initiated events" query and the scrub routine.       |
| `inc/services/class-privacy-settings-page.php` (new `Service`) | Registers the "Privacy & Data" `Menu_Page` tab under the General-settings parent; renders the Compliance info text. Modeled on `class-channels-settings-page.php`. |
| `loggers/class-privacy-logger.php` _(existing)_                | **Untouched.** It logs WordPress privacy _events_; being a registered data handler is a separate responsibility.                                                   |

Both new services are registered alongside the other settings/services in the
plugin bootstrap (the same place `Channels_Settings_Page` is wired up).

### Registration (in `Privacy_Data_Handler::loaded()`)

```php
// Exporter — always on. Pure read; zero behavioral risk.
add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );

// Eraser — gated behind experimental features for one release cycle.
// When off, WordPress's erasure simply skips Simple History (pre-feature
// status quo) — there is no half-built behavior.
if ( Helpers::experimental_features_is_enabled() ) {
    add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
}
```

## Shared event query

Both callbacks resolve the request's email to a user and page through that
user's initiated events:

-   `get_user_by( 'email', $email_address )`. No matching user → return immediately
    with `done = true` and no data.
-   Query via `Log_Query` filtered by `user` = the resolved ID, paginated
    (page size ~100), ordered **oldest-first** for stable pagination across pages.
-   `done = ( returned_count < page_size )`.

## The exporter

Registered on `wp_privacy_personal_data_exporters` as group `simple-history`.

-   Callback signature `( $email_address, $page )`.
-   One export item per event: `item_id = "sh-event-{id}"`, group label
    _"Simple History activity log"_.
-   Fields per event:
    -   **Date** (site-local + GMT)
    -   **Logger**
    -   **Level**
    -   **Message** (human-readable, interpolated to plain text)
    -   **IP address**
    -   **User agent**
-   Returns `{ data: [...], done: bool }`.

## The eraser

Registered on `wp_privacy_personal_data_erasers`, group `simple-history`. Same
user-resolution and pagination as the exporter.

For each matched event, rewrite its context rows:

| Context key(s)                                                                                            | Action                                                     |
| --------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- |
| `_user_id`                                                                                                | set to `0`                                                 |
| `_user_login`                                                                                             | remove                                                     |
| `_user_email`                                                                                             | remove                                                     |
| `server_http_user_agent`                                                                                  | remove                                                     |
| `_server_http_referer`                                                                                    | remove                                                     |
| `_server_remote_addr` **and every `_server_*` IP-header variant** (e.g. `_server_http_x_forwarded_for_0`) | re-mask to `0.0.0.x` via `Helpers::privacy_anonymize_ip()` |
| timestamp, logger, level, message key, object references                                                  | **keep**                                                   |

-   **Scrub, never delete.** Event rows are retained as an anonymized audit record
    ("someone did X at time T"), with personal data removed.
-   **Idempotent.** Re-running on an already-scrubbed event is a harmless no-op
    (keys already absent / already `0`).
-   Returns `{ items_removed: true, items_retained: true, messages: [...], done: bool }`.
    The `messages[]` line:
    > "Simple History anonymized the personal data in N activity-log entries. The
    > entries themselves are retained as an audit record with personal data removed."
-   After a successful pass, log **one** summary event ("Anonymized personal data
    in N events for a privacy erasure request") — count only, no subject PII.

## Settings UI

A new **"Privacy & Data"** tab: a `Menu_Page` child of the General-settings
parent, ordered after Log Forwarding. A single **Compliance** section rendered
via `Helpers::add_settings_section()`, no form fields. The structure leaves room
for premium to register additional subsections/fields into the same tab.

Info text is conditional, so the admin-facing claim matches what is actually wired up:

-   **Always shown:**
    > "Simple History is registered with WordPress's personal-data **export** tool
    > (Tools → Export Personal Data). When you process a request there, Simple
    > History's activity log is included automatically."
-   **Shown only when experimental features are enabled:**
    > "…and with the **erasure** tool — running an erasure request anonymizes
    > personal data in matching activity-log entries while preserving the audit record."

## Edge cases & constraints

-   **Deleted WordPress users.** Privacy requests are keyed on email; if no user
    matches, there is nothing to export/erase. Old events whose `_user_id` points
    at a since-deleted user are not reachable by email — out of scope (documented).
-   **Multisite.** Follow WordPress's per-site privacy model — operate on the
    current site's standard events table; WordPress iterates sites itself.
    Network-event-table handling is **not** in this feature's scope (follow-up).
-   **SQLite & MySQL.** All reads/writes go through `Log_Query` / `$wpdb`
    parameterized statements — no engine-specific SQL.
-   **Performance.** Pagination caps work per pass; context updates are batched
    per event.

## Testing

Integration tests (per the **testing** skill's framework):

-   Exporter returns the user's initiated events, paginates correctly, sets `done`.
-   Exporter with an email matching no user → empty data + `done = true`.
-   Eraser scrubs **every** target key and **preserves** timestamp / logger /
    message-key / object references; rows are **not** deleted.
-   Eraser handles multiple IP-header keys on a single event.
-   Idempotency: a second eraser pass on already-scrubbed events is a stable no-op.
-   Eraser is **registered** when experimental features are on and **not
    registered** when off (toggle the option/filter in test setup).
-   Settings tab renders the always-on export info text; the erasure line appears
    only when experimental features are enabled.

## Release & lifecycle (build → evaluate)

Because the eraser ships behind experimental features, the release follows the
project's build→evaluate lifecycle:

-   On shipping, create a follow-up issue **"Evaluate experimental — WP Privacy
    eraser"** (`type: idea`, `status: 2-todo`), linking back to [[3 - Add privacy-GDPR settings group]],
    revisit ~4–8 weeks later.
-   Evaluation criteria: Did anyone run an erasure? Any reports of over- or
    under-scrubbing? Decision: graduate to always-on, keep experimental, or adjust.
-   **Graduation** = make the eraser registration unconditional and make the
    Compliance info text always show both the export and erasure lines.
-   The **exporter** is always-on from day one and is **not** part of the
    experiment.

## Out of scope / future (roadmap context)

This feature is the free/core foundation of roadmap [[3 - Add privacy-GDPR settings group]].
It deliberately creates the "Privacy & Data" settings tab so the premium
features slot in later: #1 IP Address Storage, #2 User Agent Storage, #3 Login
Page Notice, #5 Data Snapshot, #6 Bulk Retroactive Anonymization.
