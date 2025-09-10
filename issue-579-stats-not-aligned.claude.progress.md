# Issue 579: Statistics Not Aligned - Investigation Progress

## Overview
Investigating discrepancies between three statistics features:
1. History Insights Sidebar (class-sidebar-stats-dropin.php)
2. History Insights Page (class-stats-service.php & class-stats-view.php)
3. Weekly Email Reports (class-email-report-service.php)

## Investigation Areas
- [x] Date range calculations consistency
- [x] Timezone handling
- [x] Event counting methods
- [x] Message key filtering
- [x] OccasionsID grouping behavior

## Executive Summary

**Primary Issue Found:** The statistics features use fundamentally different counting methods - some count grouped occasions while others count individual events, causing discrepancies that can be 100x or more.

**Secondary Issue Found:** Mixed timezone handling across components creates day-boundary mismatches.

## CRITICAL FINDINGS

### 1. TIMEZONE INCONSISTENCY - MAJOR ISSUE FOUND! ⚠️

**History Insights Sidebar:**
- Week/Month: Uses `strtotime("-$period_days days")` in Helpers::get_num_events_last_n_days() (class-helpers.php:1313)
- Today: Uses `Log_Query` class which properly handles timezone via `strtotime('today')` (class-events-stats.php:1776)
- Week/Month use **server's default timezone**, Today uses server timezone but with proper Log_Query handling

**History Insights Page:**
- Uses UTC explicitly: `new \DateTimeImmutable('now', new \DateTimeZone('UTC'))` (class-stats-service.php:96)
- Then calculates date_from and date_to in UTC timestamps

**Email Reports:**
- Uses `strtotime('-7 days')` without timezone specification (class-email-report-service.php:198, 243, 503)
- Uses `time()` for date_to which returns Unix timestamp
- These use **server's default timezone**

**Database Storage:**
- Events are stored with MySQL datetime format in the database
- Queries use `FROM_UNIXTIME()` function which converts based on MySQL server timezone
- Some queries use `gmdate()` for formatting (class-log-query.php:1207, 1213)

### 2. DATE RANGE CALCULATION DIFFERENCES

**History Insights Sidebar (28 days):**
- Sidebar chart: Uses DateTimeImmutable with `strtotime("-$num_days days")` to `time()` (class-sidebar-stats-dropin.php:171-172)
- Quick stats: 
  - Today: `strtotime('today')` (only from midnight)
  - Week: Last 7 days using `strtotime("-7 days")`
  - Month: Last 28 days using `strtotime("-28 days")`
- Helper functions use `strtotime("-$period_days days")` without time component

**History Insights Page (dynamic periods):**
- Supports: 1h, 24h, 7d, 14d, 1m, 3m, 6m periods
- Uses `$now->modify("-{$period_number} {$period_string_full_name}")` for calculation
- Always uses current time as end point (includes partial current day)
- All calculations in UTC

**Email Reports (7 days):**
- Fixed 7-day period: `strtotime('-7 days')` to `time()`
- Includes partial days at both ends (time component included)

### 3. DAY BOUNDARY HANDLING

**Log Query (Main Filter):**
- Properly handles day boundaries for date filters
- If date_from is "Y-m-d" format, adds " 00:00:00" (class-log-query.php:822)
- If date_to is "Y-m-d" format, adds " 23:59:59" (class-log-query.php:837)
- Converts to GMT using `gmdate()` for database queries

**Other Features:**
- Don't consistently handle day boundaries
- Mix of including/excluding partial days at boundaries

### 4. EVENT COUNTING METHOD ANALYSIS

**Common Event Counting (class-events-stats.php):**
- All three features use the same `Events_Stats` class methods for counting specific events
- Examples: `get_successful_logins_count()`, `get_failed_logins_count()`, `get_posts_pages_created()`
- These methods internally call `get_event_count()` which:
  - Uses `FROM_UNIXTIME()` to convert timestamps to MySQL datetime
  - Joins with contexts table to check message keys
  - **IMPORTANT**: Expects timestamps in Unix format for date_from and date_to

**Total Events Count:**
- Sidebar uses cached count: `Helpers::get_total_logged_events_count()` (stored in option, counts ALL individual events ever logged)
- Stats Page uses direct query: `$events_stats->get_total_events($date_from, $date_to)` (counts individual events in date range)
- Email Reports uses direct query: `$events_stats->get_total_events($date_from, $date_to)` (counts individual events in date range)

**Query Implementation Details:**
- `get_event_count()` method uses: `h.date >= FROM_UNIXTIME(%d) AND h.date <= FROM_UNIXTIME(%d)`
- `FROM_UNIXTIME()` converts Unix timestamp to MySQL datetime in **MySQL server's timezone**
- If MySQL server timezone differs from PHP timezone, this causes mismatches

## ROOT CAUSES OF MISALIGNMENT

1. **Primary Issue - Multiple Timezone Layers:**
   - **PHP Timezone**: Stats Page uses UTC explicitly, others use server's default timezone
   - **MySQL Timezone**: `NOW()` and `FROM_UNIXTIME()` use MySQL server timezone (independent of PHP)
   - **WordPress Assumption**: WordPress assumes PHP is UTC but doesn't enforce MySQL timezone
   - **Result**: Up to 3 different timezones in a single query (PHP calculation → MySQL processing → Display)

2. **Secondary Issue - Different Time Window Calculations:**
   - **"Today" (Sidebar)**: `strtotime('today')` = midnight to current time (partial day, PHP timezone)
   - **"Last day" (Main GUI)**: `NOW() - INTERVAL 1 DAY` = last 24 hours (full day, MySQL timezone)
   - **Result**: Completely different time periods being measured

3. **Tertiary Issues:**
   - Day boundary inconsistencies (partial vs full days)
   - Different caching periods (5 minutes vs 1 hour)
   - Mixed date calculation approaches across components

## RECOMMENDATIONS FOR FIXES

### Immediate Fixes (High Priority)

1. **Standardize ALL Timezone Handling to UTC:**
   ```php
   // All PHP date calculations should use UTC
   $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
   $date_from = $now->modify("-{$days} days")->setTime(0, 0, 0);
   $date_to = $now->setTime(23, 59, 59);
   ```

2. **Fix MySQL Timezone Issues:**
   ```sql
   -- Instead of NOW() - use UTC functions
   date >= UTC_TIMESTAMP() - INTERVAL 1 DAY
   
   -- Instead of FROM_UNIXTIME() - convert to UTC
   date >= CONVERT_TZ(FROM_UNIXTIME(%d), @@session.time_zone, '+00:00')
   
   -- Or use gmdate() in PHP before query
   date >= '%s'  -- where %s is gmdate('Y-m-d H:i:s', $timestamp)
   ```

3. **Standardize Time Window Definitions:**
   - **"Today"** = UTC midnight to current UTC time
   - **"Last day"** = Last 24 hours from current UTC time  
   - **"Last N days"** = UTC midnight N days ago to current UTC time
   - Document which definition each feature should use

### Long-term Improvements

1. **Create Centralized Date Service:**
   - Single source of truth for date calculations
   - Consistent timezone handling
   - Proper day boundary management

2. **Database Query Consistency:**
   - Consider using `CONVERT_TZ()` in MySQL queries to ensure UTC
   - Or store all dates as UTC timestamps

3. **Add Configuration Option:**
   - Allow users to set their preferred timezone for reports
   - Display timezone information in UI

## MAIN LOG AND REST API ANALYSIS (Additional Investigation)

### Main Log GUI (✅ Working Correctly):
- **Storage**: Events stored as GMT using `current_time('mysql', 1)` in class-logger.php:1210
- **Querying**: Uses `gmdate()` to convert timestamps to GMT for database queries (class-log-query.php:1207, 1213)
- **Display**: Properly converts GMT to local time for user display using `get_date_from_gmt()`
- **Date filters**: Correctly handles day boundaries (00:00:00 for start, 23:59:59 for end)
- **Timezone handling**: Consistent GMT storage → GMT queries → Local display

### Events REST API (✅ Working Correctly):
- **Input**: Accepts Unix timestamps for `date_from` and `date_to` parameters
- **Processing**: Uses same Log_Query class with proper GMT handling
- **Output**: Returns both `date_gmt` (raw from DB) and `date_local` (converted to site timezone)
- **Consistency**: Aligns with main log's timezone approach

### Stats REST API (❌ Timezone Issues Found):
- **Problem**: Uses `new \DateTime('today')` without timezone specification (line 231-234)
- **Effect**: Default date range calculations use server timezone instead of UTC
- **Impact**: API returns different results than main log for same time periods
- **File**: `/inc/class-wp-rest-stats-controller.php`

### Summary of Component Status:

**Components Working Correctly (GMT/UTC):**
- ✅ Event storage (uses GMT)
- ✅ Main log display and filtering
- ✅ Events REST API (for events endpoint)
- ✅ History Insights Page (uses UTC)

**Components NOT Working (Using Server Timezone):**
- ❌ History Insights Sidebar
- ❌ Email Reports
- ❌ Stats REST API (for stats endpoints)
- ❌ Helper functions for sidebar

## FILES REQUIRING CHANGES

Priority files to update (to achieve consistent UTC handling):
1. `/inc/class-helpers.php` - Lines 1313, 1361, 1380 (change strtotime to UTC)
2. `/inc/services/class-stats-service.php` - Already uses UTC ✅
3. `/inc/services/class-email-report-service.php` - Lines 198, 243, 503 (change strtotime to UTC)
4. `/inc/class-events-stats.php` - Line 1776 (get_num_events_today uses strtotime('today') which needs UTC)
5. `/dropins/class-sidebar-stats-dropin.php` - Lines 171-172, 319-320 (use UTC DateTimeImmutable)
6. `/inc/class-wp-rest-stats-controller.php` - Lines 231-234 (use UTC DateTime in get_default_date_range)
7. `/inc/class-log-query.php` - Lines with `NOW()` usage (replace with `UTC_TIMESTAMP()` or convert timestamps to GMT strings)

## CRITICAL DISCOVERY: MySQL Timezone Independence

**WordPress does NOT set MySQL connection timezone to UTC by default.**
- MySQL server timezone is independent of PHP timezone
- `NOW()`, `FROM_UNIXTIME()` use MySQL server timezone (could be different from PHP)
- This creates a **3-layer timezone problem**: PHP → MySQL → Display
- Main log's "Last day" filter also affected by this MySQL timezone issue

## ⚠️ MAJOR DISCOVERY: EVENT GROUPING vs INDIVIDUAL EVENT COUNTING ⚠️

### THE BIGGEST DISCREPANCY FOUND - MIXED COUNTING APPROACHES!

**Main Log Display:**
- ✅ Uses sophisticated occasion grouping via `occasionsID`
- ✅ Groups similar events together (login attacks, post edits, spam comments)
- ✅ Shows grouped occasions with `repeatCount` and `subsequentOccasions`
- ✅ Example: 100 failed logins → displayed as "1 login attack occasion"

**Sidebar "Today" Count:**
- ✅ CORRECTLY uses `Log_Query` class via `Events_Stats::get_num_events_today()`
- ✅ Counts grouped occasions (same as main log)
- ✅ Respects occasionsID grouping via `GROUP BY historyWithRepeated.repeated`
- ✅ `total_row_count` counts the grouped results, not individual events

**Sidebar "Week" and "Month" Counts:**
- ❌ Uses direct SQL query via `Helpers::get_num_events_last_n_days()`
- ❌ Query: `SELECT count(*) FROM events_table` - counts ALL individual events
- ❌ Completely ignores `occasionsID` grouping
- ❌ Example: 100 failed logins → counted as "100 individual events"

**Sidebar "Total Events" Count:**
- ❌ Uses `Helpers::get_total_logged_events_count()` - cached option value
- ❌ Counts ALL individual events ever logged in the database
- ❌ Updated immediately after each event is logged via `Helpers::increase_total_logged_events_count()` (class-logger.php:1330)
- ❌ Ignores occasionsID grouping - increments for every individual event

**Insights Page & Email Reports (All Statistics):**
- ❌ Count individual events using `Events_Stats->get_event_count()`
- ❌ Query: `SELECT COUNT(DISTINCT h.id)` - counts unique database IDs  
- ❌ Completely ignores `occasionsID` grouping
- ❌ Example: 100 failed logins → counted as "100 individual events"

### EVENT GROUPING ANALYSIS

**Events That Are Commonly Grouped:**

1. **Failed Login Attempts** (User Logger):
   - All failed logins use same occasionsID: `SimpleUserLogger/failed_user_login`
   - **Potential Impact:** Sites under attack could have 100s-1000s of failed logins grouped into 1 occasion
   - **Stats Discrepancy:** Main log shows "1 attack", stats show actual failed login count

2. **Post Updates** (Post Logger):
   - Each post gets unique occasionsID: `SimplePostLogger/post_updated/{$post->ID}`
   - **Potential Impact:** Multiple edits to same post are grouped
   - **Stats Discrepancy:** Main log shows "1 post editing session", stats show each edit

3. **Spam Comments** (Comments Logger):
   - Spam comments grouped: `SimpleCommentsLogger/anon_comment_added/type:spam`
   - **Potential Impact:** Comment spam floods grouped into single occasions
   - **Stats Discrepancy:** Main log shows "1 spam attack", stats show each spam comment

4. **File Edits, Category Changes, Translations:**
   - All use occasion grouping to prevent log flooding
   - **Stats Discrepancy:** Multiple related actions shown as single occasions vs individual counts

### MAGNITUDE OF THE PROBLEM

**Real-World Impact Examples:**
- **Login Attack:** 500 failed logins = Main log "1 occasion" vs Stats "500 events" (499x difference!)
- **Heavy Post Editing:** 20 edits to one post = Main log "1 occasion" vs Stats "20 events" (20x difference!)
- **Comment Spam:** 200 spam comments = Main log "1 occasion" vs Stats "200 events" (200x difference!)

**This explains why users report massive discrepancies between main log and statistics!**

### FILES INVOLVED IN GROUPING ISSUE

**Correctly Counting Occasions:**
- `/inc/class-log-query.php` - `query_overview_mysql()` method groups by occasions (line 324: `GROUP BY historyWithRepeated.repeated`)
- `/inc/class-events-stats.php` - `get_num_events_today()` (line 1771) uses Log_Query, counts occasions correctly

**Incorrectly Counting Individual Events:**
- `/inc/class-helpers.php` - `get_num_events_last_n_days()` (line 1307-1317): `SELECT count(*)` - counts ALL events
- `/inc/class-events-stats.php` - `get_event_count()` method (line 87): `SELECT COUNT(DISTINCT h.id)` - counts individual events
- All insights page and email report statistics use `get_event_count()` method

**Occasion ID Definition:**
- `/loggers/class-*.php` - Logger files define occasionsID patterns
- `/loggers/class-logger.php` - `append_occasions_id_to_context()` (line 1567) generates the occasion IDs

### RECOMMENDED FIXES FOR GROUPING ISSUE

**Option 1: Make Statistics Count Occasions (Recommended)**
```sql
-- Instead of: SELECT COUNT(DISTINCT h.id)
-- Use: SELECT COUNT(DISTINCT h.occasionsID)
SELECT COUNT(DISTINCT h.occasionsID) 
FROM simple_history h 
JOIN simple_history_contexts c ON h.id = c.history_id 
WHERE /* existing filters */
```

**Option 2: Make Main Log Show Individual Events**
- Disable occasion grouping in main log display
- Show all individual events (not recommended - would flood the log)

**Option 3: Add Configuration Option**
- Allow users to choose: "Count individual events" vs "Count grouped occasions"
- Display both counts in statistics: "45 occasions (156 individual events)"

### PRIORITY ORDER OF ALL ISSUES FOUND

1. **🔥 CRITICAL:** Occasion grouping mismatch (can cause 100x+ discrepancies)
2. **⚠️ HIGH:** User permission cache issue (wrong counts for different user roles)
3. **⚠️ HIGH:** Timezone inconsistencies (can cause day-boundary mismatches)  
4. **📝 MEDIUM:** Different time window calculations (28 vs 30 days, etc.)

### CONCLUSION

**The occasion grouping mismatch is likely the PRIMARY cause of user-reported statistics discrepancies.** Sites experiencing login attacks, spam floods, or heavy content editing could see massive differences between what the main log shows (grouped occasions) and what the statistics count (individual events).

## ⚠️ CRITICAL BUG: USER PERMISSION CACHE ISSUE ⚠️

### THE PROBLEM

**User-specific event counts are being cached WITHOUT user identification in the cache key!** This causes incorrect statistics display where users see wrong event counts based on whoever triggered the cache first.

### AFFECTED COMPONENTS

1. **Sidebar Stats (`get_quick_stats_data` in class-sidebar-stats-dropin.php:308-335)**
   - Cache key: `'sh_quick_stats_data_' . md5( serialize( [ $num_days_month, $num_days_week ] ) )`
   - **Missing**: User ID or capabilities in cache key
   - **Impact**: All users share the same cached data regardless of permissions

2. **Helper Functions (class-helpers.php)**
   - `get_num_events_last_n_days()` (line 1297): `'sh_' . md5( __METHOD__ . $period_days . '_2' )`
   - `get_num_events_per_day_last_n_days()` (line 1334): `'sh_' . md5( __METHOD__ . $period_days . '_3' )`
   - `get_total_logged_events_count()` (line 1380): Uses option, not user-specific at all
   - **Missing**: User ID or capabilities in ALL cache keys
   - **Impact**: Permission-filtered queries are cached and shared across all users

3. **Top Users Display (`get_top_users` in class-events-stats.php)**
   - No caching, but included in sidebar cache
   - Query doesn't filter by loggers user can read
   - **Impact**: Users without permission might see activity from restricted loggers

### HOW THE VULNERABILITY WORKS

1. **Editor user** loads the page first:
   - Query runs with `get_loggers_that_user_can_read()` for editor's permissions
   - Results cached with key that doesn't include user info
   - Editor sees limited data (correct)

2. **Administrator** loads the page next:
   - Cache hit on same key (no user differentiation)
   - Administrator sees editor's limited cached data
   - **Administrator sees LESS data than they should** ❌

3. **Reverse scenario:**
   - Administrator loads first → full count cached
   - Editor loads next → sees administrator's higher count
   - **Editor sees incorrect (inflated) event count** ⚠️

### SCOPE OF THE ISSUE

**Permissions are filtered at query time:**
- `get_loggers_that_user_can_read( $user_id )` returns different loggers per user
- SQL includes: `AND logger IN {$sql_loggers_user_can_view}`
- But cached results are shared across ALL users!

**Example scenario:**
- Site has custom logger for admin-only actions (e.g., "PluginLogger")
- Only administrators can view PluginLogger events
- Editor triggers cache → sees count of 100 events (excluding plugin events)
- Administrator loads page → also sees 100 events (should see 150 including plugin events)
- Or if Admin triggers cache first → Editor sees inflated count of 150 (should see 100)

### PROOF IN CODE

```php
// class-sidebar-stats-dropin.php line 309-310
$args_serialized = serialize( [ $num_days_month, $num_days_week ] );
$cache_key = 'sh_quick_stats_data_' . md5( $args_serialized );
// ❌ No user ID or capabilities in cache key!

// class-helpers.php line 1297
$transient_key = 'sh_' . md5( __METHOD__ . $period_days . '_2' );
// ❌ No user ID or capabilities in cache key!

// But the query IS filtered by user permissions (line 1304):
$sqlStringLoggersUserCanRead = $simple_history->get_loggers_that_user_can_read( null, 'sql' );
// ✅ Query is correctly filtered, but result is cached for ALL users!
```

### RECOMMENDED FIXES

**Option 1: Include User ID in Cache Keys (Simple but Less Efficient)**
```php
$user_id = get_current_user_id();
$cache_key = 'sh_quick_stats_data_' . md5( serialize( [ $num_days_month, $num_days_week, $user_id ] ) );
```

**Option 2: Include User Capabilities Hash (Better)**
```php
$user_loggers = $simple_history->get_loggers_that_user_can_read( null, 'array' );
$loggers_hash = md5( implode( ',', array_keys( $user_loggers ) ) );
$cache_key = 'sh_quick_stats_data_' . md5( serialize( [ $num_days_month, $num_days_week, $loggers_hash ] ) );
```

**Option 3: Don't Cache User-Specific Data (Safest)**
- Remove caching for permission-filtered queries
- Only cache truly global data
- Or cache per-logger and combine at runtime based on permissions

### PRIORITY LEVEL: ⚠️ HIGH

This is a **DATA ACCURACY BUG** that causes incorrect event counts to be displayed to users with different permission levels. While not a security vulnerability (only counts are shown, not actual event data), it's still a significant issue that undermines the reliability of the statistics feature.

### FILES TO FIX

1. `/dropins/class-sidebar-stats-dropin.php` - Line 310 (add user context to cache key)
2. `/inc/class-helpers.php` - Lines 1297, 1334, 1380 (add user context to cache keys)
3. Consider removing caching entirely for user-specific queries

## VERIFICATION STATUS (Latest Review)

### ✅ CONFIRMED ISSUES

1. **Timezone Inconsistencies** - VERIFIED
   - Stats Service: Correctly uses UTC (`new \DateTimeImmutable('now', new \DateTimeZone('UTC'))`) ✅
   - Sidebar: Uses server timezone (`strtotime("-$num_days days")`) ❌
   - Email Reports: Uses server timezone (`strtotime('-7 days')`) ❌
   - REST API Stats: Uses server timezone (`new \DateTime('today')` without timezone) ❌
   - Main Log: Uses GMT for storage and queries ✅

2. **Event Grouping Mismatch** - VERIFIED
   - Log_Query: Groups by occasions (`GROUP BY historyWithRepeated.repeated` line 324) ✅
   - Events_Stats: Counts individual events (`COUNT(DISTINCT h.id)` line 87) ❌
   - Helpers week/month: Counts ALL events (`SELECT count(*)` line 1308) ❌
   - Total count: Global option, no grouping, no user filtering ❌

3. **User Permission Cache Issue** - VERIFIED
   - Cache keys don't include user ID or capabilities ❌
   - `get_loggers_that_user_can_read()` correctly filters by user ✅
   - But results are cached globally for all users ❌
   - `get_total_logged_events_count()` is global, not user-filtered at all ❌

### 🆕 ADDITIONAL ISSUES DISCOVERED

4. **No Cache Invalidation**
   - Transients are never cleared when new events are logged
   - Users see stale data until cache expires (5 minutes to 1 hour)
   - No hooks to clear cache on event insertion

5. **Total Events Count Never User-Filtered**
   - `get_total_logged_events_count()` returns global option value
   - Incremented for EVERY event via `increase_total_logged_events_count()`
   - Shows same total for all users regardless of permissions
   - Should either be removed or made user-specific

6. **Chart Data Uses Same Flawed Functions**
   - Sidebar chart uses `get_num_events_per_day_last_n_days()`
   - Has same timezone issues (server timezone, not UTC)
   - Has same permission cache issues (not user-specific)
   - Has same grouping issues (counts individual events)

7. **SQLite Database Differences** (Mentioned but not fully investigated)
   - Document mentions SQLite doesn't support occasion grouping
   - But code shows `get_db_engine()` detection without different SQL paths
   - Needs further investigation for SQLite-specific issues

### ✅ CLARIFIED ISSUES

1. **"Today" Count Method** - VERIFIED CORRECT
   - Uses `Events_Stats::get_num_events_today()` which calls Log_Query
   - Returns `total_row_count` from Log_Query
   - **CONFIRMED**: `total_row_count` DOES respect occasion grouping!
   - The count query joins with the grouped results (`GROUP BY historyWithRepeated.repeated`)
   - Counts occasions, not individual events ✅

2. **Top Users Query**
   - `get_top_users()` doesn't filter by `get_loggers_that_user_can_read()`
   - Could show activity from loggers user shouldn't see
   - But only shows user avatars, not event details
   - Still a minor permission issue

### SUMMARY OF VERIFICATION

All three major issues are **CONFIRMED**:
1. **Timezone inconsistencies** cause day-boundary mismatches
2. **Event grouping mismatches** cause 100x+ discrepancies  
3. **Permission cache issues** cause wrong counts for different users

Additional issues found:
- No cache invalidation mechanism
- Total events count is never user-filtered
- Chart data inherits all the same problems
- SQLite handling needs investigation