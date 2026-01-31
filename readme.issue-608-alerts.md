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

**Other:**

-   Direct event links in alert notifications
-   Core plugin teaser (preview of premium UI with `inert` attribute)
-   Plugin update rollback detection (`plugin_update_rolled_back` message)
-   Forced security update detection (`plugin_update_type` context field)
-   SQL performance optimizations for sidebar stats queries

### ⏳ TODO

-   Larger text in destinations intros
-   Remove event details/context from alerts (drive users to the log)
-   Custom rules for context values: Need a way to match on event context (e.g., "post published" = post_status changed to "publish"). Research how to expose context fields in rule builder UI and evaluate in rules engine.

### 📋 Deferred

-   "Create alert from event" feature (revisit after more testing)
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

## Related Issues

-   #573 (Log Forwarding - channels infrastructure)
-   #209, #114, #366 (Original alert requests)
