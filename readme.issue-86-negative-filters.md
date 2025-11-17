# Issue #86: Negative filters

**Status:** Backlog → In Progress
**Labels:** enhancement, log query

## Description

Add support for negative filters in the event log query interface:

- NOT containing words (search exclusion)
- NOT log level (e.g., exclude debug, info, etc.)

This would allow users to filter out events they don't want to see, making it easier to focus on relevant events.

## Implementation Notes

This will require changes to:
- Filter UI components (React) - Future work, separate issue
- Search/filter query logic - Core implementation
- REST API endpoint parameters - Add support for negative parameters
- Database query building - Modify WHERE clause generation

### Current Filter Architecture

**Main Query Class:** `inc/class-log-query.php`
- Entry point: `query()` method (line 41)
- WHERE clause building: `get_inner_where()` method (lines 1205-1462)
- Search implementation: `add_search_to_inner_where_query()` (lines 1520-1600)

**Current Filter Types:**
- `search`: Text search in message, logger, level, and context values
- `loglevels`: Array of log levels (e.g., ['info', 'debug'])
- `loggers`: Array of logger slugs
- `messages`: Array of logger:message pairs
- `users`: Array of user IDs
- `initiator`: Single or array of initiators (wp_user, wp_cli, etc.)
- `context_filters`: Key-value pairs for context data

**Filter Logic:**
- All filters use AND logic (must match all criteria)
- Within a filter type, uses IN clause (OR logic)
- Example: `level IN ('info', 'debug')` - shows info OR debug

### Implementation Strategy

**Phase 1: Backend Support (This Issue)**
1. Add negative filter parameters to Log_Query class
2. Modify WHERE clause generation to handle exclusions
3. Update REST API to accept negative parameters
4. Add tests

**Phase 2: Frontend UI (Future Issue)**
1. Add UI controls for negative filters
2. Update filter state management
3. Wire up to REST API calls

### Negative Filter Design

**Proposed Parameters:**
- `exclude_search`: Text to exclude (NOT containing these words)
- `exclude_loglevels`: Array of log levels to exclude
- `exclude_loggers`: Array of loggers to exclude
- `exclude_messages`: Array of logger:message pairs to exclude
- `exclude_users`: Array of user IDs to exclude
- `exclude_initiator`: Initiator(s) to exclude

**SQL Implementation:**
- Positive: `level IN ('info', 'debug')`
- Negative: `level NOT IN ('error', 'warning')`
- Combined: `level IN ('info', 'debug') AND level NOT IN ('warning')`

## Current Analysis

### How Search Works (lines 1520-1600)

The current search splits words and creates an OR query across:
1. Main table columns (message, logger, level) - must match ALL words
2. Context table values - must match ALL words
3. Translated logger messages - exact matches

Example SQL for search "plugin activated":
```sql
(
  ( message LIKE "%plugin%" AND message LIKE "%activated%" )
  OR ( logger LIKE "%plugin%" AND logger LIKE "%activated%" )
  OR ( level LIKE "%plugin%" AND level LIKE "%activated%" )
  OR (
    id IN ( SELECT history_id FROM contexts WHERE value LIKE "%plugin%" ) AND
    id IN ( SELECT history_id FROM contexts WHERE value LIKE "%activated%" )
  )
)
```

### How Level/Logger Filtering Works (lines 1375-1393)

Uses IN clause with prepared statements:
```php
// Positive filter
$inner_where[] = $wpdb->prepare(
    "level IN ({$placeholders})",
    ...$args['loglevels']
);
```

For negative filters, we'll use NOT IN:
```php
// Negative filter
$inner_where[] = $wpdb->prepare(
    "level NOT IN ({$placeholders})",
    ...$args['exclude_loglevels']
);
```

## Progress

- [x] Analyze current filter implementation
- [ ] Design negative filter parameters
- [ ] Add parameter handling to `prepare_args()` method
- [ ] Implement negative WHERE clauses in `get_inner_where()`
- [ ] Add negative search support to `add_search_to_inner_where_query()`
- [ ] Update REST API parameter schema
- [ ] Add tests for negative filters
- [ ] Update documentation/docblocks
