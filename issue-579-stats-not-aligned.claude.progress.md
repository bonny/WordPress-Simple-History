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
- ✅ Counts grouped occasions (same as main log) when using MySQL
- ✅ Respects occasionsID grouping for MySQL databases
- ⚠️ Note: SQLite databases will use ungrouped counting

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

### PRIORITY ORDER OF ISSUES

1. **🔥 CRITICAL:** Occasion grouping mismatch (can cause 100x+ discrepancies)
2. **⚠️ HIGH:** Timezone inconsistencies (can cause day-boundary mismatches)  
3. **📝 MEDIUM:** Different time window calculations (28 vs 30 days, etc.)

### CONCLUSION

**The occasion grouping mismatch is likely the PRIMARY cause of user-reported statistics discrepancies.** Sites experiencing login attacks, spam floods, or heavy content editing could see massive differences between what the main log shows (grouped occasions) and what the statistics count (individual events).