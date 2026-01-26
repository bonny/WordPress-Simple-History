# Issue #606: Database tables not created

## Problem

Database tables are not created in certain scenarios:
- **MU Plugin**: Tables missing when using as Must-Use plugin
- **Site Duplication**: Options copied but custom tables not copied
- **Multisite Network Activation**: Subsites don't get tables

## Root Cause

The setup code relies **solely on the `simple_history_db_version` option** to decide whether to create tables. It never verifies if tables actually exist.

```php
// inc/services/class-setup-database.php
if ( $db_version !== 0 ) {
    return;  // Skips table creation if option exists!
}
```

**Site duplication example:**
- `wp_options` copied → `simple_history_db_version = 7` exists
- `wp_simple_history` table → NOT copied (custom table)
- Result: Plugin thinks "already set up" → tables never created → fatal error

## Solution Implemented

### 1. Graceful Error Handling
Changed `Log_Query` from throwing exceptions to returning `WP_Error`:
- REST API returns proper error response
- WP-CLI shows clean error message
- No more fatal crashes

### 2. Auto-Recovery
When a query or insert fails due to missing tables:
1. Detect "table doesn't exist" error
2. Reset `simple_history_db_version` to 0
3. Run setup steps to recreate tables
4. Retry original operation

**Key features:**
- Zero overhead for normal sites
- Single request recovery
- No events lost - first event after recovery is saved
- Static flag prevents infinite recursion

### Files Changed

| File | Purpose |
|------|---------|
| `inc/services/class-setup-database.php` | `recreate_tables_if_missing()` and `is_table_missing_error()` |
| `inc/class-log-query.php` | Return `WP_Error`, auto-recovery on query |
| `loggers/class-logger.php` | Auto-recovery on insert |
| `inc/class-wp-rest-events-controller.php` | Handle `WP_Error` |
| `inc/class-export.php` | Handle `WP_Error` with `wp_die()` |
| `dropins/class-rss-dropin.php` | Handle `WP_Error`, return empty |
| WP-CLI commands | Handle `WP_Error` with `WP_CLI::error()` |

## Test Coverage

**Unit tests** (`tests/wpunit/DatabaseAutoRecoveryTest.php`):
- Error detection for MySQL "table doesn't exist" messages
- Recursion prevention (only runs once per request)

**Functional tests** (`tests/functional/DatabaseAutoRecoveryCest.php`):
- Drop tables → login → tables recreated, event logged
- Drop tables → visit admin → tables recreated, no crash
- Multiple events after recovery all logged (not lost)

## References

- [GitHub Issue #606](https://github.com/bonny/WordPress-Simple-History/issues/606)
- [Support Forum Thread](https://wordpress.org/support/topic/database-tables-missing-in-multisite/)
