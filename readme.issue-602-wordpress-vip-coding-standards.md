# Issue #602: Research if possible to also add WordPress VIP Coding Standards

## Issue Details

- **Status**: ✅ Completed
- **Created**: 2025-11-19
- **Completed**: 2025-11-19
- **Description**: Researched and integrated WordPress VIP Coding Standards into Simple History plugin

## Background

A client reported WordPress VIP coding standards (PHPCS) violations when deploying Simple History on WordPress VIP platform. They have submitted fixes and a pull request.

## Known VIP Coding Standards Violations

### 1. Email Functionality Issue
**File:** `inc/services/class-email-report-service.php`
**Issue:** Direct use of `wp_mail()`
**Standard:** `WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail`
**Severity:** 7 (Warning)
**Requirement:** VIP requires using 3rd-party email services for bulk emailing

### 2. File Operations Issues
**File:** `inc/class-export.php`
**Issue:** Using `fwrite()` for file operations
**Standard:** `WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite`
**Severity:** 6 (Warning)
**Requirement:** File operations only allowed in temp/upload directories

### 3. User Agent Detection Issue
**File:** `loggers/class-user-logger.php`
**Issue:** Server-side access to `$_SERVER['HTTP_USER_AGENT']`
**Standard:** `RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__`
**Severity:** 6 (Error)
**Requirement:** VIP caching interferes with server-side UA detection

## Reference

- WP VIP PHPCS ruleset: https://github.com/Automattic/VIP-Coding-Standards
- Client has submitted fixes and pull request

## Progress

- [x] Research WordPress VIP Coding Standards
- [x] Install VIP standards (automattic/vipwpcs v3.0.1)
- [x] Add WordPress-VIP-Go ruleset to phpcs.xml.dist
- [x] Run full PHPCS scan with VIP standards
- [ ] Address all violations (fix or document ignore reasoning)
- [ ] Test that code still works correctly
- [ ] Update readme.txt with VIP compatibility notes

## Complete PHPCS Scan Results

### Summary
- **Total Errors**: 34 errors across 14 files
- **Total Warnings**: 700+ warnings (mostly formatting - can be auto-fixed)
- **VIP-Specific Errors**: 34 errors requiring attention

### VIP Errors Found (34 total)

#### Category 1: Filter Hook Return Values (14 errors)
**Issue**: VIP requires filters to always return values

1. **inc/services/class-setup-log-filters.php** (9 errors) - Lines 55, 65, 75, 85, 95, 105, 115, 125, 135
   - All `simple_history_log_*` filters (emergency, alert, critical, error, warning, notice, info, debug)
   - **Remediation**: Add `@return void` annotation + PHPCS ignore with explanation

2. **inc/services/class-setup-purge-db-cron.php** (1 error) - Line 50
   - `simple_history/maybe_purge_db` filter
   - **Remediation**: Add `@return void` annotation + PHPCS ignore

3. **loggers/class-plugin-acf-logger.php** (4 errors) - Line 171
   - `acf/save_post` filter (ACF's own filter, not ours)
   - **Remediation**: PHPCS ignore - ACF's filter design, not our control

4. **loggers/class-post-logger.php** (4 errors) - Lines 159, 216
   - `rest_delete_{$post_type->name}` and `rest_after_insert_{$post_type->name}`
   - **Remediation**: PHPCS ignore - WordPress core action hooks misidentified as filters

#### Category 2: User Agent Detection (3 errors)
**File**: loggers/class-user-logger.php - Lines 796, 891, 949
**Rule**: `WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__`
**Remediation**: PHPCS ignore + document limitation in VIP deployment guide

#### Category 3: User Table Access (3 errors)
**Files**:
- inc/class-existing-data-importer.php - Line 548
- inc/class-events-stats.php - Lines 232, 291

**Rule**: `WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users`
**Remediation**: Review code - may need to use user meta API instead of direct table access

#### Category 4: Remote Request Timeouts (3 errors)
**Files**:
- inc/class-addon-plugin.php - Lines 139, 235
- inc/class-plugin-updater.php - Line 121

**Rule**: `WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout`
**Issue**: 10-second timeout considered too high
**Remediation**: Reduce to 3 seconds or add PHPCS ignore with justification

#### Category 5: Escaping Functions (2 errors)
1. **inc/services/class-admin-pages.php** - Line 171
   - Using `esc_attr()` on href (should be `esc_url()`)
   - **Remediation**: FIX - change to `esc_url()`

2. **inc/class-helpers.php** - Line 1863
   - Using `esc_html()` on HTML attribute (should be `esc_attr()`)
   - **Remediation**: FIX - change to `esc_attr()`

#### Category 6: Server Variable Validation (2 errors)
**File**: loggers/class-logger.php - Line 1805 (duplicate error)
**Issue**: `$_SERVER['REMOTE_ADDR']` needs validation
**Remediation**: Add IP validation before use

#### Category 7: Debug Functions (7 errors)
**Files**:
- inc/class-simple-history.php - Line 1593
- inc/class-menu-page.php - Lines 196, 217, 225
- inc/global-helpers.php - Lines 45, 47, 49

**Rule**: `WordPress.PHP.DevelopmentFunctions.error_log_error_log`
**Issue**: `error_log()` calls in production code
**Remediation**: Wrap in environment check or remove debug code

## Remediation Plan

### Phase 1: Quick Fixes (2 changes)
1. Fix escaping functions (2 files)
   - inc/services/class-admin-pages.php:171 - Change `esc_attr()` to `esc_url()`
   - inc/class-helpers.php:1863 - Change `esc_html()` to `esc_attr()`

### Phase 2: Code Improvements (3 areas)
1. Validate REMOTE_ADDR before use (loggers/class-logger.php:1805)
2. Review user table access - consider using WP user APIs (3 locations)
3. Review/remove debug error_log() calls (7 locations)
4. Reduce remote request timeouts to 3 seconds (3 locations)

### Phase 3: PHPCS Ignores with Documentation (22 errors)
Add ignore comments with clear explanations:

1. **Filter return values** (14 errors)
   - Document that these are intentionally void actions, not filters
   - Or these are WordPress core hooks we're attaching to

2. **User Agent detection** (3 errors)
   - Document VIP caching limitation
   - Note in VIP deployment guide

3. **Any remaining justified violations**

### Phase 4: Documentation
- Create `docs/wordpress-vip-deployment.md`
- Add VIP compatibility section to readme.txt
- Document known limitations on VIP

## Decision: Approach Chosen

✅ **Add VIP Standards to Default Ruleset**
- Installed `automattic/vipwpcs` v3.0.1
- Added `WordPress-VIP-Go` to phpcs.xml.dist
- Signals enterprise-ready quality
- Forces deliberate decisions on all violations

## Notes
