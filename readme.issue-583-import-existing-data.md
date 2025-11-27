# Issue #583: Generate history based on existing WP data

**Status**: Complete
**Labels**: Experimental feature, Feature
**Project Board**: Simple History kanban (In Progress)

## Overview

When the plugin is installed it contains no history at all. This is a sad and boring empty state. Can we after install pull in any data from the WordPress installation and populate the log?

The information available in WordPress for historical events are very limited, but perhaps we can pull in post and page changes.

## Requirements

- Issue #584 needs to be implemented first (or events imported after a while will come up way wrong in the log)

## Todo

- [x] Add tools page with backfill information for core users and backfill functionality for premium users
- [x] Add "Backfill" tab under Tools menu with GUI for historical data import
- [x] Check where "60 days" comes from - FOUND: `Helpers::get_clear_history_interval()` (line 1010)
- [x] Phase 1: Add date filtering to `Existing_Data_Importer` (reuse `get_clear_history_interval()`)
- [x] Phase 2: Create automatic backfill service (first install only)
- [x] Phase 3: Convert core to info + upsell, create premium GUI
- [ ] How do we promote this in the best way to users using the log (perhaps for the first time, so they know there is more data to import)?
- [ ] Should we create a dedicated logger for backfill messages so log messages become translatable?
- [ ] Should use SimpleHistory_Logger?

## Progress

### Step 1: Tools Menu Architecture (Completed)

Successfully refactored the Export menu into a comprehensive "Export & Tools" menu with tabbed interface to support multiple tools including the planned Import functionality.

**Implementation Details:**

1. **Created `Tools_Menu_Dropin`** (`dropins/class-tools-menu-dropin.php`)
   - Manages the "Export & Tools" parent menu
   - Provides tabbed interface with proper navigation
   - Handles backwards compatibility redirect from old Export URLs
   - Implements location-aware tab structure:
     - When location is 'top' or 'bottom': Creates intermediate "Tools" tab with subtabs
     - When location is 'inside_dashboard' or 'inside_tools': Adds subtabs directly to Tools page

2. **Refactored `Export_Dropin`** (`dropins/class-export-dropin.php`)
   - Changed from standalone menu to subtab under Tools
   - Updated parent reference to work with both menu locations
   - Maintained all export functionality (CSV, JSON, HTML)

3. **Menu Structure:**
   - **Top/Bottom location:**
     ```
     Export & Tools (menu item)
     └── Tools (main tab with redirect)
         ├── Overview (subtab with dashboard icon)
         └── Export (subtab with download icon)
     ```
   - **Inside Dashboard/Tools location:**
     ```
     Settings (WP menu)
     └── Simple History (settings page)
         └── Export & Tools (tab)
             ├── Overview (subtab)
             └── Export (subtab)
     ```

4. **Key Features:**
   - Overview page with welcome text and list of available tools
   - Clean navigation using existing tab system (sh-PageNav and sh-SettingsTabs)
   - Dashboard icon for Overview page
   - Modular architecture - future tools can be added as separate dropins
   - Full backwards compatibility for old Export menu URLs

**Files Modified:**
- Created: `dropins/class-tools-menu-dropin.php`
- Modified: `dropins/class-export-dropin.php`
- Added icon: `css/icons/grid_view_FILL0_wght400_GRAD0_opsz48.svg` (not used, switched to dashboard icon)
- Updated: `css/icons.css` (added grid_view and verified dashboard icon exists)

**Next Steps:**
The Tools menu infrastructure is now ready to support the Import functionality. Future import tool can be implemented as a separate dropin that registers as a subtab under Tools, following the same pattern as Export.

### Step 2: Backfill Tab Implementation (Completed)

Added a new "Backfill" tab under the Tools menu that provides the GUI for generating history entries from existing WordPress data.

**Implementation Details:**

1. **Created `Import_Dropin`** (`dropins/class-import-dropin.php`)
   - Registers as subtab under Tools menu (order 3, after Overview and Export)
   - Contains complete backfill GUI with:
     - Success notices for backfill/delete operations
     - Preview section showing counts per post type and users
     - Options (post types, users, limit)
     - "Run Backfill" and "Delete Backfilled Data" buttons
   - Uses `sync_arrow_down` icon in the page title

2. **Updated `Import_Handler`** (`inc/services/class-import-handler.php`)
   - Removed GUI rendering (moved to dropin)
   - Updated redirects to use proper Tools menu tab structure (`page=simple_history_tools&selected-tab=...&selected-sub-tab=...`)

3. **Updated `Tools_Menu_Dropin`** (`dropins/class-tools-menu-dropin.php`)
   - Added Backfill to the "Available Tools" list on Overview page

4. **Updated `EventImportedIndicator.jsx`**
   - Changed indicator text from "Imported from existing data" to "Backfilled entry"

5. **Menu Structure:**
   ```
   Export & Tools (menu item)
   └── Tools (main tab with redirect)
       ├── Overview (subtab)
       ├── Export (subtab)
       └── Backfill (subtab) ← NEW
   ```

6. **UX Copy Updates:**
   - Used "Backfill" terminology instead of "Import" to clarify that it generates history from existing WordPress data (not external file imports)
   - Clear descriptions explaining the feature scans posts, pages, and users to create log entries
   - Event indicator shows "Backfilled entry" for backfilled events

**Files Created:**
- `dropins/class-import-dropin.php`
- `css/icons/sync_arrow_down_FILL0_wght400_GRAD0_opsz48.svg`

**Files Modified:**
- `inc/services/class-import-handler.php` (removed GUI, updated redirects)
- `dropins/class-tools-menu-dropin.php` (added Backfill to tools list)
- `src/components/EventImportedIndicator.jsx` (updated indicator text)
- `css/icons.css` (added sync_arrow_down icon class)

---

### Step 3: All Phases Implementation (Completed)

Successfully implemented all three phases of the backfill feature.

#### Phase 1: Date Filtering

**Modified:** `inc/class-existing-data-importer.php`
- Added `$days_back` property for configurable date range
- Updated `import_all()` to accept `days_back` option
- Added `date_query` filtering to `import_posts()` using `modified_after` date
- Added `date_query` filtering to `import_users()` using `user_registered` date
- Uses `Helpers::get_clear_history_interval()` as default (60 days)
- Added `get_backfilled_events_count()` method to count events with `_backfilled_event` context key

#### Phase 2: Automatic Backfill Service

**Created:** `inc/services/class-auto-backfill-service.php`
- Schedules cron 60 seconds after first install
- Stores status in `simple_history_auto_backfill_status` option
- Backfills ALL public post types + attachments (dynamically via `get_post_types()`)
- Uses configurable limit (default 100 per type)
- Logs completion to Simple History
- Constants:
  - `CRON_HOOK = 'simple_history/auto_backfill'`
  - `STATUS_OPTION = 'simple_history_auto_backfill_status'`
  - `DEFAULT_LIMIT = 100`

**Modified:** `inc/services/class-setup-database.php`
- Added `Auto_Backfill_Service::schedule_auto_backfill()` call in `setup_new_to_version_1()`
- Only runs on first install (when db_version === 0)

#### Phase 3: Core Info + Premium GUI

**Modified:** `dropins/class-import-dropin.php` (Core)
- Shows auto-backfill status (completed date, posts/users imported, days range)
- Shows "scheduled" message only if cron is actually scheduled
- Displays premium upsell for manual backfill via `Helpers::get_premium_feature_teaser()`
- Shows "Delete backfilled data" section with:
  - Count of backfilled events in the log
  - Explanation about `_backfilled_event` context key
  - Delete button (only shown if count > 0)

**Modified:** `inc/services/class-import-handler.php`
- Added filter check: `apply_filters('simple_history/backfill/can_run_manual_import', false)`
- Manual import requires premium (filter returns true)
- Delete functionality available to all admin users

**Created:** `simple-history-premium/inc/modules/class-backfill-module.php` (Premium)
- Enables manual import via filter: `add_filter('simple_history/backfill/can_run_manual_import', '__return_true')`
- Replaces core menu item with full GUI
- Full backfill options: post type selection, user import, limits
- Preview counts per post type
- "Run Backfill" and "Delete Backfilled Data" buttons
- Shows backfilled events count in delete section

**Modified:** `simple-history-premium/inc/class-extended-settings.php`
- Added `Modules\Backfill_Module::class` to modules array

#### New Filter Hooks

- `simple_history/backfill/can_run_manual_import` (bool) - Enable manual backfill (default: false, premium enables)
- `simple_history/auto_backfill/limit` (int) - Auto-backfill limit per type (default: 100)
- `simple_history/auto_backfill/post_types` (array) - Post types to backfill (default: all public + attachment)

---

### Step 4: Dev Mode Features (Completed)

Added developer tools for testing backfill functionality, only visible when dev mode is enabled.

#### Delete Backfilled Data (Dev Mode Only)

- Shows count of backfilled events in the log
- Explains how events are identified (`_backfilled_event` context key)
- Delete button with confirmation dialog
- Handler validates dev mode before processing

#### Re-run Automatic Backfill (Dev Mode Only)

- Resets the auto-backfill status option
- Schedules cron to run in 60 seconds
- Shows expandable preview table with:
  - Items available per post type (within date range)
  - Items already logged (would be skipped)
  - Items that would be imported (net new)
  - Total count in summary line
- Uses `<details>` element for collapsible preview
- Table uses WordPress `striped` class

**Modified:** `inc/services/class-import-handler.php`
- Added `RERUN_ACTION_NAME` constant
- Added `handle_rerun()` method
- Validates dev mode for both delete and re-run actions

**Modified:** `inc/class-existing-data-importer.php`
- Added `get_auto_backfill_preview()` method
- Returns detailed counts per post type and users
- Accounts for already logged items

---

### Step 5: Code Cleanup & UI Polish (Completed)

#### Context Key Standardization

- Renamed context key from `_imported_event` to `_backfilled_event`
- Created constant `Existing_Data_Importer::BACKFILLED_CONTEXT_KEY` for consistency
- Updated all references across codebase to use the constant
- REST API field renamed from `imported` to `backfilled`
- React component renamed from `EventImportedIndicator` to `EventBackfilledIndicator`

#### CSS Classes & Style Cleanup

Replaced all inline styles with CSS classes following SuitCSS naming convention:

**New CSS classes added:**
- `.sh-StatusBox` - Status messages with colored left border
- `.sh-StatusBox--success` / `--warning` / `--error` / `--info` - Color variants
- `.sh-StatusBox-deletedInfo` - Deletion info text
- `.sh-DevToolsBox` - Developer tools container
- `.sh-DevToolsBox-heading` / `-description` / `-section` / `-warning`
- `.sh-PreviewDetails` - Collapsible preview sections
- `.sh-CheckboxLabel` / `--spaced` - Form checkbox labels
- `.sh-textRight` - Utility class for right-aligned table cells
- `.sh-SettingsCard-title` - Card title (shared with `.sh-SettingsPage-settingsSection-title`)

**Updated skill:** `.claude/skills/code-quality/css-standards.md`
- Added section on CSS classes vs inline styles
- Documents when inline styles are acceptable
- Provides conversion process for inline styles

#### UI Improvements

**Backfill page layout:**
- Wrapped sections in `.sh-SettingsCard` components
- Added `.sh-SettingsCard-title` to section headings
- Improved copy: mentions 60-day and 100-item limits in auto-backfill description
- Added note: "Need older content? Use Manual Backfill to import beyond these limits."

**Premium teaser:**
- Now uses `Helpers::get_premium_feature_teaser()` helper function
- Title: "Go beyond 60 days"
- Feature list highlights key benefits
- Helper function updated to support array of features (renders as bullet list)

**Premium module:**
- Added persistent status storage for manual backfill results (`simple_history_manual_backfill_status` option)
- Shows "Last manual backfill" status box with posts/users imported and date range
- Removed all inline styles, now uses CSS classes

#### Attachment Backfill Improvements

- Added missing context fields for backfilled attachments:
  - `post_type` - The post type of the attachment
  - `attachment_mime` - MIME type of the attachment
- These fields enable proper thumbnail display and filtering

---

### WP-CLI Commands

```bash
# View auto-backfill status
wp option get simple_history_auto_backfill_status --format=json

# Check if backfill cron is scheduled
wp cron event list | grep backfill

# Manually trigger the backfill cron (if scheduled)
wp cron event run simple_history/auto_backfill

# Delete status to allow re-run
wp option delete simple_history_auto_backfill_status
```

---

## Implementation Plan (Reference)

### Phase 1: Add Date Filtering to Core

**Use existing purge interval - NO new constants!**

**Modify:** `inc/class-existing-data-importer.php`

1. Update `import_all()` signature - add `days_back` parameter (default: null)
2. Update `import_posts()` - add `date_query` filtering using `days_back`
3. Update `import_users()` - filter by `user_registered` date
4. Use `Date_Helper::get_last_n_days_start_timestamp()` for cutoff
5. Default to `Helpers::get_clear_history_interval()` when `days_back` is null

**Result:** Backfill and purge use SAME 60-day interval via `simple_history/db_purge_days_interval` filter

---

### Phase 2: Automatic Backfill on First Install Only

**Core plugin (free) - automatic for all users**

**Create:** `inc/services/class-auto-backfill-service.php`
- Hook into `simple_history/auto_backfill` cron event
- Check `simple_history_auto_backfill_status` option to prevent re-runs
- Run backfill with conservative limits: 100 posts + 100 pages
- Use `Helpers::get_clear_history_interval()` for date range
- Log completion/errors to Simple History

**Modify:** `inc/services/class-setup-database.php`
- Add scheduling to `setup_new_to_version_1()` method (runs ONLY on first install when db_version === 0)
- Add after `update_db_to_version(1)`:
  ```php
  if (!wp_next_scheduled('simple_history/auto_backfill')) {
      wp_schedule_single_event(time() + 60, 'simple_history/auto_backfill');
  }
  ```
- **DO NOT create setup_version_7_to_version_8()** - that would run on upgrades!

**Register:** Add `Auto_Backfill_Service` to services array

---

### Phase 3: Premium-Only Manual Backfill GUI

#### A. Core Changes (WordPress.org Compliant)

**Modify:** `dropins/class-import-dropin.php`
1. Remove all functional GUI (checkboxes, preview, buttons)
2. Show auto-backfill status from option
3. Display: when it ran, items imported, retention days used
4. Add premium upsell using `Helpers::get_premium_feature_teaser()`

**Modify:** `inc/services/class-import-handler.php`
1. Add filter check: `apply_filters('simple_history/backfill/can_run_manual_import', false)`
2. Redirect with error if false
3. Keep ALL processing logic in core

#### B. Premium Changes (Separate Repository)

**Location:** `/Users/bonny/Projects/Personal/simple-history-add-ons/simple-history-premium/`

**Create:** `inc/modules/class-backfill-module.php`
```php
class Backfill_Module extends Module {
    public function loaded() {
        // Enable manual backfill
        add_filter('simple_history/backfill/can_run_manual_import', '__return_true');

        // Register premium dropin
        add_action('simple_history/add_custom_dropin', [$this, 'register_dropin']);
    }

    public function register_dropin($simple_history) {
        $simple_history->register_dropin(
            \Simple_History_Premium\Dropins\Import_Premium_Dropin::class
        );
    }
}
```

**Create:** `inc/dropins/class-import-premium-dropin.php`
- Copy full GUI from current core `Import_Dropin`
- Post type checkboxes, user checkbox, limit dropdown
- Preview section with counts
- "Run Backfill" and "Delete Backfilled Data" buttons
- Uses core `Import_Handler` for processing

**Modify:** `inc/class-extended-settings.php`
- Add `'Backfill_Module'` to modules array

---

### Filter Hooks

**Reuse existing:**
- `simple_history/db_purge_days_interval` (int) - Controls BOTH purge AND backfill (default: 60)

**New filters:**
- `simple_history/backfill/can_run_manual_import` (bool) - Enable manual backfill (default: false)
- `simple_history/auto_backfill/limit` (int) - Auto-backfill limit per type (default: 100)
- `simple_history/auto_backfill/post_types` (array) - Post types to backfill (default: ['post', 'page'])

---

### Files Summary

**Core Repository:**
- ✏️ `inc/class-existing-data-importer.php`
- ✏️ `dropins/class-import-dropin.php`
- ✏️ `inc/services/class-import-handler.php`
- ✏️ `inc/services/class-setup-database.php`
- ➕ `inc/services/class-auto-backfill-service.php` (NEW)
- ✏️ Service registration

**Premium Repository:**
- ➕ `inc/modules/class-backfill-module.php` (NEW)
- ➕ `inc/dropins/class-import-premium-dropin.php` (NEW)
- ✏️ `inc/class-extended-settings.php`

---

### Step 6: Premium Backfill Form UX Improvements (Completed)

Simplified and improved the manual backfill form in the premium module for better usability.

#### Form Simplifications

**Removed unnecessary options:**
- Removed "Limit to X items per type" - users want to import everything
- Removed "Select all / Deselect all" links - most sites have few post types
- Removed expandable `<details>` element - form is clean enough to always show

**Improved layout:**
- Post types displayed in 2-column grid (was auto-fill)
- Each post type shows count in parentheses
- Users checkbox shows count
- Clean card design with white background and subtle border

**Flexible date range:**
- Changed from fixed dropdown to flexible input: `Last [30] [days/months/years]`
- Users can enter any number with any unit
- Default is retention setting (or 90 days if "forever")
- Handles "keep forever" retention by requiring explicit date range

#### Files Modified

**Core (`WordPress-Simple-History`):**
- `css/styles.css` - Added CSS classes:
  - `.sh-CheckboxGrid` - 2-column grid for checkboxes
  - `.sh-DateRangeInput` - Inline date range input styling
  - `.sh-BackfillForm` - Card-style form container
  - `.sh-BackfillForm-title` - Form title styling
  - `.sh-BackfillForm-table` - Compact table styling
- `inc/services/class-import-handler.php` - Updated to handle `date_range_value` + `date_range_unit` inputs

**Premium (`simple-history-premium`):**
- `inc/modules/class-backfill-module.php` - Complete form redesign:
  - Removed limit options
  - Removed select all/deselect all
  - Removed expandable details
  - Added flexible date range input
  - Added card-style form layout

#### Form Structure

```
┌─────────────────────────────────────────────┐
│ 299 items available to import               │
│                                             │
│ Post Types    ☑ Posts (26)    ☑ Pages (110) │
│               ☑ Media (127)   ☑ Products (7)│
│                                             │
│ Users         ☑ Include users (24)          │
│                                             │
│ Date range    Last [60] [days ▼]            │
│               Your retention setting is 60  │
│               days.                         │
│                                             │
│ [Run Backfill]                              │
└─────────────────────────────────────────────┘
```

---

### Step 7: Preview Accuracy & Log Message Improvements (Completed)

Fixed the backfill preview count to accurately match the actual import results.

#### Preview Count Fix

**Problem:** Preview showed higher event counts than actual import (e.g., 42 predicted vs 28 actual).

**Root causes identified and fixed:**

1. **GMT date validation** - Actual import handles invalid dates ('0000-00-00 00:00:00') by converting from local date. Preview now does the same.

2. **Update detection logic** - Changed from timestamp with 60-second threshold to simple string comparison (`$post_date_gmt !== $post_modified_gmt`) to match actual import.

3. **Logger availability checks** - Added checks for SimplePostLogger, SimpleMediaLogger, and SimpleUserLogger availability in preview to match actual import behavior.

4. **days_back handling** - Fixed cutoff date check to treat `days_back=0` the same as null (all time).

#### UI Label Updates

Changed terminology from "items" to "events" throughout the UI:
- "X items would be imported" → "X events would be created"
- "Would import" column → "Events to create" column
- "Post events created" instead of "Posts imported"

#### Log Message Improvements

Improved backfill completion log messages for better grammar and active voice:

**Before:**
- `Automatic backfill completed: created 34 post events and 1 user events`

**After:**
- `Automatic backfill created 34 post events and 1 user event`

**Changes:**
- More active voice ("created" instead of "completed: created")
- Proper singular/plural using `_n()` for translation support
- "1 user event" instead of "1 user events"

#### Files Modified

- `inc/class-existing-data-importer.php` - Fixed preview logic to match import logic
- `dropins/class-import-dropin.php` - Updated labels to show "events"
- `inc/services/class-auto-backfill-service.php` - Improved log message with proper grammar
- `inc/services/class-import-handler.php` - Improved log message with proper grammar

---

### Testing Commands

```bash
# Fresh install test
docker compose run --rm wpcli_mariadb cron event run simple_history/auto_backfill
docker compose run --rm wpcli_mariadb simple-history list --count=100
docker compose run --rm wpcli_mariadb option get simple_history_auto_backfill_status

# Check scheduled events
docker compose run --rm wpcli_mariadb cron event list

# Verify db version
docker compose run --rm wpcli_mariadb option get simple_history_db_version
```
