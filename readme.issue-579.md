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

### Priority 4 - Cache Invalidation
- Add hook to clear transients when events are logged:
```php
add_action('simple_history/log_inserted', function() {
    // Clear all sh_* transients
});
```

## Expected Outcomes
- Consistent counts across all statistics displays
- Correct permission-based filtering
- Accurate timezone handling
- Fresh data after new events are logged
- Clear communication to users about what they're seeing