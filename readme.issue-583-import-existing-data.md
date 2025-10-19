# Issue #583: Generate history based on existing WP data

**Status**: Testing & Review
**Branch**: `issue-583-import-existing-data`
**Issue URL**: https://github.com/bonny/WordPress-Simple-History/issues/583
**Code Quality**: ✅ phpcs passed, ✅ phpstan passed

## Summary

✅ **Core functionality implemented and working**

This feature provides a way to import existing WordPress data into Simple History, populating the log with historical events from before the plugin was activated. The implementation is accessible through an "Experimental Features" admin page where users can manually trigger imports with configurable options.

**What's Working**:
- Import posts and pages with creation and modification dates
- Import users with registration dates
- Configurable post type selection
- Adjustable import limits (1-1000 items per type)
- Proper message formatting matching existing loggers
- Historical date preservation using `_date` context
- Duplicate prevention - automatically skips already-imported items
- Debug tracking for troubleshooting with detailed skip reporting

**Critical Limitation** 🚨:
- **Cannot import more than 1000 items per type** - no pagination/offset mechanism
- Re-running import does NOT progress to next batch (always fetches oldest 1000)
- See "Large Dataset Limitations" section for full details and future solutions

## Overview

When the plugin is installed it contains no history at all - an empty state that's not very useful. This feature aims to populate the log with historical data from the WordPress installation after the plugin is activated.

The information available in WordPress for historical events is limited, but we can pull in:
- Post and page changes (modification dates, authors)
- User registration dates
- Any public post types available in WordPress

## Goals

**Original Goals**:
- ~~Import existing post/page data into Simple History on first activation~~
- Provide a better initial experience for new users
- Show historical context even for events that occurred before plugin installation

**Implemented Approach**:
- Manual import via Experimental Features admin page (not automatic on activation)
- User-controlled import with configurable options
- Transparent process allowing users to test on different sites first

## Implementation Considerations

### Data Sources

Available WordPress data to import:
- Posts/Pages: `post_date`, `post_modified`, `post_author`, `post_status`
- Comments: `comment_date`, `comment_approved`
- Users: `user_registered`
- Options: Limited historical data
- Media: Upload dates and modifications

### Technical Approach

- Detect first-time installation vs. existing installation
- Run import process on plugin activation or as admin action
- Import in batches to avoid timeouts
- Consider performance impact on large sites
- Provide UI feedback during import

### Questions & Answers

- ✅ **Should this run automatically on activation or require user action?**
  - **Answer**: Requires user action via Experimental Features page. Avoids performance issues and gives users control.

- ✅ **How far back should we import data?**
  - **Answer**: Configurable limit (1-1000 items per type). Users control the scope.

- ✅ **Should users be able to configure what gets imported?**
  - **Answer**: Yes. Users can select specific post types and choose whether to import users.

- 🚨 **How to handle large sites with thousands of posts?**
  - **Critical Limitation**: Cannot import more than 1000 items per type (no pagination/offset)
  - **Current Risks**: Memory issues, timeouts, slow queries on very large imports
  - **Workaround**: Only first 1000 items (oldest) will be imported
  - **Future**: Requires batch processing/AJAX with pagination support
  - **See**: "Large Dataset Limitations" section below for full analysis

- ✅ **Should this be a one-time import or repeatable?**
  - **Answer**: Repeatable. Users can safely run it multiple times - duplicate detection automatically skips already-imported items.

## Progress

### Completed
- [x] Initial issue analysis
- [x] Research WordPress data structures for historical events
- [x] Design database import strategy
- [x] Implement post/page history import
- [x] Create admin UI for manual import trigger (Experimental Features page)
- [x] Create importer class
- [x] Fix user registration message to match User_Logger format
- [x] Add detailed debug tracking for imported items
- [x] Add `_imported_event` context marker for programmatic identification
- [x] Implement duplicate prevention using batch SQL queries
- [x] Update UI to display skipped counts

### In Progress
- Manual testing with different WordPress setups
- Testing edge cases (large datasets, missing data, etc.)
- User acceptance testing

### To Do (Future Enhancements)
- [ ] 🚨 **CRITICAL**: Fix 1000-item limitation - add pagination/offset support
- [ ] 🚨 **CRITICAL**: Add memory and timeout protection for large imports
- [ ] Add batch processing for large datasets (for very large sites)
- [ ] Add progress indicator for import process (AJAX/background processing)
- [ ] Test with large datasets (5000+ posts to verify limitation)
- [ ] Handle edge cases (missing authors, deleted content, etc.)
- [ ] Update documentation
- [ ] Consider adding import for other data types (comments, media)

## Implementation Details

### Architecture

The import functionality has been implemented as an **experimental feature** accessible through an admin page, rather than running automatically on activation. This approach:

- Allows users to test the functionality on different sites
- Avoids potential performance issues on large sites during activation
- Provides transparency about what data is being imported
- Gives users control over when and what to import

### Files Created

1. **`inc/class-existing-data-importer.php`**
   - Core importer class responsible for importing historical data
   - Handles posts, pages, and users
   - Uses Simple History's logger infrastructure to create entries with historical dates
   - Supports configurable limits and post types

2. **`inc/services/class-experimental-features-page.php`**
   - Service class that adds an "Experimental Features" admin page
   - Auto-discovered by Simple History (placed in `/inc/services/`)
   - Provides UI for triggering imports
   - Handles form submission and displays results

### Key Features

- **Post Type Selection**: Users can choose which post types to import
- **Configurable Limits**: Import limit (1-1000 items per type) to prevent timeouts
- **User Import**: Optional import of user registration dates
- **Historical Dates**: Uses original `post_date_gmt` and `post_modified_gmt` for accurate history
- **Proper Initiator**: Sets initiator to `OTHER` to distinguish imported events from real-time events
- **Duplicate Prevention**: Automatically detects and skips already-imported items using batch SQL queries
- **Import Tracking**: Displays counts of imported and skipped items with detailed debugging

### Technical Approach

The importer:
1. Queries WordPress for existing posts/users
2. Uses the appropriate logger (Post_Logger, User_Logger)
3. Logs entries with custom dates using the `_date` context key
4. Respects post status (publish, draft, pending, private)
5. Logs both creation and modification events if dates differ
6. Returns detailed tracking data for debugging purposes

### Logger Integration

The importer correctly uses Simple History's logger infrastructure:

**Post Import** (`inc/class-existing-data-importer.php:110-143`):
- Uses `Post_Logger->info_message('post_created', $context)`
- Uses `Post_Logger->info_message('post_updated', $context)` if modification date differs
- Context includes: `post_id`, `post_type`, `post_title`, `_date`, `_initiator`, `_imported_event`

**User Import** (`inc/class-existing-data-importer.php:181-194`):
- Uses `User_Logger->info_message('user_created', $context)`
- Context matches User_Logger format exactly:
  - `created_user_id`, `created_user_login`, `created_user_email`
  - `created_user_first_name`, `created_user_last_name`, `created_user_url`
  - `created_user_role` (comma-separated if multiple roles)
  - `_date`, `_initiator`, `_imported_event`
- Displays as: "Created user {login} ({email}) with role {role}"

**Imported Event Marker**:
- All imported events include `_imported_event => true` in their context
- This allows programmatic identification and filtering of imported events
- Follows existing Simple History pattern (similar to `_xmlrpc_request`, `_rest_api_request`)
- Enables future features like filtering UI, duplicate detection, and analytics
- No GUI changes required - stored silently in database context table

### Duplicate Prevention

**Implementation** (`inc/class-existing-data-importer.php:287-352`):

The importer uses batch SQL queries to detect already-imported items before processing:

**For Posts**:
- Queries for all post IDs in the current batch
- Checks separately for `post_created` and `post_updated` events
- Smart skip logic: only skips if ALL applicable events exist
- Example: If a post was never modified, only checks for `post_created` event

**For Users**:
- Queries for all user IDs in the current batch
- Checks for existing `user_created` events
- Skips users that have already been imported

**Performance**:
- Only 2 SQL queries total per import (one for posts, one for users)
- Uses batch checking with IN clauses instead of N individual queries
- Leverages `_imported_event` context marker for reliable detection

**User Experience**:
- First import: "Imported 100 posts and 20 users into the history log."
- Subsequent import: "Imported 0 posts (skipped 100 already imported) and 0 users (skipped 20 already imported)."
- Page description updated to: "You can run this import multiple times. Items that have already been imported will be automatically skipped to prevent duplicates."

### Debug Tracking

The importer returns detailed results including:
- `posts_imported`: Total count of posts imported
- `users_imported`: Total count of users imported
- `posts_skipped`: Total count of posts skipped (already imported)
- `users_skipped`: Total count of users skipped (already imported)
- `posts_details`: Array with full details of each imported post (ID, title, type, status, dates, events logged)
- `users_details`: Array with full details of each imported user (ID, login, email, registration date, roles)
- `skipped_details`: Array with details of skipped items (type, ID, title/login, reason)
- `errors`: Array of any errors encountered

Debug output is logged via `error_log()` in `inc/services/class-experimental-features-page.php:220-223`

## Findings

### WordPress Data Available for Import

- **Posts/Pages**:
  - `post_date_gmt`: Creation date
  - `post_modified_gmt`: Last modification date
  - Both dates are available and accurate
  - Can distinguish between creation and updates

- **Users**:
  - `user_registered`: Registration timestamp
  - Available for all users

- **Limitations**:
  - No historical data for who made changes
  - No detailed change information (what was changed)
  - Cannot determine the number of times a post was edited
  - Authors in `post_author` field may no longer exist

### Menu Page System

- Simple History uses a sophisticated Menu Manager + Menu Page pattern
- Services in `/inc/services/` are auto-discovered
- Menu pages can be placed in different locations based on settings
- The `Menu_Page` class provides a fluent API for page creation

### Test Results

Initial testing on a development site:
- ✅ Successfully imported 96 posts/pages
- ✅ Successfully imported 22 users
- ✅ Historical dates preserved correctly
- ✅ Both creation and modification events logged for posts
- ✅ User registration messages display correctly with proper format
- ✅ No timeouts or performance issues with dataset size
- ✅ Debug logging works correctly for troubleshooting
- ✅ Duplicate prevention works correctly - re-running import skips all items
- ✅ Skipped counts display correctly in UI
- ⚠️ Edge cases discovered:
  - Posts with `0000-00-00 00:00:00` dates handled gracefully
  - Future scheduled posts imported correctly
  - Empty date fields don't cause errors

### Known Issues & Fixes

1. **User registration message format** (FIXED)
   - **Issue**: Initially used generic `info()` method with custom message
   - **Fix**: Changed to `info_message('user_created', $context)` with proper context parameters
   - **Location**: `inc/class-existing-data-importer.php:181-194`
   - **Result**: Messages now display as "Created user {login} ({email}) with role {role}"

2. **Service namespace import** (FIXED)
   - **Issue**: Incorrect namespace `use Simple_History\Service;`
   - **Fix**: Changed to `use Simple_History\Services\Service;`
   - **Location**: `inc/services/class-experimental-features-page.php:8`

3. **Security - Input sanitization** (FIXED)
   - **Issue**: Missing proper escaping and unslashing for POST/GET data
   - **Fix**: Added `sanitize_text_field()` and `wp_unslash()` for all user inputs
   - **Location**: `inc/services/class-experimental-features-page.php:190-202`

### Known Limitations

1. **Date ordering issue** (See Issue #584)
   - **Issue**: Imported events have high primary key IDs but old dates
   - **Impact**: When importing old data into a site with existing history, imported events appear at the top (by ID) instead of chronologically
   - **Example**: Import 2020 events into site with 2024 events → 2020 events show first (wrong order)
   - **Root Cause**: Simple History orders by `id DESC` not `date DESC` due to occasions grouping requirements
   - **Status**: Separate issue tracked in #584 to implement date ordering option
   - **Workaround**: Import historical data before plugin accumulates new events, or wait for #584 implementation
   - **Related File**: `readme.issue-584-date-ordering.md`

2. **Large Dataset Limitations** 🚨 **CRITICAL**

   ### Cannot Import More Than 1000 Items Total

   **The Problem**:

   The importer has **no offset or pagination mechanism**. It always fetches the oldest posts in the database:

   ```php
   // inc/class-existing-data-importer.php:90-96
   $args = [
       'posts_per_page' => $limit,  // Max 1000
       'orderby' => 'date',
       'order' => 'ASC',  // Always oldest first - NO OFFSET!
   ];
   ```

   **What Happens on a Site with 10,000 Posts:**

   1. **First Import (limit=1000)**:
      - Fetches posts 1-1000 (oldest)
      - Imports all 1000 ✅
      - Result: "Imported 1000 posts"

   2. **Second Import (trying to get more)**:
      - Fetches posts 1-1000 **again** (same oldest posts!)
      - Duplicate detection finds all already imported
      - Skips all 1000 ❌
      - Result: "Imported 0 posts (skipped 1000 already imported)"

   3. **Posts 1001-10000 can NEVER be imported** ❌

   ### Risk Analysis for Large Datasets

   **Memory Issues** 🧠:
   - Loads entire result set into memory: `$posts = get_posts( $args );`
   - 1000 posts × ~5-10KB each = 5-10MB just for post objects
   - Accumulates full details for ALL imported items: `$this->results['posts_details'][]`
   - No limit on detail accumulation - grows unbounded
   - **Risk**: Exceeds PHP `memory_limit` (typically 128MB-256MB)

   **Timeout Issues** ⏱️:
   - For 1000 posts with create + update events = 2000 log entries
   - Each entry requires ~12 database operations (1 history + ~10 context inserts)
   - **Total: ~24,000 database operations** per import
   - Plus 2-4 duplicate detection queries
   - No `set_time_limit()` or timeout handling in code
   - **Risk**: Exceeds PHP `max_execution_time` (typically 30-60 seconds)

   **Database Performance** 🐌:
   - Duplicate detection uses 3-way JOIN with large IN clause:
   ```sql
   SELECT DISTINCT c1.value as post_id
   FROM contexts_table c1
   INNER JOIN contexts_table c2 ON c1.history_id = c2.history_id
   INNER JOIN contexts_table c3 ON c1.history_id = c3.history_id
   WHERE c1.key = 'post_id'
     AND c1.value IN (1,2,3...1000)  -- Large IN clause
   ```
   - **Risk**: Slow queries on sites with large Simple History databases (millions of context rows)
   - Query complexity increases with history size, not just import size

   **No Progress Indication** 😕:
   - Synchronous processing with no user feedback
   - User sees blank page during processing
   - No way to estimate completion time
   - May appear frozen on large imports
   - **Risk**: Users force-refresh, creating partial imports

   ### Recommended Solutions (Future Work)

   **Option 1: Add Offset/Pagination**:
   - Modify query to exclude already-imported post IDs
   - Use `post__not_in` with imported IDs from duplicate check
   - Allows progressive import: run multiple times to import all posts
   - Simple implementation, works with existing duplicate detection

   **Option 2: Background Processing**:
   - WP-Cron scheduled batches (100 posts per batch)
   - AJAX chunked processing with progress bar
   - More complex but better UX for very large sites

   **Option 3: Hybrid Approach**:
   - Add `wp_raise_memory_limit( 'admin' )` for memory protection
   - Add `set_time_limit( 300 )` for 5-minute timeout (if hosting allows)
   - Reduce result detail storage (only keep counts, not full arrays)
   - Document 1000-item limitation clearly in UI

## Related Code

- **Importer**: `inc/class-existing-data-importer.php:1`
- **Service**: `inc/services/class-experimental-features-page.php:1`
- **Post Logger**: `loggers/class-post-logger.php` (used for logging post events)
- **User Logger**: `loggers/class-user-logger.php:47-50` (user_created message definition)
- **Logger Base**: `loggers/class-logger.php` (base class with `log()` method)
- **Menu System**: `inc/class-menu-manager.php`, `inc/class-menu-page.php`

## Testing Notes

### Test Scenarios

1. **Basic Import**
   - [ ] Import posts and pages on a fresh WordPress install
   - [ ] Verify entries appear in history log
   - [ ] Verify dates match original post dates

2. **Different Post Types**
   - [ ] Import custom post types
   - [ ] Import only specific post types

3. **Large Datasets**
   - [ ] Test with 100+ posts
   - [ ] Test with 1000 posts (maximum import size)
   - [ ] Verify no timeouts occur with 1000 posts
   - [ ] Check performance impact
   - [ ] **Test 1000+ limitation**: Create site with 2000+ posts, import with limit=1000, verify only first 1000 imported
   - [ ] **Test duplicate on large dataset**: Run import twice on 1000 posts, verify second run skips all 1000
   - [ ] **Test memory usage**: Monitor PHP memory consumption during 1000-post import
   - [ ] **Test query performance**: Check slow query log for duplicate detection queries

4. **Edge Cases**
   - [ ] Posts with deleted authors
   - [ ] Posts never modified (creation date = modification date)
   - [ ] Posts with future dates
   - [ ] Private/draft posts
   - [ ] Empty site (no posts)

5. **User Import**
   - [ ] Import user registrations
   - [ ] Verify registration dates are correct
   - [ ] Test with deleted users

6. **Duplicate Prevention**
   - [x] Run import twice on same dataset
   - [x] Verify second import skips all items
   - [x] Verify UI shows skipped counts
   - [ ] Import partial overlap (some new, some existing)
   - [ ] Verify only new items are imported

### How to Test

1. Navigate to **Simple History > Experimental** (or **Tools > Simple History > Experimental** depending on settings)
2. Select post types to import
3. Configure import limit
4. Click "Import Data"
5. Review results and check history log
6. Check debug log at: `/data/wp/wordpress-stable-mariadb/wp-content/debug.log` for detailed import results
7. **Test Duplicate Prevention**: Run the same import again and verify that all items are skipped

## Next Steps

### Before Merging to Main

1. **Additional Testing**:
   - [ ] Test on multisite installation
   - [ ] **Test 1000-item limitation**: Verify on site with 5000+ posts that only 1000 are imported
   - [ ] **Test memory limits**: Import 1000 posts and monitor PHP memory usage
   - [ ] **Test timeout limits**: Import 1000 posts and verify completion within timeout
   - [ ] Test with custom post types from popular plugins
   - [ ] Test error handling (database failures, missing loggers, etc.)

2. **User Acceptance**:
   - [ ] Review UI/UX of experimental features page
   - [ ] Confirm messaging is clear for end users
   - [x] ~~Consider adding warning about duplicate imports~~ - Duplicate prevention implemented

3. **Documentation**:
   - [ ] Update main plugin readme if needed
   - [ ] Consider adding inline help text on the experimental page
   - [ ] Document in changelog

### Future Enhancements (Post-Merge)

1. **Performance Improvements** 🚨 **HIGH PRIORITY** (See "Large Dataset Limitations"):
   - **Critical**: Add pagination/offset support to import beyond 1000 items
   - **Critical**: Implement `post__not_in` to exclude already-imported IDs from query
   - Batch processing with AJAX for large datasets (>1000 items)
   - Progress indicator during import (especially for 500+ items)
   - Background processing option (WP-Cron for very large sites)
   - Add memory and timeout protection (`wp_raise_memory_limit()`, `set_time_limit()`)

2. **Additional Data Sources**:
   - Comments (with dates and authors)
   - Media library uploads
   - WordPress options/settings changes
   - Taxonomy term creation dates

3. **Smart Import** (leveraging `_imported_event` context):
   - ~~Detect and skip duplicate entries~~ - ✅ **Implemented** (see Duplicate Prevention section)
   - Import only data after plugin installation (date range filtering)
   - Automatic import on first activation (with user consent)

4. **Advanced Options**:
   - Date range selection
   - Author filtering
   - Dry-run mode to preview what would be imported

5. **Enhanced Filtering** (leveraging `_imported_event` context):
   - Add UI toggle to show/hide imported events
   - Visual badge/indicator for imported events in timeline
   - Separate analytics for imported vs real-time events
   - Option to exclude imported events from exports
   - Bulk delete imported events only (preserve real-time history)
