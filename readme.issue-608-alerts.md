# Issue #608: Alerts & Notifications

**Status:** In Progress
**Size:** Large
**Labels:** Feature, Size: Large
**Branch:** `issue-608-alerts`

## Scope Clarification

This issue focuses **only on Alerts & Notifications** - selective, rule-based notifications when specific events occur.

**NOT in scope** (already implemented in Channels system):
- Log Forwarding / Channels infrastructure (done)
- Syslog channels (done in premium)
- External Database channel (done in premium)
- Datadog, Splunk channels (done in premium)
- Webhook channel (done in premium)
- File channel (done in core)

## Problem Description

Users want to be notified in real-time when **specific** events happen:

- Admin logins or failed login attempts
- Plugin/theme changes
- User registrations
- Critical errors

Unlike Log Forwarding (which streams ALL events), Alerts are **selective** - only events matching configured rules trigger notifications.

## Key Difference: Channels vs Alerts

| Aspect | Channels (done) | Alerts (this issue) |
|--------|-----------------|---------------------|
| Purpose | Archive/backup/monitoring | Real-time notification |
| Filtering | None (all events) | Rule-based (selective) |
| Volume | High | Low |
| Destinations | Log systems (Syslog, Splunk) | Notification tools (Slack, Email) |
| User need | "Store my logs externally" | "Tell me when X happens" |

## What Needs to Be Built

### 1. Alert Destinations (Premium)

Notification-focused channels based on competitor analysis and market gaps:

#### Competitor Channel Support

| Channel | WP Activity Log | Wordfence | Stream | Logtivity | Simple History |
|---------|:---------------:|:---------:|:------:|:---------:|:--------------:|
| Email | ✅ | ✅ | ✅ | ✅ | 🎯 MVP |
| Slack | ✅ | ✅ | ✅* | ✅ | 🎯 MVP |
| Discord | ❌ | ✅ | ❌ | ❌ | 🎯 MVP |
| Telegram | ❌ | ❌ | ❌ | ❌ | 🎯 MVP |
| Teams | ❌ | ❌ | ❌ | ❌ | Phase 2 |
| SMS (Twilio) | ✅ | ✅ | ❌ | ❌ | Phase 3 |
| Webhooks | ❌ | ❌ | ✅ | ✅ | ✅ Done |

#### Integration Complexity (Verified Dec 2025)

| Channel | Difficulty | Setup | Cost | Rate Limits |
|---------|:----------:|-------|------|-------------|
| **Email** | 🟢 Easy | None (wp_mail) | Free | Server limits |
| **Slack** | 🟢 Easy | User creates webhook URL | Free | 1 msg/sec |
| **Discord** | 🟢 Easy | User creates webhook URL | Free | 5 req/2 sec |
| **Telegram** | 🟢 Easy | Create bot via @BotFather | Free | 30 msg/sec |
| **Teams** | 🟡 Medium | Power Automate Workflows | Free | Varies |
| **SMS (Twilio)** | 🟡 Medium | API key + phone number | Per-message | Account-based |
| **WhatsApp** | 🔴 Hard | Business verification, template approval | Per-message | Complex |

#### Prioritized Channel List

**MVP (Must Have + Easy Wins):**
| Channel | Why | Implementation |
|---------|-----|----------------|
| **Email** | Universal, everyone has it | Via wp_mail() |
| **Slack** | Most requested, industry standard | Webhook + Block Kit |
| **Discord** | 🟢 Very easy, only Wordfence has it | Simple webhook POST |
| **Telegram** | 🟢 Very easy, popular in EU/Asia, **no competitor has it** | Bot API (free) |

**Phase 2 (Medium Effort - Unique Differentiator):**
| Channel | Why | Implementation |
|---------|-----|----------------|
| **Microsoft Teams** | Enterprise, **no competitor has it** | Power Automate Workflows* |

*Note: Teams O365 Connectors deprecated Oct 2024, full retirement end of 2025. Must use Workflows (more complex setup for users).

**Phase 3 (Enterprise/Niche):**
| Channel | Why | Implementation |
|---------|-----|----------------|
| **SMS (Twilio)** | High-urgency, direct | Twilio API |
| **Pushover** | Simple push notifications | Pushover API |
| **PagerDuty** | On-call alerting, enterprise | Events API v2 |

**Not Recommended:**
| Channel | Why Skip |
|---------|----------|
| **WhatsApp** | Requires Business API, Meta verification, template approval, per-message fees. Too complex for the value. |

**Already Done:**
- `Webhook_Channel` (premium) - covers Zapier, Make, n8n, custom endpoints

### 2. Rule Builder UI (Premium)

The backend (`Alert_Rules_Engine`, `Alert_Evaluator`, `Alert_Field_Registry`) exists but there's no UI.

**Need to build:**
- React component using React Query Builder
- Settings UI to create/edit/delete rules
- JsonLogic export to store rules
- Integration with channel settings

### 3. Alert Presets

Pre-configured rules users can enable with one click:

- **Security alerts**: Failed logins, user role changes
- **Admin actions**: Plugin/theme installs, settings changes
- **User activity**: New registrations, profile updates

### 4. "Create Alert from Event" (Nice to have)

Add action to event dropdown: "Create alert for events like this"
- Pre-fills rule builder with matching criteria
- Quick way to set up alerts

## Architecture

Alerts build on the existing Channels infrastructure:

```
Channel (base class) ← already has alert_rules support
├── File_Channel (core)
├── Webhook_Channel (premium) ← generic, already done
├── Slack_Channel (premium) ← NEW - dedicated Slack
├── Email_Channel (premium) ← NEW
├── Teams_Channel (premium) ← NEW
└── Discord_Channel (premium) ← NEW
```

The base `Channel` class already has:
- `get_alert_rules()` / `set_alert_rules()`
- `should_send_event($event_data)` - currently returns true if no rules

**Need to implement:**
- Wire up `should_send_event()` to use `Alert_Rules_Engine`
- Build UI for rule configuration
- Create notification-specific channels

## Existing Foundation

These files exist and can be leveraged:

| File | Purpose | Status |
|------|---------|--------|
| `inc/channels/class-alert-evaluator.php` | JsonLogic wrapper | ⚠️ Not tested |
| `inc/channels/class-alert-field-registry.php` | UI field definitions | ⚠️ Not tested |
| `inc/channels/class-alert-rules-engine.php` | Service facade | ⚠️ Not tested |
| `inc/libraries/JsonLogic.php` | Third-party library | ✅ (upstream tested) |
| `docs/alerts-feature-research.md` | Competitor analysis | ✅ |
| `docs/alerts-async-processing-research.md` | Performance research | ✅ |

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

## Open Questions

### Where should Alerts UI live?

Options:
1. **New tab**: Settings > Alerts (separate from Log Forwarding)
2. **Subtab**: Settings > Log Forwarding > Alerts
3. **Per-channel**: Add rule builder to each notification channel's settings

Recommendation: Option 3 - keep rules close to the channel they apply to.

### Premium vs Free?

Recommendation:
- **Free**: Show alert destinations as teasers (like current Syslog/Database)
- **Premium**: Full functionality - Slack, Email, Teams, Discord, rule builder

## Related Issues

- #573 (Log Forwarding - completed, channels infrastructure)
- #209, #114, #366 (Original alert requests)
