# Issue 579: Statistics Not Aligned

## Problem Summary
Statistics shown in different parts of Simple History are inconsistent, showing different counts for the same time periods across the sidebar stats box, dedicated stats page, and email reports.

## Current Status

**Core Issue #579**: ✅ **FULLY RESOLVED** - All statistics now aligned across the plugin

**Latest Update (2025-10-12)**:
- ✅ Fixed email preview date range to match sidebar "7 days" stat
  - Preview now uses same 7-day period as sidebar (includes today)
  - Users can verify preview numbers against sidebar
- ✅ Made email copy date-neutral for better clarity
  - Removed "weekly" and "last week" references from headline and text
  - Added "Period" section with date range label for consistency
  - Copy now works in any context (preview, actual email, historical viewing)

**Previous Update (2025-10-11)**:
- ✅ Fixed ALL filter types to use WordPress timezone consistently
  - ✅ "Today" / "Last N days" filters (replaced database NOW() with Date_Helper)
  - ✅ Custom date range filters (replaced strtotime() with DateTimeImmutable + wp_timezone())
  - ✅ Month filters (replaced strtotime() with DateTimeImmutable + wp_timezone())
- ✅ Changed "Last day" → "Today" for clarity
- ✅ Added 4 comprehensive tests for all filter types
- ✅ Complete timezone consistency across entire plugin

**Previous Fixes (2025-10-08 to 2025-10-09)**:
- ✅ Fixed date range calculation off-by-one error (issue #7)
- ✅ Renamed `get_n_days_ago_timestamp()` to `get_last_n_days_start_timestamp()` for clarity (issue #8)
- ✅ Sidebar and Stats page now show identical counts for same time periods
- ✅ "Last 30 days" now consistently means exactly 30 days across all features
- ✅ Weekly email date range calculation fixed

---

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

### 3. Timezone Inconsistencies ✅ FIXED
- **Stats Service**: Uses UTC ✅
- **Sidebar/Email/REST API**: Use server timezone ❌ → **NOW FIXED** ✅
- **Result**: All components now use WordPress timezone

### 4. Additional Issues
- ✅ Multi-layer cache synchronization fixed
- ✅ Cache refresh notice added (5-minute interval)
- ✅ Total events count optimized for non-admin users
- ✅ Weekly email date range calculation fixed
- ✅ ALL filter dropdown dates now use WordPress timezone (lastdays, custom range, month filters - fixed Oct 11, 2025)

---

## Investigation Details

### Three Components Analyzed

**1. History Insights Sidebar** (class-sidebar-stats-dropin.php)
- Quick stats: Today, Week (7 days), Month (28 days)
- Chart showing activity over time
- Top users display
- **Filters by user permissions** ✅

**2. History Insights Page** (class-stats-service.php & class-stats-view.php)
- Detailed statistics with multiple periods
- Category breakdowns (Users, Content, Media, Plugins, Core)
- Peak times and activity overview
- **Admin-only, shows all events** ✅

**3. Weekly Email Reports** (class-email-report-service.php)
- Automated weekly summaries
- Fixed 7-day period (⚠️ currently showing 8 days)
- **Filters by user permissions** ✅

### Major Findings

#### 1. TIMEZONE INCONSISTENCY ✅ FIXED

**Before Fix:**
- **History Insights Sidebar**: Used `strtotime("-$period_days days")` (server timezone)
- **History Insights Page**: Used UTC explicitly
- **Email Reports**: Used `strtotime('-7 days')` (server timezone)
- **Result**: Different time windows, day-boundary mismatches

**After Fix:**
- ✅ All components use `Date_Helper` methods
- ✅ All respect WordPress timezone setting (Settings > General)
- ✅ Consistent day boundaries (midnight to 23:59:59 in site timezone)

#### 2. EVENT COUNTING METHODS ✅ CLARIFIED

**Main Log Display:**
- ✅ Uses sophisticated occasion grouping via `occasionsID`
- ✅ Groups similar events together (login attacks, post edits, spam comments)
- ✅ Shows grouped occasions with `repeatCount` and `subsequentOccasions`
- ✅ Example: 100 failed logins → displayed as "1 login attack occasion"

**Statistics (Sidebar, Insights Page, Email Reports):**
- ✅ Count individual events (correct for statistical purposes)
- ✅ Example: 100 failed logins → counted as "100 individual events"
- ✅ This is **intentional and correct** - stats show true activity volume

**Why This Difference Exists:**
- **Main log**: Grouped for readability (prevent UI flooding)
- **Statistics**: Individual counts for accurate metrics
- **Not a bug**: Different use cases require different counting approaches

#### 3. USER PERMISSION CACHE ISSUE ✅ FIXED

**Before Fix:**
- Cache keys didn't include user capabilities
- All users shared the same cached data
- Permissions were filtered at query time but results cached globally
- Example: Editor sees admin's count or vice versa

**After Fix:**
- ✅ Cache key includes `$loggers_slugs` based on user capabilities
- ✅ Separate cache entries for different permission levels
- ✅ Users only see counts for events they can access

---

## Completed Work

### 1. Created Date_Helper Class ✅ (Oct 2024)

**Purpose**: Centralized WordPress timezone-aware date operations

**New Methods:**
- `get_current_timestamp()` - Current Unix timestamp
- `get_today_start_timestamp()` - Today at 00:00:00 in WP timezone
- `get_today_end_timestamp()` - Today at 23:59:59 in WP timezone
- `get_last_n_days_start_timestamp($days)` - Start of "last N days" period
- `get_default_date_range()` - Last 30 days to end of today
- `get_last_n_days_range($days)` - Last N days range
- `get_period_range($period)` - Range for 'week', 'month', 'fortnight', 'quarter'

**Why This Matters:**

*Old approach:*
```php
strtotime("-30 days")  // Uses server timezone (typically UTC)
```
- Ignores WordPress timezone setting
- Misaligned day boundaries

*New approach:*
```php
Date_Helper::get_last_n_days_start_timestamp(30)  // Uses WordPress timezone
```
- Respects Settings > General timezone
- Predictable day boundaries (midnight to 23:59:59)

**Files Modified:**
- Created: `/inc/class-date-helper.php`
- Updated 6 files to use `Date_Helper`

### 2. Fixed Timezone Issues Across Components ✅ (Oct 2024)

**Helper Functions** (`/inc/class-helpers.php`):
- ✅ `get_num_events_last_n_days()` - Now uses `Date_Helper`
- ✅ `get_num_events_per_day_last_n_days()` - Now uses `Date_Helper`
- ✅ Added new `get_num_events_today()` - WordPress timezone aware

**Sidebar Stats** (`/dropins/class-sidebar-stats-dropin.php`):
- ✅ Changed to use new `Helpers::get_num_events_today()`
- ✅ Chart period calculation uses `Date_Helper`
- ✅ Top users date range uses `Date_Helper`
- ✅ Fixed timezone in user activity queries (lines 531, 568, 582)

**Email Reports** (`/inc/services/class-email-report-service.php`):
- ✅ Preview email uses `Date_Helper` (lines 198-199)
- ✅ Preview HTML uses `Date_Helper` (lines 244-245)
- ✅ Send email uses `Date_Helper` (lines 504-505)
- ✅ Email scheduling uses WordPress timezone (line 481)

**REST API** (`/inc/class-wp-rest-stats-controller.php`):
- ✅ Now uses `Date_Helper::get_default_date_range()` (line 235)

**Insights Page** (`/inc/services/class-stats-service.php`):
- ✅ Uses `wp_timezone()` instead of UTC (line 96)
- ✅ Fixed date range calculation (lines 122-149)
- ✅ Chart data timezone fix (`/inc/class-events-stats.php` lines 362, 364)

### 3. Fixed Multi-Layer Caching Issue ✅ (Dec 2024)

**Problem**: Conflicting cache layers caused synchronization issues
- Helper functions cached for 1 hour
- Sidebar cached for 5 minutes
- Chart data bypassed cache entirely
- Result: Stale data, cache desync

**Solution**:
1. Removed caching from helper functions
2. Consolidated caching at sidebar level (5 minutes)
3. Chart data now included in sidebar cache

**Benefits**:
- ✅ Single cache layer eliminates synchronization issues
- ✅ Simpler architecture
- ✅ Data updates properly when events are logged

### 4. Added Cache Refresh Notice ✅ (Dec 2024)

**Implementation**: Added "Updates every 5 minutes" text to sidebar stats
- **File**: `/dropins/class-sidebar-stats-dropin.php` - Lines 370-372
- **Result**: Users understand why stats may not immediately reflect new events

### 5. Optimized Cache Data Fetching ✅ (Oct 2024)

**Problem**: Cache fetched `total_events` and `top_users` for all users, even non-admins

**Solution**:
1. Conditional data fetching based on capabilities
2. `total_events` only for users with `manage_options`
3. `top_users` only for users with `list_users`
4. Cache key includes user capabilities

**Benefits**:
- ✅ Better performance for non-admin users
- ✅ More efficient resource usage
- ✅ Separate cache entries per permission level

### 6. Fixed Chart Date Display Issues ✅ (Oct 2024)

**Problem**:
- Chart showed 31 days instead of 30
- Today's events showed as 0
- Tooltip dates didn't match labels

**Root Causes**:
- Timezone conversion issues when using `createFromFormat('U', timestamp)`
- Off-by-one in date range calculation
- DatePeriod endpoint miscalculation

**Solution** (`/dropins/class-sidebar-stats-dropin.php` lines 177-190):
```php
// Correct approach:
$days_ago = $num_days - 1; // For "last 30 days including today"
$period_start_date = new DateTimeImmutable("-{$days_ago} days", wp_timezone());
$period_start_date = new DateTimeImmutable($period_start_date->format('Y-m-d') . ' 00:00:00', wp_timezone());
$today = new DateTimeImmutable('today', wp_timezone());
$tomorrow = $today->add(date_interval_create_from_date_string('1 days'));
$period = new DatePeriod($period_start_date, $interval, $tomorrow);
```

**Benefits**:
- ✅ Chart shows exactly 30 days
- ✅ Accurate tooltips matching labels
- ✅ Today's data visible
- ✅ No timezone conversion bugs

### 7. Fixed REST API Date Range ✅ (Oct 2024)

**Problem**: REST API returned 31 days for "last month" instead of 30

**Root Cause**: `get_default_date_range()` used `get_last_n_days_start_timestamp(30)` which gave 31 days total

**Fix**:
- Updated to use `get_last_n_days_start_timestamp(29)` for 30 days
- Enhanced documentation with clear examples

**Verification**:
- ✅ All 9 REST API endpoints return `duration_days: 30`
- ✅ Activity overview shows exactly 30 dates

### 8. Fixed Date Range Calculation Off-by-One ✅ (Oct 2024)

**Problem**: Sidebar and Stats page showed different counts
- Sidebar "30 days": 188 events
- Stats page "30 days": 229 events
- Root cause: 31 days of data when including today

**Solution**:
```php
// Before:
$date = new \DateTimeImmutable("-{$days} days", wp_timezone());
// Returns 30 days ago = 31 days total

// After:
$days_ago = $days - 1;  // For "last N days including today"
$date = new \DateTimeImmutable("-{$days_ago} days", wp_timezone());
// Returns 29 days ago = 30 days total
```

**Files Modified**:
- `/inc/class-date-helper.php` - Function fix
- `/inc/services/class-stats-service.php` - Use Date_Helper

**Verification**:
- ✅ Sidebar and Stats page now show same counts
- ✅ "Last 30 days" shows exactly 30 days (Sept 9 to Oct 8)

### 9. Function Renamed for Clarity ✅ (Oct 2024)

**Renamed**: `get_n_days_ago_timestamp()` → `get_last_n_days_start_timestamp()`

**Reason**: Better self-documenting code
- "last N days start" is unambiguous
- Clear that it returns the START of a period

**Updated Files** (10 locations):
1. `/inc/class-date-helper.php`
2. `/inc/class-helpers.php`
3. `/inc/services/class-stats-service.php`
4. `/inc/services/class-email-report-service.php`
5. `/dropins/class-sidebar-stats-dropin.php`
6. `/tests/wpunit/StatsAlignmentTest.php`

### 10. Created Comprehensive Tests ✅ (Oct 2024)

**Created**: `tests/wpunit/StatsAlignmentTest.php` with 7 tests:

1. `test_admin_user_all_stats_match` ✅
2. `test_permission_filtering_intentional_difference` ✅
3. `test_timezone_alignment` ✅
4. `test_date_range_consistency` ✅
5. `test_individual_events_not_grouped_occasions` ✅
6. `test_email_report_data_alignment` ✅
7. `test_chart_data_alignment` ✅

**Test Results**: All passing ✅ (OK - 7 tests, 22 assertions)

### 11. Fixed Weekly Email Date Range Calculation ✅ (Oct 9, 2025)

**Problem**: Weekly email reports needed clear date range logic for preview vs actual send.

**Requirements Clarified**:
- **Preview** (any day): Show last 7 complete days (excludes partial today)
- **Actual email** (sent Mondays): Show previous Monday-Sunday week (excludes current Monday)

**Solution**: Created two new Date_Helper methods:

1. **`get_last_n_complete_days_range($days)`**
   - Returns last N complete days (excludes today)
   - Example: On Wednesday, returns 7 days ending Tuesday 23:59:59
   - Used for email previews

2. **`get_last_complete_week_range()`**
   - Returns most recent complete Monday-Sunday week
   - Example: On any day, returns previous Mon 00:00:00 to Sun 23:59:59
   - Used for actual sent emails

**Files Modified**:
- `/inc/class-date-helper.php` - Added new methods
- `/inc/services/class-email-report-service.php` - Updated preview and send functions
- `/tests/wpunit/DateHelperTest.php` - Added 3 new tests

**Test Results**: All 15 tests passing ✅ (OK - 15 tests, 47 assertions)

### 12. Fixed Email "Activity by Day" Chronological Ordering ✅ (Oct 9, 2025)

**Problem**: Email template showed days in calendar week order (Mon-Sun) regardless of actual date range.

**Example Issue**:
- Date range: "Thu October 2 – Wed October 8, 2025"
- Days shown: Mon, Tue, Wed, Thu, Fri, Sat, Sun (❌ wrong order)
- Expected: Thu, Fri, Sat, Sun, Mon, Tue, Wed (✅ chronological)

**Solution**: Updated email template to dynamically build days array based on actual date range:

1. Pass `date_from_timestamp` and `date_to_timestamp` to template (via `get_summary_report_data()`)
2. Iterate through each day in the actual date range
3. Display days in chronological order matching the email date range

**Files Modified**:
- `/inc/services/class-email-report-service.php:166-168` - Pass date range timestamps
- `/templates/email-summary-report.php:236-270` - Build ordered days array from date range

**Result**: "Activity by day" now shows days in the same order as the date range heading.

### 13. Added Date Tooltips to Email "Activity by Day" ✅ (Oct 9, 2025)

**Enhancement**: Added tooltips showing full date on hover for each day in the activity breakdown.

**Implementation**:
- Calculate full date format (e.g., "Thursday 2 October 2025") for each day
- Add `title` attribute to table cells
- Tooltips visible on hover in email clients that support it

**Files Modified**:
- `/templates/email-summary-report.php:261-275,289` - Add full_date to data array and title attribute

**Result**: Each day column now shows full date on hover for better clarity.

### 14. Fixed Email "Activity by Day" Showing Incomplete Data ✅ (Oct 9, 2025)

**Problem**: Email only showed top 3 days by activity count, missing other days entirely.

**Example Issue**:
- Wednesday had 5 events but showed as 0
- Monday had 1 event but showed as 0
- Only showed top 3 days: Sunday (60), Friday (12), Tuesday (7)
- Missing data for other 4 days in the week

**Root Cause**:
- `get_summary_report_data()` called `prepare_top_items($peak_days, 3, ...)`
- Limited results to top 3 most active days
- Template expected all 7 days but only received 3
- Days not in top 3 showed as 0 (default fallback)

**Solution**: Remove the top-3 limit and pass all days to template:
- Stop using `prepare_top_items()` for days (still used for top users)
- Convert all `peak_days` results to array format
- Template builds complete 7-day array, filling missing days with 0

**Files Modified**:
- `/inc/services/class-email-report-service.php:152-167` - Remove limit, pass all days

**Result**: All 7 days now show correct counts, totals match (0+12+7+60+1+7+5 = 92 ✅)

### 15. Fixed Email Preview Date Range to Match Sidebar Stats ✅ (Oct 12, 2025)

**Problem**: Email preview showed different date range than sidebar "7 days" stat, causing confusion.

**Example Issue**:
- **Sidebar "7 days"**: Last 7 days including today (e.g., Oct 6-12) = 49 events
- **Email preview**: Last 7 complete days excluding today (e.g., Oct 5-11) = 103 events
- Users couldn't verify preview numbers against sidebar

**Root Cause**:
- Email preview used `Date_Helper::get_last_n_complete_days_range()` (excludes today)
- Sidebar used `Date_Helper::get_last_n_days_range()` (includes today)
- Different date ranges = different event counts

**Solution**: Changed email preview to use same date range as sidebar:
- Preview now uses `Date_Helper::get_last_n_days_range()` (includes today)
- Actual weekly email continues using `get_last_complete_week_range()` (unchanged)

**Files Modified**:
- `/inc/services/class-email-report-service.php:225,273` - Changed preview methods to use `get_last_n_days_range()`

**Result**: Email preview now shows same 7-day period as sidebar, making numbers easy to verify.

### 16. Made Email Copy Date-Neutral ✅ (Oct 12, 2025)

**Problem**: Email copy contained time-specific references that didn't work well for previews or when viewing older emails.

**Issues Found**:
1. **Headline**: "Website weekly activity summary" ❌
2. **Subtitle**: "Here's a summary of what happened on your website last week." ❌
3. **Section header**: "Events this week" ❌
4. **Date label**: No label for the date range (inconsistent with other key-value pairs)

**Solutions**:
1. **Headline**: "Website weekly activity summary" → "Website activity summary" ✅
2. **Subtitle**: "Here's a summary of what happened on your website last week." → "Here's a summary of activity on your website." ✅
3. **Section header**: "Events this week" → "Total events" ✅
4. **Date section**: Added "Period" as labeled section between "Website" and "Total events" for consistency ✅

**Files Modified**:
- `/templates/email-summary-report.php:163` - Updated headline
- `/templates/email-summary-report.php:174` - Updated subtitle
- `/templates/email-summary-report.php:220-228` - Added "Period" section with date range
- `/templates/email-summary-report.php:233` - Updated section header to "Total events"

**Result**: Email copy is now date-neutral and works for any context (preview, actual email, historical viewing).

### 17. Fixed ALL Filter Date Calculations to Use WordPress Timezone ✅ (Oct 11, 2025)

**Problem**: ALL date filters used database/server timezone instead of WordPress timezone.

**Root Causes**:
1. **"lastdays" filters** (Today, Last 7 days, etc.): Used SQL `DATE(NOW() - INTERVAL N DAY)` with database timezone
2. **Custom date range**: Used `strtotime()` which uses PHP/server timezone
3. **Month filters** (October 2025, etc.): Used `strtotime()` which uses PHP/server timezone

All three filter types ignored WordPress timezone settings, causing:
- Different day boundaries than sidebar stats
- Inconsistent results across different timezone settings
- Confusion for users in non-UTC timezones

**Example Issue** (WordPress timezone: Europe/Stockholm UTC+2):
- Filtering "Last 7 days" at 14:00 Stockholm time:
  - **Before**: Oct 4 12:00 UTC → Oct 11 12:00 UTC ❌
  - **After**: Oct 5 00:00 Stockholm → Oct 11 23:59 Stockholm ✅
- Filtering "October 2025":
  - **Before**: Oct 1 00:00 UTC → Nov 1 00:00 UTC (includes 2 hours of Nov in Stockholm) ❌
  - **After**: Oct 1 00:00 Stockholm → Oct 31 23:59 Stockholm ✅

**Solutions**:

1. **"lastdays" filters** (lines 1262-1267):
   ```php
   // BEFORE: Used database NOW()
   'date >= DATE(NOW() - INTERVAL 7 DAY)'

   // AFTER: Uses Date_Helper with WordPress timezone
   $timestamp = Date_Helper::get_last_n_days_start_timestamp( 7 );
   $inner_where[] = sprintf( 'date >= \'%1$s\'', gmdate( 'Y-m-d H:i:s', $timestamp ) );
   ```

2. **Custom date range** (lines 816-854):
   ```php
   // BEFORE: Used strtotime() with server timezone
   $args['date_from'] = strtotime( $args['date_from'] . ' 00:00:00' );

   // AFTER: Uses DateTimeImmutable with WordPress timezone
   $date = new \DateTimeImmutable( $args['date_from'] . ' 00:00:00', wp_timezone() );
   $args['date_from'] = $date->getTimestamp();
   ```

3. **Month filters** (lines 1291-1315):
   ```php
   // BEFORE: Used strtotime() with server timezone
   $date_month_beginning = strtotime( $one_month );
   $date_month_end = strtotime( "{$one_month} + 1 month" );

   // AFTER: Uses DateTimeImmutable with WordPress timezone
   $date_month_beginning_obj = new \DateTimeImmutable( $one_month . '-01 00:00:00', wp_timezone() );
   $date_month_end_obj = $date_month_beginning_obj->modify( '+1 month' )->modify( '-1 second' );
   ```

**Files Modified**:
- `/inc/class-log-query.php:6` - Added Date_Helper import
- `/inc/class-log-query.php:816-854` - Fixed custom date range to use WordPress timezone
- `/inc/class-log-query.php:1274-1281` - Fixed "lastdays" filter to use Date_Helper + added validation
- `/inc/class-log-query.php:1291-1315` - Fixed month filters to use WordPress timezone
- `/src/constants.js:10` - Changed "Last day" to "Today" for clarity
- `/tests/wpunit/StatsAlignmentTest.php` - Added 4 new comprehensive tests (tests 8-11) using dynamic dates

**Tests Added**:
1. `test_filter_uses_same_date_range_as_sidebar()` - Verifies "lastdays" filter alignment
2. `test_custom_date_range_uses_wordpress_timezone()` - Verifies custom range uses WP timezone
3. `test_month_filter_uses_wordpress_timezone()` - Verifies month filter uses WP timezone
4. `test_all_date_filters_use_wordpress_timezone_consistently()` - Comprehensive test of all filter types

**Important Note**:
Event **counts** still won't match between filter results and sidebar stats because:
- **Sidebar**: Counts all individual events (e.g., "116 events")
- **Main GUI**: Groups similar events by occasions (e.g., 30 failed logins → 1 row)
- **This is intentional** - GUI groups for readability, stats count for accuracy

**Result**:
- ✅ ALL filter types now use WordPress timezone consistently
- ✅ Same time windows as sidebar stats (same day boundaries)
- ✅ Simpler code (no database-specific logic - works with MySQL and SQLite)
- ✅ No more strtotime() timezone issues
- ✅ Added validation for lastdays parameter (positive integer)
- ✅ "Today" label is clearer than "Last day"
- ✅ Comprehensive test coverage for all filter types (using dynamic dates, future-proof)

---

## Files Modified

### Core Files Created/Renamed
- ✅ `/inc/class-date-helper.php` - Centralized date operations

### Core Files Updated
- ✅ `/inc/class-helpers.php` - Timezone fixes, cache removal
- ✅ `/inc/class-events-stats.php` - Chart timezone fix
- ✅ `/inc/services/class-stats-service.php` - Timezone and date range fixes
- ✅ `/inc/services/class-email-report-service.php` - Timezone fixes
- ✅ `/inc/class-wp-rest-stats-controller.php` - Timezone fix
- ✅ `/dropins/class-sidebar-stats-dropin.php` - Multiple timezone and cache fixes
- ✅ `/inc/class-log-query.php` - Filter date calculation uses Date_Helper
- ✅ `/src/constants.js` - Changed "Last day" to "Today"

### Tests
- ✅ `/tests/wpunit/StatsAlignmentTest.php` - Comprehensive alignment tests
- ✅ `/tests/wpunit/DateHelperTest.php` - Date helper unit tests

---

## Expected Outcomes ✅

- ✅ Consistent counts across all statistics displays
- ✅ Correct permission-based filtering
- ✅ Accurate timezone handling (WordPress timezone everywhere)
- ✅ Clear communication to users about refresh intervals
- ✅ Performance-friendly caching (5-minute cache)
- ✅ REST API date ranges showing correct durations
- ✅ Weekly email date range (preview = last 7 complete days, sent = last complete week Mon-Sun)
- ✅ ALL filter dropdowns use WordPress timezone (lastdays, custom range, month filters)
- ✅ Sidebar and filters query same time windows (same day boundaries)

---

## Recommended Solution Strategy

### Stats Display Strategy
**Stats should show user-filtered counts** - users see statistics for events they have permission to view.

#### Implementation:
1. **Stats Box** (all users):
   - Filter by `get_loggers_that_user_can_read()`
   - Cache per capability set
   - Show contextual information

2. **Stats Page** (admins only):
   - Show complete statistics
   - Optional: Toggle to see stats from other roles' perspective

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

// Context line
echo sprintf(__('Showing: %s', 'simple-history'), 'Posts, pages, comments, and media changes');
```

---

## Investigation Background

### Components Investigated
- ✅ Event storage and retrieval
- ✅ Main log display (occasion grouping)
- ✅ Sidebar stats widget
- ✅ Stats/Insights page
- ✅ Email reports
- ✅ REST API endpoints
- ✅ Helper functions
- ✅ Cache layers
- ✅ Timezone handling
- ✅ Permission filtering

### Issues Analyzed
1. ✅ Timezone inconsistencies
2. ✅ Event counting methods
3. ✅ Occasion grouping vs individual counts
4. ✅ User permission cache
5. ✅ Multi-layer caching
6. ✅ Date range calculations
7. ✅ Chart data display
8. ✅ REST API date ranges
9. ✅ Weekly email date range
10. ✅ Filter dropdown dates

### Priority Order of Issues Found
1. 🔥 **FIXED**: Timezone inconsistencies (day-boundary mismatches)
2. 🔥 **FIXED**: User permission cache (wrong counts for different roles)
3. 📝 **CLARIFIED**: Occasion grouping (intentional, working as designed)
4. 📝 **FIXED**: Date range calculations (off-by-one errors)
5. ✅ **FIXED**: Weekly email date range
6. ✅ **FIXED**: Filter dropdown dates

---

## Conclusion

**Issue #579 is FULLY RESOLVED** ✅

All statistics alignment issues have been comprehensively fixed:

### Timezone Consistency ✅
- ✅ **Sidebar stats**: WordPress timezone
- ✅ **Stats/Insights page**: WordPress timezone
- ✅ **Email reports**: WordPress timezone
- ✅ **REST API**: WordPress timezone
- ✅ **ALL filter types**: WordPress timezone
  - ✅ Today / Last N days filters
  - ✅ Custom date range filters
  - ✅ Month filters (October 2025, etc.)
- ✅ **Chart data**: WordPress timezone

### Core Issues Fixed ✅
- ✅ Permission filtering works correctly (separate cache per capability)
- ✅ Date ranges are accurate (exactly N days, not N+1)
- ✅ No more timezone conversion bugs
- ✅ Counting methods documented (GUI groups, stats count individuals)
- ✅ Weekly email date ranges work correctly (preview vs sent behavior)
- ✅ Multi-layer caching synchronized
- ✅ Cache refresh communication clear

### Comprehensive Testing ✅
- ✅ 11 test cases covering all scenarios
- ✅ Tests for all filter types (lastdays, custom, month)
- ✅ Tests for timezone consistency across components
- ✅ Tests for permission filtering
- ✅ Tests for date range accuracy

**No outstanding issues remain. All date/time calculations now use WordPress timezone consistently across the entire plugin.**
