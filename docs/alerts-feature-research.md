# Alerts & Notifications Feature Research

**Research Date:** December 2025
**Purpose:** Design a compelling premium alerts feature that drives enterprise upgrades
**Branch:** issue-573-log-forwards-destinations

## Executive Summary

After researching competitors (WP Activity Log, Wordfence, Sucuri), enterprise monitoring tools (Datadog, PagerDuty, Splunk), and industry best practices, here are the key findings and recommendations for Simple History's alerts feature.

**Bottom line:** The alerts feature should be a **premium-only feature** that provides real-time notifications via multiple channels. The MVP should focus on **Email, Slack, and Webhooks**, with pre-built alert templates to make setup effortless.

---

## Competitor Analysis

### WP Activity Log (Main Competitor)

**Pricing:** $89/year (starter) to $139/year (enterprise)

**Alert Features (Premium Only):**
- Email notifications
- SMS via Twilio
- Slack integration
- Pre-configured alert categories:
  - WordPress notifications (themes, plugins, suspicious activity)
  - Users notifications (profiles, login activity)
  - Content notifications
  - WooCommerce notifications
- Custom notification builder with:
  - Multiple trigger rules (AND/OR logic)
  - User role filtering
  - Source IP filtering
  - Logger/event type selection
  - Grouped rules (nested conditions)

**Strengths:**
- Comprehensive rule builder
- Multiple notification channels
- Pre-built templates

**Weaknesses:**
- Complex setup for new users
- No Discord or Teams support
- No webhook option (limiting flexibility)
- UI feels dated

### Wordfence Security

**Alert Features:**
- Highly configurable email alerts
- SMS and Slack notifications
- **Discord integration** (recently added)
- Severity level options
- Daily digest option
- Wordfence Central for multi-site management

**Standout Feature:** "Reduce alert fatigue" focus - helps users tune signals

### Sucuri Security

**Alert Features:**
- Complete alert management system
- Customizable email alerts per event type
- Rate limiting (alerts per hour)
- Brute force attack settings
- Custom email subjects

### Stream (by XWP)

**Active Installations:** 90,000+
**Maintainer:** XWP (WordPress.com VIP partner)

**Alert Features:**
- Email alerts configuration
- Webhooks for integrations (Slack, IFTTT)
- Real-time notifications
- Live updates in Screen Options
- Entry highlighting for suspicious activity

**Strengths:**
- Open source, well-maintained
- Good third-party plugin tracking (WooCommerce, Yoast, Gravity Forms)
- Exclusion rules for filtering records

**Weaknesses:**
- Email notification info "not always sufficient and only in parts customizable" (user feedback)
- No dedicated mobile notifications
- Basic alert configuration

### Logtivity (SaaS)

**Model:** Cloud-hosted SaaS (requires paid account)
**URL:** [logtivity.io](https://logtivity.io/)

**Alert Features:**
- **Email** notifications
- **Slack** integration with channel selection
- **Webhooks** for custom integrations
- Global alerts (configure once for 100+ sites)
- Flexible alert rules

**Unique Features:**
- **Error log monitoring** with instant alerts
- Activity charts and data visualization
- White Label Mode for agencies
- Extended retention (up to 90 days)
- No database bloat (cloud storage)

**Use Cases Highlighted:**
- Writers logging in → Slack notifications
- Plugin/theme auto-updates → Email alerts
- eCommerce subscription changes → Custom notifications

**Opportunity:** Logtivity requires cloud hosting. Simple History can offer **self-hosted alerts** as alternative.

### Stalkfish (SaaS)

**Model:** Cloud-hosted SaaS with free tier
**URL:** [stalkfish.com](https://stalkfish.com/)

**Alert Features:**
- **Email** notifications
- **Slack** integration with app home dashboard
- Custom alerts based on activities and errors
- Mission-critical event alerts

**Unique Features:**
- Combined activity log + error monitoring
- Shared links (share errors publicly without site access)
- Multi-site monitoring from single dashboard
- No setup required ("automagically" connects)
- Slack app home with recent activity overview

**Opportunity:** Similar to Logtivity - cloud-only. Simple History can offer on-premise alternative.

### Activity Track (activitytrack.ai)

**Model:** Freemium (free + PRO)
**URL:** [activitytrack.ai](https://activitytrack.ai/)

**Alert Features:**
- **Email** notifications
- **Slack** integration
- Custom notifications by:
  - User roles
  - Specific actions
  - IP ranges
  - Threshold events (e.g., excessive login attempts)

**Unique Features (PRO):**
- **AI-Driven Summaries** - Natural language activity overviews
- **Anomaly Detection** - Flags unusual behavior automatically
- **VPN & Proxy Detection** - Flags traffic from VPNs/TOR
- **IP Anonymization** - GDPR/CCPA compliance

**Standout:** AI summaries and anomaly detection are innovative differentiators.

**Opportunity:** Consider AI-powered features for future premium tiers.

### Activity Log Pro

**URL:** [WordPress.org](https://wordpress.org/plugins/activity-log-pro/)

**Alert Features:** ❌ **None** - No real-time alerts or notifications

**Focus:**
- Logging and audit trails only
- Historical tracking and analysis
- WooCommerce integration (premium)
- Yoast SEO integration (premium)
- JSON Feed Export for SIEM integration

**Opportunity:** This plugin lacks alerts entirely. Clear differentiation opportunity.

### WP Admin Audit

**URL:** [wpadminaudit.com](https://wpadminaudit.com/)

**Alert Features (Premium):**
- **Email** notifications to:
  - User groups (e.g., all administrators)
  - Individual WordPress users
  - Specific email addresses
- **Logsnag** integration (third-party notification service)
- **Better Stack** (formerly Logtail) integration

**Configuration:**
- Filter by event types
- Filter by severity levels (critical, high, etc.)

**Additional Premium Features:**
- CSV Export
- Offsite Archive/Replication to external logging providers
- Security Policies (enforce password changes)

**Opportunity:** Limited notification channels (no Slack, Discord, webhooks).

### WP-Umbrella

**Model:** SaaS for site management
**URL:** [wp-umbrella.com](https://wp-umbrella.com/)

**Focus:** Site management, backups, uptime monitoring (not activity logging)

**Alert Features:**
- **Email** notifications
- **Slack** notifications
- Uptime monitoring alerts (2-30 minute intervals)
- SSL and security vulnerability alerts
- PHP error alerts

**Note:** WP-Umbrella does **not** have built-in activity logging. They recommend using WP Activity Log alongside their service.

**Opportunity:** Different market segment - site management vs. activity logging.

### Users Insights

**URL:** [usersinsights.com](https://usersinsights.com/)

**Focus:** User management and analytics (not activity logging/alerts)

**Activity Features:**
- User session tracking
- Last seen reports
- Activity detection (logins, page views)
- User profile activity lists

**Alert Features:** ❌ **None** - No real-time notifications

**Integrations:** WooCommerce, BuddyPress, bbPress, LearnDash

**Opportunity:** Different focus - user analytics vs. security/compliance logging.

---

## Competitor Summary Matrix

| Plugin | Email | Slack | Webhook | Discord | Teams | SMS | AI Features | Model |
|--------|:-----:|:-----:|:-------:|:-------:|:-----:|:---:|:-----------:|-------|
| **WP Activity Log** | ✅ | ✅ | ❌ | ❌ | ❌ | ✅ | ❌ | Premium |
| **Wordfence** | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ | ❌ | Freemium |
| **Stream** | ✅ | ✅* | ✅* | ❌ | ❌ | ❌ | ❌ | Free |
| **Logtivity** | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | SaaS |
| **Stalkfish** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | SaaS |
| **Activity Track** | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ✅ | Freemium |
| **WP Admin Audit** | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | Premium |
| **Activity Log Pro** | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | Premium |
| **Simple History** | 🎯 | 🎯 | 🎯 | 🎯 | 🎯 | 🎯 | 🔮 | Premium |

*Stream uses webhooks which can connect to Slack/IFTTT

**🎯 = Proposed for Simple History Premium**
**🔮 = Future consideration**

### Key Gaps in Market (Opportunities)

1. **No single plugin offers all channels** - Simple History can be first with Email + Slack + Webhooks + Discord + Teams
2. **Webhooks are rare** - Only Stream and Logtivity offer them; huge differentiator
3. **Teams support is missing everywhere** - Enterprise gap
4. **AI features are new** - Activity Track is pioneering; future opportunity
5. **Self-hosted vs SaaS** - Logtivity/Stalkfish require cloud; Simple History is self-hosted (privacy advantage)

---

## Enterprise Monitoring Best Practices

### From Datadog/PagerDuty Integration

1. **Focus on Meaningful Indicators**: Not all events need alerts. Help users identify what matters.

2. **Use Notification Rules**: Automate routing based on event properties (tags, severity, user role).

3. **Automatic Resolution**: Bidirectional sync - auto-resolve when conditions return to normal.

4. **Event Orchestration**: Route events to different destinations based on payload content.

### From PagerDuty Best Practices

1. **Escalation Policies**: Automatically escalate unresolved incidents.

2. **On-Call Scheduling**: Ensure 24/7 coverage with rotation schedules.

3. **Reduce Alert Fatigue**: Use severity levels and downtimes to reduce noise.

4. **Automate Incident Creation**: Seamless flow from monitoring to incident management.

---

## Recommended Alert Channels

### Tier 1: MVP (Must Have)

| Channel | Priority | Why |
|---------|----------|-----|
| **Email** | P0 | Universal, everyone has it, no dependencies |
| **Slack** | P0 | Most requested by teams, industry standard |
| **Webhooks** | P0 | Covers everything else (Zapier, n8n, custom) |

### Tier 2: High Value (Phase 2)

| Channel | Priority | Why |
|---------|----------|-----|
| **Microsoft Teams** | P1 | Enterprise standard, many corporate users |
| **Discord** | P1 | Growing popularity, dev/gaming communities |
| **Telegram** | P2 | Popular in certain regions, bot-friendly |

### Tier 3: Enterprise (Phase 3)

| Channel | Priority | Why |
|---------|----------|-----|
| **PagerDuty** | P2 | On-call alerting for mission-critical sites |
| **SMS (Twilio)** | P2 | Direct, high-urgency notifications |
| **Pushover** | P3 | Simple push notifications |

**Note:** Microsoft Teams incoming webhooks are being retired. New integration should use the Workflows app approach (available until December 2025+).

---

## Alert Rules Design

### Recommended Approach: Pre-built Templates + Custom Rules

**Start simple, add complexity as premium feature.**

#### Free Tier: No Alerts
- View logs in admin only
- Encourages upgrade

#### Premium MVP: Template-Based Alerts

**Pre-built Alert Templates:**

1. **Security Alerts**
   - Failed login attempts (configurable threshold)
   - Successful admin login from new IP
   - User role changes (especially to administrator)
   - User created/deleted
   - Password changes

2. **Content Alerts**
   - Post/page published
   - Post/page deleted
   - Media uploaded/deleted

3. **System Alerts**
   - Plugin activated/deactivated
   - Plugin installed/updated/deleted
   - Theme changed
   - WordPress core updated
   - Settings changed

4. **WooCommerce Alerts** (if WooCommerce active)
   - Order failed
   - Product out of stock
   - High-value order placed

**User Flow:**
```
1. Enable Alert Template → "Security Alerts"
2. Choose Destinations → ☑ Slack ☑ Email
3. Configure → Slack channel, Email recipients
4. Done!
```

#### Premium Advanced: Custom Rule Builder

For power users who need more control:

**Rule Conditions:**
| Condition | Example |
|-----------|---------|
| Logger type | "User", "Plugin", "Post" |
| Event level | "Error", "Warning", "Info" |
| User role | "Administrator", "Editor" |
| User (specific) | "john@example.com" |
| User (exclude) | "Not bot@example.com" |
| Keyword | Message contains "failed" |
| IP address | From/not from specific IP |

**Rule Logic:**
- Simple: Single condition (Logger = User AND Level = Error)
- Grouped: AND/OR combinations
- See "Rules Storage Format" section below for implementation details

### Number of Rules

**Recommendation:** Start with 5 pre-built template categories, allow unlimited custom rules in premium.

**Why?**
- Templates reduce setup friction
- Cover 80% of use cases
- Custom rules for power users
- No artificial limits = premium value

---

## Rate Limiting Strategy

### Per-Channel Limits

| Channel | Rate Limit | Strategy |
|---------|------------|----------|
| Slack | 1 msg/sec | Queue + batch |
| Discord | 5 msg/sec | Queue |
| Email | ~1/min per recipient | Digest option |
| Webhook | No limit | User's problem |
| SMS | Per Twilio limits | Cost-based limiting |

### Recommended Features

1. **Per-Alert Throttling**: "Don't send more than X alerts per hour for this rule"
2. **Daily Digest Option**: Batch non-critical alerts into daily summary
3. **Cooldown Period**: "Wait 5 minutes before alerting again for same event type"
4. **Smart Deduplication**: Don't alert twice for the same event

---

## Error Handling Strategy

### Retry Policy

```
Attempt 1: Immediate
Attempt 2: After 100ms
Attempt 3: After 500ms
Attempt 4: After 2 seconds
Attempt 5: After 10 seconds
```

### Auto-Disable Policy

- **10 consecutive failures**: Auto-disable integration
- **Show admin notice**: "Slack integration disabled due to repeated failures"
- **Log integration errors**: As Simple History events (meta!)
- **Settings UI**: Show last error message, failure count

### Fallback Options

- Optional: "If Slack fails, send to Email" backup
- Always log alert attempts to file (for debugging)

---

## UI/UX Recommendations

### Settings Page Structure

```
Simple History → Settings
├── General
├── Log Destinations (existing)
└── Alerts & Notifications (new)
```

### Alerts Tab Design

```
┌─────────────────────────────────────────────────────────┐
│ Alerts & Notifications                                   │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ ┌─ Connected Channels ─────────────────────────────────┐│
│ │ ✅ Slack - #security-alerts        [Configure]       ││
│ │ ✅ Email - admin@example.com       [Configure]       ││
│ │ ➕ Add Channel (Webhook, Discord, Teams...)          ││
│ └──────────────────────────────────────────────────────┘│
│                                                          │
│ ┌─ Active Alerts ──────────────────────────────────────┐│
│ │                                                       ││
│ │ 🔴 Security Alerts                         ✅ ON     ││
│ │    Failed logins, role changes, new admin login      ││
│ │    → Slack, Email                    [Edit][Delete]  ││
│ │                                                       ││
│ │ 🟡 Content Changes                         ✅ ON     ││
│ │    Post published, deleted                           ││
│ │    → Slack                           [Edit][Delete]  ││
│ │                                                       ││
│ │ ➕ Create New Alert                                   ││
│ │    [Use Template ▾] or [Custom Rule]                 ││
│ │                                                       ││
│ └──────────────────────────────────────────────────────┘│
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### Alert Creation Flow

**Option A: Template (Easy)**
```
1. Click "Use Template"
2. Select template: "Security Alerts"
3. Choose channels: ☑ Slack ☑ Email
4. Save → Done!
```

**Option B: Custom Rule (Advanced)**
```
1. Click "Custom Rule"
2. Name: "Failed Admin Logins"
3. Conditions:
   - Logger = User
   - Message contains "failed"
   - User role = Administrator
4. Choose channels
5. Options: Throttle (max 5/hour), Cooldown (5 min)
6. Save
```

### Compelling Premium Teasers (Free Version)

Show grayed-out/disabled alerts section with:
```
┌─────────────────────────────────────────────────────────┐
│ 🔔 Alerts & Notifications                    ⭐ Premium │
├─────────────────────────────────────────────────────────┤
│                                                          │
│ Get instant notifications when important events happen:  │
│                                                          │
│ • 📧 Email alerts for security events                    │
│ • 💬 Slack notifications to your team                    │
│ • 🔗 Webhooks to connect with any service                │
│ • 📱 Microsoft Teams, Discord, and more                  │
│                                                          │
│ Pre-built templates for:                                 │
│ ✓ Security (failed logins, role changes)                │
│ ✓ Content (published, deleted)                          │
│ ✓ System (plugins, updates)                             │
│ ✓ WooCommerce (orders, inventory)                       │
│                                                          │
│        [🚀 Upgrade to Premium]                           │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## Technical Implementation

### Recommended Architecture

```
Alert_Integration (base class)
├── Email_Alert_Channel
├── Slack_Alert_Channel
├── Discord_Alert_Channel
├── Teams_Alert_Channel
├── Webhook_Alert_Channel
└── SMS_Alert_Channel (Twilio)
```

### Event Flow

```
1. Event logged → simple_history/log/inserted
2. Alert_Rules_Engine evaluates all active rules
3. For each matching rule:
   a. Check throttle/cooldown
   b. Queue alert for each destination
4. Alert_Queue_Processor (cron or immediate):
   a. Send to destination
   b. Handle rate limits
   c. Retry on failure
   d. Log result
```

### Rule Storage (JsonLogic)

```json
{
  "name": "Failed Admin Logins",
  "enabled": true,
  "rule": {
    "and": [
      {"==": [{"var": "logger"}, "SimpleUserLogger"]},
      {"in": ["failed", {"var": "message"}]},
      {"==": [{"var": "context.user_role"}, "administrator"]}
    ]
  },
  "channels": ["slack", "email"],
  "options": {
    "throttle_per_hour": 5,
    "cooldown_minutes": 5
  }
}
```

### Queue Strategy

**For rate-limited channels (Slack, Discord):**
1. Store alerts in transient/option
2. Process via wp_schedule_single_event
3. Respect rate limits with delays
4. Batch if multiple events in queue

**For immediate channels (Email, Webhook):**
1. Send immediately on event
2. Retry on failure
3. No queuing needed

---

## Rules Storage Format Research

### Requirements

1. **Show in GUI** - Users must be able to view and modify rules
2. **Store in database** - Rules must persist in WordPress options/database
3. **Evaluate in PHP** - Rules must be evaluated server-side when events occur
4. **Preview in JS** - Optional: Show matching events in real-time (nice-to-have)

### Format Comparison

#### Option 1: JsonLogic

**Format:**
```json
{
  "and": [
    {"==": [{"var": "logger"}, "SimpleUserLogger"]},
    {"in": ["failed", {"var": "message"}]},
    {"==": [{"var": "context.user_role"}, "administrator"]}
  ]
}
```

| Aspect | Rating | Notes |
|--------|--------|-------|
| **PHP Evaluation** | Excellent | `jwadhams/json-logic-php` library |
| **JS Evaluation** | Excellent | `json-logic-js` library |
| **Storage** | Good | JSON string in wp_options |
| **UI Builder** | Moderate | React Query Builder can export to JsonLogic |
| **Readability** | Poor | Nested structure hard for humans |
| **Debugging** | Moderate | Can be "debug everywhere" scenario |
| **Flexibility** | Excellent | Any logic expressible |
| **Cross-platform** | Excellent | Same format in JS, PHP, Python |

**PHP Usage:**
```php
// Install: composer require jwadhams/json-logic-php
$rule = json_decode($stored_rule, true);
$event_data = [
    'logger' => 'SimpleUserLogger',
    'message' => 'User login failed',
    'context' => ['user_role' => 'administrator']
];
$matches = JWadhams\JsonLogic::apply($rule, $event_data);
```

#### Option 2: React Query Builder Native Format

**Format:**
```json
{
  "combinator": "and",
  "rules": [
    {"field": "logger", "operator": "=", "value": "SimpleUserLogger"},
    {"field": "message", "operator": "contains", "value": "failed"},
    {"field": "context.user_role", "operator": "=", "value": "administrator"}
  ]
}
```

| Aspect | Rating | Notes |
|--------|--------|-------|
| **PHP Evaluation** | Custom | Need to write own evaluator |
| **JS Evaluation** | Native | Direct use in React Query Builder |
| **Storage** | Good | Use `json_without_ids` format |
| **UI Builder** | Excellent | Native format, no conversion |
| **Readability** | Good | Human-readable structure |
| **Debugging** | Good | Easy to understand |
| **Flexibility** | Good | Supports nested groups |
| **Cross-platform** | Limited | Need custom PHP evaluator |

#### Option 3: Simple Custom Format

**Format:**
```json
{
  "operator": "AND",
  "conditions": [
    {"type": "logger", "value": ["SimpleUserLogger"]},
    {"type": "level", "value": ["warning", "error"]},
    {"type": "keyword", "field": "message", "value": "failed"},
    {"type": "user_role", "value": ["administrator"]}
  ]
}
```

| Aspect | Rating | Notes |
|--------|--------|-------|
| **PHP Evaluation** | Excellent | Maps to existing `Alert_Rules_Engine` |
| **JS Evaluation** | Custom | Need JS evaluator for preview |
| **Storage** | Good | JSON string in wp_options |
| **UI Builder** | Custom | Simple dropdowns/checkboxes |
| **Readability** | Excellent | Domain-specific, very clear |
| **Debugging** | Excellent | Easy to understand |
| **Flexibility** | Limited | Only predefined condition types |
| **Cross-platform** | None | Proprietary format |

### Recommendation: Hybrid Approach

**Use Simple Custom Format for basic rules + JsonLogic for advanced rules.**

#### Why Hybrid?

1. **Simple Custom** covers 80% of use cases:
   - Filter by logger type
   - Filter by severity level
   - Filter by user/role
   - Filter by keyword

2. **JsonLogic** enables advanced scenarios:
   - Complex nested conditions
   - Custom field matching
   - Future-proof extensibility

3. **Maps to existing architecture:**
   - Simple rules use `Alert_Rules_Engine` (already built)
   - Advanced rules use `json-logic-php` library

### Proposed Storage Schema

```php
// Stored in wp_options as JSON
$alert_config = [
    'id' => 'alert_123',
    'name' => 'Failed Admin Logins',
    'enabled' => true,
    'channels' => ['slack', 'email'],

    // Simple rules (free/basic) - uses existing Alert_Rules_Engine
    'simple_rules' => [
        'operator' => 'AND',
        'conditions' => [
            ['type' => 'logger', 'value' => ['SimpleUserLogger']],
            ['type' => 'keyword', 'field' => 'message', 'value' => 'failed'],
            ['type' => 'user_role', 'value' => ['administrator']],
        ],
    ],

    // Advanced rules - JsonLogic (premium feature)
    'jsonlogic_rule' => null, // Or JsonLogic object

    // Alert options
    'options' => [
        'throttle_per_hour' => 5,
        'cooldown_minutes' => 5,
    ],
];

// Store multiple alerts
update_option('simple_history_alerts', $alerts_array, false);
```

### Evaluation Flow

```php
public function should_send_alert( $alert, $event_data ) {
    // Check JsonLogic first (premium advanced rules)
    if ( ! empty( $alert['jsonlogic_rule'] ) ) {
        return \JWadhams\JsonLogic::apply( $alert['jsonlogic_rule'], $event_data );
    }

    // Fall back to simple rules
    if ( ! empty( $alert['simple_rules'] ) ) {
        return $this->rules_engine->evaluate_rules(
            $alert['simple_rules']['conditions'],
            $event_data,
            $alert['simple_rules']['operator']
        );
    }

    // No rules = send all events
    return true;
}
```

### UI Strategy

| Tier | UI Component | Storage Format |
|------|--------------|----------------|
| **Basic (Free teaser)** | Disabled preview | N/A |
| **Standard (Premium)** | Checkboxes + dropdowns | Simple custom format |
| **Advanced (Premium)** | React Query Builder | JsonLogic |

### Basic Rules UI Mockup

```
┌─────────────────────────────────────────────────────────────┐
│ Alert Conditions                                             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ Match: [All conditions ▾]  (AND/OR toggle)                  │
│                                                              │
│ ┌─ Condition 1 ──────────────────────────────────────────┐  │
│ │ Type: [Logger ▾]                                        │  │
│ │ Value: ☑ User  ☑ Plugin  ☐ Post  ☐ Media  ☐ Options   │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                              │
│ ┌─ Condition 2 ──────────────────────────────────────────┐  │
│ │ Type: [Severity ▾]                                      │  │
│ │ Value: ☐ Debug  ☐ Info  ☑ Warning  ☑ Error  ☑ Critical│  │
│ └────────────────────────────────────────────────────────┘  │
│                                                              │
│ ┌─ Condition 3 ──────────────────────────────────────────┐  │
│ │ Type: [Message contains ▾]                              │  │
│ │ Value: [failed_________________________]                │  │
│ └────────────────────────────────────────────────────────┘  │
│                                                              │
│ [+ Add Condition]                                            │
│                                                              │
│ ─────────────────────────────────────────────────────────── │
│ ☐ Enable advanced mode (JsonLogic query builder)            │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Implementation Phases

| Phase | Rules Feature | UI |
|-------|--------------|-----|
| **Phase 1** | Simple rules only | Checkboxes/dropdowns |
| **Phase 2** | Add JsonLogic support | Toggle for advanced mode |
| **Phase 3** | Full query builder | React Query Builder integration |

### Library Dependencies

**PHP (for JsonLogic):**
```bash
composer require jwadhams/json-logic-php
```

**JavaScript (for React Query Builder + JsonLogic export):**
```bash
npm install react-querybuilder @react-querybuilder/formatQuery
```

### References

- [JsonLogic.com](https://jsonlogic.com/) - Format specification
- [json-logic-php](https://github.com/jwadhams/json-logic-php) - PHP implementation
- [json-logic-js](https://github.com/jwadhams/json-logic-js) - JavaScript implementation
- [React Query Builder](https://react-querybuilder.js.org/) - UI component
- [React Query Builder Export](https://react-querybuilder.js.org/docs/utils/export) - Export formats including JsonLogic
- [React Query Builder Import](https://react-querybuilder.js.org/docs/utils/import) - Import/parse stored rules

---

## Differentiation from WP Activity Log

| Feature | WP Activity Log | Simple History (Proposed) |
|---------|-----------------|---------------------------|
| Alert channels | Email, SMS, Slack | Email, Slack, **Webhooks, Discord, Teams** |
| Pre-built templates | Yes (categories) | Yes (better organized) |
| Custom rules | Yes (complex) | Yes (**simpler UI**) |
| Webhook support | ❌ No | **✅ Yes (covers everything)** |
| Discord | ❌ No | **✅ Yes** |
| Teams | ❌ No | **✅ Yes** |
| Rate limiting | Limited | **Built-in per channel** |
| Alert fatigue controls | Limited | **Throttle, cooldown, digest** |
| Free tier alerts | ❌ None | ❌ None (but better teaser) |

### Key Differentiators

1. **Webhooks**: Universal connector that covers Zapier, n8n, custom integrations
2. **Modern channels**: Discord, Teams in addition to Slack
3. **Better UX**: Simpler rule builder, better templates
4. **Alert fatigue controls**: Built-in throttling and digest options

---

## Pricing Strategy Considerations

### WP Activity Log Pricing Reference
- Starter: $89/year
- Professional: $129/year
- Business: $149/year
- Enterprise: $139/year (includes external DB, SIEM)

### Recommendation for Simple History Premium

**Alerts should be a core premium feature**, not a separate add-on:

- Include Email + Slack + Webhooks in base premium
- Include Discord + Teams in premium
- Include PagerDuty + SMS in higher tier
- External log destinations (Splunk, AWS CloudWatch) in enterprise tier

---

## Implementation Roadmap

### Phase 1: MVP (Recommended Start)

**Channels:**
- ✅ Email alerts
- ✅ Slack integration
- ✅ Webhooks (generic HTTP POST)

**Features:**
- 5 pre-built alert templates
- Basic custom rules (single condition)
- Per-alert enable/disable
- Basic throttling
- Error handling with auto-disable

**Effort:** ~2-3 weeks

### Phase 2: Enhanced

**Channels:**
- ➕ Discord
- ➕ Microsoft Teams (new Workflows API)
- ➕ Telegram

**Features:**
- Advanced custom rules (AND/OR)
- JsonLogic rule builder UI
- Daily digest option
- Alert history/logs

**Effort:** ~2 weeks

### Phase 3: Enterprise

**Channels:**
- ➕ PagerDuty
- ➕ SMS (Twilio)
- ➕ Pushover

**Features:**
- Escalation policies
- Multi-site alert management
- API for external alert creation

**Effort:** ~2 weeks

---

## Sources

### Competitor Research
- [WP Activity Log - WordPress.org](https://wordpress.org/plugins/wp-security-audit-log/)
- [WP Activity Log Pricing | Melapress](https://melapress.com/wordpress-activity-log/pricing/)
- [Getting started with activity log notifications | Melapress](https://melapress.com/support/kb/getting-started-with-activity-log-notifications/)
- [Wordfence Security Plugin](https://wordpress.org/plugins/wordfence/)
- [Wordfence Alerts Documentation](https://www.wordfence.com/help/dashboard/alerts/)
- [Wordfence vs Sucuri Comparison](https://www.wpbeginner.com/opinion/wordfence-vs-sucuri-which-one-is-better-compared/)

### Enterprise Best Practices
- [PagerDuty + Datadog Best Practices](https://www.pagerduty.com/blog/datadog-integration-best-practices/)
- [Datadog Monitor Configuration](https://docs.datadoghq.com/monitors/configuration/)
- [Grafana Webhook Notifier](https://grafana.com/docs/grafana/latest/alerting/configure-notifications/manage-contact-points/integrations/webhook-notifier/)
- [Best Practices for Alerting Using PagerDuty](https://drdroid.io/engineering-tools/best-practices-for-alerting-using-pagerduty)

### Integration Documentation
- [Slack Webhooks Setup | Google Cloud](https://cloud.google.com/blog/products/devops-sre/use-slack-and-webhooks-for-notifications/)
- [Slack Actionable Notifications](https://api.slack.com/best-practices/blueprints/actionable-notifications)
- [Discord Webhooks Intro](https://support.discord.com/hc/en-us/articles/228383668-Intro-to-Webhooks)
- [Discord Webhook Notifications Setup | Odown](https://odown.com/blog/discord-webhook-notifications-setup/)
- [Microsoft Teams Incoming Webhooks](https://learn.microsoft.com/en-us/microsoftteams/platform/webhooks-and-connectors/how-to/add-incoming-webhook)
- [Twilio SMS WordPress Integration | AtOnce](https://atonce.com/blog/wordpress-twilio)

### Compliance & Enterprise
- [Regulatory Compliance Guide | TimeDctor](https://www.timedoctor.com/blog/regulatory-compliance/)
- [WordPress Security Plugins for Enterprise | SunArc](https://sunarctechnologies.com/blog/wordpress-security-plugins-essential-guide-for-enterprise-protection/)

### Plugin Reviews
- [WP Activity Log Review | Barn2](https://barn2.com/blog/wp-activity-log-review/)
- [Best WordPress Activity Log Plugins | WPBeginner](https://www.wpbeginner.com/showcase/best-wordpress-activity-log-and-tracking-plugins-compared/)
- [WordPress Activity Log Plugins | BlogVault](https://blogvault.net/wordpress-activity-log/)

### Additional Competitors Researched
- [Stream - WordPress.org](https://wordpress.org/plugins/stream/)
- [Stream - XWP Case Study](https://xwp.co/case-study/stream/)
- [Logtivity - WordPress.org](https://wordpress.org/plugins/logtivity/)
- [Logtivity Instant Alerts](https://logtivity.io/features/instant-alerts/)
- [Logtivity Features](https://logtivity.io/features/)
- [Stalkfish](https://stalkfish.com/)
- [Activity Track - WordPress.org](https://wordpress.org/plugins/activity-track/)
- [Activity Track.ai](https://activitytrack.ai/)
- [Activity Log Pro - WordPress.org](https://wordpress.org/plugins/activity-log-pro/)
- [WP Admin Audit - WordPress.org](https://wordpress.org/plugins/wp-admin-audit/)
- [WP Admin Audit](https://wpadminaudit.com/)
- [WP-Umbrella](https://wp-umbrella.com/)
- [WP-Umbrella Features](https://wp-umbrella.com/features/)
- [Users Insights](https://usersinsights.com/)
- [Users Insights - Track User Activity](https://usersinsights.com/wordpress-track-user-activity/)
