# Issue 579: Statistics Not Aligned

## Problem Summary
Statistics shown in different parts of Simple History are inconsistent, showing different counts for the same time periods across the sidebar stats box, dedicated stats page, and email reports.

## Root Causes Identified

### 1. Event Grouping Difference ✅ WORKING AS INTENDED
- **Main log GUI**: Groups similar events by `occasionsID` for readability (e.g., 500 failed logins = 1 grouped row)
- **Stats counts**: Count ALL individual events (`SELECT count(*)`) - this is correct behavior
- **Design**: Stats should reflect actual event volume, while GUI groups for usability
- **Not a bug**: Different display purposes require different approaches

### 2. User Permission Cache Issue ✅ FIXED
- **Problem**: Cache keys didn't include user capabilities
- **Result**: All users saw the same cached counts regardless of their permissions
- **Fix**: Cache key now includes `$loggers_slugs` based on user capabilities (line 326)

### 3. Timezone Inconsistencies
- **Stats Service**: Uses UTC ✅
- **Sidebar/Email/REST API**: Use server timezone ❌
- **Result**: Day boundary mismatches

### 4. Additional Issues
- No cache invalidation when events are logged
- Total events count is global (not user-filtered)
- Chart inherits all the same problems

## Recommended Solution

### Stats Display Strategy
**Stats should show user-filtered counts** - users see statistics for events they have permission to view.

#### Implementation:
1. **Stats Box** (all users):
   - Filter by `get_loggers_that_user_can_read()`
   - Cache per capability set (not per individual user)
   - Show contextual information

2. **Stats Page** (admins only):
   - Show complete statistics
   - Optional: Add toggle to see stats from other roles' perspective

### User Communication
For non-admin users, make it clear they see filtered stats:

```php
// Dynamic heading based on role
if (current_user_can('administrator')) {
    $subtitle = __('All events', 'simple-history');
} else {
    $subtitle = __('Events you can view', 'simple-history');
}

// Info tooltip
<span class="sh-Tooltip" aria-label="Shows events you have permission to view">ⓘ</span>

// Context line showing what they can see
echo sprintf(__('Showing: %s', 'simple-history'), 'Posts, pages, comments, and media changes');
```

## Files to Fix

### Priority 1 - Event Counting ✅ WORKING AS INTENDED
- Stats should count ALL individual events (current behavior is correct)
- Main log GUI groups events for readability (already implemented via `occasionsID`)
- No changes needed

### Priority 2 - Cache Keys ✅ COMPLETED
- ✅ `/dropins/class-sidebar-stats-dropin.php` - Line 326: Cache key includes `$loggers_slugs` based on user capabilities
- ✅ `/inc/class-helpers.php` - Lines 1298, 1328: Helper functions filter by `get_loggers_that_user_can_read()` (no longer cache)

### Priority 3 - Timezone ✅ COMPLETED
- ✅ `/inc/services/class-email-report-service.php` - Lines 198, 244, 504: Now use `Date_Helper` methods
- ✅ `/inc/class-wp-rest-stats-controller.php` - Line 235: Now uses `Date_Helper::get_default_date_range()`
- ✅ `/dropins/class-sidebar-stats-dropin.php` - Lines 178, 179, 356, 357: Now use `Date_Helper` methods

### Priority 4 - Cache Invalidation (DECISION: NOT IMPLEMENTING)
- **Original idea**: Clear transients when events are logged
- **Decision**: Keep 5-minute cache for performance reasons
- **Rationale**: Clearing cache on every event would be inefficient for busy sites
- **Solution**: Inform users about the 5-minute refresh interval

## COMPLETED WORK ✅

### Fixed Multi-Layer Caching Issue (Dec 2024)

**Problem**: The sidebar stats widget had conflicting cache layers:
- Helper functions cached for 1 hour
- Sidebar cached for 5 minutes
- Chart data bypassed cache entirely
- Result: Cache synchronization issues and stale data after new events were logged

**Solution Implemented**:
1. **Removed caching from helper functions** (`inc/class-helpers.php`):
   - `get_num_events_last_n_days()` - removed transient caching
   - `get_num_events_per_day_last_n_days()` - removed transient caching

2. **Consolidated caching at sidebar level** (`dropins/class-sidebar-stats-dropin.php`):
   - `get_quick_stats_data()` now caches chart data along with stats
   - `get_chart_data()` updated to use cached data instead of calling helpers directly
   - Single 5-minute cache for all sidebar data

**Benefits Achieved**:
- ✅ Single cache layer eliminates synchronization issues
- ✅ Eliminated cache desync between helper functions and sidebar
- ✅ Caching moved to "user layer" (presentation layer)
- ✅ Simpler architecture and maintenance
- ⚠️ Data still cached for 5 minutes (sidebar cache remains)

**Files Modified**:
- `/inc/class-helpers.php` - Lines 1295-1374: Removed caching from helper functions
- `/dropins/class-sidebar-stats-dropin.php` - Lines 317-347, 167: Added chart data to cache, updated function signature

**Testing**: Confirmed working correctly - sidebar stats now update properly when new events are logged.

### Added Cache Refresh Notice (Dec 2024)

**Implementation**: Added "Updates every 5 minutes" text to sidebar stats to inform users about refresh interval
- **File Modified**: `/dropins/class-sidebar-stats-dropin.php` - Lines 370-372
- **Approach**: Non-intrusive text added to existing permission-based message
- **Result**: Users now understand why stats may not immediately reflect new events

### Optimized Cache Data Fetching (Oct 2024)

**Problem**: Cache was fetching `total_events` and `top_users` for all users, even though only admins can view this data.

**Solution Implemented**:
1. **Conditional data fetching** (`dropins/class-sidebar-stats-dropin.php` lines 345-356):
   - `total_events` only fetched when user has `manage_options` capability
   - `top_users` only fetched when user has `list_users` capability

2. **Updated cache key** to include user capabilities:
   - Cache key now includes `$current_user_can_manage_options` and `$current_user_can_list_users`
   - Ensures separate cache entries for different permission levels

3. **Added defensive checks** when displaying data:
   - Added `isset()` checks before accessing `total_events` and `top_users` in cache

**Benefits**:
- ✅ Improved performance for non-admin users (fewer database queries)
- ✅ Better security (separate cache entries per permission level)
- ✅ More efficient resource usage

### Created Date_Helper Class for WordPress Timezone-Aware Operations (Oct 2024)

**Problem**: Date/time calculations scattered throughout codebase, using server timezone (UTC) instead of WordPress timezone setting.

**Solution Implemented**:
1. **Created centralized Date_Helper class** (`/inc/class-date-helper.php`):
   - Renamed from `Constants` class to better reflect purpose
   - All methods respect WordPress timezone setting from Settings > General
   - Single source of truth for all date/time calculations

2. **New timezone-aware timestamp methods**:
   - `get_current_timestamp()` - Current Unix timestamp
   - `get_today_start_timestamp()` - Today at 00:00:00 in WP timezone
   - `get_today_end_timestamp()` - Today at 23:59:59 in WP timezone
   - `get_n_days_ago_timestamp($days)` - N days ago at 00:00:00 in WP timezone

3. **New date range helper methods**:
   - `get_default_date_range()` - Last 30 days to end of today
   - `get_last_n_days_range($days)` - Last N days to end of today
   - `get_period_range($period)` - Range for 'week', 'month', 'fortnight', 'quarter'

4. **Timezone utility methods**:
   - `get_wp_timezone()` - Returns WordPress DateTimeZone object
   - `get_wp_timezone_string()` - Returns timezone string (e.g., 'Europe/Stockholm')

**Implementation Details**:
- Uses `DateTimeImmutable` with `wp_timezone()` for proper timezone handling
- Returns timezone-neutral Unix timestamps for database queries
- Follows WordPress 5.3+ best practices (uses `wp_date()`, `wp_timezone()`)

**Why This Matters - The Timezone Difference Explained**:

*Old approach using `strtotime()`*:
```php
strtotime("-30 days")  // Uses server timezone (typically UTC)
```
- Calculates from current server time, not day boundaries
- Example: If server is UTC and it's Oct 4, 2025 17:00 UTC
  - Returns: Sep 4, 2025 17:00 UTC
- **Problem**: Ignores WordPress timezone setting, misaligned day boundaries

*New approach using `Date_Helper`*:
```php
Date_Helper::get_n_days_ago_timestamp(30)  // Uses WordPress timezone
```
- Calculates from midnight (00:00:00) in WordPress timezone
- Example: If WordPress timezone is Europe/Stockholm (UTC+2) on Oct 4, 2025
  - Returns: Sep 4, 2025 00:00:00 Stockholm time (Sep 3, 2025 22:00 UTC)
- **Benefit**: Respects user's timezone, predictable day boundaries

**Real-World Impact**:

Scenario: WordPress in New York (UTC-5), server in UTC. It's Oct 4, 2025 1:00 AM NY time.

| Method | Calculates | Result | Issue |
|--------|------------|--------|-------|
| `strtotime("-1 days")` | 1 day ago from current UTC time | Oct 3, 1:00 AM NY | Misses first hour of Oct 3! |
| `Date_Helper::get_n_days_ago_timestamp(1)` | Yesterday at midnight NY time | Oct 3, 00:00 AM NY | Correct - full day ✓ |

This ensures stats like "Yesterday" and "Last 30 days" align with what users see in WordPress admin, not server time.

**Files Modified**:
- Created: `/inc/class-date-helper.php` (renamed from `class-constants.php`)
- Updated 6 files to use `Date_Helper` instead of `Constants`:
  - `/inc/class-helpers.php`
  - `/inc/class-simple-history.php`
  - `/inc/services/class-email-report-service.php`
  - `/inc/class-wp-rest-stats-controller.php`
  - `/dropins/class-sidebar-stats-dropin.php`

**Testing**: All methods verified to correctly respect WordPress timezone (tested with Europe/Stockholm UTC+2).

**Benefits**:
- ✅ Centralized date/time logic - easier to maintain
- ✅ WordPress timezone-aware - respects user settings
- ✅ Consistent behavior across plugin
- ✅ Foundation for fixing Priority 3 (Timezone Inconsistencies)
- ✅ Better code organization and self-documentation

### Fixed Timezone Issues in Stats Helpers and Sidebar (Oct 2024)

**Problem**: Helper functions and sidebar stats were still using `strtotime()` directly, causing timezone and counting inconsistencies:
1. Used server timezone (UTC) instead of WordPress timezone
2. "Today" counted grouped occasions while "Week/Month" counted individual events
3. "Today" didn't respect user permissions properly

**Solution Implemented**:

1. **Updated helper functions to use `Date_Helper`** (`/inc/class-helpers.php`):
   - `get_num_events_last_n_days()` - Line 1308: Now uses `Date_Helper::get_n_days_ago_timestamp()`
   - `get_num_events_per_day_last_n_days()` - Lines 1348, 1367: Now uses `Date_Helper::get_n_days_ago_timestamp()` (both MySQL and SQLite)
   - Added new `get_num_events_today()` method (lines 1320-1347):
     - Uses `Date_Helper::get_today_start_timestamp()`
     - Counts individual events (not grouped occasions)
     - Respects user permissions via `get_loggers_that_user_can_read()`

2. **Updated sidebar stats** (`/dropins/class-sidebar-stats-dropin.php`):
   - Line 343: Changed from `Events_Stats::get_num_events_today()` to `Helpers::get_num_events_today()`
   - Lines 178-179: Chart period calculation now uses `Date_Helper` methods
   - Lines 356-357: Top users date range now uses `Date_Helper` methods

3. **Enhanced function documentation**:
   - All three helper functions now explicitly document that they respect user permissions
   - Clear documentation: "only counts events from loggers the current user can view"

**Benefits Achieved**:
- ✅ WordPress timezone-aware: All calculations respect Settings > General timezone
- ✅ Consistent counting: All stats count individual events (not grouped occasions)
- ✅ User permissions respected: All methods filter by `get_loggers_that_user_can_read()`
- ✅ Single source of truth: All date calculations use `Date_Helper`
- ✅ Better documentation: Permission filtering is now explicit in PHPDoc

**Testing**: Verified with Europe/Stockholm (UTC+2) timezone - all calculations correctly use WordPress timezone, not server UTC.

### Fixed Timezone Issues in Email Reports and REST API (Oct 2024)

**Problem**: Email Report Service and REST API Controller were using server timezone (UTC) instead of WordPress timezone, causing inconsistencies with sidebar stats.

**Solution Implemented**:

1. **Email Report Service** (`/inc/services/class-email-report-service.php`):
   - `rest_preview_email()` - Lines 198-199: Now uses `Date_Helper::get_n_days_ago_timestamp()` and `Date_Helper::get_current_timestamp()`
   - `rest_preview_html()` - Lines 244-245: Now uses `Date_Helper::get_n_days_ago_timestamp()` and `Date_Helper::get_current_timestamp()`
   - `send_email_report()` - Lines 504-505: Now uses `Date_Helper::get_n_days_ago_timestamp()` and `Date_Helper::get_current_timestamp()`

2. **REST API Controller** (`/inc/class-wp-rest-stats-controller.php`):
   - `get_default_date_range()` - Line 235: Simplified to use `Date_Helper::get_default_date_range()`
   - Changed from 9 lines of custom `DateTime` code to 1 line delegating to `Date_Helper`
   - Removed server timezone dependency (old code used `new \DateTime('today')` which defaulted to UTC)

**Benefits Achieved**:
- ✅ **Complete timezone consistency**: All components now use WordPress timezone
  - Sidebar stats ✅
  - Helper functions ✅
  - Email reports ✅
  - REST API ✅
  - Chart data ✅
- ✅ **Simpler code**: Delegates to `Date_Helper` instead of duplicating logic
- ✅ **Priority 3 (Timezone Inconsistencies) - FULLY RESOLVED**

**Testing**: Verified all components correctly use WordPress timezone setting from Settings > General.

## Expected Outcomes
- ✅ Consistent counts across all statistics displays (COMPLETED - all stats count individual events)
- ✅ Correct permission-based filtering (COMPLETED - all helpers filter by user permissions)
- ✅ Accurate timezone handling (COMPLETED - all components use WordPress timezone via Date_Helper)
- ✅ Clear communication to users about what they're seeing (COMPLETED - added cache refresh notice)
- ✅ Performance-friendly caching (COMPLETED - kept 5-minute cache for efficiency)