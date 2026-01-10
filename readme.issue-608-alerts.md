# Issue #608: Alerts & Notifications

**Status:** In Progress
**Size:** Large
**Labels:** Feature, Size: Large
**Branch:** `issue-608-alerts`

## Table of Contents

- [Review and comments from human developer](#review-and-comments-from-human-developer)
- [Scope Clarification](#scope-clarification)
- [Problem Description](#problem-description)
- [Key Difference: Channels vs Alerts](#key-difference-channels-vs-alerts)
- [What Needs to Be Built](#what-needs-to-be-built)
  - [1. Alert Destinations (Premium)](#1-alert-destinations-premium)
  - [2. Alert Rules UX (Premium)](#2-alert-rules-ux-premium)
- [Destinations Architecture](#destinations-architecture)
- [Class Architecture](#class-architecture)
- [Existing Foundation](#existing-foundation)
- [Implementation Plan](#implementation-plan)
- [Settings Page Structure](#settings-page-structure)
- [Core/Premium Code Split](#corepremium-code-split)
- [Progress Log](#progress-log)
  - [2026-01-10: Alert Performance & Code Quality](#2026-01-10-alert-performance--code-quality)
  - [2026-01-08: DRY Refactoring - Destination Senders](#2026-01-08-dry-refactoring---destination-senders)
  - [2026-01-04: Review Questions Addressed](#2026-01-04-review-questions-addressed)
  - [2026-01-02: Alert Rules UX Polish](#2026-01-02-alert-rules-ux-polish)
  - [2026-01-01: Alert Rules UI Implementation](#2026-01-01-alert-rules-ui-implementation)
  - [2025-12-30: Code Quality Fixes](#2025-12-30-code-quality-fixes)
  - [2025-12-29: Destinations UI Polish](#2025-12-29-destinations-ui-polish)
  - [Status Summary](#status-summary)
- [Related Issues](#related-issues)

## Review and comments from human developer

- Intro section for Destinations (`sh-SettingsCard sh-SettingsPage-settingsSection-wrap`) and alert rules (`sh-SettingsCard sh-SettingsPage-settingsSection-wrap`) looks different from Log forwarding intro section and also different than Failed login attempts intro section. I think we need a common layout for this that works in all scenarios!
- Research: How to implement async processing via Action Scheduler or WP Cron for production sites with high event volume.
- Destinations could benefit from a column with info about what alert rules are sending to it?
- Should we also add context to messages sent? Right now it only says "Edited profile for user abc" but it would be useful to also know that was changed, for example role changed? Just a first name change vs role change, it's very different!


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

## Progress Log

### 2026-01-02: Alert Rules UX Polish

Further UX improvements to the preset alert rules interface:

**Discoverability improvements:**
- Show all destination types even when empty (displays "None configured" placeholder)
- Added "+" quick-add links next to each destination type header
- "+" links navigate to Destinations tab with `#destination-{type}` anchor
- CSS `:target` rule highlights destination section in yellow when navigated to
- Added `scroll-margin-top: 2rem` for better scroll positioning

**Tooltip improvements:**
- Changed from native `title` attribute to CSS-based tooltips (fixed double-tooltip issue)
- Tooltips now centered with `white-space: nowrap` for single-line display
- Success status icons now show "Last successful send" tooltip (was missing before)

**Email recipients display:**
- Replaced `(5)` count with envelope icon + badge showing recipient count
- Added tooltip on hover showing actual email addresses
- Truncates to first 5 emails with "+X more" if many recipients

**Status icon positioning:**
- Changed from `margin-left: auto` (far right) to `margin-left: 6px` (inline after item name)
- Cleaner visual association between item and its status

**Commits:** `104b28f`, `8e16ed4`

### 2026-01-01: Alert Rules UI Implementation

Implemented the Quick Setup (Tier 1) preset-based alert rules UI:

**Major changes:**
- Simplified preset cards to match Destinations card styling
- Removed header checkbox - enabled state now derived from selected destinations
- Group destinations by type (Email, Slack, Discord, Telegram) with column headers
- Show email recipient count in parentheses for clarity
- Status indicators (✓/!) only shown for selected destinations (errors always visible)
- Removed visual noise (badge, blue enabled border)
- Clear tooltips: "Last successful send" and "Last error X ago: message"

**Bug fixes:**
- Fixed save bug: validate destination IDs without lowercasing (preserves UUID format)
- Fixed enabled checkbox defaulting to true when unchecked
- Fixed text domain to `simple-history-add-on` throughout

**Additional UX polish (uncommitted):**
- Added help cursor to status indicator tooltips
- Added icons (Email, Slack, Discord, Telegram) before column headers
- Increased vertical spacing between destination items
- Removed selected background color (standard WordPress checkbox pattern)

**Commits:** `5336c89`

### 2025-12-30: Code Quality Fixes

Fixed phpcs and phpstan issues in premium plugin:
- Replaced alternative syntax (`if():...endif;`) with curly braces in email destination sender
- Replaced short ternary (`?:`) with full ternary in HTTP channel trait
- Fixed `Destination_Sender` namespace import in alerts module
- Fixed variable alignment for consistency

**Commits:** `28dbca1`

### 2025-12-29: Destinations UI Polish

Improved the destinations table UX with multiple fixes:
- Fixed delete confirmation showing empty destination name
- Removed unused "Save Destinations" button
- Added destination tracking for alert send success/failure (shows last status in table)
- Improved test button feedback UX with loading states
- Prevented multiple modals from opening on double-click
- Fixed button icon alignment
- Normalized email recipients format on save
- Used WordPress table patterns for consistent look

**Commits:** `27cefc0`, `ca0ad79`, `6e1f961`, `aae2878`, `cb4804b`, `6ed3a90`, `a8b0cd6`, `ffd74dd`, `19641c5`, `69ac638`, `1d2f9f3`, `83627d8`, `c0841e8`

### Status Summary

**Completed:**
- ✅ Destinations architecture and storage
- ✅ Email destination sender
- ✅ Slack destination sender
- ✅ Discord destination sender
- ✅ Telegram destination sender
- ✅ Destinations REST API
- ✅ Destinations settings page UI
- ✅ Test button for each destination
- ✅ Send tracking (success/failure status)
- ✅ Alert presets UI (Tier 1 quick setup)
- ✅ Alert rules saving and evaluation
- ✅ WP CLI commands for destinations and rules
- ✅ Event logging when destinations/rules are saved

**Not Started:**
- ⏳ Editable presets (Tier 2 - toggle specific events)
- ⏳ Custom rules builder (Tier 3)
- ⏳ "Create alert from event" feature

## Related Issues

-   #573 (Log Forwarding - completed, channels infrastructure)
-   #209, #114, #366 (Original alert requests)


### 2026-01-04: Review Questions Addressed

**1. WP CLI Commands: ✅ Implemented**

Created `class-wp-cli-alerts-command.php` with the following commands:

```bash
# Destinations
wp simple-history alerts destinations list [--type=<type>] [--format=<format>]
wp simple-history alerts destinations get <id> [--format=<format>]
wp simple-history alerts destinations test <id>
wp simple-history alerts destinations delete <id> [--yes]

# Alert Rules
wp simple-history alerts rules list [--format=<format>]
wp simple-history alerts rules enable <preset> [--destinations=<ids>]
wp simple-history alerts rules disable <preset>
```

**2. Destination Descriptions: ✅ Updated**

Changed from redundant descriptions to more helpful copy:

| Type     | Before                           | After                                   |
|----------|----------------------------------|-----------------------------------------|
| Email    | "Send alerts to email addresses" | "Configure email recipients for alerts" |
| Slack    | "Post alerts to Slack channels"  | "Configure Slack webhooks for alerts"   |
| Discord  | "Send alerts to Discord channels"| "Configure Discord webhooks for alerts" |
| Telegram | "Send alerts via Telegram bot"   | "Configure Telegram bot for alerts"     |

**3. Success/Fail Results Storage: Already documented**

Stored in `tracking` key within each destination in the `simple_history_alert_destinations` wp_option:

```php
$tracking = [
    'last_success'  => 0,        // Unix timestamp
    'last_error'    => [
        'message' => '...',
        'code'    => 0,          // HTTP status code
        'time'    => 0,          // Unix timestamp
    ],
    'success_count' => 0,
    'error_count'   => 0,
];
```

**4. Event Logging on Save: ✅ Implemented**

Created `class-alerts-logger.php` that logs:

- **Destinations:**
  - "Added alert destination "{name}" ({type})"
  - "Updated alert destination "{name}""
  - "Deleted alert destination "{name}" ({type})"

- **Alert Rules:**
  - "Enabled alert rule "{name}""
  - "Disabled alert rule "{name}""
  - "Updated alert rule "{name}""

Hooks added in `class-wp-rest-destinations-controller.php` and `class-alerts-module.php`:
- `simple_history/alerts/destination_created`
- `simple_history/alerts/destination_updated`
- `simple_history/alerts/destination_deleted`
- `simple_history/alerts/rules_saved`

**Commits:** (pending)

### 2026-01-08: DRY Refactoring - Destination Senders

Major code deduplication effort across destination senders and core plugin:

**Core Plugin Changes:**

Added to `Log_Levels` class (`inc/class-log-levels.php`):
- `get_level_color($level)` - Returns hex color codes for log levels
- `get_level_emoji($level)` - Returns emoji characters for log levels

**Premium Plugin Changes:**

Refactored `Destination_Sender` base class to use core methods:
- `get_level_color()` → delegates to `Log_Levels::get_level_color()`
- `get_level_label()` → delegates to `Log_Levels::get_log_level_translated()`
- `get_level_emoji()` → delegates to `Log_Levels::get_level_emoji()`
- Added `get_initiator_text()` helper → delegates to `Log_Initiators::get_initiator_text_from_row()`
- `format_message()` → now uses `Helpers::interpolate()`
- Moved `get_webhook_url()` from Slack/Discord to base class

Refactored individual destination senders:
- Slack, Discord, Telegram now use shared `get_initiator_text()` helper
- Removed duplicate `Log_Initiators` imports
- Telegram: Replaced custom `escape_html()` with WordPress `esc_html()`

Added to `Helpers` class (`inc/class-helpers.php`):
- `sanitize_error_message($message, $max_length)` - Strips HTML, decodes entities, normalizes whitespace, truncates

Refactored tracking traits to use core method:
- `Channel_Error_Tracking_Trait::sanitize_error_message()` → delegates to `Helpers::sanitize_error_message()` with 500 char limit
- `Destination_Tracking_Trait::sanitize_error_message()` → delegates to `Helpers::sanitize_error_message()` with 300 char limit

**Results:**
- ~150+ lines of duplicate code removed
- Single source of truth for level colors, emojis, labels, and error sanitization
- Consistent initiator formatting across all destinations
- Better alignment with WordPress coding standards

Removed pure wrapper methods from `Destination_Sender`:
- `get_level_color()` - callers now use `Log_Levels::get_level_color()` directly
- `get_level_emoji()` - callers now use `Log_Levels::get_level_emoji()` directly
- Kept `get_level_label()` - provides case normalization (`ucfirst(strtolower())`)

**Commits (core):** `c71e1ede`, `e8306da5`
**Commits (premium):** `d220df7`, `a53e841`, `75fb4e8`, `14d7902`, `67bab5a`, `444bd60`

### 2026-01-10: Alert Performance & Code Quality

**Bug Fix: Destination tracking reset**

Fixed a bug where destination tracking (last_success, error_count, etc.) was being reset to "Never used" after working correctly. Root cause: `sanitize_destinations()` callback (registered via `register_setting()`) was stripping the `tracking` key when called during `update_option()` in admin context.

**Commits (premium):** `aa2c061`

**Performance: Batched option reads**

Optimized `process_logged_event()` to use `get_options()` (WordPress 6.4+) for loading all alert options in a single DB query instead of three separate queries:

| Before | After |
|--------|-------|
| 3 `get_option()` calls | 1 `get_options()` call |
| 3 DB queries per event | 1 DB query per event |

Falls back to individual calls on WP < 6.4 for backward compatibility.

**Commits (premium):** `e59be49`

**Cleanup: Removed unused `_version` metadata**

Removed schema versioning system that was never used (all versions were 1, no migration code existed):
- Removed VERSION constants
- Removed `_version` key handling in save/read/sanitize functions
- ~53 lines removed with no functional change

**Commits (premium):** `e2ea292`

**Refactoring: Readable code improvements**

Extracted methods from `process_logged_event()` to make it read like documentation:

```php
// Before: ~50 lines with inline logic
// After: 18 lines that read like a story
public function process_logged_event( $context, $data, $logger ) {
    [ $destinations, $preset_settings, $custom_rules ] = $this->get_alert_options();

    if ( empty( $destinations ) ) {
        return;
    }

    $enabled_rules = $this->get_enabled_rules( $preset_settings, $custom_rules );

    if ( empty( $enabled_rules ) ) {
        return;
    }

    foreach ( $enabled_rules as $rule ) {
        if ( $this->rule_matches_event( $rule, $context, $data ) ) {
            $this->send_alerts( $rule, $context, $data, $destinations );
        }
    }
}
```

New extracted methods:
- `get_alert_options()` - Batched option loading with WP 6.4 compatibility
- `get_enabled_rules()` - Builds list of enabled rules from presets and custom rules

Uses PHP 7.1+ array destructuring for clean multiple return values.

**Commits (premium):** `d347330`, `30c1e38`

**Documentation: Added 'Readable Code' design principle**

Added new section to code quality guidelines (`.claude/skills/code-quality/SKILL.md`) explaining that code should read like well-written prose, with techniques:
1. Extract well-named methods
2. Use array destructuring
3. Structure methods as a story
4. Name methods for "what" not "how"

**Commits (core):** `7f8199f4`
