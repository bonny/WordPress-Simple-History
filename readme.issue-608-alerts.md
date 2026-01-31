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

---

## Research: Context-Based Alert Rules

### Key Finding

**The infrastructure already supports context-based rules!** Alert_Evaluator flattens context data and makes it available for JsonLogic evaluation. The gap is the **UI field registry** doesn't expose context fields.

### Use Case Scenarios

| Scenario                     | Context Fields Needed                 | Rule Example                         |
| ---------------------------- | ------------------------------------- | ------------------------------------ |
| Post published               | `post_new_status`                     | `post_new_status = 'publish'`        |
| Draft → Published transition | `post_prev_status`, `post_new_status` | `prev = 'draft' AND new = 'publish'` |
| User becomes administrator   | `new_role`                            | `new_role = 'administrator'`         |
| Login from unexpected IP     | `_server_remote_addr`                 | `IP not in [allowed list]`           |
| Post edited by non-author    | `post_new_author`, `_user_id`         | `author != current_user`             |
| Security plugin update only  | `plugin_update_type`                  | `update_type = 'security'`           |
| Specific post type changes   | `post_type`                           | `post_type = 'page'`                 |

### Available Context by Logger

**Post Logger:** `post_id`, `post_type`, `post_title`, `post_prev_status`, `post_new_status`, `post_prev_author`, `post_new_author`

**User Logger:** `edited_user_id`, `new_role`, `old_role`, `edited_user_email`

**Plugin Logger:** `plugin_name`, `plugin_version`, `plugin_update_type`

### Implementation Approach

**Phase 1 - Whitelist (Quick Win):**

1. Add known useful context fields to `Alert_Field_Registry::get_fields()`
2. Hardcode ~15 most valuable fields per logger
3. No DB queries needed - static definitions

**Phase 2 - Smart Discovery:**

1. Query `wp_simple_history_contexts` for unique keys from recent events
2. Cache results in transient (24h)
3. Infer field types from sample values
4. Add filter hook for customization

### Files to Modify

| File                             | Change                                                         |
| -------------------------------- | -------------------------------------------------------------- |
| `class-alert-field-registry.php` | Add `get_context_fields()` method, whitelist fields per logger |
| `class-alert-evaluator.php`      | Already works - just add documentation                         |
| React UI                         | No changes needed - fields auto-populate from registry         |

### Example Field Definition

```php
[
    'name'       => 'post_new_status',
    'label'      => __( 'Post Status (New)', 'simple-history' ),
    'inputType'  => 'select',
    'operators'  => [ '=', '!=' ],
    'values'     => [
        [ 'name' => 'publish', 'label' => 'Published' ],
        [ 'name' => 'draft', 'label' => 'Draft' ],
        [ 'name' => 'pending', 'label' => 'Pending' ],
        [ 'name' => 'trash', 'label' => 'Trash' ],
    ],
]
```

### Example JsonLogic Rule

```json
{
	"and": [
		{ "==": [ { "var": "post_prev_status" }, "draft" ] },
		{ "==": [ { "var": "post_new_status" }, "publish" ] }
	]
}
```

This matches posts transitioning specifically from draft → published.
