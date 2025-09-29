# Issue 579: Statistics Not Aligned

## Problem Summary
Statistics shown in different parts of Simple History are inconsistent, showing different counts for the same time periods across the sidebar stats box, dedicated stats page, and email reports.

## Root Causes Identified

### 1. Event Grouping Mismatch (PRIMARY CAUSE - 100x+ discrepancies)
- **Main log**: Groups similar events by `occasionsID` (e.g., 500 failed logins = 1 occasion)
- **Stats "Week/Month"**: Counts ALL individual events (`SELECT count(*)`)
- **Stats "Today"**: Correctly counts occasions (uses `Log_Query`)
- **Impact**: Sites under attack or with heavy editing show massive discrepancies

### 2. User Permission Cache Issue (HIGH PRIORITY)
- **Problem**: Cache keys don't include user ID or capabilities
- **Result**: All users see the same cached counts regardless of their permissions
- **Example**: Editor loads page → Admin sees editor's limited counts

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

### Priority 1 - Event Counting
- `/inc/class-helpers.php` - Lines 1307-1317: Change to count occasions, not events
- `/inc/class-events-stats.php` - Line 87: Consider counting occasions

### Priority 2 - Cache Keys
- `/dropins/class-sidebar-stats-dropin.php` - Line 310: Add capability hash to cache key
- `/inc/class-helpers.php` - Lines 1297, 1334: Add capability hash to cache keys

### Priority 3 - Timezone
- `/inc/services/class-email-report-service.php` - Lines 198, 243, 503: Use UTC
- `/inc/class-wp-rest-stats-controller.php` - Lines 231-234: Use UTC
- `/dropins/class-sidebar-stats-dropin.php` - Lines 171-172, 319-320: Use UTC

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

## Expected Outcomes
- Consistent counts across all statistics displays
- Correct permission-based filtering
- Accurate timezone handling
- ✅ Clear communication to users about what they're seeing (COMPLETED - added cache refresh notice)
- ✅ Performance-friendly caching (COMPLETED - kept 5-minute cache for efficiency)