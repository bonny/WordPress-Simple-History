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

**Design Principles:**
1. **Consistent naming:** Prefix all negative filters with `exclude_`
2. **Mirror positive filters:** Each positive filter has an exact negative counterpart
3. **Same data types:** Negative filters accept same types as positive (string/array)
4. **Conflict resolution:** Exclusion takes precedence (SQL AND logic)
5. **Future-proof:** Easy to extend with new filter types
6. **REST API ready:** Parameter names work in URLs

**Proposed Parameters:**

| Positive Filter | Negative Filter | Type | Example |
|----------------|-----------------|------|---------|
| `search` | `exclude_search` | string | "plugin activated" |
| `loglevels` | `exclude_loglevels` | array/string | ['debug','info'] |
| `loggers` | `exclude_loggers` | array/string | ['SimpleUserLogger'] |
| `messages` | `exclude_messages` | array/string | ['SimplePluginLogger:plugin_activated'] |
| `users` | `exclude_users` | array/string | [1,2,3] |
| `user` | `exclude_user` | int | 5 |
| `initiator` | `exclude_initiator` | array/string | ['wp_cron','wp_cli'] |

**SQL Implementation:**
- Positive: `level IN ('info', 'debug')`
- Negative: `level NOT IN ('error', 'warning')`
- Combined: `level IN ('info', 'debug') AND level NOT IN ('warning')`

**Conflict Handling (Option 1 - Exclusion Takes Precedence):**
```php
// User requests:
loggers = ['SimplePluginLogger', 'SimpleUserLogger']
exclude_loggers = ['SimpleUserLogger']

// SQL generated:
WHERE logger IN ('SimplePluginLogger', 'SimpleUserLogger')
  AND logger NOT IN ('SimpleUserLogger')

// Result: Only SimplePluginLogger shown (exclusion wins)
```

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
- [x] Design negative filter parameters
- [x] Add parameter handling to `prepare_args()` method
- [x] Implement negative WHERE clauses in `get_inner_where()`
- [x] Add negative search support - created `add_exclude_search_to_inner_where_query()`
- [x] Update REST API parameter schema
- [x] Map REST API parameters in `get_items()` method
- [x] Test with REST API calls
- [x] PHP linting passes
- [x] Write unit tests for negative filters
- [x] Update user documentation
- [x] Add WP-CLI parameter support
- [x] Add WP-CLI usage examples to documentation

## Implementation Summary

### Backend Implementation ✅ Complete

**Files Modified:**
1. `/Users/bonny/Projects/Personal/WordPress-Simple-History/inc/class-log-query.php`
2. `/Users/bonny/Projects/Personal/WordPress-Simple-History/inc/class-wp-rest-events-controller.php`
3. `/Users/bonny/Projects/Personal/WordPress-Simple-History/inc/services/wp-cli-commands/class-wp-cli-list-command.php`

**What Was Implemented:**

1. **Seven New Filter Parameters** - all following `exclude_*` naming convention:
   - `exclude_search` - Exclude events containing these words
   - `exclude_loglevels` - Exclude specific log levels
   - `exclude_loggers` - Exclude specific loggers
   - `exclude_messages` - Exclude specific logger:message pairs
   - `exclude_user` - Exclude events from a single user ID
   - `exclude_users` - Exclude events from multiple user IDs
   - `exclude_initiator` - Exclude events from specific initiators

2. **Parameter Processing** (lines 1039-1148 in Log_Query):
   - Full validation and sanitization
   - Same data type handling as positive filters (string/array)
   - Converts comma-separated strings to arrays
   - Validates initiator values against allowed constants

3. **SQL WHERE Clause Generation** (lines 1607-1688):
   - Uses `NOT IN` for array filters
   - Uses `!=` for single value filters
   - `add_exclude_search_to_inner_where_query()` method for text exclusion
   - Properly escapes all values using `$wpdb->prepare()`

4. **REST API Integration**:
   - All seven parameters registered in `get_collection_params()`
   - Proper OpenAPI schema with descriptions
   - Mapped to Log_Query in `get_items()` method
   - Reuses existing validation callbacks for initiator

5. **WP-CLI Integration**:
   - Added six exclusion parameters to `list` command
   - Parameter names: `--exclude_search`, `--exclude_log_level`, `--exclude_logger`, `--exclude_message`, `--exclude_user`, `--exclude_initiator`
   - Full validation using existing `parse_comma_separated_values()` method
   - Supports comma-separated values for all array parameters
   - Works with all output formats (table, json, csv, yaml)

### Testing Results ✅

All manual tests passed:

```bash
# Test 1: Exclude debug level events
curl 'http://wordpress-stable-docker-mariadb.test:8282/wp-json/simple-history/v1/events?per_page=5&exclude_loglevels[]=debug'
✅ Result: No debug events returned

# Test 2: Exclude events containing "cron"
curl 'http://wordpress-stable-docker-mariadb.test:8282/wp-json/simple-history/v1/events?per_page=5&exclude_search=cron'
✅ Result: No events with "cron" in message

# Test 3: Combine positive and negative filters
curl 'http://wordpress-stable-docker-mariadb.test:8282/wp-json/simple-history/v1/events?per_page=10&loglevels[]=info&exclude_search=action_scheduler'
✅ Result: Only info level, no "action_scheduler" events
```

### Conflict Resolution

When the same value appears in both inclusion and exclusion filters, **exclusion takes precedence**:

```php
// Example: User requests both inclusion and exclusion
loggers = ['SimplePluginLogger', 'SimpleUserLogger']
exclude_loggers = ['SimpleUserLogger']

// SQL generated:
WHERE logger IN ('SimplePluginLogger', 'SimpleUserLogger')
  AND logger NOT IN ('SimpleUserLogger')

// Result: Only 'SimplePluginLogger' events shown
```

This is handled automatically by SQL's AND logic - no special conflict detection needed.

### REST API Usage Examples

```bash
# Exclude debug and info levels
/wp-json/simple-history/v1/events?exclude_loglevels[]=debug&exclude_loglevels[]=info

# Exclude specific logger
/wp-json/simple-history/v1/events?exclude_loggers[]=SimpleUserLogger

# Exclude events containing specific words
/wp-json/simple-history/v1/events?exclude_search=error

# Exclude events from user ID 5
/wp-json/simple-history/v1/events?exclude_user=5

# Exclude multiple users
/wp-json/simple-history/v1/events?exclude_users[]=1&exclude_users[]=2

# Exclude WP-Cron events
/wp-json/simple-history/v1/events?exclude_initiator=wp_cron

# Combine multiple exclusions
/wp-json/simple-history/v1/events?exclude_loglevels[]=debug&exclude_search=cron&exclude_initiator=wp_cli
```

### WP-CLI Usage Examples

```bash
# Exclude debug level events
wp simple-history list --exclude_log_level=debug --count=50

# Exclude events containing "cron"
wp simple-history list --exclude_search=cron --count=50

# Exclude WordPress-initiated events (cron jobs, automatic updates)
wp simple-history list --exclude_initiator=wp --count=50

# Combine positive and negative filters
wp simple-history list --log_level=info --exclude_search=cron --count=50

# Exclude multiple log levels and initiators
wp simple-history list --exclude_log_level=debug,info --exclude_initiator=wp,wp_cli --count=100

# JSON output with exclusions
wp simple-history list --exclude_log_level=debug --exclude_search=action_scheduler --format=json --count=50
```

**Manual WP-CLI Testing:**
```bash
# Test 1: Exclude debug level - ✅ PASSED
wp simple-history list --exclude_log_level=debug --count=5
# Result: Only info/warning/error events returned

# Test 2: Exclude by search term - ✅ PASSED
wp simple-history list --exclude_search=action_scheduler --count=5
# Result: No events containing "action_scheduler"

# Test 3: Exclude WordPress system events - ✅ PASSED
wp simple-history list --exclude_initiator=wp --count=10
# Result: Only user-initiated events

# Test 4: Combined filters - ✅ PASSED
wp simple-history list --exclude_log_level=debug --exclude_initiator=wp --count=10
# Result: No debug AND no WordPress system events

# Test 5: JSON output - ✅ PASSED
wp simple-history list --exclude_log_level=debug --exclude_search=REST --count=3 --format=json
# Result: Valid JSON with filtered events
```

### URL Query String Support ✅ Complete

Negative filters can now be tested via URL without GUI changes!

**Files Modified:**
1. `src/components/EventsGui.jsx` - Added 7 new `useQueryState` hooks for exclude-* parameters (read-only, no setters)
2. `src/functions.js` - Updated `generateAPIQueryParams()` to handle exclude-* URL params (~70 lines)
3. `inc/class-helpers.php` - Updated `get_filtered_events_url()` PHP helper (~30 lines)

**Implementation Details:**
- URL parameters are **read-only** (users manually add to URL)
- No setters defined - keeps code clean until Phase 2 GUI
- `useQueryState` hooks only destructure the value, not the setter
- When GUI is added, simply add setters back: `const [ value, setValue ] = useQueryState(...)`

**URL Parameters Added:**
- `exclude-search` - Exclude by search text
- `exclude-levels` - Exclude log levels (JSON array)
- `exclude-loggers` - Exclude loggers (JSON array)
- `exclude-messages` - Exclude messages (JSON array)
- `exclude-users` - Exclude user IDs (JSON array)
- `exclude-initiator` - Exclude initiators (JSON array)
- `exclude-context` - Exclude context filters (key:value)

**Example Test URLs:**
```
# Exclude debug events
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-levels=["debug"]

# Exclude events containing "action_scheduler"
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-search=action_scheduler

# Exclude WordPress system events (cron jobs)
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-initiator=[{"value":"wp"}]

# Exclude PluginUserSwitchingLogger
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-loggers=["PluginUserSwitchingLogger"]

# Combined: Info events without cron
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&levels=["info"]&exclude-search=cron

# Clean log: No debug, no system events, no action_scheduler
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-levels=["debug"]&exclude-initiator=[{"value":"wp"}]&exclude-search=action_scheduler
```

**Documentation:** See `docs/url-negative-filters-testing.md` for complete testing guide with 10+ examples

**PHP Helper Usage:**
```php
use Simple_History\Helpers;

$url = Helpers::get_filtered_events_url([
    'exclude_loglevels' => ['debug'],
    'exclude_search' => 'cron',
]);
echo '<a href="' . esc_url( $url ) . '">View Filtered Events</a>';
```

### Future Work

**Phase 2: Frontend UI** (Separate Issue)
- Add checkbox UI for negative filters
- Add toggle buttons next to existing filter dropdowns
- Add "NOT" mode switcher for each filter type
- Wire up GUI controls to existing URL state hooks (already implemented)
- Note: All backend work is complete - Phase 2 is purely UI/UX

### Code Quality

- ✅ All PHP linting passes (`npm run php:lint`)
- ✅ Follows WordPress coding standards
- ✅ Consistent with existing code patterns
- ✅ Properly documented with PHPDoc
- ✅ Uses prepared statements (SQL injection safe)
- ✅ Future-proof parameter naming

### Performance Considerations

- Negative filters use indexed columns (logger, level, initiator)
- Context-based exclusions use existing indexes on history_id
- NOT IN queries are efficient for small exclusion lists
- Caching still works (cache key includes all filter args)

## Tests Added ✅

**File:** `tests/wpunit/NegativeFiltersTest.php`

**Test Coverage:**
- ✅ `test_exclude_loglevels_via_log_query()` - Exclude single log level
- ✅ `test_exclude_multiple_loglevels()` - Exclude multiple log levels
- ✅ `test_exclude_search_via_log_query()` - Exclude by search text
- ✅ `test_exclude_search_multiple_words()` - Exclude by multiple words (AND logic)
- ✅ `test_positive_and_negative_filters_combined()` - Verify exclusion precedence
- ✅ `test_exclude_loglevels_via_rest_api()` - REST API exclusion
- ✅ `test_exclude_search_via_rest_api()` - REST API search exclusion
- ✅ `test_exclude_loggers()` - Exclude specific loggers
- ✅ `test_exclude_loglevels_validation()` - Parameter validation (string/array)
- ✅ `test_invalid_exclude_parameter_throws_exception()` - Error handling
- ✅ `test_exclude_user()` - Exclude specific user
- ✅ `test_exclude_initiator()` - Exclude specific initiator

**Total:** 12 comprehensive tests covering all negative filter functionality.

## Documentation Added ✅

### 1. Comprehensive Usage Guide
**File:** `docs/filters-usage-examples.md` (~400 lines)

**Contents:**
- PHP Log_Query examples (basic, advanced, combined filters)
- REST API examples (bash, JavaScript)
- WP-CLI integration examples
- Filter combination best practices
- Conflict resolution examples
- Complete filter reference table
- Use case examples (security monitoring, debugging, auditing)
- Performance tips

### 2. Updated REST API Documentation
**File:** `docs/rest-api.md`

**Added:**
- Negative filter parameter table
- Exclusion filter usage examples
- Conflict resolution demonstration
- JavaScript examples with negative filters
- Complex filtering scenarios
- Link to comprehensive usage guide

### 3. In-Code Documentation
**File:** `inc/class-log-query.php`

**Added:**
- Class-level usage examples in docblock
- Positive filter examples
- Negative filter examples
- Combined filter examples
- Conflict resolution example
- Reference to usage documentation

## Summary

### What Was Delivered

✅ **7 New Filter Parameters** - Complete negative filtering system
✅ **~500 Lines of Backend Code** - Robust, production-ready implementation (PHP + JavaScript)
✅ **12 Unit Tests** - Comprehensive test coverage
✅ **~800 Lines of Documentation** - Complete usage guide with examples
✅ **REST API Integration** - Fully functional and documented
✅ **WP-CLI Integration** - All exclusion filters available via command line
✅ **URL Query String Support** - Test negative filters via URL without GUI
✅ **PHP Helper Function** - `get_filtered_events_url()` supports all negative filters
✅ **Code Quality** - Passes all linting, follows WordPress standards

### Ready for Production

The implementation is:
- ✅ **Tested** - Manual and unit tests pass
- ✅ **Documented** - PHP, REST API, and WP-CLI usage covered
- ✅ **Secure** - Uses prepared statements, proper escaping
- ✅ **Performant** - Uses database indexes, efficient queries
- ✅ **Future-proof** - Clean architecture, easy to extend
- ✅ **Backward compatible** - No breaking changes

### Phase 2: GUI (Future)

The backend is complete and ready for GUI implementation:
- Add checkbox/toggle UI for negative filters
- Update React filter state management
- Wire up existing REST API parameters
- Add "NOT" buttons next to filter dropdowns

All the hard work is done - Phase 2 is just UI wiring!
