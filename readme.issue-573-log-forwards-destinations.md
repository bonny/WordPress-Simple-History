# Issue #573: Log Forwarding / Log Destinations

**Status:** Complete ✅ (Ready to merge)
**Size:** Large
**Labels:** experimental feature, feature
**Branch:** issue-573-log-forwards-destinations

## Problem Description

Simple History currently only stores events in the local WordPress database and displays them in the WordPress admin interface. This issue extends functionality by adding support for forwarding/sending events to other destinations for backup, compliance, and security purposes.

## Use Cases

1. **Compliance**: Maintain logs for compliance requirements (SOC 2, GDPR, HIPAA, PCI DSS)
2. **Security**: Have backup logs that hackers cannot modify if they gain access to the system

## Log Destinations (Archive/Backup)

### Implemented ✅

**Free:**
- File backup (local log files with rotation)

**Premium:**
- Syslog (local and remote rsyslog)
- External Database (MySQL/MariaDB)

### Future Possibilities

Research conducted December 2025.

#### Reality Check: What's Actually Useful?

Simple History logs **WordPress audit events** (user logins, post edits, plugin changes) - not infrastructure metrics or APM traces. Most enterprise observability tools are overkill.

**Who needs log forwarding?**

| Use Case | Solution | Status |
|----------|----------|--------|
| **Compliance** (HIPAA, SOC2, PCI) | External Database | ✅ Implemented |
| **Backup against hackers** | File Channel | ✅ Implemented |
| **Agencies with 10+ sites** | Graylog, ELK, Loki | Future |
| **Already using Splunk/Datadog** | Add WP logs to existing stack | Future |
| **Simple cloud backup** | Papertrail, Loggly | Future |

#### Service Fit Assessment

| Service | Designed For | Fit for WP Audit Logs |
|---------|--------------|----------------------|
| **Graylog** | Centralized logging | ✅ Good - agencies, multiple sites |
| **Grafana Loki** | Lightweight log aggregation | ✅ Good - self-hosters |
| **Papertrail** | Simple cloud logging | ✅ Good - easy, affordable |
| **Loggly** | Simple cloud logging | ✅ Good - easy setup |
| **ELK Stack** | Log aggregation, search | ⚠️ OK if already running it |
| **Splunk** | Enterprise security, big data | ⚠️ Overkill unless already using |
| **Datadog** | Infrastructure, APM, DevOps | ❌ Overkill, expensive |
| **Dynatrace/New Relic** | APM, performance | ❌ Wrong tool entirely |
| **AWS CloudWatch** | AWS infrastructure | ⚠️ Only if site is on AWS |

#### Prioritized Roadmap

Based on actual usefulness for WordPress audit logs:

**Phase 1: Quick Wins** (Best fit, low effort)
| Service | Use Case | Implementation |
|---------|----------|----------------|
| Papertrail | Simple cloud backup | Add TLS to Syslog Channel. Auth = unique port. |
| Loggly | Simple cloud backup | HTTP POST with token, or TLS syslog |
| Graylog | Agency log aggregation | GELF over HTTP, `wp_remote_post()` |

**Phase 2: Self-Hosted** (For users who run their own infrastructure)
| Service | Use Case | Implementation |
|---------|----------|----------------|
| Grafana Loki | Lightweight aggregation | HTTP API, JSON POST |
| Elastic/Logstash | Existing ELK users | HTTP API |

**Phase 3: "We Already Use It"** (For enterprises with existing tools)
| Service | Use Case | Implementation |
|---------|----------|----------------|
| Splunk | Add WP to existing Splunk | HTTP Event Collector |
| Datadog | Add WP to existing Datadog | HTTP API |
| AWS CloudWatch | AWS-hosted WordPress | Requires SDK |

**Probably Not Worth Building:**
- Dynatrace, New Relic (APM tools, not log tools)
- Azure Monitor, Google Cloud Logging (niche audience)
- Sumo Logic (enterprise SIEM, overkill)

#### Technical Notes

**TLS Syslog** (for Papertrail/Loggly):
```php
$context = stream_context_create(['ssl' => ['verify_peer' => true]]);
$socket = stream_socket_client( 'tls://' . $host . ':' . $port, ..., $context );
```

**Loggly auth**: Token in RFC5424 structured data: `[token@41058 tag="simple-history"]`

**Papertrail auth**: Unique port per account (no token needed)

## Implementation

Events are forwarded using the `simple_history/log/inserted` action that fires after events are inserted into the database. All destinations share a common interface making it easy to add new ones.

## Current Progress

### ✅ Completed: Core Integrations System

A **complete, production-ready** integrations system has been implemented. All 221 tests are passing.

**What's been built:**

1. **Core Infrastructure** ✅
   - `Integrations_Manager` - Central coordinator for all integrations
   - Abstract `Integration` base class with common functionality
   - Interface contracts for integrations
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
     - Combined rotation frequency and retention into single concise row
     - Inline security note (non-intrusive)
     - Premium formatter teasers with disabled radio buttons
     - Descriptive checkbox label ("Enable automatic log file backups")

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

5. **Syslog Channel (Premium Feature)** ✅
   - **Local syslog** via PHP `syslog()` function
   - **Remote rsyslog** via UDP/TCP sockets
   - **RFC 5424 format** using existing premium formatter
   - **Settings UI:**
     - Mode selection: Local / Remote UDP / Remote TCP
     - Facility dropdown (LOG_USER, LOG_LOCAL0-7, LOG_DAEMON)
     - Identity string (app name in syslog)
     - Remote host/port configuration
     - Connection timeout setting
   - **Test Connection button** with AJAX feedback
   - **Error handling:**
     - Tracks consecutive failures (remote only)
     - Auto-disables after 5 consecutive errors
     - Shows last error message in settings
     - Logs auto-disable events to Simple History with error details
     - Re-enables when user saves settings

6. **External Database Channel (Premium Feature)** ✅
   - **MySQL/MariaDB support** for off-site audit log storage
   - **Hybrid schema design:**
     - Core indexed fields: `event_date`, `logger`, `level`, `user_id`, `initiator`, `message_key`, `site_url`
     - JSON context column for flexible metadata (MySQL 5.7+ compatible)
     - Auto-table creation on first use
   - **Security features:**
     - Password encryption using AES-256-CBC with salt
     - Prepared statements for all queries (SQL injection protection)
     - Optional SSL/TLS connection support
   - **Settings UI:**
     - Database host, port, name, user, password
     - Table name (customizable)
     - SSL connection toggle
     - Connection timeout
   - **Test Connection button** with AJAX feedback
   - **Error handling:**
     - Tracks consecutive failures
     - Auto-disables after 5 consecutive errors
     - Shows last error message in settings
     - Logs auto-disable events to Simple History with error details
     - Re-enables when user saves settings
   - **Compliance-ready:** Designed for SOC 2, GDPR, HIPAA, PCI DSS requirements

### 📁 Files Created

**Core Plugin:**
- `inc/integrations/class-integrations-manager.php`
- `inc/integrations/class-integration.php`
- `inc/integrations/integrations/class-file-integration.php`
- `inc/integrations/interfaces/interface-integration-interface.php`
- `inc/services/class-integrations-service.php`
- `inc/services/class-integrations-settings-page.php`
- Multiple test files in `tests/wpunit/`

**Premium Add-on (simple-history-premium):**
- `inc/channels/class-syslog-channel.php`
- `inc/modules/class-syslog-channel-module.php`
- `inc/channels/class-external-database-channel.php`
- `inc/modules/class-external-database-channel-module.php`
- `inc/channels/trait-channel-error-tracking-trait.php`

**CSS Pattern Library:**
- `sh-PremiumTeaser-disabledForm` - Disabled form pattern with `inert` attribute
- `sh-RadioOptions` - Generic radio button group styling
- `sh-InlineFields` - Multi-field row layout
- `sh-InlineField`, `sh-InlineFieldLabel`, `sh-InlineFieldInputWithSuffix`

### Testing Required (before release)

- [ ] File Channel - verify file creation, rotation, retention cleanup
- [ ] Syslog Channel - test local syslog, remote UDP/TCP, error handling
- [ ] External Database Channel - test connection, table creation, event insertion

---

## Log Format Reference

ASCII diagrams showing the structure of each log output format.

### Human-Readable Format (Free)

Best for manual log inspection. Easy to read with timestamps.

```
[2025-12-05T12:34:56Z] INFO SimpleUserLogger: User logged in | message_key=user_logged_in user_id=42 initiator=wp_user
 │                     │    │                 │                │
 │                     │    │                 │                └─ Key-value metadata pairs
 │                     │    │                 └─ Human-readable message
 │                     │    └─ Logger name (which component logged this)
 │                     └─ Log level (INFO, WARNING, ERROR, DEBUG)
 └─ ISO 8601 timestamp in UTC
```

### RFC 5424 Syslog Format (Premium)

Standard syslog format compatible with SIEM tools, rsyslog, and syslog servers.

```
<14>1 2025-12-06T12:28:23Z wordpress-stable-docker-mariadb.test SimpleHistory - plugin_activated [simplehistory@0 level="info" ...] Activated plugin "ACF"
 │  │ │                     │                                    │             │ │                 │                              └─ Human message
 │  │ │                     │                                    │             │ │                 └─ Structured data (key="value" pairs)
 │  │ │                     │                                    │             │ └─ Message ID (event type)
 │  │ │                     │                                    │             └─ Process ID (nil/dash = not applicable)
 │  │ │                     │                                    └─ App name
 │  │ │                     └─ Hostname
 │  │ └─ ISO 8601 timestamp
 │  └─ Syslog version (always 1 for RFC 5424)
 └─ Priority = facility × 8 + severity
```

### JSON Lines / GELF Format (Premium)

Machine-readable format compatible with Graylog, Elasticsearch, Splunk.

```json
{"version":"1.1","host":"example.com","short_message":"User logged in","timestamp":1733487600,"level":6,"_logger":"SimpleUserLogger","_user_id":1}
```

### Format Comparison

| Feature | Human-Readable | RFC 5424 Syslog | JSON Lines (GELF) |
|---------|----------------|-----------------|-------------------|
| Human readable | ✅ Excellent | ✅ Good | ❌ Requires parsing |
| Machine parseable | ⚠️ Regex needed | ⚠️ Syslog parser | ✅ Native JSON |
| Graylog compatible | ❌ | ✅ | ✅ (native GELF) |
| Splunk compatible | ✅ (with config) | ✅ | ✅ |

---

## Password Encryption Approach

The External Database Channel encrypts stored passwords using AES-256-CBC encryption.

### Implementation

Based on Google Site Kit's `Data_Encryption` class:
- **Encryption**: AES-256-CBC via OpenSSL
- **Key**: Derived from `LOGGED_IN_KEY` via SHA-256 hash
- **Salt**: `LOGGED_IN_SALT` prepended to password before encryption
- **IV**: Random 16-byte initialization vector per encryption

### Why LOGGED_IN_KEY instead of SECURE_AUTH_KEY?

`SECURE_AUTH_KEY` is specifically used for HTTPS cookie authentication and is more likely to be rotated after security incidents. `LOGGED_IN_KEY` is slightly more stable.

**Important**: If these keys change, encrypted passwords become unrecoverable - users must re-enter them.

### Double-Encryption Bug Fix

WordPress Settings API can call `sanitize_callback` multiple times per save. Added `is_encrypted_value()` that attempts to decrypt the input - if successful and the salt prefix matches, the value is already encrypted and should not be re-encrypted.

### References

- [Google Site Kit Documentation](https://sitekit.withgoogle.com/documentation/using-site-kit/configure-site-kit-wp-config-keys/)
- [Felix Arntz: Storing Confidential Data in WordPress](https://felix-arntz.me/blog/storing-confidential-data-in-wordpress/)
- [WordPress Trac #61706](https://core.trac.wordpress.org/ticket/61706)

---

## Tools

Useful CLI tools for working with log files generated by this feature.

### Format Compatibility

| Tool | Human-Readable (Free) | JSON/GELF (Premium) | Syslog (Premium) |
|------|:---------------------:|:-------------------:|:----------------:|
| lnav | ✅ | ✅ | ✅ |
| Toolong | ✅ | ✅ | ✅ |
| klp | ✅ | ✅ | ⚠️ |
| hl | ❌ | ✅ | ❌ |
| fblog | ❌ | ✅ | ❌ |
| jq | ❌ | ✅ | ❌ |

### Log Viewers

- **[lnav](https://lnav.org/)** - The Log Navigator. Advanced log file viewer with SQL queries.
- **[Toolong](https://github.com/Textualize/toolong)** - Terminal app to view, tail, merge log files.
- **[klp](https://github.com/dloss/klp)** - Lightweight CLI viewer for structured logs.
- **[hl](https://github.com/pamburus/hl)** - Fast JSON/logfmt log viewer. Written in Rust.
- **[fblog](https://github.com/brocode/fblog)** - Small, fast JSON log viewer. Written in Rust.

### JSON Processing

- **[jq](https://jqlang.github.io/jq/)** - Command-line JSON processor. Example: `cat log.json | jq 'select(.level == 3)'`

### Traditional Unix Tools

```bash
# Tail logs in real-time
tail -f simple-history.log

# Filter by level
grep "ERROR\|WARNING" simple-history.log

# Filter by logger
grep "SimpleUserLogger" simple-history.log
```

---

## Rejected Ideas

### PHP error_log / WP Debug Log Channel

**Rejected:** December 2024

**Reason:** WordPress official documentation states that WP_DEBUG tools are "not recommended for live sites; they are meant for local testing and staging installs."

**Technical issues:**
- Requires `WP_DEBUG=true` to work
- Logs are mixed with all other PHP errors
- No rotation or retention control
- Destination controlled by server config, not Simple History

**Alternative:** The File Channel provides a production-ready solution with dedicated log files, configurable rotation, and retention limits.

---

## Related Issues

- #608 (Alerts & Notifications - split from this issue)
- #209
- #114
- #366
- Simple-History-Add-Ons #56
