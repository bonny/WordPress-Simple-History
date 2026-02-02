# Issue #608: Alerts & Notifications

**Status:** In Progress
**Branch:** `issue-608-alerts`

## Scope

**Alerts** = Selective, rule-based notifications when specific events occur.
**NOT** Log Forwarding (which streams ALL events) - that's already done via Channels.

| Aspect       | Channels (done)      | Alerts (this issue)         |
| ------------ | -------------------- | --------------------------- |
| Purpose      | Archive/backup       | Real-time notification      |
| Filtering    | None (all events)    | Rule-based (selective)      |
| Destinations | Log systems (Syslog) | Notification (Slack, Email) |

## Status Summary

### ✅ Completed

**Infrastructure:**

-   Destinations architecture, storage, REST API, settings UI
-   All 4 destination senders (Email, Slack, Discord, Telegram)
-   Send tracking, test buttons, WP-CLI commands
-   Event logging when destinations/rules saved

**Alert Rules:**

-   Preset-based alerts UI (Tier 1 quick setup)
-   Custom rules builder (Tier 3) - React UI with react-select, REST API, WP-CLI
-   Rule evaluation engine
-   Context-based rules with grouped fields (Post Events, User Events, Plugin Events)

**Other:**

-   Direct event links in alert notifications
-   Core plugin teaser (preview of premium UI with `inert` attribute)
-   Plugin update rollback detection (`plugin_update_rolled_back` message)
-   Forced security update detection (`plugin_update_type` context field)
-   SQL performance optimizations for sidebar stats queries
-   UI/UX improvements: wording consistency, styling, success messages
-   Alert content optimization: removed event details to drive users to log
-   Rules section improvements: warnings, save button placement, pre-filled defaults
-   Rule preview feature: preview matching events before saving

### ⏳ TODO

-   ~~Larger text in destinations intros~~ ✅
-   ~~Remove event details/context from alerts (so users are driven to the log, where they can see the full event details and also see promo boxes)~~ ✅
-   ~~Research if it's ok to send event non async.~~ ✅
-   ~~Add text warning that user should not add to "broad" rules that match too many events.~~ ✅
-   ~~Add preview-filed to directly see what messages that match the rule.~~ ✅
-   ~~In quick rules it's called "Forward events to:" but in custom rules table we say "Send to". We must use same wording at all places.~~ ✅
-   ~~When saving custom rules there is no "Saved" message. Quick setup has it.~~ ✅
-   ~~Save button is at bottom of page and applies only to the quick setup rules. Kinda confusing that it is below custom rules. How can we solve or improve this usability issue?~~ ✅
-   ~~In custom rules modal, text "Conditions" and "Forward events to:" should match formatting of "Rule Name" (currently bold).~~ ✅

### 📋 Deferred

-   "Create alert from event" feature (revisit after more testing)
-   Even nicer GUI, perhaps cards at top with quick stats and info.
-   Teams channel (Phase 2)
-   SMS/Twilio, Pushover, PagerDuty (Phase 3)

## Code Organization

| Component                     | Location    |
| ----------------------------- | ----------- |
| Alerts settings tab (teaser)  | **Core**    |
| Destination classes           | **Premium** |
| Alert rule classes            | **Premium** |
| Rule evaluation engine        | **Premium** |
| Settings page with real forms | **Premium** |

**Core files:**

```
inc/services/class-alerts-settings-page-teaser.php
```

**Premium files:**

```
simple-history-premium/inc/alerts/
├── class-alerts-service.php
├── class-alerts-settings-page.php
├── class-alerts-manager.php
├── class-alert-evaluator.php
├── destinations/
│   ├── class-destination.php (base)
│   ├── class-email-destination.php
│   ├── class-slack-destination.php
│   ├── class-discord-destination.php
│   └── class-telegram-destination.php
└── presets/
    └── class-preset.php (base + security, content, plugins)
```

## Alert Rules Tiers

-   **Tier 1 (80%):** One-click presets - toggle + select destinations ✅
-   **Tier 2 (15%):** Editable presets - customize which events (deferred)
-   **Tier 3 (5%):** Custom rules - Zapier-style condition builder ✅

## Research: Async sending

Alerts are currently sent **synchronously** when an event is logged: `process_logged_event` runs on the `simple_history/log/inserted` hook and sends to destinations in the same request.

**Pros of sync:** Immediate delivery; no dependency on WP-Cron; simpler code and debugging; no risk of cron being disabled or delayed.

**Pros of async (e.g. `wp_schedule_single_event`):** The request that logged the event is not blocked by HTTP calls to Slack/Email/etc.; less risk of timeouts or slow admin; high-volume sites could batch or throttle.

**Recommendation:** Keep sync for now. Alert volume is typically low (rule-based, selective). If we see timeouts or performance issues, we can add an option to send asynchronously or move to async by default with a filter to force sync for testing.

---

## Context-Based Alert Rules (Implemented)

Fields are grouped in the rule builder dropdown:

| Group          | Fields                                       |
| -------------- | -------------------------------------------- |
| General        | Message Type, Logger, Level, Initiator, etc. |
| Post Changes   | New Status, Old Status, Post Type            |
| User Changes   | New Role, Old Role                           |
| Plugin Changes | Plugin Name                                  |

**Example rules now possible:**

-   Post published: `New Status is Published`
-   Draft → Published: `Old Status is Draft` AND `New Status is Published`
-   User becomes admin: `New Role is Administrator`
-   Specific post type: `Post Type is Page`

**Technical note:** Context fields use `context.` prefix internally (e.g., `context.post_new_status`) for collision safety. JsonLogic's dot notation accesses nested data.
