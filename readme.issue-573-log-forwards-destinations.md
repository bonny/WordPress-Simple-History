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
- Build rule/query builder UI (potentially using react-querybuilder or jsonlogic)
- Allow filtering events by:
  - Logger type and message
  - Keywords
  - Specific users or user exclusions
  - Event severity/level

### 📊 Current Status

**Production-ready**: The core system is complete and tested. File Integration is ready to ship as a free feature. The architecture is solid for adding premium integrations in the separate add-on.
