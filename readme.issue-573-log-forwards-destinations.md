# Issue #573: Log forwards/Destination/Integrations/Alerts

**Status:** In progress
**Size:** Large
**Labels:** experimental feature, feature
**Branch:** issue-573-log-forwards-destinations (renamed from feature/log-forwarding-integrations)

## Problem Description

Simple History currently only stores events in the local WordPress database and displays them in the WordPress admin interface. This issue aims to extend functionality by adding support for forwarding/sending events to other destinations.

## Potential Destinations

- Text file
- Email
- Slack or Discord channels
- System syslog
- rsyslog server
- Remote database
- ZIP archive
- Papertrail / SolarWinds Observability

## Use Cases

1. **Quick notifications**: Admins can be notified when important events happen (e.g., admin logins)
2. **Compliance**: Maintain logs for compliance requirements
3. **Security**: Have backup logs that hackers cannot modify if they gain access to the system

## Implementation Approach

- Hook into `simple_history/log/inserted` action that fires after events are inserted into the database
- Create a common interface for different destination targets
- Consider performance implications - avoid slowing down the site
- May need to use cron jobs for batched sending to remote APIs (like Slack)

## Feature Ideas

- "Create alert" functionality in event actions menu
- **Premium features** - these should be compelling enough to drive conversions from core to premium
- Show grayed-out sections in core plugin settings to advertise premium features
- Consider a "headless mode" that only logs to external destinations

## Slack Integration Specifics

- Support multiple webhooks
- Send all messages OR filtered events based on:
  - Keyword matching
  - Selected loggers/messages
  - Specific users or exclusion of users

## Third-party Logger Support

- Support PSR-3 compatible loggers (e.g., Monolog)
- Enable logging to files, syslog, Slack, Telegram, databases, etc.

## Possible Feature Names

- Log Forward
- Log Targets
- Log Destinations
- Target Integrations
- Notifier
- Alerts and Notifications
- Notification Channels

## Related Issues

- #209
- #114
- #366
- Simple-History-Add-Ons #56

## Current Progress

### ✅ Completed: Core Integrations System

A **complete, production-ready** integrations system has been implemented on this branch! All 221 tests are passing.

**What's been built:**

1. **Core Infrastructure** ✅
   - `Integrations_Manager` - Central coordinator for all integrations
   - Abstract `Integration` base class with common functionality
   - `Alert_Rules_Engine` for rule evaluation (foundation for filtering)
   - Interface contracts for integrations and alert rules
   - `Integrations_Service` for system registration
   - `Integrations_Settings_Page` - Full UI in WordPress admin

2. **File Integration (Free Feature)** ✅
   - Automatically logs events to local files
   - High-performance write buffering (batches up to 10 entries or 64KB)
   - 3-attempt retry mechanism with 100ms backoff
   - Async cleanup scheduling using WordPress cron
   - Rotation options: daily, weekly, monthly
   - Human-readable log format following Syslog RFC 5424 standards
   - **Optimized for high-traffic WordPress sites**
   - **Security features:**
     - Secure log directory with .htaccess protection (Apache 2.2 and 2.4+ compatible)
     - index.php file to prevent directory listing
     - Smart cleanup that only removes old files matching rotation frequency
   - **Settings page UX:**
     - Directory status display (exists/writable check with color indicators)
     - Auto-creates directory when viewing settings page
     - "Test folder access" link to verify 403 Forbidden protection
     - Detects if folder is in public web directory vs outside ABSPATH
     - Filter `simple_history/file_channel/log_directory` to customize path

3. **Settings System** ✅
   - 7 field types supported: checkbox, text, textarea, url, email, select, number
   - Field validation and sanitization
   - Settings persistence with caching
   - Integration with Simple History's existing settings framework
   - WordPress coding standards compliant

4. **Testing** ✅
   - 221 comprehensive wpunit tests all passing
   - Tests cover field validation, integration management, file operations, buffering, rotation, and more
   - Example integration in test fixtures for demonstration

### 📁 New Files Created

- `inc/integrations/class-integrations-manager.php`
- `inc/integrations/class-integration.php`
- `inc/integrations/class-alert-rules-engine.php`
- `inc/integrations/integrations/class-file-integration.php`
- `inc/integrations/interfaces/interface-integration-interface.php`
- `inc/integrations/interfaces/interface-alert-rule-interface.php`
- `inc/services/class-integrations-service.php`
- `inc/services/class-integrations-settings-page.php`
- Multiple test files in `tests/wpunit/`
- Detailed `issue-progress.md` tracking file

### 🎯 Next Steps

**Premium Integrations** (in separate premium plugin):
- Slack integration with webhooks
- Email alerts
- Discord integration
- HTTP webhooks
- Syslog/rsyslog
- Database integrations
- SolarWinds Observability / Papertrail

**UI/UX Enhancements**:
- Show grayed-out premium integrations in settings to drive upgrades
- Add visual indicators for premium vs free features
- Create "Create alert" functionality in event actions menu
- Consider "Test Connection" buttons for integrations

**Rule/Filter System**:
- Build rule/query builder UI (see researched libraries below)
- Allow filtering events by:
  - Logger type and message
  - Keywords
  - Specific users or user exclusions
  - Event severity/level

## Rules/Query Builder Libraries Research

### JavaScript/React UI Libraries

**1. React Query Builder** ⭐ SUPPORTS JSONLOGIC
- URL: https://react-querybuilder.js.org
- Pros:
  - Official React library, well-maintained, flexible
  - **Built-in JsonLogic export/import** via `formatQuery(query, 'jsonlogic')` and `parseJsonLogic()`
  - Can export to SQL, MongoDB, CEL, SpEL, JsonLogic, and custom formats
  - Supports custom operators and rule processors
  - Lightweight compared to alternatives
- Cons: UI is more basic, less opinionated design
- **Best for**: Full control, JsonLogic integration

**2. React Awesome Query Builder** ⭐ ALSO SUPPORTS JSONLOGIC
- URL: https://github.com/ukrbublik/react-awesome-query-builder
- Demo: https://ukrbublik.github.io/react-awesome-query-builder/
- Pros:
  - Very feature-rich, user-friendly UI, lots of field types
  - **Built-in JsonLogic export/import** via `Utils.loadFromJsonLogic()` and export utilities
  - Can export to MongoDB, SQL, JsonLogic, SpEL, ElasticSearch
  - Core package can be used server-side without React
  - Beautiful, polished UI out of the box
- Cons: Larger library, more complex, might be overkill for simple use cases
- **Best for**: Rich UI, minimal custom styling needed

**3. jQuery QueryBuilder**
- URL: https://querybuilder.js.org/
- Pros: Mature, lots of examples
- Cons: jQuery dependency (not ideal for React-based Simple History)

### Rule Evaluation Engines

**4. JsonLogic** ⭐ RECOMMENDED
- URL: https://jsonlogic.com/
- Pros:
  - Supports both JavaScript AND PHP
  - Simple JSON format for storing rules
  - Can share rules between frontend and backend
  - Lightweight and fast
- Cons: UI needs to be built separately
- **Best fit**: Use for rule evaluation, combine with React Query Builder for UI

**5. RulePilot**
- URL: https://github.com/andrewbrg/rulepilot
- Pros: Simple JSON rule processing for JavaScript
- Cons: JavaScript only, less mature

### UX/Pattern References

**6. UI Patterns - Rule Builder**
- URL: https://ui-patterns.com/patterns/rule-builder/
- Good resource for UX design patterns

### Recommended Approach

**Option A: React Query Builder + JsonLogic** ⭐ RECOMMENDED
- Use **React Query Builder** for UI (lightweight, flexible)
- Export to **JsonLogic format** using built-in `formatQuery(query, 'jsonlogic')`
- Evaluate rules in PHP using JsonLogic PHP library
- Store rules as JSON in WordPress options
- **Benefits**:
  - Both UI and evaluation use the same format (JsonLogic)
  - No custom PHP parser needed - use existing JsonLogic PHP library
  - Lightweight React component
  - Easy to extend with custom operators
  - Full round-trip: UI → JsonLogic → PHP → validation

**Option B: React Awesome Query Builder + JsonLogic**
- Use **React Awesome Query Builder** for UI (beautiful, feature-rich)
- Export to **JsonLogic** using built-in utilities
- Evaluate rules in PHP using JsonLogic PHP library
- **Benefits**:
  - Polished UI out of the box
  - Same JsonLogic advantages as Option A
  - Core package works server-side
- **Tradeoffs**: Larger bundle size, more complex

**Option C: Simple Dropdown/Checkbox UI**
- Start with basic "Select logger types" checkboxes
- Add complexity later if needed
- Fastest to implement, good for MVP
- Can migrate to JsonLogic-based rules later

### Implementation Notes

**PHP JsonLogic Library:**
- Use https://github.com/jwadhams/json-logic-php for PHP evaluation
- Composer: `composer require jwadhams/json-logic-php`
- Same logic rules work in both JavaScript and PHP

**Example Flow:**
1. User builds rule in React Query Builder UI
2. Export to JsonLogic: `{"and": [{"==": [{"var": "logger"}, "user"]}, {"in": ["login", {"var": "message"}]}]}`
3. Store JSON in WordPress options
4. On event: Evaluate rule in PHP using JsonLogic library
5. If rule matches: Send to integration

### 📊 Current Status

**Production-ready**: The core system is complete and tested. File Integration is ready to ship as a free feature. The architecture is solid for adding premium integrations in the separate add-on.

## Architecture Decision: Two Integration Types

**Decision:** Split integrations into two conceptually different types with shared base class but separate UI sections.

### Log_Integration (Log Destinations)
**Purpose:** "Store/archive everything somewhere"
**User mindset:** "I need a complete copy of my logs"

**Characteristics:**
- Comprehensive logging - typically ALL events
- No filtering/rules needed
- Focus on backup, compliance, debugging
- Simple UI: toggle on/off + destination settings

**Destinations:**
- **Local:** File backup, PHP error_log, Syslog
- **Remote:** External database, Remote rsyslog, SolarWinds/Papertrail, S3/cloud storage

### Alert_Integration (Alerts & Notifications)
**Purpose:** "Tell me when specific things happen"
**User mindset:** "I want to be interrupted/notified about X"

**Characteristics:**
- Selective, rule-based filtering
- Actionable notifications
- May need rate limiting (e.g., Slack: 1 msg/sec)
- Often async/queued for performance
- More complex UI: rule builder + destination settings

**Destinations:**
- Slack, Email, Teams, Discord, SMS, Webhooks

### Implementation

```php
Integration (base class)
├── Log_Integration (simpler, no rules)
│   ├── File
│   ├── Syslog
│   ├── Error_Log
│   └── External_Database
└── Alert_Integration (has rules, rate limiting)
    ├── Slack
    ├── Email
    ├── Teams
    └── Webhook
```

### Admin UI Structure

```
Simple History Settings
├── Log Destinations (simple toggles)
│   ├── Local
│   │   ├── ✅ File backup - /logs/simple-history.log
│   │   ├── ☐ PHP error_log
│   │   └── ☐ Syslog
│   └── Remote
│       ├── ☐ External database
│       └── ☐ SolarWinds/Papertrail
└── Alerts & Notifications (rule builder)
    ├── Slack - 2 rules configured
    ├── Email - 1 rule configured
    └── Discord - not configured
```

### Shared vs Separate

| Aspect | Log Destinations | Alerts |
|--------|-----------------|--------|
| Purpose | Archive/backup | Notification |
| Filtering | No (all events) | Yes (rule-based) |
| Volume | High | Low (selective) |
| UI complexity | Simple toggle | Rules + channels |
| Timing | Sync/immediate | Often queued |
| Rate limiting | No | Yes |

**Shared:** Event receiving, enable/disable, settings storage, validation

### Example Scenarios

**Scenario 1: Security-conscious site owner**
> "I want a backup of all logs in case a hacker clears the database, plus instant Slack alerts for failed logins."

Configuration:
- Log Destinations: ✅ File backup (all events)
- Alerts: Slack rule → "logger = user AND message contains 'failed login'"

**Scenario 2: Agency managing multiple client sites**
> "We need all events archived for compliance, and email alerts when any admin makes changes."

Configuration:
- Log Destinations: ✅ External database (all events to central DB)
- Alerts: Email rule → "user role = administrator"

**Scenario 3: Developer debugging issues**
> "I want everything in the PHP error_log so I can tail it during development."

Configuration:
- Log Destinations: ✅ PHP error_log (all events)
- Alerts: None needed

**Scenario 4: E-commerce site owner**
> "Alert me on Slack when orders fail, email me daily user registrations, keep file backup of everything."

Configuration:
- Log Destinations: ✅ File backup (all events)
- Alerts:
  - Slack rule → "logger = woocommerce AND message contains 'order failed'"
  - Email rule → "logger = user AND message = 'registered'" (daily digest)

**Scenario 5: Enterprise compliance requirement**
> "All logs must go to our SIEM (SolarWinds) and syslog server. No alerts needed."

Configuration:
- Log Destinations: ✅ SolarWinds/Papertrail, ✅ Remote rsyslog
- Alerts: None

**Scenario 6: Small blog owner**
> "Just tell me when someone logs in as admin."

Configuration:
- Log Destinations: None (just use built-in Simple History log)
- Alerts: Email rule → "logger = user AND message = 'logged in' AND user role = administrator"

### Possible Destinations (Comprehensive List)

#### Log Destinations (Archive/Backup)

**Local:**
- File ✅ (already built)
- PHP error_log
- Syslog (local)

**Self-Hosted Log Management:**
- Graylog - Popular open-source, GELF protocol
- Elasticsearch/ELK Stack - Logstash/Filebeat ingestion
- Seq - Structured logging for .NET shops
- Loki (Grafana) - Like Prometheus but for logs

**Cloud Log Services:**
- SolarWinds/Papertrail - Easy setup, good for small teams
- Splunk - Enterprise standard
- Datadog - Popular DevOps platform
- Loggly - Simple cloud logging
- New Relic - APM + logs
- Sumo Logic - Cloud SIEM

**Cloud Provider Native:**
- AWS CloudWatch Logs
- Google Cloud Logging
- Azure Monitor Logs

**Storage/Archive:**
- S3 / Google Cloud Storage / Azure Blob - Cheap long-term archive
- Remote database (MySQL/PostgreSQL)
- Remote rsyslog server

**Error Tracking (hybrid):**
- Sentry - Error tracking, but accepts log events
- Rollbar
- Bugsnag

#### Alert Integrations (Notifications)

**Team Chat:**
- Slack - Most requested
- Microsoft Teams - Enterprise
- Discord - Dev/gaming communities
- Google Chat
- Mattermost - Self-hosted Slack alternative
- Rocket.Chat - Open source
- Matrix - Decentralized

**Email:**
- Email (SMTP) - Direct or via wp_mail
- Email (SendGrid/Mailgun/SES) - Transactional email APIs

**SMS/Push:**
- SMS via Twilio
- SMS via Nexmo/Vonage
- Pushover - Simple push notifications
- Pushbullet
- Telegram - Bot API

**Incident Management:**
- PagerDuty - On-call alerting
- Opsgenie (Atlassian)
- VictorOps (Splunk On-Call)
- xMatters

**Generic/Automation:**
- Webhooks - Generic HTTP POST (covers anything)
- Zapier - Connect to 5000+ apps
- IFTTT
- Make (Integromat)
- n8n - Self-hosted automation

### Admin UI Structure Decision

**Approach:** Start with tabs in Settings page (Option B), potentially move to dedicated page later (Option D).

**Settings Page Tabs:**
```
Simple History → Settings
┌─────────┬──────────────────┬────────────────────────┐
│ General │ Log Destinations │ Alerts & Notifications │
└─────────┴──────────────────┴────────────────────────┘
```

**Log Destinations Tab:**
```
┌─────────────────────────────────────────────────────┐
│ Log Destinations                                    │
├─────────────────────────────────────────────────────┤
│ ┌─ File Backup ─────────────────────────── ✅ ON ─┐ │
│ │ Path: /wp-content/simple-history-logs/          │ │
│ │ Rotation: Daily                    [Configure]  │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─ PHP error_log ───────────────────────── ☐ OFF ─┐ │
│ │ Writes to WordPress debug.log      [Configure]  │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─ Syslog ──────────────────────────────── ☐ OFF ─┐ │
│ │ System syslog facility             [Configure]  │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ 💡 More destinations available in Premium           │
└─────────────────────────────────────────────────────┘
```

**Alerts & Notifications Tab:**
```
┌─────────────────────────────────────────────────────┐
│ Alerts & Notifications              [+ New Alert]   │
├─────────────────────────────────────────────────────┤
│ ┌─ Failed Logins ──────────────────────── ✅ ON ──┐ │
│ │ When: User login fails                          │ │
│ │ Send to: Slack #security, Email admin           │ │
│ │                            [Edit] [Delete]      │ │
│ └─────────────────────────────────────────────────┘ │
│ ┌─ Admin Actions ──────────────────────── ✅ ON ──┐ │
│ │ When: Administrator makes changes               │ │
│ │ Send to: Email admin                            │ │
│ │                            [Edit] [Delete]      │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ 💡 This feature requires Simple History Premium     │
│    [Learn More]                                     │
└─────────────────────────────────────────────────────┘
```

**Future Option:** If feature grows significantly, move to dedicated "Integrations" menu item with its own sub-tabs.

#### Implementation Priority

**MVP / High Demand:**

| Log Destinations | Alerts |
|-----------------|--------|
| File ✅ | Slack |
| PHP error_log | Email |
| Syslog | Webhooks (covers everything else) |

**Phase 2 / Premium:**

| Log Destinations | Alerts |
|-----------------|--------|
| Graylog | Microsoft Teams |
| Papertrail/SolarWinds | Discord |
| External database | Telegram |
| AWS CloudWatch | PagerDuty |

**Nice to Have:**

| Log Destinations | Alerts |
|-----------------|--------|
| Elasticsearch | SMS (Twilio) |
| Splunk/Datadog | Pushover |
| S3 archive | Zapier |

---

## Open Questions & Design Decisions

These are critical questions that need to be answered before implementing premium integrations and the rule/filter system:

### Event Processing Strategy
**Question:** How and when should we catch and send events? Directly, using cron, or Action Scheduler?

**Considerations:**
- **Direct/Synchronous**: Simple but could slow down page loads for remote APIs (Slack, webhooks)
- **WP-Cron**: WordPress built-in, but unreliable on low-traffic sites, batch processing possible
- **Action Scheduler**: More reliable than WP-Cron, better for batching, adds dependency
- **Hybrid Approach**: Direct for local (file), async for remote (API calls)

**Current Implementation:** File Integration uses direct writes with buffering for performance

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

**Related:** How do we make this intuitive for non-technical users while still powerful for advanced users?

### Multiple Rules Per Integration
**Question:** Should each integration be able to handle multiple different rules?

**Considerations:**
- **Single rule per integration**: Simpler architecture, users create multiple "instances" for different rules
  - Example: "Slack - Security" integration + "Slack - Admin Actions" integration
- **Multiple rules per integration**: More complex but potentially more user-friendly
  - Example: One Slack integration with multiple rule sets
- **Hybrid**: Some integrations support multiple rules (email), others don't (file)

**Impact on UI:** Multiple rules = need for rule management UI within each integration's settings

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
  - Requires event priority/severity system

**Current Implementation:** File Integration evaluates and writes immediately (with buffering for performance)

### Error Handling & Failure Recovery
**Question:** What to do when a notification fails? For example, Slack gets an error - should we resend and how many times? Fallback? Pause integration and notify user?

**Considerations:**
- **Retry Strategy**:
  - **No retry**: Simplest, but events could be lost
  - **Fixed retries**: Try X times (e.g., 3 attempts) with exponential backoff
  - **Smart retry**: Retry based on error type (network error = retry, auth error = don't retry)
  - **Persistent queue**: Store failed events in DB, retry later via cron
- **Retry Timing**:
  - Immediate retries (100ms, 500ms, 2s) for transient errors
  - Scheduled retries (5min, 1hr, 24hr) for persistent issues
  - Exponential backoff to avoid hammering failing APIs
- **Failure Thresholds**:
  - Auto-disable integration after X consecutive failures (e.g., 10)
  - Require manual re-enable to prevent infinite failed attempts
  - Track failure rate over time (e.g., 50% failure rate in 1 hour)
- **User Notification**:
  - **Admin notice**: Show WordPress admin notice when integration fails
  - **Email alert**: Send email to admin when integration is auto-disabled
  - **Simple History event**: Log integration failures as events (meta!)
  - **Dashboard widget**: Show integration health status
- **Fallback Options**:
  - Secondary integration (if Slack fails, try email)
  - Always log to file as backup
  - Queue for manual review/resend
- **Error Visibility**:
  - Show last error message in integration settings
  - Log errors to WordPress debug.log
  - Dedicated "Integration Logs" page showing success/failure history
  - Status indicator (green/yellow/red) per integration

**Current Implementation:** File Integration has 3-attempt retry with 100ms backoff for transient write failures

**Recommended Approach:**
- Retry 3-5 times with exponential backoff for API calls
- Auto-disable after 10 consecutive failures
- Show admin notice when disabled
- Log integration errors as Simple History events
- Display last error + failure count in settings UI

### Rules vs Destinations Architecture
**Question:** What's more user-friendly and logical - create a rule that sends to multiple destinations, or create rules per destination?

**Approach 1: Rule → Multiple Destinations** ⭐ RECOMMENDED
- User creates a rule (e.g., "Failed login attempts")
- User selects which destinations receive events matching this rule (File + Slack + Email)
- One rule evaluation → multiple destinations

**Pros:**
- ✅ Matches how users think: "When X happens, notify me via Y and Z"
- ✅ No duplication - define rule logic once
- ✅ Easy to see what events trigger which notifications
- ✅ More efficient - evaluate rule once, send to multiple places
- ✅ Less configuration work
- ✅ Industry standard (Zapier, Sentry, Datadog work this way)

**Cons:**
- ❌ Different destinations might need different settings (e.g., Slack channel selection)
- ❌ Harder to do destination-specific formatting

**Solution to cons:**
- Rule defines WHAT to send
- Each destination has its own settings for HOW to send (channel, format, etc.)
- Destination-specific overrides available if needed

**Approach 2: Destination → Rules**
- Each integration (File, Slack, Email) has its own rule(s)
- User configures rules separately per destination

**Pros:**
- ✅ Each destination can have completely unique rules
- ✅ Destination settings and rules stay together
- ✅ Clear separation of concerns

**Cons:**
- ❌ Lots of duplication if same rule for multiple destinations
- ❌ Hard to see "big picture" of notification setup
- ❌ More configuration work
- ❌ Less efficient - same rule evaluated multiple times

**Hybrid Approach: Destinations with Shared Rules**
- Store rules independently
- Destinations reference/use rules
- Rules can be reused across multiple destinations

**Real-world Examples:**

*Use Case 1:* "Send all failed logins to Slack AND Email"
- **Approach 1**: Create one rule "Failed logins", check Slack + Email ✅ Easy
- **Approach 2**: Create same rule in Slack settings, then again in Email settings ❌ Duplication

*Use Case 2:* "Send critical errors to Slack #critical, all errors to Slack #errors, all events to File"
- **Approach 1**: Create 3 rules, each with different destinations ✅ Still works
- **Approach 2**: Configure File integration with "all events", configure Slack twice with different rules ✅ Also works but requires multiple Slack integrations

*Use Case 3:* "Send user registrations to Slack #marketing, email admin, and log to file"
- **Approach 1**: One rule "User registered", select all three destinations ✅ Simple
- **Approach 2**: Configure same rule three times ❌ Tedious

**Recommended Implementation:**

1. **Rules are first-class objects** - stored independently, can be named, managed centrally
2. **Rules select destinations** - each rule has checkboxes for which integrations to use
3. **Destinations have their own settings** - Slack channel, Email recipients, File path, etc.
4. **Optional: Destination-specific overrides** - Rule can override default destination settings if needed

**UI Flow:**
```
Integrations & Alerts
├── Destinations (configured once)
│   ├── ✅ File Backup (enabled)
│   ├── ✅ Slack - #general (enabled, webhook configured)
│   ├── ✅ Email Alerts (enabled, admin@example.com)
│   └── ❌ Discord (not configured)
├── Rules (the logic)
│   ├── Rule: "Failed Logins"
│   │   ├── Condition: logger = "user" AND message contains "failed"
│   │   └── Send to: ☑ File, ☑ Slack, ☑ Email
│   ├── Rule: "Admin Actions"
│   │   ├── Condition: user role = "administrator"
│   │   └── Send to: ☑ File, ☑ Slack
│   └── Rule: "Everything"
│       ├── Condition: (all events)
│       └── Send to: ☑ File only
```

**Alternative: Simpler MVP Approach**
- Skip standalone "Rules" initially
- Each destination has built-in filtering options (simple checkboxes for logger types)
- Add advanced rules later as premium feature

### Additional Considerations
- **Performance impact**: How many integrations can run simultaneously without degrading site performance?
- **Rate limiting**: How do we handle APIs with rate limits (Slack: 1 msg/sec)?
- **Data privacy**: Should we offer PII filtering/masking options?
- **Testing integrations**: Should we provide "Test Connection" or "Send Test Event" functionality?
