# Issue #583: Generate history based on existing WP data

**Status**: Testing & Review
**Branch**: `issue-583-import-existing-data`
**Issue URL**: https://github.com/bonny/WordPress-Simple-History/issues/583
**Code Quality**: ✅ phpcs passed, ✅ phpstan passed

## Summary

✅ **Core functionality implemented and working**
📋 **Auto-import on activation planned** (see "Auto-Import on Activation" section below)

This feature provides a way to import existing WordPress data into Simple History, populating the log with historical events from before the plugin was activated. The implementation is accessible through an "Experimental Features" admin page where users can manually trigger imports with configurable options.

**What's Working**:
- ✅ Manual import via Experimental Features page (IMPLEMENTED)
- Import posts and pages with creation and modification dates
- Import users with registration dates
- **Accurate initiators**: Uses post_author for posts (WP_USER), OTHER for users
- **Simplified UI**: Clean interface with expandable options
- **Smart defaults**: All post types + users checked, no limit (imports all data)
- Configurable post type selection
- Optional import limits (up to 10,000 items per type when enabled)
- Proper message formatting matching existing loggers
- Historical date preservation using `_date` context
- Duplicate prevention - automatically skips already-imported items
- Debug tracking for troubleshooting with detailed skip reporting
- **Preview feature**: Shows approximate counts before import
- **Visual indicators**: Simple "Imported from existing data" text label for imported events

**Planned Enhancement**:
- 📋 Auto-import 60 days of data on plugin activation (matches default retention policy)
- 📋 Admin notice showing import results with link to history log
- 📋 Manual import available for premium users needing older data (365+ days)

**Important Notes**:
- **Requires "Enable experimental features"**: Import feature only visible when experimental features are enabled in Settings → General
- Default behavior: Imports ALL data (no limit) for complete historical population
- Large sites (10,000+ items): May need batch processing for optimal performance
- See "Large Dataset Limitations" section for performance considerations

## Overview

When the plugin is installed it contains no history at all - an empty state that's not very useful. This feature aims to populate the log with historical data from the WordPress installation after the plugin is activated.

The information available in WordPress for historical events is limited, but we can pull in:
- Post and page changes (modification dates, authors)
- User registration dates
- Any public post types available in WordPress

## Goals

**Original Goals**:
- Import existing post/page data into Simple History on first activation
- Provide a better initial experience for new users
- Show historical context even for events that occurred before plugin installation

**Current Approach**:
- ✅ Manual import via Experimental Features admin page (IMPLEMENTED)
- User-controlled import with configurable options
- Transparent process allowing users to test on different sites first

**Planned Enhancement - Auto-Import on Activation with Freemium Strategy**:
- Auto-import limited historical data on plugin activation (Core/Free)
- Premium users can unlock unlimited historical import
- See "Freemium Strategy" section below for detailed feature split

## Freemium Strategy

**Purpose**: This feature primarily addresses the "empty state problem" - making the plugin look populated and useful from first install. It's a UX polish feature, not a major selling point, but provides a natural upgrade path for users with extensive site history.

### Core (Free) Import

**One-time welcome import**:
- Import **last 20-50 posts/pages** (just enough to show activity)
- Import **all users** (typically not that many, and feels complete)
- Simple message: "We've populated your log with recent activity"
- No complexity, no settings - just happens on first activation

**Value**: Plugin immediately looks populated and demonstrates functionality without overwhelming the system.

### Premium Import

**"Import More History"** - Simple unlock:
- Unlocks ability to import **all historical posts/pages**
- Same simple one-click experience, just no limits
- Shows count: "Import 2,847 more historical posts"
- Optionally importing more means that there is less risk of the log being too long and causing performance issues.
- That's it. No fancy features needed - just removes the limit.

**Value**:
- Sites with extensive history (years of content, thousands of posts) get complete historical record
- Useful for audits, understanding site evolution, client reporting
- Small sites won't care much (which is fine - it's a discrete feature)

### UI Design

**Free User (after initial import)**:
```
┌─────────────────────────────────────┐
│ ✓ Imported 50 recent posts          │
│ ✓ Imported 12 users                 │
│                                      │
│ [Premium: Import 2,847 more posts]  │ ← Subtle, not pushy
└─────────────────────────────────────┘
```

**Premium User**:
```
┌─────────────────────────────────────┐
│ ✓ Imported 50 recent posts          │
│ ✓ Imported 12 users                 │
│                                      │
│ [Import All Historical Posts]       │ ← Just unlocked
└─────────────────────────────────────┘
```

### Key Points

- **Free version does the job**: Log looks populated ✓
- **Premium is there if they want completeness**: No nagging, just available
- **Transparent value**: Users can directly see how much more could be imported
- **No complex feature matrix**: Simple limit vs. unlimited distinction
- **Scales with site size**: Sites with tons of historical content see bigger numbers and might upgrade; small sites won't care (which is fine!)

### Data Constraints

We can only import what's actually stored in WordPress:
- **Posts/pages**: `post_date`, `post_modified`, `post_author` from `wp_posts` table
- **Users**: `user_registered` from `wp_users` table
- **Events available**: "Published post/page", "Updated post/page", "User registered"
- **Not available**: Login/logout history, plugin activations, settings changes, etc. (these only exist after plugin installation)

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
  - **Current**: Manual import via Experimental Features page (IMPLEMENTED)
  - **Planned**: Auto-import on activation (60 days of data) + manual import for older data
  - **Rationale**:
    - Auto-import 60 days aligns with free version's default retention policy
    - Free users: Won't waste resources importing data that gets auto-deleted
    - Premium users: Can manually import older data to match extended retention
    - Prevents empty state on fresh installs while respecting performance limits

- ✅ **How far back should we import data?**
  - **Auto-import (planned)**: 60 days of data on activation (matches default retention)
  - **Manual import (current)**: Import ALL data by default (no limit)
  - **Flexibility**: Optional limit checkbox available for users who want to restrict
  - **Use cases**:
    - Free users: Auto-import handles their needs (60 days)
    - Premium users: Manual import for older data (365+ days or unlimited)

- ✅ **Should users be able to configure what gets imported?**
  - **Answer**: Yes. Users can select specific post types and choose whether to import users. Options are in expandable "Import Options" details element.

- 🚨 **How to handle large sites with thousands of posts?**
  - **Current**: No hard limit - imports all data when limit checkbox is unchecked
  - **Risks**: Memory issues, timeouts, slow queries on very large imports
  - **Future**: Requires batch processing/AJAX with progress indicator for very large sites
  - **See**: "Large Dataset Limitations" section below for full analysis

- ✅ **Should this be a one-time import or repeatable?**
  - **Answer**: Repeatable. Users can safely run it multiple times - duplicate detection automatically skips already-imported items.

- ✅ **What initiator should imported events have?**
  - **Posts**: Use `post_author` as initiator (WP_USER with full user context: _user_id, _user_login, _user_email)
  - **Users**: Use OTHER initiator without any user ID (we don't know who created user accounts)
  - **Rationale**: Be truthful about what we know (post authors) vs. what we don't know (who created users)

- ✅ **How to show users that an event is imported?**
  - **IMPLEMENTED**: Simple meta text indicator
  - **PHP Implementation** (`loggers/class-logger.php:535-556`):
    - Method: `get_log_row_header_imported_event_output()`
    - Shows "Imported from existing data" as plain text in gray meta text (same style as "Using plugin xyz")
    - No link, no icon - simple and clean
  - **React Implementation**:
    - Component: `src/components/EventImportedIndicator.jsx`
    - Integrated into `src/components/EventHeader.jsx`
    - Shows "Imported from existing data" as plain text
    - Reads from `event.imported` field provided by REST API
  - **REST API** (`inc/class-wp-rest-events-controller.php`):
    - Added `imported` field to event schema (line 579-582)
    - Field included in `prepare_item_for_response()` (line 953-955)
    - Returns boolean: `true` if `_imported_event` context key exists, `false` otherwise
    - Simply checks for key existence using `isset()` - any truthy value works
    - Field requested via `_fields` parameter (`src/functions.js:68`)
  - **Detection**: Checks for existence of `_imported_event` context key (set to '1' by importer)
  - **UX**: Subtle, informative, doesn't overwhelm when many imported events visible

- ✅ **Should we import post revisions?**
  - **Answer**: No, revisions are NOT imported.
  - **Reasoning**:
    - Already importing creation date and last modification date (covers timeline)
    - Missing critical context: don't know WHAT changed in each revision
    - Would create low-value noise: "Updated post X" repeatedly without change details
    - Data bloat: Popular posts can have 50+ revisions
    - Simple History tracks revisions properly going forward with full change tracking
  - **What we DO import**: `post_status` includes `publish`, `draft`, `pending`, `private`
  - **Implementation**: `inc/class-existing-data-importer.php:92` - only queries actual posts, not revisions

### Auto-Import on Activation (Planned Feature)

**Overview**:
Automatically import limited historical data when the plugin is first activated, providing an immediate populated history log for new users. This follows the freemium strategy outlined above.

**Design Decisions**:

1. **Core (Free) Import Limits** ✅ **DECIDED**:
   - **Posts/Pages**: Last 20-50 items (just enough to show activity)
   - **Users**: All users (typically small number, feels complete)
   - **Strategy**: Simple count-based limit, newest items first
   - **Rationale**:
     - Solves empty state problem without overwhelming system
     - Fast and predictable performance on all site sizes
     - Creates natural upgrade opportunity (show remaining count)
     - Aligns with "nice to have" UX polish positioning

2. **Premium vs. Free Import**:
   - **Free (auto-import)**: 20-50 posts/pages + all users (one-time, automatic on activation)
   - **Premium (manual import)**: Unlimited historical data (user-controlled, repeatable)
   - **Use case split**:
     - Free users: Auto-import solves empty state, plugin looks good immediately
     - Premium users: Can import complete site history (thousands of posts, years of data)
   - **Upgrade path**: Show remaining count after free import ("Import 2,847 more posts with Premium")

3. **Activation Hook Implementation**:
   - Register `register_activation_hook()` in main plugin file
   - Call `Existing_Data_Importer->import_all()` with limit parameters
   - **Free version**: `limit = 50` for posts, `-1` (all) for users
   - **Premium version**: `limit = -1` (unlimited) for both
   - Store activation flag in options to prevent re-import on reactivation
   - Show admin notice with import results and upgrade prompt after activation

4. **Count-Based Limiting**:
   - Add/update `limit` parameter in `import_all()`, `import_posts()`, `import_users()`
   - **Free version**: Limit 20-50 posts/pages (configurable constant)
   - **Premium version**: No limit (-1 = unlimited)
   - Modify queries:
     - Posts: `ORDER BY post_date DESC LIMIT %d` (newest first)
     - Users: `ORDER BY user_registered DESC LIMIT %d` (all users for free, unlimited for premium)
   - Import newest items first (DESC order) to show recent activity
   - After free import: Query total count to show upgrade opportunity

5. **Performance Analysis** (Free Import - 50 Posts Max):
   - **All site sizes**: Max 50 posts + all users = ~60-100 events max ⚡ Very fast
   - **Import time**: < 1 second on most hosting
   - **Memory usage**: Minimal (< 5MB for 50 posts)
   - **Predictable**: Same performance regardless of total site size
   - **No timeout risk**: Completes quickly even on slow hosting

6. **Performance Safeguards**:
   - **Count-based limit**: Prevents timeouts, ensures predictable performance
   - **Memory protection**: `wp_raise_memory_limit('admin')` before import
   - **Timeout extension**: `set_time_limit(300)` for 5-minute max (if hosting allows)
   - **Error handling**: Log errors and show admin notice if import fails
   - **Graceful degradation**: Failed auto-import doesn't break activation
   - **Manual fallback**: Premium users can use full import via Experimental Features page

7. **User Experience**:
   - **On activation (Free)**:
     - Auto-import runs silently in background
     - Admin notice: "✓ Imported 50 recent posts and 12 users. [View History]"
     - Notice is dismissible
     - Link to full history log for verification
   - **Upgrade prompt (if more data available)**:
     - Show count of remaining posts: "Want more? Import 2,847 additional historical posts with Premium."
     - Link to premium upgrade page
     - Subtle, not pushy - only shown once after activation
   - **Premium users**:
     - Auto-import runs same way (50 posts + users)
     - Notice includes: "Want complete history? [Import All Historical Data]"
     - Link to Experimental Features page for full unlimited import
   - **Prevent duplicates**:
     - Existing duplicate detection handles re-runs
     - If user manually imports first, activation hook detects and skips

8. **Edge Cases**:
   - **Reactivation**: Don't re-import (check for option flag)
   - **Failed import**: Show error notice, allow manual retry via Experimental Features
   - **Sites with < 50 posts**: Import all available data, no upgrade prompt
   - **Sites with no data**: Skip import, no notice needed
   - **Manual import before activation**: Duplicate detection prevents duplication

**Implementation Tasks** (see Progress > To Do):
- [ ] Update `Existing_Data_Importer` class to support count-based limiting (already has limit param)
- [ ] Implement activation hook with 50-post auto-import for free version
- [ ] Add premium detection to enable unlimited import for premium users
- [ ] Add admin notice system for import results with upgrade prompt
- [ ] Query total post count after import to show "X more posts available"
- [ ] Add option flag to prevent re-import on reactivation
- [ ] Add memory/timeout protection for activation import
- [ ] Test on various site sizes (small, medium, large)
- [ ] Test upgrade prompt display logic
- [ ] Document in plugin readme and changelog

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
- [x] Implement accurate initiator logic (post_author for posts, OTHER for users)
- [x] Simplify UI with expandable "Import Options" details element
- [x] Change defaults: import all data (no limit), all post types checked, users checked
- [x] Add optional limit checkbox (unchecked by default, supports up to 10,000 items)
- [x] Add preview feature showing approximate import counts
- [x] Implement visual indicator for imported events (simple meta text label)
- [x] Add `imported` field to REST API for React frontend
- [x] Create React component for imported event indicator
- [x] Gate Experimental Features page behind experimental features setting

### In Progress
- Manual testing with different WordPress setups
- Testing edge cases (large datasets, missing data, etc.)
- User acceptance testing

### To Do (Future Enhancements)

#### Next Priority: Auto-Import on Activation with Freemium Strategy
- [ ] **Update importer for freemium limits** (`inc/class-existing-data-importer.php`):
  - [ ] Verify `limit` parameter works correctly (already exists)
  - [ ] Test with `limit = 50` for posts
  - [ ] Test with `limit = -1` for users (import all)
  - [ ] Add method to get total post count (for upgrade prompt)
  - [ ] Update docblocks to document freemium strategy
- [ ] **Implement activation hook with freemium logic** (main plugin file):
  - [ ] Register `register_activation_hook()` callback
  - [ ] Detect if premium version is active
  - [ ] Free version: Call `import_all()` with `post_limit = 50`, `user_limit = -1`
  - [ ] Premium version: Call `import_all()` with `post_limit = -1` (unlimited)
  - [ ] Add memory/timeout protection (`wp_raise_memory_limit()`, `set_time_limit(300)`)
  - [ ] Query total post count after import (for upgrade prompt)
  - [ ] Store import results + total count in transient for admin notice
  - [ ] Set option flag to prevent re-import on reactivation
  - [ ] Handle errors gracefully with try/catch
- [ ] **Add admin notice system with upgrade prompt**:
  - [ ] Create admin notice for successful import (dismissible)
  - [ ] Free: Show "✓ Imported X posts and Y users"
  - [ ] Free: If more posts available, show "Import Z more posts with Premium" link
  - [ ] Premium: Show "Want complete history? Import all data" link to Experimental Features
  - [ ] Include link to history log
  - [ ] Create admin notice for failed import with retry instructions
- [ ] **Testing**:
  - [ ] Test fresh activation on small site (< 50 posts) - should import all, no upgrade prompt
  - [ ] Test fresh activation on medium site (100-500 posts) - should import 50, show upgrade prompt
  - [ ] Test fresh activation on large site (5000+ posts) - should import 50, show large count in upgrade prompt
  - [ ] Test premium activation - verify unlimited import works
  - [ ] Test upgrade prompt display and link functionality
  - [ ] Test reactivation (should not re-import)
  - [ ] Test manual import before activation (duplicate detection)
  - [ ] Test empty sites (no posts/users)
  - [ ] Verify timeout protection works
  - [ ] Verify memory protection works

#### Other Enhancements
- [ ] 🚨 **CRITICAL**: Add memory and timeout protection for manual imports (`wp_raise_memory_limit()`, `set_time_limit()`)
- [ ] Add batch processing/AJAX for very large datasets (10,000+ items)
- [ ] Add progress indicator for import process (especially for large imports)
- [ ] Test with large datasets (10,000+ posts)
- [ ] Handle edge cases (missing authors, deleted content, etc.)
- [ ] Update documentation
- [ ] Consider adding import for other data types (comments, media)
- [x] ~~**Visual indicators for imported events**: Add UI to show which events are imported~~ - ✅ **IMPLEMENTED** (simple meta text label)
- [ ] ~~**First-run experience**: Add dismissible admin notice after activation suggesting to run import~~ - Covered by auto-import feature
- [ ] **Empty state CTA**: Show import suggestion in dashboard/log page when empty

## Implementation Details

### Architecture

The import functionality has been implemented as an **experimental feature** accessible through an admin page, rather than running automatically on activation. This approach:

- **Gated behind experimental features setting**: Only visible when "Enable experimental features" is checked in Settings → General
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
   - **Only loads if experimental features are enabled** (`Helpers::experimental_features_is_enabled()`)
   - Provides UI for triggering imports
   - Handles form submission and displays results

### Key Features

- **Simplified UI**: Clean interface with just "Import Data" button visible; advanced options in expandable "Import Options" details
- **Smart Defaults**: All post types and users checked by default; imports all data (no limit) unless limit checkbox enabled
- **Post Type Selection**: Users can choose which post types to import (all public post types checked by default)
- **Optional Limits**: Import limit disabled by default; optional checkbox to enable limit (1-10000 items per type)
- **User Import**: Optional import of user registration dates (enabled by default)
- **Historical Dates**: Uses original `post_date_gmt` and `post_modified_gmt` for accurate history
- **Accurate Initiators**:
  - Posts: Use `post_author` as initiator (WP_USER with full user context)
  - Users: Use OTHER initiator (we don't know who created user accounts)
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

**Post Import** (`inc/class-existing-data-importer.php:138-198`):
- Uses `Post_Logger->info_message('post_created', $context)` for creation events
- Uses `Post_Logger->info_message('post_updated', $context)` for modification events (if dates differ)
- **Initiator Logic**:
  - Uses `post_author` from WordPress post object
  - If author exists: Sets `_initiator` to `WP_USER` with full user context (`_user_id`, `_user_login`, `_user_email`)
  - If author doesn't exist: Falls back to `OTHER` initiator
- Context includes: `post_id`, `post_type`, `post_title`, `_date`, `_initiator`, `_imported_event`, plus user context if available

**User Import** (`inc/class-existing-data-importer.php:258-270`):
- Uses `User_Logger->info_message('user_created', $context)`
- **Initiator Logic**: Always uses `OTHER` (we don't know who created user accounts)
- No `_user_id`, `_user_login`, or `_user_email` set as initiator context
- **Only stores immutable data** to avoid false historical records:
  - `created_user_id` - User ID (immutable)
  - `created_user_login` - Username (immutable in WordPress)
  - `_date`, `_initiator`, `_imported_event`
- **Does NOT store** (these can change over time):
  - `created_user_email` - Can be changed by user/admin
  - `created_user_first_name`, `created_user_last_name` - Can be changed
  - `created_user_url` - Can be changed
  - `created_user_role` - Can be changed (promotions/demotions)
- **Rationale**: Storing current role/email with a backdated timestamp creates false historical records (e.g., claiming someone was "Administrator" in 2020 when they may have been promoted later)
- Displays as: "Created user {login}" by "Other" (with current data fetched from DB if fields missing)

**Imported Event Marker**:
- All imported events include `_imported_event => true` in their context
- This allows programmatic identification and filtering of imported events
- Follows existing Simple History pattern (similar to `_xmlrpc_request`, `_rest_api_request`)
- Enables future features like filtering UI, duplicate detection, and analytics
- No GUI changes required - stored silently in database context table

### Duplicate Prevention

**Implementation** (`inc/class-existing-data-importer.php:307-388`):

The importer uses batch SQL queries to detect ALL already-logged events before processing (both imported and naturally logged):

**Enhanced Detection**:
- Detects BOTH previously imported events AND naturally logged events
- Prevents duplicates if plugin was active and logging before import runs
- Uses LEFT JOIN to determine if event was imported or naturally logged
- Reports skip counts separately for each type

**For Posts**:
- Queries for all post IDs in the current batch
- Checks separately for `post_created` and `post_updated` events
- Smart skip logic: only skips if ALL applicable events exist
- Example: If a post was never modified, only checks for `post_created` event
- Distinguishes between imported vs naturally logged events

**For Users**:
- Queries for all user IDs in the current batch
- Checks for existing `user_created` events (imported OR naturally logged)
- Distinguishes between imported vs naturally logged events

**SQL Approach**:
```sql
-- Returns post_id and whether it was imported (1) or naturally logged (0)
SELECT DISTINCT
    c1.value as post_id,
    MAX(CASE WHEN c2.key = '_imported_event' THEN 1 ELSE 0 END) as is_imported
FROM contexts c1
LEFT JOIN contexts c2 ON c1.history_id = c2.history_id AND c2.key = '_imported_event'
INNER JOIN contexts c3 ON c1.history_id = c3.history_id
WHERE c1.key = 'post_id' AND c3.key = '_message_key'
GROUP BY c1.value
```

**Performance**:
- Only 2-4 SQL queries total per import (posts: 2 queries for created/updated, users: 1 query)
- Uses batch checking with IN clauses instead of N individual queries
- LEFT JOIN efficiently detects imported vs naturally logged events

**User Experience**:
- First import: "Imported 100 posts and 20 users."
- Already imported: "Imported 0 posts and 0 users (skipped: 80 posts already imported, 20 posts already in history)."
- Mixed scenario: "Imported 50 posts and 10 users (skipped: 30 posts already imported, 20 posts already in history, 5 users already imported, 5 users already in history)."
- Page description: "You can run this import multiple times. Items already in the history log (imported or naturally logged) will be automatically skipped to prevent duplicates."

### Debug Tracking

The importer returns detailed results including:
- `posts_imported`: Total count of posts imported
- `users_imported`: Total count of users imported
- `posts_skipped_imported`: Count of posts skipped (already imported)
- `posts_skipped_logged`: Count of posts skipped (already in history/naturally logged)
- `users_skipped_imported`: Count of users skipped (already imported)
- `users_skipped_logged`: Count of users skipped (already in history/naturally logged)
- `posts_details`: Array with full details of each imported post (ID, title, type, status, dates, events logged)
- `users_details`: Array with full details of each imported user (ID, login, email, registration date, roles)
- `skipped_details`: Array with details of skipped items (type, ID, title/login, reason: 'already_imported' or 'already_logged')
- `errors`: Array of any errors encountered

Debug output is logged via `error_log()` in `inc/services/class-experimental-features-page.php:345-347`

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

2. **Large Dataset Limitations** ⚠️ **PERFORMANCE CONSIDERATIONS**

   ### Default: Import All Data (No Limit)

   **Current Behavior**:

   By default, the importer fetches ALL posts/users (no limit). This provides complete historical coverage but may have performance implications on very large sites:

   ```php
   // inc/class-existing-data-importer.php:90-96
   $args = [
       'posts_per_page' => $limit,  // -1 (all) by default, or user-specified limit
       'orderby' => 'date',
       'order' => 'ASC',  // Oldest first
   ];
   ```

   **What Happens on a Site with 10,000 Posts:**

   1. **Default Import (no limit)**:
      - Fetches ALL 10,000 posts
      - Attempts to import all ⚠️
      - Risk: May hit memory/timeout limits
      - Result: Either completes successfully or fails mid-import

   2. **With Optional Limit (e.g., limit=1000)**:
      - Fetches only oldest 1000 posts
      - Imports 1000 posts ✅
      - Remaining 9000 posts not imported ⚠️
      - No pagination/offset to import the rest

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

   **Option 1: Memory & Timeout Protection** (High Priority):
   - Add `wp_raise_memory_limit( 'admin' )` before import
   - Add `set_time_limit( 300 )` for extended timeout (if hosting allows)
   - Reduce result detail storage (only keep counts, not full arrays)
   - Show warning in UI for sites with 10,000+ items

   **Option 2: AJAX/Background Processing** (Best for very large sites):
   - AJAX chunked processing with progress bar (1000 items per batch)
   - Real-time progress updates in UI
   - Handles timeouts gracefully
   - Better UX for large imports

   **Option 3: WP-Cron Scheduled Batches**:
   - Background processing via WP-Cron
   - Email notification when complete
   - No timeout issues
   - More complex implementation

## Related Code

**Backend (PHP)**:
- **Importer**: `inc/class-existing-data-importer.php` (main import logic, preview counts)
- **Service**: `inc/services/class-experimental-features-page.php` (Experimental Features admin page)
- **Post Logger**: `loggers/class-post-logger.php` (used for logging post events)
- **User Logger**: `loggers/class-user-logger.php:47-50` (user_created message definition)
- **Logger Base**: `loggers/class-logger.php:535-556` (imported event indicator method)
- **REST API**: `inc/class-wp-rest-events-controller.php:579-582,926-929` (imported field in schema and response)
- **Menu System**: `inc/class-menu-manager.php`, `inc/class-menu-page.php`

**Frontend (React)**:
- **Imported Indicator Component**: `src/components/EventImportedIndicator.jsx`
- **Event Header**: `src/components/EventHeader.jsx:31` (integrates imported indicator)
- **API Query Params**: `src/functions.js:68` (includes imported field in _fields parameter)

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
   - [ ] Test with 100+ posts (no limit)
   - [ ] Test with 1,000+ posts (no limit)
   - [ ] Test with 10,000+ posts (no limit) - verify completion or graceful failure
   - [ ] Verify no timeouts occur with moderate datasets (1000-2000 posts)
   - [ ] Check performance impact on various dataset sizes
   - [ ] **Test optional limit**: Enable limit checkbox with 1000 limit on site with 5000+ posts
   - [ ] **Test duplicate on large dataset**: Run import twice, verify second run skips all items
   - [ ] **Test memory usage**: Monitor PHP memory consumption during large imports
   - [ ] **Test timeout handling**: Monitor execution time on large imports
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
   - [ ] **Test large datasets**: Import on site with 5000+ posts and monitor performance
   - [ ] **Test memory limits**: Monitor PHP memory usage during large imports
   - [ ] **Test timeout limits**: Verify completion or graceful failure on large imports
   - [ ] **Test optional limit**: Verify limit checkbox works correctly (enable/disable)
   - [ ] Test with custom post types from popular plugins
   - [ ] Test error handling (database failures, missing loggers, etc.)
   - [ ] Test with sites that have deleted post authors

2. **User Acceptance**:
   - [ ] Review UI/UX of experimental features page
   - [ ] Confirm messaging is clear for end users
   - [x] ~~Consider adding warning about duplicate imports~~ - Duplicate prevention implemented

3. **Documentation**:
   - [ ] Update main plugin readme if needed
   - [ ] Consider adding inline help text on the experimental page
   - [ ] Document in changelog

### Future Enhancements (Post-Merge)

1. **Performance Improvements** ⚠️ **HIGH PRIORITY** (See "Large Dataset Limitations"):
   - **Critical**: Add memory and timeout protection (`wp_raise_memory_limit()`, `set_time_limit()`)
   - **Important**: Add warning in UI for very large sites (10,000+ items detected)
   - Batch processing with AJAX for very large datasets (10,000+ items)
   - Progress indicator during import (especially for 1000+ items)
   - Background processing option (WP-Cron for very large sites)
   - Reduce memory footprint by limiting detail array storage

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
