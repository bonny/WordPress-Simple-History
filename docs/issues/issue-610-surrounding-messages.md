# Issue 610: Add "Show surrounding messages" feature

## Overview

Add a feature to show events before and after a specific event, regardless of initiator or logger - simply displaying events as they exist in the database chronologically.

## Feature Request Details

- **Requested by**: Users who need to see context around specific events
- **Inspiration**: GrayLog's "Show surrounding messages" feature
- **Use case**: Debugging scenarios where users want to see what happened before/after an event without losing current search/pagination

## Decisions Made

### Scope

- **No filter GUI changes** - This feature won't add anything to the filters interface
- **Backend**: REST API, Log_Query, and WP-CLI support
- **Frontend**: Add menu item to event dropdown menu (see below)

### Technical Approach

**ID-based filtering** (not date-based):
- IDs are sequential and deterministic
- Simpler queries, no date parsing edge cases
- The `id` column is indexed for fast lookups
- Avoids timezone complications

**Parameters:**
- `surrounding_event_id` (integer) - The center event ID
- `surrounding_count` (integer, default 5) - Number of events before AND after

Example: `surrounding_count=5` returns up to 11 events (5 before + target + 5 after)

**Behavior:**
- Ignores pagination params (`page`, `offset`) when in surrounding mode
- Other filters (loggers, users, etc.) are **ignored** - shows raw chronological events
- Returns events ordered chronologically with metadata indicating the center event

### Permission Model

**Admin-only feature:**
- Requires `manage_options` capability (administrator)
- Reason: Bypasses normal logger-based permission checks, could expose events users shouldn't see

**Error handling:**
- If non-admin tries to use `surrounding_event_id`: return 403 error and abort
- Clear error message explaining permission requirement
- Do NOT silently fall back to normal query

### Implementation Layers

| Layer | Parameters | Example |
|-------|------------|---------|
| **Log_Query** | `surrounding_event_id`, `surrounding_count` | `$query->query(['surrounding_event_id' => 123, 'surrounding_count' => 5])` |
| **REST API** | Query params on `/events` | `GET /events?surrounding_event_id=123&surrounding_count=5` |
| **WP-CLI** | Command flags | `wp simple-history list --surrounding_event_id=123 --surrounding_count=5` |

**Permission checks:**
- REST API and WP-CLI handle permission checking
- Log_Query remains a pure data layer (no permission logic)

### Response Structure

**REST API:**
```json
{
  "events": [...],
  "center_event_id": 123,
  "total_count": 11
}
```

**Error response (non-admin):**
```json
{
  "code": "rest_forbidden",
  "message": "You do not have permission to view surrounding events.",
  "data": { "status": 403 }
}
```

**WP-CLI:**
- Table output with center event highlighted
- Error message if insufficient permissions

### Frontend (Event Menu)

Add a new menu item "Show surrounding events" to the event dropdown menu:

```
View event details
Copy link to event details
---
Copy event message
Copy detailed event message
---
Filter event by this event type
Stick event to top...
---
Show surrounding events    <-- NEW (opens in new tab)
```

**Behavior:**
- Opens in a new browser tab (preserves current search/pagination)
- Links to the event log page with `surrounding_event_id` parameter
- Only visible to administrators

## Files to Modify

1. **Log_Query**: `inc/class-log-query.php`
   - Add surrounding events query logic
   - Handle `surrounding_event_id` and `surrounding_count` parameters

2. **REST API**: `inc/class-wp-rest-events-controller.php`
   - Add parameter definitions
   - Add permission check for admin capability
   - Return error for non-admins

3. **WP-CLI**: `inc/services/wp-cli-commands/class-wp-cli-list-command.php`
   - Add `--surrounding_event_id` and `--surrounding_count` flags
   - Add permission check
   - Highlight center event in output

4. **Frontend (React)**: `src/components/EventActionsMenu.jsx` (or similar)
   - Add "Show surrounding events" menu item
   - Only show for administrators
   - Opens new tab with surrounding events URL

## Progress

- [x] Design the feature approach
- [ ] Implement Log_Query support
- [ ] Implement REST API support
- [ ] Implement WP-CLI support
- [ ] Implement frontend menu item
- [ ] Testing

## Open Questions (for later)

- Should results be newest-first (default) or oldest-first for surrounding view?
- Edge cases: If target event is near start of log, compensate with more events after?
