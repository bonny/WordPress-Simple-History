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
   - Rotation options: daily, weekly, monthly, or never
   - Secure log directory with .htaccess protection
   - Smart cleanup that only removes old files matching rotation frequency
   - Human-readable log format following Syslog RFC 5424 standards
   - **Optimized for high-traffic WordPress sites**

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
