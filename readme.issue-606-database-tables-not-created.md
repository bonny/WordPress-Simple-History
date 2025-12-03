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

## Proposed Solution

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

## Progress

- [x] Investigate current table creation logic
- [x] Confirm hypothesis about activation hook
- [x] Identify actual root cause (version option without table verification)
- [x] Analyze user error reports
- [x] Design solution with minimal performance impact
- [ ] Implement fix
- [ ] Test scenarios (MU plugin, site duplication, network activation)
