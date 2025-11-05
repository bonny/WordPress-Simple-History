# Issue #390: Research: Possible and interesting to log rollbacks for failed manual and auto plugin and theme updates

## Issue Overview

Research whether it's possible and interesting to log rollbacks that occur when plugin and theme updates fail (both manual and automatic updates).

## Context

WordPress now has rollback functionality for failed updates:
- **WordPress 6.3+**: Rollback for failed manual plugin and theme updates
- **Merged PR #5287**: Rollback for auto-updates with fatal errors

## Related Links

- Track ticket: https://core.trac.wordpress.org/ticket/51857
- PR: https://github.com/WordPress/wordpress-develop/pull/5287
- Make post (auto-update rollback): https://make.wordpress.org/core/2024/04/19/merge-proposal-rollback-auto-update/
- Make post (6.3 rollback): https://make.wordpress.org/core/2023/07/11/new-in-6-3-rollback-for-failed-manual-plugin-and-theme-updates/
- Changeset: https://core.trac.wordpress.org/changeset/58128

## Research Goals

1. Determine what hooks/actions are available for detecting rollbacks
2. Identify what information we can capture when a rollback occurs
3. Assess if this would provide valuable information to users
4. Determine implementation approach for Plugin and Theme loggers

## Status

- ✅ Issue moved to "In progress" on project board
- ✅ Branch created: `issue-390-log-rollbacks-failed-updates`
- ✅ Research completed

## Research Findings

### WordPress Rollback Functionality Overview

**WordPress 6.3 (July 2023):** Introduced automatic rollback for failed manual plugin and theme updates. When an update fails, WordPress automatically restores the previous version from a temporary backup.

**WordPress 6.6 (July 2024):** Extended rollback to automatic updates. If an auto-update breaks the site (detected via loopback request), WordPress automatically reverts to the previous stable version.

### Technical Implementation

**How It Works:**

1. **Before Update:** WordPress moves the current plugin/theme to `wp-content/upgrade-temp-backup/` using `WP_Upgrader::move_to_temp_backup_dir()`
2. **During Update:** The new version is installed
3. **On Success:** Backup is deleted via shutdown action
4. **On Failure:** Backup is restored via `WP_Upgrader::restore_temp_backup()` on shutdown

**Key WordPress Methods:**
- `WP_Upgrader::move_to_temp_backup_dir()` - Creates backup before update
- `WP_Upgrader::restore_temp_backup()` - Restores from backup on failure
- Restoration happens automatically on `shutdown` hook when WP_Error is detected

### Available Hooks

**Primary Hook: `upgrader_install_package_result`**
- Filters the result of `WP_Upgrader::install_package()`
- Introduced in WordPress 5.7.0
- Fires in `WP_Upgrader::run()` method
- Provides access to:
  - `$result` - Array or WP_Error with installation result
  - `$hook_extra` - Contains plugin/theme info and `temp_backup` data

**Example `$hook_extra` structure:**
```php
Array(
    [plugin] => plugin-slug/plugin-slug.php
    [temp_backup] => Array(
        [slug] => plugin-slug
        [src] => /var/www/html/wp-content/plugins
        [dir] => plugins
    )
)
```

**Other Related Hooks:**
- `upgrader_process_complete` - Fires after upgrade process completes
- `upgrader_package_options` - Filters package options
- `upgrader_pre_install`, `upgrader_post_install` - Can return WP_Error to trigger rollback

### Current Simple History Implementation

**Plugin Logger (`class-plugin-logger.php`):**
- ✅ Already uses `upgrader_install_package_result` hook (line 219)
- ✅ Already captures package results including errors (line 289-293)
- ✅ Stores results in `$package_results` property with temp_backup info
- ✅ Already logs update failures as:
  - `plugin_update_failed` (single updates)
  - `plugin_bulk_updated_failed` (bulk updates)
- ✅ Has access to `temp_backup` data in `$hook_extra`

**Theme Logger (`class-theme-logger.php`):**
- ❌ Does not use `upgrader_install_package_result` hook
- ✅ Uses `upgrader_process_complete` for installs and updates
- ❌ Does not capture detailed error information
- ❌ Does not have access to temp_backup data

### Analysis: Is Rollback Logging Possible?

**YES! WordPress has TWO completely different rollback features:**

## Important: These Are Two Separate WordPress Features

**Key Understanding:** These are NOT the same feature at different times. They are **two completely independent WordPress features** that happen to both result in rollbacks:

1. **Feature 1 (WP 6.3):** Rollback when **file operations fail** during installation
2. **Feature 2 (WP 6.6):** Rollback when **PHP code breaks** after successful installation

**Different problems, different solutions, different hooks, different implementations.**

## Rollback Scenario 1: Installation Failures (WordPress 6.3+)

**What fails:** File operations DURING installation
**Problem type:** File system issues (permissions, disk space, corrupted files)

**Applies to:** Manual updates AND auto-updates
**When it happens:** Update process fails during installation
**Triggers:**
- File system errors (permissions, disk space)
- Corrupted download/ZIP extraction fails
- Can't remove old plugin files
- Can't copy new plugin files

**How it works:**
1. WordPress moves old plugin to `wp-content/upgrade-temp-backup/`
2. Tries to install new version
3. Installation fails → Returns WP_Error
4. WordPress schedules `restore_temp_backup()` on `shutdown` hook (line 936, class-wp-upgrader.php)
5. Rollback happens automatically

**Detection method:**
- `upgrader_install_package_result` hook fires with WP_Error
- Check if `temp_backup` data exists in `$hook_extra`
- Combination = rollback will occur on shutdown

**Simple History status:** ✅ Already captures this! (Plugin Logger line 219)

## Rollback Scenario 2: Fatal Error Detection (WordPress 6.6+)

**What fails:** PHP code execution AFTER successful installation
**Problem type:** Code errors in the newly installed plugin

**Applies to:** Auto-updates ONLY (not manual updates)
**When it happens:** Plugin installs successfully but causes fatal PHP error
**Triggers:**
- New plugin version has syntax errors
- Missing dependencies (e.g., `Call to undefined function`)
- Incompatible PHP code
- Fatal errors when plugin loads

**How it works:**
1. WordPress moves old plugin to backup
2. New version installs successfully
3. WordPress performs loopback request to check for fatal errors (line 566, class-wp-automatic-updater.php)
4. Fatal error detected → Immediately calls `restore_temp_backup()` (line 576)
5. WordPress adds specific message: `plugin_update_fatal_error_rollback_successful` or `plugin_update_fatal_error_rollback_failed` (lines 589-596)

**Detection method:**
- `automatic_updates_complete` action (line 783, class-wp-automatic-updater.php)
- Check `$update_results` for messages containing rollback status

**Simple History status:** ❌ NOT currently captured (needs new hook)

## Key Differences Between The Two Features

| Aspect | Scenario 1: Installation Fails | Scenario 2: Fatal Error |
|--------|-------------------------------|------------------------|
| **What fails** | File operations | PHP code execution |
| **When** | DURING installation | AFTER installation completes |
| **Example problem** | "Can't write files", "Permission denied" | "Call to undefined function", syntax error |
| **Installation status** | Never completes | Completes successfully |
| **New code runs** | ❌ No (never installed) | ✅ Yes (installed but breaks) |
| **Hook to detect** | `upgrader_install_package_result` | `automatic_updates_complete` |
| **Manual updates** | ✅ Protected | ❌ NOT protected |
| **Auto updates** | ✅ Protected | ✅ Protected |
| **WordPress version** | 6.3+ | 6.6+ |
| **Currently logged** | ✅ YES (implemented) | ❌ NO (not yet) |

## Summary: Manual vs Auto Updates

| Update Type | Installation Failure Rollback | Fatal Error Rollback |
|-------------|------------------------------|---------------------|
| **Manual** | ✅ Yes (WP 6.3+) | ❌ No protection! |
| **Auto** | ✅ Yes (WP 6.3+) | ✅ Yes (WP 6.6+) |

**Important:** Manual updates do NOT have fatal error detection/rollback. If you manually update a plugin and it has a fatal error, your site breaks. Only auto-updates have this protection.

## Real-World Examples

### Scenario 1: Installation Failure
```
User clicks "Update Now" for Akismet
→ WordPress creates backup ✅
→ Downloads update package ✅
→ Tries to extract files... ❌ ERROR: Disk full
→ Installation FAILS
→ WordPress rolls back on shutdown ✅
→ Simple History logs: "Update failed (rolled back)" ✅
```

### Scenario 2: Fatal Error After Install
```
WordPress auto-updates Akismet at 3 AM
→ WordPress creates backup ✅
→ Downloads update package ✅
→ Extracts and installs files ✅
→ Installation SUCCEEDS
→ WordPress tests site... ❌ Fatal error: Call to undefined function
→ WordPress immediately rolls back ✅
→ Simple History logs: Nothing yet ❌ (not implemented)
```

### Current Implementation Status

## ✅ IMPLEMENTED: Scenario 1 (Installation Failures)

**Status:** Fully implemented as of this research!

**What was added:**
- ✅ Detects when rollback will occur (checks `temp_backup` + `is_wp_error`)
- ✅ Stores rollback information in `$package_results`
- ✅ Adds rollback context to log entries
- ✅ Visual rollback indicator (yellow box) in log details
- ✅ Shows "WordPress automatically restored the previous version from backup"
- ✅ Displays backup slug and error details
- ✅ Works for both manual and auto updates
- ✅ Works for both single and bulk updates

**Code changes made:**
- Enhanced `on_upgrader_install_package_result()` method (line 296-313)
- Enhanced single update failure logging (line 875-883)
- Enhanced bulk update failure logging (line 988-997)
- Added rollback details display method (line 1437-1484)

**What users see:**
```
Failed to update plugin "Akismet"

┌─────────────────────────────────────────┐
│ ✓ Automatic Rollback                    │
│ WordPress automatically restored the    │
│ previous version from backup.           │
└─────────────────────────────────────────┘
```

## ❌ NOT IMPLEMENTED: Scenario 2 (Fatal Error Rollbacks)

**Status:** Not yet implemented (documented in readme for future work)

**What's missing:**
1. ❌ No hook for `automatic_updates_complete` action
2. ❌ Not capturing auto-update fatal error rollbacks at all
3. ❌ Missing messages like `plugin_update_fatal_error_rollback_successful`
4. ❌ This is a completely separate rollback mechanism that's not logged

**Why not implemented:**
- Different WordPress feature (WP 6.6+)
- Requires different hook (`automatic_updates_complete`)
- Only affects auto-updates
- Documented as Phase 2 in recommendations

### Recommendations

## For Scenario 1: Installation Failure Rollbacks

#### Option 1A: Enhance Existing Messages (Low Effort)

Update existing failure messages to indicate rollback:
- Change `plugin_update_failed` to mention rollback occurred
- Add rollback information to context when `temp_backup` exists
- Same for Theme Logger

**Example:**
```php
// Current: "Failed to update plugin "{plugin_name}""
// Enhanced: "Failed to update plugin "{plugin_name}" (automatically rolled back to previous version)"
```

**Implementation:**
```php
// In on_upgrader_install_package_result
if (is_wp_error($result) && isset($hook_extra['temp_backup'])) {
    $context['rollback_occurred'] = true;
    $context['rollback_backup_location'] = $hook_extra['temp_backup'];
}
```

**Pros:**
- Minimal code changes
- Uses existing infrastructure
- Provides valuable context

**Cons:**
- Not a separate message type
- Cannot filter specifically for rollback events

## For Scenario 2: Fatal Error Rollbacks (Auto-Updates)

#### Option 2A: Add `automatic_updates_complete` Hook (Medium Effort)

**New hook needed:**
```php
// In Plugin Logger loaded() method
add_action( 'automatic_updates_complete', array( $this, 'on_automatic_updates_complete' ), 10, 1 );
```

**New method to add:**
```php
public function on_automatic_updates_complete( $update_results ) {
    // Check plugin updates for fatal error rollbacks
    if ( ! isset( $update_results['plugin'] ) ) {
        return;
    }

    foreach ( $update_results['plugin'] as $update ) {
        if ( ! is_wp_error( $update->result ) ) {
            continue;
        }

        $error_codes = $update->result->get_error_codes();

        // Check for fatal error rollback messages
        if ( in_array( 'plugin_update_fatal_error_rollback_successful', $error_codes ) ) {
            // Log successful rollback
        }

        if ( in_array( 'plugin_update_fatal_error_rollback_failed', $error_codes ) ) {
            // Log failed rollback (critical!)
        }
    }
}
```

**New message types needed:**
- `plugin_auto_update_fatal_error_rollback_successful`
- `plugin_auto_update_fatal_error_rollback_failed`

**Pros:**
- Captures auto-update fatal error rollbacks (currently missing)
- Distinct message types for this important scenario
- Users know when auto-updates protected their site

**Cons:**
- More code to add
- Only applies to auto-updates
- Additional translations needed

## Recommended Approach: Implement Both

**Phase 1 (Quick Win):**
- Enhance Scenario 1 messages to mention rollback
- Low effort, high value

**Phase 2 (Important):**
- Add `automatic_updates_complete` hook for Scenario 2
- Critical for complete rollback logging

**Why both are needed:**
- Scenario 1 = Installation failures (manual + auto)
- Scenario 2 = Fatal errors after install (auto only)
- Different mechanisms, different hooks, both important

## Conclusion

**Is it possible?** ✅ YES - For BOTH rollback scenarios!

**Is it interesting?** ✅ YES - Very valuable for users to know:
- When updates fail (installation or fatal errors)
- That their site was automatically protected via rollback
- What was rolled back and why
- Especially important: Auto-update fatal error protection (WP 6.6+)

**Implementation Complexity:**
- **Scenario 1 (Installation failures):** LOW - Infrastructure exists, needs enhancement
- **Scenario 2 (Fatal error rollbacks):** MEDIUM - New hook and message types needed

**Value to Users:** VERY HIGH
- **Transparency** about update failures (manual + auto)
- **Confidence** in WordPress's automatic rollback protection
- **Critical visibility** when auto-updates prevent site breakage from fatal errors
- **Better troubleshooting** information
- **Understanding** of site stability and protection mechanisms

## Recommended Implementation Plan

### Phase 1: Installation Failure Rollbacks (Quick Win)
1. ✅ **Already tested:** Test plugin confirms Scenario 1 works
2. Enhance Plugin Logger `plugin_update_failed` messages to mention rollback
3. Add rollback context when `temp_backup` exists
4. Add similar capability to Theme Logger

### Phase 2: Fatal Error Rollbacks (Important)
1. Add `automatic_updates_complete` hook to Plugin Logger
2. Create new message types for fatal error rollbacks
3. Log both successful and failed rollback attempts
4. Create test scenarios for auto-update fatal errors
5. Add similar capability to Theme Logger

### Phase 3: Polish & Documentation
1. Update plugin documentation about rollback logging
2. Consider adding rollback statistics/counts
3. User feedback and refinement

## Implementation Notes

### Scenario 1: Installation Failure Rollbacks

#### For Plugin Logger Enhancement

File: `loggers/class-plugin-logger.php`

**Changes needed in `on_upgrader_install_package_result()` (line 243):**
- Check if `temp_backup` exists when storing results
- Add rollback indicator to context
```php
if (is_wp_error($result) && isset($hook_extra['temp_backup'])) {
    $context['rollback_occurred'] = true;
    $context['rollback_backup_info'] = $hook_extra['temp_backup'];
}
```

**Changes needed in failure logging (lines 952-960):**
- Check for rollback context
- Update message to mention rollback
- Add rollback details to output

**Changes needed in messages (line 89-93, 108-112):**
- Update `plugin_update_failed` message text
- Update `plugin_bulk_updated_failed` message text
- Consider: "Failed to update plugin "{plugin_name}" (rolled back to previous version)"

#### For Theme Logger Enhancement

File: `loggers/class-theme-logger.php`

**New hook needed in `loaded()` method:**
```php
add_filter( 'upgrader_install_package_result', array( $this, 'on_upgrader_install_package_result' ), 10, 2 );
```

**New method needed:**
- Similar to Plugin Logger implementation
- Store package results with temp_backup info
- Log theme update failures with rollback info

**New message types needed:**
- `theme_update_failed` (with rollback context)
- `theme_bulk_updated_failed` (with rollback context)

### Scenario 2: Fatal Error Rollbacks (Auto-Updates)

#### For Plugin Logger Enhancement

File: `loggers/class-plugin-logger.php`

**New hook needed in `loaded()` method (after line 217):**
```php
add_action( 'automatic_updates_complete', array( $this, 'on_automatic_updates_complete' ), 10, 1 );
```

**New method needed:**
```php
/**
 * Log automatic update results, including fatal error rollbacks.
 * Fired after all automatic updates complete.
 *
 * @param array $update_results Results of all automatic updates.
 */
public function on_automatic_updates_complete( $update_results ) {
    // Only process plugin updates
    if ( ! isset( $update_results['plugin'] ) || ! is_array( $update_results['plugin'] ) ) {
        return;
    }

    foreach ( $update_results['plugin'] as $update ) {
        if ( ! is_wp_error( $update->result ) ) {
            continue;
        }

        $error_codes = $update->result->get_error_codes();

        // Check for fatal error rollback success
        if ( in_array( 'plugin_update_fatal_error_rollback_successful', $error_codes, true ) ) {
            $this->warning_message(
                'plugin_auto_update_fatal_error_rollback_successful',
                array(
                    'plugin_name' => $update->name,
                    'plugin_slug' => $update->item->slug ?? '',
                    'error_message' => $update->result->get_error_message( 'plugin_update_fatal_error_rollback_successful' ),
                )
            );
        }

        // Check for fatal error rollback failure (CRITICAL!)
        if ( in_array( 'plugin_update_fatal_error_rollback_failed', $error_codes, true ) ) {
            $this->critical_message(
                'plugin_auto_update_fatal_error_rollback_failed',
                array(
                    'plugin_name' => $update->name,
                    'plugin_slug' => $update->item->slug ?? '',
                    'error_message' => $update->result->get_error_message( 'plugin_update_fatal_error_rollback_failed' ),
                )
            );
        }
    }
}
```

**New message types needed in `get_info()` (lines 57-131):**
```php
'plugin_auto_update_fatal_error_rollback_successful' => _x(
    'Auto-update of plugin "{plugin_name}" caused fatal error and was automatically rolled back',
    'Plugin auto-update caused fatal error but was rolled back',
    'simple-history'
),
'plugin_auto_update_fatal_error_rollback_failed' => _x(
    'CRITICAL: Auto-update of plugin "{plugin_name}" caused fatal error and rollback FAILED',
    'Plugin auto-update caused fatal error and rollback failed',
    'simple-history'
),
```

**Update search labels (lines 154-158):**
- Add new failure types to "Failed plugin updates" search option

#### For Theme Logger Enhancement

Similar implementation as Plugin Logger:
1. Add `automatic_updates_complete` hook
2. Check for `theme_update_fatal_error_rollback_successful`
3. Check for `theme_update_fatal_error_rollback_failed`
4. Add corresponding message types

## Testing & Verification

### Test Plugin Created ✅

Location: `wp-content/plugins/test-update-rollback/`

A test plugin was created to simulate installation failures (Scenario 1). The plugin:
- Provides admin UI to enable/disable testing
- Intercepts `upgrader_install_package_result` hook
- Forces WP_Error after backup is created
- Triggers real WordPress rollback mechanism

### Test Results (Scenario 1 - Installation Failures)

✅ **Confirmed working:**
- WordPress creates backup in `upgrade-temp-backup/`
- Our filter forces installation failure
- WordPress automatically rolls back on shutdown
- Simple History logs the failure (already captures this!)

**What we verified:**
- `temp_backup` data is present in `$hook_extra`
- Plugin Logger's `on_upgrader_install_package_result()` captures the error
- Rollback happens automatically (verified in debug.log)
- This works for BOTH manual and auto updates

**What's missing in logs:**
- No explicit mention that rollback occurred
- Users see "update failed" but not "and was rolled back"

### Test Scenario for Scenario 2 (Fatal Error Rollbacks)

To test fatal error rollbacks (auto-updates only):
1. Create a plugin with two versions
2. Version 2 has a fatal PHP error (syntax error)
3. Enable auto-updates for the plugin
4. Trigger auto-update via WP-Cron or manually
5. WordPress should detect fatal error via loopback
6. WordPress should rollback and add `plugin_update_fatal_error_rollback_successful` message
7. Check if Simple History captures this (currently it doesn't)

**Note:** This scenario is harder to test as it requires:
- Auto-updates enabled
- A plugin that causes actual fatal error
- WordPress's loopback check to work properly
- May need to wait for scheduled auto-update

## Key Findings Summary

### What We Learned

1. **Two distinct rollback mechanisms exist:**
   - Installation failures (WP 6.3+, manual + auto)
   - Fatal error detection (WP 6.6+, auto only)

2. **Manual updates have limited protection:**
   - ✅ Protected from installation failures
   - ❌ NOT protected from fatal errors
   - If new plugin has fatal error, site breaks

3. **Auto-updates have full protection:**
   - ✅ Protected from installation failures
   - ✅ Protected from fatal errors (WP 6.6+)
   - WordPress tests for fatal errors and rolls back automatically

4. **Simple History partially logs rollbacks:**
   - ✅ Logs installation failures (Scenario 1)
   - ❌ Does NOT log fatal error rollbacks (Scenario 2)

5. **Different hooks needed:**
   - `upgrader_install_package_result` - Already used ✅
   - `automatic_updates_complete` - NOT used ❌

### Impact on Users

**High value to implement:**
- Users need to know when rollbacks protect their site
- Especially for auto-update fatal error rollbacks (Scenario 2)
- Critical for understanding auto-update safety
- Helps troubleshoot plugin compatibility issues
- Provides confidence in WordPress's protection mechanisms

### Code References

All code locations verified in WordPress 6.x source:
- `class-wp-upgrader.php` - Lines 608, 936, 1120, 1196
- `class-plugin-upgrader.php` - Lines 234-238 (temp_backup creation)
- `class-wp-automatic-updater.php` - Lines 566-596 (fatal error rollback), 783 (action hook)

## Final Recommendation

**Implement both scenarios** for complete rollback logging:
1. **Phase 1:** Enhance Scenario 1 (quick win, already mostly there)
2. **Phase 2:** Add Scenario 2 (important for auto-update visibility)

Both are valuable, but Scenario 2 is particularly important as it's a major WordPress 6.6+ feature that users should know about when it saves their site from breaking.
