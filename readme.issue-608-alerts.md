# Issue #608: Alerts & Notifications

**Status:** Planned (Foundation laid)
**Size:** Large
**Labels:** Feature, Size: Large
**Branch:** TBD (will create when work starts)

## Problem Description

Simple History collects events using its loggers and stores them in the local WordPress database. Users want to be notified in real-time when specific events happen, such as:

- Admin logins or failed login attempts
- Plugin/theme changes
- User registrations
- Critical errors

This issue focuses on implementing **rule-based notifications** that alert users via various channels (Slack, Email, Teams, Discord, Webhooks) when events match their configured criteria.

## Use Cases

1. **Quick notifications**: Admins can be notified when important events happen (e.g., admin logins, failed logins)
2. **Security monitoring**: Get alerted when suspicious activity occurs
3. **Workflow automation**: Connect to other systems via webhooks when specific events occur

## Feature Ideas

- "Create alert" functionality in event actions menu - "Create alerts for messages like this"
- These should be premium features to drive conversions from core to premium
- Show grayed-out sections in core plugin settings to advertise premium features
- Alert presets: "Security events", "Admin actions", "User activity"
- Consider a "headless mode" that only logs to external destinations

---

## Alert Integrations (Destinations)

### Team Chat
- **Slack** - Most requested, webhook-based
- Microsoft Teams - Enterprise
- Discord - Dev/gaming communities
- Google Chat
- Mattermost - Self-hosted Slack alternative
- Telegram - Bot API

### Email
- Email (SMTP) - Direct or via wp_mail
- Email via SendGrid/Mailgun/SES

### SMS/Push
- SMS via Twilio
- Pushover - Simple push notifications
- Pushbullet

### Incident Management
- PagerDuty - On-call alerting
- Opsgenie (Atlassian)
- VictorOps (Splunk On-Call)

### Generic/Automation
- **Webhooks** - Generic HTTP POST (covers anything)
- Zapier - Connect to 5000+ apps
- IFTTT
- Make (Integromat)
- n8n - Self-hosted automation

---

## Slack Integration Specifics

- Support one or multiple webhooks
- Send:
  - All messages
  - OR only events that match:
    - Contains entered keywords
    - Is one of selected loggers/messages
    - Is from a specific list of users
    - Is NOT from a specific list of users

---

## Architecture

Alerts are part of the Integration system (from #573) but with different characteristics:

```php
Integration (base class)
└── Alert_Integration (has rules, rate limiting)
    ├── Slack
    ├── Email
    ├── Teams
    └── Webhook
```

### Alert_Integration Characteristics
- Selective, rule-based filtering
- Actionable notifications
- May need rate limiting (e.g., Slack: 1 msg/sec)
- Often async/queued for performance
- More complex UI: rule builder + destination settings

### Comparison with Log Destinations

| Aspect | Log Destinations | Alerts |
|--------|-----------------|--------|
| Purpose | Archive/backup | Notification |
| Filtering | No (all events) | Yes (rule-based) |
| Volume | High | Low (selective) |
| UI complexity | Simple toggle | Rules + channels |
| Timing | Sync/immediate | Often queued |
| Rate limiting | No | Yes |

---

## Alert Rules System

### Foundation Already Built ✅

The following foundation was laid during #573:

- **JsonLogic-only approach** for rule evaluation (no custom rule types)
- **`jwadhams/json-logic-php`** library added for cross-platform rule evaluation
- **Alert_Evaluator** - Thin wrapper around JsonLogic for rule evaluation
- **Alert_Field_Registry** - UI field definitions for React Query Builder
- **Simplified Alert_Rules_Engine** - Facade delegating to Alert_Evaluator

```
Alert_Rules_Engine (service facade)
    ├── Alert_Evaluator (JsonLogic evaluation)
    │       └── JWadhams\JsonLogic (library)
    └── Alert_Field_Registry (UI field definitions)
```

### Benefits of JsonLogic
- Same rule format works in JavaScript (React Query Builder) and PHP
- No custom PHP parser needed
- Simpler architecture, less code to maintain
- Easy to extend with custom operators if needed

---

## Rules/Query Builder Libraries

### JavaScript/React UI Libraries

**1. React Query Builder** (RECOMMENDED)
- URL: https://react-querybuilder.js.org
- Built-in JsonLogic export/import via `formatQuery(query, 'jsonlogic')`
- Lightweight, flexible, full control

**2. React Awesome Query Builder**
- URL: https://github.com/ukrbublik/react-awesome-query-builder
- Very feature-rich, polished UI
- Built-in JsonLogic export/import
- Larger bundle size

### Rule Evaluation (PHP)
- **jwadhams/json-logic-php** - Already installed via Composer

### Example Rule Flow
1. User builds rule in React Query Builder UI
2. Export to JsonLogic: `{"and": [{"==": [{"var": "logger"}, "user"]}, {"in": ["failed", {"var": "message"}]}]}`
3. Store JSON in WordPress options
4. On event: Evaluate rule in PHP using JsonLogic library
5. If rule matches: Send to configured destinations

---

## Admin UI Mockup

```
Alerts & Notifications              [+ New Alert]
├─────────────────────────────────────────────────┤
│ ┌─ Failed Logins ───────────────────── ✅ ON ──┐│
│ │ When: User login fails                       ││
│ │ Send to: Slack #security, Email admin        ││
│ │                           [Edit] [Delete]    ││
│ └──────────────────────────────────────────────┘│
│ ┌─ Admin Actions ───────────────────── ✅ ON ──┐│
│ │ When: Administrator makes changes            ││
│ │ Send to: Email admin                         ││
│ │                           [Edit] [Delete]    ││
│ └──────────────────────────────────────────────┘│
│                                                 │
│ 💡 This feature requires Simple History Premium│
│    [Learn More]                                │
└─────────────────────────────────────────────────┘
```

---

## Open Questions & Design Decisions

### Event Processing Strategy
**Question:** How and when should we catch and send events? Directly, using cron, or Action Scheduler?

**Considerations:**
- **Direct/Synchronous**: Simple but could slow down page loads for remote APIs (Slack, webhooks)
- **WP-Cron**: WordPress built-in, but unreliable on low-traffic sites, batch processing possible
- **Action Scheduler**: More reliable than WP-Cron, better for batching, adds dependency
- **Hybrid Approach**: Direct for local (file), async for remote (API calls)

### Rule Complexity & User Choice
**Question:** How many rules should there be? How much choice should a user have when selecting what events to send to an integration?

**Considerations:**
- **Simple (few rules)**: Easier for users, less overwhelming, faster to implement
  - Example: "Send all events" OR "Send only these logger types"
- **Medium complexity**: Balance of power and usability
  - Example: Logger types + keywords + user filtering
- **Advanced (many rules)**: Very powerful but potentially confusing
  - Example: Full query builder with AND/OR logic, nested conditions
- **Presets + Custom**: Offer common presets ("Security events", "Admin actions") + custom rules

### Multiple Rules Per Integration
**Question:** Should each integration be able to handle multiple different rules?

**Considerations:**
- **Single rule per integration**: Simpler architecture, users create multiple "instances" for different rules
  - Example: "Slack - Security" integration + "Slack - Admin Actions" integration
- **Multiple rules per integration**: More complex but potentially more user-friendly
  - Example: One Slack integration with multiple rule sets
- **Hybrid**: Some integrations support multiple rules (email), others don't (file)

### Rule Evaluation Timing
**Question:** Should rules apply directly for each event as it comes in, or should we batch process them?

**Considerations:**
- **Immediate evaluation**: Lower latency, users get notifications faster
  - Pros: Real-time alerts, simpler state management
  - Cons: Performance impact if many events/rules, could slow down requests
- **Batch processing**: Better performance, more efficient for high-traffic sites
  - Pros: Reduced overhead, can optimize DB queries, better for rate-limited APIs
  - Cons: Delayed notifications, need to store events temporarily, more complex
- **Hybrid**: Immediate for critical events, batched for routine events

### Error Handling & Failure Recovery
**Question:** What to do when a notification fails?

**Recommendations (from #573 experience):**
- Retry 3-5 times with exponential backoff for API calls
- Auto-disable after 5 consecutive failures
- Show admin notice when disabled
- Log integration errors as Simple History events (with error message in context)
- Display last error + failure count in settings UI (mini-log format)

### Rules vs Destinations Architecture
**Question:** What's more user-friendly - create a rule that sends to multiple destinations, or create rules per destination?

**Recommended Approach: Rule → Multiple Destinations**
- User creates a rule (e.g., "Failed login attempts")
- User selects which destinations receive events matching this rule
- One rule evaluation → multiple destinations

**UI Flow:**
```
Integrations & Alerts
├── Destinations (configured once)
│   ├── ✅ Slack - #general (enabled, webhook configured)
│   ├── ✅ Email Alerts (enabled, admin@example.com)
│   └── ❌ Discord (not configured)
├── Rules (the logic)
│   ├── Rule: "Failed Logins"
│   │   ├── Condition: logger = "user" AND message contains "failed"
│   │   └── Send to: ☑ Slack, ☑ Email
│   └── Rule: "Admin Actions"
│       ├── Condition: user role = "administrator"
│       └── Send to: ☑ Slack
```

### Additional Considerations
- **Performance impact**: How many integrations can run simultaneously without degrading site performance?
- **Rate limiting**: How do we handle APIs with rate limits (Slack: 1 msg/sec)?
- **Data privacy**: Should we offer PII filtering/masking options?
- **Testing integrations**: "Test Connection" or "Send Test Event" functionality

---

## Files Already Created (Foundation)

These files were created during #573 as foundation for alerts:

- `inc/libraries/JsonLogic.php` - Third-party JsonLogic library
- `inc/channels/class-alert-evaluator.php` - JsonLogic wrapper
- `inc/channels/class-alert-field-registry.php` - UI field definitions
- `docs/alerts-feature-research.md` - Competitor analysis
- `docs/alerts-async-processing-research.md` - Performance research

---

## Implementation Priority

### MVP
- Slack
- Email
- Webhooks (covers everything else)

### Phase 2 / Premium
- Microsoft Teams
- Discord
- Telegram
- PagerDuty

### Nice to Have
- SMS (Twilio)
- Pushover
- Zapier integration

---

## Example Scenarios

**Scenario 1: Security-conscious site owner**
> "I want instant Slack alerts for failed logins."

Configuration:
- Alerts: Slack rule → "logger = user AND message contains 'failed login'"

**Scenario 2: Agency managing multiple client sites**
> "Email me when any admin makes changes."

Configuration:
- Alerts: Email rule → "user role = administrator"

**Scenario 4: E-commerce site owner**
> "Alert me on Slack when orders fail, email me daily user registrations."

Configuration:
- Alerts:
  - Slack rule → "logger = woocommerce AND message contains 'order failed'"
  - Email rule → "logger = user AND message = 'registered'" (daily digest)

**Scenario 6: Small blog owner**
> "Just tell me when someone logs in as admin."

Configuration:
- Alerts: Email rule → "logger = user AND message = 'logged in' AND user role = administrator"

---

## Related Issues

- #573 (Log Forwarding - original parent issue)
- #209
- #114
- #366
- Simple-History-Add-Ons #56
