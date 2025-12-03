# Issue #606: Database tables not created

## Problem

Database tables for Simple History are not automatically created in certain scenarios:

1. **MU Plugin**: When using the plugin as a Must-Use plugin
2. **Site Duplication**: When duplicating a site using a duplication tool
3. **Network Activation**: When network activating the plugin on multisite

## Root Cause Analysis

### Initial Hypothesis (Confirmed)
The `register_activation_hook()` is not fired in these scenarios. However, Simple History already moved away from using this hook - table creation now runs on `after_setup_theme` instead.

### Actual Root Cause
The setup code in `class-setup-database.php` relies **solely on the `simple_history_db_version` option** to decide whether to create tables. It **never verifies if tables actually exist**.

```php
// inc/services/class-setup-database.php:66-72
private function setup_new_to_version_1() {
    $db_version = $this->get_db_version();

    if ( $db_version !== 0 ) {
        return;  // Skips table creation if option exists!
    }
    // ... create tables ...
}
```

### Why This Fails

**Site Duplication Scenario:**
| What gets copied | Result |
|------------------|--------|
| `wp_options` table | `simple_history_db_version = 7` exists |
| `wp_simple_history` table | NOT copied (custom table) |
| `wp_simple_history_contexts` table | NOT copied (custom table) |

Result: Simple History sees `db_version = 7` and thinks "already set up" → tables never created → fatal error.

**Multisite Network Activation Scenario:**
- Main site: Tables created, `db_version = 7` in `wp_options`
- Subsite: Should have fresh `wp_2_options` with no `db_version`... but still failing
- Possible causes: Object caching issues, site created from template, or other edge cases

**MU Plugin Scenario:**
- If previously installed as regular plugin, `db_version` option remains
- Moving to mu-plugins doesn't reset the option
- Tables might be in different location or deleted

## User Reports

### Report 1: Multisite Network Activation
> When we install the plugin and enable it on a network level, the DB tables are created for the main site (site id =1), but not for subsites (site id >1).
> Activating the plugin on a subsite level does not fix the issues.

Error:
```
Fatal error: Uncaught Exception: Table 'database.wp_2_simple_history' doesn't exist
in /wp-content/plugins/simple-history/inc/class-log-query.php:405
```

### Report 2: MU Plugin Installation
Error:
```
Fatal error: Uncaught Exception: Error when performing query: Table
'stf_web.stf_simple_history' doesn't exist
in /var/www/html/web/app/mu-plugins/simple-history/inc/class-log-query.php:403
```

## Proposed Solutions

### 1. Graceful Error Handling in Log_Query

Currently `Log_Query` throws a fatal exception when the database query fails. This is not WordPress-like.

**Current behavior (crashes):**
```php
if ( ! empty( $wpdb->last_error ) ) {
    throw new \Exception( 'Error when performing query: ' . $wpdb->last_error );
}
```

**WordPress pattern in `$wpdb->query()`:**
```php
if ( $this->last_error ) {
    if ( $this->insert_id && preg_match( '/^\s*(insert|replace)\s/i', $query ) ) {
        $this->insert_id = 0;
    }
    $this->print_error();
    return false;
}
```

**Proposed change:**
```php
if ( ! empty( $wpdb->last_error ) ) {
    $this->last_error = $wpdb->last_error;
    // Optionally log it
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        error_log( 'Simple History query error: ' . $wpdb->last_error );
    }
    return false;
}
```

Then callers check for `false` and handle gracefully. The admin page can show a friendly "Database tables missing" message instead of a fatal error.

### 2. Auto-recreate Missing Tables

Store additional metadata to detect when tables need to be (re)created:

```php
private function setup_new_to_version_1() {
    $db_version = $this->get_db_version();
    $stored_prefix = get_option('simple_history_db_prefix', '');
    $current_prefix = $GLOBALS['wpdb']->prefix;

    // Skip only if:
    // - Version is set (not fresh install)
    // - Prefix matches (same site context)
    // - Tables verified to exist for this prefix
    if ($db_version !== 0
        && $stored_prefix === $current_prefix
        && $this->tables_verified()
    ) {
        return;
    }

    // Create tables...

    // After success:
    update_option('simple_history_db_prefix', $current_prefix);
    update_option('simple_history_tables_verified', true);
}
```

### How This Fixes Each Scenario

| Scenario | Detection Method | Result |
|----------|------------------|--------|
| Fresh install | `db_version = 0` | Creates tables |
| Multisite subsite | Prefix mismatch (`wp_` vs `wp_2_`) | Creates tables |
| Site duplication (different prefix) | Prefix mismatch | Creates tables |
| Site duplication (same prefix) | `tables_verified` not set or tables don't exist | Creates tables |
| MU plugin (fresh) | `db_version = 0` | Creates tables |
| MU plugin (after regular plugin) | `tables_verified` check | Creates tables if missing |

### Performance Considerations

- **Fresh install**: No extra queries
- **Normal operation**: No extra queries (prefix matches, verified flag is autoloaded)
- **After duplication/migration**: One-time table existence check, then cached

## References

- [WordPress Support Forum Thread](https://wordpress.org/support/topic/database-tables-missing-in-multisite/)
- [register_activation_hook documentation](https://developer.wordpress.org/reference/functions/register_activation_hook/)
- Current table creation: `inc/services/class-setup-database.php:66-105`
- Table existence check: `inc/class-helpers.php:717-740` (`Helpers::required_tables_exist()`)

## Local Testing Results

### Bug Reproduced: Orphaned db_version Option ✅

**Setup:**
1. Fresh MU plugin install (tables created, working)
2. Run `wp simple-history dev drop_tables --yes` (deletes tables, keeps options)
3. `simple_history_db_version` option still exists with value `7`

**Result - Two different failure modes:**

| Scenario | Error Type | User Experience |
|----------|-----------|-----------------|
| **Logging events** (login, edits, etc.) | Non-fatal DB error in debug.log | Site works, but events silently not logged |
| **Viewing Simple History admin page** | Fatal exception | White screen crash |

**Why the difference?**

- **Logging events:** Uses `$wpdb->insert()` which fails silently (returns `false`, logs to debug.log)
- **Querying events:** `Log_Query` explicitly checks `$wpdb->last_error` and throws exception:

```php
// inc/class-log-query.php:243-247
if ( ! empty( $wpdb->last_error ) ) {
    throw new \Exception(
        'Error when performing query: ' . $wpdb->last_error
    );
}
```

**Debug log example (non-fatal):**
```
[03-Dec-2025 14:08:06 UTC] WordPress database error Table 'wordpress.wp_mu_simple_history'
doesn't exist for query SHOW FULL COLUMNS FROM `wp_mu_simple_history` made by wp_signon,
wp_authenticate, apply_filters('authenticate'), WP_Hook->apply_filters,
wp_authenticate_username_password, apply_filters('wp_authenticate_user'),
WP_Hook->apply_filters, Simple_History\Loggers\User_Logger->onWpAuthenticateUser,
Simple_History\Loggers\Logger->warning_message, Simple_History\Loggers\Logger->log_by_message_key,
Simple_History\Loggers\Logger->log, QM_DB->query
```

### MU Plugin - Fresh Install: WORKS ✅

**Test environment:**
- Docker container: `wordpress_mu_test` on port 8306
- Simple History mounted to `mu-plugins/simple-history/`
- MU loader file at `mu-plugins/simple-history-loader.php`
- Fresh WordPress install with no prior Simple History data

**Result:** Tables are created correctly. Simple History works as expected.

**Conclusion:** The bug is NOT triggered on fresh MU plugin installations. The issue only occurs when:
1. The `simple_history_db_version` option already exists (from previous install or site duplication)
2. But the actual tables don't exist

This confirms the root cause: the setup code trusts the version option without verifying tables exist.

### Multisite Network Activation: TODO

### Site Duplication Simulation: TODO

## WP_Error Implementation (Completed)

Changed `Log_Query` to return `WP_Error` instead of throwing exceptions, making it more WordPress-like.

### Files Modified

| File | Changes |
|------|---------|
| `inc/class-log-query.php` | Return `WP_Error` instead of throwing `\Exception` |
| `inc/class-wp-rest-events-controller.php` | Handle `WP_Error` and return proper REST error response |
| `inc/class-events-stats.php` | Handle `WP_Error` and return 0 |
| `inc/class-export.php` | Handle `WP_Error` with `wp_die()` for download |
| `dropins/class-rss-dropin.php` | Handle `WP_Error` and return empty results |
| `templates/settings-tab-debug.php` | Handle `WP_Error` and display error message |
| `inc/services/wp-cli-commands/class-wp-cli-get-command.php` | Handle `WP_Error` with `WP_CLI::error()` |
| `inc/services/wp-cli-commands/class-wp-cli-list-command.php` | Handle `WP_Error` with `WP_CLI::error()` |
| `inc/deprecated/class-simplehistorylogquery.php` | Updated return type docblock |

### Before and After

**Before (Fatal crash):**
```php
if ( ! empty( $wpdb->last_error ) ) {
    throw new \Exception( 'Error when performing query: ' . $wpdb->last_error );
}
```

**After (Returns WP_Error):**
```php
if ( ! empty( $wpdb->last_error ) ) {
    return new \WP_Error(
        'simple_history_db_error',
        __( 'Database query failed.', 'simple-history' ),
        array( 'db_error' => $wpdb->last_error )
    );
}
```

### Test Results

**WP-CLI with missing tables:**
```
Error: Database query failed.
```
(Clean exit code 1, no PHP fatal error)

**REST API with missing tables:**
```json
{
  "code": "simple_history_db_error",
  "message": "Database query failed.",
  "data": {
    "db_error": "Table 'wordpress.wp_simple_history' doesn't exist"
  }
}
```
(Proper REST error response, no crash)

**Frontend (React app):**
No errors at all - Simple History does not perform any database queries on the frontend. All data is fetched via REST API, so the frontend gracefully handles API errors. Additionally, the log is only fetched on demand when hovering the admin menu bar item, so missing tables won't cause any issues until the user actively tries to view the log.

### Type Safety Fixes

Fixed TypeError in `Helpers::get_data_for_date_filter()` when tables don't exist:

| Function | Issue | Fix |
|----------|-------|-----|
| `get_unique_events_for_days()` | Returns null/string when query fails, causing division error | Always return `(int)`, don't cache null results |
| `get_pager_size()` | Could return string from option/filter | Return `max(1, (int) $pager_size)` to prevent type errors and division by zero |

## Auto-Recovery Implementation (Completed)

Added automatic table recreation when queries or inserts fail due to missing tables. Zero overhead for normal operation.

### How It Works

1. **Query fails** → Check if error is "table doesn't exist"
2. **If yes** → Reset `simple_history_db_version` to 0, run setup steps to create tables
3. **Retry** → Original operation succeeds

### Files Modified

| File | Changes |
|------|---------|
| `inc/services/class-setup-database.php` | Added `recreate_tables_if_missing()` and `is_table_missing_error()` static methods |
| `inc/class-log-query.php` | Auto-recovery in `query()` - recreates tables and retries on table missing error |
| `loggers/class-logger.php` | Auto-recovery in `log()` - recreates tables and retries on insert failure |

### Test Results

**Test: Drop tables, then query events via REST API:**
- Tables automatically recreated
- Query returned empty array (not error)

**Test: Drop tables, then log an event:**
- Tables automatically recreated
- Event was logged successfully (not lost)

```bash
# Before: Tables missing, db_version = 7
# Action: SimpleLogger()->info("Test event from CLI");
# After: Tables recreated, event logged with ID 1
```

### Key Benefits

- **Zero overhead** for normal sites (no extra queries)
- **Automatic recovery** on first query/insert failure
- **No events lost** - first event after recovery is saved
- **Single request recovery** - no need to refresh

## Automated Tests

Two test suites verify the auto-recovery functionality:

### Unit Tests (`tests/wpunit/DatabaseAutoRecoveryTest.php`)

| Test | Description |
|------|-------------|
| `test_is_table_missing_error_detects_mysql_errors` | Verifies error detection regex matches MySQL/MariaDB "table doesn't exist" errors |
| `test_is_table_missing_error_ignores_other_errors` | Ensures other DB errors (duplicate key, connection) don't trigger recovery |
| `test_recreate_tables_method_exists_and_is_callable` | Confirms the recovery methods exist and are accessible |
| `test_recreate_tables_only_runs_once` | Verifies static flag prevents infinite recursion |
| `test_log_query_returns_results_after_tables_exist` | Basic query functionality test |
| `test_logger_logs_event_successfully` | Basic logging functionality test |

### Functional Tests (`tests/functional/DatabaseAutoRecoveryCest.php`)

These tests actually drop tables and verify recreation:

| Test | Description |
|------|-------------|
| `test_auto_recovery_on_logging` | Drops tables, user logs in → tables recreated, login event logged |
| `test_auto_recovery_on_query` | Drops tables, visits admin page → tables recreated, no crash |
| `test_events_logged_after_recovery` | Drops tables, logs in twice → both events logged (not lost) |

**Why functional tests?** Unit tests use database transactions which interfere with DDL statements (DROP TABLE). Functional tests run each test in isolation with full database access.

## Progress

- [x] Investigate current table creation logic
- [x] Confirm hypothesis about activation hook
- [x] Identify actual root cause (version option without table verification)
- [x] Analyze user error reports
- [x] Design solution with minimal performance impact
- [x] Verify MU plugin works on fresh install
- [x] Reproduce bug with orphaned db_version option
- [x] Document two failure modes (silent logging failure vs fatal query error)
- [x] Research WordPress $wpdb error handling pattern (return false, don't crash)
- [x] Implement WP_Error handling in Log_Query
- [x] Update all callers to handle WP_Error
- [x] Test WP_Error handling with missing tables
- [x] Fix type errors in Helpers (division with null/string)
- [x] Implement auto-recreate missing tables
- [x] Test auto-recovery for queries (Log_Query)
- [x] Test auto-recovery for logging (Logger)
- [x] Add unit tests for auto-recovery methods
- [x] Add functional tests for table drop/recreate flow
- [ ] Test multisite network activation
