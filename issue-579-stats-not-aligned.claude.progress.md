# Issue 579: Statistics Not Aligned - Investigation Progress

## Overview
Investigating discrepancies between three statistics features:
1. History Insights Sidebar (class-sidebar-stats-dropin.php)
2. History Insights Page (class-stats-service.php & class-stats-view.php)
3. Weekly Email Reports (class-email-report-service.php)

## Investigation Areas
- [x] Date range calculations consistency
- [ ] Timezone handling
- [ ] Event counting methods
- [ ] Message key filtering

## Progress

### Initial File Review
- Starting investigation...

## CRITICAL FINDINGS

### 1. TIMEZONE INCONSISTENCY - MAJOR ISSUE FOUND! ⚠️

**History Insights Sidebar:**
- Uses `strtotime("-$period_days days")` in Helpers::get_num_events_last_n_days() (class-helpers.php:1312)
- Uses `strtotime('today')` for today's events (class-events-stats.php:996)
- These use **server's default timezone** (likely local time)

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
- Sidebar uses cached count: `Helpers::get_total_logged_events_count()` (stored in option)
- Stats Page uses direct query: `$events_stats->get_total_events($date_from, $date_to)`
- Email Reports uses direct query: `$events_stats->get_total_events($date_from, $date_to)`

**Query Implementation Details:**
- `get_event_count()` method uses: `h.date >= FROM_UNIXTIME(%d) AND h.date <= FROM_UNIXTIME(%d)`
- `FROM_UNIXTIME()` converts Unix timestamp to MySQL datetime in **MySQL server's timezone**
- If MySQL server timezone differs from PHP timezone, this causes mismatches

## ROOT CAUSES OF MISALIGNMENT

1. **Primary Issue - Timezone Mismatch:**
   - Stats Page uses UTC explicitly
   - Sidebar and Email Reports use server's default timezone (likely local time)
   - Database queries use MySQL server timezone via `FROM_UNIXTIME()`
   - This can cause up to 24 hours of difference in displayed data

2. **Secondary Issue - Day Boundary Handling:**
   - Some features include partial days (time component in timestamps)
   - Others start from midnight (`strtotime('today')`)
   - Inconsistent handling of "last 7 days" vs "past week"

3. **Caching Issues:**
   - Sidebar caches data for 5 minutes with transients
   - Helper functions cache for 1 hour
   - Can show stale data compared to real-time queries

## RECOMMENDATIONS FOR FIXES

### Immediate Fixes (High Priority)

1. **Standardize Timezone Handling:**
   ```php
   // All date calculations should use UTC
   $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
   $date_from = $now->modify("-{$days} days")->setTime(0, 0, 0);
   $date_to = $now->setTime(23, 59, 59);
   ```

2. **Update Helper Functions:**
   - Modify `Helpers::get_num_events_last_n_days()` to use UTC
   - Modify `Events_Stats::get_num_events_today()` to use UTC midnight

3. **Fix Email Report Service:**
   - Change line 198, 243, 503 from `strtotime('-7 days')` to UTC-based calculation
   - Ensure consistent day boundaries (midnight to midnight)

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

Priority files to update (to match the main log's GMT/UTC approach):
1. `/inc/class-helpers.php` - Lines 1312, 1360, 1379 (change strtotime to UTC)
2. `/inc/services/class-stats-service.php` - Already uses UTC ✅
3. `/inc/services/class-email-report-service.php` - Lines 198, 243, 503 (change strtotime to UTC)
4. `/inc/class-events-stats.php` - Line 996 (get_num_events_today needs UTC)
5. `/dropins/class-sidebar-stats-dropin.php` - Lines 171-172, 319-320 (use UTC DateTimeImmutable)
6. `/inc/class-wp-rest-stats-controller.php` - Lines 231-234 (use UTC DateTime in get_default_date_range)