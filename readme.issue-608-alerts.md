# Issue #608: Alerts & Notifications

**Status:** In Progress
**Size:** Large
**Labels:** Feature, Size: Large
**Branch:** `issue-608-alerts`

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

| File                                          | Purpose              | Status               |
| --------------------------------------------- | -------------------- | -------------------- |
| `inc/channels/class-alert-evaluator.php`      | JsonLogic wrapper    | ⚠️ Not tested        |
| `inc/channels/class-alert-field-registry.php` | UI field definitions | ⚠️ Not tested        |
| `inc/channels/class-alert-rules-engine.php`   | Service facade       | ⚠️ Not tested        |
| `inc/libraries/JsonLogic.php`                 | Third-party library  | ✅ (upstream tested) |
| `docs/alerts-feature-research.md`             | Competitor analysis  | ✅                   |
| `docs/alerts-async-processing-research.md`    | Performance research | ✅                   |

**Note:** The Alert_Rules_Engine and related classes were created as foundation but have no test coverage yet. Tests should be written before relying on this code.

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

| Concern          | What it answers            | Example                          |
| ---------------- | -------------------------- | -------------------------------- |
| **Destinations** | "Where do alerts go?"      | Slack #security, admin@email.com |
| **Rules**        | "What triggers an alert?"  | Failed logins, plugin changes    |
| **Behavior**     | "How do alerts behave?"    | Rate limits, digest mode         |

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

| Log Forwarding              | Alerts                             |
| --------------------------- | ---------------------------------- |
| All events → destination    | Only matching events → destination |
| Archive/backup purpose      | Real-time notification purpose     |
| Technical users             | All users                          |
| "Store my logs externally"  | "Tell me when X happens"           |

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
- **Core plugin**: Only teaser UI, no alert functional classes
- **Premium plugin**: All functional alert code

### What Goes Where

| Component                          | Location     | Rationale              |
| ---------------------------------- | ------------ | ---------------------- |
| Alerts settings tab (teaser only)  | **Core**     | Shows upgrade path     |
| Destination classes                | **Premium**  | Functional code        |
| Alert rule classes                 | **Premium**  | Functional code        |
| Rule evaluation engine             | **Premium**  | Functional code        |
| Settings page with real forms      | **Premium**  | Functional code        |
| Hooks for premium to register      | **Core**     | Extension point        |

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

## Related Issues

-   #573 (Log Forwarding - completed, channels infrastructure)
-   #209, #114, #366 (Original alert requests)
