# Issue #573: Log Forwarding & Integrations

**Branch:** issue-573-log-forwards-destinations

## Overview

Extend Simple History to forward events to external destinations beyond the WordPress database.

## Completed

### File Channel (Free)
- Automatic event logging to local files
- Rotation: daily, weekly, monthly
- Retention settings
- Security: .htaccess protection, index.php
- Human-readable format

### Syslog Channel (Premium)
- Local syslog via PHP `syslog()`
- Remote rsyslog via UDP/TCP
- RFC 5424 format
- Test connection button
- Auto-disable after consecutive failures

### Premium File Formatters
- JSON Lines (GELF) - Graylog, ELK, Splunk compatible
- Logfmt - Grafana Loki, Prometheus compatible
- RFC 5424 Syslog - SIEM tools compatible

### Settings UI
- Integrations tab in Settings
- Premium feature teasers with disabled form pattern
- Benefit-focused copy

## Next Steps

**Premium Integrations:**
- Slack webhooks
- Email alerts
- Discord
- HTTP webhooks

**Rule/Filter System:**
- Allow filtering which events to forward
- Query builder UI (research: React Query Builder + JsonLogic)

## Architecture

Two integration types:
1. **Log Destinations** - Archive everything (File, Syslog, external DB)
2. **Alert Integrations** - Notify on specific events (Slack, Email, webhooks)

## Related

- #209, #114, #366
- simple-history-add-ons #56
