# Issue #608: Alerts & Notifications

**Status:** In Progress
**Size:** Large
**Labels:** Feature, Size: Large
**Branch:** `issue-608-alerts`

## Review and comments from human developer

-   ~~Intro section for Destinations (`sh-SettingsCard sh-SettingsPage-settingsSection-wrap`) and alert rules (`sh-SettingsCard sh-SettingsPage-settingsSection-wrap`) looks different from Log forwarding intro section and also different than Failed login attempts intro section. I think we need a common layout for this that works in all scenarios!~~
-   ~~Research: How to implement async processing via Action Scheduler or WP Cron for production sites with high event volume.~~
-   ~~Destinations page, could each table benefit from a column with info about what alert rules are sending to it?~~
-   ~~Should we also add context to messages sent? Right now it only says "Edited profile for user abc" but it would be useful to also know that was changed, for example role changed? Just a first name change vs role change, it's very different!~~ → Documented in [Phase 2: Enhanced Alert Message Context](#enhanced-alert-message-context)
-   ~~Promo for alert features in core uses completely differnt layout, it should preview the premium settings, including 2 tabs and all the fields. But non editable and no functionality of course.~~ → Done (2026-01-10)

## Scope Clarification

This issue focuses **only on Alerts & Notifications** - selective, rule-based notifications when specific events occur.

**NOT in scope** (already implemented in Channels system):

-   Log Forwarding / Channels infrastructure (done)
-   Syslog channels (done in premium)
-   External Database channel (done in premium)
-   Datadog, Splunk channels (done in premium)
-   Webhook channel (done in premium)
-   File channel (done in core)

## Problem Description

Users want to be notified in real-time when **specific** events happen.

Example events:

-   Admin logins or failed login attempts
-   Plugin/theme changes
-   User registrations
-   Critical errors

Unlike Log Forwarding (which streams ALL events), Alerts are **selective** - only events matching configured rules trigger notifications.

## Key Difference: Channels vs Alerts

| Aspect       | Channels (done)              | Alerts (this issue)               |
| ------------ | ---------------------------- | --------------------------------- |
| Purpose      | Archive/backup/monitoring    | Real-time notification            |
| Filtering    | None (all events)            | Rule-based (selective)            |
| Volume       | High                         | Low                               |
| Destinations | Log systems (Syslog, Splunk) | Notification tools (Slack, Email) |
| User need    | "Store my logs externally"   | "Tell me when X happens"          |

## What Needs to Be Built

### 1. Alert Destinations (Premium)

Notification-focused channels based on competitor analysis and market gaps:

#### Competitor Channel Support

| Channel      | WP Activity Log | Wordfence | Stream | Logtivity | Simple History |
| ------------ | :-------------: | :-------: | :----: | :-------: | :------------: |
| Email        |       ✅        |    ✅     |   ✅   |    ✅     |     🎯 MVP     |
| Slack        |       ✅        |    ✅     |  ✅\*  |    ✅     |     🎯 MVP     |
| Discord      |       ❌        |    ✅     |   ❌   |    ❌     |     🎯 MVP     |
| Telegram     |       ❌        |    ❌     |   ❌   |    ❌     |     🎯 MVP     |
| Teams        |       ❌        |    ❌     |   ❌   |    ❌     |    Phase 2     |
| SMS (Twilio) |       ✅        |    ✅     |   ❌   |    ❌     |    Phase 3     |
| Webhooks     |       ❌        |    ❌     |   ✅   |    ✅     |    ✅ Done     |

#### Integration Complexity (Verified Dec 2025)

| Channel          | Difficulty | Setup                                    | Cost        | Rate Limits   |
| ---------------- | :--------: | ---------------------------------------- | ----------- | ------------- |
| **Email**        |  🟢 Easy   | None (wp_mail)                           | Free        | Server limits |
| **Slack**        |  🟢 Easy   | User creates webhook URL                 | Free        | 1 msg/sec     |
| **Discord**      |  🟢 Easy   | User creates webhook URL                 | Free        | 5 req/2 sec   |
| **Telegram**     |  🟢 Easy   | Create bot via @BotFather                | Free        | 30 msg/sec    |
| **Teams**        | 🟡 Medium  | Power Automate Workflows                 | Free        | Varies        |
| **SMS (Twilio)** | 🟡 Medium  | API key + phone number                   | Per-message | Account-based |
| **WhatsApp**     |  🔴 Hard   | Business verification, template approval | Per-message | Complex       |

#### Prioritized Channel List

**MVP (Must Have + Easy Wins):**

| Channel      | Why                                                        | Implementation      |
| ------------ | ---------------------------------------------------------- | ------------------- |
| **Email**    | Universal, everyone has it                                 | Via wp_mail()       |
| **Slack**    | Most requested, industry standard                          | Webhook + Block Kit |
| **Discord**  | 🟢 Very easy, only Wordfence has it                        | Simple webhook POST |
| **Telegram** | 🟢 Very easy, popular in EU/Asia, **no competitor has it** | Bot API (free)      |

**Phase 2 (Medium Effort - Unique Differentiator):**

| Channel             | Why                                  | Implementation           |
| ------------------- | ------------------------------------ | ------------------------ |
| **Microsoft Teams** | Enterprise, **no competitor has it** | Power Automate Workflows |

Note: Teams O365 Connectors deprecated Oct 2024, full retirement end of 2025. Must use Workflows (more complex setup for users).

**Phase 3 (Enterprise/Niche):**

| Channel          | Why                          | Implementation |
| ---------------- | ---------------------------- | -------------- |
| **SMS (Twilio)** | High-urgency, direct         | Twilio API     |
| **Pushover**     | Simple push notifications    | Pushover API   |
| **PagerDuty**    | On-call alerting, enterprise | Events API v2  |

**Not Recommended:**

| Channel      | Why Skip                                                                                                  |
| ------------ | --------------------------------------------------------------------------------------------------------- |
| **WhatsApp** | Requires Business API, Meta verification, template approval, per-message fees. Too complex for the value. |

**Already Done:**

-   `Webhook_Channel` (premium) - covers Zapier, Make, n8n, custom endpoints. No alerts yet however.

### 2. Alert Rules UX (Premium)

The backend exists (`Alert_Rules_Engine`, `Alert_Evaluator`) but needs a user-friendly UI.

**Design Principle:** Progressive disclosure - simple for beginners, powerful for experts.

#### Tier 1: One-Click Presets (80% of users)

```
┌─────────────────────────────────────────────────────────┐
│ Quick Alerts                                            │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ☐ Security Alerts                              [Edit]   │
│   Failed logins, user role changes, new admin users     │
│   → Sends to: [Select destination ▾]                    │
│                                                         │
│ ☐ Content Changes                              [Edit]   │
│   Posts published, pages deleted, media uploads         │
│   → Sends to: [Select destination ▾]                    │
│                                                         │
│ ☐ Plugin & Theme Activity                      [Edit]   │
│   Installs, updates, activations, deletions             │
│   → Sends to: [Select destination ▾]                    │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

-   Zero learning curve
-   User picks preset + destination
-   Enable in 5 seconds

#### Tier 2: Editable Presets (15% of users)

Click "Edit" on a preset to customize:

```
┌─────────────────────────────────────────────────────────┐
│ Edit: Security Alerts                                   │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Alert me when ANY of these happen:                      │
│                                                         │
│   ☑ User login fails                                    │
│   ☑ User role changes to Administrator                  │
│   ☑ New user created with Administrator role            │
│   ☐ Password changed                                    │
│   ☐ User deleted                                        │
│                                                         │
│ Send to: ☑ Slack  ☑ Email  ☐ Discord  ☐ Telegram       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

-   Presets become editable templates
-   Toggle specific events on/off
-   Still uses checkboxes (familiar UI)

#### Tier 3: Custom Rules (5% of power users)

Full control with Zapier-style conditions:

```
┌─────────────────────────────────────────────────────────┐
│ Create Custom Alert                                     │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Name: [Failed Admin Logins________________]             │
│                                                         │
│ Alert me when:                                          │
│                                                         │
│   [Logger ▾] [equals ▾] [User ▾]                        │
│                                                   [+AND]│
│   [Message ▾] [contains ▾] [failed________]             │
│                                                   [+AND]│
│   [User role ▾] [equals ▾] [Administrator ▾]            │
│                                                         │
│ ─────────────────────────────────────────────────────── │
│ Preview: "Alert when User logger message contains       │
│          'failed' AND user role is Administrator"       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

-   Natural language preview of what rule does
-   Field → Operator → Value pattern (proven by Zapier)
-   Limited operators per field type

#### "Create from Event" (Gmail pattern)

Add to event dropdown menu:

```
┌─────────────────────────┐
│ 📋 Copy details         │
│ 🔗 Link to this event   │
│ ─────────────────────── │
│ 🔔 Create alert for     │
│    events like this     │
└─────────────────────────┘
```

Pre-fills rule with event's logger and message key. Power users discover rule-building contextually.

#### Technical Note

**Presets don't need JsonLogic.** Simple event type arrays:

```php
$security_preset = [
    'events' => ['user_login_failed', 'user_role_changed'],
    'destinations' => ['slack', 'email'],
];
```

Only Tier 3 custom rules need the full `Alert_Rules_Engine` with JsonLogic.

#### Implementation Order

| Phase | Feature                   | Effort |
| ----- | ------------------------- | ------ |
| MVP   | Presets only (Tier 1)     | Low    |
| v1.1  | Editable presets (Tier 2) | Low    |
| v1.2  | Custom rules (Tier 3)     | Medium |
| v1.3  | "Create from event"       | Low    |

## Destinations Architecture

### The Problem

Users need to send alerts to multiple places of the same type:

-   Multiple Slack channels (Security → #security, Dev → #dev-updates)
-   Multiple Slack workspaces (Client A, Client B)
-   Multiple Telegram groups (private admin group, public channel)
-   Multiple email recipients (security-team@, editors@)

### Recommendation: Destinations as First-Class Entities

Separate "where to send" from "what to send":

```
Settings > Alerts
├── Destinations (configure once, reuse)
│   ├── "Security Team Slack" (webhook: xxx, #security)
│   ├── "Dev Team Slack" (webhook: yyy, #dev-updates)
│   ├── "Admin Email" (admin@example.com)
│   ├── "Telegram Alerts" (bot: xxx, chat: -123456)
│   └── [+ Add Destination]
│
└── Alert Rules (reference destinations)
    ├── Security Alerts → Security Team Slack, Admin Email
    └── Plugin Changes → Dev Team Slack
```

**Why this approach:**

-   No duplicate credentials (change webhook once → all alerts updated)
-   Clear separation of "where" vs "what"
-   Can test each destination independently
-   Same pattern as email clients managing "accounts"

### Destinations UI

```
┌─────────────────────────────────────────────────────────┐
│ Alert Destinations                                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Slack                                                   │
│ ├── Security Team         #security    [Test] [Edit]   │
│ └── Dev Updates           #dev         [Test] [Edit]   │
│                                    [+ Add Slack]        │
│                                                         │
│ Email                                                   │
│ └── Admin                 admin@...    [Test] [Edit]   │
│                                    [+ Add Email]        │
│                                                         │
│ Telegram                                                │
│ └── Alerts Group          @alerts_bot  [Test] [Edit]   │
│                                    [+ Add Telegram]     │
│                                                         │
│ Discord                                                 │
│ └── (none configured)     [+ Add Discord]              │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Alerts Reference Destinations

```
┌─────────────────────────────────────────────────────────┐
│ Security Alerts                               [Enabled] │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Send to:                                                │
│   ☑ Security Team (Slack)                              │
│   ☑ Admin (Email)                                      │
│   ☐ Dev Updates (Slack)                                │
│   ☐ Alerts Group (Telegram)                            │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Per-Channel Credential Requirements

| Channel      | What varies per destination      | Auth method |
| ------------ | -------------------------------- | ----------- |
| **Slack**    | Webhook URL (unique per channel) | In URL      |
| **Discord**  | Webhook URL (unique per channel) | In URL      |
| **Telegram** | Bot token + Chat ID              | Bot token   |
| **Email**    | Recipient address(es)            | wp_mail()   |
| **Teams**    | Workflow URL                     | In URL      |

**Note:** For Telegram, one bot can send to multiple groups (same token, different chat_ids).

### Technical Storage

```php
// Destinations stored separately (wp_option: simple_history_alert_destinations)
$destinations = [
    'dest_abc123' => [
        'type' => 'slack',
        'name' => 'Security Team',
        'webhook_url' => 'https://hooks.slack.com/...',
        'channel' => '#security',
    ],
    'dest_def456' => [
        'type' => 'telegram',
        'name' => 'Alerts Group',
        'bot_token' => '123:ABC...',  // encrypted
        'chat_id' => '-100123456789',
    ],
];

// Alerts reference destinations by ID (wp_option: simple_history_alerts)
$alerts = [
    'security_alerts' => [
        'preset' => 'security',
        'enabled' => true,
        'destinations' => ['dest_abc123', 'dest_def456'],
    ],
];
```

## Class Architecture

Alerts build on the existing Channels infrastructure:

```
Channel (base class) ← already has alert_rules support
├── File_Channel (core)
├── Webhook_Channel (premium) ← generic, already done
├── Slack_Channel (premium) ← NEW (MVP)
├── Email_Channel (premium) ← NEW (MVP)
├── Discord_Channel (premium) ← NEW (MVP)
├── Telegram_Channel (premium) ← NEW (MVP)
└── Teams_Channel (premium) ← NEW (Phase 2)
```

The base `Channel` class already has:

-   `get_alert_rules()` / `set_alert_rules()`
-   `should_send_event($event_data)` - currently returns true if no rules

**Need to implement:**

-   Wire up `should_send_event()` to use `Alert_Rules_Engine`
-   Build UI for rule configuration
-   Create notification-specific channels

## Existing Foundation

These files exist and can be leveraged:

| File                                                               | Purpose              | Status               |
| ------------------------------------------------------------------ | -------------------- | -------------------- |
| `simple-history-premium/inc/alerts/class-alert-evaluator.php`      | JsonLogic wrapper    | ⚠️ Not tested        |
| `simple-history-premium/inc/alerts/class-alert-field-registry.php` | UI field definitions | ⚠️ Not tested        |
| `simple-history-premium/inc/alerts/class-alert-rules-engine.php`   | Service facade       | ⚠️ Not tested        |
| `simple-history-premium/inc/libraries/JsonLogic.php`               | Third-party library  | ✅ (upstream tested) |
| `docs/alerts-feature-research.md`                                  | Competitor analysis  | ✅                   |
| `docs/alerts-async-processing-research.md`                         | Performance research | ✅                   |

**Note:** The Alert_Rules_Engine and related classes have been moved from core to premium for WordPress.org compliance. They have no test coverage yet - tests should be written before relying on this code.

## Implementation Plan

### Phase 1: MVP (4 channels - all easy)

1. **Test Alert_Rules_Engine** - Write tests for existing foundation code
2. **Wire up filtering** - Connect `should_send_event()` to rules engine
3. **Email_Channel** - Alerts via wp_mail()
4. **Slack_Channel** - Webhook + Block Kit formatting
5. **Discord_Channel** - Simple webhook POST (very easy, same pattern as Slack)
6. **Telegram_Channel** - Bot API (very easy, unique differentiator)
7. **Basic Rule UI** - Settings UI for rule creation

### Phase 2: Teams + Polish

1. **Teams_Channel** - Power Automate Workflows (more complex, but no competitor has it)
2. **Alert presets** - One-click security/admin/user presets
3. **Rate limiting** - Per-channel throttling

### Phase 3: Enterprise/Niche

1. **SMS_Channel** - Twilio integration
2. **Pushover_Channel** - Push notifications
3. **PagerDuty_Channel** - On-call alerting
4. **Digest mode** - Batch notifications (hourly/daily summary)
5. **"Create alert from event"** - Action menu integration

## Settings Page Structure

### UX Research Summary

Based on research from [Smashing Magazine](https://www.smashingmagazine.com/2025/07/design-guidelines-better-notifications-ux/), [UI Patterns](https://ui-patterns.com/patterns/rule-builder), and [Nielsen Norman Group](https://www.nngroup.com/articles/progressive-disclosure/).

**Core UX Principle: Separation of Concerns**

Alert systems work best when they separate:

| Concern          | What it answers           | Example                          |
| ---------------- | ------------------------- | -------------------------------- |
| **Destinations** | "Where do alerts go?"     | Slack #security, admin@email.com |
| **Rules**        | "What triggers an alert?" | Failed logins, plugin changes    |
| **Behavior**     | "How do alerts behave?"   | Rate limits, digest mode         |

### Recommended: Two-Subtab Approach

```
Settings (parent)
├── General (existing)
├── Log Forwarding (existing - for ALL events)
├── Alerts (NEW - for SELECTIVE notifications)
│   ├── Destinations (subtab)
│   └── Alert Rules (subtab)
└── Licenses (existing)
```

**Why separate "Log Forwarding" and "Alerts"?**

| Log Forwarding             | Alerts                             |
| -------------------------- | ---------------------------------- |
| All events → destination   | Only matching events → destination |
| Archive/backup purpose     | Real-time notification purpose     |
| Technical users            | All users                          |
| "Store my logs externally" | "Tell me when X happens"           |

### Destinations Subtab

Configure where alerts can be sent. Do this once, then reference from rules.

```
┌─────────────────────────────────────────────────────────────┐
│ Alert Destinations                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 📧 Email                                                │ │
│ │ ├── Admin Team        admin@example.com    [Test][Edit] │ │
│ │ └── Security Team     security@...         [Test][Edit] │ │
│ │                                      [+ Add Email]      │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 💬 Slack                                                │ │
│ │ └── #security-alerts  hooks.slack.com/... [Test][Edit]  │ │
│ │                                      [+ Add Slack]      │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Alert Rules Subtab

Progressive disclosure with three tiers:

**Tier 1: Quick Presets** (80% of users) - Toggle + select destinations, 5-second setup

**Tier 2: Customized Presets** (15%) - Click "Customize" to toggle specific events

**Tier 3: Custom Rules** (5%) - Zapier-style condition builder for power users

## Core/Premium Code Split

### WordPress.org Compliance

Per WordPress.org guidelines: "All hosted code must be free and fully functional. No premium/locked code."

This means:

-   **Core plugin**: Only teaser UI, no alert functional classes
-   **Premium plugin**: All functional alert code

### What Goes Where

| Component                         | Location    | Rationale          |
| --------------------------------- | ----------- | ------------------ |
| Alerts settings tab (teaser only) | **Core**    | Shows upgrade path |
| Destination classes               | **Premium** | Functional code    |
| Alert rule classes                | **Premium** | Functional code    |
| Rule evaluation engine            | **Premium** | Functional code    |
| Settings page with real forms     | **Premium** | Functional code    |
| Hooks for premium to register     | **Core**    | Extension point    |

### Core Plugin Files

```
inc/services/class-alerts-settings-page-teaser.php  # Registers teaser tab
templates/settings-alerts-teaser.php                 # Teaser HTML
```

The teaser service:

1. Registers the "Alerts" tab in settings (teaser version)
2. Shows premium feature preview
3. Gets **replaced** when premium is active via filter

### Premium Plugin Files

```
simple-history-premium/
├── inc/alerts/
│   ├── class-alerts-service.php           # Main service
│   ├── class-alerts-settings-page.php     # Real settings (replaces teaser)
│   ├── class-alerts-manager.php           # Manages destinations + rules
│   ├── class-alert-rule.php               # Rule data model
│   ├── class-alert-evaluator.php          # Evaluates rules
│   │
│   ├── destinations/
│   │   ├── class-destination.php          # Base class
│   │   ├── class-email-destination.php
│   │   ├── class-slack-destination.php
│   │   ├── class-discord-destination.php
│   │   └── class-telegram-destination.php
│   │
│   └── presets/
│       ├── class-preset.php               # Base preset class
│       ├── class-security-preset.php
│       ├── class-content-preset.php
│       └── class-plugins-preset.php
```

### How Premium Replaces Core Teaser

Premium hooks into core via filter:

```php
// Premium tells core it's handling alerts
add_filter( 'simple_history/alerts/settings_page_class', function() {
    return Alerts_Settings_Page::class;
});
```

Core teaser checks this filter and skips registration if premium is active.

## Progress Log

### 2026-01-01/02: Alert Rules UI

Implemented Tier 1 preset-based alert rules UI with UX polish:

-   Simplified preset cards matching Destinations styling
-   Destinations grouped by type with icons and status indicators
-   Email recipients shown with envelope icon + count badge (tooltip shows addresses)
-   "+" quick-add links navigate to Destinations tab with anchor highlighting
-   CSS tooltips, proper status positioning, various bug fixes

### 2025-12-29/30: Destinations UI & Code Quality

Destinations table UX improvements and phpcs/phpstan fixes:

-   Send tracking (success/failure status per destination)
-   Test button with loading states, delete confirmation fix
-   WordPress table patterns, button icon alignment
-   Code style fixes (curly braces, ternaries, namespaces)

### Status Summary

**Completed:**

-   ✅ Destinations (architecture, storage, REST API, settings UI)
-   ✅ All 4 destination senders (Email, Slack, Discord, Telegram)
-   ✅ Send tracking, test buttons, WP CLI commands
-   ✅ Alert presets UI (Tier 1 quick setup)
-   ✅ Alert rules saving and evaluation
-   ✅ Event logging when destinations/rules are saved
-   ✅ Enhanced alert message context (all senders include `get_details_text()` output)
-   ✅ Custom rules builder (Tier 3) - React UI with react-select, REST API, WP-CLI commands, human-readable labels

**Not Started:**

-   ⏳ Editable presets (Tier 2)
-   ⏳ "Create alert from event" feature

## Phase 2: Future Improvements

Features planned for after MVP release.

### Enhanced Alert Message Context

**Status:** ✅ Complete
**Priority:** High

Alert messages include detailed context via `Event::get_details_text()` which converts HTML event details (diffs, changes) to plain text. All 4 destination senders (Email, Slack, Discord, Telegram) include this in their alert messages.

**Future consideration:**

-   Per-event-type formatting (critical changes get full detail, cosmetic changes summarized)

## Related Issues

-   #573 (Log Forwarding - completed, channels infrastructure)
-   #209, #114, #366 (Original alert requests)

---

## Detailed Progress Log (Chronological)

### 2026-01-04: Review Questions Addressed

-   WP CLI commands added (`class-wp-cli-alerts-command.php`)
-   Destination descriptions updated to be more helpful
-   Event logging implemented (`class-alerts-logger.php`) for destination/rule changes

### 2026-01-08: DRY Refactoring

Code deduplication (~150 lines removed):

-   Added `Log_Levels::get_level_color()` and `get_level_emoji()` to core
-   Added `Helpers::sanitize_error_message()` to core
-   Refactored destination senders to use shared methods
-   Single source of truth for level colors, emojis, labels, error sanitization

### 2026-01-10: Performance & Code Quality

-   Fixed destination tracking reset bug (sanitize callback was stripping `tracking` key)
-   Batched option reads with `get_options()` (WP 6.4+) - 3 DB queries → 1
-   Removed unused `_version` metadata (~53 lines)
-   Extracted methods from `process_logged_event()` for readability
-   Added 'Readable Code' principle to code quality guidelines

### 2026-01-10: WordPress.org Compliance

Moved alert engine classes (JsonLogic, evaluator, field registry) from core to premium.

### 2026-01-10: Enhanced Alert Context

Added `Event::get_details_text()` for plain text event details in alerts. Hook `simple_history/log/inserted` now passes event ID via `$data['id']`.

### 2026-01-10: Teaser Redesign

Redesigned the core plugin's alert settings teaser to preview the actual premium UI:

-   **Two functional tabs**: Destinations and Alert Rules (tabs work, content is disabled)
-   **Destinations preview**: Shows all 4 types (Email, Slack with sample data; Discord, Telegram empty)
-   **Alert Rules preview**: 3 preset cards with expandable event lists, destination checkboxes, Custom Rules section
-   **HTML `inert` attribute**: Native browser support for disabling all content interaction
-   **Shared CSS components**: Reuses styles matching premium UI (`.sh-CardHeader`, `.sh-StatusIcon`, `.sh-PresetCard-*`)
-   **Upgrade CTA**: Sticky at bottom with Premium badge

Files modified:

-   `inc/services/class-alerts-settings-page-teaser.php` - Complete rewrite
-   `css/styles.css` - Replaced old teaser styles with premium-matching components

---

## Plugin Update Rollback Investigation (2026-01-13)

### Problem

Simple History logs plugin updates as successful even when WordPress rolls them back due to fatal errors. Users see "Updated WooCommerce to 10.4.3" but the plugin remains at 10.4.2.

### WordPress.org API Request/Response

**Request URL:**

```
POST https://api.wordpress.org/plugins/update-check/1.1/
```

**Request Body (truncated):**

```json
{
  "plugins": {
    "woocommerce/woocommerce.php": {
      "Name": "WooCommerce",
      "Version": "10.4.2",
      ...
    },
    ...
  }
}
```

**Response for WooCommerce:**

```php
stdClass Object (
    [id] => w.org/plugins/woocommerce
    [slug] => woocommerce
    [plugin] => woocommerce/woocommerce.php
    [new_version] => 10.4.3
    [url] => https://wordpress.org/plugins/woocommerce/
    [package] => https://downloads.w.org/plugin/woocommerce.10.4.3.zip
    [icons] => stdClass Object (
        [1x] => https://ps.w.org/woocommerce/assets/icon.svg?rev=3234504
        [svg] => https://ps.w.org/woocommerce/assets/icon.svg?rev=3234504
    )
    [banners] => stdClass Object (
        [2x] => https://ps.w.org/woocommerce/assets/banner-1544x500.png?rev=3234504
        [1x] => https://ps.w.org/woocommerce/assets/banner-772x250.png?rev=3234504
    )
    [banners_rtl] => Array ()
    [requires] =>
    [tested] => 6.9
    [requires_php] =>
    [requires_plugins] => Array ()
    [compatibility] => Array ()
    [autoupdate] => 1
    [upgrade_notice] => Version 10.4.3 contains security fixes and is highly recommended for all users.
)
```

### Key Findings

1. **Forced Update Mechanism**: WordPress uses `autoupdate => 1` flag (not `autoupdate_forced`) to force security updates even when per-plugin auto-updates are disabled.

2. **Rollback Detection**: WordPress uses these error codes in the `automatic_updates_complete` hook when rollback occurs:

    - `plugin_update_fatal_error_rollback_successful` - Update caused fatal error, rollback succeeded
    - `plugin_update_fatal_error_rollback_failed` - Update caused fatal error, rollback also failed

3. **Update Transient Storage**: WordPress caches update info in `update_plugins` site transient with properties: `last_checked`, `response`, `translations`, `no_update`, `checked`. No `autoupdate_forced` property.

### Update Flow (observed)

1. `wp_maybe_auto_update()` called
2. WordPress checks `update_plugins` transient for available updates
3. Plugin downloaded from `downloads.w.org`
4. Files extracted and installed (version becomes 10.4.3)
5. `upgrader_process_complete` hook fires → Simple History logs "Updated to 10.4.3"
6. WordPress performs loopback health check
7. Health check fails (fatal error in WooCommerce 10.4.3)
8. WordPress restores from temp backup (rollback)
9. `automatic_updates_complete` hook fires with WP_Error containing `plugin_update_fatal_error_rollback_successful`
10. Email sent: "Some plugins have failed to update"

### WordPress Rollback Code (class-wp-automatic-updater.php lines 566-619)

```php
if ( $this->has_fatal_error() ) {
    $upgrade_result = new WP_Error();
    $temp_backup = array(
        array(
            'dir'  => 'plugins',
            'slug' => $item->slug,
            'src'  => WP_PLUGIN_DIR,
        ),
    );

    $backup_restored = $upgrader->restore_temp_backup( $temp_backup );
    if ( is_wp_error( $backup_restored ) ) {
        $upgrade_result->add(
            'plugin_update_fatal_error_rollback_failed',
            sprintf( __( "The update for '%s' contained a fatal error. The previously installed version could not be restored." ), $item->slug )
        );
    } else {
        $upgrade_result->add(
            'plugin_update_fatal_error_rollback_successful',
            sprintf( __( "The update for '%s' contained a fatal error. The previously installed version has been restored." ), $item->slug )
        );
    }
}
```

### Implementation (Complete)

**Files Modified:**

`loggers/class-plugin-logger.php`:

-   Line ~108-114: Added `plugin_update_rolled_back` message type
-   Line ~213: Added `automatic_updates_complete` hook
-   Line ~1146-1183: Added `on_automatic_updates_complete()` method checking for rollback error codes

### WordPress Forced Update Mechanism (Deep Dive)

#### Does `upgrade_notice` force anything?

**No.** The `upgrade_notice` field is **display-only** - it shows in the plugins list table to inform users about the update, but has no effect on forcing updates.

#### The `autoupdate` Field

The `autoupdate` field in the WordPress.org API response is the key to forced updates:

-   When `autoupdate => 1`, WordPress updates the plugin **regardless of user settings**
-   This flag is **manually set by the WordPress.org security team** for critical security updates
-   Normal plugins do NOT have this flag - it's only for security emergencies
-   The WordPress.org team can target specific version ranges (e.g., update 7.4.x users to 7.4.3, not to 7.6.1)

#### WordPress Core Logic

From `wp-admin/includes/class-wp-automatic-updater.php` (lines 223-228):

```php
// First check: API-forced update (security team override)
$update = ! empty( $item->autoupdate );

// Second check: User-enabled auto-update (only if not already forced)
if ( ! $update && wp_is_auto_update_enabled_for_type( $type ) ) {
    $auto_updates = (array) get_site_option( "auto_update_{$type}s", array() );
    $update = in_array( $item->{$type}, $auto_updates, true );
}

// Third: disable_autoupdate flag can override (but filters still apply)
if ( ! empty( $item->disable_autoupdate ) ) {
    $update = false;
}

// Fourth: Filters can override everything
$update = apply_filters( "auto_update_{$type}", $update, $item );
```

#### Detection Logic for Simple History

To detect if an update was forced vs user-enabled:

```php
$update_plugins = get_site_transient( 'update_plugins' );
$auto_updates = get_site_option( 'auto_update_plugins', array() );

foreach ( $update_plugins->response as $plugin => $data ) {
    $api_forced = ! empty( $data->autoupdate );
    $user_enabled = in_array( $plugin, $auto_updates, true );

    if ( $api_forced && ! $user_enabled ) {
        // FORCED UPDATE: API override, user did NOT enable auto-updates
    } elseif ( $user_enabled ) {
        // USER-ENABLED: User explicitly enabled auto-updates for this plugin
    }
}
```

#### Test Site Configuration

The test site has **no plugins enabled for auto-update**:

```
auto_update_plugins = []  (empty array)
```

Yet WooCommerce updated because the API returned `autoupdate => 1`. This confirms the forced update mechanism is working.

#### Can Users Disable Forced Updates?

**Yes**, but only via code:

```php
// Disable all plugin auto-updates (including forced)
add_filter( 'auto_update_plugin', '__return_false' );

// Disable for specific plugin
add_filter( 'auto_update_plugin', function( $update, $item ) {
    if ( 'woocommerce/woocommerce.php' === $item->plugin ) {
        return false;
    }
    return $update;
}, 10, 2 );
```

#### Sources

-   [Automatic Plugin Security Updates – Make WordPress Plugins](https://make.wordpress.org/plugins/2015/03/14/plugin-automatic-security-updates/)
-   [auto*update*{$type} Hook – Developer.WordPress.org](https://developer.wordpress.org/reference/hooks/auto_plugin_update_type/)
-   [The plugin was updated automatically. Why? | WordPress.org](https://wordpress.org/support/topic/the-plugin-was-updated-automatically-why/)
-   [#57280 Security automatic updates for plugins and themes – WordPress Trac](https://core.trac.wordpress.org/ticket/57280)
-   [Store API Vulnerability Patched in WooCommerce 8.1+](https://developer.woocommerce.com/2025/12/22/store-api-vulnerability-patched-in-woocommerce-8-1/)

### UX Design Decisions

The UI was designed with input from UX review. Key decisions:

**1. Label choice: "Update method" instead of "Update type"**

-   "Update type" is ambiguous (could mean major vs minor, security vs feature)
-   "Update method" clearly indicates _how_ the update happened
-   Users reviewing logs think: "Wait, I didn't update this—how did it happen?"

**2. Value: "Security auto-update" instead of "Forced security update"**

-   Front-loads the critical word "security"
-   "Forced" has negative connotations
-   Concise and scannable in a table

**3. Hide update method for user-enabled auto-updates**

-   Users who enabled auto-updates made an explicit choice
-   The update happening automatically is _expected_ behavior
-   Only show information when it _prevents confusion_
-   Keeps logs clean and signal-rich

**4. No badge in list view**

-   Initially implemented a green "Security Update" badge
-   Removed after user feedback - added visual noise
-   The `notice` log level already provides differentiation
-   Details are available on expansion

### Historical Context

**WordPress Forced Updates Timeline:**

-   **WordPress 3.7 (2013)**: Auto-updates introduced for minor/security core releases
-   **WordPress 5.5 (2020)**: Per-plugin auto-update toggles added to admin UI
-   **WordPress 5.6 (2020)**: Per-theme auto-update toggles added
-   **WordPress 6.3 (2023)**: Rollback mechanism added for failed updates

The `autoupdate` API flag has been available since WordPress 3.7 but is rarely used. It's reserved for critical security emergencies where the WordPress.org security team needs to push updates to all sites regardless of user preferences.

**Known uses of forced updates:**

-   Various plugin security vulnerabilities over the years
-   WooCommerce security patches (e.g., 10.4.2 → 10.4.3 in January 2026)

### Why This Matters

**For site owners:**

-   Understand why a plugin updated without their action
-   Audit trail for compliance (who/what changed the site)
-   Awareness of security incidents affecting their plugins

**For agencies/developers:**

-   Client sites updated unexpectedly? Now you know why
-   Security incident response: quickly identify affected sites
-   Peace of mind: forced updates are for protection, not malice

### Status: ✅ Implemented (2026-01-14)

**Rollback Detection:**

-   ✅ Found correct error codes (`plugin_update_fatal_error_rollback_successful/failed`)
-   ✅ Updated `on_automatic_updates_complete()` to detect these error codes
-   ✅ Logs rollback events with `plugin_update_rolled_back` message

**Forced Security Update Detection:**

-   ✅ Added `plugin_update_type` context to all plugin updates (`forced_security`, `user_enabled`, `manual`)
-   ✅ Added `plugin_upgrade_notice` context field (from WordPress.org API)
-   ✅ Elevated log level to `notice` for forced security updates (vs `info` for normal updates)
-   ✅ Clean key-value table in details view showing "Update method" and "Update notice"
-   ✅ UX reviewed: Only show "Update method" for unexpected updates (forced security), hide for user-enabled auto-updates

**How it works:**
When a plugin is updated, Simple History checks:

1. Does the `update_plugins` transient have `autoupdate => 1` for this plugin? (API forced)
2. Is this plugin in the `auto_update_plugins` option? (User enabled)
3. If API forced but user didn't enable → `forced_security` (shows "Update method: Security auto-update")
4. If user enabled → `user_enabled` (no extra indicator - expected behavior)
5. Otherwise → `manual` (no indicator)

**Details View Output:**
For forced security updates:

```
| Update method | Security auto-update |
| Update notice | Version 10.4.3 contains security fixes... |
View changelog
```

For user-enabled or manual updates (only if upgrade_notice exists):

```
| Update notice | Version X.Y.Z contains... |
View changelog
```

**Context Fields Saved:**

-   `plugin_update_type` - `forced_security`, `user_enabled`, or `manual`
-   `plugin_upgrade_notice` - Human-readable message from WordPress.org (if present)

**Forced Update Detection on Update Available (2026-01-17):**

The Available Updates Logger now also captures `autoupdate` and `upgrade_notice` when plugin updates are **detected** (before installation). This gives users advance warning that a forced security update is pending.

When a plugin update is found with `autoupdate => 1`:

-   Shows **"Security auto-update"** label with "This update will be installed automatically by WordPress."
-   Shows the **Update notice** from WordPress.org in a key-value table

This helps users understand _before_ the update happens that WordPress will install it automatically, preventing confusion when they see a plugin updated despite having auto-updates disabled.

**Files Modified:**

-   `loggers/class-available-updates-logger.php`:

    -   `on_setted_update_plugins_transient()` - Captures `plugin_autoupdate` and `plugin_upgrade_notice` context
    -   `get_log_row_details_output()` - Displays forced update indicator and upgrade notice

-   `loggers/class-plugin-logger.php`:
    -   `get_plugin_update_type()` - Determines update type from transients
    -   `add_update_type_context()` - Adds `plugin_update_type` and `plugin_upgrade_notice` to event context
    -   `get_plugin_action_details_output()` - Renders key-value table in details view
    -   Uses `notice` log level for forced security updates, `info` for others

**Security:**

-   Upgrade notice is escaped with `esc_html()` before output
-   Length limited to 30 words via `wp_trim_words()` to prevent UI flooding
-   HTML stripped with `wp_strip_all_tags()` before display

**GitHub Gist:**
Example WordPress.org API response with `autoupdate => 1` documented at:
https://gist.github.com/bonny/dceab0c8582f08075919e9f760380f50

### Testing & Debugging Forced Security Updates

To test or debug the forced security update detection:

**1. Find a plugin with a known forced security update**

WooCommerce has had several forced updates (e.g., 10.4.2 → 10.4.3). Check WordPress.org announcements or plugin changelogs for security releases.

**2. Downgrade the plugin to the vulnerable version**

```bash
# Using WP-CLI (adjust for your setup)
docker compose run --rm wpcli_mariadb plugin install woocommerce --version=10.4.2 --force
```

**3. Clear the update transient**

WordPress caches update info. Clear it to force a fresh check:

```bash
docker compose run --rm wpcli_mariadb transient delete update_plugins --network
docker compose run --rm wpcli_mariadb eval 'wp_clean_plugins_cache(true);'
```

**4. Trigger the auto-update process**

```bash
docker compose run --rm wpcli_mariadb eval 'wp_maybe_auto_update();'
```

This simulates what WordPress cron does automatically (usually twice daily). WordPress will:

-   Check WordPress.org API for updates
-   Find the plugin with `autoupdate => 1` flag (security team override)
-   Apply the update even if auto-updates are disabled for that plugin

**5. Check Simple History log**

```bash
docker compose run --rm wpcli_mariadb simple-history list --count=5
```

Look for the "Security Update" badge in the output.

**6. Verify the context data**

```sql
SELECT c.key, c.value
FROM wp_simple_history_contexts c
WHERE c.history_id = [EVENT_ID]
ORDER BY c.key;
```

The `plugin_update_type` field should be `forced_security`.

**Example output (2026-01-14):**

```
ID    date                initiator  description                                                    level
9214  2026-01-14 11:01:21 WP-CLI     Updated plugin "WooCommerce" to version 10.4.3 from 10.4.2    notice

Context:
plugin_update_type = forced_security
plugin_upgrade_notice = Version 10.4.3 contains security fixes and is highly recommended for all users.
plugin_prev_version = 10.4.2
plugin_version = 10.4.3

Details HTML:
<table class="SimpleHistoryLogitem__keyValueTable">
  <tr><td>Update method</td><td>Security auto-update</td></tr>
  <tr><td>Update notice</td><td>Version 10.4.3 contains security fixes and is highly recommended for all users.</td></tr>
</table>
<p><a title="View changelog" ...>View changelog</a></p>
```

**Future improvements** (nice-to-have):

-   Differentiate message when rollback fails vs succeeds

### Related Files

-   `readme.search-performance.md` - Separate issue about slow search queries

---

## SQL Performance Optimizations (2026-01-26)

### Problem

SPX profiling revealed that sidebar stats queries were taking ~12-22ms on cache miss due to SQL that prevented index usage.

### Root Cause

Queries wrapped the `date` column in functions, preventing MySQL from using the `KEY date (date)` index:

```sql
-- Bad: Function on column = full table scan
WHERE UNIX_TIMESTAMP(date) >= 1737849600
WHERE DATE_ADD(date, INTERVAL 60 DAY) < NOW()
```

### Solution

Rewrite queries to compare the `date` column directly:

```sql
-- Good: Column compared directly = index range scan
WHERE date >= FROM_UNIXTIME(1737849600)
WHERE date < DATE_SUB(NOW(), INTERVAL 60 DAY)
```

Both are algebraically equivalent but the second form allows MySQL to use the index.

### Files Modified

| File                                             | Function                               | Change                                                |
| ------------------------------------------------ | -------------------------------------- | ----------------------------------------------------- |
| `inc/class-helpers.php:1387`                     | `get_num_events_last_n_days()`         | `UNIX_TIMESTAMP(date) >=` → `date >= FROM_UNIXTIME()` |
| `inc/class-helpers.php:1418`                     | `get_num_events_today()`               | `UNIX_TIMESTAMP(date) >=` → `date >= FROM_UNIXTIME()` |
| `inc/class-helpers.php:1497`                     | `get_num_events_per_day_last_n_days()` | `UNIX_TIMESTAMP(date) >=` → `date >= FROM_UNIXTIME()` |
| `inc/services/class-setup-purge-db-cron.php:182` | `get_purge_where_clause()`             | `DATE_ADD(date, ...) <` → `date < DATE_SUB(...)`      |
| `tests/wpunit/PurgeDBTest.php`                   | Test expectations                      | Updated to match new SQL pattern                      |

### Performance Impact

For large tables (50k+ events), these changes can improve query performance significantly:

-   **Before**: Full table scan - reads all rows
-   **After**: Index range scan - reads only matching rows

On a 1M row table querying last 7 days (~50k rows), this is potentially ~20x fewer rows read.

### Verification

```sql
EXPLAIN SELECT count(*) FROM wp_simple_history
WHERE date >= FROM_UNIXTIME(1737849600);
-- Should show type: "range" and "Using index" in Extra
```

### Not Changed

-   `class-events-stats.php` - Already uses correct pattern in WHERE clauses. The `DATE_ADD` there is only in SELECT/GROUP BY for timezone conversion, which doesn't affect index usage.
